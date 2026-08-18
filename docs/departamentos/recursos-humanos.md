# Departamento de Recursos Humanos

## 1. Escopo

O módulo de Recursos Humanos será responsável por:

- cadastro e publicação de vagas;
- cadastro de candidatos;
- candidaturas e etapas do processo seletivo;
- participação do gestor requisitante;
- coleta de dados para admissão;
- conversão de candidato aprovado em colaborador;
- cadastro organizacional do colaborador;
- gestão de setores e cargos profissionais;
- retenção, anonimização e exclusão de dados de candidatos.

Papéis principais: `candidato`, `colaborador`, `gestor`, `rh-analista` e `rh-gestor`.

## 2. Ordem sugerida de implementação

1. Estrutura organizacional compartilhada.
2. Perfis de candidato.
3. Vagas e candidaturas.
4. Etapas, avaliações e histórico.
5. Admissão e conversão em colaborador.
6. Retenção de dados e auditoria.

Especificação complementar: [Processo de avaliações de candidaturas](avaliacoes-de-candidaturas.md).

## 3. Migrations e campos

### `create_departments_table.php`

Tabela de setores profissionais; não confundir com papéis do Spatie.

| Campo         | Tipo               | Regra                 |
| ------------- | ------------------ | --------------------- |
| `id`          | bigint             | chave primária        |
| `company_id`  | foreignId          | empresa responsável   |
| `parent_id`   | foreignId nullable | setor superior        |
| `name`        | string             | nome do setor         |
| `slug`        | string             | único por empresa     |
| `active`      | boolean            | padrão `true`         |
| `timestamps`  | timestamps         | criação e atualização |
| `softDeletes` | timestamp nullable | exclusão lógica       |

Índices: único em `company_id + slug`; índices em `parent_id` e `active`.

### `create_positions_table.php`

| Campo           | Tipo               | Regra                      |
| --------------- | ------------------ | -------------------------- |
| `id`            | bigint             | chave primária             |
| `company_id`    | foreignId          | empresa responsável        |
| `department_id` | foreignId nullable | setor padrão               |
| `title`         | string             | nome do cargo profissional |
| `level`         | string nullable    | senioridade padronizada    |
| `code`          | string nullable    | código interno             |
| `description`   | text nullable      | atribuições gerais         |
| `active`        | boolean            | padrão `true`              |
| `timestamps`    | timestamps         | criação e atualização      |
| `softDeletes`   | timestamp nullable | exclusão lógica            |

Índice único sugerido: `company_id + code`, permitindo `code` nulo conforme o banco utilizado.

O título deve conter somente a função, como `Analista de Sistemas`. O campo `level` aceita `intern`, `junior`, `mid`, `senior`, `specialist`, `lead` ou `manager`. A aplicação monta o nome de exibição, como `Analista de Sistemas Sênior`, sem criar um papel de autorização para cada combinação.

### `create_candidate_profiles_table.php`

| Campo                    | Tipo               | Regra                      |
| ------------------------ | ------------------ | -------------------------- |
| `id`                     | bigint             | chave primária             |
| `user_id`                | foreignId unique   | vínculo com autenticação   |
| `professional_summary`   | text nullable      | resumo profissional        |
| `linkedin_url`           | string nullable    | perfil público             |
| `portfolio_url`          | string nullable    | portfólio                  |
| `city`                   | string nullable    | cidade de interesse        |
| `state`                  | char(2) nullable   | UF                         |
| `availability`           | string nullable    | disponibilidade declarada  |
| `talent_pool_consent_at` | timestamp nullable | consentimento adicional    |
| `talent_pool_expires_at` | timestamp nullable | expiração do consentimento |
| `anonymized_at`          | timestamp nullable | controle de anonimização   |
| `timestamps`             | timestamps         | criação e atualização      |

CPF, telefone e endereço já existentes em `users` não devem ser duplicados nessa tabela.

### `create_recruitment_jobs_table.php`

A tabela será `recruitment_jobs`, pois `jobs` já é reservada pelo Laravel para o sistema de filas.

| Campo               | Tipo               | Regra                                    |
| ------------------- | ------------------ | ---------------------------------------- |
| `id`                | bigint             | chave primária                           |
| `company_id`        | foreignId          | empresa/unidade dona da vaga             |
| `department_id`     | foreignId          | setor solicitante                        |
| `position_id`       | foreignId nullable | cargo relacionado                        |
| `hiring_manager_id` | foreignId nullable | gestor requisitante em `users`           |
| `created_by`        | foreignId          | profissional de RH criador               |
| `title`             | string             | título público                           |
| `slug`              | string             | único por empresa                        |
| `description`       | longText           | descrição da vaga                        |
| `requirements`      | longText nullable  | requisitos                               |
| `workplace_type`    | string             | `onsite`, `hybrid` ou `remote`           |
| `employment_type`   | string             | tipo de vínculo pretendido               |
| `city`              | string nullable    | cidade                                   |
| `state`             | char(2) nullable   | UF                                       |
| `status`            | string             | `draft`, `published`, `paused`, `closed` |
| `published_at`      | timestamp nullable | publicação                               |
| `closes_at`         | timestamp nullable | encerramento previsto                    |
| `timestamps`        | timestamps         | criação e atualização                    |
| `softDeletes`       | timestamp nullable | exclusão lógica                          |

### `create_applications_table.php`

| Campo              | Tipo               | Regra                 |
| ------------------ | ------------------ | --------------------- |
| `id`               | bigint             | chave primária        |
| `job_id`           | foreignId          | vaga                  |
| `candidate_id`     | foreignId          | usuário candidato     |
| `current_stage_id` | foreignId nullable | etapa atual           |
| `status`           | string             | situação geral        |
| `source`           | string nullable    | origem da candidatura |
| `cover_letter`     | text nullable      | apresentação          |
| `applied_at`       | timestamp          | data da candidatura   |
| `withdrawn_at`     | timestamp nullable | desistência           |
| `rejected_at`      | timestamp nullable | reprovação            |
| `hired_at`         | timestamp nullable | contratação           |
| `timestamps`       | timestamps         | criação e atualização |
| `softDeletes`      | timestamp nullable | exclusão lógica       |

Restrição única: `job_id + candidate_id` para impedir candidatura duplicada.

### `create_recruitment_stages_table.php`

| Campo        | Tipo            | Regra                                     |
| ------------ | --------------- | ----------------------------------------- |
| `id`         | bigint          | chave primária                            |
| `company_id` | foreignId       | empresa                                   |
| `name`       | string          | nome da etapa                             |
| `position`   | unsignedInteger | ordem                                     |
| `type`       | string          | triagem, entrevista, teste, proposta etc. |
| `active`     | boolean         | padrão `true`                             |
| `timestamps` | timestamps      | criação e atualização                     |

### `create_application_stage_histories_table.php`

| Campo            | Tipo               | Regra               |
| ---------------- | ------------------ | ------------------- |
| `id`             | bigint             | chave primária      |
| `application_id` | foreignId          | candidatura         |
| `from_stage_id`  | foreignId nullable | etapa anterior      |
| `to_stage_id`    | foreignId          | nova etapa          |
| `changed_by`     | foreignId          | usuário responsável |
| `notes`          | text nullable      | justificativa       |
| `created_at`     | timestamp          | evento imutável     |

### `create_application_evaluations_table.php`

| Campo            | Tipo                         | Regra                         |
| ---------------- | ---------------------------- | ----------------------------- |
| `id`             | bigint                       | chave primária                |
| `application_id` | foreignId                    | candidatura                   |
| `evaluator_id`   | foreignId                    | RH ou gestor autorizado       |
| `rating`         | unsignedTinyInteger nullable | escala definida pelo produto  |
| `recommendation` | string                       | avançar, aguardar ou reprovar |
| `notes`          | text nullable                | observação privada            |
| `timestamps`     | timestamps                   | criação e atualização         |

Restrição única recomendada: `application_id + evaluator_id` por rodada de avaliação.

### `create_admissions_table.php`

| Campo                 | Tipo               | Regra                                     |
| --------------------- | ------------------ | ----------------------------------------- |
| `id`                  | bigint             | chave primária                            |
| `application_id`      | foreignId unique   | candidatura aprovada                      |
| `candidate_id`        | foreignId          | futuro colaborador                        |
| `company_id`          | foreignId          | unidade de contratação                    |
| `position_id`         | foreignId          | cargo profissional                        |
| `department_id`       | foreignId          | setor                                     |
| `manager_id`          | foreignId nullable | gestor                                    |
| `status`              | string             | pendente, em análise, aprovado, cancelado |
| `expected_start_date` | date nullable      | previsão de início                        |
| `submitted_at`        | timestamp nullable | envio do candidato                        |
| `approved_at`         | timestamp nullable | aprovação do RH                           |
| `approved_by`         | foreignId nullable | aprovador                                 |
| `timestamps`          | timestamps         | criação e atualização                     |

### `create_employee_profiles_table.php`

| Campo               | Tipo               | Regra                       |
| ------------------- | ------------------ | --------------------------- |
| `id`                | bigint             | chave primária              |
| `user_id`           | foreignId unique   | usuário colaborador         |
| `company_id`        | foreignId          | unidade atual               |
| `department_id`     | foreignId          | setor atual                 |
| `position_id`       | foreignId          | cargo profissional atual    |
| `manager_id`        | foreignId nullable | gestor direto               |
| `employee_number`   | string             | matrícula única por empresa |
| `employment_status` | string             | ativo, afastado, desligado  |
| `hire_date`         | date               | admissão                    |
| `termination_date`  | date nullable      | desligamento                |
| `timestamps`        | timestamps         | criação e atualização       |
| `softDeletes`       | timestamp nullable | preservação histórica       |

### `create_hr_audit_events_table.php`

| Campo               | Tipo               | Regra                         |
| ------------------- | ------------------ | ----------------------------- |
| `id`                | bigint             | chave primária                |
| `actor_id`          | foreignId nullable | usuário responsável           |
| `impersonator_id`   | foreignId nullable | super-admin em suporte        |
| `event`             | string             | ação executada                |
| `auditable_type/id` | morphs             | registro afetado              |
| `old_values`        | json nullable      | valores anteriores protegidos |
| `new_values`        | json nullable      | novos valores protegidos      |
| `ip_address`        | string nullable    | origem                        |
| `created_at`        | timestamp          | evento imutável               |

## 4. Models previstos

- `App\Models\Department`
- `App\Models\Position`
- `App\Models\CandidateProfile`
- `App\Models\Job`
- `App\Models\Application`
- `App\Models\RecruitmentStage`
- `App\Models\ApplicationStageHistory`
- `App\Models\ApplicationEvaluation`
- `App\Models\Admission`
- `App\Models\EmployeeProfile`
- `App\Models\HrAuditEvent`

Cada model deve definir casts, relações, factories e estados de domínio. Mudanças de etapa, aprovação e conversão não devem ficar em observers ocultos; devem passar por Actions ou Services explícitos.

## 5. Controllers, Requests, Policies e Services

### Controllers

- `Hr/DepartmentController.php`
- `Hr/PositionController.php`
- `Hr/JobController.php`
- `Hr/ApplicationController.php`
- `Hr/ApplicationStageController.php`
- `Hr/ApplicationEvaluationController.php`
- `Hr/AdmissionController.php`
- `Hr/EmployeeController.php`
- `Candidate/CandidateProfileController.php`
- `Candidate/MyApplicationController.php`

### Form Requests

- `Hr/DepartmentRequest.php`
- `Hr/PositionRequest.php`
- `Hr/JobRequest.php`
- `Hr/MoveApplicationRequest.php`
- `Hr/ApplicationEvaluationRequest.php`
- `Hr/AdmissionRequest.php`
- `Candidate/CandidateProfileRequest.php`
- `Candidate/ApplicationRequest.php`

### Policies

- `DepartmentPolicy.php`
- `PositionPolicy.php`
- `JobPolicy.php`
- `ApplicationPolicy.php`
- `AdmissionPolicy.php`
- `EmployeeProfilePolicy.php`

As Policies combinarão permissão Spatie com escopo. Por exemplo, `applications.view` não será suficiente: o registro deverá pertencer a uma unidade autorizada ou estar associado à equipe do gestor.

### Actions e Services

- `PublishJob.php`
- `MoveApplicationToStage.php`
- `RejectApplication.php`
- `ApproveAdmission.php`
- `ConvertCandidateToEmployee.php`
- `AnonymizeExpiredCandidates.php`
- `HrAuditLogger.php`

## 6. Rotas e views Inertia

### Área pública e do candidato

- `resources/js/pages/careers/index.tsx`: lista de vagas publicadas.
- `resources/js/pages/careers/show.tsx`: detalhes e candidatura.
- `resources/js/pages/candidate/profile.tsx`: perfil profissional.
- `resources/js/pages/candidate/applications/index.tsx`: candidaturas próprias.
- `resources/js/pages/candidate/applications/show.tsx`: acompanhamento.
- `resources/js/pages/candidate/admission.tsx`: envio de dados admissionais.

### Área de RH

- `resources/js/pages/hr/dashboard.tsx`: indicadores operacionais.
- `resources/js/pages/hr/departments/index.tsx`: setores.
- `resources/js/pages/hr/positions/index.tsx`: cargos profissionais.
- `resources/js/pages/hr/jobs/index.tsx`: vagas.
- `resources/js/pages/hr/jobs/form.tsx`: criação e edição.
- `resources/js/pages/hr/jobs/show.tsx`: detalhes e candidatos.
- `resources/js/pages/hr/applications/index.tsx`: funil de seleção.
- `resources/js/pages/hr/applications/show.tsx`: perfil, histórico e avaliação.
- `resources/js/pages/hr/admissions/index.tsx`: admissões em andamento.
- `resources/js/pages/hr/admissions/show.tsx`: conferência e aprovação.
- `resources/js/pages/hr/employees/index.tsx`: colaboradores.
- `resources/js/pages/hr/employees/show.tsx`: cadastro organizacional.

Componentes compartilhados previstos:

- `application-stage-board.tsx`
- `application-timeline.tsx`
- `candidate-summary-card.tsx`
- `organizational-scope-filter.tsx`
- `sensitive-data-field.tsx`

## 7. Lógica de negócio

### Vagas

- somente vagas em `published` aparecem no site;
- publicação exige título, descrição, unidade e setor válidos;
- encerramento bloqueia novas candidaturas sem apagar as existentes;
- gestor requisitante acessa somente vagas vinculadas à própria equipe.

### Candidaturas

- candidato cria apenas uma candidatura por vaga;
- candidato acessa e altera somente os próprios registros;
- toda mudança de etapa gera histórico imutável;
- avaliações internas não são expostas ao candidato;
- reprovação e desistência encerram o fluxo, mas preservam auditoria.

### Admissão e conversão

- somente candidatura aprovada inicia admissão;
- aprovação é exclusiva de usuário autorizado de RH;
- conversão deve ocorrer em transação;
- a transação cria `employee_profiles`, atribui `colaborador` pelo Spatie e encerra o estado de candidato conforme a regra definida;
- nenhuma coluna booleana de papel será adicionada em `users`.

### Retenção

- contagem de 12 meses começa no encerramento do processo seletivo;
- consentimento de banco de talentos define uma nova expiração explícita;
- job agendado anonimiza dados sem quebrar métricas agregadas;
- documentos físicos devem ser excluídos do storage privado junto com a anonimização;
- execução gera relatório auditável.

### Papel de acesso, cargo e senioridade

O cadastro profissional terá três conceitos independentes:

| Conceito           | Exemplo                | Origem            |
| ------------------ | ---------------------- | ----------------- |
| Papel de acesso    | `colaborador`          | Spatie `roles`    |
| Cargo profissional | `Analista de Sistemas` | `positions.title` |
| Senioridade        | `senior`               | `positions.level` |

Um usuário com papel `colaborador`, vinculado ao setor Tecnologia da Informação e ao cargo `Analista de Sistemas` de nível `senior`, será exibido como **Analista de Sistemas Sênior**.

Regras:

- não criar papéis como `colaborador-analista-sistemas-sr`;
- promoção ou mudança de cargo altera o vínculo profissional, não o papel básico de acesso;
- ao assumir liderança, o colaborador pode receber adicionalmente o papel `gestor`;
- permissões excepcionais continuam atribuídas por papéis e permissões Spatie;
- relatórios por cargo, senioridade ou setor consultam a estrutura organizacional;
- a interface exibe separadamente a tag de papel e a tag do cargo profissional.

## 8. Testes previstos

### Feature

- candidato visualiza vagas publicadas, mas não rascunhos;
- candidato cria apenas uma candidatura por vaga;
- candidato não acessa candidatura de terceiro;
- RH acessa somente unidades autorizadas;
- gestor acessa apenas vagas e candidatos de sua equipe;
- analista de RH movimenta etapas, mas não executa aprovação reservada ao gestor de RH;
- publicação e encerramento respeitam estado da vaga;
- conversão cria colaborador e atribui o papel correto pelo Spatie;
- cargo e senioridade são exibidos corretamente sem gerar papéis adicionais;
- promoção altera cargo ou nível sem remover as permissões de colaborador;
- falha na conversão reverte toda a transação;
- retenção anonimiza candidatos expirados e preserva consentimentos válidos;
- impersonação registra o super-admin nas ações auditadas.

### Unit

- transições permitidas de vaga e candidatura;
- cálculo da data de retenção;
- montagem do escopo por empresa, unidade e equipe;
- regras de anonimização;
- Actions de publicação, movimentação e conversão.

### Frontend

- filtros e paginação do funil;
- formulários exibem erros do backend;
- botões ficam ocultos ou desabilitados conforme abilities;
- candidato nunca recebe dados internos de avaliação nas props Inertia;
- estados vazios, carregamento e confirmação de ações destrutivas.

## 9. Critérios de aceite

- permissões são verificadas no backend, não apenas escondidas na interface;
- todas as consultas respeitam unidade e equipe;
- histórico de candidatura é imutável;
- arquivos de candidato ficam privados;
- conversão em colaborador é transacional e auditada;
- papel de acesso, cargo profissional e senioridade permanecem independentes;
- retenção de 12 meses é automática e testada;
- administradores técnicos não recebem acesso de RH implicitamente.
