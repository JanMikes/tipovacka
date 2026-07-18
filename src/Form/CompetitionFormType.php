<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<CompetitionFormData>
 */
final class CompetitionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Název soutěže',
            'attr' => [
                'placeholder' => 'Např. Kámoši u piva',
            ],
        ]);

        $builder->add('description', TextareaType::class, [
            'label' => 'Popis',
            'required' => false,
            'attr' => [
                'placeholder' => 'Stručný popis soutěže…',
                'rows' => 3,
            ],
        ]);

        $withPinOptions = [
            'label' => 'Vygenerovat PIN pro připojení',
            'required' => false,
        ];

        if (true === ($options['with_pin_disabled'] ?? false)) {
            $withPinOptions['disabled'] = true;
            $withPinOptions['help'] = 'PIN lze spravovat po vytvoření v detailu soutěže.';
        }

        $builder->add('withPin', CheckboxType::class, $withPinOptions);

        $builder->add('hideOthersTipsBeforeDeadline', CheckboxType::class, [
            'label' => 'Schovat tipy ostatních před uzávěrkou',
            'required' => false,
            'help' => 'Když je zapnuto, ostatní členové uvidí tvůj tip až po uzávěrce. Ty samozřejmě vždy vidíš svoje.',
        ]);

        $builder->add('tipsDeadline', DateTimeType::class, [
            'label' => 'Uzávěrka všech tipů',
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'html5' => false,
            'with_seconds' => false,
            'format' => 'yyyy-MM-dd HH:mm',
            // Stored in UTC, entered/displayed in Czech local time.
            'model_timezone' => 'UTC',
            'view_timezone' => 'Europe/Prague',
            'help' => 'Uzávěrku lze nastavit i jednotlivě pro každý zápas. Pokud zde žádnou nezadáš a tipy ostatních jsou skryté, zveřejní se v okamžiku začátku zápasu.',
        ]);

        if (true === $options['require_match_source_creation_pin']) {
            $builder->add('matchSourceCreationPin', TextType::class, [
                'label' => 'PIN turnaje',
                'required' => true,
                'help' => 'Pro založení soutěže v tomto soukromém turnaji je potřeba zadat PIN od vlastníka.',
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
            'data_class' => CompetitionFormData::class,
            'with_pin_disabled' => false,
            'require_match_source_creation_pin' => false,
        ]);
        $resolver->setAllowedTypes('with_pin_disabled', 'bool');
        $resolver->setAllowedTypes('require_match_source_creation_pin', 'bool');
    }
}
