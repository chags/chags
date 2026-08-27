# Regras de aprovação de atestados

## Objetivo

O atestado médico aprovado deve abonar os dias úteis cobertos pelo documento e contabilizar a jornada prevista como trabalhada. As marcações geradas pelo sistema são identificadas como `medical_certificate` e exibidas em azul no cartão de ponto.

## Fluxo

1. O colaborador envia o documento pelo Escritório Virtual.
2. O documento fica com status `pending` e não altera o ponto.
3. O Setor Pessoal acessa **Setor Pessoal > Aprovar atestados**, confere o arquivo e a justificativa e decide aprovar ou rejeitar.
4. Na aprovação, o sistema usa a jornada válida em cada data para criar as marcações abonadas de entrada, intervalo e saída.
5. Na rejeição, o motivo é obrigatório e nenhuma marcação é criada.

## Contabilização

- O crédito é obtido da escala cadastrada, nunca de um número fixo no código.
- Uma jornada semanal de 44 horas deve estar distribuída na configuração da escala. Exemplo: 528 minutos por dia em cinco dias úteis totalizam 44 horas.
- Dias de folga ou sem jornada não recebem marcações nem crédito.
- A jornada abonada conta nas horas trabalhadas do dia, mês e semana.
- O atestado aprovado continua sinalizado como ocorrência abonada, em azul.
- Declarações de comparecimento continuam seguindo o intervalo parcial informado; elas não geram uma jornada integral.

## Integridade e auditoria

- Cada marcação abonada mantém vínculo com o atestado que a originou.
- As marcações registram o responsável e o horário da aprovação.
- Um documento só pode ser analisado uma vez.
- Atestados aprovados antes da implantação podem ser sincronizados de forma idempotente com `php artisan medical-certificates:sync-approved`.
- A operação é transacional: ou todas as marcações válidas são criadas e o documento é aprovado, ou nenhuma alteração é persistida.
- Se o dia já possuir batidas ativas conflitantes, a aprovação é bloqueada para análise manual do cartão, evitando horas duplicadas.
- O arquivo permanece privado e somente usuários autorizados podem consultá-lo.

## Permissão

O acesso e a decisão usam a permissão Spatie `medical-certificates.review`. `super-admin` mantém acesso irrestrito no guard `web`.
