# Profiler Shield — accès public de démonstration

Cette page contient **volontairement des identifiants publics**, réservés à l’instance de démonstration isolée de Populorum. Ils ne protègent aucune donnée de production.

| Élément | Valeur |
| --- | --- |
| Bundle | `omegaguardian/profiler-shield` |
| Dépôt | [PsiGuardian/profiler-shield](https://github.com/PsiGuardian/profiler-shield) |
| Branche demandée | `dev-1.0.1` |
| Révision installée et testée | `3595f1e483e4ea1ee2dcbd6ddbabe7e2976e1783` |
| Application | Symfony 8.1, PHP 8.4 minimum |
| Chemin de connexion | `/demo-profiler-shield-populorum` |
| **Identifiant public** | **`demo`** |
| **Mot de passe public** | **`Populorum-Demo!2026`** |
| Rôle Guardian | `developer`, limité à la lecture dans cette application |
| Authentification multifacteur | Non activée pour ce compte partagé de démo |

## Essayer après mise en ligne

1. Ouvrir le domaine HTTPS de la démonstration, puis le chemin `/demo-profiler-shield-populorum`.
2. Se connecter avec les identifiants publics ci-dessus.
3. Revenir à l’accueil : la barre du profiler Symfony devient disponible sur les requêtes autorisées.
4. Ouvrir le profiler depuis cette barre ou consulter `/_profiler/`.
5. Se déconnecter du panneau Guardian pour revenir au comportement visiteur.

Le domaine sera celui choisi par l’hébergeur de cette application Symfony. L’ancienne adresse Sites ne peut pas exécuter PHP ni ce bundle et n’est pas l’URL de cette version.

## Limites du compte public

Le compte permet d’essayer la connexion et de consulter les diagnostics pédagogiques. Les changements de mot de passe, MFA, comptes et politiques sont refusés côté application. Ainsi, un visiteur ne peut pas changer les identifiants partagés ni ouvrir les diagnostics aux visiteurs non connectés. La connexion HTTP de l’opérateur est interdite sur cette instance ; l’entretien du coffre est réservé à la CLI.

PHPInfo, l’ouverture de fichiers et l’intégration Xdebug sont fermés dans cette démo publique. Les clés d’environnement, cookies, corps de requête et données de session sont retirés du collecteur public. Les collecteurs de formulaires, SQL et exceptions ne sont pas publiés. Le bundle d’origine n’a pas été modifié pour ces restrictions : elles résident dans l’application Symfony.

Les identités Guardian n’accordent aucun privilège de vote. Les personnages, choix, coûts et expériences de Populorum restent fictifs. Paris, Marseille et Le Havre sont les seules villes du référentiel initial.

## Installation reproductible

Voir [le guide Symfony](apps/symfony/README.md). Le fichier `composer.lock` fige le bundle testé. La contrainte Composer utilise `dev-dev-1.0.1` et le fichier `composer.lock` fige la révision indiquée : `dev-` est le préfixe de Composer pour une branche, dont le nom réel est déjà `dev-1.0.1`.

Le mot de passe ci-dessus est créé par `php bin/console app:demo:prepare --env=demo`. Les clés de chiffrement Guardian, le secret Symfony, les identifiants privés et le coffre sont générés localement, exclus de Git et jamais documentés ici. Ce compte public ne doit pas être créé sur une application contenant des données confidentielles réelles.
