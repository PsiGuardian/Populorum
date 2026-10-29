# Vérifications — baseline 0.1.0

Date : 29 octobre 2026.

- **79 tests automatisés réussis, 0 échec.** Commande : `node --experimental-vm-modules --test tests/*.test.mjs`. Compte rendu brut : `test-output.txt`.
- Tests moteur : admissibilité, vote et révision sans double comptage, gel, quorum/majorité/égalité, protocole, observations et périodes, source, motifs, autonomie territoriale, finances en centimes, recours, gouvernance simulée, contrôles de signatures et d’empreintes.
- Tests d’API : vrais gestionnaires HTTP transpilés, migration SQL réelle sur SQLite en mémoire ; identité Sites et liaison D1 remplacées par des adaptateurs de test. Refus anonyme, origine tierce, contenu non JSON, requêtes concurrentes (une réussite, un 409), isolation de propriétaires, état persistant, charge SQL hostile, absence de clé privée dans les réponses.
- TypeScript : `tsc --noEmit`, réussi.
- Construction de production : helper Sites `build-site.mjs`, réussi.
- Migration D1 appliquée à la base locale, réussie.
- Navigateur sur l’aperçu géré : accueil, consultation et règles, information nominative préalable, fermeture du dialogue avec Échap, charte en anglais, navigation dans les neuf autres espaces. Présentation de bureau inspectée visuellement.

## Limites de preuve

L’aperçu ne présente pas d’identité ChatGPT connectée : les écritures y restent volontairement désactivées. Le parcours complet de vote par navigateur connecté en production n’est donc pas déclaré testé. Les tests HTTP couvrent le moteur et la persistance locale, pas la frontière d’authentification hébergée. Le déploiement final est vérifié par l’état retourné par Sites.

Aucun audit WCAG/RGAA complet, test réel sur téléphone, banc de charge, mesure de p95, audit externe, restauration chiffrée ou réseau décentralisé effectif n’a été exécuté. Voir `ACCEPTANCE.md` pour les 68 scénarios de référence et leurs limites explicites.
