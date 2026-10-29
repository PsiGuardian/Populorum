<?php

declare(strict_types=1);

namespace App\Service;

/** Titres, destinations et champs des actions : le contrôleur ne dépend pas de positions numériques. */
final class ActionForms
{
    public const TYPES = [
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
        'territory.adapt' => [
            'title' => 'Adapter un chemin à un autre territoire',
            'view' => 'territories',
            'fields' => [
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
