<?php

declare(strict_types=1);

namespace App\Form;

use App\Service\ActionForms;
use App\Service\SimulationEngine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class SimulationActionType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'action_type' => '',
            'territories' => [],
            'causal_nodes' => [],
            'proposals' => [],
            'workers' => [],
            'procurement_choices' => [],
        ]);
        $resolver->setAllowedValues(
            'action_type',
            array_keys(ActionForms::TYPES),
        );
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $actionType = $options['action_type'];

        foreach (
            ActionForms::TYPES[$actionType]['fields'] as $name => $definition
        ) {
            if (isset($options['procurement_choices'][$name])) {
                $definition['choices'] = $options['procurement_choices'][$name];
            }
            $draftField =
                in_array(
                    $actionType,
                    ['proposal.create', 'proposal.revise'],
                    true,
                )
                && !in_array(
                    $name,
                    ['title', 'territoryId', 'proposalId', 'reason'],
                    true,
                );
            $definition['required'] ??= !$draftField;
            $this->addConfiguredField(
                $builder,
                $name,
                $definition,
                $options['territories'],
                $options['causal_nodes'],
                $options['proposals'],
                $options['workers'],
            );
        }

        if (
            in_array($actionType, ['proposal.create', 'proposal.revise'], true)
        ) {
            $this->addCausalFields($builder, $options['causal_nodes']);
            if ($actionType === 'proposal.revise') {
                $builder->add(
                    'sources',
                    \Symfony\Component\Form\Extension\Core\Type\CollectionType::class,
                    [
                        'entry_type' => ProposalSourceType::class,
                        'allow_add' => true,
                        'allow_delete' => true,
                        'by_reference' => false,
                        'required' => false,
                        'label' => 'Sources du chemin',
                    ],
                );
            }
        }

        // Ces valeurs accompagnent la protection CSRF : elles ne remplacent pas le contrôle serveur.
        $builder
            ->add('revision', HiddenType::class, [
                'constraints' => [new Assert\Regex('/^\d+$/D')],
            ])
            ->add('operationId', HiddenType::class, [
                'constraints' => [new Assert\Length(min: 16, max: 100)],
            ]);
    }

    private function addConfiguredField(
        FormBuilderInterface $builder,
        string $name,
        array $definition,
        array $territories,
        array $nodes,
        array $proposals,
        array $workers,
    ): void {
        $fieldType = TextType::class;
        $fieldOptions = [
            'label' => $definition['label'],
            'required' => $definition['required'],
            'constraints' => $definition['required']
                ? [new Assert\NotBlank(), new Assert\Length(max: 3000)]
                : [new Assert\Length(max: 3000)],
        ];

        switch ($definition['type']) {
            case 'money':
                $fieldType = \Symfony\Component\Form\Extension\Core\Type\MoneyType::class;
                $fieldOptions['currency'] = 'EUR';
                $fieldOptions['divisor'] = 100;
                $fieldOptions['input'] = 'integer';
                $fieldOptions['scale'] = 2;
                $fieldOptions['html5'] = true;
                $fieldOptions['attr'] = ['step' => '0.01'];
                break;
            case 'service':
                $fieldOptions['multiple'] = $definition['multiple'] ?? false;
                $fieldType = ChoiceType::class;
                $fieldOptions['choices'] = array_combine(
                    array_column(
                        \App\Service\EssentialServices::CATALOG,
                        'label',
                    ),
                    array_keys(\App\Service\EssentialServices::CATALOG),
                );
                break;

            case 'worker':
                $fieldType = ChoiceType::class;
                $fieldOptions['choices'] = array_column(
                    $workers,
                    'id',
                    'label',
                );
                break;

            case 'proposal':
                $fieldType = ChoiceType::class;
                $fieldOptions['choices'] = array_column(
                    $proposals,
                    'id',
                    'title',
                );
                $fieldOptions['placeholder'] =
                    'Aucun chemin lié pour le moment';
                break;

            case 'territory':
                $fieldType = ChoiceType::class;
                $fieldOptions['choices'] = array_column(
                    $territories,
                    'id',
                    'name',
                );
                break;

            case 'node':
                $fieldType = ChoiceType::class;
                foreach ($nodes as $node) {
                    $fieldOptions['choices'][
                        $node['category'] . ' · ' . $node['id']
                    ] = $node['id'];
                }
                break;

            case 'hidden':
                $fieldType = HiddenType::class;
                break;

            case 'textarea':
                $fieldType = TextareaType::class;
                $fieldOptions['attr'] = ['rows' => 3];
                break;

            case 'number':
                $fieldType = NumberType::class;
                $fieldOptions['constraints'] = $definition['required']
                    ? [new Assert\NotNull()]
                    : [];
                $fieldOptions['empty_data'] = '0';
                $fieldOptions['scale'] = 2;
                $fieldOptions['html5'] = true;
                break;

            case 'date':
                $fieldOptions['attr'] = [
                    'placeholder' => 'AAAA-MM-JJ',
                    'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
                ];
                $fieldOptions['constraints'][] = new Assert\Date();
                break;

            case 'choice':
                $fieldType = ChoiceType::class;
                $fieldOptions['choices'] = $definition['choices'];
                break;

            case 'checkbox':
                $fieldType = CheckboxType::class;
                $fieldOptions['required'] = $definition['required'];
                $fieldOptions['constraints'] = $definition['required']
                    ? [new Assert\IsTrue()]
                    : [];
                break;
        }

        $builder->add($name, $fieldType, $fieldOptions);
    }

    private function addCausalFields(
        FormBuilderInterface $builder,
        array $nodes,
    ): void {
        $nodes =
            $nodes ?:
            array_map(
                static fn (string $label): array => ['category' => $label],
                SimulationEngine::CATEGORIES,
            );
        foreach ($nodes as $nodeIndex => $node) {
            $label = $node['category'];
            $builder->add('node' . $nodeIndex, TextareaType::class, [
                'label' => $nodeIndex + 1 . '. ' . $label,
                'required' => false,
                'constraints' => [new Assert\Length(max: 3000)],
                'attr' => ['rows' => 2],
            ]);
            $builder->add('kind' . $nodeIndex, ChoiceType::class, [
                'label' => 'Nature de cet élément',
                'choices' => [
                    'Hypothèse / prévision' => 'hypothesis',
                    'Observation simulée sourcée' => 'observation',
                ],
            ]);
        }
    }
}
