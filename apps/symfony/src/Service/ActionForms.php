<?php

declare(strict_types=1);

namespace App\Service;

/** Titres, destinations et champs des actions : le contrôleur ne dépend pas de positions numériques. */
final class ActionForms
{
    public const TYPES = [
        'tender.create' => [
            'title' => 'Publier un besoin et ses critères',
            'view' => 'procurement',
            'fields' => [
                'title' => [
                    'type' => 'text',
                    'label' => 'Titre du besoin fictif',
                ],
                'scope' => [
                    'type' => 'textarea',
                    'label' => 'Périmètre comparable : quantités, prestations, résultat exigé et exclusions',
                ],
                'location' => [
                    'type' => 'text',
                    'label' => 'Lieu fictif et contraintes d’accès',
                ],
                'service' => [
                    'type' => 'service',
                    'label' => 'Service concerné',
                ],
                'risks' => [
                    'type' => 'textarea',
                    'label' => 'Risques, habilitations et encadrement nécessaires',
                ],
                'cause' => [
                    'type' => 'textarea',
                    'label' => 'Cause supposée du besoin',
                ],
                'expectedEffect' => [
                    'type' => 'textarea',
                    'label' => 'Effet attendu',
                ],
                'indicator' => [
                    'type' => 'text',
                    'label' => 'Indicateur à contrôler après intervention',
                ],
                'uncertainty' => [
                    'type' => 'textarea',
                    'label' => 'Incertitudes et autres explications possibles',
                ],
                'proposalId' => [
                    'type' => 'proposal',
                    'label' => 'Arbre de proposition lié (facultatif)',
                    'required' => false,
                ],
                'missionId' => [
                    'type' => 'text',
                    'label' => 'Identifiant de mission liée (facultatif)',
                    'required' => false,
                ],
                'deadline' => [
                    'type' => 'text',
                    'label' => 'Date limite — AAAA-MM-JJ HH:MM, heure de Paris',
                ],
                'budgetCents' => [
                    'type' => 'money',
                    'label' => 'Budget plafond TTC en euros',
                ],
                'referenceCents' => [
                    'type' => 'money',
                    'label' => 'Référence de comparaison TTC en euros (0 si inconnue)',
                ],
                'referenceBasis' => [
                    'type' => 'textarea',
                    'label' => 'Origine, date et comparabilité de la référence ; indiquer si inconnue',
                ],
                'horizonYears' => [
                    'type' => 'number',
                    'label' => 'Durée commune de comparaison des coûts (années, 1 à 30)',
                ],
            ],
        ],
        'tender.question' => [
            'title' => 'Poser une question publique',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'question' => [
                    'type' => 'textarea',
                    'label' => 'Question commune — ne pas inclure de secrets d’offre',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.answer' => [
            'title' => 'Répondre à tous les candidats',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'questionId' => [
                    'type' => 'choice',
                    'label' => 'Question',
                    'choices' => [],
                ],
                'answer' => [
                    'type' => 'textarea',
                    'label' => 'Réponse publique sans modification du besoin',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.bid' => [
            'title' => 'Déposer une offre fictive chiffrée',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'company' => [
                    'type' => 'text',
                    'label' => 'Nom d’entreprise entièrement fictif',
                ],
                'lines' => [
                    'type' => 'textarea',
                    'label' => 'Une ligne par poste : libellé | quantité entière | prix unitaire TTC en centimes',
                ],
                'qualifications' => [
                    'type' => 'textarea',
                    'label' => 'Compétences, habilitations et garanties déclarées (non vérifiées)',
                ],
                'exclusions' => [
                    'type' => 'textarea',
                    'label' => 'Exclusions et réserves (écrire Aucune si aucune)',
                ],
                'days' => [
                    'type' => 'number',
                    'label' => 'Délai de réalisation en jours',
                ],
                'warrantyMonths' => [
                    'type' => 'number',
                    'label' => 'Garantie en mois',
                ],
                'annualMaintenanceCents' => [
                    'type' => 'money',
                    'label' => 'Coût annuel futur estimé TTC en euros',
                ],
                'fictionalConsent' => [
                    'type' => 'checkbox',
                    'label' => 'Je confirme que toutes les données sont fictives et seront publiques après ouverture',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.withdraw' => [
            'title' => 'Retirer mes offres avant la date limite',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.open' => [
            'title' => 'Clôturer les dépôts et ouvrir les offres',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.evaluate' => [
            'title' => 'Examiner une offre en tiers indépendant',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'offerId' => [
                    'type' => 'choice',
                    'label' => 'Offre à examiner',
                    'choices' => [],
                ],
                'compliant' => [
                    'type' => 'checkbox',
                    'label' => 'Périmètre, qualifications déclarées et exigences jugés conformes dans la simulation',
                    'required' => false,
                ],
                'qualityScore' => [
                    'type' => 'number',
                    'label' => 'Note de qualité motivée de 0 à 100',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.alert' => [
            'title' => 'Demander une explication sur une dépense',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'evidence' => [
                    'type' => 'textarea',
                    'label' => 'Référence comparable, source et contexte ; aucune accusation sans preuve',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.explain' => [
            'title' => 'Examiner un signalement en tiers indépendant',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'alertId' => [
                    'type' => 'choice',
                    'label' => 'Signalement',
                    'choices' => [],
                ],
                'response' => [
                    'type' => 'textarea',
                    'label' => 'Vérifications, réponse et points restant incertains',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.participation' => [
            'title' => 'Préparer une alternative habitante volontaire',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'scope' => [
                    'type' => 'textarea',
                    'label' => 'Tâches simples, périmètre équivalent et limites',
                ],
                'safety' => [
                    'type' => 'textarea',
                    'label' => 'Équipements, assurance, encadrement et contrôle prévus',
                ],
                'fallback' => [
                    'type' => 'textarea',
                    'label' => 'Solution professionnelle de repli et continuité du service',
                ],
                'hoursNeeded' => [
                    'type' => 'number',
                    'label' => 'Heures volontaires nécessaires au total',
                ],
                'hourValueCents' => [
                    'type' => 'money',
                    'label' => 'Valeur indicative du temps offert, en euros par heure',
                ],
                'materialsCents' => [
                    'type' => 'money',
                    'label' => 'Fournitures et équipements TTC en euros',
                ],
                'insuranceCents' => [
                    'type' => 'money',
                    'label' => 'Assurance TTC en euros',
                ],
                'supervisionCents' => [
                    'type' => 'money',
                    'label' => 'Encadrement TTC en euros',
                ],
                'controlCents' => [
                    'type' => 'money',
                    'label' => 'Contrôle TTC en euros',
                ],
                'lowRisk' => [
                    'type' => 'checkbox',
                    'label' => 'Uniquement des tâches simples à faible risque ; aucune intervention réglementée',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.volunteer' => [
            'title' => 'Proposer ou retirer ma participation volontaire',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'hours' => [
                    'type' => 'number',
                    'label' => 'Heures proposées (1 à 40 ; ignorées lors du retrait)',
                    'required' => false,
                ],
                'consent' => [
                    'type' => 'checkbox',
                    'label' => 'Je propose ces heures librement ; décocher pour retirer mon engagement',
                    'required' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.validate' => [
            'title' => 'Vérifier le plan participatif en tiers indépendant',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'checked' => [
                    'type' => 'checkbox',
                    'label' => 'Périmètre comparable, risques, assurance, encadrement, contrôle et repli examinés',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.award' => [
            'title' => 'Motiver le choix de réalisation',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'selection' => [
                    'type' => 'choice',
                    'label' => 'Solution retenue',
                    'choices' => [],
                ],
                'priceJustification' => [
                    'type' => 'textarea',
                    'label' => 'Justification spécifique d’un prix supérieur au double de la référence (si concerné)',
                    'required' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.amend' => [
            'title' => 'Proposer un avenant sans effacer le prix initial',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'deltaCents' => [
                    'type' => 'money',
                    'label' => 'Variation TTC en euros, positive ou négative',
                ],
                'scope' => [
                    'type' => 'textarea',
                    'label' => 'Modification précise, cause et effet attendu',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.approve' => [
            'title' => 'Examiner et approuver un avenant',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'amendmentId' => [
                    'type' => 'choice',
                    'label' => 'Avenant en attente',
                    'choices' => [],
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.delivery' => [
            'title' => 'Déclarer le début ou la fin de réalisation',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'actualCents' => [
                    'type' => 'money',
                    'label' => 'À la fin : coût total réalisé TTC en euros',
                    'required' => false,
                ],
                'invoice' => [
                    'type' => 'text',
                    'label' => 'À la fin : référence du justificatif fictif',
                    'required' => false,
                ],
                'observedEffect' => [
                    'type' => 'textarea',
                    'label' => 'À la fin : résultat constaté, mesure de l’indicateur et incertitudes',
                    'required' => false,
                ],
                'volunteerHours' => [
                    'type' => 'number',
                    'label' => 'Plan participatif uniquement : heures bénévoles réellement effectuées au total',
                    'required' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.inspect' => [
            'title' => 'Contrôler le service réalisé en tiers indépendant',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'accepted' => [
                    'type' => 'checkbox',
                    'label' => 'Résultat et justificatif jugés conformes ; sinon demander une correction',
                    'required' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.pay' => [
            'title' => 'Enregistrer un paiement fictif après contrôle',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'paymentReference' => [
                    'type' => 'text',
                    'label' => 'Référence fictive du paiement (aucun transfert réel)',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],
        'tender.cancel' => [
            'title' => 'Interrompre le dossier et conserver les coûts engagés',
            'view' => 'procurement',
            'fields' => [
                'tenderId' => ['type' => 'hidden', 'label' => false],
                'actualCents' => [
                    'type' => 'money',
                    'label' => 'Coût déjà engagé TTC en euros (0 si aucun)',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif public, éléments vérifiables et limites',
                ],
            ],
        ],

        'mission.cancel' => [
            'title' => 'Retirer une mission ou mon engagement fictif',
            'view' => 'services',
            'fields' => [
                'missionId' => ['type' => 'hidden', 'label' => false],
                'actualCents' => [
                    'type' => 'number',
                    'label' => 'Coût déjà engagé déclaré en centimes (0 si aucun)',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif du retrait et suites nécessaires',
                ],
            ],
        ],
        'worker.profile' => [
            'title' => 'Décrire mon métier et ma compétence',
            'view' => 'services',
            'fields' => [
                'profession' => ['type' => 'text', 'label' => 'Métier fictif'],
                'skill' => [
                    'type' => 'service',
                    'label' => 'Compétences proposées (jusqu’à huit)',
                    'multiple' => true,
                    'choices' => [
                        'water' => 'water',
                        'food' => 'food',
                        'energy' => 'energy',
                        'health' => 'health',
                        'rescue' => 'rescue',
                        'construction' => 'construction',
                        'demolition' => 'demolition',
                        'freight' => 'freight',
                        'transport' => 'transport',
                        'maintenance' => 'maintenance',
                        'waste' => 'waste',
                        'housing' => 'housing',
                        'education' => 'education',
                        'care' => 'care',
                        'digital' => 'digital',
                        'administration' => 'administration',
                        'justice' => 'justice',
                        'environment' => 'environment',
                        'culture' => 'culture',
                        'sport' => 'sport',
                    ],
                ],
                'zone' => [
                    'type' => 'choice',
                    'label' => 'Zone de proximité fictive',
                    'choices' => [
                        'Nord fictif' => 'north',
                        'Centre fictif' => 'center',
                        'Sud fictif' => 'south',
                    ],
                ],
                'hourlyCents' => [
                    'type' => 'number',
                    'label' => 'Coût horaire simulé en centimes',
                ],
                'capacityHours' => [
                    'type' => 'number',
                    'label' => 'Capacité en heures pour une mission',
                ],
            ],
        ],
        'worker.availability' => [
            'title' => 'Choisir ma disponibilité volontaire',
            'view' => 'services',
            'fields' => [
                'available' => [
                    'type' => 'checkbox',
                    'label' => 'Je suis disponible dans cette simulation',
                    'required' => false,
                ],
                'optIn' => [
                    'type' => 'checkbox',
                    'label' => 'J’accepte de recevoir des propositions de mission fictives',
                    'required' => false,
                ],
            ],
        ],
        'mission.create' => [
            'title' => 'Décrire un besoin local',
            'view' => 'services',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Besoin à traiter'],
                'description' => [
                    'type' => 'textarea',
                    'label' => 'Périmètre, risques, résultat attendu et incertitudes',
                ],
                'service' => [
                    'type' => 'service',
                    'label' => 'Service concerné',
                    'choices' => [
                        'water' => 'water',
                        'food' => 'food',
                        'energy' => 'energy',
                        'health' => 'health',
                        'rescue' => 'rescue',
                        'construction' => 'construction',
                        'demolition' => 'demolition',
                        'freight' => 'freight',
                        'transport' => 'transport',
                        'maintenance' => 'maintenance',
                        'waste' => 'waste',
                        'housing' => 'housing',
                        'education' => 'education',
                        'care' => 'care',
                        'digital' => 'digital',
                        'administration' => 'administration',
                        'justice' => 'justice',
                        'environment' => 'environment',
                        'culture' => 'culture',
                        'sport' => 'sport',
                    ],
                ],
                'zone' => [
                    'type' => 'choice',
                    'label' => 'Zone fictive du besoin',
                    'choices' => [
                        'Nord fictif' => 'north',
                        'Centre fictif' => 'center',
                        'Sud fictif' => 'south',
                    ],
                ],
                'priority' => [
                    'type' => 'choice',
                    'label' => 'Priorité déclarée',
                    'choices' => [
                        '1 — Urgence, à orienter vers les services compétents' => '1',
                        '2 — Service essentiel' => '2',
                        '3 — Prévention et entretien' => '3',
                        '4 — Qualité de vie' => '4',
                    ],
                ],
                'hours' => [
                    'type' => 'number',
                    'label' => 'Heures de travail estimées',
                ],
                'materialsCents' => [
                    'type' => 'number',
                    'label' => 'Matériel et autres coûts estimés en centimes',
                ],
                'budgetCents' => [
                    'type' => 'number',
                    'label' => 'Plafond total simulé en centimes',
                ],
            ],
        ],
        'mission.offer' => [
            'title' => 'Proposer une mission, sans l’imposer',
            'view' => 'services',
            'fields' => [
                'missionId' => ['type' => 'hidden', 'label' => false],
                'workerId' => [
                    'type' => 'worker',
                    'label' => 'Travailleur fictif à solliciter',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Pourquoi cette proposition et ce choix ?',
                ],
            ],
        ],
        'mission.respond' => [
            'title' => 'Accepter ou refuser une proposition',
            'view' => 'services',
            'fields' => [
                'missionId' => ['type' => 'hidden', 'label' => false],
                'accept' => [
                    'type' => 'checkbox',
                    'label' => 'J’accepte volontairement cette mission fictive',
                    'required' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif de ma réponse',
                ],
            ],
        ],
        'mission.progress' => [
            'title' => 'Déclarer une étape de réalisation',
            'view' => 'services',
            'fields' => [
                'missionId' => ['type' => 'hidden', 'label' => false],
                'actualCents' => [
                    'type' => 'number',
                    'label' => 'Coût total réalisé en centimes, uniquement pour clôturer',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Travail réalisé, limites et effets observés',
                ],
            ],
        ],
        'mission.review' => [
            'title' => 'Vérifier le résultat déclaré',
            'view' => 'services',
            'fields' => [
                'missionId' => ['type' => 'hidden', 'label' => false],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Vérification contradictoire, qualité et suites nécessaires',
                ],
            ],
        ],

        'account.create' => [
            'title' => 'Exposer un récit ou une inquiétude fictive',
            'view' => 'accounts',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Titre du récit'],
                'category' => [
                    'type' => 'text',
                    'label' => 'Situation étudiée : catégorie d’infraction ou autre inquiétude',
                ],
                'kind' => [
                    'type' => 'choice',
                    'label' => 'Point de départ',
                    'choices' => [
                        'Expérience fictive, y compris du point de vue de son auteur' => 'experience',
                        'Inquiétude ou difficulté à s’exprimer' => 'concern',
                    ],
                ],
                'proposalId' => [
                    'type' => 'proposal',
                    'label' => 'Chemin à enrichir (facultatif)',
                    'required' => false,
                ],
                'year' => [
                    'type' => 'text',
                    'label' => 'Année des faits fictifs (facultative, ex. 2025)',
                    'required' => false,
                ],
                'eventDate' => [
                    'type' => 'date',
                    'label' => 'Date si connue (facultative)',
                    'required' => false,
                ],
                'eventTime' => [
                    'type' => 'text',
                    'label' => 'Heure si connue — Europe/Paris, HH:MM (facultative)',
                    'required' => false,
                ],
                'nature' => [
                    'type' => 'choice',
                    'label' => 'Nature de cet ajout',
                    'choices' => [
                        'Hypothèse à examiner' => 'hypothesis',
                        'Récit personnel fictif, non vérifié' => 'self_report',
                        'Élément documenté avec source' => 'documented',
                    ],
                ],
                'disclosure' => [
                    'type' => 'choice',
                    'label' => 'État du récit',
                    'choices' => [
                        'Récit encore partiel' => 'partial',
                        'Nouvel élément' => 'additional',
                        'Correction d’une interprétation antérieure' => 'correction',
                    ],
                ],
                'cause' => [
                    'type' => 'textarea',
                    'label' => 'Ce qui a contribué à la situation — selon le personnage',
                ],
                'consequence' => [
                    'type' => 'textarea',
                    'label' => 'Conséquences, implications ou inquiétudes',
                ],
                'uncertainty' => [
                    'type' => 'textarea',
                    'label' => 'Ce qui reste incertain et comment le vérifier',
                ],
                'unknownVariables' => [
                    'type' => 'textarea',
                    'label' => 'Variables inconnues ou autres explications possibles (facultatif)',
                    'required' => false,
                ],
                'source' => [
                    'type' => 'textarea',
                    'label' => 'Source, méthode et limites (obligatoire pour un élément documenté)',
                    'required' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Pourquoi publier cet ajout ou cette correction maintenant ?',
                ],
                'fictionalConsent' => [
                    'type' => 'checkbox',
                    'label' => 'Je confirme que ce récit est fictif, sans victime réelle ni donnée civile réelle. Je comprends que chaque ajout soumis est visible et historisé.',
                ],
            ],
        ],
        'account.append' => [
            'title' => 'Compléter ou corriger mon récit fictif',
            'view' => 'accounts',
            'fields' => [
                'accountId' => ['type' => 'hidden', 'label' => false],
                'year' => [
                    'type' => 'text',
                    'label' => 'Année des faits fictifs (facultative, ex. 2025)',
                    'required' => false,
                ],
                'eventDate' => [
                    'type' => 'date',
                    'label' => 'Date si connue (facultative)',
                    'required' => false,
                ],
                'eventTime' => [
                    'type' => 'text',
                    'label' => 'Heure si connue — Europe/Paris, HH:MM (facultative)',
                    'required' => false,
                ],
                'nature' => [
                    'type' => 'choice',
                    'label' => 'Nature de cet ajout',
                    'choices' => [
                        'Hypothèse à examiner' => 'hypothesis',
                        'Récit personnel fictif, non vérifié' => 'self_report',
                        'Élément documenté avec source' => 'documented',
                    ],
                ],
                'disclosure' => [
                    'type' => 'choice',
                    'label' => 'État du récit',
                    'choices' => [
                        'Récit encore partiel' => 'partial',
                        'Nouvel élément' => 'additional',
                        'Correction d’une interprétation antérieure' => 'correction',
                    ],
                ],
                'cause' => [
                    'type' => 'textarea',
                    'label' => 'Ce qui a contribué à la situation — selon le personnage',
                ],
                'consequence' => [
                    'type' => 'textarea',
                    'label' => 'Conséquences, implications ou inquiétudes',
                ],
                'uncertainty' => [
                    'type' => 'textarea',
                    'label' => 'Ce qui reste incertain et comment le vérifier',
                ],
                'unknownVariables' => [
                    'type' => 'textarea',
                    'label' => 'Variables inconnues ou autres explications possibles (facultatif)',
                    'required' => false,
                ],
                'source' => [
                    'type' => 'textarea',
                    'label' => 'Source, méthode et limites (obligatoire pour un élément documenté)',
                    'required' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Pourquoi publier cet ajout ou cette correction maintenant ?',
                ],
                'fictionalConsent' => [
                    'type' => 'checkbox',
                    'label' => 'Je confirme que ce récit est fictif, sans victime réelle ni donnée civile réelle. Je comprends que chaque ajout soumis est visible et historisé.',
                ],
            ],
        ],

        'simulation.advance' => [
            'title' => 'Faire évoluer les personnages fictifs',
            'view' => 'citizen',
            'fields' => [],
        ],
        'proposal.transition' => [
            'title' => 'Faire évoluer l’état du chemin',
            'view' => 'proposals',
            'fields' => [
                'proposalId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'status' => [
                    'type' => 'choice',
                    'label' => 'Nouvel état',
                    'choices' => [
                        'completed' => 'completed',
                        'ready' => 'ready',
                        'deferred' => 'deferred',
                        'withdrawn' => 'withdrawn',
                        'inapplicable' => 'inapplicable',
                        'observing' => 'observing',
                        'evaluated' => 'evaluated',
                        'archived' => 'archived',
                        'published' => 'published',
                        'draft' => 'draft',
                    ],
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif',
                ],
            ],
        ],
        'proposal.reformulate' => [
            'title' => 'Proposer une reformulation validée',
            'view' => 'proposals',
            'fields' => [
                'proposalId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'locale' => [
                    'type' => 'text',
                    'label' => 'Langue (ex. fr_FR)',
                ],
                'level' => [
                    'type' => 'text',
                    'label' => 'Niveau de lecture (français simple, scientifique expliqué…)',
                ],
                'text' => [
                    'type' => 'textarea',
                    'label' => 'Reformulation',
                ],
                'glossary' => [
                    'type' => 'textarea',
                    'label' => 'Mots à expliquer',
                ],
            ],
        ],
        'governance.enact' => [
            'title' => 'Appliquer une évolution examinée',
            'view' => 'governance',
            'fields' => [
                'governanceId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Justification de la mise en application',
                ],
            ],
        ],
        'moderation.decide' => [
            'title' => 'Instruire le dossier',
            'view' => 'governance',
            'fields' => [
                'caseId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'targetId' => [
                    'type' => 'text',
                    'label' => 'Identifiant du personnage concerné',
                ],
                'rule' => [
                    'type' => 'choice',
                    'label' => 'Règle préexistante',
                    'choices' => [
                        'Menace documentée' => 'threat',
                        'Harcèlement' => 'harassment',
                        'Usurpation' => 'impersonation',
                        'Divulgation privée' => 'private_data',
                        'Spam perturbateur' => 'spam',
                        'Falsification' => 'falsification',
                    ],
                ],
                'measure' => [
                    'type' => 'choice',
                    'label' => 'Mesure proportionnée',
                    'choices' => [
                        'Avertissement' => 'warning',
                        'Pause de publication' => 'publication_pause',
                        'Classement sans mesure' => 'dismiss',
                    ],
                ],
                'days' => [
                    'type' => 'number',
                    'label' => 'Durée en jours (1–30)',
                ],
                'facts' => [
                    'type' => 'textarea',
                    'label' => 'Faits établis',
                ],
                'evidence' => [
                    'type' => 'textarea',
                    'label' => 'Preuves et sources',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motivation et proportionnalité',
                ],
            ],
        ],
        'moderation.confirm' => [
            'title' => 'Confirmer une décision en indépendance',
            'view' => 'governance',
            'fields' => [
                'caseId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motifs du second conciliateur',
                ],
            ],
        ],
        'moderation.resolve' => [
            'title' => 'Résoudre un recours',
            'view' => 'governance',
            'fields' => [
                'appealId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'outcome' => [
                    'type' => 'choice',
                    'label' => 'Conclusion',
                    'choices' => [
                        'Confirmer la mesure' => 'upheld',
                        'Révoquer la mesure' => 'revoked',
                    ],
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motifs du réexamen indépendant',
                ],
            ],
        ],
        'integrity.review' => [
            'title' => 'Examiner une alerte d’intégrité',
            'view' => 'transparency',
            'fields' => [
                'alertId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'status' => [
                    'type' => 'choice',
                    'label' => 'Conclusion',
                    'choices' => [
                        'reproducible' => 'reproducible',
                        'dismissed' => 'dismissed',
                        'security_incident' => 'security_incident',
                        'confirmed_fraud' => 'confirmed_fraud',
                        'corrected' => 'corrected',
                    ],
                ],
                'proof' => [
                    'type' => 'textarea',
                    'label' => 'Procédure de reproduction et éléments vérifiables',
                ],
            ],
        ],
        'consultation.review' => [
            'title' => 'Demander une annulation documentée',
            'view' => 'consultations',
            'fields' => [
                'consultationId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif',
                ],
                'proof' => [
                    'type' => 'textarea',
                    'label' => 'Preuve de l’incident affectant la consultation',
                ],
            ],
        ],
        'citizen.preferences' => [
            'title' => 'Préférences de lecture',
            'view' => 'citizen',
            'fields' => [
                'locale' => [
                    'type' => 'text',
                    'label' => 'Langue (fr_FR, en_GB…)',
                ],
                'recommendations' => [
                    'type' => 'checkbox',
                    'label' => 'Activer les comparaisons suggérées',
                    'required' => false,
                ],
                'largeText' => [
                    'type' => 'checkbox',
                    'label' => 'Agrandir les caractères',
                    'required' => false,
                ],
            ],
        ],

        'citizen.create' => [
            'title' => 'Créer un personnage fictif',
            'view' => 'citizen',
            'fields' => [
                'name' => [
                    'type' => 'text',
                    'label' => 'Nom inventé',
                ],
                'territoryId' => [
                    'type' => 'territory',
                    'label' => 'Territoire',
                ],
            ],
        ],
        'citizen.consent' => [
            'title' => 'Participation nominative fictive',
            'view' => 'citizen',
            'fields' => [
                'consent' => [
                    'type' => 'checkbox',
                    'label' => 'Je consens à publier les choix de mon personnage dans ma simulation',
                    'required' => true,
                ],
            ],
        ],
        'citizen.withdraw' => [
            'title' => 'Retirer le consentement fictif',
            'view' => 'citizen',
            'fields' => [],
        ],
        'citizen.update' => [
            'title' => 'Rectifier mon personnage',
            'view' => 'citizen',
            'fields' => [
                'name' => [
                    'type' => 'text',
                    'label' => 'Nom inventé',
                ],
                'territoryId' => [
                    'type' => 'territory',
                    'label' => 'Territoire',
                ],
            ],
        ],
        'citizen.erase' => [
            'title' => 'Effacer le nom fictif',
            'view' => 'citizen',
            'fields' => [],
        ],
        'proposal.create' => [
            'title' => 'Exposer mon chemin',
            'view' => 'proposals',
            'fields' => [
                'title' => [
                    'type' => 'text',
                    'label' => 'Titre',
                ],
                'territoryId' => [
                    'type' => 'territory',
                    'label' => 'Territoire',
                ],
                'theme' => [
                    'type' => 'text',
                    'label' => 'Thème',
                ],
                'tags' => [
                    'type' => 'text',
                    'label' => 'Thèmes communs, séparés par des virgules',
                ],
                'summary' => [
                    'type' => 'textarea',
                    'label' => 'Explication en français courant',
                ],
                'problem' => [
                    'type' => 'textarea',
                    'label' => 'Problème',
                ],
                'cause' => [
                    'type' => 'textarea',
                    'label' => 'Cause principale supposée',
                ],
                'consequence' => [
                    'type' => 'textarea',
                    'label' => 'Conséquence',
                ],
                'solution' => [
                    'type' => 'textarea',
                    'label' => 'Solution à expérimenter',
                ],
                'justification' => [
                    'type' => 'textarea',
                    'label' => 'Justification',
                ],
                'indicator' => [
                    'type' => 'text',
                    'label' => 'Indicateur mesurable',
                ],
                'forecast' => [
                    'type' => 'number',
                    'label' => 'Variation attendue en % (hypothèse)',
                ],
                'risk' => [
                    'type' => 'textarea',
                    'label' => 'Risques anticipés',
                ],
                'reversibility' => [
                    'type' => 'textarea',
                    'label' => 'Conditions de retour arrière',
                ],
                'source' => [
                    'type' => 'text',
                    'label' => 'Source pédagogique',
                ],
            ],
        ],
        'proposal.publish' => [
            'title' => 'Publier le brouillon',
            'view' => 'proposals',
            'fields' => [
                'proposalId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
            ],
        ],
        'proposal.revise' => [
            'title' => 'Faire évoluer mon chemin',
            'view' => 'proposals',
            'fields' => [
                'observationId' => [
                    'type' => 'text',
                    'label' => 'Identifiant de l’observation motivant la révision (facultatif)',
                    'required' => false,
                ],
                'proposalId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif de révision',
                ],
                'title' => ['type' => 'text', 'label' => 'Titre'],
                'theme' => ['type' => 'text', 'label' => 'Thème'],
                'tags' => [
                    'type' => 'text',
                    'label' => 'Thèmes communs, séparés par des virgules',
                ],
                'summary' => [
                    'type' => 'textarea',
                    'label' => 'Explication en français courant',
                ],
                'problem' => ['type' => 'textarea', 'label' => 'Problème'],
                'cause' => [
                    'type' => 'textarea',
                    'label' => 'Cause principale supposée',
                ],
                'consequence' => [
                    'type' => 'textarea',
                    'label' => 'Conséquence',
                ],
                'solution' => [
                    'type' => 'textarea',
                    'label' => 'Solution à expérimenter',
                ],
                'justification' => [
                    'type' => 'textarea',
                    'label' => 'Justification',
                ],
                'indicator' => [
                    'type' => 'text',
                    'label' => 'Indicateur mesurable',
                ],
                'forecast' => [
                    'type' => 'number',
                    'label' => 'Variation attendue en %',
                ],
                'risk' => [
                    'type' => 'textarea',
                    'label' => 'Risques anticipés',
                ],
                'reversibility' => [
                    'type' => 'textarea',
                    'label' => 'Conditions de retour arrière',
                ],
            ],
        ],
        'proposal.source' => [
            'title' => 'Ajouter une source au chemin',
            'view' => 'proposals',
            'fields' => [
                'proposalId' => ['type' => 'hidden', 'label' => false],
                'label' => ['type' => 'text', 'label' => 'Source pédagogique'],
                'date' => ['type' => 'date', 'label' => 'Date de la source'],
                'url' => [
                    'type' => 'text',
                    'label' => 'Lien facultatif',
                    'required' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif de cet ajout',
                ],
            ],
        ],
        'proposal.node' => [
            'title' => 'Ajouter un élément au raisonnement',
            'view' => 'proposals',
            'fields' => [
                'proposalId' => ['type' => 'hidden', 'label' => false],
                'category' => [
                    'type' => 'text',
                    'label' => 'Catégorie complémentaire',
                ],
                'content' => [
                    'type' => 'textarea',
                    'label' => 'Ce que vous souhaitez ajouter',
                ],
                'kind' => [
                    'type' => 'choice',
                    'label' => 'Nature',
                    'choices' => [
                        'Hypothèse / prévision' => 'hypothesis',
                        'Observation simulée' => 'observation',
                    ],
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif de cette évolution',
                ],
            ],
        ],
        'proposal.edge' => [
            'title' => 'Relier deux éléments du raisonnement',
            'view' => 'proposals',
            'fields' => [
                'proposalId' => ['type' => 'hidden', 'label' => false],
                'from' => ['type' => 'node', 'label' => 'Premier élément'],
                'relation' => [
                    'type' => 'choice',
                    'label' => 'Relation',
                    'choices' => [
                        'Cause de' => 'cause de',
                        'Contribue à' => 'contribue à',
                        'Dépend de' => 'dépend de',
                        'Rend possible' => 'rend possible',
                        'Produit potentiellement' => 'produit potentiellement',
                        'Observé après' => 'observé après',
                        'Documenté par' => 'documenté par',
                        'Remplacé par' => 'remplacé par',
                        'Alternative à' => 'alternative à',
                    ],
                ],
                'to' => ['type' => 'node', 'label' => 'Second élément'],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif et limites de cette relation',
                ],
            ],
        ],
        'territory.revise' => [
            'title' => 'Réviser une fiche territoriale',
            'view' => 'territories',
            'fields' => [
                'adaptationId' => ['type' => 'hidden', 'label' => false],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif de la révision',
                ],
                'probableConsequences' => [
                    'type' => 'textarea',
                    'label' => 'Conséquences probables (hypothèses)',
                ],
                'observedConsequences' => [
                    'type' => 'textarea',
                    'label' => 'Conséquences observées ou absence explicite de mesure',
                ],
                'source' => [
                    'type' => 'text',
                    'label' => 'Source datée ou référence documentaire',
                ],
                'review' => [
                    'type' => 'textarea',
                    'label' => 'Conditions de réexamen',
                ],
            ],
        ],
        'territory.adapt' => [
            'title' => 'Adapter un chemin à un autre territoire',
            'view' => 'territories',
            'fields' => [
                'probableConsequences' => [
                    'type' => 'textarea',
                    'label' => 'Conséquences probables',
                    'required' => false,
                ],
                'observedConsequences' => [
                    'type' => 'textarea',
                    'label' => 'Conséquences observées (si disponibles)',
                    'required' => false,
                ],
                'proposalId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'territoryId' => [
                    'type' => 'territory',
                    'label' => 'Territoire',
                ],
                'type' => [
                    'type' => 'choice',
                    'label' => 'Type',
                    'choices' => [
                        'adaptation' => 'adaptation',
                        'exclusion' => 'exclusion',
                    ],
                ],
                'characteristic' => [
                    'type' => 'textarea',
                    'label' => 'Caractéristique locale',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Causes et conséquences locales',
                ],
                'source' => [
                    'type' => 'text',
                    'label' => 'Source pédagogique',
                ],
                'alternative' => [
                    'type' => 'textarea',
                    'label' => 'Solution alternative',
                ],
                'review' => [
                    'type' => 'textarea',
                    'label' => 'Conditions de réexamen',
                ],
            ],
        ],
        'consultation.open' => [
            'title' => 'Ouvrir une consultation fictive',
            'view' => 'consultations',
            'fields' => [
                'proposalId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'days' => [
                    'type' => 'number',
                    'label' => 'Durée en jours, de 1 à 365',
                ],
                'quorum' => [
                    'type' => 'number',
                    'label' => 'Quorum, de 0 à 100 %',
                ],
            ],
        ],
        'consultation.close' => [
            'title' => 'Clôturer cette consultation',
            'view' => 'consultations',
            'fields' => [
                'consultationId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motif de clôture pédagogique',
                ],
            ],
        ],
        'vote.cast' => [
            'title' => 'Confirmer un vote fictif',
            'view' => 'consultations',
            'fields' => [
                'consultationId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'choice' => [
                    'type' => 'choice',
                    'label' => 'Mon choix fictif',
                    'choices' => [
                        'pour' => 'pour',
                        'contre' => 'contre',
                        'abstention' => 'abstention',
                    ],
                ],
            ],
        ],
        'experiment.create' => [
            'title' => 'Définir un protocole',
            'view' => 'experiments',
            'fields' => [
                'consultationId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'objective' => [
                    'type' => 'textarea',
                    'label' => 'Objectif',
                ],
                'hypothesis' => [
                    'type' => 'textarea',
                    'label' => 'Hypothèse',
                ],
                'baseline' => [
                    'type' => 'number',
                    'label' => 'Valeur initiale',
                ],
                'unit' => [
                    'type' => 'text',
                    'label' => 'Unité',
                ],
                'start' => [
                    'type' => 'date',
                    'label' => 'Début',
                ],
                'end' => [
                    'type' => 'date',
                    'label' => 'Fin',
                ],
                'stop' => [
                    'type' => 'textarea',
                    'label' => 'Critère d’arrêt',
                ],
                'method' => [
                    'type' => 'textarea',
                    'label' => 'Méthode de mesure',
                ],
                'limitations' => [
                    'type' => 'textarea',
                    'label' => 'Incertitudes et limites',
                ],
            ],
        ],
        'observation.add' => [
            'title' => 'Ajouter une observation simulée',
            'view' => 'experiments',
            'fields' => [
                'experimentId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'date' => [
                    'type' => 'date',
                    'label' => 'Date du relevé',
                ],
                'value' => [
                    'type' => 'number',
                    'label' => 'Valeur observée',
                ],
                'uncertainty' => [
                    'type' => 'number',
                    'label' => 'Incertitude ±',
                ],
                'source' => [
                    'type' => 'textarea',
                    'label' => 'Source et méthode',
                ],
                'external' => [
                    'type' => 'textarea',
                    'label' => 'Facteurs externes',
                ],
                'unexpected' => [
                    'type' => 'textarea',
                    'label' => 'Effet imprévu',
                ],
            ],
        ],
        'experiment.evaluate' => [
            'title' => 'Produire le bilan',
            'view' => 'experiments',
            'fields' => [
                'experimentId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'summary' => [
                    'type' => 'textarea',
                    'label' => 'Prévisions, observations, imprévus et limites',
                ],
                'decision' => [
                    'type' => 'choice',
                    'label' => 'Decision',
                    'choices' => [
                        'continue' => 'continue',
                        'revise' => 'revise',
                        'stop' => 'stop',
                    ],
                ],
            ],
        ],
        'finance.add' => [
            'title' => 'Ajouter une écriture fictive',
            'view' => 'transparency',
            'fields' => [
                'type' => [
                    'type' => 'choice',
                    'label' => 'Type',
                    'choices' => [
                        'expense' => 'expense',
                        'contribution' => 'contribution',
                    ],
                ],
                'label' => [
                    'type' => 'text',
                    'label' => 'Libellé pédagogique',
                ],
                'amount' => [
                    'type' => 'number',
                    'label' => 'Montant en euros',
                ],
                'receipt' => [
                    'type' => 'text',
                    'label' => 'Référence du justificatif pédagogique',
                ],
            ],
        ],
        'governance.propose' => [
            'title' => 'Proposer une évolution',
            'view' => 'governance',
            'fields' => [
                'quorum' => [
                    'type' => 'number',
                    'label' => 'Quorum des futures consultations (%)',
                ],
                'changeAllowed' => [
                    'type' => 'checkbox',
                    'label' => 'Permettre la révision du vote avant clôture',
                    'required' => false,
                ],
                'title' => [
                    'type' => 'text',
                    'label' => 'Titre de l’évolution',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motifs',
                ],
                'risk' => [
                    'type' => 'textarea',
                    'label' => 'Risques et garanties',
                ],
                'rollback' => [
                    'type' => 'textarea',
                    'label' => 'Plan de retour arrière',
                ],
            ],
        ],
        'governance.approve' => [
            'title' => 'Enregistrer un avis indépendant',
            'view' => 'governance',
            'fields' => [
                'governanceId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
            ],
        ],
        'moderation.report' => [
            'title' => 'Documenter un signalement',
            'view' => 'governance',
            'fields' => [
                'objectId' => [
                    'type' => 'text',
                    'label' => 'Identifiant concerné',
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Faits, contexte et preuves',
                ],
            ],
        ],
        'moderation.appeal' => [
            'title' => 'Exercer un recours',
            'view' => 'governance',
            'fields' => [
                'caseId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Motifs du recours et réexamen demandé',
                ],
            ],
        ],
        'integrity.alert' => [
            'title' => 'Signaler une anomalie',
            'view' => 'transparency',
            'fields' => [
                'reason' => [
                    'type' => 'textarea',
                    'label' => 'Anomalie et méthode de reproduction',
                ],
            ],
        ],
        'node.configure' => [
            'title' => 'Configurer le relais virtuel',
            'view' => 'infrastructure',
            'fields' => [
                'nodeId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
                'bandwidth' => [
                    'type' => 'number',
                    'label' => 'Plafond pédagogique en Ko/s (1–1024)',
                ],
                'enabled' => [
                    'type' => 'checkbox',
                    'label' => 'Autoriser le relais virtuel',
                    'required' => false,
                ],
            ],
        ],
        'node.sync' => [
            'title' => 'Synchroniser une copie virtuelle',
            'view' => 'infrastructure',
            'fields' => [
                'nodeId' => [
                    'type' => 'hidden',
                    'label' => false,
                ],
            ],
        ],
        'resource.add' => [
            'title' => 'Décrire une contribution fictive',
            'view' => 'citizen',
            'fields' => [
                'kind' => [
                    'type' => 'text',
                    'label' => 'Type de contribution',
                ],
                'description' => [
                    'type' => 'textarea',
                    'label' => 'Ressource ou aide proposée',
                ],
            ],
        ],
    ];
}
