# Populorum v0.6.0 — installation Arch Linux

Archive du logiciel Symfony 8.1 / PHP >= 8.4 / SQLite, sans données personnelles, sans `vendor/`, sans clés locales.

## Installation (archive GitHub)

1. Sur Arch Linux : `sudo pacman -Syu --needed php php-sqlite composer git python unzip`.
2. Activer `pdo_sqlite` et `sqlite3` dans `/etc/php/php.ini` si `php -m` ne les liste pas.
3. Depuis le dossier extrait : `cd apps/symfony && bash tools/install-arch.sh --check && bash tools/install-arch.sh`.
4. Démarrer : `php -S 127.0.0.1:5412 -t public`, puis ouvrir http://127.0.0.1:5412/.

La release est créée automatiquement **uniquement après succès des validations Ubuntu et Arch Linux**. Elle contient le code et un fichier SHA256SUMS. Si le contrôle échoue, aucune release n'est créée.

**Important :** l'index national `public-procurement.sqlite` (~1,9 Go) et les JSON DECP ne sont pas inclus. Ils peuvent être reconstruits avec `tools/decp_pipeline_v8.py` depuis les données publiques, ou distribués indépendamment dans une release de données après publication depuis le poste qui les détient. Voir `apps/symfony/docs/ARCHIVES_DECP.md`.

Cette version demeure un démonstrateur fictif, et non une plateforme électorale ou d'achats publics réglementaire.
