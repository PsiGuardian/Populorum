# Archives nationales DECP — installer, vérifier et publier

Cette fonctionnalité est **facultative** : Populorum fonctionne avec les 34 875 communes INSEE, sans les ~1,7 Go de l'index DECP national. Aucune base réelle n'est téléchargée pendant l'installation de Symfony.

L'index DECP est une **base séparée** de `var/populorum.sqlite` (simulations). Il est consulté en lecture seule. Les montants des contrats ne prouvent pas des paiements effectifs. Les sources mensuelles JSON originales sont utiles pour les réimportations et les preuves documentaires ; SQLite n'en conserve pas les champs complets.

## Distribution actuelle recommandée

Après échec de l'envoi navigateur de l'asset de ~1,7 Go, l'index national est distribué en **trois parties exactes** (`public-procurement.sqlite.part-00`, `-01`, `-02`) accompagnées de `public-procurement.parts.json` et `SHA256SUMS-DECP.txt`. L'outil permanent `tools/decp_release_parts.py` réalise un découpage sans altération et une restauration avec vérifications SHA-256 par partie et globale. Voir [l'installation des données nationales](INSTALLATION_DONNEES_NATIONALES.md), sections 3 et 3 bis. Les archives `.zst.part-0000` restent disponibles pour les anciens formats de sauvegarde avec sources.

## Depuis une archive publique GitHub Releases (ancien format optionnel)

Installer `zstd` avec pacman. Depuis `apps/symfony`, une fois la release de données effectivement publiée :

```bash
sudo pacman -S --needed zstd python
python3 tools/decp_archive.py install --tag data-decp-AAAA-MM-JJ --index-only
```

Retirer `--index-only` pour recevoir **aussi toutes les sources JSON** disponibles dans la release. La commande ne remplace jamais une base locale différente. Elle vérifie SHA-256 des fragments compressés et du fichier reconstitué et le schéma SQLite attendu. Les parties vérifiées sont gardées dans `var/decp-archive-cache/` pour permettre de reprendre un téléchargement.

**Le tag ci-dessus est un exemple, pas une release existante.** Vérifier d'abord les releases publiées : https://github.com/PsiGuardian/Populorum/releases. Aucun tag d'archive de données ne doit être annoncé avant publication effective.

## Recréer l'index national sans archive partagée

Télécharger les fichiers officiels (plusieurs Go ; prévoir espace disque et bande passante) :

```bash
cd apps/symfony
python3 tools/decp_pipeline.py download
python3 tools/decp_pipeline.py import
python3 tools/decp_pipeline.py verify
```

**Avant publication**, stopper le serveur PHP ou PHP-FPM qui lit l'index. Puis, avec une sauvegarde indépendante :

```bash
python3 tools/decp_pipeline.py publish --site-stopped
python3 tools/verify_decp_history.py --db data/research/public-procurement.sqlite
```

Si ce dernier contrôle est partiel, reconstituer l'historique sur une **copie de travail**, avec `tools/backfill_decp_history.py`, puis contrôler et publier cette copie. Ne pas modifier une base en cours de service. En présence d'un index existant, `publish` conserve une sauvegarde avant remplacement.

## Publier l'archive depuis un poste Arch Linux, sans envoi au chat

Sur la machine qui détient la base de 1,9 Go et les fichiers `decp-sources/` :

```bash
sudo pacman -S --needed python zstd github-cli
cd apps/symfony
python3 tools/verify_decp_history.py --db data/research/public-procurement.sqlite
python3 tools/decp_archive.py package \
  --output "$HOME/Archives/Populorum/data-decp-AAAA-MM-JJ"
```

Le générateur :
- réalise un snapshot SQLite cohérent via l'API de sauvegarde, même si la base utilise un WAL ;
- vérifie la correspondance SHA-256 de **chaque** fichier JSON listé dans `imports` ;
- crée des parties `.zst.part-0000` de **256 Mio maximum** ;
- écrit `MANIFEST.json` et `SHA256SUMS.txt` ;
- refuse un dossier de sortie non vide et évite ainsi les mélanges de millésimes.

Prévoir au minimum l'espace supplémentaire nécessaire au snapshot SQLite et aux archives compressées. Ne jamais versionner ces fichiers dans Git.

Pour publier une release distincte du logiciel, après une authentification volontaire dans GitHub CLI :

```bash
gh auth login
TAG=data-decp-AAAA-MM-JJ
DIR="$HOME/Archives/Populorum/$TAG"
gh release create "$TAG" "$DIR"/* \
  --repo PsiGuardian/Populorum \
  --target main \
  --draft \
  --latest=false \
  --title "DECP — archive du AAAA-MM-JJ" \
  --notes "Index SQLite et sources DECP ; fichiers fractionnés, vérifiables avec MANIFEST.json. Montants contractuels, non paiements."
```

**Avant de publier le brouillon**, comparer les assets de la release au contenu exact du dossier et vérifier leurs empreintes. En cas d'interruption, reprendre les assets manquants avec `gh release upload "$TAG" <fichiers> --repo PsiGuardian/Populorum`. Quand tout est présent : `gh release edit "$TAG" --repo PsiGuardian/Populorum --draft=false`.

Les sources originales restent à conserver aussi sur un support indépendant : GitHub Releases n'est pas un programme de préservation pérenne. Documenter leur provenance et la Licence Ouverte avec la date d'importation. Ne pas confondre le nombre de publications avec celui des contrats distincts.

## Dépannage

- **404 release** : le tag indiqué n'existe pas ou la release est encore en brouillon.
- **SHA-256 divergent** : fichier interrompu ou archive altérée ; ne pas installer.
- **Destination existante différente** : sauvegarder/déplacer explicitement l'ancienne base après arrêt des lecteurs ; aucun écrasement implicite.
- **Historique incomplet** : la liste des contrats fonctionne éventuellement, mais ne pas présenter les versions de montants comme exhaustives.
- **Espace insuffisant** : prévoir une copie de 1,9 Go, les fragments compressés et la base décompressée côté destinataire.
