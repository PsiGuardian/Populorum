#!/usr/bin/env bash
# Installation locale reproductible, sans modification du système ni des données DECP.
set -Eeuo pipefail
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ "${1:-}" != "" && "${1:-}" != "--check" && "${1:-}" != "--verify" ]]; then
    echo "Usage: bash tools/install-arch.sh [--check|--verify]" >&2
    exit 2
fi

if [[ "$(uname -s)" != "Linux" ]] || ! command -v pacman >/dev/null 2>&1; then
    echo "Installation Arch Linux: pacman introuvable. Lire LIRE_AVANT_INSTALLATION.md." >&2
    exit 1
fi

for bin in php composer git python3; do
    if ! command -v "$bin" >/dev/null 2>&1; then
        echo "Pré-requis manquant : $bin" >&2
        echo "Installer avec : sudo pacman -Syu --needed php php-sqlite composer git python" >&2
        exit 1
    fi
done

if ! php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'; then
    echo "PHP 8.4+ requis. Version installée : $(php -r 'echo PHP_VERSION;')" >&2
    exit 1
fi

missing=()
for extension in pdo pdo_sqlite sqlite3 openssl tokenizer mbstring iconv dom simplexml xml curl zlib ctype; do
    if ! php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$extension"; then
        missing+=("$extension")
    fi
done
if (("${#missing[@]}" > 0)); then
    printf 'Extensions PHP non chargées : %s\n' "${missing[*]}" >&2
    echo "Vérifier les modules disponibles et les activer dans /etc/php/php.ini ou /etc/php/conf.d/." >&2
    echo "Pour SQLite : sudo pacman -S --needed php-sqlite ; activer extension=pdo_sqlite et extension=sqlite3." >&2
    echo "Pour les autres modules, ne charger que les .so réellement installés (éviter les doublons)." >&2
    exit 1
fi

php -r '$db = new PDO("sqlite::memory:"); $db->exec("CREATE TABLE smoke (id INTEGER)"); if ($db->query("PRAGMA quick_check")->fetchColumn() !== "ok") exit(1);'
echo "Pré-requis OK : $(php -r 'echo PHP_VERSION;'), Composer $(composer --version --no-ansi | head -n 1)"
if [[ "${1:-}" == "--check" ]]; then
    exit 0
fi

if [[ ! -f composer.lock ]]; then
    echo "composer.lock absent : archive incomplète, installation arrêtée." >&2
    exit 1
fi

if ! composer install --no-interaction --prefer-dist --no-progress; then
    echo "ECHEC : Composer n'a pas installé les dépendances." >&2
    echo "Consulter le message ci-dessus ; ne pas démarrer le serveur PHP." >&2
    exit 1
fi
if [[ ! -f vendor/autoload.php ]]; then
    echo "ECHEC : vendor/autoload.php absent malgré composer install." >&2
    echo "Ne pas démarrer le serveur ; vérifier les messages de Composer." >&2
    exit 1
fi
composer check-platform-reqs --no-dev
php tools/init-demo.php
APP_ENV=demo APP_DEBUG=0 php bin/console doctrine:migrations:migrate --no-interaction --env=demo
APP_ENV=demo APP_DEBUG=0 php bin/console app:demo:prepare --env=demo
APP_ENV=demo APP_DEBUG=0 php bin/console cache:clear --env=demo --no-debug
APP_ENV=demo APP_DEBUG=0 php bin/console lint:twig templates --env=demo
APP_ENV=demo APP_DEBUG=0 php bin/console doctrine:schema:validate --env=demo
python3 tools/smoke_http.py

if [[ "${1:-}" == "--verify" ]]; then
    composer test
fi
printf '\nINSTALLATION OK. Démarrer depuis apps/symfony :\n'
printf '  APP_ENV=demo APP_DEBUG=0 php -S 127.0.0.1:5412 -t public\n'
printf '  puis ouvrir http://127.0.0.1:5412/\n'
printf 'Index national DECP facultatif : voir docs/ARCHIVES_DECP.md\n'
