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

