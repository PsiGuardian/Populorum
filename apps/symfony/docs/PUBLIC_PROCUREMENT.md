# Besoins, devis et contrôle des coûts — laboratoire fictif

Cette évolution complète les services, missions et finances des trois villes. Elle ne transforme pas le laboratoire en administration municipale opérationnelle et ne déclenche ni marché, ni notification à une entreprise, ni paiement. Toutes les entreprises, qualifications, factures et décisions de ce module sont fictives.

## Parcours

1. Ouvrir **Besoins et devis** (`/besoins-et-devis`) puis publier un besoin : périmètre comparable, lieu fictif, risques, cause supposée, effet attendu, indicateur, incertitudes, plafond et référence documentée. Une proposition causale et une mission du même territoire peuvent être liées. Le besoin et ses critères ne se modifient plus ; un changement substantiel nécessite une interruption et un nouveau dossier.
2. Fixer une date limite en heure de Paris. Avant cette date, les questions et réponses sont communes et les entreprises fictives déposent leurs offres. Chaque poste précise libellé, quantité entière et prix unitaire TTC en centimes. Exemple : `Peinture | 4 | 2500` = quatre unités de 25 €, total 100 €. Les autres champs monétaires de l’interface se saisissent en euros ; le domaine et l’API utilisent des centimes entiers.
3. Un soumissionnaire peut déposer cinq versions au maximum ou retirer ses offres avant la date limite. La dernière version active est comparée. Les reçus et enveloppes précédentes restent conservés.
4. Après l’échéance, le pilote ouvre les offres. Un tiers du territoire, distinct du pilote, de tous les soumissionnaires (y compris retirés) et des bénévoles, examine la conformité et motive une note de qualité.
5. Le classement indicatif applique les critères annoncés : coût sur la durée 50 %, qualité 30 %, délai 10 %, garantie 10 %. Le choix reste une décision motivée du pilote, dans le plafond publié. Toutes les offres doivent avoir été examinées, y compris celles écartées.
6. Le réalisateur retenu déclare le début puis la fin, le justificatif fictif, le coût et l’effet observé. Un tiers indépendant accepte le résultat ou demande une correction. Le pilote peut seulement ensuite enregistrer le paiement fictif.

## Confidentialité et intégrité

Les offres sont chiffrées avec **AES-256-GCM** avant leur insertion dans l’état. La clé est dérivée de la clé privée propre au laboratoire, avec un domaine de dérivation distinct. Un nonce aléatoire de 12 octets, une étiquette de 16 octets et l’association authentifiée dossier/offre protègent chaque enveloppe. Le contenu JSON est complété à 16 384 octets avant chiffrement pour ne pas exposer sa longueur précise. Une enveloppe altérée est refusée.

Le journal du dépôt ne contient que les identifiants de l’acteur et du dossier : pas de prix, nom d’entreprise, lignes, qualifications, réserves ou motif. Le motif est dans l’enveloppe. Les exports, répliques et API publiques ne contiennent pas la clé privée. Ils conservent les enveloppes chiffrées pour permettre de vérifier le même état signé. Les identifiants des déposants et les heures des dépôts ne sont pas anonymisés.

À l’ouverture, les dernières offres actives deviennent publiques **uniquement dans cette fiction explicitement consentie**. Cette règle ne doit pas être transposée à des secrets commerciaux réels. Les anciennes versions restent chiffrées, elles ne sont pas effacées.

La séparation des personnes est une règle du scénario, pas une habilitation administrative vérifiée. Un utilisateur du laboratoire isolé peut changer de personnage. L’administrateur du serveur possède les clés et pourrait déchiffrer : ce n’est ni un dispositif à seuil, ni une salle de marchés certifiée. La sauvegarde des clés est indispensable ; les perdre rend les offres inaccessibles. Aucun secret réel ne doit être saisi. En exploitation réelle, l’isolation des secrets, des fonctions et des traces HTTP/profiler doit faire l’objet d’une architecture et d’un audit dédiés.

Toutes les écritures passent par le moteur signé existant, CSRF, l’acteur de session, l’anti-rejeu, la révision optimiste et la transaction SQLite. Les tableaux ne sont pas des requêtes SQL/DQL ni du code exécutable ; les textes sont échappés par Twig. Limites : 100 dossiers, 100 dépôts par dossier, cinq versions par soumissionnaire, vingt postes par devis, trente avenants et cent questions/signalements par dossier, dans les limites générales du laboratoire.

## Prix inhabituel et dérive des coûts

La référence est déclarée avec son origine, sa date et sa comparabilité. Zéro signifie inconnue. Un devis initial supérieur au double de cette référence déclenche un signal explicite et impose une justification spécifique lors du choix. Ce seuil pédagogique n’est ni un estimateur du prix juste ni un détecteur de fraude. Il ne remplace pas une comparaison technique et territoriale des prestations.

Chacun peut demander une explication documentée. Un autre examinateur indépendant doit répondre ; tous les signalements en attente bloquent le choix. Une réponse conserve les éléments incertains et ne certifie pas la véracité du prix. Les signalements postérieurs au choix restent visibles sans annuler automatiquement un engagement ni un paiement.

Un avenant conserve le prix initial, son périmètre, sa variation et sa cause. Il ne modifie le plafond autorisé qu’après approbation indépendante, sans dépasser le budget publié. Le total cumulé est visible. Une seule proposition d’avenant peut être en attente. Une facture supérieure au montant autorisé est refusée. Les versions de justificatifs et les demandes de correction restent visibles. Il n’y a pas d’upload de pièce réelle : la référence saisie doit rester fictive.

Le coût sur la durée est `devis initial + entretien annuel estimé × horizon commun`. Le classement compare des offres jugées conformes : prix `50 × coût minimal / coût de l’offre`, qualité `30 × note / 100`, délai `10 × délai minimal / délai proposé`, garantie `10 × garantie / garantie maximale` (maximum au moins un mois). Les taux ne sont pas modifiables dans cette version. Les estimations futures ne sont pas actualisées et l’égalité des périmètres reste appréciée humainement.

## Alternative habitante

Le pilote peut proposer, avant l’échéance, un plan unique de tâches simples à faible risque dans un service non réglementé : même résultat attendu, heures nécessaires, fournitures/équipements, assurance, encadrement, contrôle et solution professionnelle de repli. Chaque catégorie de coût doit être explicitement chiffrée. Les services réglementés sont bloqués pour cette alternative ; cela ne constitue pas une classification juridique exhaustive des tâches.

Les habitants disponibles du territoire ayant la compétence déclarée proposent librement leurs heures, dans leur capacité. Le scénario refuse la réservation d’un participant déjà affecté à une mission ou à un autre plan actif ; il ne dispose pas encore d’un vrai agenda. Un tiers indépendant vérifie le plan, puis le choix exige assez d’heures et une nouvelle vérification de disponibilité. La valeur indicative du temps offert est affichée à côté du coût budgétaire. À la réception du plan, les heures réellement effectuées sont déclarées séparément.

Les inscriptions nouvelles ferment à l’échéance. Un participant peut retirer son engagement avant la fin des travaux, même après le choix. Si le plan était retenu, le dossier est suspendu, et sa réalisation ne peut plus être déclarée normalement. Le pilote applique le repli et peut interrompre le dossier en conservant les coûts engagés ; un nouveau périmètre demande un nouveau dossier. Pas d’affectation forcée, de pénalité de non-participation, de notation de valeur des citoyens ou de remise fiscale automatique.

## Budget et causalité

La fiche affiche cause supposée, action, effet attendu, indicateur et incertitude, puis le résultat observé. Le lien vers une proposition permet d’explorer son arbre complet ; aucune cause n’est ajoutée automatiquement au texte d’une consultation gelée. Les missions liées conservent leurs propres coûts : les deux modules ne sont pas additionnés comme des dépenses nouvelles.

La page budget affiche séparément le nombre et le coût des dossiers ayant un paiement fictif après contrôle. Les données officielles OFGL/DGFiP ne sont jamais modifiées ni diminuées de ces montants. L’écart à une référence déclarative n’est pas une économie certifiée, et encore moins une réduction individuelle d’impôt. L’objectif de 1 €/habitant/an n’est pas validé par ce module.

## Avant une utilisation réelle

Il reste nécessaire de définir et contrôler les habilitations de l’acheteur, des entreprises et des examinateurs, les conflits d’intérêts réels, les assurances et obligations sociales, les règles de passation/publicité et de conservation, les secrets d’affaires, les pièces justificatives vérifiables, les voies de recours, les droits d’accès, le rapprochement avec les systèmes comptables, l’agenda et les notifications. Un vote citoyen ne vaut pas engagement juridique de la commune. Les tâches régulières nécessaires à la continuité ne doivent pas dépendre exclusivement de volontaires.

Sources officielles consultées le 30 octobre 2026 :

- [Code de la commande publique, article L2132-1 : confidentialité](https://www.legifrance.gouv.fr/codes/section_lc/LEGITEXT000037701019/LEGISCTA000037703577/).
- [DAJ : secret des affaires et communication des documents](https://www.economie.gouv.fr/daj/lettre-de-la-daj-le-conseil-detat-precise-les-effets-du-secret-des-affaires-sur-le-droit-la).
- [Service public : documents de consultation et profil d’acheteur](https://entreprendre.service-public.gouv.fr/vosdroits/F32130).
- [Réponse ministérielle : entretien bénévole des voies communales et responsabilité](https://www.senat.fr/questions/base/2017/qSEQ171001684.html).

## Revue de l’interface

| Question | Choix retenu | Risque et réponse |
| --- | --- | --- |
| Est-ce visible ? | Entrée « Besoins et devis », filtres par ville, lien depuis missions et budget. | Les dossiers longs sont accessibles par une fiche dédiée ; la liste reste compacte. |
| Est-ce compréhensible ? | Étapes nommées, statuts français, actions près des éléments concernés. | Les identités sont fictives et la séparation des personnes est rappelée. |
| Peut-on améliorer ? | Saisie en euros, prix conservés en centimes, retour au dossier après chaque opération. | Les postes textuels demandent encore une syntaxe explicite ; exemple de calcul visible avant dépôt. |
| Quel effet indésirable ? | Transparence des dépenses et comparaison des prix. | Ne pas publier des secrets réels, accuser automatiquement ni transformer le bénévolat en obligation. |
| Comment y pallier ? | Dépôts chiffrés, examen indépendant, historique, repli, budget distinct des données officielles. | Validation humaine et règles de commande publique restent indispensables avant toute exploitation. |

## Vérifications de livraison

- 90 tests PHPUnit, 53 302 assertions : confidentialité dans HTML/API/exports, signatures vérifiables après dépôt, versions/retraits, échéance, conformité et tiers distincts, signalements, classement sur la durée, avenants, réception avec correction, paiement sans double enregistrement, consentement et conflits de disponibilité, tâches réglementées, conversion exacte euros/centimes et protection CSRF/révision.
- Suites existantes : 99 contrôles d’intégration, 64 contrôles de correction et 12 sondes d’audit, sans échec. Cela ne constitue pas une certification ni un audit exhaustif de sécurité.
- PHPStan sans erreur, 25 modèles Twig et 15 fichiers YAML valides.
- Parcours navigateur sans dépendance à JavaScript : création d’un besoin, devis de 3 000 €, contenu non visible avant ouverture, ouverture après l’échéance, examen par un autre personnage, choix motivé, début, résultat à 2 850 €, contrôle par un tiers, paiement fictif enregistré et coût final visible en tête de dossier.
- Vérification dans un cadre de 390 pixels : largeur utile et document à 375 pixels, sans débordement horizontal de page. Cette vérification ne remplace pas un test sur appareil physique ni un audit d’accessibilité complet.
