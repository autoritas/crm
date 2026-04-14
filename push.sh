#!/usr/bin/env bash
#
# push.sh — Sube la versión actual del CRM a GitHub (autoritas/crm).
#
# Uso:
#   ./push.sh                       # commitea cambios YA staged + push a la rama actual
#   ./push.sh "mensaje del commit"  # idem con mensaje personalizado
#   ./push.sh -b develop "msg"      # push a la rama indicada
#   ./push.sh --all "msg"           # añade todos los cambios (git add -A) ANTES del escaneo
#
# Por diseño NO ejecuta 'git add -A' salvo que pases --all, para no arrastrar
# ficheros sensibles (.claude/settings.local.json, dumps, .env*, etc.).
#
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REMOTE="origin"
DEFAULT_BRANCH="main"
REMOTE_URL="https://github.com/autoritas/crm.git"

# ---- Parseo de argumentos ---------------------------------------------------
BRANCH=""
MESSAGE=""
ADD_ALL=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        -b|--branch) BRANCH="$2"; shift 2 ;;
        --all)       ADD_ALL=1; shift ;;
        -h|--help)   sed -n '2,14p' "$0"; exit 0 ;;
        *)           MESSAGE="$1"; shift ;;
    esac
done

cd "$REPO_DIR"

[[ -d .git ]] || { echo "ERROR: $REPO_DIR no es un repo git." >&2; exit 1; }

if ! git remote get-url "$REMOTE" >/dev/null 2>&1; then
    git remote add "$REMOTE" "$REMOTE_URL"
fi

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
TARGET_BRANCH="${BRANCH:-${CURRENT_BRANCH:-$DEFAULT_BRANCH}}"
[[ "$CURRENT_BRANCH" == "$TARGET_BRANCH" ]] || git checkout "$TARGET_BRANCH"

# ---- Staging ----------------------------------------------------------------
if [[ "$ADD_ALL" -eq 1 ]]; then
    echo ">> git add -A (solicitado con --all)"
    git add -A
fi

# ---- Listado de ficheros staged --------------------------------------------
STAGED="$(git diff --cached --name-only)"
if [[ -z "$STAGED" ]]; then
    echo ">> No hay cambios staged. Haz 'git add <archivos>' o usa --all."
    # Pero aún permitimos push si hay commits locales sin subir.
fi

# ---- Guardarraíl 1: ficheros prohibidos ------------------------------------
FORBIDDEN_PATTERNS=(
    '^\.env($|\.)'
    '^\.claude/settings\.local\.json$'
    '^\.claude/.*\.local\.json$'
    '\.pem$'
    '\.key$'
    '^auth\.json$'
    'id_rsa'
)
while IFS= read -r f; do
    [[ -z "$f" ]] && continue
    for pat in "${FORBIDDEN_PATTERNS[@]}"; do
        if [[ "$f" =~ $pat ]]; then
            echo "ERROR: archivo prohibido staged -> $f" >&2
            echo "   Quítalo con: git restore --staged '$f'   y añádelo a .gitignore" >&2
            exit 2
        fi
    done
done <<< "$STAGED"

# ---- Guardarraíl 2: patrones de secretos en el diff -------------------------
# Busca solo en líneas añadidas (+), no en contexto.
DIFF_ADDS="$(git diff --cached -U0 | grep -E '^\+' | grep -Ev '^\+\+\+' || true)"

SECRET_REGEXES=(
    'ghp_[A-Za-z0-9]{30,}'                                # GitHub PAT
    'AKIA[0-9A-Z]{16}'                                    # AWS key id
    'sk-[A-Za-z0-9]{20,}'                                 # OpenAI / genérica
    'eyJhbGciOi[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+' # JWT
    '-----BEGIN (RSA|OPENSSH|DSA|EC|PGP) PRIVATE KEY-----'
    'mysql[^\n]*-p[A-Za-z0-9!@#\$%\^&\*_\+=\-]{8,}'       # mysql -pXXXX
    '(password|passwd|secret|api[_-]?key|token)\s*[=:]\s*["'"'"'][^"'"'"' ]{10,}' # genérico
)
HIT=0
for rx in "${SECRET_REGEXES[@]}"; do
    if grep -Eq -e "$rx" <<< "$DIFF_ADDS"; then
        echo "ERROR: posible secreto detectado en diff staged (patrón: $rx)" >&2
        HIT=1
    fi
done
if [[ "$HIT" -eq 1 ]]; then
    echo ">> Revisa 'git diff --cached' y retira los valores sensibles antes de subir." >&2
    exit 3
fi

# ---- Commit (si hay algo staged) -------------------------------------------
if [[ -n "$STAGED" ]]; then
    COMMIT_MSG="${MESSAGE:-chore: actualización $(date '+%Y-%m-%d %H:%M:%S')}"
    echo ">> Commit: $COMMIT_MSG"
    git commit -m "$COMMIT_MSG"
fi

# ---- ¿Hay algo que subir? --------------------------------------------------
git fetch "$REMOTE" "$TARGET_BRANCH" --quiet || true
AHEAD="$(git rev-list --count "$REMOTE/$TARGET_BRANCH..HEAD" 2>/dev/null || echo 0)"
if [[ "${AHEAD:-0}" -eq 0 ]]; then
    echo ">> Nada que subir: HEAD ya está al día con $REMOTE/$TARGET_BRANCH."
    exit 0
fi

# ---- Pull rebase + push -----------------------------------------------------
# Si hay cambios sin commitear (sin staging) los preservamos con autostash.
echo ">> Rebase con $REMOTE/$TARGET_BRANCH (autostash)..."
git pull --rebase --autostash "$REMOTE" "$TARGET_BRANCH" || {
    echo "ERROR: rebase con conflictos. Resuélvelos y relanza." >&2
    exit 1
}

echo ">> Push a $REMOTE/$TARGET_BRANCH..."
git push -u "$REMOTE" "$TARGET_BRANCH"
echo "OK: subido a $REMOTE_URL (rama $TARGET_BRANCH)."
