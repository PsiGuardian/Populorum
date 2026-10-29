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
        ]);
        $resolver->setAllowedValues('action_type', array_keys(ActionForms::TYPES));
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $actionType = $options['action_type'];

        foreach (ActionForms::TYPES[$actionType]['fields'] as $name => $definition) {
            $this->addConfiguredField($builder, $name, $definition, $options['territories']);
        }

        if (in_array($actionType, ['proposal.create', 'proposal.revise'], true)) {
            $this->addCausalFields($builder);
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
    ): void {
        $fieldType = TextType::class;
        $fieldOptions = [
            'label' => $definition['label'],
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 3000)],
        ];

        switch ($definition['type']) {
            case 'territory':
                $fieldType = ChoiceType::class;
                $fieldOptions['choices'] = array_column($territories, 'id', 'name');
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
                $fieldOptions['constraints'] = [new Assert\NotNull()];
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
                $fieldOptions['constraints'] = $definition['required'] ? [new Assert\IsTrue()] : [];
                break;
        }

        $builder->add($name, $fieldType, $fieldOptions);
    }

    private function addCausalFields(FormBuilderInterface $builder): void
    {
        foreach (SimulationEngine::CATEGORIES as $nodeIndex => $label) {
            $builder->add('node' . $nodeIndex, TextareaType::class, [
                'label' => ($nodeIndex + 1) . '. ' . $label,
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 3000)],
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
