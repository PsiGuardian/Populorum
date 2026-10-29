# CAHIER DES CHARGES GÉNÉRAL — DÉMOS KRATOS

### Δῆμος Κράτος — Le pouvoir appartient au peuple

**Version :** 1.0 — Première rédaction complète  
**Date :** 29 octobre 2026  
**Langue :** Français — texte de référence  
**Statut :** Document public de conception ; à examiner et valider avant toute mise en production  
**Territoire initial :** France  
**Projet :** Simulateur démocratique fictif et expérimental, destiné à devenir libre et open source  
**Technologie de référence :** Symfony 8.1, PHP 8.4, PostgreSQL

> « Que nul ne soit condamné pour avoir pensé autrement. Qu'il soit seulement invité à expliquer son chemin, et que chacun puisse apprendre des conséquences de ses propres décisions. »

**Convergence émergente :** « N'impose pas ton chemin. Éclaire le tien, et laisse les conséquences instruire chacun. »

**Avertissement :** Démos Kratos est un **projet fictif de simulation**, et non une élection officielle, un service gouvernemental ou un mécanisme produisant des décisions publiques juridiquement exécutoires. Les identités et consultations du démonstrateur sont simulées. La publication de ce document ne constitue pas une annonce d'ouverture de consultations réelles.

---

# PARTIE I — FONDEMENTS DU PROJET

## 1. Vision générale

Démos Kratos vise à concevoir et étudier une forme expérimentale de participation démocratique fondée sur la responsabilité individuelle, la mémoire collective et la convergence émergente.

Le citoyen doit pouvoir identifier un problème, décrire les causes directes, indirectes, probables et observées, proposer son chemin, en expliquer les conséquences anticipées, examiner librement les autres chemins, participer à une consultation expérimentale, observer les conséquences et faire évoluer son raisonnement.

L'objectif n'est pas de déterminer quelle personne détient la meilleure opinion. Il est de transmettre aux générations futures une mémoire structurée des décisions, des expériences et de leurs conséquences.

## 2. Objectif fondamental

Permettre des décisions communes sans imposer l'uniformité des pensées, par quatre dimensions : **liberté individuelle**, **expérience collective**, **convergence émergente** et **transparence vérifiable**. La convergence reste possible, jamais garantie ni imposée.

## 3. Principes non négociables

- **P-01 — Égalité civique :** une seule voix par personne admissible et par consultation.
- **P-02 — Liberté de pensée :** aucun chemin intellectuel imposé.
- **P-03 — Non-jugement :** aucune hiérarchie de valeur entre personnes selon leurs opinions.
- **P-04 — Autonomie des chemins :** proposer son raisonnement plutôt que chercher à contredire personnellement autrui.
- **P-05 — Observation des faits :** conséquences mesurables, documentables et vérifiables.
- **P-06 — Droit à l'erreur :** une prévision erronée n'est pas une faute morale.
- **P-07 — Droit à la révision :** chaque personne peut modifier sa réflexion.
- **P-08 — Mémoire collective :** conservation proportionnée, conforme au droit.
- **P-09 — Autonomie communale :** décisions et expérimentations localisées.
- **P-10 — Transparence :** méthodes de calcul, dépenses et opérations auditables.
- **P-11 — Absence de privilège technique :** administrer le site ne donne pas davantage de votes.
- **P-12 — Volontariat :** nul n'est tenu de participer ou de publier sa pensée.
- **P-13 — Protection des personnes :** prévenir la pression, le harcèlement et l'usurpation.
- **P-14 — Indépendance :** aucun acteur ne doit pouvoir capturer unilatéralement le projet.
- **P-15 — Réversibilité :** capacité de corriger, migrer et revoir la gouvernance.

## 4. Positionnement juridique et institutionnel

Le projet distingue **proposition expérimentale sans effet légal**, **expérimentation autorisée avec une collectivité** et **acte officiel adopté par une autorité compétente**. Le terme « loi expérimentale » ne doit jamais induire un effet juridique fictif.

La « présidence collective » désigne symboliquement la décision du peuple dans la simulation, sans remplacer le président de la République ni les institutions. Les actes réels respectent les compétences et procédures prévues par le droit. Les modalités nominatives réelles impliquant des opinions politiques sensibles nécessiteront un fondement juridique, des garanties de protection et une validation préalable.

# PARTIE II — ORGANISATION TERRITORIALE

## 5. Référentiel géographique français

Importer le Code officiel géographique INSEE, millésime 2026, avec ses codes, millésimes et historiques : France, régions, départements, communes et collectivités particulières. Les intercommunalités seront modélisées séparément. Distinguer commune, mairie, arrondissements municipaux, communes associées/déléguées et statuts ultramarins. Chaque changement de nom, fusion, création et rattachement doit pouvoir être retracé.

## 6. Fiche territoriale

La fiche d'une commune rassemble : nom, code INSEE, région/département, statut administratif, population sourcée et millésimée, rattachements, propositions, consultations, expériences, observations, adaptations, exclusions et bilans. Les coordonnées de la mairie, si utilisées, proviennent de sources identifiées avec date de mise à jour.

## 7. Autonomie municipale

Chaque commune possède ses décisions expérimentales et son historique. Un vote local ne s'applique **jamais automatiquement** ailleurs. Le système accepte les propositions locales, les reprises volontaires, les consultations séparées entre communes, les adaptations et les inapplicabilités territoriales. Les différences de mesures concernent les contextes, non la valeur des citoyens.

## 8. Fiche d'inapplicabilité territoriale

Une adaptation ou exclusion comprend : proposition, territoire, caractéristique locale, cause, conséquences probables et observées, sources, solution alternative et conditions de réexamen. Elle ne peut résulter d'une action administrative discrétionnaire non tracée.

## 9. Extension territoriale

Le démonstrateur commence en France. Le modèle de données et les interfaces ne doivent pas empêcher une extension future à d'autres pays, avec leurs structures territoriales, critères d'éligibilité et règles juridiques propres.

# PARTIE III — IDENTITÉ ET PARTICIPATION CITOYENNE

## 10. Catégories d'utilisateurs

| Fonction | Responsabilité |
| --- | --- |
| Visiteur | Lecture des données publiques |
| Citoyen participant | Propositions, chemins, observations, votes |
| Citoyen vérificateur | Examen des preuves, signalement d'anomalies |
| Contributeur technique | Code et documentation |
| Observateur territorial | Apport d'observations vérifiables |
| Conciliateur | Traitement des incidents de comportement |
| Auditeur | Vérification des méthodes et résultats |
| Opérateur de nœud | Hébergement et relais autorisés |
| Administrateur technique | Disponibilité et sécurité |
| Gouvernance collégiale | Décisions sensibles et recours |

Les rôles techniques n'accordent jamais une voix supplémentaire.

## 11. Identité civique

Le mode nominatif volontaire associe unicité, éligibilité territoriale, identité publique vérifiée, information préalable et consentement lorsque requis. Le simple visiteur peut lire sans compte. **En version initiale, toutes les identités et tous les votes sont fictifs**, avec signalement explicite dans l'interface. Identité civile, éligibilité territoriale et identité affichée sont trois notions distinctes. Aucun déploiement réel de données politiques nominatives avant l'examen juridique et technique.

## 12. Compte citoyen

Données possibles : identifiant unique, nom public, commune, statut de vérification, historique territorial pertinent, langue, accessibilité, sécurité, consentements, contributions, votes publics volontaires et chemins versionnés. Les secrets d'authentification, justificatifs d'identité, coordonnées privées et adresses personnelles sont exclus du registre public.

## 13. Droits des participants

Accès, rectification, export, révision autorisée, changement de territoire vérifié, retrait de consentement le cas échéant, effacement lorsque légalement applicable, contestation de suspension, départ du service. La conception doit concilier mémoire et obligations légales : aucune promesse de publication nominative irréversible avec une promesse contradictoire d'effacement. Séparer autant que possible preuves permanentes et données privées.

## 14. Prévention des identités multiples

Vérification adaptée au risque ; registre d'admissibilité ; contraintes transactionnelles d'unicité ; détection des tentatives ; possibilité de recours en cas de faux positif. Une adresse IP ou un appareil ne constitue pas à lui seul une preuve d'identité. Le changement territorial ne doit pas produire une double admissibilité illicite.

# PARTIE IV — LES CHEMINS INDIVIDUELS

## 15. Définition du chemin

Le **chemin** est l'unité de raisonnement autonome d'un citoyen, pas une réponse dirigée contre une personne. Il contient l'auteur, le territoire, un problème, une date, une version, des causes, hypothèses, observations, solutions, conséquences prévues et mesurées, alternatives et révisions.

## 16. Arbre causal

Douze éléments de base, conformes à la charte :

| Élément | Description |
| --- | --- |
| Problème | Situation à comprendre |
| Cause directe | Mécanisme immédiatement responsable |
| Cause indirecte | Facteur contributif |
| Cause probable | Hypothèse explicative |
| Cause observée | Fait documenté |
| Solution proposée | Action envisageable |
| Conséquence favorable probable | Bénéfice anticipé |
| Conséquence néfaste probable | Risque anticipé |
| Conséquence observable | Indicateur vérifiable |
| Conséquence imprévue | Effet non anticipé |
| Solution alternative | Autre chemin possible |
| Révision | Évolution du raisonnement |

Le modèle doit permettre de compléter la typologie sans détruire des données déjà créées.

## 17. Catégories complémentaires

Réversibilité, irréversibilité, délai d'apparition, durée, incertitude, facteur externe, effet indirect, coût matériel, coût humain, populations concernées, condition d'arrêt, critère de réussite, critère d'échec. Elles décrivent les expériences sans noter moralement les auteurs.

## 18. Liens entre causes et conséquences

Relations typées : « cause de », « contribue à », « dépend de », « rend possible », « produit potentiellement », « observé après », « documenté par », « remplacé par » et « alternative à ». Ne pas confondre corrélation, succession temporelle, hypothèse et causalité démontrée. Gérer les facteurs confondants et l'incertitude.

## 19. Versionnement

Toute modification significative crée une révision identifiable, permettant de comparer hypothèses, observations, solutions et raisonnement dans le temps. Ne jamais qualifier automatiquement une ancienne version d'« erreur » et une nouvelle de « vérité ». Conserver l'historique dans la limite des droits applicables.

## 20. Coexistence sans contradiction personnelle

L'interface privilégie : **Exposer mon chemin**, **Ajouter une cause**, **Ajouter une observation**, **Ajouter une source**, **Proposer une autre solution**, **Faire évoluer mon chemin**, **Consulter les conséquences**, **Créer un chemin alternatif**. Pas de « Contredire une personne », ni de note de supériorité intellectuelle ou morale. Des vérifications factuelles sourcées restent possibles, mais portent sur les données, jamais sur la valeur humaine d'un auteur.

# PARTIE V — PROPOSITIONS ET CONSULTATIONS

## 21. Création d'une proposition

Étapes : territoire, problème, explication courante, résumé cause/conséquence/solution/justification, arbre causal, sources, solution, indicateurs, risques, réversibilité, publication. Les brouillons incomplets sont autorisés ; l'ouverture d'une consultation nécessite les informations obligatoires.

## 22. Compréhension linguistique

Chaque proposition comprend explication courte, explication détaillée, résumé **CAUSE → CONSÉQUENCE → SOLUTION → JUSTIFICATION**, glossaire et reformulations adaptées. Le français courant est la base du démonstrateur ; variantes familières, régionales, argotiques et traductions peuvent enrichir la compréhension sans changer le sens. Les reformulations doivent être versionnées et contrôlables.

## 23. Cycle de vie

**Brouillon → Publiée → Complétée → Prête pour consultation → Consultation ouverte → Consultation close → Résultat proclamé → Expérimentation → Observation → Bilan → Archivage ou révision.**

États complémentaires : retirée, suspendue pour vérification juridique ou technique, dupliquée avec rattachement, liée à un chemin voisin, hors compétence locale, inapplicable à un territoire, reportée, remplacée. Toute transition est datée, motivée et auditée.

## 24. Gel de la proposition soumise au vote

À l'ouverture, texte, pièces déterminantes et identifiant de version sont figés. Toute modification substantielle requiert une nouvelle version et, selon les règles, une nouvelle consultation. Aucune modification silencieuse de l'objet d'un vote.

## 25. Paramètres d'une consultation

Identifiant, commune, proposition et version, période, règles d'admission, vérification d'identité, modalités nominatives, quorum, majorité, abstentions, égalités, annulation, recours et publication. Les paramètres affectant le résultat doivent être publiés **avant l'ouverture**, puis figés.

## 26. Vote citoyen

Trois options : **POUR**, **CONTRE**, **ABSTENTION**. Le vote n'évalue pas moralement les personnes. Le citoyen peut exposer son chemin en complément. L'absence de justification écrite ne rend pas arbitrairement un vote nul. Les critères d'admissibilité sont antérieurs à la consultation.

## 27. Modification du vote

Si les règles l'autorisent, un vote peut être changé tant que la consultation est ouverte, avec historique audité et **une seule voix active**. Après clôture, aucun changement du dépouillement historique ; les évolutions ultérieures de position sont traitées dans le chemin personnel ou une nouvelle consultation.

## 28. Résultats en temps réel

Afficher nombres POUR, CONTRE, ABSTENTION, participants, éligibles lorsque mesurable, taux de participation, horodatage, statut provisoire/définitif et événements en attente. La liste nominative est réservée au mode volontaire juridiquement autorisé. Avertir que la publication en temps réel peut influencer le comportement et exposer à des pressions.

## 29. Calcul des résultats

Le moteur applique sans intervention discrétionnaire les règles publiées : exprimés, abstentions, majorité, quorum, égalités et participation. Gérer les consultations vides sans division par zéro. Permettre le recalcul indépendant. Une rectification ou annulation suit une procédure publiquement traçable.

# PARTIE VI — EXPÉRIMENTATION ET CONSÉQUENCES

## 30. Définition d'une expérience

Étude d'une mesure et de ses résultats : simulation pure, analyse de données historiques publiques, observation nouvelle ou partenariat réel légalement autorisé. Toute donnée simulée doit être signalée comme telle ; aucune conséquence fictive ne sera présentée comme fait réel.

## 31. Protocole

Inclure objectif, hypothèse, état initial, période, indicateurs prédéfinis, sources, facteurs externes, conséquences favorables/défavorables, critères d'arrêt, méthode d'évaluation et limites. Fixer de préférence les indicateurs avant l'expérience afin de prévenir la sélection opportuniste des résultats.

## 32. Conséquences observées

Chaque observation enregistre date, indicateur, valeurs initiale et mesurée, unité, méthode, source, incertitude, territoire, groupe concerné si nécessaire, facteurs externes et liens causaux hypothétiques. Distinguer qualité de preuve et jugement moral sur l'auteur.

## 33. Bilan prévisionnel et réel

Comparer prévisions et observations : conforme, partiellement conforme, résultat de sens différent, non mesurable, preuves insuffisantes, imprévu. Aucun indicateur de « bon » ou « mauvais » citoyen n'est généré.

## 34. Réversibilité

Une expérimentation devrait prévoir un retour arrière. Toute irréversibilité doit être visible avant la consultation. Une majorité favorable ne suffit pas à justifier une action risquant gravement les droits, la santé, la sécurité ou l'environnement.

## 35. Bilan communal

Regrouper mesures, hypothèses, résultats, effets non anticipés, coûts, adaptations, conditions locales, continuation, modification ou arrêt. Toute donnée est liée à sa source et à sa période.

# PARTIE VII — CONVERGENCE ÉMERGENTE

## 36. Définition technique

Rapprochement libre et progressif de solutions ou constats provenant de chemins distincts. Mesurer similitudes indépendantes, éléments causaux communs, indicateurs partagés, résultats territoriaux et évolutions temporelles, **sans classer la valeur des personnes**.

## 37. Carte de convergence

Visualiser chemins distincts, parallèles, convergence partielle, convergence observée et divergence persistante. Distinguer les différences de contexte. Le rapprochement ne peut jamais devenir une obligation individuelle.

## 38. Algorithmes d'analyse

Graphes causaux, regroupement thématique, similarités de solutions, séries temporelles, statistiques descriptives, comparaisons territoriales, valeurs manquantes. Les critères sont publiés, explicables et désactivables pour les recommandations. Aucun score moral, politique ou intellectuel d'une personne.

## 39. Limites de convergence

Proximité statistique, proximité conceptuelle et démonstration empirique sont distinctes. Deux effets semblables ne prouvent pas une cause commune ; une majorité de chemins similaires n'est pas une preuve scientifique.

# PARTIE VIII — TRANSPARENCE PUBLIQUE

## 40. Registre public

Rendre consultables dans les limites légales : propositions, révisions, règles, résultats, preuves d'intégrité, bilans, ressources, dépenses, changements techniques, anomalies confirmées, corrections, suspensions, décisions de gouvernance et recours publiables. Les données protégées restent séparées.

## 41. Journal d'événements

Événement : identifiant, type, date UTC, origine autorisée, objet, version, référence de l'événement précédent, empreinte, signature si nécessaire et statut de validation. Le journal doit détecter modification et rupture de chaîne. Un hash ne prouve pas, à lui seul, la véracité d'un fait ou l'identité civile.

## 42. Preuves publiques

Empreintes, signatures numériques, chaînage, arbres de Merkle ou équivalent, points de contrôle publiés, copies indépendantes, vérification d'unicité, recalcul et comparaison inter-nœuds. Les preuves ne doivent pas exiger de publier des informations personnelles protégées.

## 43. Vérification indépendante

Un outil ouvert permet de récupérer les données publiables, vérifier signatures, continuité des événements, résultats, cohérence locale/publique, produire un rapport et soumettre une preuve d'anomalie. Les algorithmes et jeux d'essai seront documentés.

## 44. Alertes d'incohérence

États : **signalement non vérifié**, **divergence reproductible**, **incident de sécurité**, **fraude démontrée**. Une seule alerte peut ouvrir une enquête mais ne suffit jamais à établir une culpabilité. Les divergences affectant le résultat doivent faire l'objet d'une information publique proportionnée.


# PARTIE IX — INFRASTRUCTURE DISTRIBUÉE

## 45. Vision

Le projet doit pouvoir évoluer vers un réseau de serveurs volontaires, limitant la dépendance à un opérateur unique et permettant la vérification indépendante. Cette évolution reste expérimentale ; la distribution ne garantit pas automatiquement l'intégrité.

## 46. Types de nœuds

**Consultation** (pages publiques), **relais** (événements autorisés), **miroir** (copie vérifiable), **auditeur** (recalcul indépendant), **applicatif** (services autorisés), **autorité d'éligibilité** (droits de participation sous gouvernance explicite). Un nœud bénévole ne devient jamais de fait une autorité de vote.

## 47. Partage volontaire de bande passante

Agent facultatif avec plafonds par défaut proposés : **50 % de la capacité montante autorisée lorsque la connexion semble inutilisée**, **10 % lorsqu'elle est utilisée**. Ce sont des plafonds configurables, pas des quotas imposés ; l'usage personnel a toujours priorité. Les mesures de disponibilité sont approximatives et doivent être conservatrices.

## 48. Contrôle de l'agent volontaire

Activation/désactivation, pause immédiate, débit maximal, volume mensuel, plages horaires, désactivation sur connexion limitée, batterie ou température excessive, statut, historique technique, version, politique de mise à jour et révocation. Ne pas observer la navigation personnelle pour estimer la bande passante.

## 49. Données répliquées

Données publiques admissibles : propositions, résultats publiables, preuves, résumés de journal, référentiels territoriaux, observations licites et ressources statiques. Jamais de réplication automatique de mots de passe, justificatifs, adresses privées, clés, journaux privés ou opinions sensibles nominatives sans fondement et garanties juridiques.

## 50. Résilience

Tester déconnexion, panne de serveur, copie corrompue, désynchronisation, événement invalide, rejeu, saturation, indisponibilité centrale et divergence de protocole. Définir explicitement consensus et autorités avant toute utilisation sensible.

## 51. Risques spécifiques

Modéliser attaques Sybil, collusion de nœuds, attaque d'éclipse, déni de service, fausses signatures, clé compromise, partition réseau, fausse date, modification avant signature, censure, fuite de données et incompatibilité des versions. Réduire l'exposition des nœuds volontaires.

## 52. Stratégie de déploiement

**A :** serveur unique, journal vérifiable.  
**B :** miroirs publics indépendants.  
**C :** nœuds volontaires de réplication et vérification.  
**D :** fédération avec consensus et gouvernance.  
**E :** tests à grande échelle.

Chaque étape exige tests, audits et documentation avant passage à la suivante.

# PARTIE X — ARCHITECTURE SYMFONY 8

## 53. Pile technique

| Domaine | Technologie proposée |
| --- | --- |
| Framework | Symfony 8.1 |
| Runtime | PHP 8.4 minimum |
| Données | PostgreSQL ; PostGIS éventuel |
| ORM/migrations | Doctrine ORM / Doctrine Migrations |
| Rendu | Twig, HTML5, CSS3, JavaScript |
| Sécurité | Symfony Security / Validator |
| Formulaires | Symfony Form |
| Traduction | Symfony Translation |
| Sérialisation/API | Contrôleurs Symfony / Serializer |
| Asynchrone | Symfony Messenger |
| Temps réel | SSE ou Mercure |
| Cache | Cache Symfony, Redis optionnel |
| Logs | Monolog, journal métier séparé |
| Tests | PHPUnit et tests fonctionnels |
| Qualité | PHPStan, PHP-CS-Fixer, contrôle des dépendances |
| Carte | OpenStreetMap et composants libres |
| Déploiement | Linux, PHP-FPM, serveur web et PostgreSQL |

Les dépendances sont figées dans des fichiers de verrouillage et mises à jour avec revue de sécurité. Une installation reproductible sur Arch Linux devra être documentée, en privilégiant les paquets système disponibles.

## 54. Architecture modulaire

Quatorze modules métier : **Identity**, **Territory**, **Proposal**, **Pathway**, **Consultation**, **Voting**, **Tally**, **Experiment**, **Convergence**, **Transparency**, **Federation**, **Governance**, **Moderation**, **Finance**, ainsi qu'un composant transversal **Localization** et une assistance facultative **Assistance**. Dans le plan de déploiement, Localization et Assistance peuvent être regroupés comme services transversaux. Les règles métier ne sont pas codées dans les seuls contrôleurs HTTP.

Responsabilités : Identity (identité et éligibilité), Territory (référentiels), Proposal (versions), Pathway (graphes), Consultation (règles et dates), Voting (bulletins), Tally (comptage), Experiment (observations), Convergence (comparaisons), Transparency (preuves), Federation (nœuds), Governance (décisions et recours), Moderation (comportements) et Finance (ressources). Localization/Assistance sont indépendants du moteur de vote.

## 55. Principes de programmation

PHP typé strict, POO, injection de dépendances, interfaces, contrôleurs légers, logique métier testable, validations serveur, autorisations systématiques, transactions, contraintes d'intégrité, migrations, idempotence et séparation du rendu. Aucun résultat ne dépend de JavaScript seul et aucun privilège caché ne permet de falsifier un vote.

## 56. Base de données

PostgreSQL stocke les entités transactionnelles. Graphes causaux via relations normalisées ; attributs complémentaires documentés ; géométrie étendue via PostGIS si utile. Index fondés sur les requêtes réelles, migrations suivies, sauvegardes et procédures de retour arrière pour opérations destructives.

## 57. Entités principales

**Territoires :** Country, Region, Department, Municipality, MunicipalOffice, TerritoryHistory, TerritoryRelation, TerritorialCompetence.

**Identités :** Citizen, CivicIdentity, EligibilityRecord, TerritorialAffiliation, PublicationConsent, AuthenticationCredential, CitizenPreference.

**Chemins/propositions :** Proposal, ProposalRevision, Pathway, PathwayRevision, CausalNode, CausalEdge, Hypothesis, Evidence, Alternative, Forecast, RevisionReason.

**Consultations :** Consultation, ConsultationRuleSet, EligibleParticipant, Vote, VoteRevision, Tally, TallySnapshot, CollectiveDecision.

**Expériences :** Experiment, ExperimentProtocol, Observation, Indicator, ObservationSource, Evaluation, TerritorialAdaptation, TerritorialExclusion.

**Intégrité/réseau :** AuditEvent, IntegrityCheckpoint, DigitalSignature, NetworkNode, NodeAttestation, SyncCheckpoint, IntegrityAlert, IncidentReport.

**Gouvernance :** GovernanceProposal, GovernanceDecision, ModerationCase, Appeal, FinancialEntry, ResourceContribution, TranslationRevision.

## 58. Contraintes d'intégrité

Unicité territoriale avec millésime, une voix active par citoyen/consultation, vote associé à la version figée, admissibilité vérifiée, période valide, règles non rétroactives, transitions auditables, historiques de révision, contrôles des cycles lorsque le sous-graphe l'impose, sommes financières cohérentes et preuves cryptographiques recalculables.

## 59. API

API versionnée pour territoires, propositions, chemins, graphes, consultations, résultats, observations, bilans, preuves, finances et état des nœuds. Opérations sensibles protégées et séparées des routes publiques. Documenter pagination, filtres, quotas, sérialisation, versionnement, erreurs et politique de compatibilité.

## 60. Événements métier

Événements : ProposalCreated, ProposalRevisionPublished, ConsultationOpened, VoteRecorded, VoteChanged, ConsultationClosed, TallyPublished, ObservationRecorded, ExperimentEvaluated, IntegrityMismatchDetected, IntegrityIncidentConfirmed, NodeDisconnected, PublicCorrectionPublished. Consommateurs idempotents pour éviter les doubles traitements.

# PARTIE XI — INTERFACE ET EXPÉRIENCE UTILISATEUR

## 61. Direction artistique

Identité civique contemporaine inspirant dignité, liberté, connaissance, temps, expérience, collectivité, convergence et transparence ; références antiques discrètes, sans symbole partisan. Palette initiale : or #C6A667, bleu nuit #111D2B, pierre #F2EEE5, blanc #FFFFFF, vert #2F6B59, rouge d'alerte #B84A4A. La couleur ne doit jamais être l'unique indicateur.

## 62. Page d'accueil

Titre, traduction grecque, devise, convergence émergente, présentation courte, carte de France, communes et consultations simulées, chemins, expériences documentées, coûts, charte et vérificateur public. **Mention constante du caractère fictif et expérimental**.

## 63. Navigation principale

Accueil ; Mon territoire ; Explorer les communes ; Propositions ; Exposer mon chemin ; Consultations ; Expériences ; Convergence émergente ; Mémoire collective ; Transparence ; Infrastructure citoyenne ; Participer ; Charte. Espace citoyen pour les fonctions privées.

## 64. Carte territoriale

Navigation région → département → commune, consultations, propositions, expériences, adaptations locales et comparaisons territoriales. Une liste textuelle accessible fournit des fonctions équivalentes.

## 65. Affichage d'un chemin

Auteur public si légal, territoire, proposition, graphe causal, versions, sources, observations, conséquences, chemins autonomes voisins, révision. Responsive téléphone/tablette/ordinateur et lecture accessible sans graphe.

## 66. Tableau de bord de consultation

Version exacte, résumé courant, arbre causal, autres chemins, attentes, votes actualisés, statut du dépouillement, règles, preuves, événements et observations après clôture. Une affirmation non vérifiée est visuellement distinguée d'un constat.

## 67. Mémoire personnelle

Frise chronologique : découverte d'un problème, proposition, vote, conséquence imprévue, révision et nouvelle expérience. La plateforme ne qualifie jamais automatiquement une étape d'échec moral personnel.

## 68. Accessibilité

Concevoir pour clavier, lecteur d'écran, basse vision, difficultés de lecture, limitations motrices, connexions lentes et anciens téléphones. Cible **WCAG 2.2 AA** et exigences RGAA applicables. Aucun acte essentiel ne dépend exclusivement d'une animation, couleur ou représentation graphique.

# PARTIE XII — LANGUES ET INTELLIGENCE ARTIFICIELLE

## 69. Langues

Interface principale en français. Architecture Symfony Translation prête pour les **28 locales documentaires** (ar_SA, cs_CZ, de_DE, el_GR, en_CA, en_GB, en_IE, en_US, es_ES, fr_FR, hi_IN, hr_HR, hu_HU, id_ID, it_IT, ja_JP, ka_GE, mn_MN, ms_MY, nl_NL, pl_PL, pt_PT, ro_RO, ru_RU, sk_SK, tr_TR, vi_VN, zh_CN). Traductions versionnées, relecture humaine, avertissement en cas d'écart avec le texte soumis au vote.

## 70. Reformulations linguistiques

Niveaux : français courant, administratif expliqué, scientifique vulgarisé, très simple, familier, expressions régionales, argot utile, autres langues. Chaque variante doit rester fidèle au contenu original et être identifiable.

## 71. Assistance facultative par IA

Peut aider à expliquer les termes, structurer l'arbre, relever les éléments manquants, proposer des catégories à observer, traduire et résumer. Ne peut voter, classer la valeur des citoyens, modifier un texte sans validation, inventer des sources, effacer silencieusement une hypothèse, sanctionner seule, fabriquer de faux soutiens ou prendre une décision publique. Toute aide déterminante est déclarée et validée avant publication.

## 72. Neutralité algorithmique

Rendre les tris explicites : date, lieu, thème, état, disponibilité des données. Éviter que la popularité ou le profilage politique déterminent secrètement l'accès aux propositions. Les recommandations, si proposées, doivent être explicables et désactivables.

# PARTIE XIII — SÉCURITÉ, PROTECTION ET GOUVERNANCE

## 73. Sécurité applicative

Protéger contre SQLi, XSS, CSRF, erreurs de contrôle d'accès, falsification des requêtes, élévation de privilèges, vol de session, attaques d'authentification, rejeu, double vote, falsification des résultats, usurpation, fuite de données, déni de service, composants vulnérables et compromission de déploiement. Vérifier les autorisations côté serveur sur chaque API.

## 74. Authentification

Hachage robuste, MFA adaptée, protection contre les tentatives automatisées, sessions maîtrisées, révocation, audit, notification d'incident et récupération sécurisée. Les secrets ne sont pas stockés dans GitHub.

## 75. Administration technique

Un administrateur ne peut pas créer des votes non déclarés dans une consultation réelle, modifier directement les résultats, effacer des preuves discrètement, changer des règles closes, lire les secrets des comptes ni agir politiquement au nom du peuple. Les actions sensibles exigent des contrôles séparés et parfois plusieurs validations indépendantes.

## 76. Modération et coexistence

Intervenir sur menaces, harcèlement, usurpation, divulgation illégale, fraude démontrée, spam, sabotage et perturbations répétées. Aucune sanction uniquement parce qu'une opinion est étrange, minoritaire ou différente. Favoriser les chemins autonomes ; accepter les vérifications des faits sans juger la personne.

## 77. Sanctions de comportement

Avertissement, restrictions proportionnées, suspension temporaire de publication, mesure contre automatisation malveillante ou suspension après faits établis. Chaque mesure comporte règle préexistante, faits, motifs, durée, proportionnalité, recours et réexamen. Un signalement isolé n'établit jamais une fraude.

## 78. Protection des données

Les opinions politiques nominatives constituent des **données sensibles**. Définir le responsable, finalités, bases légales, condition spéciale de traitement, registre, information, consentements lorsque requis, conservation, rectification, effacement, opposition le cas échéant, sous-traitance, gestion des violations et analyse d'impact si nécessaire. Préparer une AIPD avant déploiement réel à grande échelle. **Ne pas décentraliser les données nominatives sensibles avant démonstration de la conformité des droits des personnes.**

## 79. Gouvernance technique

Séparer maintenance ordinaire et modifications touchant éligibilité, calculs, publication des choix ou droits. Exiger proposition documentée, justification, risques, revue, tests, approbation selon gouvernance, publication de version et plan de retour arrière. Pas de changement démocratique silencieux.

# PARTIE XIV — COÛTS ET RESSOURCES

## 80. Principe économique

Minimiser les dépenses monétaires par les contributions volontaires **sans cacher les coûts réels** : matériel, courant, connectivité, domaine, sauvegardes, maintenance, remplacement, temps humain, audit, incidents, accessibilité et frais éventuels.

## 81. Rapport financier public

Publier dépenses, contributions, apports matériels, ressources offertes, énergie estimée, services, maintenance, évolution des coûts, justificatifs licites et écarts budgétaires. Respecter les données personnelles des contributeurs et fournisseurs.

## 82. Contribution citoyenne

Développement, traduction, audit, documentation, accessibilité, tests, hébergement volontaire, observation, amélioration d'outils, relecture. Aucune contribution n'achète une voix ou un privilège politique.

## 83. Licence et propriété intellectuelle

Choisir avant publication du logiciel : licence libre (AGPL à étudier pour les services), licence des documents et contenus, règles de réutilisation des données, droits des auteurs, contributions et politique de marques. La mise en ligne du cahier des charges **ne fixe pas à elle seule la licence du futur logiciel**.

# PARTIE XV — PERFORMANCES ET QUALITÉ

## 84. Objectifs non fonctionnels

| Mesure | Cible initiale, à valider par banc d'essai |
| --- | --- |
| API publique | p95 inférieur à 800 ms |
| Enregistrement de vote | p95 inférieur à 2 s hors vérification externe |
| Actualisation temps réel | Moins de 3 s en conditions nominales |
| Disponibilité pilote | 99,5 % sur période précisée |
| Accessibilité | WCAG 2.2 AA |
| Unicité des voix | Aucune duplication acceptée |
| Décompte | Recalcul identique |
| Restauration | Procédure prouvée par un test |
| Synchronisation | Aucune corruption silencieuse |

Ces objectifs ne décrivent pas des performances déjà obtenues. Les pages principales devront être rapides sur connexion mobile courante.

## 85. Scalabilité

Tests : dizaines de citoyens fictifs ; milliers d'utilisateurs simulés ; dizaines de milliers de sessions ; centaines de milliers d'identités de test ; charges plus fortes réparties territorialement. Étudier les dizaines de millions d'identités théoriques sans présumer de simultanéité ni déclarer une capacité nationale sans preuves.

## 86. Sauvegarde et restauration

Automatisation, contrôle d'intégrité, chiffrement, séparation physique/logique appropriée, tests de restauration, reprise après sinistre, reconstruction de résultats depuis événements, gestion de corruption, objectifs de perte et délai de reprise documentés. Plusieurs nœuds ne remplacent pas les sauvegardes.

## 87. Observabilité

Disponibilité, erreurs, latence, files de messages, état des bases, synchronisation, journal d'intégrité, ressources, sauvegardes, incidents. La télémétrie doit rester proportionnée, sans surveillance politique comportementale.

# PARTIE XVI — DÉMONSTRATEUR DE RÉFÉRENCE

## 88. Scénario initial

Trois communes fictives — ou communes réelles associées exclusivement à des identités et données simulées — examinent les nuisances automobiles :

- **Chemin A — Navettes publiques :** cause supposée, dépendance automobile ; mesure, navettes ; bénéfices, circulation réduite ; coûts, fonctionnement ; indicateurs, trafic/fréquentation/coût.
- **Chemin B — Infrastructures cyclables :** cause supposée, manque de parcours sécurisés ; mesure, voies cyclables ; effets, trajets motorisés et travaux ; indicateurs, déplacements/sécurité/coût.
- **Chemin C — Réorganisation des horaires :** cause supposée, pics concentrés ; mesure, étalement volontaire ; effets, pics modifiés et contraintes d'organisation ; indicateurs, trafic horaire/satisfaction/perturbations.

## 89. Déroulement de la simulation

Créer plusieurs citoyens fictifs, les rattacher aux communes, présenter trois chemins et leurs causes, ouvrir les consultations, enregistrer des votes fictifs, afficher les résultats, contrôler signatures et unicité, simuler les observations, introduire un imprévu, réviser un chemin, visualiser les rapprochements, produire les bilans et exporter preuves/données publiques. Aucun résultat simulé n'est décrit comme fait réel.

## 90. Scénarios de convergence

Tester **convergence complète**, **convergence partielle**, **absence de convergence** et **convergence suivie de divergence**. L'interface doit montrer qu'aucun de ces scénarios ne condamne une personne.


# PARTIE XVII — MATRICE DE RECETTE FONCTIONNELLE

La matrice suivante constitue la première batterie de **68 scénarios vérifiables**. Les résultats des tests ne sont pas prétendus réussis : ils doivent être réellement exécutés et consignés pendant la réalisation.

## 91. Scénarios de validation

### A. Référentiel territorial


**DK-001** — Importer le référentiel INSEE 2026 et contrôler l'intégrité des identifiants territoriaux.

**DK-002** — Vérifier le bon rattachement d'une commune.

**DK-003** — Importer un nouveau millésime en préservant l'historique.

**DK-004** — Gérer arrondissements municipaux et communes déléguées.

**DK-005** — Empêcher l'application automatique ailleurs d'une proposition locale.

**DK-006** — Permettre une reprise volontaire d'une proposition par une autre commune.

**DK-007** — Justifier et historiser une exclusion ou adaptation territoriale.

**DK-008** — Empêcher qu'un changement de commune permette de voter deux fois.

### B. Identité et droits


**DK-009** — Créer un citoyen fictif avec identité civique unique.

**DK-010** — Refuser une identité dupliquée dans une consultation.

**DK-011** — Autoriser la lecture publique sans connexion.

**DK-012** — Empêcher le vote d'un visiteur non admissible.

**DK-013** — Vérifier que l'administration technique ne confère aucune voix supplémentaire.

**DK-014** — Contrôler le consentement à la participation nominative expérimentale.

**DK-015** — Vérifier rectification et effacement selon le droit applicable.

**DK-016** — Exclure toute donnée secrète et tout justificatif privé des exports publics.

### C. Chemins et propositions


**DK-017** — Créer un chemin autonome.

**DK-018** — Enregistrer les douze catégories de l'arbre causal.

**DK-019** — Distinguer hypothèse et cause observée.

**DK-020** — Empêcher l'ouverture au vote d'une proposition incomplète.

**DK-021** — Attacher une source à une observation.

**DK-022** — Réviser un chemin en préservant les versions accessibles légalement.

**DK-023** — Afficher des chemins indépendants sans classement de valeur des personnes.

**DK-024** — Enregistrer une conséquence imprévue avec sa date.

### D. Consultations et votes


**DK-025** — Ouvrir une consultation avec texte et règles figés.

**DK-026** — Enregistrer une voix POUR.

**DK-027** — Enregistrer une voix CONTRE.

**DK-028** — Enregistrer une ABSTENTION.

**DK-029** — Refuser une seconde voix active du même citoyen.

**DK-030** — Autoriser le changement de vote prévu sans double comptage.

**DK-031** — Refuser tout vote après clôture.

**DK-032** — Refuser une modification silencieuse du texte soumis au vote.

**DK-033** — Vérifier les calculs de majorité et de quorum.

**DK-034** — Gérer une consultation sans voix sans erreur de division.

**DK-035** — Afficher les résultats actualisés et leur caractère provisoire.

**DK-036** — Recalculer indépendamment un décompte à l'identique.

### E. Observations et convergence


**DK-037** — Lier une expérience à la bonne consultation clôturée.

**DK-038** — Définir les indicateurs avant l'observation.

**DK-039** — Ajouter des résultats simulés explicitement étiquetés.

**DK-040** — Distinguer prévision, observation et interprétation causale.

**DK-041** — Repérer une convergence partielle de chemins.

**DK-042** — Présenter une divergence persistante sans la qualifier d'échec personnel.

**DK-043** — Retracer la révision d'un chemin après une observation.

**DK-044** — Produire un bilan territorial avec sources et périodes.

### F. Sécurité et intégrité


**DK-045** — Rejeter la modification d'un vote par un utilisateur non autorisé.

**DK-046** — Détecter la modification d'un contenu après empreinte.

**DK-047** — Détecter la rupture d'une chaîne de journaux.

**DK-048** — Refuser le rejeu d'une opération critique.

**DK-049** — Tester les protections contre les injections et le XSS.

**DK-050** — Tester les contrôles d'accès sur toutes les API sensibles.

**DK-051** — Créer une alerte d'intégrité depuis un signalement.

**DK-052** — Empêcher qu'un signalement isolé suspende automatiquement un innocent.

### G. Réseau distribué


**DK-053** — Synchroniser deux copies publiques compatibles.

**DK-054** — Détecter une divergence entre deux nœuds.

**DK-055** — Refuser un événement sans signature lorsque celle-ci est obligatoire.

**DK-056** — Reprendre la synchronisation après déconnexion.

**DK-057** — Respecter les plafonds configurés de bande passante.

**DK-058** — Arrêter immédiatement le relais volontaire sur demande.

**DK-059** — Empêcher la transmission de données privées non autorisées à un nœud public.

**DK-060** — Restaurer un nœud vérificateur depuis une copie saine.

### H. Accessibilité, gouvernance et finances


**DK-061** — Utiliser les opérations essentielles au clavier.

**DK-062** — Fournir une alternative textuelle à l'arbre causal.

**DK-063** — Consulter l'explication simple et le glossaire.

**DK-064** — Vérifier qu'une traduction n'altère pas le texte de référence soumis au vote.

**DK-065** — Enregistrer une dépense avec montant et justificatif cohérents.

**DK-066** — Consulter les ressources et coûts publiables.

**DK-067** — Exiger la procédure de gouvernance pour modifier les règles démocratiques.

**DK-068** — Exercer un recours contre une mesure de modération ou une sanction.


## 91 bis. Conditions de validation

Chaque exécution devra préciser : identifiant, version, préconditions, jeu d'entrée, procédure, résultat attendu, résultat constaté, preuves, statut (réussi, échoué, bloqué, non exécuté) et incident éventuel. Un affichage à l'écran ne prouve pas le fonctionnement du moteur métier. Prévoir cas normaux, limites et adversariaux.

# PARTIE XVIII — PLAN DE RÉALISATION

## 92. Phase 0 — Fondation documentaire

Livrables : charte, présent cahier des charges, glossaire, règles simulées, analyse initiale de risques, architecture, plan de tests et décisions de conception. **Recette :** identifier et résoudre les contradictions entre charte, vote, droits et contraintes techniques.

## 93. Phase 1 — Socle Symfony

Livrables : Symfony 8.1, PHP 8.4, PostgreSQL, Doctrine, migrations, authentification, interface, référentiel territorial et tests initiaux. **Recette :** installation reproductible et démarrage sur Arch Linux.

## 94. Phase 2 — Citoyens et chemins

Comptes fictifs, communes, éditeur causal, versions, observations, sources et interface de consultation. **Recette :** créer un chemin complet et le faire évoluer sans perte historique illicite.

## 95. Phase 3 — Consultations

Propositions, règles, admissibilité, vote, décompte, actualisation, clôture, publication et audit. **Recette :** aucune double voix, résultat recalculable.

## 96. Phase 4 — Expériences et convergence

Protocoles, indicateurs, observations, bilans, comparaisons, visualisations et révisions. **Recette :** convergence, divergence et effets inattendus correctement représentés.

## 97. Phase 5 — Transparence cryptographique

Journal, signatures, empreintes, points de contrôle, exports, vérificateur indépendant, alertes. **Recette :** toute falsification dans le périmètre du modèle d'intégrité est détectable.

## 98. Phase 6 — Infrastructure distribuée

Synchronisation, agent volontaire, limites de bande passante, réplication autorisée, détection, reprise et simulation de réseau. **Recette :** copies cohérentes ou divergences signalées malgré les pannes prévues.

## 99. Phase 7 — Préparation d'une expérimentation publique

Préalables : examen juridique, AIPD le cas échéant, audits de sécurité et de vote, accessibilité, gouvernance, recours, coûts, limites de responsabilité, partenariats éventuels. **Aucune date de consultation publique réelle n'est fixée.**

# PARTIE XIX — CRITÈRES D'ACCEPTATION GÉNÉRAUX

## 100. Acceptation du démonstrateur

Le pilote peut être déclaré fonctionnel si : application et migrations exécutables ; données territoriales cohérentes ; comptes fictifs ; chemins et versions ; règles de consultation immuables ; vote unique et admissible ; résultat recalculable ; observations sourcées ; convergence sans jugement ; journal d'intégrité ; protection des données ; interface accessible ; tests critiques exécutés ; distinction explicite entre simulation et effet juridique.

## 101. Critères bloquants

Échec bloquant si plusieurs voix actives par personne, modification non autorisée d'un résultat, changement silencieux du texte soumis au vote, simulation présentée comme réalité, privilège de vote caché, modification de journal indétectable dans le modèle prévu, fuite de données privées, sanction automatique depuis un signalement isolé, application territoriale indue, traitement sensible réel sans cadre légal, dépendance à une IA externe pour un acte essentiel, ou calcul non publiquement reproductible.

## 102. Évolutivité

Sans reconstruction globale : nouveaux territoires, langues, catégories causales, observations, visualisations, règles versionnées, nœuds, gouvernance, migration de fournisseur et fédération. Les droits et garanties fondamentales restent applicables.

# PARTIE XX — DÉCISIONS À FORMALISER

## 103. Arbitrages structurants

| Référence | Décision à formaliser avant ouverture réelle |
| --- | --- |
| DC-01 | Préciser la participation nominative publique, consentements et retrait |
| DC-02 | Choisir les méthodes de vérification d'identité |
| DC-03 | Définir l'éligibilité territoriale et les changements de commune |
| DC-04 | Arrêter quorum, majorité et règles de simulation |
| DC-05 | Définir les conditions de changement d'un vote |
| DC-06 | Définir la gouvernance collégiale des règles |
| DC-07 | Définir des critères de convergence sans noter les personnes |
| DC-08 | Séparer données publiables, vérifiables et protégées |
| DC-09 | Définir autorités, consensus et responsabilité des nœuds |
| DC-10 | Définir comptabilité et statut des contributions |
| DC-11 | Choisir les licences du code et des contenus |
| DC-12 | Identifier les responsables juridiques de chaque traitement |

Aucun de ces points ne doit être laissé à une valeur secrète du code.

# PARTIE XXI — RÉFÉRENCES ET TRAÇABILITÉ

## 104. Documents de référence

- [Charte fondatrice, français, 24 articles](../../CHARTER.md).
- [Symfony — Versions et maintenance](https://symfony.com/releases).
- [Symfony 8.1](https://symfony.com/releases/8.1).
- [INSEE — Code officiel géographique 2026](https://www.insee.fr/fr/information/8740222).
- [Code général des collectivités territoriales — L2121-29](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006389895).
- [Code électoral — L59](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006353163).
- [CNIL — Données sensibles](https://www.cnil.fr/fr/definition/donnee-sensible).
- [CNIL — Analyse d'impact](https://www.cnil.fr/fr/ce-quil-faut-savoir-sur-lanalyse-dimpact-relative-la-protection-des-donnees-aipd).
- [CNIL — Droits des électeurs](https://www.cnil.fr/fr/respecter-les-droits-des-electeurs-sur-leurs-donnees-personnelles).

Les références sont à revérifier à chaque mise à jour du document ; le présent texte ne constitue pas une consultation juridique.

## 105. Gestion des versions

Toute version comporte date, numéro, changements, exigences ajoutées/modifiées/supprimées, décisions motivantes, conséquences, impacts techniques et juridiques et nouveaux tests. Les choix passés ne sont jamais effacés sans explication. La publication d'un texte ne vaut pas autorisation de mettre le dispositif réel en production.

# DÉCLARATION DE CLÔTURE

Démos Kratos n'est pas destiné à devenir une simple plateforme de sondages ou une compétition entre opinions. Le vote exprime une décision à un instant ; l'observation permet d'en examiner les conséquences ; la mémoire conserve les leçons ; la convergence émergente permet à des chemins de se rapprocher librement.

**Que nul ne possède le chemin d'autrui. Que nul ne soit réduit à ses erreurs. Que chaque conséquence puisse instruire chacun. Que la mémoire commune éclaire les décisions futures.**

**DÉMOS KRATOS — Δῆμος Κράτος — Le pouvoir appartient au peuple**

> « N'impose pas ton chemin. Éclaire le tien, et laisse les conséquences instruire chacun. »

---

**Fin du cahier des charges — Version 1.0.**  
**Statut :** Proposition de conception fictive ; aucune mise en production publique réelle approuvée.
