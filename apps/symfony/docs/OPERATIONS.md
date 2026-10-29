# Exploitation du démonstrateur Symfony

Les commandes sont à lancer dans `apps/symfony`, avec PHP 8.4+, les extensions indiquées et `composer install`. DocumentRoot : `public/`, HTTPS en ligne. Les clés, comptes, profils, sessions, sauvegardes et accès CSV restent hors du dossier public et de Git.

La page `/charter` lit les 28 documents embarqués dans `data/charters.json.gz` : le déploiement de `apps/symfony` seul suffit. Après une modification de `CHARTER.md` ou de `docs/translations/*/CHARTER.md` à la racine du dépôt, exécuter `php tools/build-charters.php` dans `apps/symfony` et committer l’archive régénérée. Les tests vérifient son identité avec les sources lisibles.

Si `/charter` échoue en ligne, relever le véritable statut HTTP et consulter les journaux privés de Symfony et de PHP-FPM. Un statut 500 peut être remplacé par une page générique du bundle. Après mise à jour du code et de l’archive, vider le cache de l’environnement réellement utilisé (`php bin/console cache:clear --env=demo` pour la configuration de démonstration) et recharger PHP-FPM si OPcache ne vérifie pas les dates des fichiers. Une véritable 404 nécessite aussi de vérifier `php bin/console debug:router --env=demo` et le renvoi des URL vers `public/index.php` dans le serveur web.

## Mise à niveau

```bash
php bin/console doctrine:migrations:migrate --env=demo --no-interaction
php bin/console cache:clear --env=demo
```

Les projections métier ont leurs propres tables, notamment une contrainte unique sur scénario/consultation/citoyen pour les voix actives. Le snapshot et les projections sont modifiés dans la même transaction et sous CAS. Le snapshot signé permet la reconstruction ; les objets complexes gardent des attributs JSON documentés. Ce modèle n’est pas présenté comme la normalisation intégrale de toutes les entités conceptuelles du cahier des charges.

## Laboratoire partagé et accès fictifs

```bash
php bin/console app:citizens:accounts --workspace=shared-demo --output=var/shared-demo-access.csv
```

Cette commande crée des secrets aléatoires hachés par Symfony et un CSV privé en mode 0600. Ne pas publier ce CSV et ne pas réutiliser ces accès pour une instance réelle. Connexion : `/citizen/login`. Un compte est lié à un personnage et un laboratoire, sans possibilité de changer de personnage par `/persona`. La lecture anonyme reste ouverte. Le mode de jeu isolé conserve le sélecteur de personnages et ne constitue pas une authentification civile. Pour exiger un compte avant toute écriture, configurer `POPULORUM_REQUIRE_LOGIN=1` dans `.env.local`.

Récupération/révocation locale, sans mot de passe dans les arguments :

```bash
php bin/console app:citizens:recover IDENTIFIANT --revoke
php bin/console app:citizens:recover IDENTIFIANT --password-stdin
```

La seconde commande lit le secret sur l’entrée standard ; l’opérateur doit vérifier indépendamment le demandeur. Aucun secret ni justificatif civil ne doit être envoyé dans le journal public. Les accès Guardian restent indépendants. Pour une ouverture réelle, MFA citoyenne, notifications de sécurité et procédures d’identité doivent faire l’objet d’un déploiement et d’un audit dédiés ; cette simulation ne vérifie pas une identité civile.

## Messages et reprise

```bash
php bin/console messenger:setup-transports --env=demo
php bin/console app:events:dispatch --env=demo
php bin/console messenger:consume audit --time-limit=3600 --memory-limit=128M --env=demo
php bin/console messenger:failed:show --env=demo
```

Planifier le dispatcher et superviser le consommateur. L’outbox est écrite avec la mutation ; elle peut être retransmise après interruption. Les reçus d’événements garantissent l’idempotence du consommateur. Une erreur d’intégrité bloque le traitement ; les échecs persistants vont dans la file `failed`, sans modifier le décompte. Les diagnostics publics ne collectent ni messages, ni tokens de sécurité, ni journaux privés.

## Sauvegarder et restaurer

```bash
mkdir -p var/backups var/private
php bin/console app:backup create var/backups/ --key-file=var/private/backup.key
php bin/console app:backup restore var/backups/FICHIER.json --key-file=var/private/backup.key --destination=var/restored.sqlite
```

La clé indépendante de 32 octets est créée si elle n’existe pas. Conserver une copie de la clé séparément des archives, sur un support protégé ; perdre la clé rend la restauration impossible. AES-256-GCM authentifie l’archive compressée. Le snapshot utilise `VACUUM INTO` pour sa cohérence SQLite/WAL. La restauration refuse toute destination existante, contrôle SQLite et les signatures de chaque simulation, puis écrit le nouveau fichier. Valider le décompte et les comptes sur cette copie avant tout basculement manuel. Ne jamais remplacer une base en service sans arrêt, sauvegarde et plan de retour arrière.

Les unités systemd fournies sont des exemples à adapter aux chemins/comptes réels ; elles ne sont pas activées automatiquement. Un timer journalier ne prouve pas une disponibilité de 99,5 %. Pour le pilote, objectifs à confirmer : perte maximale de 24 h, reprise en 4 h ; un exercice opérationnel sur l’hébergement réel doit les valider. Les tests locaux de restauration ne remplacent pas cet exercice.

## Copies publiques et agent volontaire

Après une première mutation signée du laboratoire choisi :

```bash
php bin/console app:replica publish --workspace=shared-demo
```

La publication est explicite et expose `/api/v1/replica`. Vérifier l’empreinte d’autorité par un canal indépendant avant de la copier sur un relais. Le protocole v1 porte une enveloppe signée, un état public signé et son point de contrôle. Les copies anciennes, signatures manquantes, changements de clé, branches divergentes et données privées connues sont rejetés. Une autorité épinglée n’est pas un consensus distribué ; les relais ne deviennent pas des autorités d’éligibilité ou de vote.

Copier `config/agent.example.json` dans un emplacement privé et adapter URL HTTPS, répertoire, empreinte, consentement, volume et débit. Il est désactivé par défaut. Pour une synchronisation explicitement consentie :

```bash
php bin/console app:replica sync --config=/CHEMIN/PRIVE/config.json
```

Les limites sont applicatives : plafond moyen, volume mensuel, taille de copie, plage UTC, signalement de connexion limitée et indicateurs matériels. Le réglage conservateur de 10 % évite de prétendre détecter l’inactivité d’une personne ; aucun historique de navigation n’est observé. Batterie et température Linux sont examinées localement lorsque des capteurs utilisables existent ; les paramètres manuels permettent une suspension conservatrice sur les autres plateformes. Pour l’arrêt, créer `STOP` dans le répertoire de copie ou passer `enabled` à false. Un processus déjà bloqué dans une lecture réseau peut attendre son délai réseau de 10 s ; `systemctl stop populorum-agent.service` arrête immédiatement le processus supervisé. La dernière copie saine n’est remplacée qu’après contrôle complet et par renommage atomique. Le volume reçu, y compris lors d’un échec, reste compté.

Le timer facultatif relance la synchronisation, permettant une reprise après déconnexion. Ne pas installer un service ni ouvrir un port automatiquement. Pas de réplication de données politiques personnelles réelles tant que les droits et responsabilités juridiques n’ont pas été établis.

## Observabilité et limites

Journaux privés tournants `var/log/application.log` et `var/log/security.log`, statut d’agent, reçus d’événements et alertes métier sont distincts. La surveillance externe de disponibilité, stockage, sauvegardes et incidents relève de l’hébergement cible. Le profiler partagé est un diagnostic de démonstration, jamais un coffre d’informations confidentielles.

## Parcours et données d’exemple

Les nouvelles sessions reçoivent 300 personnages fictifs. Les sessions existantes sont conservées ; le bouton « Ouvrir un nouveau laboratoire » dans Participer crée un nouvel espace isolé. Le journal initial est signé avec une clé propre à cet espace, générée côté serveur. Le sélecteur de personnages concerne uniquement les espaces anonymes de simulation.

Sur Linux, le serveur intégré de PHP doit disposer de plusieurs travailleurs pour tester le SSE sans bloquer les navigations : `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:5412 -t public`. La production utilise PHP-FPM ou un serveur adapté, jamais ce serveur de développement. Les sessions sont stockées sous `var/sessions/<environnement>` ; aucune dépendance à un répertoire système PHP préexistant.

## Profils narratifs et millésime 0.4

La fixture par défaut est `data/scenario.json.gz`, décompressée en mémoire à la création d’un laboratoire. `php tools/build-municipal-fixtures.php` la reconstruit, écrit les 2 700 dossiers complets dans `data/citizen-deliberations.ndjson.gz` et produit une copie JSON développée locale. PHP doit fournir zlib. Aucun changement de schéma SQL n’est nécessaire pour ce lot ; le snapshot et les projections incluent les nouveaux attributs JSON.

Les espaces anciens restent conservés. Créer un nouveau laboratoire depuis Participer pour disposer des profils ; ne pas écraser une base existante. Une évolution du modèle devra conserver son ancienne version pour que les anciennes empreintes restent vérifiables. Les détails sont dans [FICTIONAL_MINDS.md](FICTIONAL_MINDS.md).

## Millésime 0.5 : suffrage familial

Ouvrir un laboratoire neuf pour les familles fictives et les nouvelles règles de consultation. Les espaces précédents restent lisibles avec leur unité de suffrage figée. Ne pas modifier les rattachements d’une consultation existante pour convertir son résultat. Pour un changement de rattachement déclaré, préparer un nouveau millésime ou une nouvelle consultation sous une procédure explicite. Voir [FAMILY_SUFFRAGE.md](FAMILY_SUFFRAGE.md).
