# Audit de couverture du cahier des charges

Date : 29 octobre 2026. Application examinée : `apps/symfony`, branche `development/symfony-sqlite`, code métier du commit GitHub `663e7327af56b319db8bc03551e1212da0a80a0d`. Le présent ajout ne corrige pas les fonctionnalités : il rend les écarts et leurs preuves consultables.

**Verdict : le cahier des charges complet n’est pas implémenté.** Le socle Symfony fonctionne, mais un écran présent ou une méthode existante ne suffit pas à valider une exigence. Aucune conformité globale ni capacité de production n’est déclarée.

Références : [cahier des charges français](../../../docs/specifications/REQUIREMENTS.fr.md), [charte](../../../CHARTER.md), [baseline](BASELINE.md), [validation précédente](VALIDATION.md).

## Périmètre et méthode

Lecture des 105 rubriques (dont la recette du §91 et ses conditions §91 bis), des 68 scénarios DK-001 à DK-068, des 24 mutations du moteur, du contrôleur HTTP, des formulaires, templates, entités, migrations, configuration et outils de vérification. Nouvelle exécution de `tests/integration.php` : **99 contrôles réussis, zéro échec**. Exécution de [12 sondes complémentaires](../tests/requirements-audit.php) : **5 satisfaites, 7 écarts**, résultats bruts dans [requirements-audit-results.json](requirements-audit-results.json).

Les sondes opèrent sur des copies en mémoire et un export temporaire. Elles n’altèrent pas une base de visiteur et ne contiennent que des données fictives. La commande d’audit retourne volontairement 1 tant qu’une sonde n’est pas satisfaite ; elle est distincte du contrôle de non-régression de la CI. Les deux ensembles ne sont pas additionnés pour annoncer un taux de conformité.

Limites : pas de navigateur, parcours clavier, lecteur d’écran, infrastructure publique, agent réseau, charge, restauration, pentest exhaustif ou examen juridique exécuté. L’état du code est celui de cette branche ; `main` n’a pas été fusionnée. Les preuves externes et références réglementaires ne sont pas réauditées ici.

**Dérogations demandées par l’utilisateur :** SQLite en fichier à la place de PostgreSQL pour l’instant ; exactement Paris, Marseille et Le Havre. L’import national est différé ; son absence n’est pas traitée comme un défaut de cette étape. L’historisation territoriale, les graphes et la fédération restent des exigences du projet complet.

Les états de la matrice sont distincts : « Validé dans la démo » correspond à un comportement fictif exécuté dans le périmètre décrit, « Partiel » à une réalisation incomplète, « Absent » à une fonction sans moteur effectif, « Non vérifié » à une recette non exécutée, « Différé » à la restriction territoriale acceptée. Un scénario peut être validé ici sans satisfaire toutes les exigences voisines de sa rubrique.

## Écarts reproduits et priorités

| Sonde | Constat observé | Portée et suite proposée |
| --- | --- | --- |
| AUD-01 | Création avec titre et commune seulement rejetée : texte de 4 caractères requis pour tous les champs. | §21 : autoriser un vrai brouillon incomplet ; réserver la validation complète à publication/ouverture. |
| AUD-02 | Un treizième type de nœud est ignoré ; le moteur reconstruit douze catégories fixes. | §16 : typologie extensible et migration compatible. |
| AUD-03 | Une relation causale envoyée à la création n’est pas conservée (`edges=[]`). | §18 : persister/valider les relations typées et leurs contraintes, puis fournir l’éditeur. |
| AUD-04 | Révision de `problem` et `cause` sans effet ; seuls résumé, solution, risque et nœuds sont révisables. | §19 : versionner tous les champs significatifs, sources comprises ; éviter les champs ignorés silencieusement. |
| AUD-05 | Après rectification puis effacement, l’ancien nom subsiste dans `events[].payload.changes`. | Priorité haute avant toute donnée personnelle réelle : séparer contenu effaçable et preuves, définir une expurgation vérifiable sans casser arbitrairement les signatures. Aujourd’hui les personnages sont fictifs. |
| AUD-06 | Un deuxième bilan remplace `experiment.evaluation` sans historique dédié de bilans. | L’événement signé conserve la première saisie : ce n’est pas une disparition de toute trace. Il manque une révision explicite et consultable des bilans, ou un refus de réévaluation. |
| AUD-07 | Une consultation dont l’échéance est passée reste `open` et `provisional=true` jusqu’à clôture manuelle. | Les votes tardifs sont bien refusés. Clarifier le statut « échue, en attente de proclamation » et l’automatisation éventuelle ; le cahier ne dit pas qu’un résultat doit être proclamé automatiquement à la seconde d’échéance. |

Autre écart par lecture du code : l’export d’une expérience associe l’expérience à la **proposition courante** via `proposalMap`, et non explicitement au snapshot de la consultation. Après révision du chemin, l’export peut mélanger protocole ancien et texte nouveau. Le snapshot du vote, lui, reste bien conservé (AUD-12). Ajouter version/snapshot explicites à l’expérience et à son export ; reproduire le parcours HTTP complet dans la prochaine correction.

La convergence calcule une intersection de tags et l’égalité du nom de l’indicateur. Les quatre scénarios du §90 sont des textes pédagogiques, pas quatre moteurs de simulation exécutés. L’infrastructure configure des nœuds virtuels et mémorise des empreintes ; elle ne transfère aucune copie réseau et ne peut démontrer débit réel, reprise ou résilience.

## Matrice des 68 scénarios

| État | Nombre |
| --- | ---: |
| Validé dans la démo | 31 |
| Partiel | 28 |
| Absent | 4 |
| Non vérifié | 2 |
| Différé par périmètre | 3 |

Ces nombres décrivent les scénarios de recette, pas un pourcentage de code terminé.

| Scénario | Exigence | État | Résultat constaté / limite | Preuve ou point d’entrée |
| --- | --- | --- | --- | --- |
| DK-001 | Importer le référentiel INSEE 2026 et contrôler l'intégrité des identifiants territoriaux. | Différé par périmètre | Trois codes fixés ; import national 2026 absent, périmètre réduit demandé. | Fixtures, DemoDatabase ; tests initiaux. |
| DK-002 | Vérifier le bon rattachement d'une commune. | Partiel | Région et département présents ; comparaison automatisée des rattachements à la source non exécutée. | Municipality, cities.json. |
| DK-003 | Importer un nouveau millésime en préservant l'historique. | Différé par périmètre | Aucun import de millésime ni historique de fusion/rattachement. | Municipality sans entité historique. |
| DK-004 | Gérer arrondissements municipaux et communes déléguées. | Différé par périmètre | Arrondissements et communes déléguées non modélisés à cette étape. | Modèle territorial. |
| DK-005 | Empêcher l'application automatique ailleurs d'une proposition locale. | Validé dans la démo | Vote hors commune rejeté ; reprise locale crée un brouillon sans consultation automatique. | Suite principale ; AUD-09. |
| DK-006 | Permettre une reprise volontaire d'une proposition par une autre commune. | Validé dans la démo | Reprise volontaire par un personnage local et référence originId conservée. | AUD-09. |
| DK-007 | Justifier et historiser une exclusion ou adaptation territoriale. | Partiel | Motif, caractéristique, source, alternative et réexamen présents ; conséquences prévues/observées et révisions de fiche absentes. | adaptProposalToTerritory. |
| DK-008 | Empêcher qu'un changement de commune permette de voter deux fois. | Validé dans la démo | Changement différé lorsqu’une consultation admissible est ouverte. | Suite principale : changement territorial refusé. |
| DK-009 | Créer un citoyen fictif avec identité civique unique. | Validé dans la démo | Création fictive et refus des noms dupliqués ; aucune vérification civile prétendue. | Suite principale. |
| DK-010 | Refuser une identité dupliquée dans une consultation. | Validé dans la démo | Un bulletin actif par identifiant fictif ; choix identique rejeté, changement remplace la voix. | Suite principale. |
| DK-011 | Autoriser la lecture publique sans connexion. | Validé dans la démo | Pages publiques rendues sans connexion Guardian. | Suite principale HttpKernel. |
| DK-012 | Empêcher le vote d'un visiteur non admissible. | Validé dans la démo | Personnage inexistant, hors commune ou sans consentement refusé ; visiteur peut choisir un personnage de sa simulation. | Suite principale ; persona. |
| DK-013 | Vérifier que l'administration technique ne confère aucune voix supplémentaire. | Partiel | Compte Guardian indépendant et absence de branche privilégiée dans le vote ; pas de matrice complète des rôles civiques. | castVote, subscriber Guardian. |
| DK-014 | Contrôler le consentement à la participation nominative expérimentale. | Validé dans la démo | Consentement exigé et retrait bloque une nouvelle participation. | Suite principale. |
| DK-015 | Vérifier rectification et effacement selon le droit applicable. | Partiel | Nom courant effacé mais ancien nom encore exporté dans les événements ; rectification partielle. | AUD-05 en échec ; suite principale. |
| DK-016 | Exclure toute donnée secrète et tout justificatif privé des exports publics. | Partiel | Clé privée, opérations et secrets du collecteur exclus ; pas de modèle complet des justificatifs privés ni de tests de toutes les catégories. | Suite principale ; publicState. |
| DK-017 | Créer un chemin autonome. | Validé dans la démo | Création d’un chemin autonome dans son territoire ; aucun lien de réponse personnelle requis. | AUD-02/03 exécutent createProposal ; inspection de la méthode. |
| DK-018 | Enregistrer les douze catégories de l'arbre causal. | Validé dans la démo | Douze catégories enregistrées, ordre contrôlé ; extension à une treizième non prise en charge. | Suite principale ; AUD-02 pour la limite du §16. |
| DK-019 | Distinguer hypothèse et cause observée. | Validé dans la démo | Nature observation/hypothèse distinguée dans les nœuds. | Suite principale. |
| DK-020 | Empêcher l'ouverture au vote d'une proposition incomplète. | Validé dans la démo | Indicateur manquant empêche l’ouverture. | Suite principale. |
| DK-021 | Attacher une source à une observation. | Validé dans la démo | Source obligatoire pour une observation, absence rejetée. | Suite principale. |
| DK-022 | Réviser un chemin en préservant les versions accessibles légalement. | Partiel | Snapshot antérieur conservé, mais révision limitée à solution/risque/résumé/nœuds. | Suite principale ; AUD-04 en échec. |
| DK-023 | Afficher des chemins indépendants sans classement de valeur des personnes. | Partiel | Interface sans score humain ; absence de parcours navigateur de recette sémantique. | proposals et convergence Twig ; HttpKernel 200. |
| DK-024 | Enregistrer une conséquence imprévue avec sa date. | Validé dans la démo | Imprévu enregistré avec la date d’observation. | Suite principale. |
| DK-025 | Ouvrir une consultation avec texte et règles figés. | Validé dans la démo | Snapshot du texte, version, règles et admissibles à l’ouverture ; snapshot conservé après révision ultérieure. | Suite principale ; AUD-12. |
| DK-026 | Enregistrer une voix POUR. | Validé dans la démo | POUR enregistré et export signé vérifié. | Suite principale. |
| DK-027 | Enregistrer une voix CONTRE. | Validé dans la démo | CONTRE enregistré et export signé vérifié. | Suite principale. |
| DK-028 | Enregistrer une ABSTENTION. | Validé dans la démo | ABSTENTION enregistrée et export signé vérifié. | Suite principale. |
| DK-029 | Refuser une seconde voix active du même citoyen. | Validé dans la démo | Même choix refusé et révision sans deuxième bulletin actif. | Suite principale. |
| DK-030 | Autoriser le changement de vote prévu sans double comptage. | Validé dans la démo | Changement conserve l’historique et le total. | Suite principale. |
| DK-031 | Refuser tout vote après clôture. | Validé dans la démo | Vote après clôture refusé ; échéance aussi contrôlée côté moteur. | Suite principale ; castVote. |
| DK-032 | Refuser une modification silencieuse du texte soumis au vote. | Validé dans la démo | Révision refusée pendant consultation ouverte ; texte figé indépendant ensuite. | Suite principale ; AUD-12. |
| DK-033 | Vérifier les calculs de majorité et de quorum. | Partiel | Majorité, égalité, abstention, quorum calculés ; limites de quorum et tous seuils pas couverts exhaustivement. | Suite principale ; AUD-08. |
| DK-034 | Gérer une consultation sans voix sans erreur de division. | Validé dans la démo | Zéro participant et zéro admissible sans division par zéro. | Suite principale. |
| DK-035 | Afficher les résultats actualisés et leur caractère provisoire. | Partiel | Résultats provisoires au rechargement ; aucun SSE/Mercure ; statut open persiste après échéance sans clôture manuelle. | consultations Twig ; AUD-07. |
| DK-036 | Recalculer indépendamment un décompte à l'identique. | Validé dans la démo | Outil PHP séparé lancé sur export temporaire ; mêmes totaux, exprimés, participation et adoption. | AUD-08. |
| DK-037 | Lier une expérience à la bonne consultation clôturée. | Partiel | Lien et garde close/adoptée implémentés ; création de protocole non exercée par la suite principale ; version exportée à clarifier après révision. | createExperiment ; export experiment. |
| DK-038 | Définir les indicateurs avant l'observation. | Partiel | Indicateur pris du texte figé, protocole avant observation ; recette de création complète non exécutée. | createExperiment, addObservation. |
| DK-039 | Ajouter des résultats simulés explicitement étiquetés. | Validé dans la démo | Valeurs ajoutées avec simulated=true et page rendue en mode simulé. | Suite principale. |
| DK-040 | Distinguer prévision, observation et interprétation causale. | Partiel | Prévision, valeur et incertitude distinctes ; interprétation causale structurée et classification de preuve incomplètes. | Modèle expérience et Twig. |
| DK-041 | Repérer une convergence partielle de chemins. | Partiel | Intersection des tags et indicateur partagé seulement ; aucune convergence empirique calculée. | convergence. |
| DK-042 | Présenter une divergence persistante sans la qualifier d'échec personnel. | Partiel | Texte pédagogique sans jugement présent ; pas de scénario temporel de divergence calculé. | convergence Twig. |
| DK-043 | Retracer la révision d'un chemin après une observation. | Partiel | Réviser après observation est possible manuellement ; pas de lien structuré de justification à l’observation. | reviseProposal. |
| DK-044 | Produire un bilan territorial avec sources et périodes. | Partiel | Bilan par expérience et filtre communal ; pas de synthèse communale complète ni comparaison prévisions/résultats structurée. | experiments Twig, export. |
| DK-045 | Rejeter la modification d'un vote par un utilisateur non autorisé. | Partiel | Acteur HTTP imposé depuis session et admissibilité contrôlée ; sélection de tout personnage permise dans sa simulation, pas d’authentification civique. | DemoController ; castVote. |
| DK-046 | Détecter la modification d'un contenu après empreinte. | Validé dans la démo | Modification de l’état signé détectée et écriture ultérieure refusée. | Suite principale. |
| DK-047 | Détecter la rupture d'une chaîne de journaux. | Validé dans la démo | Référence précédente altérée détectée. | Suite principale. |
| DK-048 | Refuser le rejeu d'une opération critique. | Validé dans la démo | Identifiant déjà utilisé rejeté ; formulaire périmé rejeté par CAS. | Suite principale. |
| DK-049 | Tester les protections contre les injections et le XSS. | Non vérifié | Twig échappe et SQL utilise des paramètres ; tests adversariaux SQLi/XSS non exécutés. | Inspection uniquement, pas de preuve de recette DK-049. |
| DK-050 | Tester les contrôles d'accès sur toutes les API sensibles. | Partiel | CSRF, verrou optimiste et compte Guardian testés ; pas de matrice exhaustive d’autorisation pour toutes les actions. | Suite principale ; routes. |
| DK-051 | Créer une alerte d'intégrité depuis un signalement. | Validé dans la démo | Alerte créée en état to_verify. | AUD-10. |
| DK-052 | Empêcher qu'un signalement isolé suspende automatiquement un innocent. | Validé dans la démo | Signalement ne suspend aucun personnage. | Suite principale. |
| DK-053 | Synchroniser deux copies publiques compatibles. | Absent | Aucun transport entre deux processus/nœuds ; simple empreinte virtuelle. | synchronizeVirtualNode. |
| DK-054 | Détecter une divergence entre deux nœuds. | Partiel | Comparaison de deux empreintes virtuelles affichée, pas de protocole inter-nœuds. | infrastructure Twig. |
| DK-055 | Refuser un événement sans signature lorsque celle-ci est obligatoire. | Partiel | Signature vide rejetée par vérificateur local ; aucun récepteur réseau réel. | AUD-11. |
| DK-056 | Reprendre la synchronisation après déconnexion. | Absent | Aucune reprise de transport après déconnexion. | Aucun agent/transport. |
| DK-057 | Respecter les plafonds configurés de bande passante. | Absent | Débit enregistré comme nombre ; aucun transfert ni mesure de plafond. | configureVirtualNode. |
| DK-058 | Arrêter immédiatement le relais volontaire sur demande. | Partiel | Flag stopped bloque la synchronisation virtuelle ; aucun agent actif à arrêter. | Suite principale. |
| DK-059 | Empêcher la transmission de données privées non autorisées à un nœud public. | Partiel | Exports expurgés ; transmission et autorisation inter-nœuds non testables faute de transport. | publicState. |
| DK-060 | Restaurer un nœud vérificateur depuis une copie saine. | Absent | Aucune restauration d’un nœud depuis copie saine. | Aucun agent/sauvegarde. |
| DK-061 | Utiliser les opérations essentielles au clavier. | Non vérifié | Liens, formulaires, skip link et focus prévus ; parcours clavier/lecteur d’écran non exécuté. | Twig et CSS ; pas d’audit WCAG/RGAA. |
| DK-062 | Fournir une alternative textuelle à l'arbre causal. | Partiel | Douze rubriques textuelles disponibles ; pas de graphe complet dont vérifier l’équivalence accessible. | proposals Twig. |
| DK-063 | Consulter l'explication simple et le glossaire. | Partiel | Résumé courant et glossaire global présents ; glossaire spécifique et reformulations versionnées absents. | citizen et proposals Twig. |
| DK-064 | Vérifier qu'une traduction n'altère pas le texte de référence soumis au vote. | Partiel | 28 chartes documentaires, français de référence ; pas de traductions de propositions soumises au vote ni validation linguistique. | page charter. |
| DK-065 | Enregistrer une dépense avec montant et justificatif cohérents. | Validé dans la démo | Montant converti en centimes, trois décimales rejetées, référence de justificatif obligatoire ; justificatif fictif textuel. | Suite principale. |
| DK-066 | Consulter les ressources et coûts publiables. | Partiel | Page et exports exposent finances/contributions fictives ; comptabilité complète et justificatifs documentaires non réalisés. | transparency, resource.add. |
| DK-067 | Exiger la procédure de gouvernance pour modifier les règles démocratiques. | Partiel | Proposition + deux avis indépendants ; aucun mécanisme d’application de règles versionnées ni verrou de déploiement collégial. | Suite principale ; governance.*. |
| DK-068 | Exercer un recours contre une mesure de modération ou une sanction. | Partiel | Dépôt d’un recours enregistré ; décision de sanction, instruction/réexamen et résolution du recours absents. | Suite principale ; moderation.*. |

## Revue de toutes les rubriques du cahier des charges

Cette table couvre aussi les demandes hors des 68 scénarios. « Documenté » ne signifie pas « implémenté » ; les préalables juridiques sont des livrables à obtenir, sans conclusion juridique ici.

| § | Rubrique | État | Observation |
| --- | --- | --- | --- |
| 1 | Vision générale | Socle présent | Problèmes, causes, solutions et observations fictives ; raisonnement/graphes complets partiels. |
| 2 | Objectif fondamental | Socle présent | Liberté et absence de classement visibles ; convergence limitée aux tags. |
| 3 | Principes non négociables | Partiel | Invariants de vote testés ; indépendance et protection réelles non démontrées. |
| 4 | Positionnement juridique et institutionnel | Documenté | Mention fictive constante ; aucune décision publique légale ni identité civile mise en œuvre. |
| 5 | Référentiel géographique français | Différé/partiel | Trois villes demandées ; pas d’import COG, d’historique ni d’intercommunalités. |
| 6 | Fiche territoriale | Partiel | Codes, sources, régions/départements ; population, statut, mairie et historiques absents. |
| 7 | Autonomie municipale | Présent en démo | Admissibilité locale et adaptation volontaire ; pas d’application automatique. |
| 8 | Fiche d'inapplicabilité territoriale | Partiel | Fiche motivée et auditée ; conséquences et historique spécifique incomplets. |
| 9 | Extension territoriale | À développer | Codes communaux français et structures fixes ; pas de modèle Country/hiérarchie internationale. |
| 10 | Catégories d'utilisateurs | Partiel | Personnages fictifs et Guardian ; pas de permissions pour les dix rôles civiques. |
| 11 | Identité civique | Présent en démo | Choix de personnage explicite ; aucune vérification d’identité réelle. |
| 12 | Compte citoyen | Partiel | Nom, commune, consentement, historique ; préférences linguistiques/accessibilité et sécurité civique absentes. |
| 13 | Droits des participants | Partiel | Export/retrait/rectification ; nom ancien dans journal malgré effacement (AUD-05). |
| 14 | Prévention des identités multiples | Partiel | Unicité d’identifiant et nom fictif ; pas d’anti-Sybil réel ni recours de vérification. |
| 15 | Définition du chemin | Partiel | Chemin incorporé à Proposal ; pas d’entité Pathway autonome ni de graphe riche. |
| 16 | Arbre causal | Partiel | Douze catégories fixes ; extension non conservée (AUD-02). |
| 17 | Catégories complémentaires | Partiel | Risques et réversibilité ; autres catégories complémentaires non structurées. |
| 18 | Liens entre causes et conséquences | Absent | Relations typées ignorées à la création (AUD-03), pas de contrôle des cycles/confondants. |
| 19 | Versionnement | Partiel | Snapshots de proposition ; révision incomplète des champs (AUD-04). |
| 20 | Coexistence sans contradiction personnelle | Partiel | Vocabulaire non conflictuel ; ajouts autonomes de sources/causes et chemins voisins incomplets. |
| 21 | Création d'une proposition | Partiel | Formulaire complet ; brouillon incomplet impossible (AUD-01). |
| 22 | Compréhension linguistique | Partiel | Résumé courant et quatre éléments ; glossaire de proposition et reformulations versionnées absents. |
| 23 | Cycle de vie | Partiel | Quelques états seulement ; retrait, suspension, report, remplacement et archivage non implémentés. |
| 24 | Gel de la proposition soumise au vote | Présent en démo | Snapshot figé et révision interdite pendant ouverture ; AUD-12. |
| 25 | Paramètres d'une consultation | Partiel | Période, règles, admissibles, quorum figés ; annulation/recours/paramétrage complet manquants. |
| 26 | Vote citoyen | Présent en démo | Trois choix, pas de justification imposée au vote. |
| 27 | Modification du vote | Présent en démo | Changement autorisé avant clôture, historique, une voix active. |
| 28 | Résultats en temps réel | Partiel | Rechargement manuel ; pas de flux temps réel, statut après échéance à clarifier (AUD-07). |
| 29 | Calcul des résultats | Partiel | Décompte public et outil indépendant ; correction/annulation formelles non réalisées. |
| 30 | Définition d'une expérience | Présent en démo | Expériences exclusivement fictives ; autres modes non implémentés. |
| 31 | Protocole | Partiel | Objectif/hypothèse/période/indicateur/méthode/arrêt ; sources et facteurs du protocole incomplets. |
| 32 | Conséquences observées | Partiel | Valeurs, source, incertitude, date, facteur externe ; groupes et liens causaux structurés absents. |
| 33 | Bilan prévisionnel et réel | Partiel | Bilan libre et décision ; catégories de comparaison absentes, bilans remplacés (AUD-06). |
| 34 | Réversibilité | Partiel | Réversibilité affichée ; retour arrière opérationnel des expériences non implémenté. |
| 35 | Bilan communal | Partiel | Liste filtrable d’expériences ; pas de synthèse communale complète. |
| 36 | Définition technique | Partiel | Tags communs et indicateur ; pas de rapprochement observé ou temporel. |
| 37 | Carte de convergence | Partiel | Illustration et cartes de comparaison ; pas de graphe de convergence fondé sur les données. |
| 38 | Algorithmes d'analyse | Partiel | Intersection de tags seulement ; séries temporelles, graphes et statistiques non implémentés. |
| 39 | Limites de convergence | Documenté | Avertissement proximité/causalité et absence de jugement ; pas de validation scientifique. |
| 40 | Registre public | Partiel | Journal et exports ; corrections/suspensions/versions techniques structurées incompletes. |
| 41 | Journal d'événements | Partiel | SHA-256, chaîne et signatures ECDSA ; identifiant de version/statut de validation métier à enrichir. |
| 42 | Preuves publiques | Partiel | Point de contrôle signé local ; pas d’ancrage indépendant ni de comparaison réseau réelle. |
| 43 | Vérification indépendante | Partiel | Vérificateur et recompteur PHP ; rapport d’audit étendu et preuves publiques indépendantes manquants. |
| 44 | Alertes d'incohérence | Partiel | Alerte to_verify ; instruction et transitions vers incident confirmé/fraude absentes. |
| 45 | Vision | Préparé seulement | Vision documentée ; réseau réel absent. |
| 46 | Types de nœuds | Partiel | Deux nœuds virtuels ; six rôles et attestations réseau absents. |
| 47 | Partage volontaire de bande passante | Absent | Pas d’agent ni de transfert, donc aucun partage effectif de bande passante. |
| 48 | Contrôle de l'agent volontaire | Absent | Flag/debit virtuels ; pas de volume, horaires, batterie, thermique ni révocation d’agent réel. |
| 49 | Données répliquées | Partiel | Export public expurgé ; aucune réplication réseau ni autorisation de transmission. |
| 50 | Résilience | Non exécuté | Pas de tests de partition, saturation, déconnexion ou corruption inter-nœuds. |
| 51 | Risques spécifiques | Non réalisé | Pas de modèle de menaces réseau complet ni simulation des attaques listées. |
| 52 | Stratégie de déploiement | Phase A partielle | Serveur unique et journal local ; phases B à E non réalisées. |
| 53 | Pile technique | Partiel avec dérogation | Symfony/Doctrine/Twig/Form présents ; SQLite accepté ; Messenger, Translation configuré, SSE/Mercure, Monolog, PHPUnit et PHPStan manquants. |
| 54 | Architecture modulaire | Partiel | Services séparés du contrôleur ; les quatorze domaines restent regroupés dans SimulationEngine. |
| 55 | Principes de programmation | Partiel | Typage, validation, DI, CSRF et CAS ; interfaces métier et autorisations multirôles incomplètes. |
| 56 | Base de données | Dérogation/partiel | SQLite demandé ; deux tables métier et snapshot JSON, pas de graphes normalisés ni restauration. |
| 57 | Entités principales | Partiel | Deux entités Doctrine uniquement, les autres objets sont des structures JSON. |
| 58 | Contraintes d'intégrité | Partiel | Invariants essentiels dans moteur et CAS ; pas de contraintes SQL relationnelles de vote ni cycles causaux. |
| 59 | API | Partiel | GET /api/v1/state et exports ; pas d’API de ressources complète, pagination/quotas/contrat. |
| 60 | Événements métier | Partiel | Événements audit action.* ; pas de bus/consommateurs Messenger idempotents distincts. |
| 61 | Direction artistique | Partiel/non audité | Palette et identité civique en CSS ; variante du rouge et rendu/contrastes non audités. |
| 62 | Page d'accueil | Présent en démo | Accueil, devise, carte illustrative, charte, coûts et liens ; données fictives signalées. |
| 63 | Navigation principale | Partiel | Sections principales accessibles ; espace citoyen simulé, pas d’espace privé authentifié civique. |
| 64 | Carte territoriale | Partiel | Carte SVG illustrative et liste trois villes ; pas de navigation région/département ni fond OSM. |
| 65 | Affichage d'un chemin | Partiel | Douze rubriques textuelles et versions ; pas de graphe/relations/chemins voisins complets. |
| 66 | Tableau de bord de consultation | Partiel | Texte figé, règles, totaux ; arbre et autres chemins pas intégrés au tableau, pas de temps réel. |
| 67 | Mémoire personnelle | Partiel | Journal collectif et export citoyen ; pas de frise personnelle complète. |
| 68 | Accessibilité | Non vérifié | HTML sémantique, labels et clavier prévus ; pas de recette WCAG 2.2 AA/RGAA exécutée. |
| 69 | Langues | Partiel | 28 chartes documentaires ; interface française en dur, pas de catalogues Symfony Translation configurés. |
| 70 | Reformulations linguistiques | Absent | Pas de variantes de propositions identifiées/versionnées ; résumé simple seulement. |
| 71 | Assistance facultative par IA | Facultatif, absent | Aucun service IA ; aucune action essentielle n’en dépend, mais pas d’aide facultative intégrée. |
| 72 | Neutralité algorithmique | Partiel | Recherche/territoire et critères de tags explicites ; tris date/thème/état non complets. |
| 73 | Sécurité applicative | Partiel/non audité | CSRF, transactions, anti-rejeu, échappement ; tests XSS/SQLi/charge et revue complète non exécutés. |
| 74 | Authentification | Partiel | Authentification Guardian des diagnostics ; pas d’authentification, récupération ou MFA des citoyens. |
| 75 | Administration technique | Partiel | Protection du profiler et pas de vote privilégié ; pouvoir de l’opérateur sur base/clé non neutralisé par ancrage externe. |
| 76 | Modération et coexistence | Partiel | Dossier de signalement ; traitement, qualification et décisions de modération absents. |
| 77 | Sanctions de comportement | Absent | Pas de moteur de sanction avec durée, règle, proportionnalité, instruction et réexamen. |
| 78 | Protection des données | Partiel/documentaire | Uniquement données fictives ; responsable/registre/AIPD réels non établis et effacement à corriger. |
| 79 | Gouvernance technique | Partiel | Proposition/avis collégiaux ; pas d’application des règles ni de contrôle de déploiement collégial. |
| 80 | Principe économique | Partiel | Dépenses/contributions fictives ; pas de coût réel d’exploitation mesuré. |
| 81 | Rapport financier public | Partiel | Montants en centimes et référence fictive ; énergie, budget et justificatifs publiables incomplets. |
| 82 | Contribution citoyenne | Partiel | Contribution de ressource textuelle ; processus complet pour chaque forme de participation non réalisé. |
| 83 | Licence et propriété intellectuelle | Partiel | Licence MIT du code Symfony ; licences documents/données et politique de marque à formaliser. |
| 84 | Objectifs non fonctionnels | Non mesuré | Aucun banc p95/actualisation/disponibilité ; pas d’audit accessibilité ni restauration. |
| 85 | Scalabilité | Non exécuté | Suite locale à quelques personnages, aucun test milliers de sessions ou capacité nationale. |
| 86 | Sauvegarde et restauration | Absent | Pas d’automatisation de sauvegarde chiffrée, ni reprise/restauration testée. |
| 87 | Observabilité | Partiel | Profiler Shield réel et journal métier ; pas de surveillance complète des latences, sauvegardes et alertes. |
| 88 | Scénario initial | Présent en démo | Trois villes réelles, neuf personnages, navettes/vélo/horaires fictifs. |
| 89 | Déroulement de la simulation | Partiel | Nombreuses étapes exécutables ; chaîne complète création-expérience-bilan-convergence non recettée de bout en bout. |
| 90 | Scénarios de convergence | Partiel | Quatre descriptions pédagogiques ; seule proximité de tags calculée, pas de scénario temporel exécuté. |
| 91 | Scénarios de validation | Partiel | 31 scénarios validés dans leur périmètre, 28 partiels, 4 absents, 2 non vérifiés, 3 différés ; voir matrice. |
| 92 | Phase 0 — Fondation documentaire | Partiel | Charte/specifications/baseline ; glossaire de référence, risques et arbitrages complets non établis. |
| 93 | Phase 1 — Socle Symfony | Partiel | Pile Symfony/SQLite, installation Arch documentée ; installation sur Arch non exécutée, comptes citoyens absents. |
| 94 | Phase 2 — Citoyens et chemins | Partiel | Personnages et versions ; graphes complets et droits d’effacement incomplets. |
| 95 | Phase 3 — Consultations | Partiel | Vote/admissibilité/gel/décompte présents ; temps réel, états et recours à compléter. |
| 96 | Phase 4 — Expériences et convergence | Partiel | Observations/bilans ; convergence temporelle/empirique et révisions de bilan à réaliser. |
| 97 | Phase 5 — Transparence cryptographique | Partiel | Journal signé/outils ; ancrage indépendant et garanties de l’opérateur à compléter. |
| 98 | Phase 6 — Infrastructure distribuée | Absent en réel | Seulement nœuds virtuels ; agent, transport, résilience et tests réseau absents. |
| 99 | Phase 7 — Préparation d'une expérimentation publique | Non réalisé | Pas d’examen juridique, d’audits externes, d’AIPD ni de partenariats publics. |
| 100 | Acceptation du démonstrateur | Non acquis | Plusieurs critères existent ; accessibilité et recette exhaustive insuffisantes pour acceptation globale. |
| 101 | Critères bloquants | Non certifié | Invariants positifs testés ; pas de conclusion de non-présence de tous les blocages sans audits et correction des écarts. |
| 102 | Évolutivité | Partiel | Code lisible et données évolutives ; catégories fixes, normalisation et fédération restent des obstacles. |
| 103 | Arbitrages structurants | Partiel | Règles simulées documentées ; les douze arbitrages DC ne sont pas tous formellement arrêtés. |
| 104 | Documents de référence | Documentaire | Liens de référence présents ; leur actualité et contenu n’ont pas été revérifiés dans cet audit du code. |
| 105 | Gestion des versions | Partiel | Git, baseline et lock ; processus complet de versionnement des exigences/règles/traductions à établir. |


Le §91 bis est couvert par les limites de méthode et les résultats bruts : la recette actuelle n’a pas encore, pour chacun des 68 scénarios, un dossier d’exécution complet avec préconditions, entrées, étapes, attendu, constaté, preuves et incident. La matrice est un **audit de couverture**, pas un faux procès-verbal de recette complète.

## Ordre de correction proposé

1. Droits et cohérence : effacement du journal, version de référence des expériences/exports, révisions explicites des bilans, statut après échéance.
2. Chemins : vrais brouillons, révision de tous les champs, sources versionnées, catégories extensibles, relations et éditeur causal ; transitions de cycle de vie complètes.
3. Expériences : comparaison prévision/résultat, bilan communal, quatre scénarios de convergence réellement calculés et testés.
4. Identités et gouvernance : comptes fictifs authentifiés si l’on passe à un espace partagé, autorisations par rôles, sanctions motivées, traitement des recours, règles appliquées sous gouvernance versionnée.
5. Architecture : modules métier, normalisation progressive compatible SQLite, API documentée, Translation, Messenger et temps réel ; tests PHPUnit/PHPStan.
6. Exploitation et recette : sauvegarde/restauration, parcours navigateur, clavier/lecteur d’écran, sécurité et charge ; réplication puis agent volontaire dans une phase distincte.

Le passage à PostgreSQL et l’import national restent différés tant que la restriction SQLite/trois villes est maintenue. L’IA étant facultative, son absence ne bloque pas les actes essentiels. Les clés/identifiants civils réels et les fonctionnalités de production ne sont pas ajoutés implicitement par cet audit.

## Reproduire les résultats

Depuis `apps/symfony`, après installation :

```bash
composer test
php tests/requirements-audit.php
composer style:check
```

La deuxième commande retourne 1 pour les écarts décrits. Pour mettre à jour la preuve après correction :

```bash
php tests/requirements-audit.php > docs/requirements-audit-results.json
```

La suite principale et l’audit ont des buts différents : une CI verte prouve la non-régression dans le périmètre testé, pas l’achèvement du cahier des charges.
