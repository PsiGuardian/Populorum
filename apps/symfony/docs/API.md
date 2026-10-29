# API v1 et événements

Toutes les données du démonstrateur sont fictives. Les noms des communes et les références documentaires peuvent être réels. Aucun effet juridique et aucune identité civile n’est vérifiée par cette API.

## Ressources publiques

`GET /api/v1/{resource}` : `territories`, `proposals`, `pathways`, `graphs`, `consultations`, `results`, `observations`, `evaluations`, `proofs`, `finance`, `nodes`, `rules`, `alerts`.

Paramètres : `page` (1 minimum), `limit` (1–100, défaut 20), `q` (recherche textuelle), `territory` (identifiant), `sort` (`date`, `title`, `status`, `theme`). Réponse : `data` et `meta` contenant total, page, limite, nombre de pages, révision et marqueur de simulation. Tri explicite, aucune personnalisation politique. Erreur de tri : 400. Les ressources sont liées au laboratoire de la session ou du compte connecté ; elles ne parcourent pas les scénarios privés d’autres visiteurs.

`GET /api/v1/state` conserve l’export global compatible avec le premier démonstrateur. Les clés et identifiants anti-rejeu privés sont exclus. `GET /api/v1/replica` expose seulement le laboratoire qu’un opérateur a explicitement publié par CLI ; 404 en l’absence de publication. Aucun laboratoire de navigateur n’est automatiquement publié.

## Écritures protégées

`GET /api/v1/session` retourne le jeton CSRF de **la session courante**, sa révision, le personnage actif et le statut de connexion. Réponse `no-store`, aucune autorisation CORS.

`POST /api/v1/actions/{type}` utilise les mêmes actions et contrôles métier que les formulaires. En-tête `X-CSRF-Token`, corps JSON :

```json
{
  "revision": 0,
  "operationId": "une-valeur-aleatoire-unique-au-moins-16-caracteres",
  "payload": { "consent": true }
}
```

L’acteur est imposé par la session/connexion : le champ `citizenId` envoyé par un client est refusé. Les champs inconnus sont refusés ; pas de mot de passe, de clé ni de jeton dans une charge métier. Limite du corps : 256 Kio. Les choix, rôles, règles, périodes, sources et propriétés des graphes sont vérifiés côté PHP. Réponses : 200 en cas de succès, 400 pour données invalides, 401 si connexion requise, 403 pour CSRF/autorisation, 404 si action/objet inconnu, 409 pour conflit ou rejeu, 413 pour corps excessif, 429 pour limitation de débit. Le résultat contient la nouvelle révision.

Les formulaires et l’API partagent un plafond initial de 120 écritures/minute par session et adresse technique, indépendant de l’admissibilité civique. La connexion est limitée séparément à cinq tentatives/minute par Symfony Security. Adapter les plafonds au pilote et au proxy réel ; ne jamais utiliser l’adresse réseau comme preuve d’identité.

## SSE

`GET /api/v1/events` est un flux `text/event-stream`. Événement nommé `state` : révision, consultations et marqueur de simulation. `id` = révision ; `Last-Event-ID` évite les renvois inutiles. Le flux dure au maximum 20 s puis le navigateur se reconnecte ; l’état est vérifié chaque seconde, sans conserver le verrou de session. `?once=1` renvoie une observation immédiate pour la recette. Le flux est une amélioration facultative : le vote et les résultats restent accessibles sans JavaScript. Prévoir assez de workers PHP et désactiver le buffering du proxy ; ce mécanisme n’est pas une preuve de capacité nationale.

## Compatibilité

Versionner une rupture de schéma dans une future API v2. Les ajouts de champs ne changent pas les règles d’une consultation figée. Les messages Messenger contiennent uniquement scénario et événement ; ils ne sont pas un second canal de vote.
