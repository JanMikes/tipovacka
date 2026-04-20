<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<GroupFormData>
 */
final class GroupFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Název skupiny',
            'attr' => [
                'placeholder' => 'Např. Kámoši u piva',
            ],
        ]);

        $builder->add('description', TextareaType::class, [
            'label' => 'Popis',
            'required' => false,
            'attr' => [
                'placeholder' => 'Stručný popis skupiny…',
                'rows' => 3,
            ],
        ]);

        $withPinOptions = [
            'label' => 'Vygenerovat PIN pro připojení',
            'required' => false,
        ];

        if (true === ($options['with_pin_disabled'] ?? false)) {
            $withPinOptions['disabled'] = true;
            $withPinOptions['help'] = 'PIN lze spravovat po vytvoření v detailu skupiny.';
        }

        $builder->add('withPin', CheckboxType::class, $withPinOptions);

        if (true === $options['require_tournament_creation_pin']) {
            $builder->add('tournamentCreationPin', TextType::class, [
                'label' => 'PIN turnaje',
                'required' => true,
                'help' => 'Pro založení skupiny v tomto soukromém turnaji je potřeba zadat PIN od vlastníka.',
                'attr' => [
                    'autocomplete' => 'off',
                    'maxlength' => 8,
                ],
                'constraints' => [
                    new NotBlank(message: 'Zadejte prosím PIN turnaje.'),
                ],
                'mapped' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GroupFormData::class,
            'with_pin_disabled' => false,
            'require_tournament_creation_pin' => false,
        ]);
        $resolver->setAllowedTypes('with_pin_disabled', 'bool');
        $resolver->setAllowedTypes('require_tournament_creation_pin', 'bool');
    }
}
