<?php

declare(strict_types=1);

namespace App\Service;

/** Broad service map, not a transfer of legal powers or an exhaustive national administration. */
final class EssentialServices
{
    public const CATALOG = [
        'water' => [
            'label' => 'Eau potable et assainissement',
            'owner' => 'Intercommunalité / opérateur',
            'regulated' => true,
            'priority' => 1,
            'depends' => ['energy', 'maintenance'],
            'profession' => 'Technicien des réseaux d’eau',
        ],
        'food' => [
            'label' => 'Alimentation et distribution',
            'owner' => 'Producteurs, commerces et collectivités',
            'regulated' => false,
            'priority' => 1,
            'depends' => ['freight', 'water'],
            'profession' => 'Agent de logistique alimentaire',
        ],
        'energy' => [
            'label' => 'Énergie et réseaux',
            'owner' => 'Opérateurs / autorités organisatrices',
            'regulated' => true,
            'priority' => 1,
            'depends' => ['digital', 'maintenance'],
            'profession' => 'Technicien des réseaux énergétiques',
        ],
        'health' => [
            'label' => 'Santé et accès aux soins',
            'owner' => 'État, ARS et professionnels de santé',
            'regulated' => true,
            'priority' => 1,
            'depends' => ['water', 'energy', 'transport'],
            'profession' => 'Professionnel de santé fictif',
        ],
        'rescue' => [
            'label' => 'Secours, incendies et sécurité civile',
            'owner' => 'Services de secours / autorités compétentes',
            'regulated' => true,
            'priority' => 1,
            'depends' => ['transport', 'digital'],
            'profession' => 'Agent de sécurité civile fictif',
        ],
        'construction' => [
            'label' => 'Construction et rénovation',
            'owner' => 'Maîtrise d’ouvrage et entreprises habilitées',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['freight', 'energy'],
            'profession' => 'Artisan du bâtiment',
        ],
        'demolition' => [
            'label' => 'Déconstruction et réemploi',
            'owner' => 'Professionnels habilités / autorités compétentes',
            'regulated' => true,
            'priority' => 3,
            'depends' => ['waste', 'freight'],
            'profession' => 'Technicien de déconstruction',
        ],
        'freight' => [
            'label' => 'Acheminement et transport des ressources',
            'owner' => 'Opérateurs logistiques',
            'regulated' => false,
            'priority' => 2,
            'depends' => ['transport', 'energy'],
            'profession' => 'Coordinateur de logistique',
        ],
        'transport' => [
            'label' => 'Mobilité et transport des personnes',
            'owner' => 'Autorité organisatrice / opérateurs',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['energy', 'maintenance'],
            'profession' => 'Conducteur de transport fictif',
        ],
        'maintenance' => [
            'label' => 'Entretien, diagnostic et réparation',
            'owner' => 'Propriétaires / collectivités / prestataires',
            'regulated' => false,
            'priority' => 2,
            'depends' => ['freight'],
            'profession' => 'Agent d’entretien et de petite réparation',
        ],
        'waste' => [
            'label' => 'Déchets, propreté et recyclage',
            'owner' => 'Intercommunalité / opérateurs',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['freight', 'energy'],
            'profession' => 'Agent de valorisation des déchets',
        ],
        'housing' => [
            'label' => 'Logement et hébergement',
            'owner' => 'État, collectivités, bailleurs',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['construction', 'water'],
            'profession' => 'Coordinateur de l’hébergement',
        ],
        'education' => [
            'label' => 'Éducation et transmission',
            'owner' => 'État et collectivités selon les niveaux',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['digital', 'maintenance'],
            'profession' => 'Intervenant pédagogique fictif',
        ],
        'care' => [
            'label' => 'Solidarité, autonomie et aide de proximité',
            'owner' => 'Département, CCAS et associations',
            'regulated' => false,
            'priority' => 2,
            'depends' => ['food', 'transport'],
            'profession' => 'Coordinateur d’entraide',
        ],
        'digital' => [
            'label' => 'Télécommunications et services numériques',
            'owner' => 'Opérateurs / administrations',
            'regulated' => false,
            'priority' => 2,
            'depends' => ['energy'],
            'profession' => 'Technicien informatique',
        ],
        'administration' => [
            'label' => 'Administration, finances et accès aux droits',
            'owner' => 'État et collectivités',
            'regulated' => false,
            'priority' => 2,
            'depends' => ['digital'],
            'profession' => 'Agent administratif fictif',
        ],
        'justice' => [
            'label' => 'Justice, sûreté et protection des droits',
            'owner' => 'Autorités judiciaires et services compétents',
            'regulated' => true,
            'priority' => 1,
            'depends' => ['administration', 'digital'],
            'profession' => 'Professionnel du droit fictif',
        ],
        'environment' => [
            'label' => 'Environnement, climat et biodiversité',
            'owner' => 'État, collectivités et opérateurs',
            'regulated' => false,
            'priority' => 3,
            'depends' => ['water', 'waste'],
            'profession' => 'Agent de suivi environnemental',
        ],
        'culture' => [
            'label' => 'Culture, loisirs, distraction et patrimoine',
            'owner' => 'Collectivités, associations et acteurs culturels',
            'regulated' => false,
            'priority' => 4,
            'depends' => ['maintenance', 'transport'],
            'profession' => 'Animateur culturel fictif',
        ],
        'defense' => [
            'label' => 'Défense et souveraineté',
            'owner' => 'État exclusivement',
            'regulated' => true,
            'priority' => 1,
            'depends' => ['energy', 'digital'],
            'profession' => 'Spécialiste de continuité institutionnelle fictif',
        ],
        'diplomacy' => [
            'label' => 'Diplomatie et coopération internationale',
            'owner' => 'État / institutions internationales',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['administration', 'digital'],
            'profession' => 'Chargé de coopération fictif',
        ],
        'money' => [
            'label' => 'Monnaie, régulation financière et paiements',
            'owner' => 'Banques centrales et autorités habilitées',
            'regulated' => true,
            'priority' => 1,
            'depends' => ['digital', 'administration'],
            'profession' => 'Analyste financier fictif',
        ],
        'agriculture' => [
            'label' => 'Agriculture et renouvellement des ressources',
            'owner' => 'Producteurs et autorités compétentes',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['water', 'energy'],
            'profession' => 'Technicien agricole fictif',
        ],
        'industry' => [
            'label' => 'Production, industrie et approvisionnement',
            'owner' => 'Entreprises et autorités compétentes',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['energy', 'freight'],
            'profession' => 'Technicien de production fictif',
        ],
        'research' => [
            'label' => 'Recherche, innovation et évaluation',
            'owner' => 'Établissements de recherche / acteurs habilités',
            'regulated' => false,
            'priority' => 3,
            'depends' => ['education', 'digital'],
            'profession' => 'Coordinateur de recherche fictif',
        ],
        'employment' => [
            'label' => 'Emploi, formation et conditions de travail',
            'owner' => 'État, partenaires sociaux et organismes compétents',
            'regulated' => false,
            'priority' => 2,
            'depends' => ['education', 'administration'],
            'profession' => 'Conseiller en formation fictif',
        ],
        'social_insurance' => [
            'label' => 'Protection sociale et assurance collective',
            'owner' => 'Organismes sociaux et État',
            'regulated' => true,
            'priority' => 2,
            'depends' => ['administration', 'digital'],
            'profession' => 'Coordinateur de protection sociale fictif',
        ],
        'sport' => [
            'label' => 'Sport, détente et vie associative',
            'owner' => 'Collectivités et associations',
            'regulated' => false,
            'priority' => 4,
            'depends' => ['maintenance', 'care'],
            'profession' => 'Animateur de vie associative',
        ],
    ];

    public function fixtures(array $state): array
    {
        $workers = [];
        $services = array_keys(self::CATALOG);
        foreach ($state['citizens'] as $index => $citizen) {
            $skill = $services[$index % count($services)];
            $workers[] = [
                'id' => $citizen['id'],
                'citizenId' => $citizen['id'],
                'territoryId' => $citizen['territoryId'],
                'profession' => self::CATALOG[$skill]['profession'],
                'skills' => [$skill],
                'available' => true,
                'optIn' => true,
                'zone' => ['north', 'center', 'south'][$index % 3],
                'hourlyCents' => 1800 + ($index % 8) * 300,
                'capacityHours' => 8,
                'qualification' => 'Simulation uniquement, aucune habilitation réelle',
                'fictional' => true,
            ];
        }

        return $workers;
    }
}
