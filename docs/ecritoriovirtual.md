# Escritório Virtual — primeira fase

## Objetivo

O Escritório Virtual será o painel pessoal dos colaboradores da empresa. A primeira fase terá somente duas páginas:

1. **Dashboard**
2. **Cartão de Ponto**

O escopo inicial será jornada de trabalho, registros de ponto, banco de horas e resumo de férias. Outros recursos serão implementados em fases futuras.

## Regras de acesso

- O usuário deve estar autenticado.
- Todo usuário interno terá acesso ao Dashboard, mesmo sem `EmployeeProfile`.
- O campo `users.tracks_time` definirá quem deve registrar ponto.
- O colaborador acessa somente os próprios dados.
- O papel `colaborador` usa as permissões existentes `intranet.access`, `time-records.view-own` e `vacations.view-own`.
- Ajustes de ponto exigem a nova permissão `time-records.request-adjustment`.
- Gestores e Departamento Pessoal continuam usando as permissões de equipe e administração já definidas.
- A autorização será feita no backend com `spatie/laravel-permission`, Policies e consultas limitadas ao colaborador autenticado.
- O papel irrestrito permanece `super-admin`, no guard `web`.

## 1. Dashboard

Rota: `GET /virtual-office`

O dashboard exibirá:

- Nome, cargo, setor e matrícula.
- Jornada prevista para o dia.
- Registros de ponto do dia.
- Horas trabalhadas no dia e no mês.
- Saldo atual do banco de horas.
- Resumo de férias: período aquisitivo, dias disponíveis e próximas férias agendadas.
- Atalho para o Cartão de Ponto.
- Solicitações de ajuste de ponto pendentes.

## 2. Cartão de Ponto

Rota: `GET /virtual-office/time-card`

O cartão exibirá um mês por vez:

- Data e dia da semana.
- Jornada prevista.
- Entrada, início do intervalo, fim do intervalo e saída.
- Total trabalhado no dia.
- Saldo diário positivo ou negativo.
- Ocorrências, faltas e registros incompletos.
- Saldo acumulado do banco de horas.
- Situação de solicitações de ajuste.

O colaborador poderá solicitar a correção de um dia, informando os horários corretos e uma justificativa. A aprovação será feita posteriormente pelo gestor ou Departamento Pessoal.

## Estrutura de banco de dados

### Tabela `employee_work_schedules`

Define a jornada contratual do colaborador e mantém o histórico de vigência.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | Chave primária |
| `user_id` | foreignId | FK para `users`, exclusão em cascata |
| `name` | string(100) | Ex.: Jornada administrativa |
| `weekdays` | json | Dias da semana aplicáveis, de 1 a 7 |
| `start_time` | time | Início previsto |
| `break_start_time` | time, nullable | Início previsto do intervalo |
| `break_end_time` | time, nullable | Fim previsto do intervalo |
| `end_time` | time | Término previsto |
| `daily_minutes` | unsignedSmallInteger | Carga diária em minutos |
| `weekly_minutes` | unsignedSmallInteger | Carga semanal em minutos |
| `valid_from` | date | Início da vigência |
| `valid_until` | date, nullable | Fim da vigência |
| `active` | boolean | Padrão `true` |
| `created_at`, `updated_at` | timestamps | Controle padrão |

Índices: `user_id`, `active` e índice composto em `user_id`, `valid_from`, `valid_until`.

### Tabela `time_entries`

Armazena cada marcação individual de ponto.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | Chave primária |
| `user_id` | foreignId | FK para `users`, exclusão em cascata |
| `recorded_at` | timestampTz | Data e hora efetiva da marcação |
| `type` | string(20) | `clock_in`, `break_start`, `break_end` ou `clock_out` |
| `source` | string(20) | `manual`, `web`, `import` ou `adjustment` |
| `notes` | text, nullable | Observação operacional |
| `ip_address` | string(45), nullable | IP quando registrado pela aplicação |
| `created_by` | foreignId, nullable | FK para `users`; autor do registro |
| `created_at`, `updated_at` | timestamps | Controle padrão |

Índices: `user_id`, `recorded_at` e índice composto em `user_id`, `recorded_at`.

### Tabela `time_adjustment_requests`

Registra pedidos de correção sem modificar diretamente as marcações originais.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | Chave primária |
| `user_id` | foreignId | FK para `users`, exclusão em cascata |
| `work_date` | date | Dia solicitado |
| `requested_entries` | json | Tipos e horários propostos |
| `reason` | text | Justificativa obrigatória |
| `status` | string(20) | `pending`, `approved` ou `rejected` |
| `reviewed_by` | foreignId, nullable | FK para `users` |
| `reviewed_at` | timestampTz, nullable | Momento da análise |
| `review_notes` | text, nullable | Parecer do responsável |
| `created_at`, `updated_at` | timestamps | Controle padrão |

Índices: `user_id`, `work_date`, `status`. Deve existir somente uma solicitação pendente por colaborador e data.

### Tabela `hour_bank_transactions`

Mantém o livro-razão do banco de horas. O saldo será a soma de `minutes`.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | Chave primária |
| `user_id` | foreignId | FK para `users`, exclusão em cascata |
| `work_date` | date | Data de referência |
| `minutes` | integer | Positivo para crédito e negativo para débito |
| `type` | string(20) | `worked`, `absence`, `compensation` ou `adjustment` |
| `description` | string(255), nullable | Motivo do lançamento |
| `time_adjustment_request_id` | foreignId, nullable | Origem em ajuste aprovado |
| `created_by` | foreignId, nullable | FK para `users` |
| `created_at`, `updated_at` | timestamps | Controle padrão |

Índices: `user_id`, `work_date` e índice composto em ambos.

### Tabela `vacation_periods`

Nesta fase será usada somente para exibir o resumo de férias no dashboard.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | Chave primária |
| `user_id` | foreignId | FK para `users`, exclusão em cascata |
| `accrual_start` | date | Início do período aquisitivo |
| `accrual_end` | date | Fim do período aquisitivo |
| `entitled_days` | unsignedTinyInteger | Dias adquiridos, normalmente 30 |
| `used_days` | unsignedTinyInteger | Dias já utilizados |
| `scheduled_start` | date, nullable | Próximo início agendado |
| `scheduled_end` | date, nullable | Próximo término agendado |
| `status` | string(20) | `accruing`, `available`, `scheduled` ou `completed` |
| `created_at`, `updated_at` | timestamps | Controle padrão |

Restrição única em `user_id`, `accrual_start`, `accrual_end`. Os dias disponíveis serão calculados por `entitled_days - used_days`.

## Models

### Models novos

- `App\Models\EmployeeWorkSchedule`
- `App\Models\TimeEntry`
- `App\Models\TimeAdjustmentRequest`
- `App\Models\HourBankTransaction`
- `App\Models\VacationPeriod`

### Relacionamentos em `User`

- `workSchedules(): HasMany`
- `timeEntries(): HasMany`
- `timeAdjustmentRequests(): HasMany`
- `hourBankTransactions(): HasMany`
- `vacationPeriods(): HasMany`

### Perfil funcional opcional

- `employeeProfile(): HasOne` complementa cargo, setor e matrícula, mas não controla o acesso ao Escritório Virtual.

Todos os campos de data/hora, JSON, booleanos e inteiros deverão possuir casts explícitos nos Models.

## Controllers

### `App\Http\Controllers\VirtualOffice\DashboardController`

Controller invocável responsável por entregar ao usuário autenticado os dados pessoais e, quando `tracks_time` estiver ativo, ponto do dia, totais do mês, banco de horas, férias e ajustes pendentes.

### `App\Http\Controllers\VirtualOffice\TimeCardController`

Método `index(Request $request)` responsável por validar o mês solicitado e retornar jornada, marcações, totais, ocorrências e saldo diário do colaborador autenticado.

### `App\Http\Controllers\VirtualOffice\TimeAdjustmentRequestController`

Método `store(StoreTimeAdjustmentRequest $request)` responsável por criar uma solicitação de ajuste para o próprio colaborador, sem alterar diretamente `time_entries`.

## Form Request

### `App\Http\Requests\VirtualOffice\StoreTimeAdjustmentRequest`

Campos aceitos:

- `work_date`: obrigatório, data válida e não futura.
- `requested_entries`: obrigatório, array com horários válidos e em ordem cronológica.
- `requested_entries.*.type`: um dos quatro tipos permitidos.
- `requested_entries.*.time`: formato `H:i`.
- `reason`: obrigatório, texto entre 10 e 1000 caracteres.

## Rotas

As rotas ficarão dentro do grupo autenticado existente e usarão middleware de permissão Spatie.

```php
Route::prefix('virtual-office')
    ->name('virtual-office.')
    ->middleware('permission:intranet.access')
    ->group(function () {
        Route::get('/', VirtualOfficeDashboardController::class)
            ->name('dashboard');

        Route::get('time-card', [TimeCardController::class, 'index'])
            ->middleware('permission:time-records.view-own')
            ->name('time-card.index');

        Route::post('time-adjustments', [TimeAdjustmentRequestController::class, 'store'])
            ->middleware('permission:time-records.request-adjustment')
            ->name('time-adjustments.store');
    });
```

Parâmetro de consulta do Cartão de Ponto: `month`, no formato `YYYY-MM`. Se ausente, será usado o mês atual.

## Páginas frontend

- `resources/js/pages/virtual-office/dashboard.tsx`
- `resources/js/pages/virtual-office/time-card/index.tsx`

Na sidebar haverá o botão **Escritório Virtual**, com os sublinks **Dashboard** e **Cartão de Ponto**.

## Fora da primeira fase

- Solicitação e aprovação completa de férias.
- Holerites e informe de rendimentos.
- Benefícios.
- Documentos do colaborador.
- Dados pessoais editáveis.
- Comunicados, avaliações e treinamentos.
- Registro de ponto em tempo real pela aplicação.
- Painéis de gestão e Departamento Pessoal.
- Integração com relógio de ponto ou folha de pagamento.
