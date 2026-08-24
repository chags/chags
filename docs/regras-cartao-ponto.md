# Regras do Cartão de Ponto — Brasil

## Objetivo

Este documento define as regras funcionais do Cartão de Ponto do Escritório Virtual. As marcações devem representar os horários reais do colaborador e serão armazenadas sem arredondamento ou alteração.

> Importante: estas regras devem ser validadas pelo Departamento Pessoal e pela assessoria trabalhista da empresa, especialmente quando existir acordo ou convenção coletiva.

## 1. Jornada semanal

- A jornada padrão será configurável por colaborador.
- Para a jornada comum, o limite normal é de até 8 horas diárias e 44 horas semanais.
- A distribuição das 44 horas dependerá da escala cadastrada. Exemplo: 8 horas de segunda a sexta e 4 horas no sábado.
- O sistema calculará diariamente:
  - minutos previstos;
  - minutos trabalhados;
  - saldo positivo ou negativo;
  - horas extras;
  - saldo acumulado semanal e do banco de horas.
- A semana será considerada de segunda-feira a domingo.
- Folgas, feriados, férias, afastamentos e escalas especiais não gerarão débito quando estiverem corretamente cadastrados.

## 2. Marcações obrigatórias

Toda jornada deve possuir marcações em pares: uma entrada precisa ter uma saída correspondente.

### Jornada mínima — duas marcações

1. `clock_in`: entrada no início da jornada.
2. `clock_out`: saída no fim da jornada.

Essa modalidade será usada somente quando não houver intervalo intrajornada a registrar ou quando houver dispensa legal da marcação do intervalo.

### Jornada padrão com almoço — quatro marcações

1. `clock_in`: entrada pela manhã.
2. `break_start`: saída para o almoço.
3. `break_end`: retorno do almoço.
4. `clock_out`: saída no final do expediente.

Exemplo de jornada:

| Evento | Horário previsto |
| --- | ---: |
| Entrada | 08:00 |
| Saída para almoço | 12:00 |
| Retorno do almoço | 13:00 |
| Saída | 17:00 |

Regras:

- Uma jornada com quantidade ímpar de marcações ficará `incomplete`.
- Uma entrada sem saída correspondente ficará `incomplete`.
- O sistema não criará automaticamente uma marcação ausente.
- O colaborador deverá solicitar ajuste, informando os horários reais e a justificativa.
- A marcação original nunca será apagada ou substituída; ajustes deverão manter histórico e responsável pela aprovação.

## 3. Intervalo para almoço

- O intervalo não será contabilizado como tempo trabalhado.
- Para jornadas superiores a 6 horas, a regra padrão é intervalo mínimo de 1 hora.
- A redução do intervalo somente poderá ser configurada quando houver fundamento legal, acordo ou convenção coletiva aplicável.
- O tempo trabalhado no dia será a soma dos pares:

```text
(saída para almoço - entrada)
+
(saída final - retorno do almoço)
```

## 4. Tolerância e janela de marcação

### Regra legal padrão

A tolerância da CLT será aplicada da seguinte forma:

- até 5 minutos em cada marcação;
- limite máximo de 10 minutos somados no dia;
- somente quando os dois limites forem respeitados as variações poderão ser desconsideradas;
- se qualquer marcação ultrapassar 5 minutos ou a soma diária ultrapassar 10 minutos, o sistema calculará o tempo efetivamente registrado, conforme parametrização validada pelo DP.

### Janela operacional desejada

A empresa deseja permitir um aviso de janela de 10 minutos antes e 10 minutos depois de cada horário previsto.

Exemplo para entrada prevista às 08:00:

- início da janela: 07:50;
- horário previsto: 08:00;
- fim da janela: 08:10.

Essa janela de ±10 minutos será tratada como **janela operacional de alerta**, e não como arredondamento automático nem como tolerância legal padrão.

- O relógio deve registrar o horário real, inclusive fora da janela.
- O sistema não deve impedir a marcação fora da janela.
- Marcações fora da janela serão sinalizadas para conferência.
- Para cálculo legal automático, prevalecerão 5 minutos por marcação e 10 minutos no total diário, salvo regra coletiva formalmente cadastrada.

### Contabilização solicitada pela empresa

A regra de negócio desejada é:

- entrada antes do horário previsto: minutos positivos;
- entrada depois do horário previsto: minutos negativos;
- saída antes do horário previsto: minutos negativos;
- saída depois do horário previsto: minutos positivos.

Exemplos para entrada prevista às 08:00:

| Marcação real | Diferença bruta |
| --- | ---: |
| 07:50 | +10 minutos |
| 07:55 | +5 minutos |
| 08:00 | 0 |
| 08:05 | -5 minutos |
| 08:10 | -10 minutos |

O sistema deverá guardar dois valores diferentes:

1. `raw_balance_minutes`: diferença real entre jornada prevista e realizada.
2. `payable_balance_minutes`: saldo após aplicação da tolerância legal, acordo coletivo, autorização de hora extra e regras do banco de horas.

Isso evita perder a marcação real e permite que o DP altere a regra de cálculo sem modificar o histórico.

## 5. Horas extras

- A jornada diária poderá ter no máximo 2 horas extras, quando autorizadas por acordo individual, acordo coletivo ou convenção coletiva.
- Hora extra não deve ser confundida com uma simples marcação antecipada ou tardia.
- O sistema deverá exigir autorização ou regra de aprovação para transformar saldo positivo em hora extra ou crédito no banco de horas.
- O limite diário normal, incluindo horas extras, será de 10 horas trabalhadas, salvo regime legal específico.

### Trabalho extraordinário separado da jornada

Quando o colaborador encerrar a jornada normal e retornar para um período extraordinário, deverá registrar um novo par:

5. `overtime_in`: entrada para hora extra.
6. `overtime_out`: saída da hora extra.

Exemplo:

| Evento | Horário |
| --- | ---: |
| Saída da jornada normal | 17:00 |
| Entrada para hora extra | 17:30 |
| Saída da hora extra | 19:00 |

Regras:

- `overtime_in` exige `overtime_out` correspondente.
- O período extraordinário não poderá ultrapassar 120 minutos no dia.
- Se ultrapassar 120 minutos, o sistema registrará o horário real, sinalizará a irregularidade e exigirá análise do DP.
- O sistema nunca deve truncar ou alterar a marcação real para adequá-la ao limite.

## 6. Cálculo diário

### Tempo trabalhado

```text
worked_minutes = soma de todos os pares válidos de entrada e saída
```

Para uma jornada padrão com almoço:

```text
worked_minutes =
    (break_start - clock_in)
    + (clock_out - break_end)
    + períodos extraordinários válidos
```

### Saldo bruto

```text
raw_balance_minutes = worked_minutes - expected_minutes
```

### Saldo contabilizável

```text
payable_balance_minutes =
    saldo bruto
    após tolerância aplicável
    após justificativas e abonos
    após autorização de hora extra
```

Possíveis destinos do saldo:

- crédito no banco de horas;
- débito no banco de horas;
- hora extra para pagamento;
- atraso ou falta;
- compensação;
- saldo desconsiderado pela tolerância;
- pendência para análise.

## 7. Fechamento semanal

- O sistema somará as horas previstas e realizadas de todos os dias da semana.
- Para jornada contratual de 44 horas:

```text
weekly_balance_minutes = weekly_worked_minutes - 2.640
```

- Não haverá débito automático apenas porque um dia ficou abaixo da jornada se a escala permitir compensação dentro da semana.
- O fechamento considerará feriados, folgas, férias, afastamentos, abonos e compensações.
- Atingir 44 horas na semana não autoriza ultrapassar os limites diários sem a regra legal ou coletiva correspondente.

## 8. Situações do dia

| Situação | Significado |
| --- | --- |
| `open` | Jornada iniciada e ainda não encerrada |
| `regular` | Marcações completas e jornada cumprida |
| `incomplete` | Quantidade ímpar ou par incompleto |
| `missing` | Dia previsto sem marcações |
| `pending_adjustment` | Ajuste solicitado e aguardando análise |
| `adjusted` | Ajuste aprovado com histórico preservado |
| `overtime_pending` | Hora extra aguardando autorização |
| `outside_window` | Marcação fora da janela operacional |
| `daily_limit_exceeded` | Mais de 2 horas extras ou limite diário ultrapassado |

## 9. Configurações necessárias

As regras não devem ficar fixas no código. O sistema deverá permitir configurar:

- dias da semana trabalhados;
- horários previstos de entrada, intervalo e saída;
- minutos esperados por dia e por semana;
- jornada semanal, com padrão de 44 horas;
- tolerância legal por marcação, padrão de 5 minutos;
- tolerância legal diária, padrão de 10 minutos;
- janela operacional antes e depois do horário, padrão desejado de 10 minutos;
- limite de horas extras diárias, padrão de 120 minutos;
- exigência de autorização de hora extra;
- destino do saldo positivo: banco de horas ou pagamento;
- acordo ou convenção coletiva e sua vigência;
- feriados, folgas e escalas especiais.

## 10. Integridade e auditoria

- Toda marcação deve conter usuário, data, hora, fuso, origem e identificador único.
- A marcação original é imutável.
- Ajustes devem registrar valor anterior, valor proposto, justificativa, solicitante, aprovador e datas.
- O trabalhador deve conseguir consultar suas marcações e comprovantes.
- O sistema deverá preservar os pares sequenciais de entrada e saída.
- O programa de tratamento deverá produzir os arquivos e relatórios exigidos caso a aplicação seja utilizada como REP-P/PTRP oficial.

## 11. Decisões pendentes antes da implementação

1. Confirmar com o DP se a janela de ±10 minutos será apenas alerta ou se existe instrumento coletivo que altere o cálculo.
2. Definir se os primeiros minutos positivos irão diretamente para banco de horas ou dependerão de autorização.
3. Definir como serão tratados atrasos compensados no mesmo dia ou na mesma semana.
4. Definir a distribuição das 44 horas para cada escala.
5. Definir regras de sábado, domingo, feriado e trabalho noturno.
6. Definir quem aprova ajustes e horas extras.
7. Definir se o sistema será somente gerencial ou se será registrado como REP-P e programa de tratamento oficial.

## 12. Setor Pessoal — Configuração do Cartão de Ponto

O sistema terá um novo módulo administrativo chamado **Setor Pessoal**. Seu primeiro submenu será **Configuração do Cartão de Ponto**.

Rota proposta: `GET /personnel/time-card-settings`.

Essa tela será o local central para criar grupos de jornada, definir escalas e vincular usuários. Somente usuários com `time-records.manage` poderão alterar configurações; usuários com `time-records.view` poderão consultá-las.

### Dashboard de configuração

O dashboard deverá apresentar:

- quantidade de grupos ativos;
- usuários que batem ponto;
- usuários ainda sem grupo;
- distribuição de usuários por tipo de escala;
- grupos com configurações incompletas;
- atalhos para criar grupo, editar escala e vincular usuários.

### Grupo de jornada

Cada grupo terá:

- nome único, por exemplo `Comercial Matriz`;
- descrição;
- tipo de escala;
- carga horária semanal;
- horários por dia ou por ciclo;
- tolerância legal por marcação;
- tolerância legal diária;
- janela operacional;
- limite diário de horas extras;
- exigência de autorização para hora extra;
- data inicial do ciclo, quando aplicável;
- situação ativa ou inativa;
- usuários vinculados e vigência de cada vínculo.

### Tipos de escala

#### `5x2` — Comercial

- Cinco dias trabalhados e dois dias de descanso.
- Configuração padrão sugerida: segunda a sexta-feira.
- A carga diária será calculada conforme a distribuição das horas semanais.
- O grupo poderá possuir horários diferentes por dia.

#### `6x1`

- Seis dias trabalhados e um dia de descanso.
- O dia de descanso será configurável.
- O sistema deverá respeitar o descanso semanal e a carga semanal definida.

#### `12x36`

- Doze horas de trabalho seguidas por trinta e seis horas de descanso.
- Exige uma data inicial de referência para calcular os dias trabalhados e de descanso.
- O ciclo terá dois dias: `trabalho` e `descanso`.
- Intervalos e regras coletivas aplicáveis deverão ser configurados e validados pelo DP.

#### `custom`

- Escala totalmente personalizável.
- Permitirá definir a duração do ciclo e cada dia como trabalho ou descanso.
- Cada dia trabalhado poderá ter horários e carga diária próprios.

### Aplicação ao usuário

- Apenas usuários com `tracks_time = true` poderão ser vinculados a um grupo de jornada.
- Um usuário poderá ter somente um grupo ativo na mesma data.
- Todo vínculo terá `valid_from` e `valid_until`, permitindo preservar o histórico de mudanças de escala.
- Ao calcular o cartão, o sistema localizará o grupo vigente na data da jornada.
- Alterar um grupo não poderá reescrever marcações originais.
- Mudanças retroativas deverão exigir confirmação e ficar registradas em auditoria.
- Usuários que batem ponto e não possuem grupo serão exibidos como pendência no dashboard.

### Estrutura prevista

#### `work_schedule_groups`

Armazena nome, tipo de escala, carga semanal, tolerâncias, limite de hora extra, início do ciclo, situação e responsável pela criação.

#### `work_schedule_days`

Armazena os dias ou posições do ciclo, indicando trabalho/descanso, entrada, intervalo, saída e minutos esperados.

#### `work_schedule_assignments`

Vincula usuários aos grupos com período de vigência, situação e responsável pela atribuição.

### Regras de cálculo

1. Localizar o vínculo de grupo vigente para o usuário e a data.
2. Resolver o dia da escala por dia da semana (`5x2` e `6x1`) ou posição do ciclo (`12x36` e `custom`).
3. Carregar horários, minutos esperados, tolerâncias e limite de hora extra do grupo.
4. Aplicar as marcações reais sem arredondar ou alterá-las.
5. Gerar saldo bruto, saldo contabilizável, ocorrências e pendências.
6. Manter o grupo e a regra usados no fechamento para preservar a rastreabilidade.

## Referências oficiais

- [Constituição Federal, art. 7º, XIII e XVI](https://www.planalto.gov.br/ccivil_03/constituicao/constituicao.htm): limites de jornada e adicional de hora extra.
- [CLT, arts. 58, 59, 71 e 74](https://www.planalto.gov.br/ccivil_03/decreto-lei/del5452compilado.htm): tolerância, horas extras, intervalo e controle de jornada.
- [Portaria MTP nº 671/2021 — versão compilada](https://www.gov.br/trabalho-e-emprego/pt-br/assuntos/legislacao/portarias-1/portarias-vigentes-3/PDFPortarian671de8denovembrode2021compilada13.05.2025.pdf): registro eletrônico de ponto e programa de tratamento.
- [Perguntas e Respostas sobre a Portaria nº 671/2021](https://www.gov.br/trabalho-e-emprego/pt-br/assuntos/inspecao-do-trabalho/fiscalizacao-do-trabalho/Perguntas%20e%20Respostas%20REP), Ministério do Trabalho e Emprego: marcações realizadas em pares e preservação da realidade das marcações.
