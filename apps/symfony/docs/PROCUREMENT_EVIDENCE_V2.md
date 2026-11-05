# Marchés publics — DECP v2 et preuves documentaires

**Statut : alpha, reconstruction obligatoire.** L'ancien index `populorum-decp-1`
n'est pas réutilisé. Aucun mécanisme de compatibilité applicative n'est maintenu.

## Principe et sources vérifiables

Sources publiques officielles :
- DECP — [fichiers consolidés](https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides)
- BOAMP / DILA — [données ouvertes et API](https://www.boamp.fr/pages/donnees-ouvertes-et-api/)
- TED — [API de recherche officielle](https://docs.ted.europa.eu/api/latest/search.html)
- Les délibérations, CCTP, CCAP, DCE, actes et documents financiers doivent
  être rattachés par **URL réelle du document précis**, après examen.

Le montant d'un marché n'est ni un paiement comptable ni une preuve du coût final.
Les montants d'accords-cadres peuvent être des plafonds ; les publications
successives ne doivent pas être additionnées.

## Identité conservatrice et archives originales

La clé v2 distingue notamment acheteur, identifiant publié, CPV, intitulé,
date de notification et titulaires publiés. Elle **empêche de fusionner**
des lots décrits séparément avec un identifiant DECP commun. Elle n'est pas un
identifiant juridique du lot et peut distinguer deux versions du même contrat :
la fusion ultérieure requiert des documents concordants.

La table `contract_records` contient pour chaque objet source :
- fichier source par SHA-256, nature de l'objet, numéro d'ordre dans le fichier ;
- SHA-256 de l'objet original canonisé ;
- déclaration source complète compressée (zlib).

La table `contract_publications` garde les séries de valeurs déclarées par
fichier, sans transformer ces valeurs en paiements. Aucun contenu original n'est
supprimé durant une modification d'un marché.

## Reconstruction sûre

Depuis `apps/symfony`, si les JSON sont déjà dans
`data/research/decp-sources/` :

```bash
python3 tools/decp_pipeline.py import
python3 tools/decp_pipeline.py verify
```

Si les sources doivent être (re)téléchargées ou si leurs liens directs sont
inconnus, exécuter d'abord :

```bash
python3 tools/decp_pipeline.py download
```

Le téléchargement crée `resource-catalog.json` avec les URL **réelles**
fournies par l'API data.gouv.fr et `resource-catalog-meta.json` avec la révision
catalogue, la taille et le SHA-256 de chaque fichier. À chaque actualisation,
le téléchargeur ne réutilise une ressource que si sa révision et son empreinte
concordent ; un fichier republié avec la même taille est rechargé. Les
publications sources précédentes restent dans l'index jusqu'à reconstruction
et publication contrôlée. Si le manifeste n'existe pas, l'interface
indique que l'URL exacte est inconnue et renvoie au catalogue DECP, sans prétendre
ouvrir le fichier source directement.

Après vérification et arrêt du serveur Symfony :

```bash
python3 tools/decp_pipeline.py publish --site-stopped
```

Une sauvegarde de l'ancienne base publique est créée avant remplacement ;
la base de production n'est jamais modifiée pendant l'importation.

## Ajouter une justification sourcée

L'ajout documentaire est **hors HTTP**, en processus d'importation contrôlé.
Le fichier JSON doit contenir une liste de déclarations, avec les clés :

```json
[
  {
    "contract_key": "CLE_SHA1_DE_40_CARACTERES_ISSUE_DE_L_INDEX",
    "category": "purpose",
    "source_type": "deliberation",
    "source_name": "Nom exact de l'organisme publiant",
    "source_url": "https://DOMAINE_OFFICIEL/CHEMIN/DU/DOCUMENT",
    "document_title": "Titre du document",
    "statement": "Motif explicitement établi par le document",
    "excerpt": "Extrait court du document étayant directement ce motif",
    "published_at": "2024-02-01",
    "status": "documented"
  }
]
```

Remplacer les exemples par des documents **réels et vérifiés** : ces URLs et
affirmations illustratives ne sont pas des données à importer. Les catégories
sont `context`, `purpose`, `scope`, `selection`, `funding`, `payment`,
`outcome`. Le statut `candidate` exige `statement` et `excerpt` vides.
Aucune URL non HTTPS, privée, locale ou sans hôte valide n'est admise.

```bash
python3 tools/import_procurement_evidence.py /chemin/justificatifs.json \
  --db data/research/public-procurement-v2-staging.sqlite
```

Il faut ajouter les références **avant** la commande `publish`.
Le site présente toutes les preuves avec auteur institutionnel, titre, extrait,
date et URL du document. Les faits non documentés affichent explicitement
« Justification documentaire non disponible ».

## Première fiche réelle de validation

Une première pièce sourcée est conservée dans
`data/curated-procurement/vimartin-2023-lot6.json` : procès-verbal du conseil
municipal de Vimartin-sur-Orthe du 20 février 2023, republié par Pappers
Politique à l'URL exacte du document. Le procès-verbal mentionne la relance du
lot 6 de l'école communale après une consultation infructueuse. Cette
justification est importée seulement si le contrat DECP correspondant est
présent dans la reconstruction. Elle ne prouve aucun paiement ni résultat des
travaux. La provenance secondaire de l'hébergement est affichée sans la
présenter comme une page officielle de la mairie.

## Découverte des avis BOAMP, sans automatisation dangereuse

La commande suivante interroge l'API publique BOAMP de façon limitée et ne
stocke que des avis **candidats**, jamais une interprétation des motifs :

```bash
python3 tools/enrich_procurement_boamp.py --db data/research/public-procurement-v2-staging.sqlite \
  --limit 10 --pause 1
```

Le modèle BOAMP utilisé par le client s'appuie sur les champs `idweb`,
`objet`, `nomacheteur`, `dateparution`, `url_avis` documentés par la DILA.
La disponibilité opérationnelle de l'API et les correspondances individuelles
doivent être vérifiées sur les données réellement retournées.

Une correspondance textuelle ne confirme **ni** que l'avis concerne le bon lot
**ni** qu'un acte d'achat ou paiement a eu lieu. Aucun scraping n'est lancé dans
une requête Symfony.

## Contrôles et sécurité

```bash
python3 tests/decp_v2_evidence_test.py
python3 tests/decp_pipeline_test.py
python3 tests/decp_history_test.py
python3 tests/decp_sql_inputs_test.py
python3 tests/decp_archive_test.py
```

Les requêtes SQL utilisent des paramètres liés ; la lecture du JSON source
est bornée lors de l'affichage. Les identifiants externes ne sont jamais
considérés comme des autorisations d'accès. Les URL de documents sont contrôlées
et le rendu HTML/Twig garde l'échappement automatique.

Les données publiées n'impliquent aucune conclusion sur la régularité d'un
marché. Les informations techniques sensibles et les secrets protégés par la
loi ne doivent pas être importés dans les fiches publiques.
