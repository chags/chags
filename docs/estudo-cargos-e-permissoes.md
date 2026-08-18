# Estudo de cargos, perfis e permissões

## 1. Objetivo

Este documento propõe a estrutura inicial de perfis de acesso para os módulos de recrutamento, RH, Departamento Pessoal e intranet da Chags Technology.

A implementação deve utilizar exclusivamente o pacote `spatie/laravel-permission`, no guard `web`. Os perfis abaixo representam papéis de acesso ao sistema; cargo, função, departamento, gestor e vínculo empregatício devem ser dados próprios do colaborador e não novos papéis de autorização.

## 2. Situação atual

O sistema possui dois papéis:

- `super-admin`: acesso irrestrito por meio do `Gate::before`; é o papel canônico de administração total.
- `administrador`: recebe atualmente permissões de configurações do sistema e gerenciamento de usuários.

Permissões existentes:

- `system.settings.view`
- `system.settings.company.update`
- `system.settings.mail.update`
- `system.settings.mail.test`
- `system.settings.appearance.update`
- `users.view`
- `users.create`
- `users.update`
- `users.delete`

Hoje o cadastro e a interface de usuários aceitam apenas `administrador` e `super-admin`. A ampliação exigirá atualizar o seeder, a validação, a tela de usuários, os testes e os filtros de consulta.

## 3. Princípios de modelagem

### Papel de acesso não é cargo

Exemplos de cargos profissionais são Desenvolvedor, Técnico de Suporte, Analista de RH e Assistente de Departamento Pessoal. Esses valores devem ficar em entidades como `positions`, `departments` e `employee_profiles`.

Os papéis do Spatie indicam o que a pessoa pode fazer no sistema. Um Analista de RH e um Coordenador de RH podem compartilhar o papel `rh-analista`, com permissões adicionais atribuídas a um papel de gestão quando necessário.

Da mesma forma, `colaborador` é um papel básico de acesso, não o nome do cargo. Um colaborador pode ocupar o cargo `Analista de Sistemas`, com senioridade `senior`, e ser exibido como **Analista de Sistemas Sênior**. Cargo e senioridade pertencem à estrutura profissional; não devem gerar papéis como `colaborador-analista-sistemas-sr`.

### Menor privilégio

Cada perfil deve receber somente as permissões necessárias. Dados como CPF, endereço, remuneração, documentos, dados bancários, benefícios, afastamentos e informações de saúde exigem controle mais restrito e registro de auditoria.

### Escopo dos registros

A permissão informa a ação; uma Policy deve limitar em quais registros ela pode ocorrer. Exemplos:

- candidato acessa somente a própria candidatura;
- colaborador acessa somente o próprio perfil e documentos liberados;
- gestor acessa apenas colaboradores de sua equipe;
- profissionais de RH e DP acessam somente candidatos e colaboradores das unidades explicitamente autorizadas;
- gestor participa do recrutamento e aprova férias e ponto somente para sua própria equipe.

## 4. Papéis recomendados

| Papel técnico   | Nome na interface  | Finalidade                                                                      |
| --------------- | ------------------ | ------------------------------------------------------------------------------- |
| `candidato`     | Candidato          | Participar de processos seletivos e manter seus próprios dados.                 |
| `colaborador`   | Colaborador        | Usar a intranet e consultar ou solicitar alterações nos próprios dados.         |
| `gestor`        | Gestor             | Acompanhar sua equipe e participar das etapas autorizadas de recrutamento e RH. |
| `rh-analista`   | Analista de RH     | Operar vagas, candidaturas, admissões e cadastros de pessoas.                   |
| `rh-gestor`     | Gestor de RH       | Administrar processos de RH, relatórios e ações sensíveis do setor.             |
| `dp-analista`   | Analista de DP     | Operar documentos trabalhistas, ponto, férias, benefícios e folha.              |
| `dp-gestor`     | Gestor de DP       | Aprovar e administrar rotinas sensíveis de Departamento Pessoal.                |
| `administrador` | Administrador      | Administrar a plataforma, sem acesso automático a dados sigilosos de RH/DP.     |
| `super-admin`   | Superadministrador | Acesso irrestrito e uso excepcional.                                            |

Não é necessário criar todos os papéis no primeiro ciclo. A implantação mínima pode começar com `candidato`, `colaborador`, `rh-analista` e `dp-analista`, mantendo os dois papéis atuais.

## 5. Matriz funcional proposta

Legenda: **P** = próprios dados; **E** = equipe autorizada; **T** = todos os registros dentro do escopo organizacional; **A** = aprovação; **—** = sem acesso.

| Área                    |      Candidato |            Colaborador |     Gestor |       RH analista |         RH gestor |          DP analista |            DP gestor |
| ----------------------- | -------------: | ---------------------: | ---------: | ----------------: | ----------------: | -------------------: | -------------------: |
| Perfil pessoal          |              P |                      P |        P/E |                 T |                 T |                    T |                    T |
| Vagas publicadas        |              P |                      P |          P |                 T |                 T |             Consulta |             Consulta |
| Candidaturas            |              P | Próprias, se aplicável |          E |                 T |               T/A | Consulta na admissão | Consulta na admissão |
| Admissão                | Dados próprios |                      P |   Consulta |                 T |               T/A |                    T |                  T/A |
| Documentos pessoais     |              P |                      P | E limitada |        T limitada |                 T |                    T |                  T/A |
| Férias e afastamentos   |              — |                      P |        E/A |                 T |               T/A |                    T |                  T/A |
| Ponto                   |              — |                      P |        E/A |          Consulta |          Consulta |                    T |                  T/A |
| Benefícios              |              — |                      P | E limitada |                 T |               T/A |                    T |                  T/A |
| Folha e remuneração     |              — |                      P |          — | Consulta restrita | Consulta restrita |                    T |                  T/A |
| Relatórios do setor     |              — |                      — |          E |                 T |                 T |                    T |                    T |
| Configuração do sistema |              — |                      — |          — |                 — |                 — |                    — |                    — |

O papel `administrador` não deve receber automaticamente acesso à folha, remuneração ou documentos pessoais. Essas capacidades devem depender de permissões explícitas de RH/DP. O `super-admin` continua sendo a exceção técnica irrestrita.

## 6. Catálogo inicial de permissões

O padrão recomendado é `recurso.ação`, mantendo compatibilidade com o padrão atual.

### Recrutamento

- `jobs.view`
- `jobs.create`
- `jobs.update`
- `jobs.publish`
- `jobs.close`
- `applications.view-own`
- `applications.create`
- `applications.update-own`
- `applications.view`
- `applications.update-status`
- `applications.evaluate`
- `applications.delete`

### Colaboradores e estrutura organizacional

- `employees.view-own`
- `employees.update-own`
- `employees.view-team`
- `employees.view`
- `employees.create`
- `employees.update`
- `employees.deactivate`
- `departments.view`
- `departments.manage`
- `positions.view`
- `positions.manage`

### Documentos e admissão

- `admissions.view-own`
- `admissions.submit-own`
- `admissions.view`
- `admissions.update`
- `admissions.approve`
- `employee-documents.view-own`
- `employee-documents.upload-own`
- `employee-documents.view`
- `employee-documents.manage`

### Departamento Pessoal

- `time-records.view-own`
- `time-records.view-team`
- `time-records.view`
- `time-records.manage`
- `time-records.approve`
- `vacations.view-own`
- `vacations.request`
- `vacations.view-team`
- `vacations.manage`
- `vacations.approve`
- `benefits.view-own`
- `benefits.view`
- `benefits.manage`
- `payroll.view-own`
- `payroll.view`
- `payroll.manage`
- `payroll.approve`

### Intranet

- `intranet.access`
- `announcements.view`
- `announcements.manage`
- `policies.view`
- `policies.manage`

## 7. Distribuição inicial de permissões

### `candidato`

- visualizar vagas publicadas;
- criar e acompanhar a própria candidatura;
- editar os próprios dados enquanto o processo permitir;
- enviar documentos solicitados na etapa de admissão.

### `colaborador`

- acessar a intranet;
- visualizar e solicitar atualização dos próprios dados;
- visualizar próprios documentos, ponto, férias, benefícios e demonstrativos liberados;
- abrir solicitações pessoais.

### `gestor`

- todas as capacidades de colaborador;
- consultar equipe vinculada;
- participar de avaliações de candidatos quando convidado;
- aprovar solicitações da equipe conforme o fluxo definido;
- não visualizar automaticamente remuneração, documentos pessoais ou dados bancários.

### `rh-analista`

- administrar vagas e processos seletivos;
- consultar e movimentar candidaturas;
- conduzir admissão e manter dados cadastrais;
- operar férias, benefícios e documentos quando fizer parte da atribuição do RH;
- sem aprovação final de rotinas definidas como sensíveis.

### `rh-gestor`

- todas as capacidades de `rh-analista`;
- aprovar admissões e rotinas de RH;
- acessar relatórios consolidados;
- gerenciar configurações funcionais do módulo de RH, sem administrar configurações técnicas da plataforma.

### `dp-analista`

- operar admissão documental, ponto, férias, benefícios e folha;
- consultar dados trabalhistas necessários;
- gerar documentos e relatórios operacionais;
- sem aprovação final de fechamento quando houver segregação de funções.

### `dp-gestor`

- todas as capacidades de `dp-analista`;
- aprovar férias, ajustes de ponto e fechamento de folha;
- acessar relatórios consolidados e configurações funcionais do DP.

## 8. Estrutura de dados sugerida

Manter autenticação e autorização em `users`, `roles`, `permissions`, `model_has_roles` e tabelas relacionadas do Spatie. Não adicionar colunas booleanas como `is_candidate`, `is_employee` ou `is_hr` em `users`.

Entidades de domínio recomendadas para fases futuras:

- `departments`: setores da organização;
- `positions`: cargos profissionais, com título e senioridade independentes do papel de acesso;
- `employee_profiles`: matrícula, admissão, vínculo, cargo, setor, gestor e situação;
- `candidate_profiles`: informações específicas do candidato;
- `recruitment_jobs`: vagas, sem conflitar com a tabela `jobs` usada pelas filas do Laravel;
- `applications`: candidaturas e estágio do processo;
- `employee_documents`: documentos com classificação e regras de acesso;
- `organizational_scopes`: empresas e unidades às quais cada profissional de RH/DP tem acesso.

Um usuário pode mudar de candidato para colaborador sem perder histórico. Ao concluir a admissão, o sistema deve retirar `candidato` e atribuir `colaborador`, ou manter ambos apenas quando houver uma necessidade funcional expressa. A troca deve ocorrer em transação e gerar auditoria.

## 9. Segurança, privacidade e auditoria

- Aplicar Policies além de middleware de permissão para limitar registros por titular, equipe e unidade.
- Registrar quem visualizou, exportou ou alterou dados sensíveis.
- Exigir motivo para alterações manuais críticas e manter histórico antes/depois.
- Restringir exportações e downloads por permissões próprias.
- Evitar dados pessoais em logs, notificações e URLs.
- Manter dados de candidatos por 12 meses após o encerramento do processo; depois disso, excluir ou anonimizar, salvo consentimento expresso para permanência adicional no banco de talentos.
- Revisar periodicamente usuários de `rh-gestor`, `dp-gestor`, `administrador` e `super-admin`.
- Aplicar dupla aprovação obrigatória ao fechamento da folha, desligamentos, alterações salariais e ajustes retroativos de ponto.

### Suporte por impersonação

O administrador técnico não recebe acesso implícito aos dados de RH ou DP. Somente o `super-admin` pode iniciar uma sessão de suporte como outro usuário para reproduzir problemas com as mesmas permissões e experiência da conta atendida.

- a ação deve estar disponível apenas na gestão de usuários e nunca para administradores comuns;
- a interface deve indicar permanentemente quando a impersonação estiver ativa;
- deve existir uma ação visível para retornar à conta original do `super-admin`;
- início e encerramento devem registrar superadministrador, usuário atendido, data, hora e endereço IP;
- não deve ser possível iniciar impersonações encadeadas;
- ações executadas durante a sessão devem continuar identificáveis em auditoria;
- a funcionalidade é de suporte e não substitui a concessão formal de papéis de RH/DP.

## 10. Plano de implantação

### Fase 1 — Fundação de acesso

1. Criar um seeder idempotente de papéis e permissões.
2. Criar os papéis mínimos: `candidato`, `colaborador`, `rh-analista` e `dp-analista`.
3. Preservar `super-admin` no guard `web` e o comportamento irrestrito atual.
4. Substituir a lista fixa de papéis da validação e da interface por uma fonte autorizada no backend.
5. Impedir que usuários comuns atribuam papéis privilegiados.
6. Criar testes de permissão positiva e negativa para cada perfil.
7. Preparar o escopo organizacional para múltiplas empresas e unidades.

### Fase 2 — Recrutamento

1. Criar perfis de candidato, vagas e candidaturas.
2. Implementar acesso somente aos próprios registros para o candidato.
3. Implementar operação de vagas e candidaturas para RH.
4. Auditar mudanças de etapa e avaliações.
5. Permitir participação do gestor somente nas vagas e candidaturas vinculadas à sua equipe.
6. Automatizar a exclusão ou anonimização após 12 meses, respeitando eventual consentimento para banco de talentos.

### Fase 3 — Colaboradores e RH

1. Criar setores, cargos profissionais e perfis de colaborador.
2. Implementar a conversão auditada de candidato em colaborador.
3. Implementar autosserviço e escopo de equipe.
4. Adicionar `gestor` e `rh-gestor` quando os fluxos de aprovação existirem.
5. Manter papéis, permissões e responsabilidades de RH separados dos de Departamento Pessoal.
6. Limitar consultas e operações às unidades autorizadas e, no caso de gestores, à própria equipe.

### Fase 4 — Departamento Pessoal

1. Implantar documentos, férias, ponto, benefícios e folha em módulos separados.
2. Adicionar permissões específicas por operação e por dado sensível.
3. Adicionar `dp-gestor`, aprovações e segregação de funções.
4. Implementar auditoria, exportações controladas e política de retenção.
5. Exigir dupla aprovação para fechamento da folha, desligamentos, alterações salariais e ajustes retroativos de ponto.
6. Integrar ou importar a folha de um fornecedor externo; não processar a folha integralmente nesta fase.

## 11. Critérios de aceite da primeira implantação

- Todos os papéis e permissões são criados pelo Spatie no guard `web`.
- Nenhuma coluna booleana de papel é adicionada à tabela `users`.
- Cargo profissional e senioridade não são armazenados como papéis Spatie.
- O `super-admin` mantém acesso irrestrito.
- Cada novo perfil possui testes que confirmam acessos permitidos e negados.
- Candidato e colaborador não conseguem consultar dados de terceiros.
- Administrador técnico não recebe acesso implícito a dados sigilosos de RH/DP.
- A atribuição de papéis privilegiados é restrita e auditável.
- As consultas de RH/DP respeitam unidade, equipe ou outro escopo organizacional definido.

## 12. Decisões aprovadas

1. **Múltiplas unidades:** a estrutura atenderá várias empresas ou unidades. As equipes de RH e DP acessarão apenas as unidades para as quais estiverem autorizadas.
2. **Separação entre RH e DP:** Recursos Humanos e Departamento Pessoal funcionarão como áreas distintas, com papéis e permissões independentes.
3. **Participação dos gestores:** gestores poderão participar dos processos seletivos e aprovar férias e ajustes de ponto somente para colaboradores da própria equipe.
4. **Administrador técnico e suporte:** o administrador técnico somente manterá a plataforma e não terá acesso implícito a dados de RH/DP. Apenas o `super-admin` poderá assumir temporariamente a sessão de um usuário para suporte, com aviso persistente, retorno seguro e auditoria.
5. **Dupla aprovação:** fechamento da folha, desligamentos, alterações salariais e ajustes retroativos de ponto exigirão dupla aprovação.
6. **Retenção de candidatos:** dados e documentos serão mantidos por 12 meses após o encerramento do processo seletivo. Depois disso, serão excluídos ou anonimizados, salvo consentimento expresso para permanência adicional no banco de talentos.
7. **Folha de pagamento:** inicialmente, o sistema utilizará integração ou importação de fornecedor externo. O processamento completo da folha ficará para uma fase futura.

Essas decisões são os requisitos de referência para a modelagem das Policies, dos escopos organizacionais, dos fluxos de aprovação e das próximas fases de implementação.
