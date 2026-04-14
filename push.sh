#!/usr/bin/env bash
#
# push.sh — Sube la versión actual del CRM a GitHub (autoritas/crm).
#
# Uso:
#   ./push.sh                       # commit con mensaje por defecto + push a main
#   ./push.sh "mensaje del commit"  # commit con mensaje personalizado + push a main
#   ./push.sh -b develop "msg"      # commit + push a la rama indicada
#
set -euo pipefail

# ---- Configuración ----------------------------------------------------------
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REMOTE="origin"
DEFAULT_BRANCH="main"
REMOTE_URL="https://github.com/autoritas/crm.git"

# ---- Parseo de argumentos ---------------------------------------------------
BRANCH=""
MESSAGE=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        -b|--branch)
            BRANCH="$2"; shift 2 ;;
        -h|--help)
            sed -n '2,10p' "$0"; exit 0 ;;
        *)
            MESSAGE="$1"; shift ;;
    esac
done

cd "$REPO_DIR"

# ---- Verificaciones ---------------------------------------------------------
if [[ ! -d .git ]]; then
    echo "ERROR: $REPO_DIR no es un repositorio git." >&2
    exit 1
fi

# Asegurar que el remote existe y apunta al repo correcto
if ! git remote get-url "$REMOTE" >/dev/null 2>&1; then
    echo ">> Añadiendo remote '$REMOTE' -> $REMOTE_URL"
    git remote add "$REMOTE" "$REMOTE_URL"
fi

# Determinar rama destino
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
TARGET_BRANCH="${BRANCH:-${CURRENT_BRANCH:-$DEFAULT_BRANCH}}"

if [[ "$CURRENT_BRANCH" != "$TARGET_BRANCH" ]]; then
    echo ">> Cambiando a la rama $TARGET_BRANCH"
    git checkout "$TARGET_BRANCH"
fi

# ---- Commit -----------------------------------------------------------------
if [[ -n "$(git status --porcelain)" ]]; then
    echo ">> Añadiendo cambios al índice..."
    git add -A

    COMMIT_MSG="${MESSAGE:-chore: actualización $(date '+%Y-%m-%d %H:%M:%S')}"
    echo ">> Creando commit: $COMMIT_MSG"
    git commit -m "$COMMIT_MSG"
else
    echo ">> No hay cambios pendientes, se procede solo con el push."
fi

# ---- Pull + Push ------------------------------------------------------------
echo ">> Sincronizando con $REMOTE/$TARGET_BRANCH (rebase)..."
git pull --rebase "$REMOTE" "$TARGET_BRANCH" || {
    echo "ERROR: conflicto durante el rebase. Resuélvelo y ejecuta 'git rebase --continue'." >&2
    exit 1
}

echo ">> Subiendo a $REMOTE/$TARGET_BRANCH..."
git push -u "$REMOTE" "$TARGET_BRANCH"

echo
echo "OK: versión subida a $REMOTE_URL (rama $TARGET_BRANCH)."
