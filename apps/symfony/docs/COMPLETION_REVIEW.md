> Mise à jour 0.4 : profils évolutifs et 2 700 votes raisonnés, [modèle et recette](FICTIONAL_MINDS.md). La validation courante ci-dessous inclut ce lot.

# Revue d’achèvement du lot 0.3 — 29 octobre 2026

Le lot complète les principaux parcours du démonstrateur Symfony/SQLite et ajoute le jeu de 300 personnages et 27 propositions documentées. Il ne constitue pas une certification de l’intégralité du cahier des charges de production. La [matrice initiale](REQUIREMENTS_AUDIT.md) reste une photographie historique ; les constats courants sont ci-dessous.

## Vérifications exécutées

| Contrôle | Résultat | Périmètre et preuve |
| --- | --- | --- |
| Régressions métier/HTTP | 99 contrôles réussis | `tests/integration.php`, scénario historique isolé |
| Corrections et charte multilingue | 64 contrôles réussis | `tests/corrections.php`, 28 chartes, catégories, révisions et exports |
| Sondes du cahier des charges | 12 contrôles réussis | `tests/requirements-audit.php` |
| Contrats supplémentaires | 38 tests / 51 650 assertions, zéro échec | PHPUnit : DomainCompletionTest, ApplicationCompletionTest, ReplicaTransportTest, MunicipalFixturesTest et FictionalMindTest |
| Analyse statique | PHPStan niveau 5 sans erreur | `composer analyse` |
| Syntaxe et modèle | 18 templates Twig valides, mapping et base synchronisés | `lint:twig`, `doctrine:schema:validate` |
| Dépendances | Aucune alerte connue ni paquet abandonné signalé | `composer audit --locked`, 29 octobre 2026 ; le bundle VCS demeure figé |
| Lisibilité | PHP développé sur plusieurs lignes et conventions Symfony | `composer style:check` |
| Parcours navigateur natif | Charte, filtre communal, détail d’un chemin, vote enregistré | Aperçu PHP réel, pas de maquette HTML ; captures ci-dessous |
| Clavier | Lien d’évitement vers main ; choix et confirmation d’un vote | Parcours manuel ciblé, pas d’audit RGAA complet |
| Charge locale | p95 lecture API 89,272 ms ; vote + transaction 306,237 ms | 100 lectures / 50 écritures séquentielles, 300 profils / 2 700 votes historisés ; [mesures](benchmark-results.json), `tools/benchmark.php` |

Le test de connexion lit le jeton CSRF dans le DOM et ne dépend pas des espaces ou retours à la ligne du template. La suite a été rejouée après reconstruction du cache Symfony.

Ces 213 contrôles/tests ont des granularités différentes ; le nombre d’assertions ne mesure pas un pourcentage de conformité. La mesure de performance exclut réseau, concurrence et déploiement PHP-FPM. Les sauvegardes restaurées et copies corrompues sont exercées dans les tests ; le test HTTPS lance deux récepteurs logiques et un vrai serveur TLS local avec CA de test vérifiée. L’arrêt et la reprise sont testés. Le plafond de débit est mesuré sur un flux mémoire contrôlé, pas sur une connexion Internet résidentielle.

Le serveur PHP mono-processus bloque les autres requêtes pendant un flux SSE ; pour la recette locale Linux, employer `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:5412 -t public`. En ligne, PHP-FPM doit disposer de travailleurs suffisants ; prévoir un diffuseur spécialisé pour une fréquentation élevée. Aucun objectif de disponibilité ou de montée en charge nationale n’est validé ici.

![Vote fictif enregistré](images/populorum-vote-verifie.jpg)

![Articles structurés de la charte](images/populorum-charte-articles.jpg)

## Parcours et protections ajoutés

Comptes Symfony fictifs liés au personnage et au laboratoire, récupération/révocation CLI, CSRF, limitation de débit, API filtrée/paginée, SSE, préférences et navigation traduite FR/EN, transitions motivées, reformulations/glossaire rattachés à une version, gouvernance effective sur les consultations futures, décisions de modération à confirmation indépendante et recours, niveaux d’alerte, annulation collégiale d’un résultat, comparaison descriptive des expériences, mémoire personnelle et bilans communaux. L’effacement du nom exclut sa conservation dans les exports de journal.

Les projections SQL et le snapshot sont dans une même transaction sous verrouillage optimiste ; la contrainte d’unicité des voix et le rollback sont testés. L’outbox et le consommateur Messenger sont durables et idempotents. Les copies publiques sont expurgées, signées, épinglées à une clé, contrôlées contre les retours arrière/divergences ; un checkpoint signé permet de réparer une copie locale corrompue sans oublier le dernier historique accepté. Les sauvegardes chiffrées AES-GCM restent privées et se restaurent vers un fichier neuf.

## Relecture des 68 scénarios

« Couvert en démo » désigne les cas exécutés dans ce périmètre fictif ; cela ne vaut pas recette exhaustive de toutes variantes. « Partiel » conserve une limite réelle. Les préconditions et opérations exactes sont les méthodes de tests indiquées ; cette table n’est pas un procès-verbal complet selon §91 bis.

| Scénario | Exigence | État courant | Preuve ou écart restant |
| --- | --- | --- | --- |
| DK-001 | Importer le référentiel INSEE 2026 et contrôler l'intégrité des identifiants territoriaux. | Différé par périmètre | Trois codes fixés ; import national 2026 absent, périmètre réduit demandé. |
| DK-002 | Vérifier le bon rattachement d'une commune. | Couvert en démo | Trois codes/rattachements fixés, contrôles de fixtures ; pas d’import national. |
| DK-003 | Importer un nouveau millésime en préservant l'historique. | Différé par périmètre | Aucun import de millésime ni historique de fusion/rattachement. |
| DK-004 | Gérer arrondissements municipaux et communes déléguées. | Différé par périmètre | Arrondissements et communes déléguées non modélisés à cette étape. |
| DK-005 | Empêcher l'application automatique ailleurs d'une proposition locale. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-006 | Permettre une reprise volontaire d'une proposition par une autre commune. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-007 | Justifier et historiser une exclusion ou adaptation territoriale. | Couvert en démo | Création motivée, reprise locale, conséquences probables/observées et révisions par auteur local ; test dédié DomainCompletionTest. |
| DK-008 | Empêcher qu'un changement de commune permette de voter deux fois. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-009 | Créer un citoyen fictif avec identité civique unique. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-010 | Refuser une identité dupliquée dans une consultation. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-011 | Autoriser la lecture publique sans connexion. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-012 | Empêcher le vote d'un visiteur non admissible. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-013 | Vérifier que l'administration technique ne confère aucune voix supplémentaire. | Partiel | Compte Guardian séparé ; rôles civiques contrôlés. Matrice exhaustive de tous rôles/actions à étendre. |
| DK-014 | Contrôler le consentement à la participation nominative expérimentale. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-015 | Vérifier rectification et effacement selon le droit applicable. | Partiel | AUD-05 et corrections : effacement du nom jusque dans les exports ; validation juridique réelle extérieure au logiciel. |
| DK-016 | Exclure toute donnée secrète et tout justificatif privé des exports publics. | Partiel | Exports, API, replica et collecteurs testés sans secrets connus ; audit exhaustif des justificatifs réels non applicable aux fixtures. |
| DK-017 | Créer un chemin autonome. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-018 | Enregistrer les douze catégories de l'arbre causal. | Couvert en démo | Douze rubriques et catégories complémentaires ; corrections.php. |
| DK-019 | Distinguer hypothèse et cause observée. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-020 | Empêcher l'ouverture au vote d'une proposition incomplète. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-021 | Attacher une source à une observation. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-022 | Réviser un chemin en préservant les versions accessibles légalement. | Couvert en démo | Révision de tous textes, sources, prévision, nœuds et historique ; corrections.php. |
| DK-023 | Afficher des chemins indépendants sans classement de valeur des personnes. | Couvert en démo | Chemins indépendants et ordre éditorial annoncé, sans score humain ; recette navigateur et Twig. |
| DK-024 | Enregistrer une conséquence imprévue avec sa date. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-025 | Ouvrir une consultation avec texte et règles figés. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-026 | Enregistrer une voix POUR. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-027 | Enregistrer une voix CONTRE. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-028 | Enregistrer une ABSTENTION. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-029 | Refuser une seconde voix active du même citoyen. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-030 | Autoriser le changement de vote prévu sans double comptage. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-031 | Refuser tout vote après clôture. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-032 | Refuser une modification silencieuse du texte soumis au vote. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-033 | Vérifier les calculs de majorité et de quorum. | Couvert en démo | Seuil exact, sous-seuil, majorité stricte, égalité et abstention ; DomainCompletionTest et intégration. |
| DK-034 | Gérer une consultation sans voix sans erreur de division. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-035 | Afficher les résultats actualisés et leur caractère provisoire. | Couvert en démo | Échéance distincte, SSE et rendu actualisé ; corrections et ApplicationCompletionTest. |
| DK-036 | Recalculer indépendamment un décompte à l'identique. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-037 | Lier une expérience à la bonne consultation clôturée. | Couvert en démo | Création de protocole depuis consultation adoptée/clôturée ; texte, version et indicateur figés, doublon refusé ; test dédié. |
| DK-038 | Définir les indicateurs avant l'observation. | Couvert en démo | Protocole avec méthode, période, seuil d’arrêt et indicateur antérieur aux observations ; test dédié. |
| DK-039 | Ajouter des résultats simulés explicitement étiquetés. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-040 | Distinguer prévision, observation et interprétation causale. | Couvert en démo | Comparaison descriptive avec prévision et incertitude, aucune causalité alléguée ; DomainCompletionTest. |
| DK-041 | Repérer une convergence partielle de chemins. | Couvert en démo | Convergences partielle/complète et comparaison d’observations ; ConvergenceAnalyzer et tests. |
| DK-042 | Présenter une divergence persistante sans la qualifier d'échec personnel. | Couvert en démo | Quatre scénarios dont convergence puis divergence temporelle, aucun jugement personnel ; DomainCompletionTest. |
| DK-043 | Retracer la révision d'un chemin après une observation. | Couvert en démo | Révision liée à une observation du même chemin ; lien étranger rejeté, historique testé. |
| DK-044 | Produire un bilan territorial avec sources et périodes. | Partiel | Bilan communal avec périodes, sources, observations, adaptations et coûts ; méthode de synthèse causale réelle non validée. |
| DK-045 | Rejeter la modification d'un vote par un utilisateur non autorisé. | Couvert en démo | Compte Symfony lié à son acteur ; tentative de persona interdite ; ApplicationCompletionTest. |
| DK-046 | Détecter la modification d'un contenu après empreinte. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-047 | Détecter la rupture d'une chaîne de journaux. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-048 | Refuser le rejeu d'une opération critique. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-049 | Tester les protections contre les injections et le XSS. | Partiel | Recherche SQLi et texte XSS stocké/échappé testés ; pas de pentest exhaustif ni externe. |
| DK-050 | Tester les contrôles d'accès sur toutes les API sensibles. | Partiel | API CSRF/acteur/version/champs, session login et accès Guardian vérifiés ; matrice exhaustive à étendre. |
| DK-051 | Créer une alerte d'intégrité depuis un signalement. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-052 | Empêcher qu'un signalement isolé suspende automatiquement un innocent. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-053 | Synchroniser deux copies publiques compatibles. | Couvert en démo | Deux copies publiques compatibles via HTTPS local vérifié ; ReplicaTransportTest. |
| DK-054 | Détecter une divergence entre deux nœuds. | Couvert en démo | Divergence/fork ou mauvais historique rejeté par ReplicaProtocol ; tests de copies. |
| DK-055 | Refuser un événement sans signature lorsque celle-ci est obligatoire. | Couvert en démo | Signature obligatoire contrôlée avant réception ; AUD-11 et tests de protocole. |
| DK-056 | Reprendre la synchronisation après déconnexion. | Couvert en démo | Arrêt et reprise depuis la copie saine ; ReplicaTransportTest. |
| DK-057 | Respecter les plafonds configurés de bande passante. | Couvert en démo | Pacing et budget mensuel réellement contrôlés ; test chronométré sur flux contrôlé. |
| DK-058 | Arrêter immédiatement le relais volontaire sur demande. | Partiel | STOP et consentement relus pendant le transfert ; arrêt systemd immédiat. Lecture réseau bloquée limitée par timeout de dix secondes. |
| DK-059 | Empêcher la transmission de données privées non autorisées à un nœud public. | Partiel | Format public strict et champs privés rejetés ; uniquement personnages fictifs. Cadre légal de réplication de personnes réelles non validé. |
| DK-060 | Restaurer un nœud vérificateur depuis une copie saine. | Couvert en démo | Restauration depuis copie saine avec checkpoint signé et refus du rollback ; ReplicaTransportTest. |
| DK-061 | Utiliser les opérations essentielles au clavier. | Partiel | Lien d’évitement et vote testés au clavier ; audit complet clavier/lecteur d’écran/zoom et RGAA à réaliser. |
| DK-062 | Fournir une alternative textuelle à l'arbre causal. | Couvert en démo | SVG de relations, liens vers douze textes et liste des relations ; alternative sans JavaScript. |
| DK-063 | Consulter l'explication simple et le glossaire. | Couvert en démo | Reformulations et glossaire par version ; validation auteur et contrôle de référence ; DomainCompletionTest. |
| DK-064 | Vérifier qu'une traduction n'altère pas le texte de référence soumis au vote. | Partiel | Reformulation rattachée à la version et texte figé inchangé testé ; validation linguistique des 28 langues extérieure. |
| DK-065 | Enregistrer une dépense avec montant et justificatif cohérents. | Couvert en démo | Régressions intégration et/ou sondes complémentaires réussies ; périmètre fictif conservé. |
| DK-066 | Consulter les ressources et coûts publiables. | Partiel | Ressources et coûts publiables exposés ; pièces comptables réelles, rapprochement complet et contrôle externe non acquis. |
| DK-067 | Exiger la procédure de gouvernance pour modifier les règles démocratiques. | Partiel | Deux avis indépendants puis application versionnée aux futures consultations testée ; protection d’un déploiement réel par gouvernance externe à mettre en place. |
| DK-068 | Exercer un recours contre une mesure de modération ou une sanction. | Couvert en démo | Décision motivée, confirmation indépendante, restriction temporaire de publication sans bloquer le vote, recours/révocation tracés ; DomainCompletionTest. |

## Points qui empêchent de déclarer une conformité intégrale

1. Périmètre accepté : SQLite et seulement trois communes ; import/historique territorial national, arrondissements et communes déléguées restent différés.
2. Identité réelle, MFA citoyenne, notifications de sécurité, prévention Sybil au-delà d’une autorité centrale fictive, qualification juridique et AIPD n’ont pas de validation de terrain.
3. Les tables métier sont des projections transactionnelles avec attributs JSON ; l’ensemble des entités conceptuelles n’est pas normalisé. Les fiches d’adaptation ont désormais un cycle de révisions propre avec motif, source et conséquences distinctes ; les anciennes fiches sans auteur restent conservées sans réattribution silencieuse.
4. L’interface principale reste française, avec navigation FR/EN et 28 chartes documentaires ; cela ne constitue pas une traduction intégrale validée de l’application en 28 langues.
5. La réplication vérifie un historique signé ; elle n’est pas un consensus distribué ni une protection contre une autorité de signature compromise avant signature. Les mises à jour d’agent et révocations opérateur sont manuelles.
6. Recette complète des 68 dossiers selon §91 bis, tests de concurrence prolongés, audit WCAG/RGAA, pentest externe, restauration géographique et disponibilité mesurée restent à exécuter. Les résultats locaux ne les remplacent pas.

Ces points sont conservés comme chantiers ouverts et ne sont pas marqués « terminés ». Aucun déploiement de production ni fusion automatique n’est effectué. [Guide d’exploitation](OPERATIONS.md), [contrat API](API.md), [recherche locale et fixtures](MUNICIPAL_FIXTURES.md).
