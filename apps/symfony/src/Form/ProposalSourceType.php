<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** Une source reste éditable avec la révision complète du chemin. */
final class ProposalSourceType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Source',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 3000),
                ],
            ])
            ->add('date', TextType::class, [
                'label' => 'Date (AAAA-MM-JJ)',
                'constraints' => [new Assert\Date(), new Assert\NotBlank()],
            ])
            ->add('url', TextType::class, [
                'label' => 'Lien facultatif',
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Assert\Url(protocols: ['http', 'https'])],
            ]);
    }
}
