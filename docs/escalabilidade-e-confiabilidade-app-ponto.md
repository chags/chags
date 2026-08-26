# Escalabilidade e confiabilidade do aplicativo de ponto

## Objetivo

Este documento registra os impactos esperados com o crescimento do aplicativo de ponto, os riscos existentes no fluxo atual e as mudanças necessárias para evitar lentidão, duplicidade de batidas e perda aparente de requisições.

O cenário de referência é de **até 5.000 funcionários por empresa**, com concentração de acessos nos horários de entrada, início e fim do intervalo e saída. A arquitetura deve permitir ajuste de capacidade conforme o número de empresas e funcionários ativos.

## Premissas

- Ter 5.000 usuários cadastrados em uma empresa não significa ter 5.000 requisições simultâneas, mas os horários de ponto podem concentrar uma parcela relevante deles em poucos minutos.
- O horário oficial da batida é sempre definido pela API, no fuso configurado no servidor.
- Ao abrir o aplicativo, são consultados o usuário autenticado e o estado das batidas do dia.
- O cartão mensal só é consultado ao abrir a tela, trocar o mês ou atualizar manualmente.
- O aplicativo não possui cache offline nem sincronização em segundo plano atualmente.
- PostgreSQL é o banco de dados de produção.
- Cada empresa possui infraestrutura cloud/VPS própria; API, banco, cache, filas e arquivos não são compartilhados entre empresas.

## Fluxo atual

### Inicialização do aplicativo

1. Recupera o token salvo no armazenamento seguro.
2. Confirma se o dispositivo está registrado.
3. Consulta os dados do usuário.
4. Consulta as batidas e o próximo tipo de registro do dia.

Assim, 5.000 usuários abrindo o aplicativo no mesmo intervalo podem gerar aproximadamente 10.000 requisições principais, além de eventuais verificações do dispositivo.

### Registro de ponto

O endpoint de batida atualmente possui:

- transação no banco de dados;
- chave de idempotência;
- restrição única da chave por usuário e rota;
- limite de 10 requisições por minuto por usuário;
- horário atribuído pelo servidor;
- armazenamento da resposta associada à chave de idempotência.

Essas proteções são importantes, mas ainda não cobrem todos os casos de concorrência e instabilidade de rede.

## Riscos identificados

### R01 — Nova chave após resposta perdida

**Cenário:** a API registra a batida, mas a conexão cai antes de o aplicativo receber a resposta. Ao tocar novamente, o aplicativo cria uma nova chave de idempotência.

**Impacto:** a nova requisição pode registrar o próximo tipo de batida, embora o usuário pretendesse apenas repetir a solicitação anterior.

**Prioridade:** crítica.

### R02 — Requisições concorrentes com chaves diferentes

**Cenário:** toque duplo, dois aparelhos ou duas solicitações quase simultâneas consultam o mesmo próximo tipo antes da primeira transação terminar.

**Impacto:** duplicidade do mesmo tipo de batida ou avanço incorreto da jornada.

**Prioridade:** crítica.

### R03 — Pico de acessos

**Cenário:** muitos usuários abrem o aplicativo ou registram o ponto no mesmo minuto.

**Impacto:** esgotamento de processos PHP-FPM, conexões do PostgreSQL, CPU ou memória; aumento do tempo de resposta e ocorrência de timeouts.

**Prioridade:** alta.

### R04 — Dependência excessiva do PostgreSQL

**Cenário:** sessões, cache, filas, rate limiting e dados transacionais concorrem pelos mesmos recursos do banco.

**Impacto:** maior contenção e degradação durante os horários de pico.

**Prioridade:** alta.

### R05 — Falta de operação offline

**Cenário:** usuário sem internet no momento da batida.

**Impacto:** a batida não é enviada. Uma fila offline sem regras de segurança também poderia permitir horário adulterado ou envio fora da janela permitida.

**Prioridade:** média. Deve ser tratada como uma funcionalidade própria, e não como repetição automática comum.

### R06 — Falta de observabilidade consolidada

**Cenário:** erros e lentidão acontecem sem métricas, identificador de requisição ou alertas centralizados.

**Impacto:** demora para diferenciar problema do aparelho, rede, PHP-FPM, API ou banco.

**Prioridade:** alta.

## Mudanças propostas

### 1. Idempotência persistente no aplicativo

- Gerar a chave antes de enviar a batida.
- Salvar a chave e o estado da operação no armazenamento seguro/local.
- Reutilizar a mesma chave em toda repetição da mesma intenção.
- Criar outra chave somente após resposta definitiva de sucesso ou erro funcional não recuperável.
- Ao receber timeout, consultar o estado da operação antes de permitir uma nova batida.

**Resultado esperado:** uma resposta perdida não cria uma segunda intenção de batida.

### 2. Serialização da jornada por usuário e dia

- Executar a decisão e a gravação dentro da mesma transação.
- Adquirir bloqueio pessimista para o usuário/jornada diária antes de calcular o próximo tipo.
- Manter o bloqueio até concluir a gravação e a resposta idempotente.
- Avaliar uma tabela de estado diário da jornada para fornecer uma linha clara de bloqueio.

**Resultado esperado:** apenas uma requisição por usuário altera a jornada por vez.

### 3. Restrição de unicidade no banco

- Criar uma data de trabalho normalizada conforme o fuso de negócio.
- Adicionar restrição única compatível com as regras do produto, por exemplo:
  `user_id + work_date + type`, considerando como tratar registros cancelados e ajustes.
- Definir explicitamente como horas extras e múltiplos intervalos serão representados antes da migration.

**Resultado esperado:** mesmo diante de erro de aplicação ou concorrência, o banco impede duplicidades proibidas.

> A migration não deve ser criada até confirmar se o domínio permitirá múltiplos intervalos ou múltiplas jornadas no mesmo dia.

### 4. Repetição segura de rede

- Repetir automaticamente apenas erros transitórios: timeout, falha de conexão e respostas 502, 503 ou 504.
- Utilizar a mesma chave de idempotência.
- Aplicar espera progressiva com variação aleatória.
- Não repetir automaticamente erros 401, 403, 409, 422 ou 429 sem uma regra específica.
- Informar ao usuário quando o resultado estiver sendo confirmado.

### 5. Redis e separação de responsabilidades

- Usar Redis para cache, filas e rate limiting.
- Manter o PostgreSQL como fonte transacional das batidas.
- Configurar workers de fila supervisionados.
- Não colocar a gravação principal da batida em fila: a confirmação precisa ser transacional e imediata.

### 6. Ajustes de infraestrutura

- Dimensionar processos PHP-FPM conforme CPU e memória disponíveis.
- Definir limites coerentes de conexões entre PHP-FPM e PostgreSQL.
- Habilitar OPcache em produção.
- Manter caches do Laravel gerados no deploy.
- Configurar timeouts coerentes no Nginx, PHP-FPM e aplicativo.
- Avaliar pool de conexões do PostgreSQL, como PgBouncer, conforme o resultado do teste de carga.

### 7. Observabilidade

Registrar, sem dados sensíveis:

- identificador da requisição;
- usuário e dispositivo;
- chave de idempotência ou seu hash;
- tipo pretendido e tipo registrado;
- duração total e duração das consultas;
- status HTTP;
- ocorrência de timeout, conflito ou repetição;
- uso de CPU, memória, processos PHP-FPM e conexões do banco.

Criar alertas para:

- aumento de respostas 5xx;
- p95 e p99 acima das metas;
- saturação do PHP-FPM;
- esgotamento de conexões;
- deadlocks e violações de unicidade;
- crescimento anormal da taxa de repetição.

## Plano de implementação

### Fase 1 — Integridade crítica

- [x] Persistir e reutilizar no aplicativo a chave de idempotência e o tipo esperado da batida.
- [x] Reconciliar uma operação incerta repetindo a mesma intenção e consultando o estado atual após a confirmação.
- [x] Bloquear a linha do usuário durante decisão e gravação da batida.
- [ ] Executar teste paralelo real para a mesma pessoa em PostgreSQL, além dos testes automatizados de repetição e intenção obsoleta já implementados.
- [x] Definir a regra de unicidade como uma batida ativa por usuário, data de trabalho e tipo.
- [x] Adicionar a restrição parcial no banco, permitindo nova tentativa quando o registro anterior estiver cancelado.
- [x] Exigir que a intenção enviada pelo app corresponda ao próximo tipo calculado pela API.
- [x] Validar hora extra depois da saída normal e com duração máxima de 120 minutos.

### Fase 2 — Capacidade e estabilidade

- [x] Configurar Redis para cache, filas e rate limiting.
- [x] Revisar PHP-FPM, OPcache e conexões do PostgreSQL.
- [x] Criar teste de carga com múltiplas identidades e cenários de abertura e gravação.
- [x] Ajustar os limites locais com base nos resultados medidos.
- [ ] Repetir os testes na VPS de homologação com 4 vCPU e 6 GB de RAM e confirmar os limites finais.

### Fase 3 — Operação e observabilidade

- [ ] Propagar identificador único em cada requisição.
- [ ] Criar métricas e painel de saúde.
- [ ] Criar alertas e procedimento de resposta a incidentes.
- [ ] Definir retenção e limpeza das chaves de idempotência expiradas.

### Fase 4 — Offline, se aprovado

- [ ] Definir regras legais e operacionais para batida sem internet.
- [ ] Registrar horário do aparelho apenas como evidência, nunca como horário oficial sem validação.
- [ ] Assinar localmente a intenção com a chave do dispositivo.
- [ ] Registrar horário monotônico, integridade do aparelho e momento da sincronização.
- [ ] Exigir revisão quando a política de risco determinar.

## Plano de teste de carga

Testar separadamente, adotando inicialmente uma janela crítica de cinco minutos para os 5.000 funcionários até existirem métricas reais:

1. 5.000 usuários cadastrados em uma empresa, sem concorrência relevante.
2. 500 usuários abrindo o aplicativo em 10 segundos.
3. 1.000 usuários registrando ponto em 60 segundos.
4. 5.000 usuários abrindo e registrando ponto em uma janela de cinco minutos.
5. Repetição da mesma chave após timeout simulado.
6. Duas chaves diferentes simultâneas para o mesmo usuário.
7. Queda da conexão após o commit e antes da resposta.
8. Indisponibilidade temporária do Redis e do PostgreSQL.

Os dados usados no teste devem representar usuários e dispositivos fictícios e não devem atingir o ambiente produtivo sem janela e autorização operacional.

## Critérios iniciais de aceite

- Nenhuma batida perdida após confirmação HTTP de sucesso.
- Nenhuma duplicidade proibida em requisições concorrentes.
- Repetir a mesma chave retorna exatamente o resultado original.
- Timeout após commit pode ser reconciliado sem criar outra batida.
- Taxa de erro inferior a 1% durante o teste-alvo.
- p95 de leitura da tela inicial inferior a 1 segundo.
- p95 do registro de ponto inferior a 1,5 segundo.
- Recuperação controlada após pico, sem filas ou conexões permanentemente presas.

As metas de latência devem ser revisadas após medir a infraestrutura real de produção.

## Resultado do teste local inicial

Executado em 26/08/2026 contra a API local via Nginx, usando o endpoint autenticado `GET /api/v1/time-punch/status`:

| Métrica | Resultado |
| --- | ---: |
| Requisições | 5.000 |
| Concorrência | 100 |
| Duração | 71,477 s |
| Vazão | 69,95 req/s |
| HTTP 200 | 5.000 |
| Erros | 0 |
| Latência mínima | 129,67 ms |
| Latência p50 | 708,31 ms |
| Latência p95 | 1.259,36 ms |
| Latência p99 | 1.974,33 ms |
| Latência máxima | 2.555,64 ms |

O teste superou a vazão média de aproximadamente 17 requisições por segundo necessária para distribuir 5.000 acessos em cinco minutos, sem erros. O p95 ficou abaixo da meta inicial de 1,5 segundo, mas o p99 ficou próximo de 2 segundos.

Esse resultado foi a linha de base anterior aos ajustes da Fase 2, usando somente um usuário/dispositivo e o endpoint de leitura do estado diário.

O teste pode ser repetido dentro do contêiner com:

```bash
php scripts/load-test-mobile.php 5000 100 http://nginx/api/v1 5000 status
```

O script cria usuários e dispositivos temporários, não imprime tokens ou credenciais e remove os dados criados ao finalizar, inclusive em caso de falha.

## Resultado local após a Fase 2

Executado em 26/08/2026 via Nginx local, com concorrência 100 e 5.000 identidades distintas:

| Cenário | Requisições | Resultado HTTP | Vazão | p50 | p95 | p99 | Erros |
| --- | ---: | --- | ---: | ---: | ---: | ---: | ---: |
| Estado diário | 5.000 | 5.000 × 200 | 103,46 req/s | 548,19 ms | 871,25 ms | 1.019,16 ms | 0 |
| Abertura (`/me` + estado) | 10.000 | 10.000 × 200 | 111,67 req/s | 509,40 ms | 820,50 ms | 1.000,12 ms | 0 |
| Registro de entrada | 5.000 | 5.000 × 201 | 64,90 req/s | 850,71 ms | 1.472,25 ms | 1.688,87 ms | 0 |

Todos os cenários atenderam aos critérios iniciais de erro, p95 de leitura e p95 de gravação. A limpeza final confirmou que não permaneceram usuários temporários nem chaves de idempotência órfãs.

Os limites locais configurados são: PHP-FPM dinâmico com até 24 filhos e reciclagem após 1.000 requisições; OPcache de 256 MB; PostgreSQL com `shared_buffers` de 1 GB, `effective_cache_size` de 3 GB e até 100 conexões; Redis com AOF `everysec`, limite de 512 MB e política `noeviction`; e um worker de fila dedicado.

Esses números comprovam o comportamento no ambiente local, mas não garantem por si só a capacidade da VPS contratada. A homologação deve repetir os três cenários enquanto mede CPU, memória, processos PHP-FPM e conexões do PostgreSQL. Se a aplicação for recriada no Docker, o Nginx também deve ser recarregado ou recriado para resolver o novo endereço do contêiner.

Exemplos dos outros cenários:

```bash
php scripts/load-test-mobile.php 10000 100 http://nginx/api/v1 5000 startup
php scripts/load-test-mobile.php 5000 100 http://nginx/api/v1 5000 punch
```

## Estratégia de implantação

1. Implementar integridade e testes automatizados.
2. Aplicar migrations compatíveis com os dados existentes.
3. Publicar a API antes do aplicativo que depende do novo protocolo.
4. Liberar o aplicativo para um grupo piloto.
5. Acompanhar métricas nos horários de pico.
6. Expandir gradualmente até toda a base.
7. Manter plano de reversão da aplicação, sem remover dados de batidas já confirmadas.

## Modelo de isolamento por empresa

- Cada empresa terá sua própria VPS e sua própria instalação da aplicação.
- Cada instalação terá banco PostgreSQL, Redis, processos PHP-FPM, filas, logs e backups próprios.
- A configuração-base por empresa será de **4 vCPU, 6 GB de memória RAM e 100 GB de armazenamento NVMe**.
- Uma indisponibilidade ou pico de uma empresa não deve consumir recursos de outra.
- Atualizações poderão usar o mesmo pacote de versão, mas serão implantadas e verificadas por ambiente.
- O dimensionamento será feito por faixa de funcionários e confirmado por teste de carga na VPS contratada.
- O monitoramento e os alertas deverão identificar explicitamente a empresa/instalação afetada.
- Backups e restauração serão executados individualmente por empresa.

## Decisões definidas

- [x] **Intervalos e ausências:** a jornada normal permite exatamente quatro batidas: entrada, início do intervalo, fim do intervalo e saída. Não são permitidos múltiplos intervalos. Uma saída temporária, como consulta médica, será registrada separadamente por um par manual `absence_start` e `absence_end`, acompanhado de justificativa. Essas batidas não substituem o intervalo regular. Registros fora do horário previsto pertencem ao fluxo de horas extras.
- [x] **Múltiplas jornadas:** o modelo atual permite somente uma jornada completa por dia. Duas ou mais jornadas no mesmo dia não serão aceitas no fluxo padrão. Essa possibilidade exigirá futuramente um modelo de escala personalizado, com segmentos e regras definidos previamente; o sistema não deve inferir uma segunda jornada apenas pela existência de novas batidas.
- [x] **Hora extra:** será permitido apenas um par `overtime_start` e `overtime_end` por usuário e dia, sempre depois da saída normal, com duração máxima de 120 minutos. A configuração de escala já possui `daily_overtime_limit_minutes` (máximo de 120) e `requires_overtime_approval` (padrão verdadeiro), mas a duração ainda não é validada no serviço de aprovação. Também faltam a garantia global de um único par diário e a validação de início posterior a `clock_out`. Antes da produção, essas regras deverão ser aplicadas no request/serviço, protegidas contra concorrência e cobertas por testes de rejeição.
- [x] **Aprovação da hora extra:** o colaborador registra ou solicita o par de hora extra, que permanece pendente. Somente o gestor responsável pode aprovar ou rejeitar na tela de validação. O período só será creditado no banco de horas depois da aprovação do gestor. Não haverá aprovação automática pelo aplicativo nem crédito enquanto a solicitação estiver pendente.
- [x] **Operação offline:** o aplicativo não registrará nem enfileirará batidas sem internet. Ele deverá informar claramente que está offline e orientar o colaborador a tentar novamente. Se a conexão retornar depois do horário da batida, o colaborador poderá criar uma solicitação manual informando o horário e a justificativa padronizada `problema_tecnico`. A solicitação permanecerá pendente e somente será incorporada ao cartão após aprovação do gestor. O horário do aparelho não será tratado como horário oficial.
- [x] **Capacidade por empresa:** o sistema deve suportar empresas com até 5.000 funcionários e permitir ajuste de infraestrutura conforme o crescimento. Até existirem métricas reais, o teste de referência considerará os 5.000 usuários concentrados em uma janela de cinco minutos nos horários de pico.
- [x] **Infraestrutura-base:** cada empresa terá VPS própria com 4 vCPU, 6 GB de RAM e 100 GB NVMe, além de banco e API exclusivos. Essa configuração será validada para a meta de até 5.000 funcionários por meio do teste de carga; aumento de recursos será orientado pelas métricas de CPU, memória, latência, processos PHP-FPM e conexões do PostgreSQL.
- [x] **Isolamento entre empresas:** cada empresa terá estrutura cloud/VPS própria. Não haverá compartilhamento da API, PostgreSQL, Redis ou capacidade computacional entre empresas.

Essas decisões orientam a implementação da integridade, da migration de unicidade, do fluxo de aprovação e dos testes de carga.
