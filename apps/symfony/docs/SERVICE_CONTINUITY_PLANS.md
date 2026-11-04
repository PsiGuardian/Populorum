# Plans publics de continuité des services essentiels

## Objet

La rubrique \`/services-et-missions\` contient désormais une sélection de
**28 fiches de continuité**, une par fonction du catalogue existant
(\`EssentialServices::CATALOG\`). Les plans décrivent :

1. la mission et ses dépendances ;
2. la prévention des ruptures ;
3. la détection de défaillances ou d'alertes ;
4. les solutions temporaires validées par les autorités compétentes ;
5. les conditions de rétablissement et les indicateurs publics à suivre.

Les fiches constituent un **outil pédagogique de simulation**, sans capacité
d'intervention ni contrôle de réseau. L'application ne devient ni un
exploitant d'eau potable, ni une cellule de crise ORSEC, ni un centre
de commandement. L'information technique non publique (cartographie des
organes sensibles, accès, schémas opérationnels, identifiants, vulnérabilités
précises, ressources et stocks en temps réel) ne doit jamais y être exposée.

## Plan particulier : eau saine et agréable à consommer

Le Code de la santé publique exige que l'eau destinée à la consommation
humaine soit propre et salubre. La fiche suit aussi, sans les confondre :

- qualité sanitaire démontrée par les contrôles requis ;
- disponibilité quantitative et accès équitable ;
- acceptabilité gustative/olfactive et satisfaction des usagers ;
- fiabilité de l'information publique et de la réponse aux plaintes.

Un goût agréable n'est **pas** une preuve sanitaire. Inversement, la
conformité aux seuils réglementaires ne dispense pas d'analyser les
anomalies sensorielles et les incidents signalés.

### Scénarios publics

| Incident | Préparation et protection | Continuité / reprise |
| --- | --- | --- |
| Contamination chimique ou gaz/produits volatils suspectés | Surveillance adaptée au risque, séparation des circuits, intégrité de la chaîne, contrôle des accès, intervention de l'exploitant et de l'ARS | Alerte institutionnelle, restrictions et eau de substitution vérifiée ; retour seulement sur analyses et décision sanitaire |
| Anomalie d'intégrité et reflux de fluides | Prévention réglementaire des retours de flux, protection physique et organisationnelle ; contrôles par l'exploitant | Séparation des usages et évaluation sanitaire professionnelle |
| Fuite / rupture | Détection et diagnostic par personnel habilité ; protection des usagers | Continuité de substitution, réparation contrôlée, vérification de l'intégrité et analyses avant reprise |
| Obstruction / baisse de débit | Surveillance du service, entretien préventif et signalement des variations | Diagnostic et intervention professionnels, puis vérification du débit et de l'eau distribuée |
| Coupure électrique ou désorganisation prolongée, y compris conflit | Interdépendances eau/énergie/communications, coordination des responsabilités et redondance appropriée | Distribution alternative approuvée, accès prioritaire aux personnes vulnérables et reprise progressive validée |

**Les procédures techniques exactes (manœuvres de vannes, détails
des accès, points de vulnérabilité, paramétrages et tactiques) ne figurent
pas dans les fiches publiques.** Elles appartiennent au plan d'exploitation
professionnel, avec accès restreint.

### Alerte sanitaire

Si un contaminant chimique, un gaz ou un produit volatil est suspecté,
il ne faut pas recommander le simple fait de faire bouillir l'eau :
cela **ne garantit pas** la décontamination chimique. Les exploitants,
la collectivité et l'ARS déterminent les usages interdits ou autorisés,
les analyses, les solutions de substitution, l'information des usagers
et la levée des restrictions.

Le gouvernement français mentionne notamment des distributions d'eau
embouteillée, d'unités de traitement mobiles ou de citernes adaptées
comme solutions possibles d'alimentation de substitution, sous la
responsabilité des autorités compétentes.

## Autorités et responsabilités

La personne responsable de la production ou distribution d'eau (PRPDE)
et les exploitants préparent et maintiennent les PGSSE, en coordination
avec les autorités sanitaires. Les mairies, préfectures, ARS et autres
acteurs interviennent selon le contexte et leurs compétences. Le
logiciel **ne doit pas les remplacer ni déclarer qu'une eau est sûre**.
Une mission citoyenne ne donne aucune habilitation d'accès aux
installations.

## Sources publiques de référence

- [Légifrance — arrêté du 3 janvier 2023 relatif aux PGSSE](https://www.legifrance.gouv.fr/loda/id/JORFTEXT000046967963/)
- [Ministère chargé de la santé — plans de gestion de la sécurité sanitaire de l'eau](https://sante.gouv.fr/sante-et-environnement/eaux/article/les-plans-de-gestion-de-la-securite-sanitaire-des-eaux-pgsse)
- [OMS — manuel des plans de sécurité sanitaire de l’eau](https://www.who.int/fr/publications/i/item/9789240067691)
- [Ministère de la Transition écologique — alimentation de substitution](https://www.ecologie.gouv.fr/actualites/savoir-secheresse)
- [CDC — différents avis sur l'eau en situation d'urgence](https://www.cdc.gov/water-emergency/about/drinking-water-advisories-an-overview.html)
- [EPA — outils de surveillance et prévention sur les réseaux](https://www.epa.gov/dwreginfo/drinking-water-distribution-system-tools-and-resources)

## Périmètre de cette livraison

- Données **en lecture seule**, version initiale du catalogue, 28 services
  couverts, dépendances reprises du modèle existant.
- Fiche eau : 5 scénarios et ressources institutionnelles vérifiables.
- Sélection publique sans compte, sans action sur un service réel.
- La création d'une mission fictive reste gérée par la fonctionnalité
  existante « Services et missions ».

**Non implémenté à ce stade :** déclaration persistante d'incidents,
édition multi-acteurs des plans, historique de leurs révisions, validation
opérationnelle par l'exploitant, intégration des mesures de qualité de l'eau
ou activation de dispositifs d'urgence. Ces fonctionnalités exigeraient
des modèles d'autorisation, un historique signé et une séparation des
informations publiques et confidentielles. Elles ne doivent pas être
déduites des fiches de simulation actuellement publiées.
