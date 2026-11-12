# Populorum v0.14.0-clean — référentiel BOAMP national et concordance locale

## Architecture nationale

La plateforme n'a plus besoin d'interroger BOAMP une fois par contrat
DECP pour effectuer une analyse de masse. Deux nouveaux outils
**maintenus** travaillent avec l'API d'export officielle et SQLite :

- `tools/import_boamp_national.py` : téléchargement en streaming du
  JSONL national, en conservant source, version SHA-256, historique,
  annonce antérieure, identifiants et JSON compressé ;
- `tools/match_boamp_national.py` : rapprochement local par SIRET
  cité, référence et objet, en lots avec reprise. Il n'effectue
  **aucun appel réseau** et n'écrit jamais dans les DECP ;
- Les fichiers `public-procurement.sqlite`, `boamp-national.sqlite`,
  et `procurement-auto-concordance.sqlite` restent indépendants ;
- Les fiches web lisent automatiquement les résultats locaux après
  leur calcul, tout en conservant les sources des anciennes recherches ;
- Les plafonds, factures et paiements ne sont jamais inférés de BOAMP.

## Périmètre et limites explicites

Le moteur traite les contrats DECP avec SIRET à quatorze chiffres et
identifiant exploitable. Un numéro de SIRET trouvé dans une annonce
peut correspondre à un opérateur économique, non à l'acheteur.
Les objets proches sont donc des **pistes**, pas des preuves du lot.

Quand une organisation dépasse le nombre de notices inspectables
par traitement, la correspondance est marquée **partielle** et
aucune absence de lien n'est affirmée.

Les dates aberrantes de la base DECP sont conservées à l'identique
et ne suffisent pas à écarter une correspondance.

La lecture de fichiers nationaux de grande taille nécessite du
stockage et du temps disque. L'import affiche sa progression et
réserve de l'espace disponible avant de poursuivre.

## Premier essai puis import national

Depuis `apps/symfony` :

```bash
python3 tools/import_boamp_national.py --download --probe
python3 tools/import_boamp_national.py --download --since 2024-01-01
python3 tools/import_boamp_national.py --stats

python3 tools/match_boamp_national.py --limit 25 --offset 0
python3 tools/match_boamp_national.py --resume --limit 1000
```

Pour importer tous les avis historiques accessibles, remplacer
`--since 2024-01-01` par `--full`, après vérification de l'espace disque.

Les mises à jour suivantes peuvent employer un export de fenêtre
glissante sans effacer l'historique.

## Mise à jour d'une installation existante

Les deux scripts, le service
`src/Service/PublicProcurementData.php` et le partial
`templates/partials/procurement-dossier.html.twig` sont les seuls
fichiers de production modifiés. **Aucune migration DECP ni
réinstallation Symfony n'est requise.** Les bases BOAMP et concordance
sont créées séparément ; les anciennes données locales sont préservées.

Guide complet : [BOAMP_NATIONAL_LOCAL.md](../apps/symfony/docs/BOAMP_NATIONAL_LOCAL.md).

## Tests

- Flux JSONL réel vérifié sur l'API DILA (avis BOAMP ciblé).
- Filtre d'export `dateparution` vérifié contre l'API réelle.
- Rapprochement d'une notice officielle avec un exemple DECP local
  en CI, sans réimport de la base nationale.
- Tests d'idempotence, de versions, de lots distincts, de données
  financières non additionnées et de réconciliation hors ligne.
- Suite Symfony + Twig + installation Arch.
