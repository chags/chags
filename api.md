# API mobile de ponto

## Objetivo

Disponibilizar uma API REST versionada para o aplicativo Flutter registrar e
consultar pontos e solicitar ajustes. A API reutilizará os mesmos serviços e
regras de negócio da aplicação web; não haverá uma segunda implementação das
regras de jornada.

Nesta fase será construído **somente o backend da API**, seus contratos Swagger,
integrações, migrations e testes. O aplicativo Flutter, suas telas, WebView e
capturas de câmera ou localização não fazem parte desta entrega.

Escopo inicial:

- autenticação por JWT;
- documentação interativa Swagger/OpenAPI;
- consulta do usuário autenticado;
- consulta do estado do ponto do dia;
- registro de ponto usando a hora oficial do servidor;
- consulta do cartão por mês;
- criação e consulta de solicitações de ajuste;
- cadastro de telefone no formato internacional do WhatsApp;
- liberação do aplicativo por código de seis dígitos enviado via WhatsApp;
- renovação e revogação do token.

Ficam fora da primeira versão: aprovação pelo gestor, configuração de jornadas,
cadastro de feriados, administração de usuários, validação por geolocalização e
biometria facial. A API será preparada para incorporar geolocalização e
biometria em uma versão futura sem quebrar o contrato inicial do aplicativo.

## Princípios obrigatórios

1. Todas as rotas ficarão sob `/api/v1`.
2. O aplicativo nunca enviará o horário efetivo da batida. O backend usará o
   relógio do servidor em `America/Sao_Paulo`.
3. A API não confiará no tipo de batida enviado pelo Flutter. O próximo tipo
   será calculado pelo backend, assim como já ocorre no sistema web.
4. Apenas usuários com `tracks_time = true` poderão usar os endpoints de
   ponto, independentemente do papel.
5. Papéis e permissões continuarão sendo controlados por
   `spatie/laravel-permission`.
6. O registro externo terá `source = mobile`, IP e usuário responsável.
7. Ajustes manuais sempre nascerão com status `pending`.
8. A resposta usará JSON e datas ISO 8601. Horários locais simples usarão
   `HH:mm`.
9. A API será idempotente onde houver risco de repetição por rede móvel.
10. Geolocalização e biometria facial serão opcionais até que a empresa habilite
    essas políticas. A ausência desses dados não poderá impedir a batida na
    primeira versão.
11. O código de WhatsApp será uma verificação adicional para liberar o app, não
    substituirá silenciosamente a identidade e as permissões do usuário.

## Componentes previstos

- Autenticação JWT oficial do projeto: `tymon/jwt-auth`.
- Swagger/OpenAPI oficial do projeto: `DarkaOnLine/L5-Swagger`, repositório
  `git@github.com:DarkaOnLine/L5-Swagger.git` e pacote Composer
  `darkaonline/l5-swagger`.
- `routes/api.php` para as rotas versionadas.
- Controllers em `App\\Http\\Controllers\\Api\\V1`.
- Form Requests próprios da API.
- API Resources para padronizar as respostas.
- Reutilização de `TimePunchDecisionService`, `TimeCardService`,
  `WorkScheduleResolver` e das validações de ajuste existentes.

Usaremos a linha 2.x de `tymon/jwt-auth`, compatível com os componentes
Illuminate 13. O pacote JWT não deve criar papéis, permissões ou uma tabela de
usuários paralela.

## Autenticação JWT

A autenticação será implementada com
[`tymon/jwt-auth`](https://github.com/tymondesigns/jwt-auth). A instalação e a
configuração deverão ser executadas dentro do container `chags-app`:

```bash
docker exec chags-app composer require tymon/jwt-auth
docker exec chags-app php artisan vendor:publish --provider="Tymon\\JWTAuth\\Providers\\LaravelServiceProvider"
docker exec chags-app php artisan jwt:secret
```

O comando `jwt:secret` preencherá `JWT_SECRET` no ambiente. Cada ambiente deverá
ter seu próprio segredo, que não poderá ser versionado nem reutilizado. A
configuração publicada ficará em `config/jwt.php`.

O model `User` implementará `Tymon\\JWTAuth\\Contracts\\JWTSubject`, fornecendo
o identificador JWT e claims adicionais mínimos. Será criado um guard `api` com
driver `jwt` em `config/auth.php`. Isso será separado do guard `web`, preservando
o login local em desenvolvimento, o WorkOS em produção e as permissões Spatie
existentes no guard `web`.

### Fluxo

1. No fluxo principal do app, Flutter valida telefone e código de WhatsApp.
2. No primeiro acesso, recebe um JWT limitado até concluir o FACEIO.
3. Após a ativação inicial, ou imediatamente nos acessos posteriores, recebe o
   JWT liberado.
4. O Flutter envia `Authorization: Bearer {access_token}`.
5. Ao expirar, usa `POST /api/v1/auth/refresh`.
6. No logout, `POST /api/v1/auth/logout` invalida o token no blacklist do JWT.

O endpoint de e-mail e senha poderá ser mantido como fluxo alternativo ou para
testes administrativos, mas não será a entrada principal do aplicativo.

O contrato externo poderá apresentar `access_token` e `refresh_token`, mas a
implementação precisa respeitar o mecanismo efetivamente oferecido pelo
`tymon/jwt-auth`: renovação rotativa do JWT e blacklist. Não será criado um
refresh token persistente paralelo sem necessidade comprovada.

Produção continua usando WorkOS para a interface web. A API mobile terá fluxo
próprio de token e não desabilitará o WorkOS.

### Tempos sugeridos

- access token: 15 minutos;
- refresh token: 30 dias;
- tolerância de relógio: no máximo 30 segundos;
- rotação obrigatória do refresh token;
- blacklist habilitada para logout e renovação;
- revogação lógica de todos os tokens após troca de senha ou bloqueio do
  usuário, usando uma versão de token ou marco temporal validado nos claims.

### Login

`POST /api/v1/auth/login`

Requisição:

```json
{
  "email": "colaborador@empresa.com",
  "password": "senha",
  "device_name": "Samsung A55"
}
```

Resposta `200`:

```json
{
  "data": {
    "access_token": "jwt",
    "token_type": "Bearer",
    "expires_in": 900,
    "refresh_token": "token-de-renovacao",
    "refresh_expires_in": 2592000,
    "user": {
      "id": 8,
      "name": "Ana Botafogo",
      "email": "colaborador@empresa.com",
      "tracks_time": true
    }
  }
}
```

Não revelar se um e-mail existe. Credenciais inválidas sempre retornam a mesma
mensagem.

## Telefone no formato WhatsApp

O usuário terá um número próprio para WhatsApp armazenado em formato E.164, por
exemplo:

```text
+5511999999999
```

Regras:

- iniciar com `+` e código do país;
- conter somente algarismos depois do `+`;
- para Brasil, usar `+55`, DDD e número com nove dígitos quando aplicável;
- salvar a forma normalizada, nunca a máscara visual;
- exibir mascarado, por exemplo `+55 11 *****-9999`;
- número deve ser único quando usado para liberação do app;
- registrar quando e por quem o número foi confirmado ou alterado.

Campos previstos:

- `whatsapp_phone`: número normalizado em E.164;
- `whatsapp_phone_verified_at`: confirmação do número;
- `whatsapp_phone_changed_at`: última alteração;
- `app_unlock_required`: indica se o segundo fator é exigido.

A alteração do telefone será feita no sistema administrativo, protegida por
permissão Spatie e auditada. No endpoint de entrada, o usuário informa um número
para localização do cadastro, mas não consegue alterar o telefone armazenado.

## Liberação do app por código no WhatsApp

O código de seis dígitos será a forma inicial de localizar o usuário e liberar o
aplicativo. O endpoint será público e receberá o telefone informado pelo usuário.
O Laravel normalizará o valor, procurará exatamente o número cadastrado e, se ele
for elegível, enviará o código para esse mesmo WhatsApp.

Fluxo:

1. Usuário digita seu telefone no aplicativo.
2. Flutter chama `POST /api/v1/app-unlock/whatsapp/request` com o telefone e a
   identificação do dispositivo.
3. Laravel normaliza o número e procura um usuário ativo com aquele WhatsApp
   confirmado.
4. Se encontrado, Laravel gera um código criptograficamente aleatório entre `000000` e
   `999999`, armazena somente seu hash e envia ao WhatsApp verificado.
5. Flutter envia código e desafio para
   `POST /api/v1/app-unlock/whatsapp/verify`.
6. Laravel valida usuário, telefone, dispositivo, hash, expiração, tentativas e
   uso único.
7. No primeiro acesso, Laravel emite um JWT limitado e exige o cadastro facial
   FACEIO antes da liberação definitiva.
8. Nos acessos posteriores, Laravel emite o JWT liberado para usar o app.
9. As rotas de ponto exigem JWT válido e sessão liberada.

### Solicitar código

`POST /api/v1/app-unlock/whatsapp/request`

Requisição:

```json
{
  "phone": "+5511999999999",
  "device_id": "01K..."
}
```

Resposta `202`:

```json
{
  "message": "Se o telefone estiver disponível, enviaremos um código.",
  "data": {
    "challenge_id": "01K...",
    "expires_in": 300,
    "resend_after": 60
  }
}
```

### Validar código

`POST /api/v1/app-unlock/whatsapp/verify`

Requisição:

```json
{
  "challenge_id": "01K...",
  "device_id": "01K...",
  "code": "482731"
}
```

Resposta `200`:

```json
{
  "message": "Aplicativo liberado com sucesso.",
  "data": {
    "access_token": "novo-jwt",
    "token_type": "Bearer",
    "expires_in": 900,
    "app_unlocked": true,
    "unlock_method": "whatsapp_otp",
    "first_access": false,
    "requires_face_enrollment": false
  }
}
```

No primeiro acesso, a resposta terá `app_unlocked: false`,
`first_access: true` e `requires_face_enrollment: true`. O token retornado
permitirá somente consultar a própria conta e concluir o fluxo FACEIO; não
permitirá consultar nem registrar ponto.

Regras de segurança:

- código exatamente com seis dígitos, incluindo zeros à esquerda;
- validade de cinco minutos;
- uso único e invalidação após sucesso;
- máximo de cinco tentativas por desafio;
- intervalo mínimo de 60 segundos entre envios;
- limite por usuário, telefone, dispositivo e IP;
- normalização do telefone antes da pesquisa e do rate limit;
- novo envio invalida o código anterior;
- código armazenado somente como hash com comparação segura;
- código, telefone completo e resposta do provedor não aparecem nos logs;
- mensagem de resposta não revela se o usuário possui WhatsApp cadastrado;
- tempo e formato de resposta semelhantes para telefone existente ou inexistente;
- bloqueio temporário e auditoria após excesso de falhas;
- provedor de WhatsApp encapsulado por uma interface para permitir substituição;
- em testes, usar um transport fake e nunca enviar mensagem real.

O desafio deverá registrar `user_id` quando localizado, hash normalizado do
telefone, `device_id`, hash do código, expiração, quantidade de tentativas, data
de consumo e identificador da mensagem no provedor. Registros expirados serão
removidos por rotina agendada.

Se o WhatsApp estiver indisponível, a API deverá retornar uma falha controlada e
permitir reenvio posterior. Não haverá código fixo de contingência ou bypass em
produção.

## Vínculo e validação do dispositivo

Não coletaremos indiscriminadamente o maior número possível de informações. A
API usará um vínculo criptográfico forte como identidade principal do aparelho e
um conjunto mínimo de sinais como análise de risco. Modelo, IP ou versão do
sistema isoladamente não identificam um dispositivo com segurança.

### Identificador principal

No primeiro acesso, o app deverá gerar:

- `installation_id`: UUID aleatório exclusivo daquela instalação;
- um par de chaves criptográficas não exportáveis;
- chave privada protegida pelo Android Keystore ou iOS Secure Enclave/Keychain;
- chave pública enviada ao Laravel e vinculada ao usuário após WhatsApp e
  FACEIO.

Em operações sensíveis, o Laravel enviará um nonce e exigirá sua assinatura pela
chave do dispositivo. A troca da chave pública ou perda da chave privada será
tratada como novo dispositivo e exigirá FACEIO novamente.

### Informações necessárias

| Campo | Exemplo | Uso |
|---|---|---|
| `installation_id` | UUID | Identificar a instalação |
| `public_key` | chave codificada | Provar posse do dispositivo vinculado |
| `platform` | `android` ou `ios` | Aplicar política correta |
| `app_version` | `1.2.0` | Compatibilidade e versão mínima |
| `app_build` | `42` | Identificar o binário |
| `package_name` | `com.empresa.ponto` | Confirmar aplicação esperada |
| `app_signing_digest` | hash | Identificar assinatura do aplicativo |
| `device_manufacturer` | `Samsung` | Auditoria e suporte |
| `device_model` | `SM-A556E` | Auditoria e sinal auxiliar |
| `os_version` | `Android 16` | Risco e compatibilidade |
| `security_patch` | `2026-08-01` | Sinal de integridade Android |
| `locale` | `pt-BR` | Experiência e anomalia auxiliar |
| `timezone` | `America/Sao_Paulo` | Anomalia auxiliar, nunca identidade |
| `biometric_available` | `true` | Capacidade, não resultado facial |
| `push_token_hash` | hash | Notificações e troca de token |
| `integrity_provider` | `play_integrity` | Origem da atestação |
| `integrity_verdict` | valor normalizado | Avaliação de app/dispositivo |
| `ip_address` | obtido pelo servidor | Auditoria e risco |

O IP será obtido pelo Laravel, não confiado a partir do JSON. O app não enviará
IMEI, IMSI, lista de aplicativos, contatos, MAC address, número de série ou outro
identificador invasivo e sujeito a restrições da plataforma.

### Atestação da aplicação

- Android: usar Google Play Integrity para verificar aplicação reconhecida,
  binário não modificado e integridade do dispositivo.
- iOS: usar Apple App Attest para comprovar que a requisição veio de uma
  instância legítima do aplicativo.
- Quando disponível, validar chave protegida por hardware e sua cadeia de
  atestação no backend.
- Cada atestação deve conter nonce ou hash da requisição para impedir replay.
- O resultado será validado pelo Laravel diretamente com a plataforma; o app
  não poderá enviar apenas `integrity_ok: true`.

### Mudanças que exigem FACEIO novamente

FACEIO será exigido quando ocorrer qualquer evento de identidade forte:

- novo `installation_id` por reinstalação;
- chave pública diferente ou assinatura do nonce inválida;
- acesso em aparelho ainda não vinculado;
- restauração do app em outro aparelho;
- troca do telefone WhatsApp cadastrado;
- recuperação de conta ou redefinição administrativa de segurança;
- vínculo do dispositivo revogado pelo usuário ou gestor;
- `app_signing_digest` ou `package_name` não reconhecido;
- atestação indicando aplicativo adulterado;
- evidência forte de root, jailbreak, emulador não permitido ou clonagem;
- combinação de sinais de alto risco definida pela política.

O Laravel deverá responder nesses casos com código de negócio como
`FACE_VERIFICATION_REQUIRED`, emitir somente JWT limitado e abrir uma nova sessão
de primeiro vínculo FACEIO.

### Mudanças que não exigem FACEIO isoladamente

Estas mudanças são normais e apenas atualizam a auditoria ou elevam levemente o
risco:

- atualização do aplicativo assinada oficialmente;
- atualização do Android ou iOS;
- mudança de IP, Wi-Fi, operadora ou rede móvel;
- renovação do push token;
- alteração de idioma, fuso ou horário de verão;
- pequena mudança no nome, fabricante ou representação do modelo;
- falta temporária de GPS;
- troca de versão do patch de segurança.

Nenhum desses sinais isolados deverá bloquear o usuário ou exigir FACEIO. Uma
decisão adicional poderá ocorrer apenas quando vários sinais mudarem juntos e a
política de risco justificar.

### Resultado da avaliação

A API normalizará a decisão sem devolver detalhes que facilitem fraude:

```json
{
  "device_status": "face_verification_required",
  "reason_code": "DEVICE_KEY_CHANGED",
  "allowed_actions": [
    "me.read",
    "face_enrollment.start"
  ]
}
```

Estados previstos:

- `trusted`: dispositivo vinculado e íntegro;
- `review`: mudança de baixo ou médio risco, acesso limitado conforme política;
- `face_verification_required`: exige FACEIO para novo vínculo;
- `blocked`: aplicativo adulterado, dispositivo revogado ou fraude confirmada.

O histórico de dispositivos deverá permitir ao usuário e ao gestor consultar
nome amigável, modelo, plataforma, primeiro e último acesso, situação e data de
revogação. Dados técnicos sensíveis ficam restritos à auditoria autorizada.

### Endpoints de coleta e validação

O registro não será feito com uma única requisição sem contexto. Primeiro o app
obtém um desafio curto; depois envia a coleta assinada e vinculada a esse
desafio. Os endpoints aceitam o JWT limitado emitido após validar o WhatsApp.

#### 1. Solicitar desafio

`POST /api/v1/devices/challenges`

Cabeçalho:

```http
Authorization: Bearer {jwt-limitado-ou-completo}
Content-Type: application/json
```

Requisição:

```json
{
  "installation_id": "550e8400-e29b-41d4-a716-446655440000",
  "platform": "android",
  "purpose": "register"
}
```

Valores de `purpose`:

- `register`: primeiro vínculo ou reinstalação;
- `verify`: validação de um aparelho já vinculado;
- `sensitive_action`: confirmação antes de uma operação crítica futura.

Resposta `201`:

```json
{
  "data": {
    "challenge_id": "01K...",
    "nonce": "valor-aleatorio-base64url",
    "expires_at": "2026-08-25T10:05:00-03:00",
    "required_attestation": "play_integrity"
  }
}
```

O desafio terá validade máxima de cinco minutos, finalidade definida e uso
único. O `nonce` também será associado ao usuário, instalação e IP inicial.

#### 2. Registrar dispositivo e enviar informações

`POST /api/v1/devices/register`

```json
{
  "challenge_id": "01K...",
  "installation_id": "550e8400-e29b-41d4-a716-446655440000",
  "device_name": "Celular de João",
  "public_key": {
    "algorithm": "ES256",
    "value": "chave-publica-codificada"
  },
  "challenge_signature": "assinatura-base64url",
  "app": {
    "version": "1.2.0",
    "build": "42",
    "package_name": "com.empresa.ponto",
    "signing_digest": "sha256:..."
  },
  "device": {
    "platform": "android",
    "manufacturer": "Samsung",
    "model": "SM-A556E",
    "os_version": "16",
    "security_patch": "2026-08-01",
    "locale": "pt-BR",
    "timezone": "America/Sao_Paulo",
    "biometric_available": true,
    "is_physical_device": true
  },
  "notifications": {
    "push_token": "token-do-provedor"
  },
  "attestation": {
    "provider": "play_integrity",
    "token": "token-opaco-gerado-pela-plataforma"
  }
}
```

Para iOS, `attestation.provider` será `app_attest` e o objeto conterá os dados
opacos exigidos pelo Apple App Attest. O contrato OpenAPI usará schemas
específicos por plataforma com `oneOf`.

O Laravel não confiará em `signing_digest`, `is_physical_device` ou qualquer
outro booleano declarado pelo app. Esses campos auxiliam a auditoria; a decisão
virá da validação criptográfica da assinatura e do token de atestação diretamente
com Google ou Apple.

O `push_token` será cifrado ou armazenado em serviço próprio. Para comparação e
auditoria, a aplicação manterá somente seu hash. O token não fará parte da
impressão digital que decide exigir FACEIO, pois pode mudar normalmente.

Resposta `201` quando o vínculo for confiável, mas ainda faltar o FACEIO inicial:

```json
{
  "data": {
    "device_id": "01K...",
    "status": "face_verification_required",
    "trusted": false,
    "face_verification_required": true,
    "allowed_actions": [
      "me.read",
      "face_enrollment.start"
    ]
  }
}
```

Após o webhook `ENROLL` do FACEIO ser confirmado, o mesmo dispositivo passa para
`trusted`.

#### 3. Verificar dispositivo nos próximos acessos

`POST /api/v1/devices/verify`

O app solicita antes um desafio com `purpose: verify` e envia:

```json
{
  "challenge_id": "01K...",
  "device_id": "01K...",
  "installation_id": "550e8400-e29b-41d4-a716-446655440000",
  "challenge_signature": "assinatura-base64url",
  "app": {
    "version": "1.2.1",
    "build": "43",
    "package_name": "com.empresa.ponto",
    "signing_digest": "sha256:..."
  },
  "device": {
    "platform": "android",
    "manufacturer": "Samsung",
    "model": "SM-A556E",
    "os_version": "16",
    "security_patch": "2026-08-01",
    "locale": "pt-BR",
    "timezone": "America/Sao_Paulo",
    "biometric_available": true,
    "is_physical_device": true
  },
  "attestation": {
    "provider": "play_integrity",
    "token": "novo-token-opaco"
  }
}
```

Resposta confiável:

```json
{
  "data": {
    "device_id": "01K...",
    "status": "trusted",
    "trusted": true,
    "face_verification_required": false,
    "allowed_actions": [
      "time-punch.read",
      "time-punch.create",
      "time-adjustments.create"
    ]
  }
}
```

Resposta quando uma mudança forte for detectada:

```json
{
  "data": {
    "device_id": "01K...",
    "status": "face_verification_required",
    "trusted": false,
    "face_verification_required": true,
    "reason_code": "DEVICE_KEY_CHANGED",
    "allowed_actions": [
      "me.read",
      "face_enrollment.start"
    ]
  }
}
```

Uma chave diferente não poderá simplesmente substituir a chave cadastrada nesse
endpoint. Ela inicia um novo vínculo e exige WhatsApp mais FACEIO. Uma assinatura
inválida, atestação adulterada ou dispositivo revogado poderá retornar `403` em
vez de abrir automaticamente o recadastro.

### Campos calculados exclusivamente pelo Laravel

- `user_id`, extraído do JWT;
- IP, considerando somente proxies confiáveis configurados;
- datas de criação, primeiro e último acesso;
- hash normalizado dos sinais usados na comparação;
- resultado validado da atestação;
- score e nível de risco;
- estado confiável, revisão, FACEIO necessário ou bloqueado;
- motivo da decisão;
- histórico das alterações;
- usuário ou gestor responsável por eventual revogação.

O app nunca poderá enviar diretamente `trusted`, `risk_score`,
`face_verification_required`, `user_id` ou qualquer permissão resultante.

## Endpoints

### Autenticação e conta

| Método | Endpoint | Descrição |
|---|---|---|
| POST | `/api/v1/auth/login` | Autenticar dispositivo |
| POST | `/api/v1/auth/refresh` | Renovar credenciais |
| POST | `/api/v1/auth/logout` | Revogar a sessão atual |
| GET | `/api/v1/me` | Dados do usuário autenticado |
| POST | `/api/v1/app-unlock/whatsapp/request` | Enviar código de liberação |
| POST | `/api/v1/app-unlock/whatsapp/verify` | Validar código e liberar app |
| POST | `/api/v1/devices/challenges` | Criar nonce de registro ou validação |
| POST | `/api/v1/devices/register` | Registrar chave pública e sinais do aparelho |
| POST | `/api/v1/devices/verify` | Validar assinatura, atestação e mudanças |
| GET | `/api/v1/devices` | Listar os próprios dispositivos vinculados |
| DELETE | `/api/v1/devices/{id}` | Revogar um dispositivo próprio |

### Ponto

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/api/v1/time-punch/status` | Estado das batidas do dia |
| POST | `/api/v1/time-punch` | Registrar a próxima batida |
| GET | `/api/v1/time-card?month=2026-08` | Consultar cartão mensal |
| GET | `/api/v1/time-adjustments` | Listar ajustes do próprio usuário |
| POST | `/api/v1/time-adjustments` | Solicitar ajuste manual |
| GET | `/api/v1/time-adjustments/{id}` | Consultar um ajuste próprio |

## Estado do ponto

`GET /api/v1/time-punch/status`

Resposta `200`:

```json
{
  "data": {
    "server_time": "2026-08-25T08:02:14-03:00",
    "timezone": "America/Sao_Paulo",
    "next_type": "clock_in",
    "entries": [],
    "pending_adjustments": []
  }
}
```

Valores possíveis de `next_type`:

- `clock_in`: entrada;
- `break_start`: início do intervalo;
- `break_end`: fim do intervalo;
- `clock_out`: saída;
- `null`: quatro batidas regulares concluídas.

As opções `overtime_start` e `overtime_end` serão expostas em ação
específica quando o backend determinar que a jornada regular já permite hora
extra. A hora extra continua sujeita à aprovação do gestor.

## Registrar ponto

`POST /api/v1/time-punch`

Cabeçalhos:

```http
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

Corpo inicial:

```json
{
  "device": {
    "id": "identificador-local-do-aparelho",
    "platform": "android",
    "app_version": "1.0.0"
  },
  "location": null
}
```

O campo `recorded_at` não será aceito. O servidor calcula:

- data e hora;
- tipo da próxima batida;
- status;
- motivo;
- janela da jornada;
- feriado ou folga;
- IP e origem `mobile`.

O campo `location` fica reservado para evolução futura e inicialmente será
opcional ou ignorado de acordo com a configuração do servidor. A confirmação
biométrica pertence ao desbloqueio do aplicativo e não fará parte do corpo da
batida nesta primeira arquitetura.

Resposta `201`:

```json
{
  "message": "Ponto registrado com sucesso.",
  "data": {
    "id": 154,
    "type": "clock_in",
    "type_label": "Entrada",
    "recorded_at": "2026-08-25T08:02:14-03:00",
    "status": "approved",
    "reason": null,
    "next_type": "break_start"
  }
}
```

Regras de status:

| Situação | Status |
|---|---|
| Batida automática dentro da janela | `approved` |
| Batida automática fora da janela | `cancelled` |
| Batida em folga, feriado ou folga de banco | `pending` |
| Batida de hora extra | `pending` |
| Ajuste ou batida manual | `pending` |

Uma batida cancelada permanece no histórico. O usuário deverá solicitar a
batida correta pelo endpoint de ajuste.

## Evolução futura: geolocalização

Quando a política for habilitada, o aplicativo poderá enviar a posição obtida
no momento da batida:

```json
{
  "location": {
    "latitude": -23.550520,
    "longitude": -46.633308,
    "accuracy_meters": 12.4,
    "captured_at": "2026-08-25T08:02:10-03:00",
    "mocked": false
  }
}
```

Dados previstos:

- latitude e longitude;
- precisão em metros;
- horário da captura;
- indicação de localização simulada, quando fornecida pelo sistema operacional;
- distância calculada até o local de trabalho autorizado;
- resultado da validação da cerca geográfica.

A geolocalização enviada pelo dispositivo nunca substituirá o horário oficial
do servidor. O backend deverá validar limites numéricos, precisão, idade da
captura e política da empresa. A decisão poderá ser configurada como:

- apenas auditoria, sem bloquear a batida;
- fora da cerca gera status `pending` para análise;
- fora da cerca bloqueia a operação, somente quando houver política formal.

O sistema deverá prever locais autorizados por empresa ou unidade, com
latitude, longitude e raio em metros. Falha de GPS ou falta de permissão deverá
ter tratamento explícito e não ser confundida com tentativa de fraude.

## Estudo futuro: reconhecimento facial no aplicativo

O reconhecimento facial acontecerá no primeiro acesso ao aplicativo e será
repetido somente quando um sinal forte indicar novo dispositivo ou perda do
vínculo confiável. Depois que o código do WhatsApp for validado, o novo usuário
deverá concluir o cadastro e a confirmação inicial pelo FACEIO. O Laravel
continuará responsável por autenticação JWT, correlação do usuário, confirmação
segura do evento e autorização final.

O app não poderá enviar apenas `biometric_confirmed: true`, pois esse valor pode
ser forjado. A liberação dependerá da correlação entre uma sessão criada pelo
Laravel, o resultado do FACEIO no cliente e o webhook autenticado recebido pelo
backend.

### Integração escolhida: FACEIO

O reconhecimento facial será implementado com
[`FACEIO`](https://faceio.net/). O `fio.js` oferece `enroll()` e
`authenticate()`. Nesta arquitetura usaremos `enroll()` no primeiro acesso. O
serviço solicita acesso à câmera, processa o cadastro facial e devolve um
`facialId` único e o `payload` associado. O método `authenticate()` ficará
reservado para uma possível política futura de reconhecimento recorrente.

O FACEIO é orientado a aplicações web e usa um widget JavaScript. Para o Flutter,
o estudo técnico deverá validar sua execução em uma WebView segura ou confirmar
com o fornecedor a existência de uma integração móvel nativa suportada. Não
criaremos uma implementação Flutter não oficial sem validar câmera, retorno ao
app, política de origem e comportamento em Android e iOS.

O aplicativo utilizará somente o **Public ID** da aplicação FACEIO. A chave da
REST API e o token de autenticação dos webhooks são segredos e ficarão apenas no
Laravel. A REST API administrativa do FACEIO também deverá ser chamada somente
pelo backend privado.

Fluxo previsto para o primeiro acesso:

1. Usuário informa o telefone e valida o código recebido no WhatsApp.
2. Laravel detecta que ainda não existe vínculo facial confirmado e devolve um
   JWT limitado.
3. Flutter solicita o cadastro facial ao Laravel.
4. Laravel cria uma sessão curta vinculada ao usuário e dispositivo.
5. Flutter abre o widget FACEIO e chama `enroll()` com um payload opaco fornecido
   pelo Laravel, sem CPF, e-mail ou outro dado pessoal direto.
6. FACEIO realiza consentimento, captura, cadastro e verificações de segurança.
7. O widget devolve o `facialId` ao app.
8. FACEIO envia o evento `ENROLL` ao webhook do Laravel.
9. Laravel somente confirma o vínculo após correlacionar sessão, `facialId`,
   aplicação e webhook autêntico.
10. Laravel marca o primeiro acesso como concluído e emite o JWT liberado.

Fluxo previsto para os acessos posteriores:

1. Usuário informa o telefone cadastrado.
2. Usuário valida o código de seis dígitos recebido no WhatsApp.
3. Laravel identifica que o primeiro acesso e o vínculo facial já foram
   concluídos.
4. Laravel libera o aplicativo sem executar novamente o FACEIO.

No primeiro acesso, o retorno JavaScript isoladamente não será suficiente para
liberação, pois pode ser adulterado em um cliente comprometido. A confirmação
final dependerá do webhook autenticado do FACEIO. Segundo a documentação do
fornecedor, a autenticidade do webhook é verificada pelo token Bearer presente
no cabeçalho `WWW-Authenticate`, que deverá ser comparado em tempo constante com
o segredo armazenado no servidor.

Endpoints previstos:

| Método | Endpoint | Descrição |
|---|---|---|
| POST | `/api/v1/face-auth/enrollment-sessions` | Iniciar cadastro facial |
| POST | `/api/v1/face-auth/confirm` | Confirmar o primeiro cadastro após webhook |
| DELETE | `/api/v1/face-auth/enrollment` | Remover vínculo facial do usuário |
| POST | `/webhooks/faceio` | Receber `ENROLL`, `DELETION` e futuros eventos `AUTH` |

O webhook deverá:

- ficar fora do middleware JWT, mas validar obrigatoriamente seu token secreto;
- aceitar somente eventos e `appId` esperados;
- ser idempotente para impedir processamento duplicado;
- responder em menos de 6 segundos;
- armazenar apenas dados mínimos de auditoria;
- nunca confiar em `payload` para escolher um usuário sem validar a sessão;
- rejeitar evento expirado, sem correlação ou com `facialId` divergente.

O FACEIO oferece detecção de vivacidade e opções contra spoofing que deverão ser
habilitadas e testadas no console. Também deverá ser avaliada a exigência do PIN
do próprio FACEIO: para o ponto, uma autenticação facial seguida de PIN pode ser
mais segura, mas altera a experiência desejada.

### Plano e custos

O plano gratuito serve apenas para protótipo: atualmente limita o índice a mil
rostos, restringe as autenticações, possui segurança mínima e não oferece
webhooks. Como a confirmação segura pelo Laravel depende de webhook, a produção
exigirá no mínimo um plano pago que ofereça esse recurso.

Adotaremos a implantação em duas fases:

#### Fase 1 — desenvolvimento e homologação com plano Free

- integrar o `fio.js` ao fluxo Flutter/WebView;
- validar câmera, consentimento e `enroll()`; testar `authenticate()` somente
  como possibilidade futura;
- cadastrar somente usuários de teste autorizados;
- testar o vínculo entre usuário interno e `facialId`;
- manter login JWT, senha ou PIN como confirmação obrigatória;
- não permitir que o retorno facial isolado autorize uma batida real;
- identificar visualmente o ambiente como homologação;
- não usar dados biométricos de colaboradores em produção.

Durante essa fase, o resultado devolvido pelo JavaScript será considerado apenas
uma demonstração funcional, pois o cliente pode ser adulterado e o plano não
fornece o webhook necessário para confirmação independente pelo Laravel.

#### Fase 2 — produção com Starter ou superior

- contratar um plano com webhook e opções de segurança adequadas;
- configurar o token secreto do webhook somente no Laravel;
- exigir correlação entre sessão, retorno do widget e evento `ENROLL` no
  primeiro acesso;
- ativar vivacidade e proteções contra spoofing disponíveis;
- executar homologação de segurança, privacidade e LGPD;
- migrar ou refazer os cadastros faciais conforme a política aprovada;
- somente então permitir que a confirmação facial libere o aplicativo.

A troca de plano será controlada por configuração, por exemplo:

```dotenv
FACEIO_ENABLED=false
FACEIO_MODE=prototype
FACEIO_PUBLIC_ID=
FACEIO_API_KEY=
FACEIO_WEBHOOK_TOKEN=
```

Valores previstos para `FACEIO_MODE`:

- `disabled`: integração desligada;
- `prototype`: plano Free, sem confiança para autorizar operação real;
- `verified`: plano com webhook validado, habilitado após homologação.

O backend deverá recusar a liberação biométrica quando o modo for `prototype`,
mesmo que o Flutter informe autenticação facial bem-sucedida.

Na consulta realizada em agosto de 2026, a tabela pública apresentava Starter a
US$ 20/mês, Pro a US$ 39/mês, Business a US$ 99/mês e Enterprise a US$ 200/mês
por aplicação. Esses valores não serão fixados como regra do sistema e deverão
ser conferidos novamente antes da contratação.

Antes da implementação, o estudo deverá confirmar:

- funcionamento oficial ou homologado dentro do Flutter;
- plano com webhooks e opções de segurança necessárias;
- região onde vetores faciais e dados são armazenados;
- LGPD, consentimento, retenção, exportação e exclusão do `facialId`;
- prevenção contra foto, vídeo, deepfake e câmera virtual;
- taxa de falso positivo e falso negativo com usuários reais;
- contingência quando FACEIO, câmera ou internet estiverem indisponíveis;
- recadastro após troca de aparência ou falha recorrente;
- remoção no desligamento do colaborador;
- proibição de usar estimativas de idade ou gênero no processo de ponto.

O FACEIO validará a identidade somente na ativação inicial do app, mas não
determinará horário, tipo ou status da batida. Essas regras continuarão
exclusivamente no Laravel.

## Solicitar ajuste

`POST /api/v1/time-adjustments`

Requisição:

```json
{
  "work_date": "2026-08-25",
  "requested_entries": [
    {
      "type": "clock_out",
      "time": "18:00"
    }
  ],
  "reason": "Registrei a saída fora do horário e preciso informar a hora correta."
}
```

Tipos permitidos:

- `clock_in`;
- `break_start`;
- `break_end`;
- `clock_out`;
- `overtime_start`;
- `overtime_end`.

Validação:

- data obrigatória, válida e não futura;
- entre uma e quatro entradas na primeira versão;
- tipo não pode se repetir na mesma solicitação;
- horário no formato `HH:mm`;
- horários devem estar em ordem cronológica;
- justificativa entre 10 e 1000 caracteres;
- não pode existir solicitação pendente para o mesmo tipo e data.

Resposta `201`:

```json
{
  "message": "Ajuste enviado para aprovação do gestor.",
  "data": {
    "id": 41,
    "work_date": "2026-08-25",
    "status": "pending",
    "requested_entries": [
      {
        "type": "clock_out",
        "time": "18:00"
      }
    ]
  }
}
```

O usuário somente poderá consultar ajustes pertencentes a ele. Um identificador
de outro usuário deve retornar `404`, evitando revelar a existência do
registro.

## Consulta do cartão mensal

`GET /api/v1/time-card?month=2026-08`

A resposta deve reutilizar o resultado de `TimeCardService` e conter:

- mês consultado;
- jornada prevista;
- dias do mês;
- batidas e seus status;
- horas previstas e trabalhadas;
- saldo diário;
- banco acumulado;
- ajustes pendentes.

O parâmetro `month` usa `YYYY-MM`. Sem o parâmetro, será usado o mês corrente
em `America/Sao_Paulo`.

## Padrão de erros

```json
{
  "message": "Os dados informados são inválidos.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "requested_entries.0.time": [
      "O horário deve usar o formato HH:mm."
    ]
  },
  "request_id": "01K..."
}
```

| HTTP | Uso |
|---|---|
| 400 | Requisição malformada |
| 401 | Token ausente, inválido ou expirado |
| 403 | Usuário sem `tracks_time` ou sem permissão |
| 404 | Recurso inexistente ou pertencente a outro usuário |
| 409 | Repetição detectada pela chave de idempotência |
| 422 | Regra de negócio ou validação |
| 429 | Limite de requisições excedido |
| 500 | Erro interno com `request_id` |

## Swagger/OpenAPI

A documentação será implementada com
[`DarkaOnLine/L5-Swagger`](https://github.com/DarkaOnLine/L5-Swagger). O endereço
SSH informado identifica o repositório, enquanto a instalação normal pelo
Composer usa o nome `darkaonline/l5-swagger`.

Todos os comandos deverão ser executados dentro do container `chags-app`:

```bash
docker exec chags-app composer require darkaonline/l5-swagger
docker exec chags-app php artisan vendor:publish --provider="L5Swagger\\L5SwaggerServiceProvider"
docker exec chags-app php artisan l5-swagger:generate
```

Antes de instalar, o Composer deverá confirmar uma versão compatível com o
Laravel atual. Não será forçada uma versão incompatível apenas para concluir a
instalação.

O Laravel fará a descoberta automática do service provider. A configuração
publicada ficará em `config/l5-swagger.php`, e as variáveis específicas serão
documentadas no `.env.example`.

A especificação será OpenAPI 3 e terá:

- esquema de segurança `bearerAuth` do tipo HTTP bearer/JWT;
- exemplos de requisição e resposta;
- schemas reutilizáveis;
- descrição de todos os status HTTP;
- agrupamento por tags `Auth`, `App Unlock`, `Time Punch`, `Time Card` e
  `Time Adjustments`;
- botão **Authorize** para testar o JWT;
- JSON da especificação em `/api/documentation/openapi.json`;
- interface Swagger em `/api/documentation`.

Usaremos atributos PHP do OpenAPI nas classes de documentação e schemas
dedicados, evitando concentrar contratos extensos dentro dos controllers. A
geração deverá fazer parte da validação automatizada para detectar atributos ou
referências inválidas.

Em produção, a documentação não deverá ficar pública. O acesso será protegido
por autenticação e permissão administrativa ou desabilitado por configuração.

## Segurança e auditoria

- HTTPS obrigatório fora do ambiente local.
- Senhas nunca são armazenadas pelo Flutter.
- Refresh token deve ser guardado no armazenamento seguro do sistema
  operacional.
- Rate limit sugerido: login `5/min`, consulta `60/min` e batida `10/min`.
- Rate limit específico para emissão e validação de código de WhatsApp.
- Chave de idempotência obrigatória no registro de ponto.
- Não aceitar `user_id`, `status`, `source`, `recorded_at` ou
  `created_by` enviados pelo cliente.
- Registrar dispositivo, IP, versão do app e identificador da requisição para
  auditoria.
- Não ativar geolocalização ou reconhecimento facial próprio sem finalidade
  formal, base legal, transparência ao colaborador, política de privacidade e
  prazo de retenção. Dados biométricos exigem proteção reforçada.
- O Laravel não armazenará vetores faciais; manterá somente o `facialId`, o
  vínculo com o usuário e os dados mínimos de auditoria permitidos pela política.
- Solicitar localização somente no momento necessário, explicando por que o
  dado será coletado.
- Aplicar acesso mínimo às chaves públicas, vínculos de dispositivos e
  coordenadas, com trilha de auditoria.
- Criptografar os arquivos e dados sensíveis em trânsito e em repouso.
- Respostas não devem expor stack trace, SQL ou dados de outros usuários.

## Estrutura sugerida

```text
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php
│   │   ├── WhatsAppUnlockController.php
│   │   ├── DeviceController.php
│   │   ├── MeController.php
│   │   ├── TimePunchController.php
│   │   ├── TimeCardController.php
│   │   └── TimeAdjustmentController.php
│   ├── Requests/Api/V1/
│   └── Resources/Api/V1/
├── OpenApi/
└── Services/VirtualOffice/
routes/
└── api.php
tests/
└── Feature/Api/V1/
```

Controllers web e API não devem chamar uns aos outros. Ambos devem delegar aos
mesmos services de domínio.

## Plano detalhado de implementação

### Etapa 0 — regras desta entrega

Esta entrega implementará somente API, banco, integrações de backend, Swagger e
testes. Não serão criados Flutter, WebView, tela de câmera ou código nativo de
Play Integrity/App Attest. Os contratos desses recursos serão preparados para o
aplicativo consumir depois.

Todo comando será executado no container `chags-app`. Antes de iniciar:

```bash
docker ps -a
docker exec chags-app php artisan about
docker exec chags-app php artisan test --compact
```

Não usar `php`, Composer, Node ou Artisan diretamente no host.

### Etapa 1 — instalar JWT e Swagger

```bash
docker exec chags-app composer require tymon/jwt-auth
docker exec chags-app php artisan vendor:publish --provider="Tymon\\JWTAuth\\Providers\\LaravelServiceProvider"
docker exec chags-app php artisan jwt:secret

docker exec chags-app composer require darkaonline/l5-swagger
docker exec chags-app php artisan vendor:publish --provider="L5Swagger\\L5SwaggerServiceProvider"
docker exec chags-app php artisan l5-swagger:generate
```

Arquivos esperados:

- `config/jwt.php`;
- `config/l5-swagger.php`;
- `JWT_SECRET` no `.env` local;
- chaves documentadas, sem valores, no `.env.example`.

Após a instalação:

```bash
docker exec chags-app composer validate
docker exec chags-app php artisan package:discover
docker exec chags-app php artisan optimize:clear
```

### Etapa 2 — habilitar a camada API

Criar `routes/api.php` e registrar em `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Não executar `install:api`, pois não usaremos Sanctum. O prefixo final será
`/api/v1`, definido no arquivo de rotas.

Criar em `config/auth.php`:

```php
'api' => [
    'driver' => 'jwt',
    'provider' => 'users',
],
```

O guard `web` continuará sendo o padrão da aplicação e do Spatie. O guard `api`
serve para autenticação JWT; a autorização continuará usando as permissões
canônicas do guard `web` por meio de Gates e policies existentes.

### Etapa 3 — migrations e tabelas

Scaffolding inicial, sempre dentro do container:

```bash
docker exec chags-app php artisan make:migration add_mobile_api_fields_to_users_table
docker exec chags-app php artisan make:model WhatsAppUnlockChallenge -mf
docker exec chags-app php artisan make:model ApiDevice -mf
docker exec chags-app php artisan make:model DeviceChallenge -mf
docker exec chags-app php artisan make:model FaceioIdentity -mf
docker exec chags-app php artisan make:model FaceioSession -mf
docker exec chags-app php artisan make:model IntegrationWebhookEvent -mf
docker exec chags-app php artisan make:model ApiIdempotencyKey -mf
```

Os generators criarão arquivos básicos; colunas, índices, casts, fillable e
relacionamentos serão revisados manualmente antes de migrar.

#### Alteração em `users`

Adicionar sem remover o campo `phone` já existente:

| Campo | Tipo | Regra |
|---|---|---|
| `whatsapp_phone` | string(20), nullable, unique | E.164 normalizado |
| `whatsapp_phone_verified_at` | timestampTz nullable | Número confirmado |
| `whatsapp_phone_changed_at` | timestampTz nullable | Última alteração |
| `app_unlock_required` | boolean default true | Exige liberação |
| `first_app_access_completed_at` | timestampTz nullable | FACEIO inicial concluído |
| `jwt_invalid_before` | timestampTz nullable | Revogação lógica global |

O `phone` atual permanece como contato pessoal. `whatsapp_phone` será o número
único usado para entrada no app.

#### `whatsapp_unlock_challenges`

| Campo | Tipo/índice |
|---|---|
| `id` | ULID primário |
| `user_id` | FK nullable, indexada |
| `phone_hash` | char(64), indexado |
| `device_installation_id` | UUID/string indexado |
| `code_hash` | string |
| `expires_at` | timestampTz indexado |
| `attempts` | unsigned tiny integer |
| `consumed_at` | timestampTz nullable |
| `provider_message_id` | string nullable |
| `request_ip_hash` | char(64) nullable |
| timestamps | criação e atualização |

`user_id` será nulo para número inexistente, mantendo resposta uniforme. Nenhum
código ou telefone completo ficará nessa tabela.

#### `api_devices`

| Grupo | Campos principais |
|---|---|
| Identidade | `id` ULID, `user_id`, `installation_id`, `name` |
| Chave | `public_key`, `key_algorithm`, `key_fingerprint` unique |
| App | `platform`, `app_version`, `app_build`, `package_name`, `signing_digest` |
| Aparelho | `manufacturer`, `model`, `os_version`, `security_patch`, `locale`, `timezone` |
| Segurança | `attestation_provider`, `attestation_status`, `risk_level`, `status` |
| FACEIO | `face_verified_at`, `face_verification_version` |
| Auditoria | `first_seen_at`, `last_seen_at`, `last_ip`, `revoked_at`, `revoked_by` |

Índices únicos:

- `user_id + installation_id`;
- `key_fingerprint`;
- índice de consulta em `user_id + status`.

#### `device_challenges`

| Campo | Uso |
|---|---|
| `id` ULID | Identificador público |
| `user_id` | Dono do desafio |
| `installation_id` | Instalação esperada |
| `purpose` | `register`, `verify`, `sensitive_action` |
| `nonce_hash` | Hash do nonce entregue |
| `expires_at` | Expiração curta |
| `consumed_at` | Uso único |
| `request_ip` | Auditoria |

#### `faceio_identities`

| Campo | Uso |
|---|---|
| `id` ULID | Identidade interna |
| `user_id` unique | Um vínculo ativo por usuário |
| `facial_id_encrypted` | `facialId` cifrado |
| `facial_id_hash` unique | Busca sem descriptografar |
| `enrolled_at` | Cadastro confirmado |
| `deleted_at` | Remoção lógica/auditoria |

#### `faceio_sessions`

| Campo | Uso |
|---|---|
| `id` ULID | Sessão pública |
| `user_id`, `api_device_id` | Correlação obrigatória |
| `purpose` | inicialmente `first_enrollment` |
| `opaque_payload_hash` | Vincular retorno sem dado pessoal |
| `facial_id_hash` | Preenchido após evento |
| `status` | `pending`, `confirmed`, `expired`, `failed` |
| `expires_at`, `confirmed_at`, `consumed_at` | Ciclo de vida |

#### `integration_webhook_events`

Tabela genérica para idempotência e auditoria:

- `provider`;
- `external_event_id` ou hash determinístico;
- `event_type`;
- `payload_hash`;
- `status`;
- `received_at`, `processed_at`;
- `error_code` nullable.

Não salvar o payload biométrico completo.

#### `api_idempotency_keys`

Usada inicialmente no registro de ponto:

- usuário, chave, rota e método;
- hash da requisição;
- código e corpo resumido da resposta;
- expiração;
- unique `user_id + route + idempotency_key`.

### Etapa 4 — models e relacionamentos

Criar:

- `App\\Models\\WhatsAppUnlockChallenge`;
- `App\\Models\\ApiDevice`;
- `App\\Models\\DeviceChallenge`;
- `App\\Models\\FaceioIdentity`;
- `App\\Models\\FaceioSession`;
- `App\\Models\\IntegrationWebhookEvent`;
- `App\\Models\\ApiIdempotencyKey`.

Atualizar `User`:

- implementar `Tymon\\JWTAuth\\Contracts\\JWTSubject`;
- implementar `getJWTIdentifier()` e `getJWTCustomClaims()`;
- adicionar casts de datas e booleanos novos;
- esconder `password`, `remember_token` e campos sensíveis;
- relacionamentos `apiDevices`, `faceioIdentity`, `faceioSessions` e desafios;
- nunca colocar telefone completo ou `facialId` nos claims JWT.

Claims mínimos:

- versão do token;
- `device_id`, quando o aparelho estiver vinculado;
- `app_unlocked`;
- `unlock_method`;
- instante da liberação.

### Etapa 5 — contratos de services

#### Autenticação

- `JwtTokenService`: emitir, renovar, invalidar e aplicar `jwt_invalid_before`.
- `AppUnlockService`: coordenar WhatsApp, primeiro acesso, dispositivo e token.

#### WhatsApp

- `PhoneNormalizer`: converter e validar E.164.
- `WhatsAppCodeService`: gerar código seguro, hash, expiração e tentativas.
- `WhatsAppMessageSender` (interface): enviar a mensagem.
- `FakeWhatsAppMessageSender`: testes e desenvolvimento.
- Adaptador real será escolhido por configuração posteriormente.

Mensagem sugerida: `Seu código de acesso é 482731. Ele expira em 5 minutos. Não
compartilhe este código.` O código será passado ao provider em memória e nunca
registrado no log.

#### Dispositivo

- `DeviceChallengeService`: criar e consumir nonces.
- `DeviceSignatureVerifier`: validar ES256/assinatura da chave cadastrada.
- `DeviceRegistrationService`: registrar sem permitir troca silenciosa de chave.
- `DeviceRiskService`: comparar sinais e decidir `trusted`, `review`, FACEIO ou
  bloqueio.
- `AppAttestationVerifier` (interface).
- `PlayIntegrityVerifier` e `AppAttestVerifier`: adaptadores futuros/reais.
- `FakeAppAttestationVerifier`: testes locais.

#### FACEIO

- `FaceioSessionService`: criar e consumir sessão de primeiro acesso.
- `FaceioWebhookVerifier`: autenticar token e aplicação.
- `FaceioWebhookService`: processar `ENROLL`/`DELETION` com idempotência.
- `FaceioIdentityService`: vincular e excluir `facialId` cifrado.

#### Ponto

- `TimePunchService`: extrair do controller web o cálculo, criação e resposta.
- `TimePunchStatusService`: estado diário compartilhado por web e API.
- reutilizar `TimePunchDecisionService` sem duplicar regra;
- reutilizar `TimeCardService`;
- reutilizar ou extrair `TimeAdjustmentService` para web e API;
- `IdempotencyService` para impedir batida repetida.

### Etapa 6 — Form Requests

Criar em `App\\Http\\Requests\\Api\\V1`:

- `RequestWhatsAppCodeRequest`;
- `VerifyWhatsAppCodeRequest`;
- `RefreshTokenRequest`;
- `CreateDeviceChallengeRequest`;
- `RegisterDeviceRequest`;
- `VerifyDeviceRequest`;
- `CreateFaceioEnrollmentSessionRequest`;
- `ConfirmFaceioEnrollmentRequest`;
- `StoreTimePunchRequest`;
- `IndexTimeCardRequest`;
- `StoreTimeAdjustmentRequest` ou reutilização segura das regras existentes.

Requests validam formato; autorização e regras de negócio permanecem nos Gates,
middlewares e services.

### Etapa 7 — API Resources

Criar em `App\\Http\\Resources\\Api\\V1`:

- `UserResource`;
- `TokenResource`;
- `DeviceResource`;
- `TimePunchStatusResource`;
- `TimeEntryResource`;
- `TimeCardResource`;
- `TimeAdjustmentResource`.

Todos seguem envelope `data`, nomes `snake_case`, ISO 8601 e timezone explícito.

### Etapa 8 — controllers

Controllers finos, sem regra de jornada ou integração direta:

```bash
docker exec chags-app php artisan make:controller Api/V1/WhatsAppUnlockController
docker exec chags-app php artisan make:controller Api/V1/AuthController
docker exec chags-app php artisan make:controller Api/V1/MeController --invokable
docker exec chags-app php artisan make:controller Api/V1/DeviceController
docker exec chags-app php artisan make:controller Api/V1/FaceioEnrollmentController
docker exec chags-app php artisan make:controller Api/V1/FaceioWebhookController --invokable
docker exec chags-app php artisan make:controller Api/V1/TimePunchController
docker exec chags-app php artisan make:controller Api/V1/TimeCardController --invokable
docker exec chags-app php artisan make:controller Api/V1/TimeAdjustmentController
```

| Controller | Responsabilidade |
|---|---|
| `WhatsAppUnlockController` | `requestCode`, `verifyCode` |
| `AuthController` | `refresh`, `logout` e login alternativo |
| `MeController` | usuário e estado de ativação |
| `DeviceController` | `challenge`, `register`, `verify`, `index`, `revoke` |
| `FaceioEnrollmentController` | criar sessão, confirmar, excluir vínculo |
| `FaceioWebhookController` | receber webhook sem JWT e delegar validação |
| `TimePunchController` | `status`, `store` |
| `TimeCardController` | `show` mensal próprio |
| `TimeAdjustmentController` | `index`, `store`, `show` próprios |

O webhook não usa autenticação JWT, mas exige validação do segredo FACEIO,
idempotência, `appId`, tipo de evento e correlação da sessão.

### Etapa 9 — middleware e autorização

Criar:

- `EnsureTracksTime`: exige `tracks_time = true`;
- `EnsureAppUnlocked`: valida claim e estado atual do dispositivo;
- `EnsureTrustedDevice`: exige dispositivo não revogado e risco permitido;
- `ForceJsonResponse`: padroniza negociação de conteúdo, se necessário.

Ordem das rotas de ponto:

```text
auth:api
→ tracks.time
→ app.unlocked
→ device.trusted
→ throttle
→ controller
```

O `super-admin` continua irrestrito para administração web, mas não deverá bater
ponto em nome de outra pessoa pela API. A API sempre usa o usuário do JWT.

### Etapa 10 — rotas

Estrutura proposta de `routes/api.php`:

```php
Route::prefix('v1')->group(function () {
    Route::post('app-unlock/whatsapp/request', [WhatsAppUnlockController::class, 'requestCode']);
    Route::post('app-unlock/whatsapp/verify', [WhatsAppUnlockController::class, 'verifyCode']);
    Route::post('webhooks/faceio', FaceioWebhookController::class);

    Route::middleware('auth:api')->group(function () {
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', MeController::class);

        Route::post('devices/challenges', [DeviceController::class, 'challenge']);
        Route::post('devices/register', [DeviceController::class, 'register']);
        Route::post('devices/verify', [DeviceController::class, 'verify']);
        Route::get('devices', [DeviceController::class, 'index']);
        Route::delete('devices/{device}', [DeviceController::class, 'revoke']);

        Route::post('face-auth/enrollment-sessions', [FaceioEnrollmentController::class, 'store']);
        Route::post('face-auth/confirm', [FaceioEnrollmentController::class, 'confirm']);

        Route::middleware(['tracks.time', 'app.unlocked', 'device.trusted'])->group(function () {
            Route::get('time-punch/status', [TimePunchController::class, 'status']);
            Route::post('time-punch', [TimePunchController::class, 'store']);
            Route::get('time-card', TimeCardController::class);
            Route::get('time-adjustments', [TimeAdjustmentController::class, 'index']);
            Route::post('time-adjustments', [TimeAdjustmentController::class, 'store']);
            Route::get('time-adjustments/{adjustment}', [TimeAdjustmentController::class, 'show']);
        });
    });
});
```

O webhook poderá ficar em `/api/v1/webhooks/faceio`; a URL cadastrada no FACEIO
deve corresponder exatamente à rota final.

### Etapa 11 — Swagger/OpenAPI

Criar classes em `app/OpenApi`:

- `OpenApiInfo`: título, versão, servidores e `bearerAuth`;
- schemas comuns de sucesso, erro, paginação e validação;
- schemas de WhatsApp, dispositivo Android/iOS, tokens, ponto e ajustes;
- uma classe de documentação por grupo de endpoints.

Todos os endpoints devem declarar:

- tag e operação;
- autenticação ou rota pública;
- request body e exemplos;
- respostas `200/201/202`, `401`, `403`, `404`, `409`, `422`, `429` e `500`;
- códigos de negócio como `FACE_VERIFICATION_REQUIRED`;
- headers `Authorization` e `Idempotency-Key`, quando aplicáveis.

Geração e validação:

```bash
docker exec chags-app php artisan l5-swagger:generate
docker exec chags-app php artisan route:list --path=api
```

### Etapa 12 — configuração

Adicionar ao `.env.example` sem segredos:

```dotenv
JWT_TTL=15
JWT_REFRESH_TTL=43200
JWT_BLACKLIST_ENABLED=true

L5_SWAGGER_GENERATE_ALWAYS=false

WHATSAPP_DRIVER=fake
WHATSAPP_CODE_TTL_SECONDS=300
WHATSAPP_CODE_MAX_ATTEMPTS=5
WHATSAPP_RESEND_SECONDS=60

DEVICE_ATTESTATION_DRIVER=fake
DEVICE_CHALLENGE_TTL_SECONDS=300

FACEIO_ENABLED=false
FACEIO_MODE=prototype
FACEIO_PUBLIC_ID=
FACEIO_API_KEY=
FACEIO_WEBHOOK_TOKEN=
```

Criar arquivos `config/mobile-api.php`, `config/whatsapp.php` e `config/faceio.php`
para centralizar leitura e defaults. Services não devem chamar `env()`.

### Etapa 13 — testes automatizados

Pastas:

```text
tests/Feature/Api/V1/Auth/
tests/Feature/Api/V1/Devices/
tests/Feature/Api/V1/TimePunch/
tests/Feature/Api/V1/TimeAdjustments/
tests/Feature/Api/V1/Webhooks/
tests/Unit/Services/MobileApi/
```

Cenários mínimos:

- resposta uniforme para telefone existente e inexistente;
- código correto, incorreto, expirado, consumido e bloqueado;
- código e telefone não aparecem em logs;
- primeiro acesso produz JWT limitado;
- desafio expirado ou repetido falha;
- assinatura válida, inválida e chave trocada;
- novo aparelho exige FACEIO;
- atualização oficial não exige FACEIO;
- webhook FACEIO válido, segredo inválido, duplicado e sem correlação;
- usuário sem `tracks_time` recebe `403`;
- isolamento completo entre usuários;
- idempotência impede duas batidas;
- horário sempre vem do servidor em `America/Sao_Paulo`;
- regras de status são iguais no web e na API;
- ajuste manual nasce pendente;
- Swagger é gerado sem erro.

Execução progressiva:

```bash
docker exec chags-app vendor/bin/pint --test
docker exec chags-app php artisan test --compact tests/Feature/Api
docker exec chags-app php artisan test --compact
docker exec chags-app npm run build
```

### Etapa 14 — sequência de entrega

1. Dependências, configuração JWT/Swagger e rota API.
2. Migrations, models e factories.
3. WhatsApp fake, desafios e emissão JWT limitada/completa.
4. Vínculo de dispositivo, assinatura e atestação fake.
5. FACEIO em modo protótipo e webhook preparado.
6. Extração dos services de ponto compartilhados.
7. Endpoints de ponto, cartão e ajustes.
8. Swagger completo.
9. Testes de regressão e segurança.
10. Escolha e integração do provedor real de WhatsApp.
11. Homologação externa de Play Integrity, App Attest e FACEIO quando o Flutter
    estiver disponível.

## Critérios de aceite

- Usuário com `tracks_time = true` registra ponto pelo Flutter,
  independentemente do papel.
- Usuário com `tracks_time = false` recebe `403`.
- O horário gravado vem exclusivamente do servidor.
- Duplo toque ou reenvio de rede não cria duas batidas.
- A API e o sistema web produzem o mesmo status para a mesma regra de jornada.
- Ajuste manual sempre fica pendente.
- Um usuário nunca consulta o ponto ou ajuste de outro usuário.
- Tokens expirados e revogados são recusados.
- Telefone informado pelo aplicativo é normalizado em E.164 e o código somente
  é enviado quando ele corresponde exatamente ao número confirmado no cadastro.
- Código de seis dígitos expira, tem uso único, limite de tentativas e nunca é
  armazenado ou registrado em texto puro.
- Quando exigido, o usuário somente acessa as rotas de ponto depois de liberar a
  sessão pelo WhatsApp.
- No primeiro acesso, o JWT permanece limitado até o Laravel confirmar o evento
  `ENROLL` do FACEIO; nos acessos posteriores, somente o código do WhatsApp é
  exigido para liberar o app.
- Novo dispositivo, reinstalação ou troca da chave criptográfica exige FACEIO;
  atualização legítima do app ou sistema operacional não exige isoladamente.
- Laravel valida assinatura do nonce e atestação da plataforma, sem confiar em
  indicadores booleanos enviados pelo cliente.
- Swagger representa exatamente as validações e respostas implementadas.
- Testes automatizados cobrem os endpoints e os principais cenários de negócio.
- Quando habilitada, a geolocalização fica vinculada à batida correta e somente
  usuários autorizados conseguem consultar seus dados de auditoria.
- Uma sessão de primeiro cadastro facial expirada, inválida, pertencente a outro
  usuário ou já utilizada nunca pode liberar o aplicativo.
- O Laravel não armazena imagem nem vetor facial; valida a sessão do Flutter com
  o webhook autenticado do FACEIO e mantém somente o vínculo pelo `facialId`.
