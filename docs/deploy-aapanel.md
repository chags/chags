# Envio ao aaPanel por FTP

O workflow `.github/workflows/deploy.yml` somente sincroniza os arquivos do
repositório com a pasta vinculada à conta FTP criada no aaPanel. Ele não acessa
o terminal e não executa comandos no servidor.

## Criar a conta FTP no aaPanel

1. Abra `FTP` no aaPanel e clique em `Add FTP`.
2. Vincule a conta à pasta do projeto, por exemplo `/www/wwwroot/chags`.
3. Guarde o servidor, usuário e senha gerados.
4. Verifique se a porta FTP `21` está liberada no firewall do servidor e do
   provedor.

## Secrets do GitHub

Crie um environment chamado `production` no repositório e cadastre somente:

| Secret | Conteúdo |
| --- | --- |
| `FTP_SERVER` | IP ou domínio do servidor, sem `ftp://` |
| `FTP_USERNAME` | Usuário FTP criado pelo aaPanel |
| `FTP_PASSWORD` | Senha da conta FTP |

Como a conta FTP já aponta para a pasta do projeto, o destino usado pelo
workflow é `./`.

## Arquivos que não são enviados

- `.env`, `.env.backup` e `.env.production`
- `vendor`
- `node_modules`
- `.git` e `.github`
- builds e links gerados em `public`
- logs, sessões e caches de execução

O arquivo `.env.example` é enviado normalmente como referência. O `.env` real
deve ser criado e mantido diretamente no servidor.

## Comandos no servidor

Após o primeiro envio, acesse o terminal do aaPanel e execute os comandos de
instalação e inicialização que forem necessários. Para a composição Docker de
produção incluída no projeto, o fluxo é:

```bash
cd /www/wwwroot/chags
cp .env.example .env
# Edite o .env antes de continuar.
docker compose -f docker-compose.production.yml up -d --build
docker exec chags-app npm ci
docker exec chags-app npm run build
docker exec chags-app php artisan optimize
docker exec chags-app php artisan storage:link
```

Nos próximos envios, execute novamente apenas os comandos necessários para a
alteração publicada, como migrations, build dos assets ou reinício dos
containers.
