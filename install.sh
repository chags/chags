#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_DIR="${PROJECT_DIR:-/www/wwwroot/chags}"
WEB_USER="${WEB_USER:-www}"
PHP_BIN="${PHP_BIN:-/www/server/php/84/bin/php}"
ENV_FILE="$PROJECT_DIR/.env"
ENV_EXAMPLE="$PROJECT_DIR/.env.example"
INSTALL_LOG="${INSTALL_LOG:-$PROJECT_DIR/install-report.log}"
CURRENT_STEP="Inicialização"

fail() {
    printf 'ERRO: %s\n' "$1" >&2
    exit 1
}

report_error() {
    local exit_code=$?
    local line="$1"
    local command="$2"

    set +e
    printf '\n============================================================\n' >&2
    printf 'A INSTALAÇÃO FALHOU\n' >&2
    printf 'Etapa: %s\n' "$CURRENT_STEP" >&2
    printf 'Linha: %s\n' "$line" >&2
    printf 'Comando: %s\n' "$command" >&2
    printf 'Código de saída: %s\n' "$exit_code" >&2
    printf 'Relatório completo: %s\n' "$INSTALL_LOG" >&2
    printf '============================================================\n' >&2
    exit "$exit_code"
}

set_env() {
    local key="$1"
    local value="$2"
    local temporary

    [[ "$value" != *$'\n'* ]] || fail "O valor de $key não pode conter quebra de linha."
    [[ "$value" != *'"'* ]] || fail "O valor de $key não pode conter aspas duplas."
    [[ "$value" != *'\'* ]] || fail "O valor de $key não pode conter barra invertida."
    [[ "$value" != *'${'* ]] || fail "O valor de $key não pode conter a sequência \${."

    temporary="$(mktemp)"
    ENV_KEY="$key" ENV_VALUE="$value" awk '
        BEGIN { found = 0 }
        $0 ~ "^" ENVIRON["ENV_KEY"] "=" {
            print ENVIRON["ENV_KEY"] "=\"" ENVIRON["ENV_VALUE"] "\""
            found = 1
            next
        }
        { print }
        END {
            if (! found) {
                print ENVIRON["ENV_KEY"] "=\"" ENVIRON["ENV_VALUE"] "\""
            }
        }
    ' "$ENV_FILE" > "$temporary"

    mv "$temporary" "$ENV_FILE"
}

[[ "$EUID" -eq 0 ]] || fail "Execute este instalador como root no terminal do aaPanel."
[[ -d "$PROJECT_DIR" ]] || fail "Diretório do projeto não encontrado: $PROJECT_DIR"
[[ -w "$PROJECT_DIR" ]] || fail "Sem permissão de escrita em: $PROJECT_DIR"

touch "$INSTALL_LOG" || fail "Não foi possível criar o relatório: $INSTALL_LOG"
chmod 600 "$INSTALL_LOG"
exec > >(tee -a "$INSTALL_LOG") 2>&1
trap 'report_error "$LINENO" "$BASH_COMMAND"' ERR

printf '\nInstalação iniciada em %s\n' "$(date '+%Y-%m-%d %H:%M:%S')"
printf 'Projeto: %s\n' "$PROJECT_DIR"
printf 'Relatório: %s\n\n' "$INSTALL_LOG"

CURRENT_STEP="Validação do servidor"

[[ -x "$PHP_BIN" ]] || fail "PHP não encontrado em $PHP_BIN. Ajuste PHP_BIN ao executar o script."

if command -v composer >/dev/null 2>&1; then
    COMPOSER_BIN="$(command -v composer)"
elif [[ -f /www/server/php/84/bin/composer ]]; then
    COMPOSER_BIN=/www/server/php/84/bin/composer
else
    fail "Composer não está instalado ou não está no PATH."
fi

command -v node >/dev/null 2>&1 || fail "Node.js não está instalado ou não está no PATH."
command -v npm >/dev/null 2>&1 || fail "npm não está instalado ou não está no PATH."
command -v openssl >/dev/null 2>&1 || fail "OpenSSL não está instalado."
command -v awk >/dev/null 2>&1 || fail "awk não está instalado."
command -v tee >/dev/null 2>&1 || fail "tee não está instalado."
id "$WEB_USER" >/dev/null 2>&1 || fail "O usuário do PHP-FPM '$WEB_USER' não existe."
[[ -f "$ENV_EXAMPLE" ]] || fail "Arquivo .env.example não encontrado."
[[ -f "$PROJECT_DIR/artisan" ]] || fail "Arquivo artisan não encontrado."
[[ -f "$PROJECT_DIR/composer.json" ]] || fail "Arquivo composer.json não encontrado."
[[ -f "$PROJECT_DIR/package.json" ]] || fail "Arquivo package.json não encontrado."

php_version="$($PHP_BIN -r 'echo PHP_VERSION;')"
php_major_minor="$($PHP_BIN -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$php_major_minor" == "8.4" ]] || fail "PHP 8.4 é obrigatório. Encontrado: $php_version"

required_extensions=(ctype curl fileinfo gd intl mbstring openssl pdo_pgsql tokenizer xml zip)
for extension in "${required_extensions[@]}"; do
    "$PHP_BIN" -m | grep -Fxiq "$extension" || fail "Extensão PHP ausente: $extension"
done

node_major="$(node -p 'process.versions.node.split(".")[0]')"
[[ "$node_major" =~ ^[0-9]+$ && "$node_major" -ge 22 ]] || fail "Node.js 22 ou superior é obrigatório."

printf 'PHP: %s\n' "$php_version"
printf 'Node.js: %s\n' "$(node --version)"
printf 'npm: %s\n' "$(npm --version)"
printf 'Composer: %s\n\n' "$($PHP_BIN "$COMPOSER_BIN" --version --no-ansi)"

CURRENT_STEP="Coleta e validação das configurações"

printf 'Nome da aplicação [Chags]: '
read -r APP_NAME_INPUT
APP_NAME_INPUT="${APP_NAME_INPUT:-Chags}"

printf 'URL da aplicação [https://seu-dominio.com.br]: '
read -r APP_URL_INPUT
APP_URL_INPUT="${APP_URL_INPUT:-https://seu-dominio.com.br}"

printf 'Servidor PostgreSQL [127.0.0.1]: '
read -r DB_HOST_INPUT
DB_HOST_INPUT="${DB_HOST_INPUT:-127.0.0.1}"

printf 'Porta PostgreSQL [5432]: '
read -r DB_PORT_INPUT
DB_PORT_INPUT="${DB_PORT_INPUT:-5432}"

printf 'Nome do banco PostgreSQL [chags]: '
read -r DB_DATABASE_INPUT
DB_DATABASE_INPUT="${DB_DATABASE_INPUT:-chags}"

printf 'Usuário do banco PostgreSQL [chags]: '
read -r DB_USERNAME_INPUT
DB_USERNAME_INPUT="${DB_USERNAME_INPUT:-chags}"

printf 'Senha do banco PostgreSQL: '
read -r -s DB_PASSWORD_INPUT
printf '\n'

[[ -n "$APP_NAME_INPUT" ]] || fail "O nome da aplicação é obrigatório."
[[ "$APP_URL_INPUT" =~ ^https?://[^[:space:]]+$ ]] || fail "Informe uma URL válida iniciada por http:// ou https://."
[[ "$DB_PORT_INPUT" =~ ^[0-9]+$ ]] || fail "A porta do banco deve conter somente números."
(( DB_PORT_INPUT >= 1 && DB_PORT_INPUT <= 65535 )) || fail "A porta do banco deve estar entre 1 e 65535."
[[ "$DB_DATABASE_INPUT" =~ ^[A-Za-z0-9_.-]+$ ]] || fail "Nome do banco inválido."
[[ "$DB_USERNAME_INPUT" =~ ^[A-Za-z0-9_.-]+$ ]] || fail "Usuário do banco inválido."
[[ -n "$DB_PASSWORD_INPUT" ]] || fail "A senha do banco é obrigatória."

CURRENT_STEP="Teste da conexão PostgreSQL"
printf 'Testando a conexão com o PostgreSQL...\n'
DB_HOST="$DB_HOST_INPUT" \
DB_PORT="$DB_PORT_INPUT" \
DB_DATABASE="$DB_DATABASE_INPUT" \
DB_USERNAME="$DB_USERNAME_INPUT" \
DB_PASSWORD="$DB_PASSWORD_INPUT" \
    "$PHP_BIN" -r '
        try {
            new PDO(
                "pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD"),
                [PDO::ATTR_TIMEOUT => 10],
            );
            echo "Conexão com o PostgreSQL confirmada.\n";
        } catch (Throwable $error) {
            fwrite(STDERR, "Falha ao conectar ao PostgreSQL: ".$error->getMessage()."\n");
            exit(1);
        }
    '

CURRENT_STEP="Criação do arquivo .env"

if [[ -f "$ENV_FILE" ]]; then
    backup="$ENV_FILE.backup.$(date +%Y%m%d%H%M%S)"
    cp "$ENV_FILE" "$backup"
    printf 'Backup do .env criado em %s\n' "$backup"
else
    cp "$ENV_EXAMPLE" "$ENV_FILE"
fi

existing_app_key="$(awk -F= '/^APP_KEY=/{sub(/^APP_KEY=/, ""); gsub(/^"|"$/, ""); print; exit}' "$ENV_FILE")"
if [[ -z "$existing_app_key" ]]; then
    set_env APP_KEY "base64:$(openssl rand -base64 32 | tr -d '\n')"
    printf 'Nova APP_KEY gerada.\n'
else
    printf 'APP_KEY existente preservada.\n'
fi

set_env APP_NAME "$APP_NAME_INPUT"
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$APP_URL_INPUT"
set_env LOG_LEVEL warning
set_env DB_CONNECTION pgsql
set_env DB_HOST "$DB_HOST_INPUT"
set_env DB_PORT "$DB_PORT_INPUT"
set_env DB_DATABASE "$DB_DATABASE_INPUT"
set_env DB_USERNAME "$DB_USERNAME_INPUT"
set_env DB_PASSWORD "$DB_PASSWORD_INPUT"
set_env VITE_APP_NAME "$APP_NAME_INPUT"

chmod 600 "$ENV_FILE"
cd "$PROJECT_DIR"

CURRENT_STEP="Instalação das dependências PHP"
"$PHP_BIN" "$COMPOSER_BIN" install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

CURRENT_STEP="Build do frontend"
rm -f public/hot
npm ci
npm run build

CURRENT_STEP="Preparação das permissões"
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +

CURRENT_STEP="Migrations do banco"
"$PHP_BIN" artisan migrate --force

CURRENT_STEP="Finalização do Laravel"
if [[ ! -L public/storage ]]; then
    "$PHP_BIN" artisan storage:link
fi
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan optimize

CURRENT_STEP="Verificação final"
"$PHP_BIN" artisan about --only=environment
[[ -f public/build/manifest.json ]] || fail "O manifest do Vite não foi gerado em public/build."
[[ -w storage/logs ]] || fail "O diretório storage/logs não está gravável."

printf '\n============================================================\n'
printf 'INSTALAÇÃO CONCLUÍDA COM SUCESSO\n'
printf 'Aplicação: %s\n' "$APP_NAME_INPUT"
printf 'URL: %s\n' "$APP_URL_INPUT"
printf 'Banco: %s:%s/%s\n' "$DB_HOST_INPUT" "$DB_PORT_INPUT" "$DB_DATABASE_INPUT"
printf 'Usuário do banco: %s\n' "$DB_USERNAME_INPUT"
printf 'Relatório: %s\n' "$INSTALL_LOG"
printf '============================================================\n'
