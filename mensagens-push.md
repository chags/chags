# Mensagens e notificações internas

## Objetivo

Criar uma central de mensagens no sino da navbar para:

- permitir que o Setor Pessoal envie comunicados para um usuário específico ou para todos;
- exibir mensagens automáticas geradas pelo sistema;
- entregar no painel web do próprio usuário o código temporário de liberação do aplicativo enquanto a Evolution API não estiver configurada;
- registrar leitura, expiração e auditoria das mensagens.

Nesta primeira versão, “push” significa notificação interna consultada pelo painel. A atualização pode usar polling a cada 30 segundos. Web Push, Firebase Cloud Messaging e Laravel Reverb ficam como evoluções posteriores.

## Regras de segurança

1. O código de liberação nunca pode aparecer no CRUD do Setor Pessoal, nos logs, em respostas administrativas ou para outro usuário.
2. O código só pode ser consultado pelo usuário destinatário autenticado no painel web.
3. O código deve ficar criptografado no banco e expirar no mesmo instante do `WhatsAppUnlockChallenge`.
4. Mensagens expiradas não entram na contagem do sino.
5. A API de solicitação do código deve continuar retornando a mesma resposta para telefone conhecido e desconhecido, evitando enumeração de usuários.
6. O limite atual de tentativas, reenvio e expiração do desafio continua sendo a fonte de verdade.
7. Visualizar o código no painel não consome o desafio. Ele só é consumido quando validado corretamente pelo aplicativo.
8. A autorização deve usar exclusivamente `spatie/laravel-permission`. O papel irrestrito continua sendo `super-admin` no guard `web`.

## Experiência do usuário

### Sino da navbar

Substituir a notificação demonstrativa atualmente fixa em `resources/js/components/app-header.tsx` por dados reais.

O sino deve apresentar:

- badge somente quando houver mensagens não lidas;
- quantidade de não lidas, usando `99+` como limite visual;
- as cinco mensagens mais recentes no dropdown;
- título, resumo, data/hora e indicação de não lida;
- destaque para mensagens de código de acesso;
- ação “Marcar todas como lidas”;
- link “Ver todas” para a central de mensagens.

O clique em uma mensagem abre seu detalhe e marca a entrega como lida. Mensagens com código devem mostrar também a validade e um botão para copiar o código.

### Central do usuário

Criar `/mensagens` com:

- abas “Todas” e “Não lidas”;
- paginação;
- detalhe da mensagem;
- ação para marcar como lida/não lida;
- exclusão da caixa pessoal somente depois de marcar a mensagem como lida;
- estado vazio;
- indicação clara de mensagem expirada;
- ocultação do conteúdo sensível depois da expiração.

### CRUD do Setor Pessoal

Criar `/personnel/mensagens` com:

- listagem de rascunhos, agendadas, enviadas, expiradas e arquivadas;
- criação de título e conteúdo;
- destinatário individual ou todos os usuários ativos;
- agendamento opcional;
- expiração opcional;
- pré-visualização e confirmação antes do envio;
- edição e exclusão apenas enquanto estiver em rascunho;
- arquivamento de mensagens já enviadas;
- métricas de destinatários e leituras.

Mensagens automáticas do sistema podem aparecer na listagem apenas como metadados de auditoria. O Setor Pessoal não pode abrir o conteúdo sensível nem reenviar um código.

## Modelo de dados

### `in_app_messages`

| Campo | Tipo | Regra |
|---|---|---|
| `id` | ULID | chave primária |
| `type` | string | `administrative`, `system`, `app_unlock_code` |
| `status` | string | `draft`, `scheduled`, `sent`, `archived` |
| `title` | string | obrigatório |
| `body` | text nullable | conteúdo comum, nunca armazena o código |
| `sensitive_payload` | text nullable | cast `encrypted:array`; contém o código somente quando necessário |
| `audience` | string | `user` ou `all` |
| `created_by` | FK nullable | usuário do Setor Pessoal; nulo para mensagens automáticas |
| `source_type` | string nullable | tipo da entidade que originou a mensagem |
| `source_id` | string nullable | ID do desafio ou outra entidade |
| `scheduled_at` | timestampTz nullable | envio futuro |
| `published_at` | timestampTz nullable | momento efetivo do envio |
| `expires_at` | timestampTz nullable | validade |
| `created_at`, `updated_at`, `deleted_at` | timestamps | soft delete e auditoria |

Índices recomendados: `type`, `status`, `published_at`, `expires_at` e índice composto em `source_type/source_id`.

### `in_app_message_recipients`

| Campo | Tipo | Regra |
|---|---|---|
| `id` | ULID | chave primária |
| `message_id` | FK | cascade no rascunho; preservar mensagens publicadas via soft delete |
| `user_id` | FK | destinatário |
| `read_at` | timestampTz nullable | controle de leitura |
| `dismissed_at` | timestampTz nullable | ocultação pelo destinatário |
| `created_at`, `updated_at` | timestamps | auditoria |

Criar restrição única em `message_id/user_id`. Ao publicar para todos, materializar uma entrega por usuário ativo. Isso garante contagem, leitura e auditoria consistentes.

## Permissões Spatie

Adicionar ao `RolesAndPermissionsSeeder`:

- `messages.view-own`: central e sino do próprio usuário;
- `messages.manage`: listar e editar rascunhos administrativos;
- `messages.send`: publicar ou agendar mensagens;
- `messages.archive`: arquivar mensagens enviadas;
- `messages.view-metrics`: consultar destinatários e métricas agregadas.

Distribuição sugerida:

- `colaborador`, `gestor`, `rh-analista`, `rh-gestor`, `dp-analista`, `dp-gestor` e `administrador`: `messages.view-own`;
- `dp-analista`: `messages.manage`, `messages.send` e `messages.view-metrics`;
- `dp-gestor`: herda as permissões do analista e recebe `messages.archive`;
- `super-admin`: acesso irrestrito via `Gate::before`, sem duplicar autorização.

Mesmo usuários administrativos só acessam entregas próprias pelo endpoint do sino. O endpoint do CRUD nunca serializa `sensitive_payload`.

## Rotas web

Rotas do usuário, protegidas por `auth`, validação WorkOS em produção e `permission:messages.view-own`:

```text
GET   /mensagens
GET   /mensagens/{recipient}
PATCH /mensagens/{recipient}/lida
PATCH /mensagens/{recipient}/nao-lida
POST  /mensagens/marcar-todas-lidas
GET   /mensagens/resumo
```

`/mensagens/resumo` retorna somente a contagem e as cinco entregas recentes para o sino. Todos os acessos devem começar pela relação do usuário autenticado, nunca por uma consulta global seguida de autorização tardia.

Rotas administrativas, protegidas pelas permissões correspondentes:

```text
GET    /personnel/mensagens
POST   /personnel/mensagens
GET    /personnel/mensagens/{message}
PATCH  /personnel/mensagens/{message}
DELETE /personnel/mensagens/{message}
POST   /personnel/mensagens/{message}/enviar
POST   /personnel/mensagens/{message}/arquivar
```

## Serviços e responsabilidades

### `InAppMessageService`

- cria rascunhos administrativos;
- publica para um usuário ou para todos dentro de transação;
- cria as entregas sem duplicidade;
- agenda e arquiva mensagens;
- não recebe nem retorna conteúdo sensível nas operações administrativas.

### `AppUnlockNotificationService`

- recebe `User`, `WhatsAppUnlockChallenge` e o código em memória;
- cria uma mensagem automática destinada somente ao usuário;
- salva o código em `sensitive_payload` com cast criptografado;
- usa `source_type = WhatsAppUnlockChallenge::class` e `source_id` para idempotência;
- define `expires_at` igual ao desafio;
- nunca registra o código em log.

### Integração com `WhatsAppCodeService`

Após criar o desafio para um telefone verificado:

1. criar a notificação interna com o código;
2. tentar o envio pelo driver WhatsApp configurado;
3. manter a notificação interna mesmo quando o driver for `fake` ou o provedor falhar;
4. não deixar falha do canal externo impedir a criação do desafio e da mensagem interna;
5. registrar apenas o status técnico do envio externo, sem telefone completo e sem código.

A mensagem sugerida é:

```text
Título: Código de acesso ao aplicativo
Mensagem: Use o código abaixo para liberar o aplicativo Chags Ponto.
Código: 123456
Validade: 5 minutos
```

O código vem exclusivamente do payload criptografado e só é incluído na resposta destinada ao proprietário da entrega.

## Atualização do sino

### Primeira versão

- compartilhar no primeiro carregamento Inertia apenas `unreadCount` e as cinco mensagens recentes;
- atualizar `/mensagens/resumo` a cada 30 segundos enquanto a aba estiver visível;
- pausar o polling quando `document.visibilityState !== 'visible'`;
- atualizar imediatamente após marcar uma mensagem como lida;
- evitar uma consulta por mensagem usando eager loading e índices.

### Evolução futura

- emitir evento após publicação;
- usar Laravel Reverb/Echo para atualização em tempo real;
- adicionar Web Push ou FCM apenas quando for necessário notificar fora do painel aberto.

## Agendamento e limpeza

Criar comandos agendados:

- publicar mensagens com `status = scheduled` e `scheduled_at <= now()`;
- remover `sensitive_payload` de mensagens expiradas;
- excluir definitivamente mensagens automáticas expiradas após o período de auditoria definido;
- manter mensagens administrativas conforme a política de retenção da empresa.

O scheduler deve executar no servidor por cron ou worker apropriado. A expiração deve ser aplicada também na consulta, sem depender apenas do comando de limpeza.

## Validações administrativas

- título: obrigatório, máximo de 150 caracteres;
- corpo: obrigatório para mensagens administrativas, máximo definido pelo produto;
- destinatário: obrigatório quando `audience = user`;
- destinatário deve ser um usuário ativo;
- agendamento deve estar no futuro;
- expiração deve ser posterior ao envio/agendamento;
- mensagens publicadas são imutáveis; correções devem gerar uma nova mensagem;
- envio para todos exige tela de confirmação com quantidade de destinatários.

## Auditoria e privacidade

- registrar criador, data de publicação, audiência e quantidade de destinatários;
- registrar em auditoria toda exclusão feita pelo destinatário, sem armazenar conteúdo sensível no evento;
- não registrar códigos, payloads sensíveis ou telefones completos;
- métricas administrativas mostram apenas totais agregados;
- impedir serialização acidental de `sensitive_payload` usando atributo oculto no model e Resource específico para o usuário;
- aplicar rate limit aos endpoints de resumo e leitura;
- usar policies e middleware Spatie, incluindo testes contra acesso horizontal por alteração de IDs.

## Testes obrigatórios

### Feature

- usuário vê somente as próprias mensagens;
- usuário não consegue acessar entrega de outro usuário;
- badge conta apenas mensagens próprias, não lidas, publicadas e não expiradas;
- marcar uma entrega como lida atualiza a contagem;
- Setor Pessoal autorizado cria rascunho individual e global;
- usuário sem permissão não acessa o CRUD;
- mensagem global cria uma entrega por usuário ativo sem duplicação;
- mensagem enviada não pode ser editada ou excluída diretamente;
- CRUD administrativo nunca retorna payload sensível;
- solicitação válida do app cria desafio e mensagem interna;
- telefone desconhecido não cria entrega e mantém resposta indistinguível;
- código interno expira junto com o desafio;
- código expirado deixa de ser retornado mesmo antes da limpeza física;
- falha do provedor WhatsApp não impede a notificação interna;
- reenvio respeita rate limit e não deixa dois códigos ativos visíveis sem indicação clara do mais recente.

### Frontend

- sino sem badge quando não há mensagens;
- badge e dropdown refletem novas mensagens;
- ação de leitura atualiza a interface;
- código possui ação de copiar e exibe validade;
- estados vazio, carregando, expirado e erro são acessíveis;
- navegação por teclado e rótulos ARIA no dropdown.

## Ordem de implementação

1. migrations, models, relacionamentos, casts criptografados e factories;
2. permissões Spatie, policies e seeder;
3. serviços de publicação e entrega;
4. integração automática no `WhatsAppCodeService`;
5. controllers, requests, resources e rotas do usuário;
6. compartilhamento inicial Inertia e polling do sino;
7. central de mensagens do usuário;
8. CRUD do Setor Pessoal;
9. scheduler de publicação e limpeza;
10. testes de segurança, integração e interface;
11. Swagger da API mobile, documentando apenas eventual metadado de entrega — nunca o código em endpoints públicos.

## Critérios de aceite

- o Setor Pessoal envia mensagens individuais e globais com autorização Spatie;
- o sino mostra contagem e mensagens reais, sem conteúdo demonstrativo fixo;
- o usuário consegue ler no painel web o código solicitado pelo próprio telefone;
- nenhum funcionário do Setor Pessoal consegue consultar códigos de outros usuários;
- códigos são criptografados, expiram e nunca aparecem em logs;
- telefone desconhecido continua sem revelar existência de cadastro;
- a indisponibilidade da Evolution API não impede o fluxo pelo painel web;
- testes automatizados cobrem autorização, privacidade, expiração e integração mobile.
