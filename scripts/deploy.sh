#!/bin/bash
# scripts/deploy.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

trap 'rm -f "$PROJECT_ROOT/version.txt" "$PROJECT_ROOT/deploy-info.json"' ERR

REMOTE="oliweb"
REMOTE_PATH="sites/oliphoto"

DRY_RUN=""
FORCE=false

for arg in "$@"; do
    case "$arg" in
        --test)   DRY_RUN="--dry-run"; echo "🧪 MODE TEST (aucun changement réel)" ;;
        --force)  FORCE=true ;;
    esac
done

echo "📁 Racine du projet : $PROJECT_ROOT"
cd "$PROJECT_ROOT"

VERSION=$(git describe --tags --always --dirty)
DEPLOY_DATE=$(date -Iseconds)

if ! git describe --tags --exact-match 2>/dev/null; then
    echo "⚠️  Pas sur un tag exact — version : $VERSION"
    if [[ "$FORCE" == "true" ]]; then
        echo "   --force : déploiement sans tag"
    else
        read -p "   Continuer quand même ? (y/N) " -n 1 -r
        echo
        [[ ! $REPLY =~ ^[Yy]$ ]] && { echo "❌ Annulé"; exit 1; }
    fi
fi

echo "📌 Version : $VERSION"
echo "📅 Date    : $DEPLOY_DATE"

echo "$VERSION" > version.txt
cat > deploy-info.json << EOF
{
    "version": "$VERSION",
    "deployed_at": "$DEPLOY_DATE",
    "deployed_by": "$(whoami)",
    "git_branch": "$(git rev-parse --abbrev-ref HEAD)",
    "git_commit": "$(git rev-parse HEAD)"
}
EOF

echo "🔨 Build des assets..."
yarn build

echo "🔧 Optimisation Statamic..."
php please stache:clear
php please glide:clear


echo "📦 Déploiement..."
rsync -avz --delete $DRY_RUN \
    --exclude='.git' \
    --exclude='.claude' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.env' \
    --exclude='scripts/' \
    --exclude='storage/app/*' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='users/' \
    --exclude='public/photos/' \
    --exclude='public/build/' \
    --exlude='content/'
    ./ $REMOTE:$REMOTE_PATH/

# Assets compilés : sync sans --delete (ne jamais supprimer un upload)
rsync -avz $DRY_RUN \
    ./public/build/ $REMOTE:$REMOTE_PATH/public/build/
    ./content/ $REMOTE:$REMOTE_PATH/content/

if [[ "$DRY_RUN" ]]; then
    echo "🧪 Test terminé, aucun changement effectué"
    rm -f version.txt deploy-info.json
    exit 0
fi

echo "🔧 Optimisation Statamic..."
ssh -T $REMOTE bash << ENDSSH
    set -e
    source ~/.profile

    cd $REMOTE_PATH

    php -v | head -n1

    composer install --no-dev --optimize-autoloader
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link
    php please stache:clear
    php please stache:warm
    php artisan statamic:assets:meta
    php please static:clear
    php please glide:clear

    echo ""
    echo "📌 Version déployée : \$(cat version.txt 2>/dev/null || echo 'inconnue')"
ENDSSH

rm -f version.txt deploy-info.json

echo ""
echo "✅ Déploiement terminé !"
echo "📌 Version : $VERSION"
