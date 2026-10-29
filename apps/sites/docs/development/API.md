# API version 1 — simulateur Sites

`GET /api/v1/state` renvoie `{state, revision, authenticated, convergence}`. Sans connexion : jeu pédagogique de lecture, sans écriture. Avec connexion : espace D1 lié à l’identifiant Sites du compte. Réponses `Cache-Control: no-store`.

`POST /api/v1/state` exige l’identité injectée par Sites, JSON, origine même site lorsqu’un en-tête Origin est présent, corps <=100 000 caractères, révision courante et identifiant d’opération unique. Une erreur d’origine retourne 403, une absence d’identité 401, un conflit ou rejeu 409. Les champs et droits métier sont validés côté serveur. Le client ne peut fournir ni propriétaire ni identité de compte.

```json
{"revision":0,"action":{"type":"citizen.consent","payload":{"citizenId":"u1","consent":true},"operationId":"identifiant-unique-au-moins-huit-caracteres"}}
```

La réponse de mutation contient le même format que GET et la révision incrémentée. En cas de 409, recharger avant de soumettre une nouvelle opération ; ne pas rejouer automatiquement un vote. L’interface actualise GET toutes les 2,5 secondes.

Actions disponibles :

- Identity : `citizen.create`, `citizen.consent`, `citizen.update`, `citizen.erase`.
- Pathway : `proposal.create`, `proposal.publish`, `proposal.revise`, `territory.adapt`.
- Consultation : `consultation.open`, `consultation.close`, `vote.cast`.
- Experiment : `experiment.create`, `observation.add`, `experiment.evaluate`.
- Transparency : `integrity.verify` (lecture), `integrity.alert`, `finance.add`.
- Governance : `governance.propose`, `governance.approve`, `moderation.report`, `moderation.appeal`.
- Federation virtuelle : `node.configure`, `node.sync`.
- Contributions et préférences : `resource.add`, `preferences.update`.

Les schémas de champs et règles exécutables sont dans `lib/domain.mjs`. Le contrôleur n’exécute aucune mutation en dehors du moteur. Le journal signe uniquement des données fictives ; la clé privée, la liste anti-rejeu et l’identifiant du propriétaire ne sont jamais renvoyés.

## Limites de cette API

Endpoint d’état agrégé pour le pilote, sans pagination ; limite métier à 10 000 opérations. Ce n’est pas encore l’API de ressources paginée prévue au cahier des charges. Aucune API de vote civique réel. Le choix du personnage est une fonction pédagogique du propriétaire de l’espace, pas une authentification de ce citoyen. Le système repose sur la validation des en-têtes d’identité à la frontière Sites ; une instance auto-hébergée ne doit jamais faire confiance à des en-têtes envoyés directement par un client non authentifié.
