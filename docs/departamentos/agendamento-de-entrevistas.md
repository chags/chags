# Agendamento das entrevistas — fases 4, 5 e 6

## 1. Objetivo

Transformar o botão **Avaliações** do RH em uma central para preparar, agendar, comunicar e avaliar as entrevistas das candidaturas.

O módulo deverá:

- criar ou registrar a reunião da entrevista;
- convidar candidato e entrevistadores;
- enviar a convocação por e-mail e permitir que o RH abra o WhatsApp com uma mensagem pronta;
- mostrar o compromisso no portal institucional do candidato;
- permitir confirmação, cancelamento e reagendamento;
- liberar o formulário de avaliação correspondente;
- manter histórico e auditoria sem expor pareceres internos ao candidato.

## 2. Numeração pública das fases

O fluxo exibido ao candidato considera as duas etapas iniciais do portal. Por isso, a sequência fica:

1. Candidatura recebida;
2. Análise do currículo;
3. Teste comportamental DISC;
4. Entrevista com RH;
5. Entrevista técnica;
6. Entrevista com o gestor da área;
7. Avaliação final.

Este documento trata das fases **4, 5 e 6**. A entrevista técnica pode usar perguntas preparadas por IA, mas o agendamento descrito aqui é de uma reunião com pessoa avaliadora.

## 3. Responsabilidade de cada fase

### Fase 4 — entrevista com RH

- Responsável: Analista ou Gestor de RH.
- Assuntos: trajetória profissional, comunicação, disponibilidade, modelo de trabalho, pretensão e requisitos gerais.
- Resultado: nota de 1 a 5, parecer, pontos favoráveis, riscos profissionais e recomendação.

### Fase 5 — entrevista técnica

- Responsável: profissional técnico indicado ou gestor da área.
- Assuntos: competências da vaga, experiências práticas, cenários e perguntas técnicas.
- Resultado: nota de 1 a 5 por critério, parecer técnico e recomendação.
- A IA pode sugerir três perguntas conforme vaga e currículo, mas não agenda, aprova ou reprova autonomamente.

### Fase 6 — entrevista com gestor da área

- Responsável: gestor requisitante da vaga; cliente externo poderá participar como convidado.
- Assuntos: aderência às atividades, contexto da equipe, desafios, senioridade e expectativas.
- Resultado: nota de 1 a 5, parecer do gestor e recomendação.

O cliente externo não terá acesso ao painel na primeira versão. O gestor interno continuará responsável pelo registro do parecer.

## 4. Jornada recomendada

1. A candidatura é aprovada na fase anterior.
2. O RH abre **Avaliações > Agenda de entrevistas**.
3. Seleciona candidatura e fase.
4. Informa data, horário, duração, fuso horário, formato e participantes.
5. Para entrevista online, escolhe **Criar Google Meet** ou **Informar link manualmente**.
6. O backend grava primeiro o agendamento como `draft`.
7. A integração cria o evento no calendário e solicita um Meet exclusivo.
8. Após receber o link, o sistema marca o agendamento como `scheduled`.
9. O RH revisa a prévia e clica em **Agendar e enviar convite**.
10. E-mail é enfileirado; o botão do WhatsApp fica disponível para envio manual da mesma convocação.
11. O candidato vê data e botão **Confirmar presença** no portal.
12. Confirmação ou pedido de reagendamento é notificado ao RH.
13. Antes da reunião, lembretes são enviados conforme configuração.
14. Após o horário, o avaliador preenche e finaliza a avaliação.
15. Um usuário autorizado decide avançar ou reprovar; a nota não movimenta a candidatura automaticamente.

## 5. Tela do botão Avaliações

Rota proposta: `/hr/evaluations`.

A página terá abas:

### Agenda

- calendário mensal/semanal e lista dos próximos compromissos;
- filtros por empresa, vaga, fase, entrevistador e situação;
- indicadores `Rascunho`, `Aguardando confirmação`, `Confirmada`, `Reagendamento solicitado`, `Concluída`, `Cancelada` e `Falha de integração`;
- botão **Agendar entrevista**;
- ações **Ver**, **Reagendar**, **Cancelar**, **Reenviar convite**, **Copiar link** e **Abrir avaliação**.

### Avaliações pendentes

- entrevistas realizadas sem parecer finalizado;
- prazo para preenchimento;
- responsável e demais participantes;
- botão **Preencher avaliação** ou **Continuar rascunho**.

### Avaliações concluídas

- pareceres finalizados conforme permissão e escopo organizacional;
- filtros por fase, avaliador, vaga e recomendação;
- avaliações finalizadas ficam somente leitura.

## 6. Formulário de agendamento

Campos:

| Campo | Regra |
|---|---|
| Candidatura | obrigatória e ativa |
| Fase | somente RH, técnica ou gestor |
| Título | preenchido automaticamente, editável |
| Data e início | obrigatórios e futuros |
| Duração | padrão 45 ou 60 minutos |
| Fuso horário | padrão da empresa, persistido no registro |
| Formato | online, presencial ou telefone |
| Local | obrigatório para presencial |
| Provedor | Google Meet ou link manual para online |
| Link manual | URL válida quando não houver criação automática |
| Organizador | usuário conectado ao calendário |
| Entrevistadores | um ou mais usuários autorizados |
| Convidados externos | nome e e-mail, opcionais |
| Instruções públicas | visíveis ao candidato |
| Observações internas | nunca enviadas ao candidato |
| Canais | e-mail obrigatório; WhatsApp opcional |
| Lembretes | por exemplo, 24 horas e 1 hora antes |

O sistema deverá avisar sobre conflito de horário do candidato, organizador e entrevistadores internos. Um conflito poderá ser ignorado por Gestor de RH mediante confirmação e auditoria.

## 7. Google Calendar e Google Meet

### Decisão recomendada

Usar **OAuth 2.0 individual por analista**. Cada profissional conecta sua própria conta Google ao perfil do sistema e autoriza somente o acesso necessário ao Calendar. A conta conectada pelo analista que agenda será a organizadora do evento e a pessoa que abrirá e administrará a reunião.

Tokens deverão ser criptografados no banco; segredos do cliente permanecem na configuração segura do ambiente ou em armazenamento criptografado de configurações.

### 7.1. Configuração administrativa única

O super-admin configura uma vez, em **Configurações > Sistema > Google Calendar**:

- `client_id` e `client_secret` do projeto Google Cloud;
- URL de callback de produção;
- domínios corporativos permitidos, quando aplicável;
- duração e lembretes padrão;
- política para contas pessoais Google;
- opção de link manual quando não houver conexão;
- e-mail/calendário corporativo de contingência, se existir.

Essas credenciais identificam a aplicação, não representam a conta de um analista.

### 7.2. Conexão individual do analista

Cada analista verá em seu perfil a seção **Minha agenda Google**:

1. Clica em **Conectar Google Calendar**.
2. O sistema redireciona para a tela oficial de consentimento do Google.
3. O analista escolhe a conta que realmente utilizará nas entrevistas.
4. O callback valida `state`, troca o código pelos tokens e identifica a conta conectada.
5. O analista escolhe o calendário em que os eventos serão criados, normalmente `primary`.
6. O sistema executa um teste e mostra `Conectado como analista@empresa.com`, calendário e data do último teste.

O OAuth deverá pedir acesso `offline`, permitindo renovar o token e executar reagendamentos e cancelamentos mesmo depois que o analista fechar o navegador. Refresh tokens serão criptografados e nunca enviados ao frontend. Referência: [OAuth 2.0 para aplicações web](https://developers.google.com/identity/protocols/oauth2/web-server).

### 7.3. Escolha do organizador

No formulário, o campo **Organizador** terá como padrão o analista logado. Somente usuários com conexão Google válida poderão ser selecionados para criação automática do Meet.

- Analista agenda para si: cria no próprio calendário.
- Gestor de RH agenda para outro analista: seleciona o analista organizador e utiliza a conexão dele, desde que exista autorização interna explícita para isso.
- Avaliadores adicionais: entram como convidados, não como donos da reunião.
- Candidato: entra como convidado pelo e-mail da candidatura.
- Sem conexão válida: usar link manual ou escolher outro organizador conectado.

O sistema deverá deixar claro antes de salvar: **“Esta reunião será criada e administrada por Nome — conta@google.com”**.

### 7.4. Disponibilidade

Antes de confirmar, o sistema consulta `freeBusy` da agenda do organizador e, quando autorizado, dos entrevistadores internos conectados. O retorno deverá indicar apenas livre/ocupado, sem copiar títulos ou descrições de compromissos pessoais. Referência: [consulta de disponibilidade do Google Calendar](https://developers.google.com/workspace/calendar/api/v3/reference/freebusy/query).

O candidato não precisa conectar o Google Calendar. Ele recebe o convite no e-mail informado na candidatura e acompanha o agendamento no portal.

### 7.5. Criação e propriedade da reunião

Ao criar o evento:

- usar `events.insert`;
- enviar início/fim com fuso explícito;
- incluir participantes autorizados;
- usar identificador idempotente para evitar reuniões duplicadas;
- solicitar uma conferência nova com `conferenceData.createRequest`;
- enviar `conferenceDataVersion=1`;
- aguardar o estado de criação da conferência antes de liberar o convite;
- guardar `provider_event_id`, link do evento, link do Meet e estado da sincronização.

Cada entrevista terá um Meet próprio. Não reutilizar links entre candidaturas.

Se o Google falhar, o agendamento continuará salvo como `integration_failed`, sem enviar convite incompleto. O RH poderá tentar novamente ou informar um link manual.

### 7.6. Reagendamento, cancelamento e sincronização

- Reagendamento atualiza o mesmo evento remoto; não cria outro Meet sem necessidade.
- Cancelamento pelo sistema cancela o evento no calendário do organizador e notifica convidados.
- Alterações feitas diretamente no Google poderão ser reconciliadas por sincronização/webhook em uma segunda entrega.
- Se o evento remoto desaparecer, o painel mostra **Evento removido no Google** e solicita ação do RH.
- Toda operação usa `provider_event_id` e chave idempotente para evitar duplicidade.

Na primeira versão, o sistema será a fonte principal do agendamento: mudanças devem ser feitas pelo painel do RH. Isso reduz divergências enquanto a sincronização bidirecional não estiver pronta.

### 7.7. Ausência, férias ou desligamento do analista

Uma reunião do Google pertence ao organizador original e não deve ser simplesmente “transferida” alterando um campo local. Portanto:

- para ausência pontual, outro entrevistador participa como convidado e o link continua válido;
- antes da reunião, um Gestor de RH pode substituir o organizador: o sistema cancela o evento anterior e cria um novo evento/Meet na conta substituta, notificando todos;
- ao desconectar a conta, o sistema lista reuniões futuras e exige escolher **manter**, **substituir organizador** ou **cancelar**;
- ao inativar/desligar um usuário, um processo de pendências identifica reuniões futuras antes de revogar a conexão;
- o refresh token revogado muda a conexão para `reauthorization_required`, mas não apaga dados locais.

Para uma empresa inteiramente no Google Workspace, uma evolução possível é usar conta de serviço com delegação em todo o domínio. Isso exige ação do administrador Google Workspace e concede alcance mais amplo; por segurança e simplicidade, **não será o modelo inicial**. A primeira versão adotará consentimento individual. Referência: [credenciais e delegação no Google Workspace](https://developers.google.com/workspace/guides/create-credentials).

Referência oficial: [criação de eventos e conferências no Google Calendar](https://developers.google.com/workspace/calendar/api/guides/create-events).

## 8. Comunicação com o candidato

### E-mail

Canal padrão e obrigatório, implementado como notificação enfileirada do Laravel após a transação do agendamento ser confirmada.

Conteúdo mínimo:

- nome do candidato e da vaga;
- tipo da entrevista;
- data, horário e fuso;
- duração;
- formato/local;
- botão **Confirmar presença**;
- botão **Entrar na reunião** somente quando houver link;
- instrução para solicitar reagendamento;
- contato do RH.

O sistema também enviará e-mails de reagendamento, cancelamento e lembrete. Referência: [notificações e filas do Laravel](https://laravel.com/docs/13.x/notifications).

### WhatsApp — primeira versão

Não haverá integração com a API da Meta na primeira versão. O painel terá o botão **Enviar pelo WhatsApp**, que abre o aplicativo ou WhatsApp Web no número do candidato com a mensagem já preenchida. O profissional de RH revisa e confirma o envio manualmente.

Formato correto do link:

```text
https://wa.me/NUMERO?text=MENSAGEM_CODIFICADA
```

Exemplo em JavaScript:

```js
const phone = candidatePhone.replace(/\D/g, '');
const whatsappPhone = phone.startsWith('55') ? phone : `55${phone}`;
const url = `https://wa.me/${whatsappPhone}?text=${encodeURIComponent(message)}`;
window.open(url, '_blank', 'noopener,noreferrer');
```

O número deve conter somente dígitos, incluindo código do país e DDD, sem `+`, espaços, parênteses ou hífens.

A mensagem será gerada no backend ou a partir de dados já autorizados na tela e conterá:

- nome do candidato;
- nome da vaga;
- fase da entrevista;
- data, horário e fuso;
- formato ou endereço;
- link da reunião, quando online;
- link do portal para confirmar ou solicitar reagendamento;
- nome/contato do RH.

Regras:

- mostrar o botão somente quando houver telefone válido;
- exigir que o agendamento esteja salvo antes de montar a mensagem;
- não incluir notas, avaliações ou observações internas;
- abrir em nova aba com `noopener,noreferrer`;
- registrar auditoria `interview.whatsapp-opened`, com usuário e horário;
- apresentar a situação **Aberto para envio manual**, nunca **Entregue**, pois o sistema não consegue confirmar que a mensagem foi enviada ou lida;
- manter e-mail e portal como canais oficiais do agendamento.

Uma futura integração com WhatsApp Business Platform/Cloud API será opcional, necessária apenas se a empresa quiser envio automático, modelos aprovados, webhooks e confirmação de entrega.

## 9. Portal do candidato

Na fase atual, o card mostrará:

- `Entrevista aguardando agendamento` quando ainda não houver horário;
- data, horário, fuso, formato e duração após o agendamento;
- situação da confirmação;
- botão **Confirmar presença**;
- botão **Solicitar reagendamento**, com motivo e até o limite configurado;
- botão **Entrar na reunião**, liberado preferencialmente 15 minutos antes;
- instruções públicas do RH.

Observações internas, participantes internos ocultos, notas e avaliações não serão retornados nas props do candidato.

## 10. Tabelas e migrations

### `interview_schedules`

| Campo | Tipo/objetivo |
|---|---|
| `id` | chave primária |
| `application_id` | candidatura |
| `stage_id` | fase RH, técnica ou gestor |
| `organizer_id` | usuário organizador |
| `format` | online, presencial, telefone |
| `provider` | google_meet, manual, presencial, telefone |
| `status` | draft, scheduled, confirmed, reschedule_requested, completed, cancelled, integration_failed |
| `title` | título público |
| `starts_at`, `ends_at` | horário em UTC |
| `timezone` | fuso usado na apresentação |
| `location` | local presencial |
| `meeting_url` | link criptografado ou protegido conforme decisão técnica |
| `provider_event_id` | identificador remoto único |
| `provider_event_url` | URL administrativa do evento |
| `public_instructions` | texto do candidato |
| `internal_notes` | texto confidencial |
| `candidate_response` | pending, accepted, declined, reschedule_requested |
| `candidate_responded_at` | data da resposta |
| `reschedule_reason` | motivo informado pelo candidato |
| `created_by`, `updated_by`, `cancelled_by` | responsáveis |
| `cancelled_at`, `cancellation_reason` | cancelamento auditável |
| timestamps e soft delete | rastreabilidade |

Índices: `application_id + stage_id`, `starts_at`, `status`, `provider_event_id` único quando preenchido.

### `interview_participants`

- `interview_schedule_id`;
- `user_id` opcional;
- `name` e `email` para convidado externo;
- `role`: organizer, interviewer, observer;
- `response_status`;
- timestamps.

### `interview_notification_deliveries`

- agendamento e destinatário;
- canal: mail ou whatsapp_manual;
- tipo: invitation, rescheduled, cancelled, reminder;
- estado: queued, sent, delivered, failed ou opened_manual;
- identificador do provedor;
- tentativa, erro sanitizado e datas;
- payload mínimo necessário, sem guardar conteúdo sensível desnecessário.

### `calendar_connections`

- usuário ou empresa proprietária;
- provedor;
- calendário selecionado;
- access token e refresh token criptografados;
- expiração, escopos e situação;
- data/resultado do último teste.

## 11. Models e relacionamentos

- `InterviewSchedule` pertence a candidatura, fase e organizador.
- `InterviewSchedule` possui participantes e entregas de notificação.
- `Application` possui muitos agendamentos.
- `RecruitmentStage` possui muitos agendamentos.
- `User` possui conexões de calendário e participações.
- `ApplicationEvaluation` referencia opcionalmente `interview_schedule_id`.

Não criar colunas booleanas de cargo. Autorização continuará integralmente em `spatie/laravel-permission` e Policies.

## 12. Services e jobs

- `InterviewSchedulingService`: valida fase, conflitos e cria/reagenda/cancela.
- `CalendarProvider` (contrato): cria, atualiza e remove evento.
- `GoogleCalendarService`: implementação Google Calendar/Meet.
- `InterviewNotificationService`: seleciona canais e modelos.
- `InterviewWhatsAppLinkService`: normaliza o telefone e monta a mensagem/link manual.
- `CreateCalendarEventJob`: integração idempotente.
- `SendInterviewInvitationJob`: convite após reunião pronta.
- `SendInterviewReminderJob`: lembretes devidos.
- `SyncCalendarEventJob`: reconcilia alterações e falhas.

Jobs externos terão tentativas limitadas, backoff e identificadores idempotentes. Nenhum erro externo apagará o agendamento local.

## 13. Controllers, requests e views

### Backend

- `Hr/InterviewScheduleController`: index, store, show, update, cancel e resend.
- `Hr/ApplicationEvaluationController`: rascunho e finalização do parecer.
- `Candidate/InterviewScheduleController`: visualizar, confirmar e solicitar reagendamento.
- Form Requests distintos para criação, reagendamento, cancelamento e resposta do candidato.
- Policies com escopo por empresa, vaga e avaliador.

### Frontend

- `resources/js/pages/hr/evaluations/index.tsx`;
- `resources/js/pages/hr/evaluations/schedule.tsx` ou modal compartilhado;
- `resources/js/pages/hr/evaluations/show.tsx`;
- componente `InterviewScheduleCard` no portal do candidato;
- formulários específicos das entrevistas RH, técnica e gestor.

## 14. Permissões Spatie

- `interviews.view`;
- `interviews.create`;
- `interviews.update`;
- `interviews.cancel`;
- `interviews.send-invitations`;
- `interviews.evaluate-rh`;
- `interviews.evaluate-technical`;
- `interviews.evaluate-manager`;
- `interviews.view-internal-notes`;
- `calendar-integrations.manage`.

`super-admin` no guard `web` mantém acesso irrestrito via Gate. Analista de RH agenda e avalia RH; Gestor de RH administra o fluxo; avaliador técnico e gestor acessam somente entrevistas em que participam ou vagas sob sua responsabilidade.

## 15. Auditoria, LGPD e segurança

- auditar criação, reagendamento, cancelamento, convite, reenvio, confirmação, alteração de link e personificação;
- nunca registrar tokens, credenciais ou link completo em logs comuns;
- criptografar credenciais e limitar a exposição do link ao candidato da candidatura;
- usar links/ações assinados e com validade para respostas por e-mail;
- validar novamente a propriedade da candidatura no portal autenticado;
- aplicar retenção e anonimização conforme política de recrutamento;
- não enviar parecer, nota ou motivo interno em convites.

## 16. Testes obrigatórios

- RH autorizado agenda cada uma das fases 4, 5 e 6;
- fase incorreta ou candidatura encerrada não aceita agendamento;
- horários são persistidos em UTC e apresentados no fuso correto;
- conflito é detectado;
- criação do Meet é idempotente e não duplica evento após retry;
- falha do Google preserva rascunho e não envia convite sem link;
- convite por e-mail é enfileirado somente após commit;
- botão do WhatsApp só aparece com telefone válido e nunca informa entrega automática;
- candidato só vê e responde ao próprio agendamento;
- candidato não recebe observações internas;
- confirmação, reagendamento e cancelamento geram auditoria;
- entrevista concluída libera o formulário correto;
- avaliação finalizada não é editável pela operação comum;
- reprovação encerra reuniões futuras ou solicita confirmação para cancelá-las;
- super-admin personificado gera auditoria com ator e personificador.

## 17. Ordem de implementação

1. Criar tabelas, models, Policies, permissões e auditoria.
2. Implementar agenda com link manual, e-mail e portal do candidato.
3. Implementar confirmação, reagendamento, cancelamento e lembretes.
4. Conectar Google Calendar/Meet por OAuth.
5. Implementar formulários de avaliação das fases 4, 5 e 6.
6. Opcionalmente, integrar WhatsApp Cloud API e webhooks se futuramente houver necessidade de envio automático.
7. Adicionar calendário visual, métricas e tratamento de conflitos avançado.

Essa ordem entrega primeiro um fluxo utilizável mesmo sem credenciais externas e permite ativar Google Meet e WhatsApp sem bloquear o recrutamento.
