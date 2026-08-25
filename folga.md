# Regras de aprovação e sinalização das batidas de ponto

## Objetivo

Controlar as batidas do cartão de ponto conforme a escala e as janelas de horário do colaborador, identificando automaticamente registros aprovados, pendentes de aprovação ou cancelados.

As regras devem funcionar tanto para dias normais de trabalho quanto para dias de folga.

## Tipos de batida

Em um dia normal de trabalho, o cartão de ponto prevê quatro batidas regulares, nesta ordem:

1. Entrada (`clock_in`)
2. Início do intervalo (`break_start`)
3. Fim do intervalo (`break_end`)
4. Saída (`clock_out`)

Cada tipo de batida deve possuir uma janela de horário configurada na escala do colaborador.

Além das batidas regulares, o seletor de **batidas extras** deve oferecer:

5. Hora extra — Entrada (`overtime_start`)
6. Hora extra — Saída (`overtime_end`)

As batidas de hora extra não fazem parte das quatro posições regulares do cartão. Elas devem aparecer em uma área própria de horas extras no respectivo dia.

## Status e cores

| Status | Cor | Significado |
|---|---|---|
| `approved` | Verde | Batida válida e aprovada |
| `pending` | Amarelo | Batida ou ausência aguardando análise de uma autoridade |
| `cancelled` | Vermelho | Batida automática feita fora da janela permitida |

A cor deve ser aplicada individualmente em cada batida, e não apenas no dia inteiro.

Além da cor, a interface deve exibir o texto ou ícone do status. A informação não pode depender somente da cor, por questões de acessibilidade.

## Matriz de decisão

| Situação | Status inicial | Cor |
|---|---|---|
| Batida automática em dia de trabalho, dentro da janela | `approved` | Verde |
| Batida automática em dia de trabalho, fora da janela | `cancelled` | Vermelho |
| Batida manual, dentro ou fora da janela | `pending` | Amarelo |
| Batida automática ou manual em dia de folga | `pending` | Amarelo |
| Batida prevista não realizada até o fim da janela | Pendência de batida ausente | Amarelo |
| Hora extra — Entrada ou Saída | `pending` | Amarelo |

O dia de folga tem precedência sobre a regra de janela: nenhuma batida realizada em uma folga pode ser aprovada automaticamente.

## Regras funcionais

### 1. Batida automática dentro da janela

Quando o colaborador registra uma batida pelo relógio normal da aplicação e o horário está dentro da janela correspondente:

- a batida é criada com status `approved`;
- a batida aparece em verde no cartão de ponto;
- não é necessária aprovação posterior;
- a aprovação deve ser registrada como automática pelo sistema.

### 2. Batida automática fora da janela

Quando o colaborador registra uma batida pelo relógio normal, mas fora da janela do tipo esperado:

- a batida deve ser registrada para preservar o histórico;
- seu status inicial deve ser `cancelled`;
- ela deve aparecer em vermelho;
- o sistema deve registrar o motivo `outside_window`;
- a batida cancelada não entra no cálculo de horas;
- ela deve permanecer cancelada no histórico e não pode ser convertida diretamente em uma batida aprovada;
- o colaborador deve criar uma nova batida manual com o horário correto e uma justificativa;
- a nova batida manual fica `pending` até a decisão do gestor.

### 3. Batida manual

Toda batida criada manualmente deve exigir aprovação, independentemente do horário ou da janela:

- o status inicial deve ser `pending`;
- a batida deve aparecer em amarelo;
- deve ser registrado que a origem é manual;
- o usuário deve informar uma justificativa;
- a batida não entra no cálculo definitivo de horas até ser aprovada.

Essa regra também se aplica quando a batida manual estiver dentro da janela prevista.

### 4. Batida em dia de folga

Qualquer batida realizada em um dia identificado como folga na escala do colaborador deve ser analisada por uma autoridade:

- o status inicial deve ser `pending`;
- a batida deve aparecer em amarelo;
- o sistema deve registrar o motivo `day_off`;
- nenhuma batida em folga deve ser aprovada automaticamente;
- após a aprovação, a batida passa para `approved` e aparece em verde;
- enquanto estiver pendente, ela não entra no cálculo definitivo de horas trabalhadas.

Caso uma batida em folga também seja manual, devem ser registrados os dois fatores: origem manual e dia de folga.

### 5. Batida prevista não realizada

Quando o fim da janela de uma das quatro batidas for ultrapassado sem existir uma batida válida:

- o cartão deve sinalizar a posição esperada como pendente e em amarelo;
- essa pendência representa uma batida ausente, não uma batida real;
- o colaborador pode solicitar uma batida manual, informando horário e justificativa;
- a solicitação manual permanece `pending` até a decisão da autoridade;
- não deve ser criada uma batida fictícia automaticamente.

### 6. Batidas de hora extra

O colaborador poderá registrar um período de hora extra por meio do seletor de batidas extras, usando:

- **Hora extra — Entrada** (`overtime_start`), para iniciar o período;
- **Hora extra — Saída** (`overtime_end`), para encerrar o período.

Toda batida de hora extra deve ser submetida à aprovação do gestor:

- o status inicial deve ser `pending`;
- a batida deve aparecer em amarelo;
- deve ser registrado o motivo `overtime`;
- o colaborador deve informar uma justificativa;
- a entrada e a saída devem ser analisadas pelo gestor;
- o período não entra no total de horas extras enquanto não estiver integralmente aprovado.

A hora extra deve respeitar as seguintes validações:

- uma entrada de hora extra deve existir antes da respectiva saída;
- a saída deve ocorrer depois da entrada;
- não pode haver dois períodos de hora extra abertos simultaneamente para o mesmo colaborador;
- um par incompleto deve aparecer como pendente e não pode gerar saldo;
- a duração deve ser calculada entre a entrada e a saída aprovadas;
- períodos de hora extra não podem se sobrepor entre si;
- períodos de hora extra não podem ser contabilizados sobre um intervalo já contabilizado como jornada regular;
- se uma das duas batidas for rejeitada ou cancelada, o período inteiro não deve ser contabilizado;
- a decisão deve identificar o gestor responsável e a data da análise.

Depois que o gestor aprovar as duas batidas, ambas passam para `approved`, aparecem em verde e o período passa a compor o total de horas extras. Se o gestor rejeitar a solicitação, as batidas passam para `cancelled`, aparecem em vermelho e não geram saldo.

Em dia de folga, as batidas de hora extra continuam pendentes e seguem o mesmo fluxo de aprovação do gestor. A aprovação do par permite contabilizar o período como hora extra trabalhada na folga.

## Prioridade das regras

Ao determinar o status inicial, o sistema deve aplicar esta ordem:

1. Se o dia é folga, a batida fica `pending`.
2. Se é uma batida de hora extra, fica `pending`.
3. Se a origem é manual, a batida fica `pending`.
4. Se é automática e está dentro da janela, fica `approved`.
5. Se é automática e está fora da janela, fica `cancelled`.

## Encaminhamento para aprovação

Batidas e ocorrências que exigem análise devem ser encaminhadas para a fila de aprovação com os dados necessários para a decisão. As permissões, ações e responsabilidades do gestor estão especificadas em [gestor-ponto.md](gestor-ponto.md).

Uma batida automática cancelada por estar fora da janela deve continuar cancelada para fins de auditoria. A correção deve ocorrer por meio de uma nova batida manual pendente, mantendo o vínculo entre a batida cancelada e sua solicitação de correção.

## Exceção individual de jornada

Uma alteração válida apenas para um colaborador e uma data não deve modificar o grupo de horário.

- o grupo continua sendo a fonte do horário padrão;
- a exceção individual tem prioridade somente na data informada;
- nos demais dias, o colaborador continua seguindo o grupo;
- uma exceção pode alterar entrada, intervalo e saída;
- batidas automáticas dentro das janelas da exceção são aprovadas normalmente;
- sem exceção prévia, prevalecem as janelas do grupo.

A ordem para resolver o horário do dia deve ser:

1. Exceção individual do colaborador na data.
2. Horário do grupo vigente.
3. Sem configuração aplicável, encaminhar as batidas para análise.

## Feriados

O calendário deve aceitar feriados nacionais, estaduais, municipais e próprios da empresa, aplicáveis por empresa, unidade ou local de trabalho. Também deve aceitar feriado integral ou parcial.

Em um feriado aplicável ao colaborador:

- a ausência de batidas não gera falta nem pendência;
- o cartão mostra o rótulo **Feriado**;
- qualquer batida automática ou manual fica `pending`;
- um período incompleto não gera horas trabalhadas;
- somente pares aprovados podem ser contabilizados como trabalho em feriado;
- as horas de feriado devem ser classificadas separadamente das horas extras comuns.

## Folga compensatória pelo banco de horas

Uma folga previamente combinada pode consumir o saldo positivo do banco de horas sem exigir batidas no dia.

Exemplo para saldo de oito horas e jornada prevista de oito horas:

1. A folga é registrada para o colaborador e a data.
2. O sistema valida e reserva 480 minutos do saldo.
3. O cartão mostra **Folga — Banco de horas**.
4. O colaborador não precisa realizar as quatro batidas.
5. O sistema lança uma transação de `-480` minutos.
6. O saldo passa de oito horas para zero.

Regras:

- o débito corresponde à jornada prevista para a data, não a um valor fixo;
- a ausência de batidas nesse dia não gera falta;
- o sistema nunca deve deduzir que uma ausência é folga compensatória sem um lançamento previamente autorizado;
- o saldo não pode ficar negativo, salvo política explícita da empresa;
- o cancelamento da folga gera uma transação inversa, sem apagar o lançamento original;
- se houver batidas durante a folga, elas ficam `pending` para análise antes do fechamento.

## Ausência abonada por atestado médico

Uma ausência coberta por atestado deve ser registrada como ocorrência, sem criar batidas fictícias e sem descontar o banco de horas.

O colaborador deve poder informar:

- período de dia inteiro, parcial ou múltiplos dias;
- data inicial e final;
- horários inicial e final, quando o afastamento for parcial;
- justificativa;
- documento comprobatório.

Enquanto estiver em análise, a ocorrência fica `pending` e amarela. Quando aprovada:

- passa para `approved` e verde;
- o cartão mostra **Ausência abonada — atestado médico**;
- as horas cobertas deixam de ser ausência;
- não há débito no banco de horas;
- as horas abonadas permanecem separadas das horas efetivamente trabalhadas.

Quando rejeitada, a ocorrência passa para `cancelled`, em vermelho, e o período volta a ser tratado como ausência a regularizar.

Documentos médicos são dados sensíveis e devem possuir acesso restrito. O sistema deve evitar expor informações clínicas desnecessárias no cartão de ponto.

## Auditoria mínima

Cada batida deve permitir identificar:

- `status`: `pending`, `approved` ou `cancelled`;
- `source`: `automatic` ou `manual`;
- data e hora registradas;
- tipo da batida;
- motivo da exceção, quando houver;
- usuário que criou o registro manual;
- autoridade que aprovou, rejeitou ou regularizou;
- data e hora da decisão;
- justificativa do colaborador;
- comentário da autoridade.

Uma alteração de status nunca deve apagar o registro ou seus valores anteriores.

## Exibição no cartão de ponto

O cartão deve manter quatro posições previstas por dia normal. Cada posição pode apresentar:

- horário registrado;
- status e cor;
- indicação de batida manual;
- indicação de batida fora da janela;
- indicação de aprovação por autoridade;
- ação para solicitar ajuste quando a batida estiver ausente.

Os períodos de hora extra devem exibir entrada, saída, duração calculada e status da aprovação. Um período incompleto deve mostrar claramente qual batida ainda está ausente.

Nos dias de folga, o cartão deve mostrar claramente o rótulo **Folga**. Caso existam batidas, elas devem aparecer separadamente e inicialmente em amarelo, aguardando aprovação.

## Exemplos

### Dia normal

| Evento | Resultado |
|---|---|
| Entrada automática às 08:02, dentro da janela | Verde, `approved` |
| Início do intervalo não registrado | Amarelo, batida ausente |
| Fim do intervalo inserido manualmente às 13:00 | Amarelo, `pending` |
| Saída automática após o fim da janela | Vermelho, `cancelled` |
| Nova saída manual informada no horário correto | Amarelo, `pending`, aguardando o gestor |

### Hora extra

| Evento | Resultado |
|---|---|
| Hora extra — Entrada às 18:10 | Amarelo, `pending` |
| Hora extra — Saída às 20:10 | Amarelo, `pending` |
| Gestor aprova as duas batidas | Ambas verdes; 2 horas extras contabilizadas |
| Gestor rejeita uma das batidas | Período vermelho e não contabilizado |

### Dia de folga

| Evento | Resultado |
|---|---|
| Entrada automática | Amarelo, `pending`, motivo `day_off` |
| Saída manual | Amarelo, `pending`, origem manual e motivo `day_off` |
| Autoridade aprova a entrada | Verde, `approved` |

## Critérios de aceite

- O sistema considera a escala e a janela específica de cada tipo de batida.
- Batidas automáticas dentro da janela, em dia normal, são aprovadas automaticamente.
- Batidas automáticas fora da janela, em dia normal, são registradas como canceladas.
- Toda batida manual fica pendente, mesmo dentro da janela.
- Toda batida em folga fica pendente, seja automática ou manual.
- O seletor de batidas extras oferece Hora extra — Entrada e Hora extra — Saída.
- Toda batida de hora extra fica pendente até a decisão do gestor.
- Horas extras somente são contabilizadas quando a entrada e a saída estiverem aprovadas.
- Pares incompletos, rejeitados, cancelados ou sobrepostos não geram saldo de hora extra.
- Exceções individuais não alteram o horário dos demais integrantes do grupo.
- Feriados sem batidas não geram falta.
- Trabalho em feriado exige aprovação e contabilização separada.
- Folga compensatória previamente autorizada debita a jornada prevista do banco de horas.
- Ausência abonada por atestado não cria batidas fictícias nem debita o banco de horas.
- Batidas previstas e ausentes são sinalizadas em amarelo sem criar registros fictícios.
- Cada batida possui seu próprio status e sua própria sinalização visual.
- Somente batidas aprovadas entram no cálculo definitivo de horas.
- Toda decisão de uma autoridade fica registrada para auditoria.
