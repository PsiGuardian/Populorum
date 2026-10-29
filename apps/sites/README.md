# Populorum — démonstrateur Sites 0.1.0

**DÉMOS KRATOS — Δῆμος Κράτος — Le pouvoir appartient au peuple.**

> N’impose pas ton chemin. Éclaire le tien, et laisse les conséquences instruire chacun.

Application de simulation fictive issue de [PsiGuardian/Populorum](https://github.com/PsiGuardian/Populorum). Toutes les identités, communes, voix, coûts et observations sont fictifs. Aucun acte officiel, paiement, partage réel de connexion ni consultation politique réelle.

## Périmètre livré

Navigation responsive en français, trois communes fictives, neuf personnages initiaux, création de chemins avec douze catégories causales, brouillons et publication, versions, adaptations territoriales, consultations à texte et règles figés, admissibilité figée, consentement nominatif fictif, trois choix de vote et changements sans double comptage, clôture, résultats recalculables, protocoles, observations sourcées, imprévus, bilans, comparateur de thèmes sans classement de personnes, quatre scénarios pédagogiques de convergence, mémoire, écritures financières fictives, contributions, propositions de gouvernance à avis indépendants, signalements et recours, relais virtuels, journal chaîné SHA-256 signé ECDSA P-256, empreinte de l’état courant, exports et vérificateur indépendant. La charte est disponible dans les 28 locales du dépôt.

Les données sont conservées côté serveur dans D1, dans un espace distinct par compte ChatGPT. Le nom et l’adresse e-mail du compte ne sont jamais copiés dans les personnages ni exportés. La connexion prouve l’accès à cet espace ; elle ne vérifie pas une identité civique. Le mode visiteur peut lire le jeu pédagogique, sans écrire.

## Écart de plateforme

Cette application utilise React/Vinext et Cloudflare Workers/D1, compatibles avec Sites. **Elle ne constitue pas l’implémentation Symfony 8.1/PHP/PostgreSQL du cahier de référence.** Les documents fondateurs restent inchangés. Le référentiel national INSEE, le réseau pair à pair réel, l’agent de bande passante, les procédures juridiques, l’exploitation à grande échelle et les audits complets ne sont pas déclarés livrés.

Voir [la baseline](docs/development/BASELINE.md), [la recette des 68 scénarios](docs/development/ACCEPTANCE.md), [le protocole API](docs/development/API.md) et [les résultats de vérification](docs/development/VALIDATION.md).

## Exécution

Node.js >= 22.13, pnpm selon `packageManager` du package.json. Les tests utilisent les modules VM et SQLite intégrés à Node, expérimentaux selon la version.

```sh
pnpm install --frozen-lockfile
pnpm test
pnpm typecheck
pnpm build
```

Pour le cycle de vie hébergé, suivre la compétence Sites. La migration `drizzle/0000_previous_professor_monster.sql` crée la table `workspaces`. Les migrations hébergées sont appliquées par Sites.

Aperçu local hors environnement géré :

```sh
pnpm build
node --import ./scripts/sites-env.mjs ./node_modules/wrangler/bin/wrangler.js d1 execute DB --local --config dist/server/wrangler.json --persist-to .wrangler/state --file drizzle/0000_previous_professor_monster.sql
pnpm dev
```

Le profil portable fourni par Sites propose la connexion de développement sur `/signin-with-chatgpt?return_to=/`. Cette simulation d’authentification n’est pas intégrée aux versions de production.

## Vérification hors ligne

Dans Transparence, exporter les preuves ; puis :

```sh
node tools/verify-export.mjs populorum-preuves.json
```

Dans Consultations, exporter une consultation ; puis :

```sh
node tools/recount.mjs consultation-c1.json
```

Un journal signé n’atteste ni la vérité de ses faits, ni une identité civile, ni l’absence de compromission de l’opérateur. Le premier état pédagogique est non signé ; toute opération suivante contrôle et engage l’état courant. Les clés de cette simulation sont gérées par l’opérateur et ne sont pas des signatures individuelles des citoyens.

## Licence

Le code nouvellement développé dans ce dossier est distribué sous licence MIT, décrite dans `LICENSE`. Les textes fondateurs et traductions issus du dépôt conservent leur statut documentaire d’origine ; cette licence ne leur attribue pas une nouvelle licence. Les dépendances conservent leurs licences propres.
