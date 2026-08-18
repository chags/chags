# Teste comportamental baseado no modelo DISC

## 1. Objetivo

Implementar a fase 3 do processo seletivo: um questionário comportamental autoral baseado nas quatro dimensões do modelo DISC.

O candidato acessará o teste pela página da própria candidatura. Serão apresentadas 20 perguntas, uma por vez. Após concluir, o sistema calculará o perfil predominante, armazenará as respostas e mostrará ao candidato uma flag com o perfil e um modal explicativo.

Este questionário não é o produto comercial Everything DiSC®, não reproduz suas perguntas e não deve ser apresentado como teste psicológico, diagnóstico, avaliação clínica ou instrumento cientificamente validado.

## 2. Referências conceituais

O modelo descreve quatro tendências comportamentais:

- **D — Dominância:** foco em resultados, ação, desafios e decisões.
- **I — Influência:** foco em comunicação, entusiasmo, persuasão e relacionamentos.
- **S — Estabilidade:** foco em cooperação, constância, apoio e ambiente previsível.
- **C — Conformidade/Conscienciosidade:** foco em qualidade, precisão, análise e critérios.

Todos possuem uma combinação das quatro dimensões. O perfil predominante indica preferência relativa, não competência, caráter ou capacidade profissional. Não existe perfil melhor ou pior.

Fontes consultadas:

- Everything DiSC, visão geral do modelo: https://www.everythingdisc.com/what-is-disc/
- DiSC Profile, funcionamento e limites: https://www.discprofile.com/what-is-disc/how-disc-works
- DiSC Profile, estilos comportamentais: https://www.discprofile.com/disc-styles
- Everything DiSC, teoria e pesquisa: https://www.discprofile.com/CMS/media/doc/ed/research/theory-research.pdf
- U.S. Copyright Office, proteção aplicável a perguntas e respostas de testes: https://www.copyright.gov/comp3/2017version/redlines/chap700.pdf

## 3. Regras funcionais

1. O teste pertence a uma candidatura específica.
2. Somente o candidato dono da candidatura pode acessá-lo.
3. O teste só fica disponível quando a candidatura estiver na fase pública `disc`.
4. Antes de começar, o candidato verá finalidade, duração estimada e aviso de privacidade.
5. Após clicar em **Começar teste**, a tentativa fica registrada.
6. O sistema apresenta uma pergunta por tela.
7. Todas as 20 perguntas são obrigatórias.
8. O candidato pode voltar às perguntas anteriores antes da conclusão.
9. As respostas são salvas a cada avanço para permitir recuperação após queda de conexão.
10. Após confirmar a última resposta, a tentativa é finalizada definitivamente.
11. O candidato não pode refazer, reiniciar ou alterar respostas após a conclusão.
12. Requisições diretas ao backend também devem impedir uma segunda tentativa.
13. Após a conclusão, o botão **Fazer teste DISC** desaparece.
14. A flag da etapa muda para **Concluído — Perfil X**.
15. Um link **Conhecer meu perfil** abre o modal explicativo.
16. O RH visualiza pontuações e perfil, mas não pode modificar respostas.
17. Exceções para anulação de uma tentativa exigirão permissão administrativa específica, justificativa e auditoria; não fazem parte da primeira versão.

## 4. Experiência na linha do tempo

Na rota `/candidato/candidaturas/{application}`:

### Antes do início

- Flag: **Ação necessária**.
- Título: **Teste comportamental DISC**.
- Texto: “Responda às 20 situações de acordo com a forma como você normalmente age.”
- Botão: **Fazer teste DISC**.

### Em andamento

- Flag: **Em andamento**.
- Botão: **Continuar teste**.
- Indicador: `Pergunta respondida / 20`.

### Concluído

- Flag verde: **Concluído — Perfil D**, por exemplo.
- O botão de iniciar ou continuar desaparece.
- Link: **Conhecer meu perfil**.
- O link abre um modal com título, descrição, tendências e orientações.

## 5. Tela do teste

### Rota

`GET /candidato/candidaturas/{application}/disc`

### Layout

- Utilizar o mesmo tema e `PublicSiteShell` do portal do candidato.
- Não utilizar dashboard, sidebar ou cabeçalho administrativo.
- Card central com largura confortável para leitura.
- Barra de progresso no topo.
- Texto `Pergunta X de 20`.
- Uma pergunta visível por vez.
- Quatro alternativas em cards grandes, adequadas a celular.
- Botões **Anterior** e **Próxima**.
- Na pergunta 20, o botão muda para **Concluir teste**.
- Antes da conclusão, abrir uma confirmação informando que as respostas não poderão ser alteradas.

### Acessibilidade

- Alternativas devem ser `radio` com rótulos completos.
- Navegação possível por teclado.
- Foco deve ir para o título da próxima pergunta.
- Progresso deve usar `aria-valuenow`, `aria-valuemin` e `aria-valuemax`.
- Mensagens de erro devem usar `role="alert"`.
- Não depender exclusivamente de cores para comunicar estados.

## 6. Formato das perguntas

Cada pergunta apresenta uma situação e quatro respostas. Cada resposta corresponde internamente a uma dimensão, mas a letra D/I/S/C nunca aparece durante o questionário.

Para reduzir resposta condicionada:

- A ordem visual das alternativas deve variar entre perguntas.
- As quatro respostas devem ser igualmente positivas e plausíveis.
- Evitar termos como “certo”, “errado”, “melhor” ou “pior”.
- Solicitar a alternativa que mais representa o comportamento habitual.

## 7. Banco de dados

### `disc_questions`

| Campo | Tipo | Regra |
|---|---|---|
| `id` | bigint | Chave primária. |
| `code` | string unique | Ex.: `disc_q01`. |
| `position` | smallint | Ordem de 1 a 20. |
| `prompt` | text | Situação apresentada. |
| `active` | boolean | Permite desativar pergunta em versões futuras. |
| `version` | string | Ex.: `1.0`. |
| timestamps | timestamps | Controle da pergunta. |

### `disc_options`

| Campo | Tipo | Regra |
|---|---|---|
| `id` | bigint | Chave primária. |
| `disc_question_id` | foreign key | Pergunta. |
| `code` | string | Identificador interno. |
| `text` | text | Alternativa exibida. |
| `dimension` | char(1) | `D`, `I`, `S` ou `C`. Nunca enviar ao frontend. |
| `weight` | smallint | Inicialmente `1`. |
| `display_order` | smallint | Ordem da alternativa. |

### `disc_assessments`

| Campo | Tipo | Regra |
|---|---|---|
| `id` | bigint | Chave primária. |
| `application_id` | foreign key unique | Garante uma tentativa por candidatura. |
| `candidate_id` | foreign key | Dono do teste. |
| `status` | string | `not_started`, `in_progress`, `completed`. |
| `questionnaire_version` | string | Versão respondida. |
| `current_position` | smallint | Última pergunta acessada. |
| `started_at` | timestamp nullable | Início. |
| `completed_at` | timestamp nullable | Conclusão imutável. |
| `d_score` | smallint | Pontuação D. |
| `i_score` | smallint | Pontuação I. |
| `s_score` | smallint | Pontuação S. |
| `c_score` | smallint | Pontuação C. |
| `dominant_profile` | char(1) nullable | Maior pontuação. |
| `secondary_profile` | char(1) nullable | Segunda maior, para desempate descritivo. |
| `result_snapshot` | json nullable | Textos e percentuais usados no resultado. |
| `consent_at` | timestamp nullable | Aceite da finalidade. |
| `ip_address` | string nullable | Evidência de conclusão. |
| timestamps | timestamps | Controle da tentativa. |

### `disc_answers`

| Campo | Tipo | Regra |
|---|---|---|
| `id` | bigint | Chave primária. |
| `disc_assessment_id` | foreign key | Tentativa. |
| `disc_question_id` | foreign key | Pergunta. |
| `disc_option_id` | foreign key | Resposta escolhida. |
| `answered_at` | timestamp | Momento da resposta. |
| unique | assessment + question | Uma resposta por pergunta. |

## 8. Questionário autoral — versão 1.0

As marcações entre colchetes são internas e não devem ser enviadas ao navegador.

### Pergunta 1

Quando surge um desafio inesperado no trabalho, minha tendência inicial é:

- `[D]` Assumir a frente e buscar uma solução rápida.
- `[I]` Conversar com as pessoas e mobilizar o grupo.
- `[S]` Manter a calma e ajudar a equipe a se organizar.
- `[C]` Levantar os fatos antes de decidir o caminho.

### Pergunta 2

Ao receber uma tarefa nova, eu prefiro:

- `[C]` Entender critérios, detalhes e resultado esperado.
- `[D]` Ter autonomia para decidir como executá-la.
- `[S]` Saber como ela se integra à rotina da equipe.
- `[I]` Trocar ideias e explorar possibilidades com outras pessoas.

### Pergunta 3

Durante uma reunião, geralmente contribuo mais quando:

- `[I]` Posso apresentar ideias e estimular a participação.
- `[C]` Analiso as propostas e identifico inconsistências.
- `[D]` Ajudo o grupo a tomar uma decisão objetiva.
- `[S]` Escuto os envolvidos e procuro construir consenso.

### Pergunta 4

Quando o prazo está apertado, costumo:

- `[S]` Preservar a cooperação e manter um ritmo constante.
- `[D]` Priorizar o essencial e avançar com rapidez.
- `[C]` Organizar etapas para reduzir erros mesmo sob pressão.
- `[I]` Manter o entusiasmo e pedir apoio quando necessário.

### Pergunta 5

Em um projeto com pouca orientação, eu normalmente:

- `[D]` Defino uma direção e começo a agir.
- `[S]` Busco alinhar expectativas antes de avançar.
- `[I]` Procuro pessoas para pensar em alternativas.
- `[C]` Reúno informações e estabeleço um método.

### Pergunta 6

Quando preciso convencer alguém sobre uma proposta, eu:

- `[C]` Apresento evidências, riscos e critérios objetivos.
- `[I]` Uso entusiasmo e adapto a conversa à pessoa.
- `[S]` Demonstro como a proposta beneficia o grupo.
- `[D]` Destaco resultados e a necessidade de agir.

### Pergunta 7

Em mudanças de processo, o que mais me ajuda é:

- `[S]` Ter tempo para compreender e ajustar a rotina.
- `[I]` Participar das conversas e compartilhar expectativas.
- `[D]` Entender rapidamente o objetivo e partir para a execução.
- `[C]` Receber regras, impactos e procedimentos bem definidos.

### Pergunta 8

Quando identifico um erro importante, minha reação mais comum é:

- `[D]` Corrigir imediatamente e evitar impacto no resultado.
- `[C]` Investigar a causa e revisar o procedimento.
- `[I]` Conversar de forma aberta para encontrar uma saída.
- `[S]` Apoiar os envolvidos e corrigir sem gerar conflito.

### Pergunta 9

Em atividades de equipe, sinto-me mais confortável:

- `[I]` Criando conexões e mantendo todos engajados.
- `[S]` Oferecendo suporte e promovendo colaboração.
- `[C]` Cuidando da organização e da qualidade das entregas.
- `[D]` Direcionando prioridades e cobrando avanços.

### Pergunta 10

Ao tomar uma decisão relevante, eu valorizo mais:

- `[C]` Dados confiáveis e análise cuidadosa.
- `[S]` Estabilidade e impacto sobre as pessoas.
- `[D]` Velocidade e potencial de resultado.
- `[I]` Opiniões, possibilidades e aceitação do grupo.

### Pergunta 11

Quando recebo uma crítica, geralmente prefiro:

- `[S]` Uma conversa respeitosa e reservada.
- `[D]` Uma mensagem direta com o que precisa mudar.
- `[I]` Um diálogo aberto que também reconheça os acertos.
- `[C]` Exemplos específicos e critérios claros.

### Pergunta 12

Ao iniciar o dia de trabalho, minha prioridade costuma ser:

- `[D]` Atacar primeiro o objetivo de maior impacto.
- `[I]` Alinhar pessoas e assuntos que dependem de interação.
- `[C]` Organizar tarefas, prazos e padrões necessários.
- `[S]` Dar continuidade ao combinado e apoiar demandas do time.

### Pergunta 13

Em uma negociação, minha abordagem natural é:

- `[I]` Criar proximidade e manter a conversa dinâmica.
- `[D]` Defender o objetivo e buscar um acordo rápido.
- `[S]` Procurar uma solução equilibrada para todos.
- `[C]` Examinar condições e evitar compromissos imprecisos.

### Pergunta 14

Quando uma equipe está desmotivada, eu tendo a:

- `[C]` Identificar problemas concretos no processo.
- `[S]` Escutar as pessoas e oferecer apoio consistente.
- `[I]` Reanimar o grupo com energia e reconhecimento.
- `[D]` Reforçar metas e provocar uma reação prática.

### Pergunta 15

Se preciso aprender algo novo, prefiro:

- `[S]` Avançar gradualmente com orientação disponível.
- `[C]` Estudar materiais e compreender os fundamentos.
- `[D]` Experimentar na prática e ajustar rapidamente.
- `[I]` Aprender por conversas, exemplos e troca de experiências.

### Pergunta 16

Quando há opiniões muito diferentes, eu normalmente:

- `[D]` Enfrento a questão e conduzo para uma decisão.
- `[C]` Comparo argumentos e procuro coerência.
- `[S]` Reduzo tensões e busco pontos em comum.
- `[I]` Facilito a conversa e incentivo novas ideias.

### Pergunta 17

Meu ambiente de trabalho ideal oferece:

- `[I]` Contato com pessoas, variedade e espaço para expressão.
- `[C]` Clareza, qualidade e oportunidade de aprofundamento.
- `[D]` Autonomia, desafios e metas ambiciosas.
- `[S]` Cooperação, previsibilidade e relações de confiança.

### Pergunta 18

Quando delego uma atividade, costumo:

- `[C]` Explicar critérios e acompanhar a qualidade.
- `[D]` Definir o resultado e dar liberdade para executar.
- `[I]` Transmitir entusiasmo e manter comunicação frequente.
- `[S]` Garantir que a pessoa tenha apoio e segurança.

### Pergunta 19

Diante de uma oportunidade arriscada, eu:

- `[S]` Avalio como preservar estabilidade durante a mudança.
- `[I]` Exploro o potencial com outras pessoas.
- `[D]` Considero o ganho e aceito agir com incerteza.
- `[C]` Analiso cenários, dados e possíveis consequências.

### Pergunta 20

Ao concluir um projeto, o que mais me satisfaz é:

- `[D]` Ver que uma meta desafiadora foi alcançada.
- `[S]` Perceber que a equipe trabalhou de forma harmoniosa.
- `[C]` Entregar algo correto, consistente e bem executado.
- `[I]` Celebrar o resultado e o envolvimento das pessoas.

## 9. Algoritmo de pontuação

### Cálculo básico

Cada alternativa selecionada soma `1` à dimensão correspondente:

```text
D = quantidade de respostas associadas a D
I = quantidade de respostas associadas a I
S = quantidade de respostas associadas a S
C = quantidade de respostas associadas a C
TOTAL = D + I + S + C = 20
```

Percentual:

```text
percentual_da_dimensão = (pontuação_da_dimensão / 20) × 100
```

O perfil predominante é a dimensão com maior pontuação. A segunda maior é armazenada como perfil secundário.

### Empates

Em caso de empate na maior pontuação:

1. Não inventar uma pergunta de desempate após a conclusão.
2. Manter o resultado principal como combinação, por exemplo `DI`, `SC` ou `CS`.
3. Ordenar as letras pela pontuação; em empate absoluto, utilizar uma ordem determinística definida na versão (`D`, `I`, `S`, `C`) apenas para armazenamento.
4. O texto deve explicar que duas tendências apareceram com intensidade semelhante.

### Validações

- Exatamente 20 respostas antes da conclusão.
- Cada alternativa deve pertencer à sua pergunta.
- Uma resposta por pergunta.
- A dimensão e o peso são lidos exclusivamente no backend.
- Pontuação calculada no backend dentro de transação.
- Após `completed_at`, nenhuma alteração é permitida.

## 10. Textos dos resultados

### Perfil D — Dominância

**Flag:** `Perfil D — Dominância`

Você tende a agir com objetividade, iniciativa e foco em resultados. Situações desafiadoras, autonomia e metas claras podem estimular seu melhor desempenho. Em decisões, costuma valorizar velocidade e impacto. Como ponto de atenção, vale reservar espaço para ouvir perspectivas diferentes, comunicar o contexto das decisões e avaliar riscos antes de avançar.

### Perfil I — Influência

**Flag:** `Perfil I — Influência`

Você tende a demonstrar energia social, facilidade de comunicação e disposição para envolver outras pessoas. Ambientes colaborativos, variados e com espaço para troca podem favorecer seu desempenho. Sua capacidade de gerar entusiasmo é uma força. Como ponto de atenção, pode ser útil registrar combinados, acompanhar detalhes e equilibrar otimismo com análise prática.

### Perfil S — Estabilidade

**Flag:** `Perfil S — Estabilidade`

Você tende a valorizar cooperação, confiança, constância e relações respeitosas. Pode contribuir para um ambiente equilibrado, oferecendo apoio e mantendo compromissos. Mudanças graduais e expectativas claras costumam favorecer seu desempenho. Como ponto de atenção, procure expressar discordâncias, sinalizar limites e agir mesmo quando todas as condições ainda não parecem ideais.

### Perfil C — Conformidade/Conscienciosidade

**Flag:** `Perfil C — Conformidade`

Você tende a valorizar precisão, qualidade, lógica e critérios bem definidos. Pode se destacar na análise de informações, identificação de riscos e construção de entregas consistentes. Ambientes que reconhecem conhecimento e organização podem favorecer seu desempenho. Como ponto de atenção, procure equilibrar aprofundamento com prazos e compartilhar conclusões de forma acessível.

### Perfis combinados

Para combinações, o modal concatena uma introdução específica e os textos resumidos das duas dimensões:

> Suas respostas indicam duas tendências com intensidade semelhante: **D e I**. Isso significa que seu comportamento pode combinar características dos dois estilos conforme o contexto.

Não usar combinações para afirmar personalidade fixa ou prever sucesso em uma vaga.

## 11. Controllers, services e policies

### Backend

- `Candidate/DiscAssessmentController@show`: abre ou recupera tentativa.
- `Candidate/DiscAssessmentController@answer`: salva uma resposta e posição atual.
- `Candidate/DiscAssessmentController@complete`: valida, calcula e conclui.
- `Hr/DiscAssessmentController@show`: consulta resultado no painel.
- `DiscScoringService`: calcula pontuações, percentuais e perfil.
- `DiscAssessmentPolicy`: valida candidato dono da candidatura e acesso do RH.

### Regras da policy

- `start`: papel `candidato`, permissão `applications.view-own`, candidatura própria, fase atual com ação `disc`, ausência de conclusão.
- `answer`: tentativa do candidato autenticado e status `in_progress`.
- `complete`: mesmas regras de `answer`, com 20 respostas válidas.
- `viewResult`: candidato dono da tentativa ou RH com `applications.evaluate`.

## 12. Rotas propostas

```text
GET  /candidato/candidaturas/{application}/disc
POST /candidato/candidaturas/{application}/disc/iniciar
PUT  /candidato/candidaturas/{application}/disc/respostas/{question}
POST /candidato/candidaturas/{application}/disc/concluir

GET  /hr/applications/{application}/disc
```

Todas as rotas devem usar `auth`, Spatie e policy de propriedade.

## 13. Integração com a linha do tempo

O backend da página de acompanhamento deverá retornar para a fase DISC:

```json
{
  "name": "Teste comportamental DISC",
  "status": "action_required",
  "action": {
    "label": "Fazer teste DISC",
    "url": "/candidato/candidaturas/1/disc"
  },
  "result": null
}
```

Após conclusão:

```json
{
  "name": "Teste comportamental DISC",
  "status": "completed",
  "action": null,
  "result": {
    "profile": "S",
    "label": "Perfil S — Estabilidade",
    "description": "Texto armazenado no snapshot"
  }
}
```

O resultado vem do `result_snapshot`; não deve ser recalculado ao abrir a página, preservando a versão respondida.

## 14. LGPD, ética e uso no processo seletivo

- Informar finalidade antes do início.
- Registrar consentimento específico.
- Coletar apenas respostas necessárias.
- Restringir resultado ao candidato e profissionais autorizados.
- Definir prazo de retenção junto à candidatura.
- Permitir atendimento de solicitações do titular.
- Não utilizar o resultado como diagnóstico psicológico.
- Não classificar perfil como bom ou ruim.
- Não reprovar automaticamente com base exclusiva no perfil.
- Não usar o perfil como substituto da avaliação de competências.
- Manter revisão humana e registrar decisões do RH separadamente.

## 15. Auditoria

Registrar eventos:

- `disc.assessment.started`
- `disc.answer.saved`
- `disc.assessment.completed`
- `disc.result.viewed-by-hr`

Para evitar excesso, `disc.answer.saved` pode ser armazenado em log técnico próprio, mantendo na auditoria principal apenas início e conclusão.

## 16. Testes obrigatórios

### Feature

- Candidato acessa o teste somente da própria candidatura.
- Teste não abre antes da fase DISC.
- Teste não abre em candidatura de outro candidato.
- Iniciar cria apenas uma tentativa.
- Recarregar recupera posição e respostas salvas.
- Alternativa de outra pergunta é rejeitada.
- Não é possível pular pergunta na conclusão.
- Vinte respostas produzem pontuação total igual a 20.
- Perfil dominante é calculado corretamente.
- Empate gera perfil combinado.
- Conclusão grava snapshot e `completed_at`.
- Respostas não podem ser alteradas após conclusão.
- Segunda tentativa é bloqueada no backend.
- Linha do tempo remove o botão depois da conclusão.
- Resultado não expõe mapeamento interno das alternativas.

### Frontend

- Apenas uma pergunta aparece por vez.
- Progresso vai de 1/20 a 20/20.
- Botão próxima exige uma resposta.
- Botão anterior preserva respostas.
- Atualizar a página recupera o progresso.
- Confirmação aparece antes da conclusão.
- Após concluir, botão desaparece e flag acende.
- Modal do perfil abre e fecha por teclado.
- Experiência funciona em celular e desktop.

## 17. Critérios de aceite

- O botão aparece somente na fase DISC e para o candidato correto.
- São exibidas exatamente 20 perguntas autorais, uma por tela.
- O progresso é salvo durante a execução.
- O cálculo ocorre exclusivamente no backend.
- Após concluir, o candidato não consegue refazer nem editar.
- A linha do tempo mostra **Concluído — Perfil X**.
- O modal explica o perfil alcançado sem linguagem diagnóstica.
- O RH consegue consultar o resultado com permissão apropriada.
- Nenhum resultado provoca reprovação automática.
