# E-mails automáticos

## Objetivo

Centralizar os e-mails transacionais da aplicação em templates editáveis, enviados automaticamente a partir de eventos do processo seletivo e auditados por entrega.

O primeiro escopo cobre a jornada do candidato, desde a criação da conta e candidatura até a contratação, reprovação ou desistência.

## Regras gerais

- Cada template possui um código técnico imutável, nome, assunto, conteúdo, situação ativa/inativa e lista de variáveis permitidas.
- Administradores podem editar assunto, conteúdo e ativação pelo CRUD, mas não podem criar códigos arbitrários para eventos inexistentes.
- O sistema deve escapar valores inseridos nas variáveis e rejeitar variáveis desconhecidas.
- Disparos são enfileirados após a confirmação da transação no banco.
- Cada entrega registra destinatário, template, candidatura, estado, tentativas, erro e data de envio.
- Uma chave de idempotência impede o mesmo evento de gerar e-mails duplicados.
- Falha no envio não deve desfazer uma candidatura ou mudança de etapa.
- O link principal deve levar à candidatura no portal do candidato.
- Templates inativos não são enviados, mas o evento deve ficar registrado como ignorado.
- Produção usa o SMTP configurado no sistema; desenvolvimento usa Mailpit.

## Variáveis compartilhadas

| Variável | Conteúdo |
|---|---|
| `{{candidate_name}}` | Nome do candidato |
| `{{candidate_email}}` | E-mail do candidato |
| `{{job_title}}` | Título da vaga |
| `{{company_name}}` | Nome da empresa |
| `{{application_url}}` | Link para acompanhar a candidatura |
| `{{current_stage}}` | Nome público da etapa atual |
| `{{previous_stage}}` | Nome público da etapa anterior |
| `{{support_email}}` | E-mail de contato configurado |

Variáveis específicas de entrevistas: `{{interview_date}}`, `{{interview_time}}`, `{{interview_timezone}}`, `{{interview_format}}`, `{{interview_location}}` e `{{interview_url}}`.

## Catálogo inicial

### `candidate.first_access`

**Evento:** criação da primeira conta do candidato.

**Assunto:** Bem-vindo ao portal de carreiras da {{company_name}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Sua conta foi criada com sucesso. No portal do candidato você pode acompanhar suas candidaturas, realizar avaliações e responder aos convites de entrevista.
>
> Acesse o portal: {{application_url}}

### `application.received`

**Evento:** candidatura criada.

**Assunto:** Recebemos sua candidatura para {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Recebemos sua candidatura para a vaga de {{job_title}}. Seu perfil seguirá para análise e avisaremos por e-mail sempre que houver uma atualização importante.
>
> Acompanhe sua candidatura: {{application_url}}

### `application.stage_changed`

**Evento:** alteração da etapa atual sem ação específica do candidato.

**Assunto:** Sua candidatura para {{job_title}} avançou

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Sua candidatura avançou para a etapa “{{current_stage}}”. Você pode acompanhar os detalhes e as próximas orientações no portal.
>
> Acompanhe sua candidatura: {{application_url}}

### `application.disc_available`

**Evento:** etapa atual passa a exigir o teste DISC.

**Assunto:** Teste comportamental disponível — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> O teste comportamental DISC está disponível para sua candidatura. Reserve alguns minutos, responda com tranquilidade e conclua todas as questões.
>
> Iniciar o teste: {{application_url}}

### `application.disc_completed`

**Evento:** candidato conclui o teste DISC.

**Assunto:** Teste comportamental concluído — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Recebemos suas respostas do teste comportamental. Não é necessário realizar nenhuma ação agora; avisaremos quando houver uma nova etapa.
>
> Acompanhe sua candidatura: {{application_url}}

### `application.interview_scheduled`

**Evento:** entrevista agendada.

**Assunto:** Entrevista agendada — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Sua entrevista da etapa “{{current_stage}}” foi agendada para {{interview_date}}, às {{interview_time}} ({{interview_timezone}}).
>
> Consulte os detalhes e confirme sua participação: {{application_url}}

### `application.interview_rescheduled`

**Evento:** data, horário ou local de entrevista alterado.

**Assunto:** Sua entrevista foi reagendada — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Sua entrevista foi reagendada para {{interview_date}}, às {{interview_time}} ({{interview_timezone}}). Consulte os dados atualizados no portal.
>
> Ver entrevista: {{application_url}}

### `application.interview_cancelled`

**Evento:** entrevista cancelada.

**Assunto:** Atualização sobre sua entrevista — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> A entrevista anteriormente agendada foi cancelada. Caso seja necessário um novo agendamento, você receberá outra comunicação.
>
> Acompanhe sua candidatura: {{application_url}}

### `application.action_required`

**Evento:** qualquer etapa nova exige uma ação do candidato e não possui template mais específico.

**Assunto:** Uma ação está disponível na sua candidatura — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> A etapa “{{current_stage}}” está disponível e requer sua participação. Consulte as instruções no portal do candidato.
>
> Continuar candidatura: {{application_url}}

### `application.final_review`

**Evento:** candidatura entra em avaliação final.

**Assunto:** Sua candidatura está em avaliação final — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Seu processo chegou à avaliação final. Nossa equipe está consolidando as informações e entraremos em contato assim que houver uma decisão.
>
> Acompanhe sua candidatura: {{application_url}}

### `application.hired`

**Evento:** candidatura alterada para `hired`.

**Assunto:** Você foi aprovado para {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Temos uma ótima notícia: você foi aprovado no processo seletivo para {{job_title}}. Nossa equipe entrará em contato com as orientações para os próximos passos da admissão.
>
> Consulte o portal: {{application_url}}

### `application.rejected`

**Evento:** candidatura alterada para `rejected`.

**Assunto:** Atualização sobre o processo seletivo — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Agradecemos seu interesse e sua participação no processo seletivo para {{job_title}}. Neste momento, seguiremos com outros perfis para a vaga.
>
> Esperamos contar com sua participação em futuras oportunidades.

Uma mensagem individual informada pelo RH poderá ser acrescentada como `{{rejection_message}}`.

### `application.withdrawn`

**Evento:** candidato desiste da candidatura.

**Assunto:** Desistência registrada — {{job_title}}

**Texto padrão:**

> Olá, {{candidate_name}}!
>
> Registramos sua desistência do processo seletivo para {{job_title}}. Agradecemos seu interesse e esperamos encontrar você em futuras oportunidades.

## CRUD

O gerenciamento ficará em **Configurações do sistema → Templates de e-mail** e terá:

- listagem por nome, código, evento e situação;
- edição de assunto e conteúdo;
- ativação e desativação;
- visualização das variáveis aceitas;
- pré-visualização com dados fictícios;
- envio de teste para um destinatário informado;
- restauração do texto padrão;
- histórico de alterações e de entregas.

Permissões Spatie previstas:

- `system.settings.email-templates.view`;
- `system.settings.email-templates.update`;
- `system.settings.email-templates.test`;
- `system.settings.email-deliveries.view`.

## Ordem de implementação

1. Catálogo persistido e seeder idempotente.
2. Renderização segura das variáveis e layout visual comum.
3. CRUD administrativo, pré-visualização e envio de teste.
4. Primeiro acesso e confirmação da candidatura.
5. Mudanças de etapa e ações pendentes.
6. Entrevistas, lembretes, reagendamento e cancelamento.
7. Contratação, reprovação e desistência.
8. Histórico, retentativas e monitoramento da fila.
