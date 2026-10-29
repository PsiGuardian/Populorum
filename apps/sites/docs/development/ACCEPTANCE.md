# Recette des 68 scénarios du cahier des charges

Date : 29 octobre 2026. La suite de 79 tests est distincte de cette matrice : elle ne signifie pas que les 68 exigences de référence sont intégralement validées. Les tests moteur/API utilisent des identités fictives, WebCrypto réel et SQLite en mémoire avec la migration D1. La frontière d’identité Sites est simulée dans les tests API.

| Cas | Exigence | Couverture à cette baseline | Preuve / limite |
| --- | --- | --- | --- |
| DK-001 | Importer le référentiel INSEE 2026 et contrôler l'intégrité des identifiants territoriaux. | Non exécuté | Référentiel INSEE national non importé. |
| DK-002 | Vérifier le bon rattachement d'une commune. | Partiel | Rattachements fictifs uniquement ; COG officiel absent. |
| DK-003 | Importer un nouveau millésime en préservant l'historique. | Non exécuté | Import multi-millésimes non implémenté. |
| DK-004 | Gérer arrondissements municipaux et communes déléguées. | Non exécuté | Statuts administratifs particuliers non implémentés. |
| DK-005 | Empêcher l'application automatique ailleurs d'une proposition locale. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-006 | Permettre une reprise volontaire d'une proposition par une autre commune. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-007 | Justifier et historiser une exclusion ou adaptation territoriale. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-008 | Empêcher qu'un changement de commune permette de voter deux fois. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-009 | Créer un citoyen fictif avec identité civique unique. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-010 | Refuser une identité dupliquée dans une consultation. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-011 | Autoriser la lecture publique sans connexion. | Partiel — interface | Lecture anonyme observée dans l’aperçu. |
| DK-012 | Empêcher le vote d'un visiteur non admissible. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-013 | Vérifier que l'administration technique ne confère aucune voix supplémentaire. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-014 | Contrôler le consentement à la participation nominative expérimentale. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-015 | Vérifier rectification et effacement selon le droit applicable. | Partiel | Effacement du nom fictif uniquement ; droits réels hors périmètre. |
| DK-016 | Exclure toute donnée secrète et tout justificatif privé des exports publics. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-017 | Créer un chemin autonome. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-018 | Enregistrer les douze catégories de l'arbre causal. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-019 | Distinguer hypothèse et cause observée. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-020 | Empêcher l'ouverture au vote d'une proposition incomplète. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-021 | Attacher une source à une observation. | Partiel | Source exigée pour les observations, pas de gestion documentaire complète. |
| DK-022 | Réviser un chemin en préservant les versions accessibles légalement. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-023 | Afficher des chemins indépendants sans classement de valeur des personnes. | Partiel | Aucun classement de personne ; comparateur thématique élémentaire. |
| DK-024 | Enregistrer une conséquence imprévue avec sa date. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-025 | Ouvrir une consultation avec texte et règles figés. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-026 | Enregistrer une voix POUR. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-027 | Enregistrer une voix CONTRE. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-028 | Enregistrer une ABSTENTION. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-029 | Refuser une seconde voix active du même citoyen. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-030 | Autoriser le changement de vote prévu sans double comptage. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-031 | Refuser tout vote après clôture. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-032 | Refuser une modification silencieuse du texte soumis au vote. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-033 | Vérifier les calculs de majorité et de quorum. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-034 | Gérer une consultation sans voix sans erreur de division. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-035 | Afficher les résultats actualisés et leur caractère provisoire. | Partiel — interface | Résultats provisoires affichés, sondage 2,5 s ; latence bout en bout non mesurée. |
| DK-036 | Recalculer indépendamment un décompte à l'identique. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-037 | Lier une expérience à la bonne consultation clôturée. | Partiel | Protocole rattaché et adoption vérifiée ; protocole complet à approfondir. |
| DK-038 | Définir les indicateurs avant l'observation. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-039 | Ajouter des résultats simulés explicitement étiquetés. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-040 | Distinguer prévision, observation et interprétation causale. | Partiel | Hypothèses et observations simulées distinguées ; bilan causal manuel. |
| DK-041 | Repérer une convergence partielle de chemins. | Partiel | Intersection de thèmes testée ; pas de mesure empirique de convergence. |
| DK-042 | Présenter une divergence persistante sans la qualifier d'échec personnel. | Partiel | Absence de proximité testée ; scénarios visuels pédagogiques. |
| DK-043 | Retracer la révision d'un chemin après une observation. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-044 | Produire un bilan territorial avec sources et périodes. | Partiel | Export de bilan et période présents ; rapport territorial complet à développer. |
| DK-045 | Rejeter la modification d'un vote par un utilisateur non autorisé. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-046 | Détecter la modification d'un contenu après empreinte. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-047 | Détecter la rupture d'une chaîne de journaux. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-048 | Refuser le rejeu d'une opération critique. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-049 | Tester les protections contre les injections et le XSS. | Partiel | Requête SQL paramétrée testée avec charge hostile ; audit XSS complet non réalisé. |
| DK-050 | Tester les contrôles d'accès sur toutes les API sensibles. | Partiel | Anonymat, origine et séparation des comptes testés ; audit exhaustif non réalisé. |
| DK-051 | Créer une alerte d'intégrité depuis un signalement. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-052 | Empêcher qu'un signalement isolé suspende automatiquement un innocent. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-053 | Synchroniser deux copies publiques compatibles. | Partiel | Points de contrôle virtuels uniquement, pas deux serveurs indépendants. |
| DK-054 | Détecter une divergence entre deux nœuds. | Partiel | Comparaison de checkpoints virtuels ; protocole réseau absent. |
| DK-055 | Refuser un événement sans signature lorsque celle-ci est obligatoire. | Partiel | Signatures d’événements vérifiées ; protocole de réception réseau absent. |
| DK-056 | Reprendre la synchronisation après déconnexion. | Partiel | Resynchronisation virtuelle seulement ; panne réseau non testée. |
| DK-057 | Respecter les plafonds configurés de bande passante. | Partiel | Validation des paramètres seulement ; aucun débit réseau effectif. |
| DK-058 | Arrêter immédiatement le relais volontaire sur demande. | Partiel | Arrêt du relais virtuel testé ; agent réel absent. |
| DK-059 | Empêcher la transmission de données privées non autorisées à un nœud public. | Partiel | Exports sans clés testés ; réplication réseau réelle absente. |
| DK-060 | Restaurer un nœud vérificateur depuis une copie saine. | Partiel | Aucune restauration de nœud réseau réalisée. |
| DK-061 | Utiliser les opérations essentielles au clavier. | Partiel — interface | Ouverture/fermeture du dialogue au clavier vérifiées ; audit clavier exhaustif non réalisé. |
| DK-062 | Fournir une alternative textuelle à l'arbre causal. | Partiel — interface | Catégories accessibles sous forme de liste textuelle. |
| DK-063 | Consulter l'explication simple et le glossaire. | Partiel — interface | Glossaire et lecture simple présents ; revue cognitive à poursuivre. |
| DK-064 | Vérifier qu'une traduction n'altère pas le texte de référence soumis au vote. | Partiel — interface | Charte anglaise affichée ; version française figée du vote inchangée. |
| DK-065 | Enregistrer une dépense avec montant et justificatif cohérents. | Réussi dans le périmètre fictif | Test métier exécuté dans tests/domain.test.mjs. |
| DK-066 | Consulter les ressources et coûts publiables. | Partiel — interface | Finances fictives visibles ; coûts réels d’exploitation non renseignés. |
| DK-067 | Exiger la procédure de gouvernance pour modifier les règles démocratiques. | Partiel | Deux avis indépendants simulés ; aucune modification réelle de règles activée. |
| DK-068 | Exercer un recours contre une mesure de modération ou une sanction. | Partiel | Dépôt de recours audité ; instruction humaine et sanction réversible à développer. |
