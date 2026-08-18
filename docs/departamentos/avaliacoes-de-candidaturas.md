# Processo de avaliações de candidaturas

Especificação complementar para agenda, Google Meet, e-mail e WhatsApp: [Agendamento das entrevistas — fases 4, 5 e 6](agendamento-de-entrevistas.md).

## 1. Objetivo

O módulo de avaliações permitirá que profissionais de RH e gestores registrem pareceres internos sobre candidatos durante o processo seletivo. A avaliação será sempre vinculada a uma candidatura existente e não será exibida ao candidato.

O objetivo da primeira versão é responder com clareza:

- quem avaliou;
- em qual etapa a avaliação ocorreu;
- qual nota geral foi atribuída;
- qual foi a recomendação;
- quais observações justificam o parecer;
- se o parecer ainda é um rascunho ou já foi finalizado.

## 2. Princípios do processo

- A avaliação pertence à candidatura, não diretamente ao usuário candidato.
- Cada avaliador registra o próprio parecer; um usuário não altera o parecer de outro.
- RH pode consultar todas as avaliações dentro do seu escopo autorizado.
- O gestor requisitante consulta e avalia somente candidaturas das vagas sob sua responsabilidade.
- Pareceres são informações internas e sensíveis.
- A avaliação não movimenta a candidatura automaticamente.
- Avançar, reprovar ou contratar continua sendo uma decisão explícita no CRUD de candidaturas.
- Toda criação, alteração, finalização e exclusão gera auditoria.
- Papéis e permissões serão implementados exclusivamente com `spatie/laravel-permission`.

## 3. Papéis e permissões

### Analista de RH

Permissões utilizadas:

- `applications.view` para consultar a candidatura;
- `applications.evaluate` para criar e editar o próprio parecer;
- `applications.update-status` para movimentar a candidatura, em uma ação separada.

Pode:

- visualizar avaliações da candidatura;
- criar uma avaliação própria;
- salvar como rascunho;
- finalizar o próprio parecer;
- editar ou excluir o próprio rascunho.

### Gestor requisitante

Permissão utilizada: `applications.evaluate`.

Pode avaliar somente quando estiver vinculado à vaga como `hiring_manager_id` ou quando uma regra explícita de equipe conceder acesso.

### Gestor de RH

Herda as permissões do Analista de RH e poderá excluir avaliações finalizadas em situações excepcionais, sempre com justificativa e auditoria.

### Super-admin

Mantém acesso irrestrito pelo `Gate::before`, inclusive durante suporte. Quando estiver personificando outro usuário, a auditoria registrará também o `impersonator_id`.

### Candidato

Não recebe avaliações, notas, recomendações ou observações nas props do Inertia, APIs, notificações ou área autenticada.

## 4. Fluxo recomendado

1. **Triagem curricular assistida por IA:** currículo estruturado, comparado com a vaga e pontuado.
2. **Teste DISC:** questionário comportamental preenchido pelo candidato e disponibilizado ao RH como apoio à entrevista.
3. **Entrevista técnica com IA:** três perguntas técnicas relacionadas à vaga, respostas avaliadas e nota calculada.
4. **Entrevista com RH:** avaliação humana de trajetória, comunicação, disponibilidade e requisitos profissionais.
5. **Entrevista com cliente/setor:** gestor requisitante avalia o candidato no contexto técnico e operacional da equipe.
6. **Avaliação final e liberação:** RH consolida todas as avaliações, registra considerações e libera aprovação, banco de talentos ou reprovação.

Em todas as fases, a candidatura poderá ser reprovada por usuário autorizado, com justificativa obrigatória. Notas produzidas por IA gerarão recomendação e alerta, mas não executarão reprovação automática sem confirmação humana.

### Por que a avaliação não movimenta automaticamente

A recomendação de um avaliador é um insumo, não a decisão final. Uma candidatura pode ter pareceres divergentes, avaliações pendentes ou necessidade de validação do Gestor de RH. Separar as duas ações evita reprovações acidentais e preserva responsabilidades.

## 4.1. Jornada avaliativa recomendada

O processo não terá uma única avaliação genérica. Cada candidatura poderá passar por avaliações diferentes conforme a vaga. O RH configurará quais etapas são obrigatórias, opcionais ou não aplicáveis.

Fluxo oficial:

```text
Candidatura
    ↓
Triagem curricular
    ↓
Teste DISC
    ↓
Entrevista técnica com IA — 3 perguntas
    ↓
Entrevista com RH
    ↓
Entrevista com cliente/setor — gestor
    ↓
Avaliação final e liberação
    ↓
Reprovação, banco de talentos ou admissão
```

As seis fases serão o fluxo padrão. O Gestor de RH poderá marcar uma fase como não aplicável em vagas específicas, mediante justificativa registrada. A ordem não será alterada durante um processo já iniciado sem auditoria.

## 4.2. Avaliação de triagem curricular

### Responsável

Analista ou Gestor de RH.

### Quando acontece

Logo após o recebimento da candidatura e antes do primeiro contato com o candidato.

### Como acontece

A triagem será dividida em processamento automático e revisão humana:

1. O candidato envia o currículo em PDF, DOC ou DOCX.
2. O arquivo original permanece no armazenamento privado.
3. Um job assíncrono extrai o texto do arquivo.
4. A IA transforma o texto em dados estruturados.
5. O sistema normaliza competências, experiências, formação e certificações.
6. Um algoritmo compara esses dados com critérios previamente cadastrados na vaga.
7. O sistema calcula uma nota interna de aderência entre 0 e 100.
8. O RH revisa o currículo, os dados extraídos e a explicação da nota.
9. Somente o RH confirma se o candidato avança, fica em análise ou é reprovado.

O processamento automático nunca reprovará nem avançará uma candidatura sem ação humana.

### Critérios recomendados

- formação obrigatória, quando realmente necessária;
- experiência mínima relacionada à função;
- conhecimentos técnicos declarados;
- certificações obrigatórias;
- localização ou disponibilidade para o modelo de trabalho;
- aderência geral aos requisitos essenciais.

### Resultado

- nota automática de aderência entre 0 e 100;
- nível de confiança da extração;
- critérios atendidos, parcialmente atendidos e não identificados;
- alertas de dados que a IA não conseguiu interpretar;
- parecer humano de 1 a 5 após a revisão;
- recomendação para avançar, manter em análise ou reprovar;
- observação justificando principalmente requisitos não atendidos.

Esta etapa não deve utilizar idade, fotografia, gênero, estado civil, religião, deficiência não relacionada à atividade ou outras características protegidas como critério de decisão.

### O candidato verá a nota?

Recomendação inicial: não. A nota automática e o parecer são instrumentos internos de triagem. O candidato poderá receber apenas a situação geral da candidatura quando o produto implementar essa comunicação. A decisão deve ser explicável internamente e revisável pelo RH.

## 4.2.1. Dados extraídos do currículo

A IA deverá produzir uma resposta estruturada, validada pelo backend, contendo somente informações profissionais relevantes:

- nome profissional informado no documento;
- resumo profissional;
- cargos anteriores;
- empresas anteriores;
- datas de início e término;
- duração calculada das experiências;
- atividades e responsabilidades;
- competências técnicas;
- ferramentas e tecnologias;
- formação acadêmica;
- cursos e certificações;
- idiomas e níveis declarados;
- links profissionais, como LinkedIn, GitHub ou portfólio;
- localização profissional, quando informada;
- texto que não pôde ser classificado.

O sistema não deverá inferir ou classificar:

- raça ou etnia;
- religião;
- orientação sexual;
- opinião política;
- estado civil;
- condição de saúde;
- deficiência, salvo informação voluntária necessária para acessibilidade;
- idade aproximada por datas de formação;
- fotografia, aparência ou origem social.

## 4.2.2. Tabela `curricula`

O arquivo original continuará referenciado na candidatura, mas os dados extraídos serão armazenados em uma nova tabela `curricula`.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | chave primária |
| `application_id` | foreignId unique | candidatura de origem |
| `candidate_id` | foreignId | candidato proprietário |
| `file_hash` | string | identifica alteração e evita reprocessamento |
| `extraction_status` | string | `pending`, `processing`, `completed`, `failed` ou `reviewed` |
| `professional_summary` | text nullable | resumo extraído |
| `skills` | json nullable | competências normalizadas |
| `experiences` | json nullable | experiências e períodos |
| `education` | json nullable | formação acadêmica |
| `certifications` | json nullable | cursos e certificações |
| `languages` | json nullable | idiomas declarados |
| `professional_links` | json nullable | links profissionais |
| `total_experience_months` | unsignedInteger nullable | experiência total calculada |
| `raw_text` | longText nullable | texto extraído, protegido e com retenção controlada |
| `extraction_confidence` | decimal nullable | confiança geral entre 0 e 1 |
| `provider` | string nullable | provedor utilizado |
| `model` | string nullable | modelo utilizado |
| `prompt_version` | string nullable | versão das instruções de extração |
| `processed_at` | timestamp nullable | conclusão do processamento |
| `reviewed_at` | timestamp nullable | revisão humana |
| `reviewed_by` | foreignId nullable | profissional que confirmou os dados |
| `processing_error` | text nullable | erro técnico sem credenciais |
| `timestamps` | timestamps | criação e atualização |

Os campos JSON serão utilizados na primeira versão para acelerar a implantação. Caso relatórios complexos se tornem necessários, experiências, formações e competências poderão ser normalizadas em tabelas filhas.

## 4.2.3. Critérios estruturados da vaga

Para o algoritmo comparar dados de forma confiável, requisitos escritos apenas em texto livre não são suficientes. O formulário da vaga ganhará uma seção **Critérios de triagem**.

Nova tabela recomendada: `job_screening_criteria`.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | chave primária |
| `job_id` | foreignId | vaga relacionada |
| `type` | string | experiência, competência, formação, certificação, idioma ou localização |
| `name` | string | critério esperado |
| `description` | text nullable | contexto para comparação |
| `required` | boolean | eliminatório ou classificatório |
| `weight` | unsignedTinyInteger | peso relativo na pontuação |
| `minimum_months` | unsignedInteger nullable | experiência mínima aplicável |
| `active` | boolean | permite retirar o critério sem apagar histórico |
| `timestamps` | timestamps | criação e atualização |

A soma dos pesos ativos deverá resultar em 100 ou será normalizada proporcionalmente pelo sistema.

## 4.2.4. Algoritmo de alocação e pontuação

O modelo recomendado é híbrido e auditável:

### Etapa A — extração por IA

A IA recebe o texto do currículo e devolve JSON em um schema fechado. Ela identifica que expressões como “Laravel”, “framework Laravel” e “desenvolvimento em Laravel” representam a mesma competência, sem alterar o documento original.

### Etapa B — normalização

O backend:

- padroniza maiúsculas, acentos e sinônimos;
- calcula duração das experiências pelas datas;
- remove duplicidades;
- valida tipos e limites do JSON;
- marca campos incertos para revisão.

### Etapa C — alocação nos critérios

Cada dado extraído é relacionado a um critério da vaga. O registro da comparação deverá indicar:

- critério avaliado;
- evidência encontrada no currículo;
- nível de correspondência entre 0 e 1;
- peso do critério;
- justificativa curta;
- origem da evidência no documento;
- necessidade de revisão humana.

Nova tabela recomendada: `curriculum_screening_matches`.

O resultado geral será armazenado em `curriculum_screenings`, contendo candidatura, currículo, vaga, nota, confiança, situação do processamento, versão dos critérios, provedor, modelo, prompt e datas. Assim, um reprocessamento cria uma nova versão sem apagar a análise anterior.

### Etapa D — cálculo da nota

Para cada critério:

```text
pontos do critério = peso × correspondência
```

A nota final será a soma dos pontos, normalizada entre 0 e 100.

Exemplo:

| Critério | Peso | Correspondência | Pontos |
| --- | ---: | ---: | ---: |
| PHP | 25 | 1,00 | 25,00 |
| Laravel | 25 | 0,90 | 22,50 |
| SQL | 15 | 0,80 | 12,00 |
| Testes automatizados | 15 | 0,60 | 9,00 |
| Experiência de 4 anos | 20 | 0,75 | 15,00 |
| **Total** | **100** |  | **83,50** |

O resultado será exibido como **83,5 de 100**, acompanhado das evidências. A nota sem explicação não será aceita pelo sistema.

### Critérios obrigatórios

A ausência de um critério obrigatório gera um alerta visível, mas não reprova automaticamente. O RH confirma se a evidência realmente está ausente ou apenas não foi identificada pela extração.

### Uso da IA na nota

A IA poderá sugerir o nível de correspondência semântica, mas o cálculo matemático será feito pelo backend. Dessa forma, pesos, fórmula e resultado permanecem reproduzíveis e auditáveis.

## 4.2.5. Processamento assíncrono

A leitura do currículo não deve atrasar a resposta do formulário público.

Fluxo técnico:

```text
Candidatura gravada
    ↓
Job ExtractCurriculumData enviado à fila
    ↓
Extração local do texto
    ↓
Envio do texto ao provedor de IA
    ↓
Validação e armazenamento do JSON
    ↓
Job ScoreCurriculumForJob
    ↓
Nota disponível para revisão do RH
```

Se a IA estiver desativada ou indisponível, a candidatura continuará válida e ficará com o status **Triagem automática pendente**. O RH poderá analisar manualmente ou solicitar reprocessamento.

## 4.2.6. Configurações da IA

Será criada uma aba **Inteligência Artificial** em:

```text
/settings/system
```

As configurações serão armazenadas no banco, seguindo o padrão adotado para SMTP e Turnstile. Segredos serão criptografados com o cast `encrypted` do Laravel e nunca retornarão ao navegador.

Tabela recomendada: `ai_settings`.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | registro único de configuração |
| `enabled` | boolean | ativa o processamento por IA |
| `provider` | string | provedor selecionado |
| `base_url` | string nullable | endpoint para API compatível ou privada |
| `model` | string | modelo usado na extração |
| `api_key` | text nullable | credencial criptografada |
| `organization` | string nullable | organização/projeto, quando aplicável |
| `timeout` | unsignedInteger | tempo máximo da requisição |
| `max_output_tokens` | unsignedInteger | limite da resposta estruturada |
| `temperature` | decimal | recomendado `0` para consistência |
| `send_curriculum_data` | boolean | autorização operacional explícita |
| `prompt_version` | string | versão ativa do prompt/schema |
| `last_tested_at` | timestamp nullable | último teste bem-sucedido |
| `timestamps` | timestamps | criação e atualização |

A aba terá:

- ativar/desativar IA;
- provedor;
- URL base;
- modelo;
- chave secreta;
- organização ou projeto;
- timeout;
- limite de tokens;
- confirmação de envio de dados de currículo;
- botão **Testar conexão**;
- indicação de chave já configurada;
- estado do último teste.

Permissões Spatie previstas:

- `system.settings.ai.update`;
- `system.settings.ai.test`.

Somente usuários autorizados poderão visualizar a aba ou alterar a configuração. A chave será mascarada e campo vazio manterá o valor atual.

## 4.2.7. Segurança, LGPD e fornecedores

Antes de ativar um provedor externo, será necessário definir:

- finalidade específica do tratamento;
- base legal e transparência no aviso de privacidade;
- quais dados serão enviados;
- região de processamento;
- prazo de retenção pelo fornecedor;
- uso ou não dos dados para treinamento pelo fornecedor;
- contrato ou termos aplicáveis ao tratamento de dados;
- processo para exclusão e atendimento ao titular;
- registro da versão do modelo e do prompt usados na decisão assistida.

O envio preferencial será do texto profissional necessário à extração. Dados desnecessários deverão ser removidos antes da chamada. Arquivo original, chave da API e credenciais nunca serão incluídos em logs.

## 4.2.8. Revisão humana da triagem

Na tela da candidatura, o RH verá lado a lado:

- currículo original;
- dados estruturados;
- critérios da vaga;
- evidências associadas;
- nota calculada;
- confiança da extração;
- alertas e falhas.

O RH poderá:

- confirmar os dados extraídos;
- corrigir uma competência, período ou formação;
- desvincular uma evidência incorreta;
- executar novamente a pontuação;
- registrar parecer humano;
- avançar, manter ou reprovar em ação separada.

Toda correção manual preservará os dados anteriores na auditoria.

## 4.3. Fase 2 — Teste DISC

### Objetivo

Identificar tendências comportamentais declaradas pelo candidato para apoiar a preparação das entrevistas humanas. O DISC não mede conhecimento técnico, inteligência, saúde mental, honestidade ou capacidade profissional de forma isolada.

### Responsável

O candidato responde ao questionário na área autenticada. O RH libera, acompanha e revisa o resultado.

### Quando acontece

Depois que o RH aprovar a triagem curricular e antes da entrevista técnica com IA.

### Como acontece

1. O RH libera o teste para a candidatura.
2. O candidato recebe instruções sobre finalidade, tratamento dos dados e tempo estimado.
3. O sistema apresenta uma afirmação ou conjunto de alternativas por vez.
4. Cada resposta é salva durante o preenchimento.
5. Ao concluir, o algoritmo calcula os quatro fatores DISC.
6. O sistema gera um perfil descritivo, sem classificar o candidato como aprovado ou inadequado.
7. O RH utiliza o resultado para formular perguntas de aprofundamento na entrevista.

### Dimensões

| Fator | Significado operacional |
| --- | --- |
| `D` — Dominância | forma de lidar com desafios, decisões e resultados |
| `I` — Influência | interação, comunicação e persuasão |
| `S` — Estabilidade | ritmo, cooperação e consistência |
| `C` — Conformidade | atenção a normas, detalhes e qualidade |

Os fatores serão apresentados como intensidades relativas, não como diagnóstico ou rótulo definitivo de personalidade.

### Resultado

- percentuais ou índices de D, I, S e C;
- fatores predominantes;
- descrição padronizada das tendências;
- pontos para aprofundamento na entrevista com RH;
- data, versão do questionário e método de cálculo;
- situação `pending`, `in_progress`, `completed` ou `reviewed`.

### Regra de decisão

O DISC não terá nota de aprovação e não poderá reprovar automaticamente. Caso o RH decida reprovar o candidato nessa fase, deverá registrar evidências profissionais adicionais e uma justificativa relacionada à vaga. A justificativa “perfil DISC incompatível” não será aceita isoladamente.

### Consentimento e acessibilidade

- o candidato deverá ser informado de que se trata de avaliação comportamental não diagnóstica;
- o resultado será interno e não ficará disponível para cliente externo sem autorização e finalidade definidas;
- o candidato poderá solicitar esclarecimento sobre o tratamento dos dados;
- o teste deverá permitir adaptações de acessibilidade;
- eventual dificuldade de uso da interface não poderá ser interpretada como característica comportamental.

## 4.3.1. Estrutura de dados do DISC

Tabela `disc_assessments`:

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | chave primária |
| `application_id` | foreignId unique | candidatura avaliada |
| `status` | string | estado do preenchimento |
| `questionnaire_version` | string | versão imutável do instrumento |
| `calculation_version` | string | versão do algoritmo |
| `dominance_score` | decimal nullable | fator D |
| `influence_score` | decimal nullable | fator I |
| `steadiness_score` | decimal nullable | fator S |
| `conscientiousness_score` | decimal nullable | fator C |
| `primary_factor` | string nullable | fator predominante |
| `secondary_factor` | string nullable | segundo fator |
| `summary` | text nullable | descrição gerada por template aprovado |
| `released_by` | foreignId nullable | usuário que liberou |
| `released_at` | timestamp nullable | data da liberação |
| `started_at` | timestamp nullable | início do preenchimento |
| `completed_at` | timestamp nullable | conclusão |
| `reviewed_by` | foreignId nullable | revisão do RH |
| `reviewed_at` | timestamp nullable | data da revisão |
| `timestamps` | timestamps | criação e atualização |

Tabelas complementares:

- `disc_questions`: enunciado, grupo, versão, ordem e status;
- `disc_question_options`: alternativas e contribuição de pontuação;
- `disc_answers`: avaliação, pergunta, alternativa escolhida e data da resposta.

As respostas brutas serão privadas. O algoritmo de cálculo será versionado para permitir reprodução e auditoria do resultado.

## 4.4. Fase 3 — Entrevista técnica com IA

### Responsável

O candidato responde de forma autenticada. A IA formula e pontua as perguntas, enquanto o RH acompanha o resultado e confirma a decisão.

### Quando acontece

Depois que o RH aprovar a triagem curricular da Fase 1.

### Como acontece

1. O sistema seleciona os critérios técnicos mais relevantes da vaga.
2. A IA gera exatamente três perguntas técnicas compatíveis com cargo e senioridade.
3. O RH pode revisar as perguntas antes de liberar a entrevista ou utilizar liberação automática previamente configurada.
4. O candidato acessa uma pergunta por vez na área autenticada.
5. Cada resposta é salva antes da próxima pergunta.
6. Depois da terceira resposta, a IA avalia cada resposta segundo critérios previamente definidos.
7. O backend calcula a nota da fase entre 0 e 100 a partir das três notas.
8. O RH consulta perguntas, respostas, evidências, nota e justificativas.

As três perguntas devem avaliar dimensões diferentes. Para Desenvolvedor PHP Sênior, por exemplo:

- fundamento técnico de PHP/Laravel;
- solução de um problema real ou análise de código;
- arquitetura, testes, segurança ou tomada de decisão.

### Pontuação

Cada pergunta recebe nota de 0 a 100 e peso configurável. Na configuração padrão, as três terão o mesmo peso. A nota da fase será a média ponderada.

A IA deverá devolver para cada resposta:

- nota;
- critérios atendidos;
- critérios não demonstrados;
- evidências encontradas na resposta;
- justificativa objetiva;
- confiança da análise;
- sinalização para revisão humana.

### Regras

- perguntas e respostas ficam privadas;
- não será permitido regenerar uma pergunta depois de o candidato visualizá-la;
- falha da IA não reprova o candidato e permite retomada;
- acessibilidade ou tempo adicional não reduz a nota;
- a IA não deve avaliar estilo de escrita quando isso não for requisito da vaga;
- nota abaixo do corte gera recomendação de reprovação, não reprovação automática;
- RH pode aprovar, manter ou reprovar, sempre registrando consideração.

### Resultado

- três perguntas e respostas imutáveis após conclusão;
- nota técnica automática de 0 a 100;
- explicação por pergunta;
- recomendação da IA;
- decisão humana de avançar ou reprovar.

## 4.4.1. Dados da entrevista com IA

Tabela recomendada: `ai_candidate_interviews`.

| Campo | Tipo | Regra |
| --- | --- | --- |
| `id` | bigint | chave primária |
| `application_id` | foreignId unique | candidatura entrevistada |
| `status` | string | `pending`, `released`, `in_progress`, `completed`, `failed` ou `reviewed` |
| `score` | decimal nullable | nota final de 0 a 100 |
| `recommendation` | string nullable | avançar, manter ou recomendar reprovação |
| `model` | string nullable | modelo utilizado |
| `prompt_version` | string nullable | versão das instruções |
| `released_by` | foreignId nullable | usuário que liberou a entrevista |
| `released_at` | timestamp nullable | liberação para o candidato |
| `started_at` | timestamp nullable | início pelo candidato |
| `completed_at` | timestamp nullable | terceira resposta concluída |
| `reviewed_by` | foreignId nullable | responsável pela revisão humana |
| `reviewed_at` | timestamp nullable | conclusão da revisão |
| `processing_error` | text nullable | erro técnico sanitizado |
| `timestamps` | timestamps | criação e atualização |

Tabela `ai_interview_questions`:

- vínculo com a entrevista;
- sequência de 1 a 3;
- pergunta imutável;
- competência avaliada;
- critérios esperados em JSON;
- peso;
- modelo e versão do prompt;
- data de geração.

Tabela `ai_interview_answers`:

- vínculo único com a pergunta;
- resposta do candidato;
- data da resposta;
- nota de 0 a 100;
- evidências e justificativa;
- critérios atendidos e ausentes em JSON;
- confiança da IA;
- data do processamento.

Perguntas e respostas não serão armazenadas em logs técnicos. O conteúdo seguirá a retenção da candidatura.

## 4.5. Fase 4 — Entrevista com Recursos Humanos

### Responsável

Analista ou Gestor de RH.

### Quando acontece

Depois da aprovação na entrevista técnica com IA.

### Como acontece

A entrevista pode ser presencial ou por videoconferência. O avaliador utiliza roteiro padronizado e registra somente evidências profissionais relevantes.

### Critérios recomendados

- comunicação e clareza;
- trajetória e experiências relevantes;
- interesse pela oportunidade;
- disponibilidade para início;
- expectativa salarial, quando aplicável;
- entendimento do modelo de trabalho;
- comportamentos profissionais observáveis;
- compatibilidade entre objetivos profissionais e atividades da vaga.

### Resultado

- nota humana de 1 a 5;
- recomendação para avançar, manter ou reprovar;
- pontos fortes;
- riscos ou pontos a validar;
- considerações obrigatórias quando houver reprovação.

O termo “aderência cultural” não será aceito como justificativa vaga. O parecer deve citar comportamentos ou requisitos profissionais objetivos.

## 4.6. Fase 5 — Entrevista com cliente ou setor

### Responsável

Gestor requisitante vinculado à vaga em `hiring_manager_id`. Quando houver cliente externo, o gestor interno continuará responsável por registrar a avaliação no sistema.

### Quando acontece

Depois da aprovação na entrevista com RH.

### Como acontece

O gestor entrevista o candidato considerando atividades reais, contexto da equipe, desafios do cargo e senioridade esperada. O cliente externo não receberá acesso direto ao sistema na primeira versão.

### Critérios recomendados

- domínio aplicável aos desafios da função;
- resolução de problemas;
- autonomia para a senioridade;
- priorização e tomada de decisão;
- colaboração no contexto da equipe;
- liderança, quando aplicável;
- alinhamento com responsabilidades e modelo de trabalho.

### Resultado

- nota humana de 1 a 5;
- recomendação para aprovar, manter ou reprovar;
- principais evidências;
- riscos de contratação;
- necessidades de desenvolvimento ou integração;
- considerações obrigatórias quando houver reprovação.

## 4.7. Fase 6 — Avaliação final e liberação de aprovação

### Responsável

Analista de RH prepara a consolidação. Gestor de RH realiza a liberação final quando a política da empresa exigir dupla validação.

### Quando acontece

Depois que as cinco fases anteriores estiverem concluídas e aprovadas.

### Como acontece

O sistema apresenta:

- nota da triagem curricular;
- resultado comportamental DISC;
- nota e respostas da entrevista com IA;
- parecer da entrevista com RH;
- parecer do gestor/cliente;
- alertas, divergências e critérios obrigatórios;
- histórico completo de decisões.

Não será utilizada apenas uma média cega. O RH revisará as evidências e registrará as considerações finais.

### Resultado

- **liberar aprovação e iniciar admissão**;
- **manter em análise**;
- **direcionar ao banco de talentos**, com consentimento válido;
- **reprovar**.

A liberação para admissão será uma ação explícita, confirmada e auditada. A decisão final não altera nem apaga avaliações anteriores.

## 4.8. Reprovação em qualquer fase

Todas as fases terão a ação **Reprovar candidato**.

Regras:

- exige permissão `applications.update-status`;
- exige motivo padronizado e consideração textual;
- registra fase, responsável, data, IP e eventual personificação;
- altera a candidatura para `rejected` e preenche `rejected_at`;
- impede acesso às fases seguintes;
- preserva avaliações e evidências anteriores;
- permite correção administrativa apenas pelo Gestor de RH, com nova auditoria;
- notas automáticas nunca executam essa ação sozinhas.

## 4.9. Avaliações opcionais

Conforme a vaga, o processo poderá incluir:

- avaliação de idioma;
- avaliação de liderança;
- apresentação de portfólio;
- dinâmica de grupo;
- estudo de caso comercial;
- avaliação de atendimento ao cliente;
- validação de certificação obrigatória.

Avaliações psicológicas ou testes psicológicos somente poderão ser aplicados por profissional legalmente habilitado, usando instrumentos e procedimentos adequados. Essa modalidade não fará parte da primeira versão do sistema.

## 4.10. Critérios eliminatórios e classificatórios

Cada vaga poderá definir dois tipos de critério:

- **eliminatório:** requisito indispensável, cuja ausência pode encerrar o processo;
- **classificatório:** característica que melhora a avaliação, mas não elimina automaticamente.

Exemplos:

| Critério | Tipo sugerido |
| --- | --- |
| Registro profissional exigido por lei | Eliminatório |
| Disponibilidade para trabalho presencial obrigatório | Eliminatório |
| Conhecimento em tecnologia complementar | Classificatório |
| Certificação desejável | Classificatório |

O sistema não reprovará automaticamente com base em uma nota isolada. O usuário autorizado confirmará a decisão e registrará a justificativa.

## 4.11. Padronização e comparação justa

Para uma mesma vaga:

- os critérios devem ser definidos antes do início das entrevistas;
- candidatos devem ser avaliados pela mesma escala;
- perguntas principais e testes devem ser equivalentes;
- notas precisam estar acompanhadas de evidências;
- critérios protegidos ou não relacionados ao trabalho não podem influenciar a decisão;
- adaptações de acessibilidade não podem reduzir a nota do candidato;
- divergências relevantes entre avaliadores devem ser revisadas pelo RH.

## 4.12. Como o sistema representará os tipos de avaliação

Além dos campos gerais, a implementação deverá acrescentar `evaluation_type` à avaliação, com os valores iniciais:

| Valor | Exibição |
| --- | --- |
| `resume_screening` | Triagem curricular |
| `disc_assessment` | Teste DISC |
| `ai_technical_interview` | Entrevista técnica com IA |
| `hr_interview` | Entrevista com RH |
| `manager_interview` | Entrevista com gestor |
| `final_review` | Parecer consolidado |
| `other` | Outra avaliação |

Na primeira versão, cada avaliador poderá registrar uma avaliação de cada tipo por candidatura. Portanto, a unicidade recomendada passa a ser:

```text
application_id + evaluator_id + evaluation_type
```

Isso permite que o mesmo profissional realize, por exemplo, a triagem curricular e posteriormente o parecer consolidado sem sobrescrever o primeiro registro.

## 5. Campos da avaliação

### Campos obrigatórios na primeira versão

| Campo | Tipo | Regra |
| --- | --- | --- |
| `application_id` | foreignId | candidatura avaliada |
| `evaluator_id` | foreignId | preenchido pelo usuário autenticado |
| `stage_id` | foreignId nullable | etapa existente no momento da avaliação |
| `evaluation_type` | string | tipo de avaliação realizada |
| `rating` | unsignedTinyInteger | nota geral de 1 a 5 |
| `recommendation` | string | `advance`, `hold` ou `reject` |
| `notes` | text | justificativa interna |
| `status` | string | `draft` ou `submitted` |
| `submitted_at` | timestamp nullable | momento da finalização |
| `timestamps` | timestamps | criação e última alteração |

### Escala de nota recomendada

| Nota | Significado |
| --- | --- |
| 1 | Não atende aos requisitos essenciais |
| 2 | Atende parcialmente, com lacunas relevantes |
| 3 | Atende ao esperado para a vaga |
| 4 | Supera parte relevante do esperado |
| 5 | Excepcional para o contexto da vaga |

### Recomendações

| Valor | Exibição | Consequência automática |
| --- | --- | --- |
| `advance` | Recomenda avançar | nenhuma |
| `hold` | Manter em análise | nenhuma |
| `reject` | Recomenda reprovar | nenhuma |

As observações serão obrigatórias para avaliações finalizadas. Para rascunhos, poderão ser incompletas.

## 6. Modelo de dados

A tabela `application_evaluations` já existe com:

- `application_id`;
- `evaluator_id`;
- `rating`;
- `recommendation`;
- `notes`;
- timestamps;
- unicidade em `application_id + evaluator_id`.

Será criada uma migration incremental, sem modificar a migration histórica, para acrescentar:

- `stage_id`, nullable, com `nullOnDelete`;
- `evaluation_type`, identificando a avaliação realizada;
- `status`, padrão `draft`;
- `submitted_at`, nullable.

A restrição única atual em `application_id + evaluator_id` deverá ser substituída por `application_id + evaluator_id + evaluation_type`.

Na primeira versão, cada avaliador terá uma avaliação de cada tipo por candidatura. Ele poderá atualizar seu rascunho e finalizá-lo. Uma versão futura poderá acrescentar rodadas caso seja necessário repetir o mesmo tipo de avaliação em etapas diferentes.

## 7. Estados e transições

```text
Novo parecer → Rascunho → Finalizado
                    ↓
                 Excluído
```

Regras:

- somente o autor edita o rascunho;
- finalizar exige nota, recomendação e observações;
- parecer finalizado é somente leitura para o autor;
- exclusão será lógica se a tabela receber `softDeletes`; caso a primeira versão mantenha exclusão física, o conteúdo anterior deverá permanecer na auditoria;
- reabertura de parecer finalizado não fará parte da primeira versão;
- Gestor de RH poderá excluir um parecer finalizado apenas com justificativa.

## 8. Telas e experiência de uso

### Botão do dashboard

O botão **Avaliações** em `/hr` será ativado e direcionará para:

```text
/hr/evaluations
```

### Lista de avaliações

A página apresentará:

- candidato;
- vaga;
- etapa avaliada;
- avaliador;
- nota de 1 a 5;
- recomendação;
- situação do parecer;
- data de finalização;
- ações permitidas.

Filtros:

- busca por candidato, vaga ou avaliador;
- recomendação;
- status do parecer;
- paginação no backend com 15 registros.

### Avaliação dentro da candidatura

O detalhe da candidatura exibirá uma seção **Avaliações internas** com:

- média simples das notas finalizadas;
- quantidade de recomendações para avançar, aguardar e reprovar;
- lista de pareceres finalizados;
- botão **Nova avaliação** ou **Continuar rascunho**.

### Formulário

Campos:

- nota geral por seleção de 1 a 5;
- recomendação;
- observações;
- botões **Salvar rascunho** e **Finalizar avaliação**.

Antes da finalização, o sistema solicitará confirmação, informando que o parecer se tornará somente leitura.

## 9. Controllers, Requests, Policy e rotas

Arquivos previstos:

- `ApplicationEvaluationController.php` para listar, criar, atualizar e excluir;
- `ApplicationEvaluationRequest.php` para autorização e validação contextual;
- `ApplicationEvaluationPolicy.php` para verificar autor, papel, vaga e escopo;
- `ApplicationEvaluation.php` com relações e casts;
- migration incremental para os novos campos;
- `resources/js/pages/hr/evaluations/index.tsx`;
- componente compartilhado de formulário de avaliação.

Para a triagem curricular assistida:

- `Curriculum.php`;
- `CurriculumScreening.php`;
- `CurriculumScreeningMatch.php`;
- `JobScreeningCriterion.php`;
- `AiSetting.php`;
- `ExtractCurriculumData.php`;
- `ScoreCurriculumForJob.php`;
- `AiSettingsController.php`;
- `AiSettingRequest.php`;
- serviço de integração com o provedor de IA;
- serviço determinístico de cálculo da nota;
- aba **Inteligência Artificial** nas configurações do sistema.

Para a entrevista técnica com IA:

- `AiCandidateInterview.php`;
- `AiInterviewQuestion.php`;
- `AiInterviewAnswer.php`;
- `GenerateTechnicalInterviewQuestions.php`;
- `EvaluateTechnicalInterviewAnswer.php`;
- `CompleteTechnicalInterview.php`;
- tela do candidato para responder uma pergunta por vez;
- painel de revisão do RH;
- bloqueio transacional após a terceira resposta.

Para o teste DISC:

- `DiscAssessment.php`;
- `DiscQuestion.php`;
- `DiscQuestionOption.php`;
- `DiscAnswer.php`;
- `ReleaseDiscAssessment.php`;
- `CalculateDiscProfile.php`;
- tela autenticada do candidato;
- painel de revisão do RH;
- questionário e fórmula versionados.

Rotas previstas:

```text
GET    /hr/evaluations
POST   /hr/applications/{application}/evaluations
PUT    /hr/evaluations/{evaluation}
DELETE /hr/evaluations/{evaluation}
```

Não haverá rota pública de avaliações.

## 10. Regras de validação

- a candidatura deve existir e estar no escopo do avaliador;
- o candidato nunca pode ser o próprio avaliador;
- `rating` deve estar entre 1 e 5 ao finalizar;
- `recommendation` deve ser `advance`, `hold` ou `reject`;
- `notes` deve ter no máximo 10.000 caracteres;
- `notes` é obrigatória ao finalizar;
- não pode haver mais de uma avaliação do mesmo tipo por avaliador e candidatura na primeira versão;
- a etapa armazenada deve pertencer à mesma empresa da vaga;
- avaliações finalizadas não podem ser editadas pela operação comum;
- uma candidatura excluída não aceita novas avaliações.

## 11. Auditoria e LGPD

Eventos previstos:

- `application_evaluation.created`;
- `application_evaluation.updated`;
- `application_evaluation.submitted`;
- `application_evaluation.deleted`.

Cada evento armazenará ator, eventual super-admin personificador, IP e valores anteriores/novos apropriados.

As observações podem conter dados pessoais e não devem ser incluídas integralmente em logs técnicos. O acesso será restrito aos perfis autorizados. A retenção acompanhará a candidatura; ao anonimizar o candidato, os textos livres deverão ser excluídos ou anonimizados conforme a política de retenção.

## 12. Notificações

Não serão enviadas notificações na primeira versão. Em uma evolução futura:

- RH poderá ser avisado quando o gestor finalizar um parecer;
- o gestor poderá ser avisado quando receber uma solicitação de avaliação;
- o candidato continuará sem receber o conteúdo interno do parecer.

## 13. Testes previstos

### Backend

- Analista de RH cria e edita o próprio rascunho;
- Gestor requisitante avalia somente vaga sob sua responsabilidade;
- usuário sem `applications.evaluate` recebe `403`;
- avaliador não altera parecer de outro usuário;
- finalização exige nota, recomendação e observações;
- parecer finalizado não aceita edição comum;
- avaliação não altera etapa ou status automaticamente;
- entrevista com IA gera exatamente três perguntas;
- DISC é liberado somente após aprovação da triagem;
- questionário DISC permite retomada sem perder respostas;
- resultado DISC é reproduzível pela versão do questionário e do cálculo;
- DISC não produz nota eliminatória nem reprovação automática;
- justificativa baseada somente em “perfil DISC incompatível” é rejeitada;
- candidato não visualiza perguntas futuras antes de responder a atual;
- pergunta visualizada não pode ser regenerada;
- cada resposta é persistida antes de liberar a próxima;
- conclusão calcula a média ponderada das três respostas;
- falha do provedor permite retomada sem perder respostas;
- nota da IA não reprova automaticamente;
- reprovação em qualquer fase exige justificativa e auditoria;
- candidatura reprovada não acessa fases posteriores;
- criação, finalização e exclusão geram auditoria;
- listagem aplica busca, filtro e paginação no backend;
- candidato nunca recebe avaliações internas.
- envio da candidatura funciona mesmo com a IA indisponível;
- currículo é processado pela fila sem bloquear o formulário público;
- resposta da IA fora do schema é rejeitada e registrada como falha;
- chave da IA permanece criptografada e não retorna ao frontend;
- setor de RH sem permissão não altera configurações da IA;
- algoritmo reproduz a mesma nota com os mesmos dados, pesos e evidências;
- critério obrigatório ausente gera alerta, sem reprovação automática;
- correção humana recalcula a nota e gera auditoria;
- reprocessamento preserva a versão anterior da triagem.

### Frontend

- botão do dashboard abre o CRUD;
- formulário permite salvar rascunho;
- finalização exige confirmação;
- abilities ocultam ações não autorizadas;
- mensagens de validação aparecem em português;
- lista apresenta estado vazio, filtros e paginação;
- nota e recomendação utilizam rótulos em português.

## 14. Critérios de aceite

- CRUD acessível pelo botão **Avaliações** do dashboard do RH;
- permissões verificadas no backend com Spatie e Policy;
- somente o autor altera o próprio parecer;
- parecer finalizado é imutável na operação comum;
- avaliação não movimenta candidatura automaticamente;
- candidato não recebe conteúdo interno;
- listagem paginada e filtrada no backend;
- ações sensíveis auditadas;
- testes cobrem autorização, transições e isolamento dos dados.

## 15. Decisões recomendadas para validação

1. Usar nota geral de 1 a 5.
2. Usar recomendações **avançar**, **manter em análise** e **reprovar**.
3. Permitir uma avaliação de cada tipo por avaliador em cada candidatura na primeira versão.
4. Permitir rascunho antes da finalização.
5. Tornar a avaliação finalizada somente leitura.
6. Não movimentar candidatura automaticamente.
7. Exigir observação ao finalizar.
8. Não notificar usuários na primeira versão.
9. Manter avaliações totalmente invisíveis ao candidato.
10. Usar IA para extração e correspondência semântica, com cálculo final determinístico no backend.
11. Utilizar nota automática de 0 a 100 para triagem curricular e nota humana de 1 a 5 para pareceres.
12. Nunca avançar ou reprovar automaticamente com base apenas na IA.
13. Armazenar configurações da IA no banco, com chave criptografada.
14. Processar currículos de forma assíncrona pela fila.
15. Preservar versões anteriores quando o currículo for reprocessado.
16. Não mostrar ao candidato a nota interna de triagem na primeira versão.
17. Adotar seis fases oficiais: triagem curricular, DISC, entrevista com IA, entrevista com RH, entrevista com gestor/cliente e avaliação final.
18. Usar o DISC como apoio comportamental, sem nota eliminatória ou reprovação automática.
19. Versionar questionário e algoritmo DISC para reproduzir resultados.
20. Gerar exatamente três perguntas na entrevista técnica com IA.
21. Mostrar uma pergunta por vez e tornar pergunta e resposta imutáveis após o envio.
22. Usar nota de 0 a 100 nas fases automatizadas e de 1 a 5 nas entrevistas humanas.
23. Permitir reprovação em qualquer fase, sempre confirmada por usuário autorizado e acompanhada de considerações profissionais objetivas.
