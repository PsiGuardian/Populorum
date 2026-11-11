<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Public, non-operational resilience plans for the 28 simulated civic services.
 * Not a PGSSE, an ORSEC plan, an incident command system or work authorization.
 * Keep tactical infrastructure details and real emergency contacts out of this catalogue.
 */
final class ServiceContinuityPlans
{
    private const DOMAINS = [
        'water' => [
            'objective' => 'Garantir une eau saine, distribuée en quantité suffisante, avec un goût et une odeur acceptables.',
            'prevent' => 'Mettre à jour le PGSSE avec l’exploitant : qualité du captage à la distribution, contrôle sanitaire, prévention des retours de flux et sécurisation des accès autorisés.',
            'alternative' => 'Prévoir avec la collectivité et l’exploitant une alimentation de substitution contrôlée, accessible aussi aux personnes isolées.',
            'restore' => 'Ne rétablir l’usage alimentaire qu’après remise en état, contrôles adaptés au danger et validation des autorités sanitaires compétentes.',
            'indicator' => 'Qualité sanitaire validée, continuité de distribution, plaintes sur goût/odeur, accès des personnes vulnérables.',
        ],
        'food' => [
            'objective' => 'Maintenir l’accès équitable à des aliments sûrs et à des produits adaptés aux besoins nutritionnels.',
            'prevent' => 'Identifier les dépendances aux producteurs, aux transports, à l’eau et au stockage alimentaire.',
            'alternative' => 'Organiser des approvisionnements de substitution autorisés et une distribution accessible aux personnes vulnérables.',
            'restore' => 'Vérifier les conditions sanitaires, la traçabilité et les flux rétablis avec les professionnels compétents.',
            'indicator' => 'Disponibilité des denrées, conformité sanitaire et couverture des besoins prioritaires.',
        ],
        'energy' => [
            'objective' => 'Préserver l’alimentation énergétique des fonctions vitales sans exposer les installations.',
            'prevent' => 'Identifier avec les gestionnaires les besoins critiques, les interdépendances et les solutions de relève certifiées.',
            'alternative' => 'Prioriser la continuité des établissements essentiels selon les décisions des gestionnaires habilités.',
            'restore' => 'Rétablir par les opérateurs après contrôles de sécurité et inspection des installations concernées.',
            'indicator' => 'Disponibilité des services essentiels et conformité des remises en service.',
        ],
        'health' => [
            'objective' => 'Garantir l’accès aux soins urgents, aux traitements indispensables et aux personnes dépendantes.',
            'prevent' => 'Préparer la coordination sanitaire, les stocks critiques et la continuité des dossiers et des communications.',
            'alternative' => 'Prévoir des lieux et circuits de soins alternatifs validés par les autorités de santé.',
            'restore' => 'Vérifier l’accessibilité, les ressources cliniques et la sécurité des soins avant retour au fonctionnement normal.',
            'indicator' => 'Continuité des soins essentiels et accès des populations prioritaires.',
        ],
        'rescue' => [
            'objective' => 'Conserver l’alerte, l’orientation et la capacité des services de secours.',
            'prevent' => 'Organiser les interfaces entre services de secours, collectivités et opérateurs vitaux.',
            'alternative' => 'Utiliser les moyens de communication et d’accueil de substitution validés par l’autorité compétente.',
            'restore' => 'Vérifier les voies d’alerte, les équipements et les capacités de réponse.',
            'indicator' => 'Accès effectif à l’alerte et disponibilité des secours.',
        ],
        'construction' => [
            'objective' => 'Assurer les réparations nécessaires aux bâtiments essentiels sans compromettre la sécurité.',
            'prevent' => 'Identifier les bâtiments publics prioritaires, les diagnostics et les entreprises habilitées.',
            'alternative' => 'Prévoir des locaux temporaires conformes et accessibles.',
            'restore' => 'Soumettre les ouvrages à inspection et réception par les responsables habilités.',
            'indicator' => 'Bâtiments essentiels utilisables et conformité des réparations.',
        ],
        'demolition' => [
            'objective' => 'Sécuriser les structures dangereuses et limiter les dommages secondaires.',
            'prevent' => 'Tenir une cartographie de risques non publique et des procédures de consignation professionnelles.',
            'alternative' => 'Protéger les personnes et proposer des cheminements ou locaux provisoires.',
            'restore' => 'Faire contrôler les zones et la gestion des déchets avant restitution des lieux.',
            'indicator' => 'Réduction des expositions et réouverture vérifiée des espaces.',
        ],
        'freight' => [
            'objective' => 'Maintenir l’acheminement des biens indispensables, en particulier eau, médicaments et alimentation.',
            'prevent' => 'Identifier les volumes critiques et la dépendance aux transporteurs et à l’énergie.',
            'alternative' => 'Prévoir plusieurs prestataires autorisés et des moyens logistiques de substitution.',
            'restore' => 'Confirmer la réception, la conformité des biens et la reprise des livraisons.',
            'indicator' => 'Livraisons essentielles honorées et traçabilité des approvisionnements.',
        ],
        'transport' => [
            'objective' => 'Préserver la mobilité vers les soins, les ressources vitales et les lieux de protection.',
            'prevent' => 'Identifier les publics prioritaires et les dépendances aux carburants et aux infrastructures.',
            'alternative' => 'Organiser des solutions adaptées d’accompagnement et de transport accessibles.',
            'restore' => 'Confirmer les autorisations, la sécurité des parcours et la capacité des opérateurs.',
            'indicator' => 'Accès effectif aux destinations essentielles.',
        ],
        'maintenance' => [
            'objective' => 'Éviter que les petites défaillances deviennent des ruptures de services vitaux.',
            'prevent' => 'Prévoir maintenance préventive, pièces de rechange et contrôle des compétences des intervenants.',
            'alternative' => 'Mobiliser des prestataires autorisés et des équipements temporaires vérifiés.',
            'restore' => 'Documenter l’intervention, l’essai de fonctionnement et la vérification indépendante.',
            'indicator' => 'Incidents récurrents et délais de remise en service documentés.',
        ],
        'waste' => [
            'objective' => 'Maintenir l’hygiène collective, l’évacuation des déchets et la prévention des risques sanitaires.',
            'prevent' => 'Prévoir les besoins de collecte, de traitement et la protection des agents.',
            'alternative' => 'Utiliser uniquement des dispositifs temporaires de collecte et de stockage autorisés.',
            'restore' => 'Contrôler propreté, salubrité et capacité de traitement.',
            'indicator' => 'Collecte sécurisée et nuisances sanitaires signalées.',
        ],
        'housing' => [
            'objective' => 'Préserver un abri salubre, accessible et protecteur pour les habitants.',
            'prevent' => 'Évaluer les besoins d’hébergement, d’eau, de chauffage et d’accessibilité.',
            'alternative' => 'Coordonner les capacités d’hébergement autorisées et l’aide aux personnes isolées.',
            'restore' => 'Contrôler la salubrité et la sûreté des locaux avant retour.',
            'indicator' => 'Personnes hébergées en sécurité et conditions sanitaires assurées.',
        ],
        'education' => [
            'objective' => 'Maintenir l’instruction et l’accompagnement des mineurs en situation de crise.',
            'prevent' => 'Identifier les besoins pédagogiques et les solutions hors ligne compatibles avec la protection des enfants.',
            'alternative' => 'Prévoir des lieux ou modalités de continuité validés par les autorités éducatives.',
            'restore' => 'Vérifier l’accès des élèves, la sécurité et la reprise de l’enseignement.',
            'indicator' => 'Continuité éducative et accompagnement des élèves fragiles.',
        ],
        'care' => [
            'objective' => 'Maintenir l’accès des personnes âgées, handicapées ou isolées aux aides vitales.',
            'prevent' => 'Coordonner les acteurs sociaux et les ressources essentielles sans publier de données nominatives.',
            'alternative' => 'Organiser les visites et relais de proximité par des intervenants consentants et habilités.',
            'restore' => 'Vérifier le retour de l’accompagnement et les besoins non couverts.',
            'indicator' => 'Accès effectif à l’aide et absence d’isolement non pris en charge.',
        ],
        'digital' => [
            'objective' => 'Garantir des canaux d’information fiables et la continuité des outils essentiels.',
            'prevent' => 'Préparer sauvegardes, modes hors ligne, authentification forte et messages institutionnels vérifiables.',
            'alternative' => 'Prévoir des communications non numériques et la diffusion multicanal.',
            'restore' => 'Contrôler intégrité, sécurité et accès avant réouverture des applications.',
            'indicator' => 'Accessibilité de l’information et intégrité des communications.',
        ],
        'administration' => [
            'objective' => 'Maintenir l’accès aux actes essentiels, à l’information publique et aux droits.',
            'prevent' => 'Prévoir la continuité des dossiers prioritaires et des moyens d’accueil.',
            'alternative' => 'Prévoir des procédures provisoires encadrées et des points d’accueil accessibles.',
            'restore' => 'Rapprocher les dossiers, traiter les retards et contrôler les décisions.',
            'indicator' => 'Demandes essentielles traitées et continuité des droits.',
        ],
        'justice' => [
            'objective' => 'Préserver les droits fondamentaux et l’accès aux autorités judiciaires compétentes.',
            'prevent' => 'Documenter les procédures de continuité institutionnelle et de conservation des preuves.',
            'alternative' => 'Prévoir des canaux autorisés pour les demandes urgentes et l’assistance juridique.',
            'restore' => 'Auditer la traçabilité des actes et les garanties procédurales.',
            'indicator' => 'Maintien des garanties et traitement des situations urgentes.',
        ],
        'environment' => [
            'objective' => 'Identifier les dégradations environnementales menaçant la santé et les ressources.',
            'prevent' => 'Suivre les indicateurs publics de qualité environnementale avec leurs limites.',
            'alternative' => 'Mettre en place des observations et alertes validées par les organismes compétents.',
            'restore' => 'Vérifier la réduction des impacts et la traçabilité des mesures.',
            'indicator' => 'Qualité mesurée des milieux et risques sanitaires traités.',
        ],
        'culture' => [
            'objective' => 'Préserver les liens sociaux, les lieux culturels et le patrimoine accessible.',
            'prevent' => 'Identifier les espaces et collections prioritaires sans exposer les sites sensibles.',
            'alternative' => 'Prévoir des activités sûres, adaptées et des ressources hors ligne.',
            'restore' => 'Contrôler l’accueil et la conservation du patrimoine.',
            'indicator' => 'Maintien des liens sociaux et accès à des activités culturelles sûres.',
        ],
        'defense' => [
            'objective' => 'Assurer la continuité institutionnelle dans le respect des compétences exclusives de l’État.',
            'prevent' => 'Conserver uniquement un cadre civique public et les interfaces avec la protection des populations.',
            'alternative' => 'S’appuyer sur les dispositifs institutionnels légalement compétents.',
            'restore' => 'Faire valider les conditions de réouverture des services civils par les autorités.',
            'indicator' => 'Protection des personnes et accessibilité des services civils essentiels.',
        ],
        'diplomacy' => [
            'objective' => 'Maintenir l’accès à l’assistance et à l’information diplomatique officielle.',
            'prevent' => 'Identifier les interfaces publiques avec les institutions et représentations compétentes.',
            'alternative' => 'Conserver des canaux d’information institutionnels de substitution.',
            'restore' => 'Vérifier l’accessibilité de l’assistance et la fiabilité des messages.',
            'indicator' => 'Accès aux informations et aux démarches officielles.',
        ],
        'money' => [
            'objective' => 'Préserver l’accès aux moyens de paiement, aux ressources de subsistance et aux prestations.',
            'prevent' => 'Identifier les dépendances aux réseaux, services financiers et autorités régulatrices.',
            'alternative' => 'Prévoir des procédures temporaires autorisées et accessibles, sans divulguer de données financières privées.',
            'restore' => 'Rapprocher les opérations et vérifier la conformité auprès des institutions compétentes.',
            'indicator' => 'Accès aux paiements indispensables et intégrité des transactions.',
        ],
        'agriculture' => [
            'objective' => 'Maintenir la production agricole sûre et l’accès à l’eau et aux intrants autorisés.',
            'prevent' => 'Évaluer les besoins saisonniers, la qualité de l’eau et les risques sanitaires.',
            'alternative' => 'Coordonner la continuité des productions essentielles et des approvisionnements sûrs.',
            'restore' => 'Contrôler l’état des ressources, les récoltes et la sécurité alimentaire.',
            'indicator' => 'Capacités de production, sécurité des aliments et pertes constatées.',
        ],
        'industry' => [
            'objective' => 'Maintenir la production des biens indispensables sans compromettre la sécurité.',
            'prevent' => 'Identifier les besoins critiques, la maintenance et la protection des travailleurs.',
            'alternative' => 'Prévoir des fournisseurs et capacités de repli qualifiés.',
            'restore' => 'Vérifier la sécurité et la conformité avant remise en production.',
            'indicator' => 'Disponibilité des biens essentiels et incidents de sécurité.',
        ],
        'research' => [
            'objective' => 'Conserver l’observation, les résultats scientifiques et la possibilité de les vérifier.',
            'prevent' => 'Préparer des sauvegardes documentées et des protocoles de qualité.',
            'alternative' => 'Maintenir un réseau de compétences et des méthodes vérifiables hors ligne.',
            'restore' => 'Contrôler la reproductibilité, la provenance et la qualité des résultats.',
            'indicator' => 'Données préservées, évaluations réalisées et preuves disponibles.',
        ],
        'employment' => [
            'objective' => 'Soutenir les personnes sans emploi et les travailleurs mobilisables volontairement.',
            'prevent' => 'Identifier les besoins de formation, de protection et les compétences déclarées.',
            'alternative' => 'Prévoir des mises en relation volontaires et des solutions d’information accessibles.',
            'restore' => 'Vérifier les conditions de travail, les droits et la continuité des formations.',
            'indicator' => 'Accès aux droits et capacité de formation et d’emploi.',
        ],
        'social_insurance' => [
            'objective' => 'Garantir le maintien des prestations sociales et de la couverture des bénéficiaires.',
            'prevent' => 'Identifier les traitements prioritaires et assurer la résilience des données administratives.',
            'alternative' => 'Prévoir des procédures légalement autorisées en cas d’indisponibilité numérique.',
            'restore' => 'Rapprocher les versements, corriger les ruptures et auditer les droits.',
            'indicator' => 'Prestations essentielles versées et continuité de couverture.',
        ],
        'sport' => [
            'objective' => 'Préserver l’activité physique et les relations associatives sans mise en danger.',
            'prevent' => 'Évaluer la sûreté et l’accessibilité des installations et des intervenants.',
            'alternative' => 'Prévoir des activités adaptées dans des espaces autorisés.',
            'restore' => 'Vérifier les installations et les conditions d’accueil.',
            'indicator' => 'Pratique accessible et conditions de sécurité assurées.',
        ],
    ];

    public static function all(): array
    {
        $catalog = [];
        foreach (EssentialServices::CATALOG as $id => $service) {
            $catalog[$id] = self::for($id);
        }

        return $catalog;
    }

    public static function for(string $serviceId): array
    {
        if (!isset(EssentialServices::CATALOG[$serviceId], self::DOMAINS[$serviceId])) {
            throw new \InvalidArgumentException('Service de continuité inconnu.');
        }
        $service = EssentialServices::CATALOG[$serviceId];

        return [
            'id' => $serviceId,
            'label' => $service['label'],
            'owner' => $service['owner'],
            'regulated' => $service['regulated'],
            'priority' => $service['priority'],
            'depends' => array_map(
                static fn (string $dependency): string => EssentialServices::CATALOG[$dependency]['label'],
                $service['depends'],
            ),
            'goal' => self::DOMAINS[$serviceId]['objective'],
            'prevent' => self::DOMAINS[$serviceId]['prevent'],
            'alternative' => self::DOMAINS[$serviceId]['alternative'],
            'restore' => self::DOMAINS[$serviceId]['restore'],
            'indicator' => self::DOMAINS[$serviceId]['indicator'],
            'scenarios' => $serviceId === 'water' ? self::waterScenarios() : [],
            'sources' => $serviceId === 'water' ? self::waterSources() : [],
        ];
    }

    public static function phases(): array
    {
        return [
            ['label' => 'Prévenir', 'detail' => 'Évaluer les risques, les dépendances et les responsabilités.'],
            ['label' => 'Détecter', 'detail' => 'Recueillir des signaux fiables et signaler les anomalies.'],
            ['label' => 'Protéger', 'detail' => 'Mettre en sécurité les personnes et basculer vers une continuité approuvée.'],
            ['label' => 'Vérifier', 'detail' => 'Documenter les tests indépendants et autoriser le rétablissement.'],
        ];
    }

    private static function waterScenarios(): array
    {
        return [
            [
                'id' => 'contamination',
                'name' => 'Contamination suspectée, y compris substances volatiles ou gaz',
                'signal' => 'Anomalie signalée dans la qualité de l’eau, indices concordants ou suspicion d’atteinte du réseau.',
                'protect' => 'Ne pas présumer que l’eau est consommable : alerter immédiatement l’exploitant, la collectivité et l’ARS. Les restrictions d’usage relèvent des autorités compétentes.',
                'continue' => 'Activer une fourniture de substitution validée ; informer les habitants et protéger prioritairement les personnes dépendantes.',
                'verify' => 'L’exploitant et les autorités déterminent les analyses adaptées au contaminant possible et valident le retour aux usages.',
            ],
            [
                'id' => 'intrusion',
                'name' => 'Protection contre les intrusions et retours de fluides',
                'signal' => 'Perte d’intégrité constatée, accès non autorisé suspecté ou anomalie de qualité détectée.',
                'protect' => 'Mettre en œuvre une défense multicouche : séparation des circuits, prévention certifiée des retours de flux, contrôle de l’intégrité et accès strictement autorisés.',
                'continue' => 'Maintenir des voies de secours sûres et éviter toute communication publique sur l’emplacement des organes sensibles.',
                'verify' => 'Inspections et contrôles sanitaires réalisés par les exploitants habilités, avec traçabilité.',
            ],
            [
                'id' => 'leak',
                'name' => 'Fuite ou rupture de canalisation',
                'signal' => 'Baisse de service, eau au sol, perte mesurée ou signalement d’usagers.',
                'protect' => 'Sécuriser la zone, faire diagnostiquer puis isoler le tronçon concerné par le gestionnaire autorisé ; informer sur toute restriction d’usage.',
                'continue' => 'Faire mobiliser une distribution alternative approuvée pendant la réparation.',
                'verify' => 'Après réparation, contrôle de l’intégrité, remise en état sanitaire et analyses nécessaires avant reprise de consommation.',
            ],
            [
                'id' => 'obstruction',
                'name' => 'Obstruction ou chute de débit',
                'signal' => 'Baisse durable du débit, interruption d’alimentation ou variation anormale relevée par l’exploitant.',
                'protect' => 'Diagnostiquer sans intervention improvisée ; une opération sur un réseau d’eau public relève des professionnels habilités.',
                'continue' => 'Réorienter l’approvisionnement et assurer une desserte de substitution en attendant la réparation.',
                'verify' => 'Confirmer le retour du débit et de la qualité sanitaire avant réouverture du service.',
            ],
            [
                'id' => 'outage',
                'name' => 'Coupure électrique prolongée',
                'signal' => 'Indisponibilité durable de l’énergie affectant les capacités de traitement ou de distribution.',
                'protect' => 'Activer la coordination de crise entre opérateurs, mairie, ARS et préfecture selon les compétences applicables.',
                'continue' => 'Prévoir des capacités alternatives validées, des annonces multicanal et un accès prioritaire aux besoins vitaux.',
                'verify' => 'Restaurer progressivement les étapes de production et de distribution sous contrôle sanitaire.',
            ],
            [
                'id' => 'conflict',
                'name' => 'Conflit civil ou armé : perturbations multiples',
                'signal' => 'Perturbations combinées des transports, communications, personnels et approvisionnements.',
                'protect' => 'Coordonner la protection des personnes avec les exploitants et autorités compétentes, sans exposer les installations sensibles.',
                'continue' => 'Prévoir des ressources alternatives contrôlées, des communications redondantes et une assistance accessible aux personnes isolées.',
                'verify' => 'Rétablir les fonctions par étapes, avec contrôle sanitaire et vérification des capacités de distribution.',
            ],
        ];
    }

    private static function waterSources(): array
    {
        return [
            [
                'title' => 'PGSSE : arrêté français du 3 janvier 2023',
                'url' => 'https://www.legifrance.gouv.fr/loda/id/JORFTEXT000046967963/',
            ],
            [
                'title' => 'Ministère de la Santé : plans de gestion de l’eau',
                'url' => 'https://sante.gouv.fr/sante-et-environnement/eaux/article/les-plans-de-gestion-de-la-securite-sanitaire-des-eaux-pgsse',
            ],
            [
                'title' => 'OMS : manuel de gestion de la sécurité sanitaire de l’eau',
                'url' => 'https://www.who.int/fr/publications/i/item/9789240067691',
            ],
            [
                'title' => 'Ministère de la Transition écologique : alimentation de substitution',
                'url' => 'https://www.ecologie.gouv.fr/actualites/savoir-secheresse',
            ],
            [
                'title' => 'CDC : consignes en cas de contamination chimique',
                'url' => 'https://www.cdc.gov/water-emergency/about/drinking-water-advisories-an-overview.html',
            ],
        ];
    }
}
