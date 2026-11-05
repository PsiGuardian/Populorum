# Référentiels nationaux de Populorum

**La procédure d'installation complète, en une seule page**, avec toutes les commandes de téléchargement INSEE, marchés publics DECP et statistiques SSMSI, figure dans [LIRE_AVANT_INSTALLATION.md](https://github.com/PsiGuardian/Populorum/blob/main/LIRE_AVANT_INSTALLATION.md).

Les ressources nationales sont facultatives. Le référentiel INSEE est déjà inclus sous forme compressée. L'archive SSMSI reste disponible via [data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026), mais **l'ancienne archive DECP de cette release est en schéma v1 et ne convient plus au code v2**. Il faut reconstruire le nouvel index à partir des [JSON consolidés officiels](https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides), selon le guide principal et [PROCUREMENT_EVIDENCE_V2.md](PROCUREMENT_EVIDENCE_V2.md).

Les bases externes sont conservées dans `data/geography/` et `data/research/`, séparément du fichier des scénarios `var/populorum.sqlite`. **L'installation alpha est neuve : les commandes d'import DECP refusent une base publique déjà existante et n'effectuent aucune migration.** Les montants DECP sont contractuels, non des paiements attestés, et une statistique indisponible ne constitue jamais un zéro.

Ce document ne duplique plus les commandes, afin d'éviter des instructions divergentes d'une version à l'autre.
