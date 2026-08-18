# Departamento Pessoal

## 1. Escopo

O módulo de Departamento Pessoal será responsável por:

- documentos trabalhistas do colaborador;
- jornada, registros e ajustes de ponto;
- solicitações e períodos de férias;
- benefícios;
- importação e consulta de folha de pagamento;
- alterações salariais;
- desligamentos;
- fluxos de dupla aprovação para operações críticas.

Papéis principais: `colaborador`, `gestor`, `dp-analista` e `dp-gestor`. RH poderá consultar apenas o mínimo explicitamente autorizado; `administrador` não terá acesso automático aos dados deste módulo.

## 2. Dependências

Antes deste módulo devem existir:

- empresas e unidades;
- `departments`, `positions` e `employee_profiles` definidos no documento de RH;
- escopo organizacional por usuário;
- armazenamento privado de documentos;
- infraestrutura de auditoria;
- matriz Spatie já implementada.

## 3. Ordem sugerida de implementação

1. Documentos do colaborador.
2. Férias e aprovações de gestor.
3. Ponto, ajustes e aprovações.
4. Benefícios.
5. Importação de folha e demonstrativos.
6. Alterações salariais e desligamentos com dupla aprovação.

## 4. Migrations e campos

### `create_employee_documents_table.php`

| Campo         | Tipo               | Regra                      |
| ------------- | ------------------ | -------------------------- |
| `id`          | bigint             | chave primária             |
| `employee_id` | foreignId          | perfil do colaborador      |
| `type`        | string             | categoria documental       |
| `title`       | string             | nome exibido               |
| `disk`        | string             | disco privado              |
| `path`        | string             | caminho interno            |
| `mime_type`   | string             | tipo validado              |
| `size`        | unsignedBigInteger | bytes                      |
| `checksum`    | string             | integridade e deduplicação |
| `expires_at`  | date nullable      | validade                   |
| `uploaded_by` | foreignId          | responsável                |
| `verified_at` | timestamp nullable | conferência                |
| `verified_by` | foreignId nullable | conferente                 |
| `timestamps`  | timestamps         | criação e atualização      |
| `softDeletes` | timestamp nullable | exclusão lógica            |

O caminho físico nunca deve ser enviado diretamente ao navegador. Downloads passam por controller autorizado e resposta temporária ou stream.

### `create_time_entries_table.php`

| Campo         | Tipo               | Regra                                   |
| ------------- | ------------------ | --------------------------------------- |
| `id`          | bigint             | chave primária                          |
| `employee_id` | foreignId          | colaborador                             |
| `occurred_at` | timestamp          | data e hora                             |
| `type`        | string             | entrada, saída, início/fim de intervalo |
| `source`      | string             | manual, importação ou integração        |
| `notes`       | text nullable      | observação                              |
| `created_by`  | foreignId nullable | responsável pela criação                |
| `timestamps`  | timestamps         | criação e atualização                   |

Registros originais devem ser imutáveis. Correções utilizam solicitações de ajuste.

### `create_time_adjustment_requests_table.php`

| Campo                    | Tipo                         | Regra                         |
| ------------------------ | ---------------------------- | ----------------------------- |
| `id`                     | bigint                       | chave primária                |
| `employee_id`            | foreignId                    | solicitante                   |
| `time_entry_id`          | foreignId nullable           | registro relacionado          |
| `requested_at`           | timestamp                    | horário solicitado            |
| `reason`                 | text                         | justificativa                 |
| `status`                 | string                       | pendente, aprovado, rejeitado |
| `is_retroactive`         | boolean                      | aciona dupla aprovação        |
| `manager_approved_at/by` | timestamp/foreignId nullable | primeira aprovação            |
| `dp_approved_at/by`      | timestamp/foreignId nullable | segunda aprovação             |
| `rejected_at/by`         | timestamp/foreignId nullable | rejeição                      |
| `timestamps`             | timestamps                   | criação e atualização         |

### `create_vacation_requests_table.php`

| Campo                    | Tipo                         | Regra                                              |
| ------------------------ | ---------------------------- | -------------------------------------------------- |
| `id`                     | bigint                       | chave primária                                     |
| `employee_id`            | foreignId                    | colaborador                                        |
| `accrual_start/end`      | date                         | período aquisitivo                                 |
| `starts_at`              | date                         | início solicitado                                  |
| `ends_at`                | date                         | fim solicitado                                     |
| `days`                   | unsignedSmallInteger         | dias calculados                                    |
| `sell_days`              | unsignedSmallInteger         | abono solicitado                                   |
| `advance_thirteenth`     | boolean                      | solicitação declarada                              |
| `status`                 | string                       | rascunho, pendente, aprovado, rejeitado, cancelado |
| `manager_approved_at/by` | timestamp/foreignId nullable | aprovação da liderança                             |
| `dp_approved_at/by`      | timestamp/foreignId nullable | validação do DP                                    |
| `rejected_at/by`         | timestamp/foreignId nullable | rejeição                                           |
| `rejection_reason`       | text nullable                | justificativa                                      |
| `timestamps`             | timestamps                   | criação e atualização                              |

### `create_benefit_types_table.php`

| Campo           | Tipo            | Regra                    |
| --------------- | --------------- | ------------------------ |
| `id`            | bigint          | chave primária           |
| `company_id`    | foreignId       | empresa                  |
| `name`          | string          | benefício                |
| `provider`      | string nullable | fornecedor               |
| `active`        | boolean         | padrão `true`            |
| `configuration` | json nullable   | parâmetros não sigilosos |
| `timestamps`    | timestamps      | criação e atualização    |

### `create_employee_benefits_table.php`

| Campo             | Tipo                    | Regra                        |
| ----------------- | ----------------------- | ---------------------------- |
| `id`              | bigint                  | chave primária               |
| `employee_id`     | foreignId               | colaborador                  |
| `benefit_type_id` | foreignId               | benefício                    |
| `status`          | string                  | ativo, suspenso, encerrado   |
| `starts_at`       | date                    | início                       |
| `ends_at`         | date nullable           | fim                          |
| `employee_amount` | decimal nullable        | desconto do colaborador      |
| `company_amount`  | decimal nullable        | parcela da empresa           |
| `metadata`        | encrypted/json nullable | dados específicos protegidos |
| `timestamps`      | timestamps              | criação e atualização        |

### `create_payroll_imports_table.php`

| Campo               | Tipo               | Regra                                           |
| ------------------- | ------------------ | ----------------------------------------------- |
| `id`                | bigint             | chave primária                                  |
| `company_id`        | foreignId          | empresa/unidade                                 |
| `reference_month`   | date               | primeiro dia da competência                     |
| `provider`          | string             | origem externa                                  |
| `original_filename` | string             | nome auditável                                  |
| `disk/path`         | string             | arquivo privado                                 |
| `checksum`          | string             | impede importação duplicada                     |
| `status`            | string             | recebido, validando, inválido, pronto, aprovado |
| `records_count`     | unsignedInteger    | total processado                                |
| `errors_count`      | unsignedInteger    | inconsistências                                 |
| `imported_by`       | foreignId          | analista responsável                            |
| `approved_by`       | foreignId nullable | segundo aprovador                               |
| `approved_at`       | timestamp nullable | aprovação final                                 |
| `timestamps`        | timestamps         | criação e atualização                           |

Restrição única: `company_id + reference_month + checksum`.

### `create_payroll_statements_table.php`

| Campo               | Tipo               | Regra                    |
| ------------------- | ------------------ | ------------------------ |
| `id`                | bigint             | chave primária           |
| `payroll_import_id` | foreignId          | lote de origem           |
| `employee_id`       | foreignId          | colaborador              |
| `reference_month`   | date               | competência              |
| `gross_amount`      | decimal            | total bruto              |
| `discount_amount`   | decimal            | descontos                |
| `net_amount`        | decimal            | total líquido            |
| `details`           | encrypted/json     | rubricas protegidas      |
| `published_at`      | timestamp nullable | liberação ao colaborador |
| `timestamps`        | timestamps         | criação e atualização    |

Restrição única: `employee_id + reference_month` por lote válido.

### `create_salary_change_requests_table.php`

| Campo                   | Tipo                         | Regra                                   |
| ----------------------- | ---------------------------- | --------------------------------------- |
| `id`                    | bigint                       | chave primária                          |
| `employee_id`           | foreignId                    | colaborador afetado                     |
| `current_salary`        | encrypted decimal            | valor anterior                          |
| `proposed_salary`       | encrypted decimal            | novo valor                              |
| `effective_date`        | date                         | vigência                                |
| `reason`                | text                         | justificativa                           |
| `status`                | string                       | pendente, aprovado, rejeitado, aplicado |
| `requested_by`          | foreignId                    | solicitante                             |
| `first_approved_by/at`  | foreignId/timestamp nullable | primeira aprovação                      |
| `second_approved_by/at` | foreignId/timestamp nullable | segunda aprovação                       |
| `timestamps`            | timestamps                   | criação e atualização                   |

Os dois aprovadores devem ser usuários diferentes.

### `create_termination_requests_table.php`

| Campo                        | Tipo                         | Regra                                    |
| ---------------------------- | ---------------------------- | ---------------------------------------- |
| `id`                         | bigint                       | chave primária                           |
| `employee_id`                | foreignId                    | colaborador                              |
| `type`                       | string                       | modalidade de desligamento               |
| `requested_termination_date` | date                         | data proposta                            |
| `reason`                     | text                         | justificativa protegida                  |
| `status`                     | string                       | pendente, aprovado, rejeitado, concluído |
| `requested_by`               | foreignId                    | solicitante                              |
| `first_approved_by/at`       | foreignId/timestamp nullable | primeira aprovação                       |
| `second_approved_by/at`      | foreignId/timestamp nullable | segunda aprovação                        |
| `completed_by/at`            | foreignId/timestamp nullable | conclusão                                |
| `timestamps`                 | timestamps                   | criação e atualização                    |

### `create_dp_audit_events_table.php`

Estrutura equivalente a `hr_audit_events`, com ator, impersonador, evento, registro afetado, alterações protegidas, IP e timestamp imutável. Na implementação, pode ser substituída por uma única tabela compartilhada de auditoria se o desenho final mantiver isolamento e política de retenção adequados.

## 5. Models previstos

- `App\Models\EmployeeDocument`
- `App\Models\TimeEntry`
- `App\Models\TimeAdjustmentRequest`
- `App\Models\VacationRequest`
- `App\Models\BenefitType`
- `App\Models\EmployeeBenefit`
- `App\Models\PayrollImport`
- `App\Models\PayrollStatement`
- `App\Models\SalaryChangeRequest`
- `App\Models\TerminationRequest`
- `App\Models\DpAuditEvent` ou um model compartilhado de auditoria

Valores financeiros e payloads com rubricas devem utilizar casts criptografados quando precisarem ser persistidos de forma consultável apenas pela aplicação.

## 6. Controllers, Requests, Policies e Services

### Controllers

- `Dp/EmployeeDocumentController.php`
- `Dp/TimeEntryController.php`
- `Dp/TimeAdjustmentController.php`
- `Dp/VacationController.php`
- `Dp/BenefitTypeController.php`
- `Dp/EmployeeBenefitController.php`
- `Dp/PayrollImportController.php`
- `Dp/PayrollStatementController.php`
- `Dp/SalaryChangeController.php`
- `Dp/TerminationController.php`
- `Employee/MyDocumentController.php`
- `Employee/MyTimeRecordController.php`
- `Employee/MyVacationController.php`
- `Employee/MyBenefitController.php`
- `Employee/MyPayrollStatementController.php`

### Form Requests

- `Dp/EmployeeDocumentRequest.php`
- `Dp/TimeEntryImportRequest.php`
- `Dp/TimeAdjustmentDecisionRequest.php`
- `Dp/VacationDecisionRequest.php`
- `Dp/BenefitTypeRequest.php`
- `Dp/EmployeeBenefitRequest.php`
- `Dp/PayrollImportRequest.php`
- `Dp/SalaryChangeRequest.php`
- `Dp/TerminationRequest.php`
- `Employee/TimeAdjustmentRequest.php`
- `Employee/VacationRequest.php`

### Policies

- `EmployeeDocumentPolicy.php`
- `TimeEntryPolicy.php`
- `TimeAdjustmentRequestPolicy.php`
- `VacationRequestPolicy.php`
- `EmployeeBenefitPolicy.php`
- `PayrollImportPolicy.php`
- `PayrollStatementPolicy.php`
- `SalaryChangeRequestPolicy.php`
- `TerminationRequestPolicy.php`

### Actions, Services e Jobs

- `ApproveTimeAdjustment.php`
- `ApproveVacation.php`
- `ImportPayroll.php`
- `ValidatePayrollImport.php`
- `ApprovePayrollImport.php`
- `PublishPayrollStatements.php`
- `RequestSalaryChange.php`
- `ApproveSalaryChange.php`
- `RequestTermination.php`
- `ApproveTermination.php`
- `DpAuditLogger.php`
- `ProcessPayrollImport.php` como job em fila

## 7. Rotas e views Inertia

### Autosserviço do colaborador

- `resources/js/pages/employee/documents/index.tsx`
- `resources/js/pages/employee/time-records/index.tsx`
- `resources/js/pages/employee/time-adjustments/create.tsx`
- `resources/js/pages/employee/vacations/index.tsx`
- `resources/js/pages/employee/vacations/create.tsx`
- `resources/js/pages/employee/benefits/index.tsx`
- `resources/js/pages/employee/payroll/index.tsx`
- `resources/js/pages/employee/payroll/show.tsx`

### Área de Departamento Pessoal

- `resources/js/pages/dp/dashboard.tsx`
- `resources/js/pages/dp/documents/index.tsx`
- `resources/js/pages/dp/time-records/index.tsx`
- `resources/js/pages/dp/time-adjustments/index.tsx`
- `resources/js/pages/dp/vacations/index.tsx`
- `resources/js/pages/dp/benefits/index.tsx`
- `resources/js/pages/dp/payroll-imports/index.tsx`
- `resources/js/pages/dp/payroll-imports/show.tsx`
- `resources/js/pages/dp/salary-changes/index.tsx`
- `resources/js/pages/dp/salary-changes/show.tsx`
- `resources/js/pages/dp/terminations/index.tsx`
- `resources/js/pages/dp/terminations/show.tsx`

Componentes compartilhados previstos:

- `approval-timeline.tsx`
- `dual-approval-status.tsx`
- `employee-scope-filter.tsx`
- `private-document-download.tsx`
- `payroll-import-summary.tsx`
- `sensitive-value.tsx`

## 8. Lógica de negócio

### Escopo e sigilo

- colaborador acessa somente os próprios registros;
- gestor acessa ponto e férias somente da própria equipe;
- gestor não acessa remuneração, folha, dados bancários ou documentos sem permissão específica;
- DP acessa somente unidades autorizadas;
- administrador técnico não acessa informações do módulo;
- super-admin impersonando mantém identificação do ator original na auditoria.

### Dupla aprovação

Fechamento de folha, desligamento, alteração salarial e ajuste retroativo de ponto exigem dois aprovadores diferentes. A Action deve:

1. bloquear autoaprovação quando o solicitante também seria aprovador;
2. impedir que a mesma pessoa faça as duas aprovações;
3. validar papel, permissão e unidade de cada aprovador;
4. aplicar a mudança somente após a segunda aprovação;
5. registrar todo o fluxo de forma imutável.

### Ponto

- registros importados ou originais não são sobrescritos;
- correções geram solicitações e registros compensatórios;
- ajuste retroativo sempre usa dupla aprovação;
- gestor realiza a primeira análise da própria equipe e DP conclui a validação.

### Férias

- períodos são calculados no backend;
- sobreposição e saldo insuficiente bloqueiam a solicitação;
- gestor aprova apenas sua equipe;
- DP valida regras e efetiva o período;
- cancelamento após aprovação exige fluxo e auditoria próprios.

### Folha externa

- o sistema não calculará a folha nesta fase;
- arquivo recebido é armazenado em área privada e validado em fila;
- checksum bloqueia lote duplicado;
- linhas inválidas não são publicadas;
- fechamento exige dupla aprovação;
- demonstrativos só ficam visíveis após publicação explícita;
- reprocessamento cria nova versão, sem apagar o lote anterior.

## 9. Testes previstos

### Feature

- colaborador acessa somente seus documentos e demonstrativos;
- gestor acessa férias e ponto somente da própria equipe;
- administrador recebe `403` em todas as rotas de DP;
- analista de DP acessa somente unidades autorizadas;
- downloads privados exigem Policy e não revelam caminho físico;
- ajuste retroativo não é aplicado com uma única aprovação;
- a mesma pessoa não executa as duas aprovações;
- alteração salarial e desligamento respeitam dupla aprovação;
- importação duplicada é rejeitada pelo checksum;
- lote inválido não publica demonstrativos;
- demonstrativo não publicado não aparece ao colaborador;
- impersonação registra ator atual e super-admin original.

### Unit

- cálculo e sobreposição de férias;
- transições dos estados de aprovação;
- validação de competência da folha;
- parser do formato do fornecedor;
- regras de arredondamento monetário apenas para dados importados;
- escopo por empresa, unidade e equipe;
- exigência de aprovadores distintos.

### Jobs e integração

- importação é idempotente;
- falhas podem ser retomadas sem duplicar registros;
- arquivos malformados geram relatório e não importação parcial;
- logs não contêm remuneração nem dados pessoais;
- publicação ocorre somente após lote aprovado.

### Frontend

- dados sensíveis ficam mascarados até ação autorizada;
- status das duas aprovações é exibido corretamente;
- formulários mostram validações do backend;
- interface não oferece ações incompatíveis com abilities;
- estados de importação em fila são atualizados com segurança.

## 10. Critérios de aceite

- nenhuma informação de DP é concedida automaticamente ao papel `administrador`;
- todas as consultas respeitam titular, equipe e unidade;
- documentos e arquivos de folha ficam em storage privado;
- quatro operações críticas exigem dois aprovadores distintos;
- folha é somente integrada/importada nesta fase;
- valores sensíveis são protegidos em repouso e não aparecem em logs;
- operações críticas possuem auditoria completa e testes negativos.
