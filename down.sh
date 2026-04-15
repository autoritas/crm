#!/usr/bin/env bash
#
# down.sh — Actualiza el CRM en producción desde GitHub (autoritas/crm).
#
# Uso:
#   ./down.sh                 # pull + instalar + migrar + cachear (rama actual)
#   ./down.sh -b main         # fuerza rama main
#   ./down.sh --no-build      # salta npm ci && npm run build
#   ./down.sh --no-migrate    # salta php artisan migrate
#   ./down.sh --no-maint      # no entra en modo mantenimiento
#
# Pensado para ejecutarse en el servidor:
#   cd /var/www/html/crm && ./down.sh
#
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REMOTE="origin"
DEFAULT_BRANCH="main"

# ---- Flags ------------------------------------------------------------------
BRANCH=""
DO_BUILD=1
DO_MIGRATE=1
DO_MAINT=1
while [[ $# -gt 0 ]]; do
    case "$1" in
        -b|--branch)  BRANCH="$2"; shift 2 ;;
        --no-build)   DO_BUILD=0; shift ;;
        --no-migrate) DO_MIGRATE=0; shift ;;
        --no-maint)   DO_MAINT=0; shift ;;
        -h|--help)    sed -n '2,14p' "$0"; exit 0 ;;
        *)            echo "Argumento desconocido: $1" >&2; exit 1 ;;
    esac
done

cd "$REPO_DIR"
[[ -d .git ]] || { echo "ERROR: $REPO_DIR no es un repo git." >&2; exit 1; }
[[ -f artisan ]] || { echo "ERROR: no encuentro 'artisan' en $REPO_DIR." >&2; exit 1; }
[[ -f .env ]] || { echo "ERROR: no hay .env en $REPO_DIR. Crea/copia uno antes de actualizar." >&2; exit 1; }

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
TARGET_BRANCH="${BRANCH:-${CURRENT_BRANCH:-$DEFAULT_BRANCH}}"

echo ">> Rama destino: $TARGET_BRANCH"
[[ "$CURRENT_BRANCH" == "$TARGET_BRANCH" ]] || git checkout "$TARGET_BRANCH"

# ---- Limpieza previa: si hay cambios locales los guardamos (autostash) -----
DIRTY=0
if ! git diff --quiet || ! git diff --cached --quiet; then
    DIRTY=1
    echo ">> Hay cambios locales — se preservarán con autostash en el rebase."
fi

# ---- Modo mantenimiento -----------------------------------------------------
MAINT_ACTIVE=0
if [[ "$DO_MAINT" -eq 1 ]]; then
    echo ">> Activando modo mantenimiento..."
    php artisan down --render="errors::503" --retry=30 || true
    MAINT_ACTIVE=1
fi

# Asegura que salimos de mantenimiento pase lo que pase
cleanup() {
    local code=$?
    if [[ "$MAINT_ACTIVE" -eq 1 ]]; then
        echo ">> Desactivando modo mantenimiento..."
        php artisan up || true
    fi
    exit $code
}
trap cleanup EXIT

# ---- Git pull ---------------------------------------------------------------
echo ">> Fetch $REMOTE..."
git fetch "$REMOTE" "$TARGET_BRANCH"

echo ">> Pull --rebase --autostash..."
git pull --rebase --autostash "$REMOTE" "$TARGET_BRANCH"

echo ">> HEAD actual: $(git rev-parse --short HEAD) — $(git log -1 --pretty=%s)"

# ---- Composer ---------------------------------------------------------------
echo ">> composer install (prod)..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---- Front-end --------------------------------------------------------------
if [[ "$DO_BUILD" -eq 1 ]]; then
    if [[ -f package.json ]]; then
        echo ">> npm ci && npm run build..."
        npm ci --no-audit --no-fund
        npm run build
    else
        echo ">> package.json no presente — salto build front-end."
    fi
else
    echo ">> --no-build: salto build front-end."
fi

# ---- Migraciones ------------------------------------------------------------
if [[ "$DO_MIGRATE" -eq 1 ]]; then
    echo ">> php artisan migrate --force..."
    php artisan migrate --force
else
    echo ">> --no-migrate: salto migraciones."
fi

# ---- Storage link (idempotente) --------------------------------------------
if [[ ! -L public/storage ]]; then
    echo ">> php artisan storage:link..."
    php artisan storage:link || true
fi

# ---- Limpiar y reconstruir caches ------------------------------------------
echo ">> Limpiando caches..."
php artisan optimize:clear

echo ">> Reconstruyendo caches de producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:cache-components || true

# ---- Permisos (por si el pull ha añadido ficheros nuevos) ------------------
if id www-data >/dev/null 2>&1; then
    echo ">> Ajustando ownership a www-data en storage/ y bootstrap/cache/..."
    chown -R www-data:www-data storage bootstrap/cache || true
    chmod -R 775 storage bootstrap/cache || true
fi

# ---- Recargar PHP-FPM (si existe) para invalidar opcache -------------------
for svc in php8.3-fpm php8.2-fpm php-fpm; do
    if systemctl list-unit-files 2>/dev/null | grep -q "^${svc}\.service"; then
        echo ">> Recargando ${svc}..."
        systemctl reload "$svc" || systemctl restart "$svc" || true
        break
    fi
done

# ---- Reiniciar colas si hay supervisor -------------------------------------
if command -v supervisorctl >/dev/null 2>&1; then
    if supervisorctl status 2>/dev/null | grep -q 'crm-worker'; then
        echo ">> Reiniciando workers crm-worker:*..."
        supervisorctl restart 'crm-worker:*' || true
    fi
fi

echo "OK: despliegue completo en rama $TARGET_BRANCH ($(git rev-parse --short HEAD))."
