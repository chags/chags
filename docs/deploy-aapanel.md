# Envio ao aaPanel por FTP

O workflow `.github/workflows/deploy.yml` somente sincroniza os arquivos do
repositório com a pasta vinculada à conta FTP criada no aaPanel. Ele não acessa
o terminal e não executa comandos no servidor.

## Criar a conta FTP no aaPanel

1. Abra `FTP` no aaPanel e clique em `Add FTP`.
2. Vincule a conta diretamente à pasta raiz do projeto.
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

## Instalação no servidor

O servidor não utiliza Docker. Após o primeiro envio, siga a seção "Instalação
em produção no aaPanel" do `README.md` e execute o arquivo `install.sh`.
