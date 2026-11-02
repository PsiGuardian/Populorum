# Populorum v0.10.1-clean — installation rapide de la base nationale DECP

Le guide unique d'installation conserve la vérification SHA-256 de l'archive officielle DECP et le contrôle des tables et du schéma SQLite, mais ne lance plus `PRAGMA quick_check` sur les 1,84 Go pendant l'installation. L'exécution de `python3 tools/decp_pipeline.py status` est désormais aussi légère, et n'effectue plus de scan intégral, de sorte que le temps gagné n'est pas perdu à l'étape suivante. Le contrôle intégral est toujours disponible sur demande. Les données existantes et les contrôles des archives demeurent protégés, et le guide explique la reprise d'une base déjà extraite.

# Populorum v0.10.0-clean

La notice d'installation complète est disponible sans quitter la page de release : le workflow publie exactement `LIRE_AVANT_INSTALLATION.md` comme description de la release. Elle comprend les téléchargements facultatifs INSEE, DECP et SSMSI, leur vérification et le démarrage local.
