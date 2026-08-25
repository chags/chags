# Regras do gestor para ponto e banco de horas

## Objetivo

Definir as ações, permissões e responsabilidades de gestores e autoridades na análise de batidas, exceções de jornada, horas extras, folgas compensatórias e ausências justificadas.

As regras funcionais do cartão de ponto do colaborador estão especificadas em [folga.md](folga.md).

## Autorização

Todas as permissões devem ser implementadas com `spatie/laravel-permission`. Não devem ser criadas colunas booleanas de gestor ou um sistema paralelo de autorização.

Permissões sugeridas:

- `time-entries.view-team`;
- `time-entries.approve`;
- `time-entries.reject`;
- `work-schedule-exceptions.manage`;
- `overtime.approve`;
- `hour-bank-leave.manage`;
- `medical-certificates.review`;
- `time-audit.view`.

Possuir a permissão não concede acesso irrestrito a todos os colaboradores. O sistema também deve validar se o usuário possui autoridade sobre o colaborador, unidade ou equipe correspondente. O papel `super-admin`, no guard `web`, permanece irrestrito.

## Fila de análise

A fila deve apresentar:

- colaborador e unidade;
- data e horário;
- tipo e origem da batida;
- jornada regular, hora extra, folga ou feriado;
- horário previsto e janela permitida;
- justificativa e anexos autorizados;
- motivo da pendência ou cancelamento;
- batida original relacionada, quando houver;
- status atual.

## Decisões sobre batidas

O gestor autorizado pode:

- aprovar uma batida `pending`, alterando-a para `approved`;
- rejeitar uma batida `pending`, alterando-a para `cancelled`;
- analisar a batida manual criada para corrigir uma automática cancelada;
- consultar o histórico sem apagar registros.

Uma batida automática cancelada por estar fora da janela nunca deve ser convertida diretamente em aprovada. O colaborador cria uma batida manual correta, vinculada à original, e o gestor decide sobre a nova batida.

O comentário é obrigatório em rejeições e correções. Toda decisão registra responsável e data.

## Exceção individual de jornada

O gestor pode autorizar, para um colaborador e uma data específica:

- entrada ou saída antecipada;
- entrada ou saída posterior;
- alteração do intervalo;
- jornada especial no dia.

A exceção não altera o grupo de horário. Antes de salvar, o sistema deve mostrar o horário original e o horário excepcional, validar a duração e registrar uma justificativa.

## Horas extras

As batidas `overtime_start` e `overtime_end` sempre exigem análise.

O gestor deve analisar o período completo e verificar:

- entrada anterior à saída;
- inexistência de sobreposição;
- inexistência de período simultaneamente contabilizado como jornada regular;
- justificativa;
- duração calculada.

Somente quando o par estiver aprovado o período poderá gerar saldo. Se uma batida for rejeitada, o período inteiro não gera crédito.

## Folga compensatória

O gestor pode registrar diretamente uma folga pelo banco de horas, sem exigir solicitação prévia do colaborador.

Ao registrar a folga, o sistema deve:

- mostrar o saldo disponível;
- mostrar a jornada prevista para o dia;
- impedir saldo insuficiente, salvo política explícita;
- reservar os minutos necessários;
- criar a exceção `hour_bank_leave`;
- debitar os minutos na data definida;
- impedir ausência e pendências de batidas naquele dia.

O cancelamento deve gerar uma movimentação inversa e preservar a auditoria.

## Atestado médico

A análise deve permitir aprovar total ou parcialmente o período informado. Quando aprovado, o sistema registra os minutos abonados sem gerar crédito ou débito no banco de horas.

O acesso ao documento deve ser restrito às permissões adequadas. Um gestor sem autorização para dados médicos deve visualizar apenas que existe uma ausência justificada em análise, sem acessar informações clínicas ou o arquivo.

As exigências documentais, prazos e autoridades competentes devem ser configuradas conforme a política de RH e as regras aplicáveis à empresa.

## Auditoria

Cada decisão deve registrar:

- tipo da ocorrência;
- status anterior e novo status;
- valores anteriores e novos, quando aplicável;
- usuário responsável;
- data e hora;
- comentário;
- vínculo com batida, exceção, documento ou transação do banco de horas.

Registros financeiros do banco de horas e batidas não devem ser apagados. Correções devem usar novas decisões ou transações inversas.

## Critérios de aceite

- Somente usuários com permissão e autoridade sobre o colaborador podem decidir.
- O `super-admin` no guard `web` possui acesso irrestrito.
- Rejeições e correções exigem comentário.
- Batidas automáticas canceladas não são aprovadas diretamente.
- Horas extras somente geram saldo após aprovação do par completo.
- Exceções individuais não alteram grupos de horário.
- Folgas compensatórias validam e debitam a jornada prevista.
- Aprovação de atestado abona horas sem movimentar o banco.
- Toda decisão e movimentação permanece auditável.
