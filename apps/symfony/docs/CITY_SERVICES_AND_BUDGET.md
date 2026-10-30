# Services essentiels, missions volontaires et budget public

Lot de revue du 30 octobre 2026, livré en archive sans publication GitHub ni déploiement.

## Portée

Le module est fonctionnel dans le laboratoire fictif Symfony/SQLite. Il n’organise pas un pays réel, ne remplace pas les autorités existantes, n’établit pas de contrats de travail et ne constitue pas un outil d’appel des secours. Les citoyens, métiers, compétences, disponibilités, zones et coûts de mission sont inventés. Les comptes financiers importés sont, eux, des données publiques réelles et datées.

## Carte des fonctions collectives

Le catalogue comprend 28 fonctions : eau, alimentation, énergie, santé, secours, construction, déconstruction et réemploi, acheminement, transport de personnes, entretien et réparation, déchets, logement, éducation, entraide, numérique, administration, justice, environnement, culture et distraction, sport, défense, diplomatie, monnaie et paiements, agriculture, industrie, recherche, emploi/formation et protection sociale.

Chaque fonction expose un acteur compétent indicatif, ses dépendances et une priorité de continuité. Ces dépendances peuvent former des cycles. Ni cette liste ni ses priorités ne remplacent une analyse exhaustive des besoins d’un pays. Les responsabilités et habilitations doivent être vérifiées avant tout usage réel ; la carte distingue notamment les compétences étatiques et intercommunales des compétences municipales.

## Annuaire et missions

Les 300 personnages possèdent un profil professionnel fictif associé à leur identifiant existant. Ils peuvent modifier leur métier et jusqu’à huit compétences, puis choisir leur disponibilité et leur accord pour recevoir des propositions. Une nouvelle fiche créée pour un personnage additionnel commence sans disponibilité ni consentement à la sollicitation.

Le demandeur crée un besoin : périmètre, incertitudes, service, zone fictive, priorité déclarée, charge en heures, matériel/autres coûts et plafond total. Les candidats doivent réunir :

- même ville, compétence adaptée, disponibilité et consentement ;
- capacité suffisante et absence d’une autre mission proposée, acceptée ou en cours ;
- devis estimatif (heures × coût horaire + matériel/autres coûts) inférieur ou égal au plafond ;
- demandeur distinct du travailleur, pour permettre une vérification par une autre personne.

Le classement est lexicographique : proximité de zone déclarée, puis coût estimé, puis identifiant pour départager les égalités. C’est une règle explicite de coordination, pas une preuve d’optimum global. Les zones nord/centre/sud sont inventées ; leurs écarts ne sont ni des kilomètres ni des temps de trajet. Le choix reste humain et volontaire.

Le circuit couvre : besoin → proposition → acceptation ou refus → début → fin et coût déclaré → examen par le demandeur. L’auteur ou le travailleur peut retirer une mission non close en documentant le motif et le coût déjà engagé. Aucune proposition n’envoie de message ni ne déclenche une intervention réelle.

Les services marqués « circuit habilité » ne fournissent aucun candidat assignable par ce circuit. Les autres services ne couvrent que la coordination ou des tâches simples sans danger, pas une autorisation générale d’intervention. Un métier déclaré ne vaut pas habilitation. Une urgence réelle relève des services compétents. La réserve communale de sécurité civile s’inscrit notamment dans un cadre d’autorité publique, distinct d’un mandat populaire informel.

Toutes les mutations suivent le moteur existant : acteur déterminé côté serveur, CSRF, champs autorisés, anti-rejeu, révision optimiste, transaction SQLite et journal signé. Les nouvelles routes d’action /agir/ sont incluses dans la limitation des écritures. Les formulaires restent natifs et accessibles sans JavaScript.

Les profils et missions sont persistés dans le snapshot transactionnel de chaque laboratoire et son journal. Ils ne créent pas de voix supplémentaires et ne modifient pas les consultations figées. Ils sont séparés des comptes publics : une mission fictive ne génère pas automatiquement une économie réelle.

## Finances importées

Deux sources publiques 2025 sont archivées :

1. **149 agrégats OFGL**, consolidant les budgets principaux et annexes après neutralisation des flux croisés : Paris 51 lignes, Marseille 49, Le Havre 49.
2. **4 322 lignes DGFiP** de balances comptables, filtrées par SIREN : Paris 2 231 lignes, Marseille 1 398, Le Havre 693. Ce sont des comptes et mouvements annuels, pas 4 322 factures ou dépenses individuelles.

Les extractions incluent leur provenance, date de consultation et empreintes des réponses sources. Le site fonctionne sur ces fichiers locaux et ne dépend pas d’un appel réseau pendant la consultation. Les outils CLI `tools/fetch-public-finance.py` et `tools/fetch-public-balances.py` reproduisent les requêtes vérifiées ; ils ne sont pas lancés par les requêtes HTTP.

Les agrégats sont tous consultables par ville. La balance est filtrable par collectivité et préfixe de compte, avec 50 lignes par page et toutes les colonnes publiées accessibles. Les jeux archivés sont téléchargeables par des routes à liste fermée ; aucun chemin de fichier utilisateur n’est accepté.

**Ne pas additionner les agrégats entre eux ni les comptes de toutes les classes.** Les totaux, sous-totaux, opérations d’ordre, investissements, flux de dette et mouvements non budgétaires ont des sens distincts. Une consolidation peut différer d’un total annoncé dans une présentation municipale. Un rapprochement complet avec chaque annexe n’est pas revendiqué.

### Dépenses totales consolidées et enveloppe à 1 €

| Collectivité | Dépenses totales OFGL 2025 | Population totale de référence OFGL | Enveloppe annuelle à 1 €/habitant |
|---|---:|---:|---:|
| Paris | 9 960 803 322,39 € | 2 129 257 | 2 129 257 € |
| Marseille | 1 755 911 653,39 € | 883 466 | 883 466 € |
| Le Havre | 307 939 092,89 € | 168 329 | 168 329 € |

Le périmètre parisien inclut les compétences communales et départementales. Les populations de référence de cette source ne doivent pas être confondues avec celles des indicateurs SSMSI d’un autre millésime. Les dépenses par habitant ne représentent pas l’impôt payé par chaque personne.

### Ce qui reste hors de cette extraction

Les factures individuelles, les ordres de paiement, l’ensemble des dépenses des métropoles, des régions, des départements hors Paris, de l’État et des organismes sociaux ne sont pas tous consolidés ici. L’historique de chaque contrat et sa ventilation fonctionnelle ne sont pas reconstitués à partir des seuls comptes par nature. Une absence de publication n’est jamais remplacée par une dépense nulle. « Toutes les dépenses publiques » ne peut donc pas être présenté comme une couverture acquise.

## Simulateur du plafond de 1 €

Le scénario prend les dépenses totales consolidées et protège, par convention explicite du modèle, les frais de personnel, les charges financières et les remboursements d’emprunts recensés. Ce n’est ni une liste complète des dépenses légalement obligatoires ni une préconisation d’abolir tout le reste.

Une réduction hypothétique de 0 à 100 % est appliquée uniquement à l’enveloppe restante. Un montant d’autres ressources peut être déclaré, mais n’est ni identifié ni garanti automatiquement. Le site montre les dépenses résiduelles, la contribution locale théorique par habitant et l’écart à financer. Le résultat reste systématiquement marqué comme non validé économiquement et juridiquement.

Même à 100 % de réduction de toute l’enveloppe restante, sans autres ressources, les trois villes restent au-dessus de 1 € par habitant avec ce plancher. Un financement externe peut déplacer la charge ; il ne prouve pas une baisse de l’impôt total. Dotations financées par ailleurs, tarifs des usagers, coûts privés, entretien différé, personnel, assurance, matériaux, contrôle et coordination ne disparaissent pas.

L’objectif souhaité reste consultable et testable. Il n’est pas annoncé comme atteint, comme un taux d’impôt légal ou comme un résultat garanti d’une optimisation logicielle.

## Passage éventuel à un usage réel

Les éléments qui restent à concevoir avant un usage opérationnel réel comprennent la vérification des qualifications, la contractualisation et la rémunération, les assurances, la protection du travail, la commande publique, les pouvoirs de décision, les réservations horaires réelles, la localisation consentie, la réponse aux crises, la réconciliation comptable et l’audit du déploiement. Les tests du laboratoire ne certifient pas ces fonctions inexistantes.

## Sources officielles consultées le 30 octobre 2026

- OFGL : https://data.ofgl.fr/explore/dataset/ofgl-base-communes-consolidee/
- DGFiP : https://opendatamef.outscale-euw2.opendatasoft.com/explore/dataset/balances-comptables-des-communes-en-2025/
- Paris : https://www.paris.fr/pages/comptes-administratifs-de-la-ville-retrouvez-toutes-les-infos-27793
- Marseille : https://www.marseille.fr/mairie/administration-de-la-commune/le-budget
- Le Havre : https://lehavre.fr/ma-ville/la-mairie/budgets/finances-locales/compte-financier-unique
- Réserves communales : https://www.securite-civile.interieur.gouv.fr/reagir-face-aux-risques-et-aux-menaces/comment-se-preparer-face-aux-risques/reserves-communales-de-securite-civile
- Dépenses obligatoires : https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000006070633/LEGISCTA000006164575/2026-03-31

## Recette du lot

- 74 tests PHPUnit réussis, 53 180 assertions : classement de proximité, refus volontaire, retrait, double affectation, intervention réglementée, plusieurs compétences, protection des votes et signatures, données financières et cible non validée.
- 99 contrôles d’intégration, 64 contrôles de correction et 12 sondes d’audit réussis.
- Syntaxe Twig/YAML et analyse statique PHP validées.
- Vérification au navigateur du budget, des montants et populations sources, puis d’un parcours complet : besoin fictif, candidat compatible, proposition, changement de personnage, acceptation, début, coût final déclaré de 85 € dans la fiction et examen par le demandeur initial.

Ces résultats couvrent le logiciel de démonstration ; ils ne constituent pas une validation d’intervention réelle, de qualification professionnelle, de budget municipal légal ou de taux d’impôt futur.
