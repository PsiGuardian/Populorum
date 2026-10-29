# Contexte documentaire des fixtures municipales

Recherche arrêtée au 29 octobre 2026. Ce jeu est une fiction de participation : 100 personnages par commune, neuf propositions par commune. Aucun personnage, vote, consentement, budget ou résultat ne décrit une personne réelle ni une préférence des habitants. Les propositions sont des projets expérimentaux municipaux, sans valeur de loi adoptée. Chaque fiche précise les compétences et coordinations nécessaires.

## Méthode et limites

Le fichier `data/research/ssmsi-2024-2025-three-cities.json` conserve 90 lignes du jeu officiel SSMSI : trois communes, deux années, quinze indicateurs. Il conserve les valeurs brutes, unités, indicateurs de diffusion et millésimes des dénominateurs. L’URL du fichier original, sa date et son SHA-256 y sont enregistrés. Licence Ouverte 2.0. Le fichier complet de 39 Mo n’est pas dupliqué dans Git. La population municipale utilisée pour les taux est celle de 2023, les logements ceux de 2022.

Ces données mesurent des faits enregistrés, pas toute la délinquance subie. Les variations d’enregistrement, de plainte, d’activité policière et les flux de visiteurs limitent les comparaisons. Les unités diffèrent : victimes, infractions, véhicules. Les cambriolages sont rapportés à 1 000 logements ; les autres taux cités à 1 000 habitants. Il ne faut jamais additionner ces catégories en un « taux de criminalité global ». L’amende forfaitaire délictuelle est un sous-ensemble de l’usage de stupéfiants. Les homicides ne figurent pas dans cet extrait communal : aucun chiffre départemental ou médiatique ne leur est substitué.

Les articles de presse servent à repérer des préoccupations, avec leur périmètre et leur date. Ils ne démontrent ni un niveau global de criminalité ni une causalité. La couverture de Marseille d’octobre 2026 évoque notamment une baisse des règlements de compte ; elle ne justifie pas de prétendre que tous les indicateurs se dégradent. La couverture parisienne d’un épisode de violences et celle des pressions sur les métiers portuaires au Havre ne constituent pas des mesures de fréquence comparables.

La priorité éditoriale est visible : les trois premiers projets de chaque ville concernent sécurité, prévention ou accueil des victimes. Elle ne repose sur aucun score de personnes et n’affirme pas une hiérarchie statistique entre communes. Les autres projets portent sur écoles, logement, accessibilité, chaleur, mobilités, énergie et risques naturels. Les hypothèses causales et objectifs de variation restent explicitement à tester. Aucun article de presse n’est reproduit.

## Quelques indicateurs officiels 2025

| Commune | Indicateur | Nombre enregistré | Taux pour mille | Dénominateur |
| --- | --- | ---: | ---: | --- |
| Paris | Violences physiques intrafamiliales | 6259 | 2.975 | Habitants |
| Paris | Vols sans violence contre des personnes | 94924 | 45.121 | Habitants |
| Paris | Cambriolages de logement | 7072 | 5.055 | Logements |
| Paris | Trafic de stupéfiants | 5570 | 2.648 | Habitants |
| Marseille | Violences physiques intrafamiliales | 3823 | 4.315 | Habitants |
| Marseille | Vols sans violence contre des personnes | 17122 | 19.324 | Habitants |
| Marseille | Cambriolages de logement | 4346 | 9.321 | Logements |
| Marseille | Trafic de stupéfiants | 3649 | 4.118 | Habitants |
| Le Havre | Violences physiques intrafamiliales | 815 | 4.889 | Habitants |
| Le Havre | Vols sans violence contre des personnes | 1699 | 10.193 | Habitants |
| Le Havre | Cambriolages de logement | 534 | 5.626 | Logements |
| Le Havre | Trafic de stupéfiants | 196 | 1.176 | Habitants |

Les deux années complètes restent consultables dans l’extrait. Une hausse ou baisse d’une catégorie ne décrit pas les autres.

## Les 27 propositions

### Paris

| Projet | Priorité | Sources documentaires |
| --- | --- | --- |
| Présence de proximité sur les cheminements fréquentés | Sécurité / prévention | `ssmsi`, `paris-police` |
| Accueil confidentiel et orientation des victimes | Sécurité / prévention | `ssmsi`, `paris-victims` |
| Médiation et continuité des services lors des grands rassemblements | Sécurité / prévention | `paris-media`, `paris-police`, `legal-state` |
| Traversées sûres autour des écoles | Services / cadre de vie | `paris-schools`, `paris-works` |
| Ombre et confort d’été dans les écoles | Services / cadre de vie | `paris-heat` |
| Parcours de fraîcheur accessibles et information de proximité | Services / cadre de vie | `paris-heat`, `paris-schools` |
| Permanences sur les droits locatifs et les loyers | Services / cadre de vie | `paris-rents` |
| Orientation sur les meublés touristiques et le logement permanent | Services / cadre de vie | `paris-tourism` |
| Cheminements accessibles pendant les chantiers de voirie | Services / cadre de vie | `paris-works` |

### Marseille

| Projet | Priorité | Sources documentaires |
| --- | --- | --- |
| Médiation de proximité autour des équipements publics | Sécurité / prévention | `ssmsi`, `marseille-prevention`, `marseille-media` |
| Protection et orientation des jeunes exposés aux pressions | Sécurité / prévention | `marseille-prevention`, `marseille-media` |
| Lieux d’accueil sûrs et information nocturne | Sécurité / prévention | `ssmsi`, `marseille-safeplaces`, `marseille-safety` |
| Traversées accompagnées et rues des enfants | Services / cadre de vie | `marseille-schools` |
| Signalements d’habitat dangereux et suivi contradictoire | Services / cadre de vie | `marseille-housing` |
| Cours ombragées et confort thermique scolaire | Services / cadre de vie | `marseille-schools` |
| Accès effectif aux services périscolaires et à la restauration | Services / cadre de vie | `marseille-schools` |
| Parcours accessibles vers les services publics | Services / cadre de vie | `marseille-prevention`, `marseille-schools` |
| Prévention des incendies dans les équipements municipaux | Services / cadre de vie | `marseille-safety` |

### Le Havre

| Projet | Priorité | Sources documentaires |
| --- | --- | --- |
| Appui confidentiel face aux pressions autour du port | Sécurité / prévention | `havre-media`, `havre-port`, `ssmsi` |
| Orientation des signalements de fret vers les autorités compétentes | Sécurité / prévention | `havre-freight`, `havre-port` |
| Médiation de soirée et accueil des victimes | Sécurité / prévention | `havre-safety`, `ssmsi` |
| Cheminements sûrs pendant les travaux de la ligne C | Services / cadre de vie | `havre-tram` |
| Exercices d’alerte accessibles face aux inondations | Services / cadre de vie | `havre-flood`, `havre-alerts` |
| Suivi énergétique de bâtiments publics pilotes | Services / cadre de vie | `havre-climate` |
| Orientation habitat et rénovation du centre ancien | Services / cadre de vie | `havre-projects`, `havre-climate` |
| Continuités piétonnes et cyclables vers les transports | Services / cadre de vie | `havre-climate`, `havre-tram` |
| Suivi public et prudent du littoral de Dollemard | Services / cadre de vie | `havre-projects` |

## Registre des sources

Les dates de publication inconnues sont distinguées des dates de consultation ; un contenu peut évoluer. L’encadrement parisien des loyers est notamment à revérifier après le 24 novembre 2026 avant tout usage réel. Les sources juridiques ne remplacent pas une validation de compétence par un professionnel.

- **ssmsi** — [SSMSI : statistiques communales 2024–2025, géographie 2026](https://www.data.gouv.fr/datasets/bases-statistiques-communale-departementale-et-regionale-de-la-delinquance-enregistree-par-la-police-et-la-gendarmerie-nationales). Publication : 2026-07-09 ; consultation : 2026-10-29. Type : public_document.
- **ssmsi-method** — [SSMSI : méthode, unités et secret statistique](https://statistiques.interieur.gouv.fr/ssmsi/sources-et-methodes-statistiques/sources-de-donnees/base-communale). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **legal-police** — [CGCT : pouvoirs de police municipale et limites de compétence](https://www.legifrance.gouv.fr/loda/article_lc/LEGIARTI000006390150). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **legal-state** — [Conseil d’État : articulation des polices municipale et étatique](https://www.conseil-etat.fr/fr/arianeweb/CE/decision/2026-09-18/518576). Publication : 2026-09-18 ; consultation : 2026-10-29. Type : public_document.
- **paris-police** — [Paris : missions de proximité, prévention et sécurité routière](https://www.paris.fr/pages/tout-savoir-sur-la-police-municipale-parisienne-16970). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **paris-victims** — [Services publics parisiens : accueil et orientation des victimes](https://www.masecurite.interieur.gouv.fr/fr/informations-locales/police-paris). Publication : 2026-04-10 ; consultation : 2026-10-29. Type : public_document.
- **paris-media** — [Presse : inquiétudes du tourisme après les violences d’octobre](https://www.lemonde.fr/economie/article/2026/10/19/apres-les-violences-urbaines-du-17-octobre-le-tourisme-francais-craint-pour-son-attractivite-mes-proches-au-bresil-m-ont-dit-de-ne-pas-aller-a-paris_6797583_3234.html). Publication : 2026-10-19 ; consultation : 2026-10-29. Type : media_context.
- **paris-schools** — [Paris : aménagements de rues aux écoles et accessibilité](https://www.parisetmetropole-amenagement.fr/fr/rues-aux-ecoles-des-espaces-publics-repenses-pour-les-enfants-et-le-quartier-835). Publication : 2026-08-06 ; consultation : 2026-10-29. Type : public_document.
- **paris-heat** — [Paris : adaptation des équipements scolaires à la chaleur](https://www.paris.fr/pages/face-au-changement-climatique-paris-s-adapte-et-accelere-ses-transformations-36619). Publication : 2026-09-29 ; consultation : 2026-10-29. Type : public_document.
- **paris-rents** — [Paris : cadre et calendrier de l’encadrement des loyers](https://www.paris.fr/pages/l-encadrement-des-loyers-comprendre-le-dispositif-29091). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **paris-tourism** — [Paris : règles administratives des meublés touristiques](https://www.paris.fr/pages/meubles-touristiques-3637). Publication : 2026-03-25 ; consultation : 2026-10-29. Type : public_document.
- **paris-works** — [Paris : voirie, chantiers et maintien des cheminements](https://www.paris.fr/pages/chantiers-de-voirie-3207). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **marseille-prevention** — [Marseille : prévention, médiation et cadre partenarial](https://www.marseille.fr/index.php/prevention-delinquance). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **marseille-media** — [Presse/AFP : évolution récente des homicides liés aux trafics](https://www.leparisien.fr/societe/narcotrafic-a-marseille-avec-cinq-narchomicides-depuis-janvier-la-ville-voit-les-reglements-de-compte-baisser-a-un-niveau-jamais-vu-16-10-2026-QULMJXPT2NGLBCYRSNYGJY2ZPA.php). Publication : 2026-10-16 ; consultation : 2026-10-29. Type : media_context.
- **marseille-safety** — [Marseille : services de sécurité, prévention et secours](https://www.marseille.fr/securite). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **marseille-schools** — [Marseille : rentrée 2026, sécurité, fraîcheur et services scolaires](https://www.marseille.fr/rentree-scolaire). Publication : 2026-08-28 ; consultation : 2026-10-29. Type : public_document.
- **marseille-housing** — [Marseille : sécurité des immeubles et habitat indigne](https://www.marseille.fr/logement-urbanisme/logement/logements-insalubres-et-arretes-de-perils). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **marseille-safeplaces** — [Région Sud : lieux d’accueil sûrs à Marseille et formation des agents](https://www.maregionsud.fr/actualites/detail/la-region-sud-un-lieu-sur). Publication : 2026-09-01 ; consultation : 2026-10-29. Type : public_document.
- **havre-safety** — [Le Havre : services de sécurité, tranquillité et prévention](https://lehavre.fr/services-au-quotidien/cadre-de-vie/securite-prevention). Publication : 2025-06-05 ; consultation : 2026-10-29. Type : public_document.
- **havre-freight** — [Préfecture : coopération pour prévenir les vols de fret](https://www.seine-maritime.gouv.fr/Actualites/Prevention-des-vols-de-fret). Publication : 2026-05-28 ; consultation : 2026-10-29. Type : public_document.
- **havre-port** — [HAROPA PORT : coopération de sûreté avec les services de l’État](https://www.haropaport.com/fr/actualites/haropa-port-et-les-services-de-letat-mobilises-pour-renforcer-la-surete-portuaire). Publication : 2026-07-23 ; consultation : 2026-10-29. Type : public_document.
- **havre-media** — [Presse : exposition des métiers portuaires aux pressions criminelles](https://www.leparisien.fr/faits-divers/pecheurs-et-dockers-cibles-et-appates-comment-les-narcotrafiquants-jettent-leurs-filets-dans-les-ports-30-04-2026-KVTC5WI2UJBUZGWTCJGFIP2OHQ.php). Publication : 2026-04-30 ; consultation : 2026-10-29. Type : media_context.
- **havre-tram** — [Le Havre : phases des travaux de la ligne C et circulation](https://lehavre.fr/actualites/toutes-les-actualites/ligne-c-de-tramway-les-prochaines-etapes-du-chantier). Publication : 2026-09-02 ; consultation : 2026-10-29. Type : public_document.
- **havre-flood** — [Le Havre : risque d’inondation et de submersion](https://lehavre.fr/services-au-quotidien/cadre-de-vie/risques-majeurs/risques-dorigine-naturelle/inondation-et). Publication : 2026-01-23 ; consultation : 2026-10-29. Type : public_document.
- **havre-climate** — [Le Havre Seine Métropole : plan climat, énergie et mobilités](https://www.lehavreseinemetropole.fr/plan-climat-air-energie-territorial-pcaet). Publication : non déterminée ; consultation : 2026-10-29. Type : public_document.
- **havre-projects** — [Le Havre : rénovation, centre ancien et littoral de Dollemard](https://lehavre.fr/ma-ville/le-havre-ville-en-mouvement). Publication : 2026-06-09 ; consultation : 2026-10-29. Type : public_document.
- **havre-alerts** — [Le Havre Seine Métropole : prévision et information sur les inondations](https://www.lehavreseinemetropole.fr/inondations). Publication : 2026-06-30 ; consultation : 2026-10-29. Type : public_document.

## Reproduction et mise à niveau

```bash
php tools/build-municipal-fixtures.php
composer test
```

Le générateur PHP lit uniquement les définitions et sources versionnées : aucun réseau à l’installation, aucun hasard dans les opinions ni inférence sur les habitants. Les clés de signature sont générées pour chaque laboratoire lors de sa première création et restent privées. Six consultations (trois ouvertes, trois clôturées), 210 votes artificiels et trois protocoles avec six observations inventées permettent d’exercer les parcours. Les consultations ouvertes expirent le 29 octobre 2027 ; il faut ensuite préparer un nouveau millésime de fixtures, pas modifier silencieusement un texte déjà voté.

Les laboratoires existants restent conservés. Dans « Participer », un visiteur anonyme peut ouvrir un nouveau laboratoire. Pour un espace partagé neuf, `app:citizens:accounts --workspace=IDENTIFIANT_NEUF --output=var/access.csv` le crée avec ses accès fictifs privés. Ne jamais écraser la base active pour renouveler les fixtures.
