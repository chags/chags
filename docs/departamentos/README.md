# Planejamento técnico dos departamentos

Este diretório detalha a implantação dos dois departamentos definidos para a plataforma. Os documentos são especificações de trabalho; os arquivos de aplicação listados ainda não foram criados.

## Departamentos

1. [Recursos Humanos](recursos-humanos.md): vagas, candidatos, processos seletivos, admissão e gestão cadastral de colaboradores.
2. [Departamento Pessoal](departamento-pessoal.md): documentos trabalhistas, ponto, férias, benefícios, integração de folha e desligamentos.

## Processos detalhados

- [Avaliações de candidaturas](avaliacoes-de-candidaturas.md): pareceres internos de RH e gestores durante o processo seletivo.
- [Agendamento de entrevistas](agendamento-de-entrevistas.md): agenda, reuniões, convites e avaliações das fases 4, 5 e 6.

## Fundação compartilhada

Os dois departamentos utilizarão:

- autenticação existente em `users`;
- papéis e permissões do `spatie/laravel-permission` no guard `web`;
- empresas e unidades organizacionais;
- cargos profissionais e setores separados dos papéis de acesso;
- Policies para limitar registros por titular, equipe e unidade;
- auditoria das operações sensíveis;
- arquivos privados, entregues somente após autorização do backend.

As tabelas funcionais descritas nesses documentos armazenarão dados de negócio. Nenhuma delas substituirá as tabelas de autorização do Spatie.
