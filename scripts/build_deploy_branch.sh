#!/usr/bin/env bash
# Grid402 // Gera o branch `deploy` — raiz = conteudo de public_html/
#
# Por que: o Git Deploy da Hostinger publica a RAIZ do branch no document root.
# Como o repo tem public_html/ como subpasta, o app cairia em /public_html/ e o
# docroot ficaria sem index.php (403 Forbidden).
# Este script achata public_html/* para a raiz do branch `deploy`.
#
# Uso: bash scripts/build_deploy_branch.sh [branch] [remote]
set -euo pipefail

BRANCH="${1:-deploy}"
REMOTE="${2:-origin}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo ">> Gerando branch '${BRANCH}' a partir de ${REMOTE}/main..."
git -C "$ROOT" fetch "$REMOTE" main

WORKTREE="$(mktemp -d)"
cleanup() { rm -rf "$WORKTREE"; }
trap cleanup EXIT

# Extrai apenas public_html/ do HEAD de main
git -C "$ROOT" archive --format=tar "$REMOTE/main:public_html" | tar -x -C "$WORKTREE"

# Remove segredos/artefatos que nao devem ir para producao
rm -f "$WORKTREE/private_data"/*.json 2>/dev/null || true
rm -f "$WORKTREE/private_data/.env" 2>/dev/null || true
rm -rf "$WORKTREE/node_modules" "$WORKTREE/dist" 2>/dev/null || true

# Garante o .htaccess de bloqueio do private_data
if [ ! -f "$WORKTREE/private_data/.htaccess" ]; then
    mkdir -p "$WORKTREE/private_data"
    printf 'Require all denied\nDeny from all\n' > "$WORKTREE/private_data/.htaccess"
fi

echo ">> Arquivos a publicar: $(find "$WORKTREE" -type f | wc -l)"

# Cria o branch como ORPHAN (sem historico do repo)
cd "$WORKTREE"
git init -q -b "$BRANCH"
git config user.name "Grid402 Deploy Bot"
git config user.email "deploy@grid402.online"
git add -A
git commit -q -m "deploy: public_html -> raiz do site (auto-generated de main)"

# Traz o commit para o repo principal (preserva as credenciais do remote) e publica
git -C "$ROOT" fetch -f "$WORKTREE" "$BRANCH":"refs/heads/$BRANCH"
git -C "$ROOT" push --force "$REMOTE" "refs/heads/$BRANCH":"refs/heads/$BRANCH"

echo ">> Branch '${BRANCH}' publicado em ${REMOTE}."
echo ">> No hPanel: Git -> branch = ${BRANCH} -> Reimplantar"
