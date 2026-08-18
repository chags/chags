# Portal do candidato — área institucional

## 1. Objetivo

Criar uma área autenticada para o candidato acompanhar suas candidaturas sem acessar o dashboard administrativo. O portal utilizará o mesmo cabeçalho, rodapé, identidade visual, responsividade e alternância de tema do site institucional.

O candidato verá cards das vagas em que se inscreveu e poderá abrir cada candidatura para consultar as fases do processo, a fase atual e as ações disponíveis.

## 2. Princípios definidos

- O portal não reutilizará o layout, menu lateral ou navegação do dashboard.
- Todas as páginas utilizarão `PublicSiteShell` ou um `CandidateSiteShell` derivado dele.
- Autorização será feita com `spatie/laravel-permission`, usando o papel `candidato` no guard `web`.
- O candidato poderá acessar somente candidaturas ligadas ao próprio usuário.
- Notas internas, pareceres confidenciais, prompts, erros técnicos e auditoria não serão expostos.
- As fases terão nomes e textos públicos próprios, evitando mostrar termos internos do RH.
- A decisão final continuará sendo humana; o portal apenas informa o andamento publicado pelo RH.

## 3. Jornada do candidato

### 3.1. Inscrição

1. O visitante escolhe uma vaga em `/trabalhe-conosco`.
2. Preenche o formulário, envia o currículo e cria sua senha.
3. A candidatura e a conta com papel `candidato` são criadas.
4. O currículo é armazenado e a extração local dos dados é iniciada.
5. Após o envio, o candidato recebe confirmação e um link para entrar no portal.

### 3.2. Acesso

- Rota sugerida: `/candidato/entrar`.
- Após autenticação, usuários com papel `candidato` são direcionados para `/candidato`.
- Usuários administrativos continuam seguindo para o dashboard correspondente.
- Deve existir recuperação de senha e encerramento de sessão no tema institucional.

### 3.3. Acompanhamento

Na página `/candidato`, o usuário verá todas as suas candidaturas em cards. Ao selecionar **Acompanhar processo**, abrirá a página `/candidato/candidaturas/{application}`.

## 4. Páginas

### 4.1. Entrada do candidato

**Rota:** `GET /candidato/entrar`

Conteúdo:

- Logomarca e identidade institucional.
- E-mail e senha.
- Lembrar acesso.
- Recuperar senha.
- Botão **Entrar**.
- Link **Ver vagas abertas**.

### 4.2. Minhas candidaturas

**Rota:** `GET /candidato`

Cabeçalho:

- Saudação com o primeiro nome.
- Texto explicando o acompanhamento.
- Botão **Ver novas vagas**.
- Menu da conta e sair.

Cada card deverá mostrar:

- Imagem quadrada da vaga.
- Título da vaga.
- Empresa, unidade e setor.
- Modalidade e localidade.
- Data da inscrição.
- Situação pública da candidatura.
- Fase atual.
- Indicador resumido de progresso.
- Botão **Acompanhar processo**.

Estados vazios:

- Nenhuma candidatura: mostrar convite e botão para consultar vagas.
- Vaga encerrada: manter o histórico da candidatura acessível.
- Candidatura retirada: mostrar a data da desistência.

### 4.3. Detalhes da candidatura

**Rota:** `GET /candidato/candidaturas/{application}`

Conteúdo:

- Resumo da vaga e data da inscrição.
- Situação atual em destaque.
- Linha do tempo das fases públicas.
- Fase atual destacada.
- Fases concluídas com data.
- Próxima fase, quando puder ser divulgada.
- Mensagem pública definida pelo RH.
- Ações pendentes do candidato.
- Link para consultar o aviso de privacidade.

## 5. Linha do tempo recomendada

1. **Candidatura recebida** — currículo armazenado com sucesso.
2. **Análise do perfil** — extração e avaliação do currículo.
3. **Teste comportamental DISC** — aguardando, em andamento ou concluído.
4. **Entrevista com RH**.
5. **Entrevista técnica** — quando aplicável à vaga.
6. **Entrevista com gestor da área**.
7. **Avaliação final**.
8. **Resultado do processo**.

Nem toda vaga precisa utilizar todas as fases. A linha do tempo será montada com as fases configuradas para a vaga.

### Estados visuais

- `completed`: concluída, ícone de confirmação e cor verde.
- `current`: fase atual, destaque com cor primária.
- `pending`: futura, aparência neutra.
- `action_required`: exige ação do candidato, cor de atenção e botão.
- `cancelled`: fase cancelada ou não aplicável.

## 6. Visibilidade das fases

Recomenda-se acrescentar campos em `recruitment_stages`:

| Campo | Tipo | Finalidade |
|---|---|---|
| `candidate_visible` | boolean | Define se a fase aparece no portal. |
| `public_name` | string nullable | Nome amigável mostrado ao candidato. |
| `public_description` | text nullable | Explicação pública da fase. |
| `candidate_action` | string nullable | Identifica uma ação, como responder ao DISC. |

O nome interno pode continuar sendo “Triagem automatizada”, enquanto o candidato vê “Análise do perfil”.

## 7. Dados reaproveitados

### Tabelas existentes

- `users`: autenticação e dados do candidato.
- `candidate_profiles`: informações complementares.
- `applications`: candidatura, situação e fase atual.
- `recruitment_jobs`: informações da vaga.
- `recruitment_stages`: catálogo das fases.
- `application_stage_histories`: histórico das mudanças.
- `curricula`: extração e avaliação do currículo.

### Dados que não devem ser enviados ao frontend

- Chaves e configurações dos provedores de IA.
- Texto integral extraído do currículo, salvo quando necessário apenas para processamento.
- Notas internas do RH.
- Pareceres destinados exclusivamente aos avaliadores.
- Erros técnicos, prompts e respostas brutas da IA.
- Eventos de auditoria e dados de outros candidatos.

## 8. Controllers e componentes

### Backend

- `CandidatePortalController@index`: lista candidaturas do usuário autenticado.
- `CandidateApplicationController@show`: apresenta uma candidatura pertencente ao usuário.
- `CandidateAuthController`: entrada e saída pelo fluxo institucional, caso o login atual não seja reutilizado.
- `CandidateApplicationPolicy`: valida propriedade da candidatura e permissões Spatie.

### Frontend

- `resources/js/pages/candidate/index.tsx`
- `resources/js/pages/candidate/applications/show.tsx`
- `resources/js/pages/candidate/auth/login.tsx`
- `resources/js/components/candidate-application-card.tsx`
- `resources/js/components/recruitment-timeline.tsx`
- `resources/js/layouts/candidate-site-layout.tsx`

O layout poderá compor o `PublicSiteShell`, acrescentando apenas navegação para **Minhas candidaturas**, **Meus dados** e **Sair**.

## 9. Rotas propostas

```text
GET  /candidato/entrar
POST /candidato/entrar
POST /candidato/sair
GET  /candidato
GET  /candidato/candidaturas/{application}
GET  /candidato/perfil
PUT  /candidato/perfil
```

Rotas de candidatura deverão usar `auth`, a política de propriedade e a permissão adequada, como `applications.view-own`.

## 10. Regras de segurança e LGPD

- Utilizar o guard `web` e papéis/permissões do Spatie.
- Consultas sempre filtradas por `candidate_id = auth()->id()`.
- Não confiar apenas no ID informado na URL.
- Não expor currículo por URL pública.
- Registrar acessos e alterações relevantes.
- Manter consentimento, versão do aviso e data já gravados na candidatura.
- Permitir solicitação de correção ou exclusão conforme política de retenção.
- Não mostrar avaliação comportamental como diagnóstico psicológico.
- Não permitir reprovação automática exclusivamente por IA ou DISC.

## 11. Testes necessários

### Feature

- Candidato autenticado acessa a própria lista.
- Candidato não acessa candidatura de outra pessoa.
- Usuário sem papel ou permissão recebe `403`.
- Visitante é direcionado para o login do candidato.
- Cards mostram apenas candidaturas próprias.
- Linha do tempo mostra somente fases públicas.
- Fases internas e notas confidenciais não aparecem na resposta Inertia.
- Candidatura encerrada continua visível no histórico.

### Frontend

- Cards responsivos em celular e desktop.
- Estado vazio e carregamento.
- Cores e textos de cada estado da fase.
- Botões de ação aparecem somente quando aplicáveis.
- Navegação mantém o tema institucional.

## 12. Ordem de implementação

1. Adicionar campos públicos às fases e atualizar o CRUD do RH.
2. Criar policy de acesso próprio e rotas autenticadas do candidato.
3. Criar o layout institucional autenticado.
4. Implementar a página de cards das candidaturas.
5. Implementar detalhes e linha do tempo.
6. Integrar futuras ações do DISC e entrevistas.
7. Criar testes de autorização, privacidade e interface.

## 13. Critérios de aceite

- O candidato não visualiza o dashboard administrativo.
- A aparência é a mesma do site institucional.
- Cada candidatura aparece em um card separado.
- O botão **Acompanhar processo** abre as fases daquela candidatura.
- A fase atual e as concluídas ficam visualmente claras.
- Nenhum dado interno de RH ou de outro candidato é exposto.
- O fluxo funciona em celular e desktop.
