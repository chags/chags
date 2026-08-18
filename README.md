# Chags Technology

Somos a Chags Technology, uma empresa de tecnologia especializada em serviços de TI terceirizados para empresas e governo.

Nossa missão é oferecer soluções tecnológicas eficientes, seguras e escaláveis, com foco em produtividade, inovação e suporte contínuo para organizações que buscam transformar sua operação digital.

## O que fazemos

- Serviços de TI terceirizados para empresas
- Suporte e gestão de infraestrutura tecnológica
- Soluções digitais e automação de processos
- Atendimento especializado para órgãos públicos e organizações privadas

## Nossa proposta

A Chags Technology atua como parceiro estratégico, entregando tecnologia com responsabilidade, agilidade e excelência, contribuindo para o crescimento e a modernização dos negócios e da gestão pública.

## Estrutura do projeto

Estamos estruturando uma plataforma integrada para apoiar a operação da empresa, com módulos para:

- Sistema interno de RH
- Central de suporte e atendimento
- Intranet corporativa
- Página de Trabalhe Conosco
- Site institucional

Essa estrutura permitirá integrar colaboradores, processos internos, comunicação corporativa e oportunidades de carreira em uma única experiência digital.

## Ambiente Docker

Este projeto utiliza Docker como ambiente principal para desenvolvimento.

- Use `docker compose up -d --build` para subir os containers.
- Use `docker compose exec app php artisan migrate` para executar migrations.
- Use `docker compose exec app php artisan ...` para rodar comandos do Laravel dentro do container.
- O app fica disponível em `http://localhost:9000`.

## Instalação em produção no aaPanel

O servidor de produção não utiliza Docker. O site já deve estar configurado no
aaPanel com PHP 8.4, apontando para a subpasta `public` da raiz do projeto:

```text
<raiz-do-projeto>/public
```

Antes de instalar, habilite no PHP as extensões `pdo_pgsql`, `pgsql`, `gd`,
`intl`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl` e
`zip`. Instale também Composer, Node.js 22 ou superior, npm e PostgreSQL.

Depois que o GitHub Actions concluir o envio por FTP, abra o terminal do aaPanel.
Já estando na raiz do projeto, execute:

```bash
chmod 700 install.sh
./install.sh
```

O instalador solicita e valida:

- Nome e URL pública da aplicação.
- Servidor e porta do PostgreSQL.
- Nome do banco, usuário e senha.
- PHP 8.4 e extensões obrigatórias.
- Composer, Node.js e npm.
- Conexão real com o banco antes das migrations.
- Permissões do Laravel e geração do build do Vite.

O arquivo `.env` é criado a partir do `.env.example` com `APP_ENV=production`,
`APP_DEBUG=false` e uma nova `APP_KEY`. Se já existir uma `APP_KEY`, ela será
preservada para não invalidar dados criptografados. Um `.env` existente também
recebe backup antes de qualquer alteração.

A senha do banco não é exibida. Toda a execução é registrada em:

O relatório será salvo como `install-report.log` na mesma pasta do instalador.

Se ocorrer uma falha, o terminal e o relatório mostrarão a etapa, linha,
comando e código do erro. Para usar outro PHP ou usuário do PHP-FPM:

```bash
PHP_BIN=/caminho/do/php WEB_USER=www ./install.sh
```

Se uma instalação iniciada anteriormente apresentar `Please provide a valid
cache path`, recrie os diretórios de runtime antes de repetir o build:

```bash
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache
chown -R www:www storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +
```

Após futuras atualizações por FTP, rode novamente Composer, o build e as
migrations:

```bash
/www/server/php/84/bin/php "$(command -v composer)" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
rm -f public/hot
npm ci
PHP_BIN=/www/server/php/84/bin/php npm run build
/www/server/php/84/bin/php artisan migrate --force
/www/server/php/84/bin/php artisan optimize:clear
/www/server/php/84/bin/php artisan optimize
```

## Contato

Entre em contato para conhecer nossos serviços e descobrir como podemos apoiar sua organização.
