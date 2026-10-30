# Recherche, récits progressifs et incertitude

Lot de revue du 30 octobre 2026. Les modifications restent locales et sont livrées en archive, avant toute publication GitHub.

## Recherche et routes

La recherche actuelle **n’exécute pas de DQL** : elle applique une comparaison littérale sur les données de la simulation chargées en mémoire. Aucun terme saisi n’est concaténé à une requête SQL/DQL, une expression régulière, un nom de colonne ou un template. La persistance de l’espace utilise les paramètres liés DBAL existants.

Le formulaire poste vers la route nommée `site_search_submit` (`/recherche`), avec CSRF, puis redirige vers `/recherche/proposals/all/find`, par exemple. Le chemin sans terme est distinct : une recherche du caractère `_` n’est pas transformée en recherche vide. Les anciens liens `?q=…` restent acceptés et redirigent vers la route canonique.

Les liens de l’interface passent par `CivicUrlExtension`, qui utilise exclusivement le générateur de routes Symfony. Les principales rubriques disposent de routes dédiées (`/chemins`, `/charte`, `/recits`…), ainsi que les fiches et les actions portant sur un objet. Les alias précédents restent disponibles pour ne pas casser les liens existants. Des paramètres facultatifs peuvent encore servir à l’indicateur, à la pagination, à une traduction ou à un choix prérempli : leur présence n’est pas une faille. Une URL lisible ne remplace pas les contrôles de sécurité.

`SearchInput` impose un texte UTF-8 de 160 caractères maximum, refuse les tableaux et caractères de contrôle et vérifie les territoires. Les apostrophes, `%`, `_` et fragments SQL sont du texte littéral. Twig échappe les valeurs affichées. L’API emploie la même validation ; son tri reste une liste fermée. Les lectures de recherche sont limitées à 120 par minute et par adresse réseau hachée ; le formulaire relève également du limiteur d’écritures existant. Ce contrôle ne constitue pas une preuve d’identité et peut affecter plusieurs personnes derrière une même adresse.

Si un futur développeur déplace cette recherche en DQL/SQL, il devra utiliser les paramètres liés (`setParameter`/paramètres DBAL), conserver la liste fermée des tris et définir explicitement le comportement de `%` et `_` avec LIKE. Aucun fragment utilisateur ne doit devenir une expression de requête. Déplacer le terme dans le chemin ne masque pas la recherche aux journaux ou à l’historique du navigateur.

## Récits de cause à effet

`CausalAccounts` traite deux actions : `account.create` et `account.append`. Elles suivent la chaîne habituelle : auteur déterminé côté serveur, CSRF, révision optimiste, anti-rejeu, validation, sauvegarde SQLite transactionnelle et journal signé.

Un récit est attaché au personnage auteur et à sa ville. Le rattachement à un chemin est facultatif et choisi dans une liste de propositions existantes. Le texte peut porter sur une expérience fictive du point de vue de son auteur ou sur une inquiétude empêchant de s’exprimer. Il ne faut pas nommer de victime réelle, de tiers réel ou soumettre un aveu réel.

Chaque ajout contient : cause ou mécanisme proposé, conséquences ou inquiétudes, incertitudes et moyens de vérification, autres variables possibles, source éventuelle et motif de publication. L’auteur choisit « récit encore partiel », « nouvel élément » ou « correction ». Les éléments non saisis ne sont pas collectés. Les ajouts antérieurs ne sont pas réécrits ; une correction explique une nouvelle interprétation. Seul l’auteur peut compléter son récit. Les règles existantes de modération et de recours demeurent applicables.

Le moment raconté et la date d’enregistrement sont distincts :

- année seule, date ou date avec heure HH:MM, selon les informations que le personnage souhaite donner ;
- année et date peuvent rester inconnues ;
- une heure nécessite une date et reste une heure locale déclarée Europe/Paris, sans précision universelle inventée ;
- les dates impossibles, futures ou contradictoires sont refusées ;
- l’enregistrement utilise l’horodatage serveur UTC, affiché dans l’interface en Europe/Paris.

Les contributions enrichissent la lecture d’un arbre et sa prochaine révision. Elles ne modifient pas automatiquement ses nœuds, son texte soumis au vote, les votes existants ou les consultations figées. L’opinion d’un personnage reste distincte de la voix familiale ; aucune pénalité, score de culpabilité ou poids de vote supplémentaire n’est calculé à partir d’un récit.

Les nouveaux récits sont persistés dans le document transactionnel de simulation et dans le journal d’audit ; aucune nouvelle table criminelle nominative n’est créée. Ils sont inclus dans l’export de mémoire de la simulation. Ils ne sont pas agrégés entre les laboratoires et ne constituent pas des données officielles.

## Incertitude de connaissance

La présentation distingue hypothèse, récit personnel fictif non vérifié et élément avec source déclarée. La présence d’une source ne vaut pas validation indépendante. Toute nouvelle contribution doit expliquer une incertitude, même si elle indique seulement ce qui reste inconnu.

Une explication compatible avec un résultat peut être incomplète. Une variable inconnue peut être présente sans changer le résultat observé. Ce cadre relève de l’incertitude de connaissance et ne doit pas être présenté comme une application du principe physique d’incertitude quantique.

Une technologie non divulguée peut être imaginée comme hypothèse ; son absence de documentation ne démontre ni son existence ni son intervention. Le site n’utilise pas cette hypothèse pour accuser une personne réelle et ne transforme pas une répétition en preuve. Les observations, les explications alternatives et les possibilités de réfutation restent à documenter.

## Statistiques et fixtures

La page des récits donne accès aux quinze catégories de l’extrait communal SSMSI pour Paris, Marseille et Le Havre : effectifs, unités de compte, taux 2024/2025, écarts de taux et source datée. Les cambriolages restent rapportés aux logements. Les homicides et certains autres crimes ne figurent pas dans cet extrait ; leur absence n’est pas interprétée comme une absence de faits. Les récits ne sont jamais attribués aux auteurs des faits réellement recensés et ne modifient pas les statistiques.

Les 300 personnages ont désormais un prénom et un nom fictifs, avec des noms de famille partagés uniquement selon les rattachements déclarés. Les identifiants, appartenances, profils psychologiques fictifs et règles de vote sont conservés. Les noms n’indiquent aucune propension à commettre un délit. Les fixtures proposent six récits pédagogiques, dont trois historiques à trois étapes. Ils n’affirment pas que ces événements ont eu lieu dans les villes concernées.

Pour les installations existantes : « Participer → Ouvrir un nouveau laboratoire » charge les nouvelles fixtures. Les anciens laboratoires et leurs signatures sont conservés ; aucune migration silencieuse de leurs identités n’est réalisée.

## Cadre non punitif et limites juridiques

L’intention est de comprendre et d’améliorer le système, sans sanction automatique liée au fait de contribuer. **Elle ne confère aucune immunité juridique**. Les articles 427 et 428 du Code de procédure pénale permettent l’appréciation judiciaire des éléments de preuve et des aveux ; une déclaration du site ne peut pas les neutraliser.

La publicité volontaire ne constitue pas à elle seule une autorisation générale de tenir un fichier réel d’infractions nominatives. L’article 10 du RGPD prévoit un cadre particulier pour ces traitements. Toute éventuelle version destinée à des récits réels nécessiterait une conception juridique et de protection des personnes distincte. La présente version demeure fictive et n’est ni une plateforme de dépôt de plainte ni un registre pénal.

Sources officielles consultées le 30 octobre 2026 :

- https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000006071154/LEGISCTA000006182908/2026-04-01
- https://www.cnil.fr/fr/reglement-europeen-protection-donnees/chapitre2

## Vérifications

Les nouveaux tests couvrent les entrées de recherche malformées et fragments SQL littéraux, la route canonique, le CSRF, la conservation du territoire, les champs autorisés de l’API, la persistance des récits, leur échappement HTML, les dates et heures invalides, les droits de l’auteur, l’historique, les signatures et la préservation des votes et textes figés.

Les vérifications au navigateur couvrent la recherche « find » sur sa route dédiée, le nouveau laboratoire aux noms fictifs, la création d’un récit sans date et l’ajout ultérieur d’une précision datée.
