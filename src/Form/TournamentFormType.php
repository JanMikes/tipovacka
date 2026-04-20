<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TournamentFormData>
 */
final class TournamentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Název turnaje',
            'attr' => [
                'placeholder' => 'Např. Liga mistrů 2026/27',
            ],
        ]);

        $builder->add('description', TextareaType::class, [
            'label' => 'Popis',
            'required' => false,
            'attr' => [
                'placeholder' => 'Stručný popis turnaje…',
                'rows' => 4,
            ],
        ]);

        $builder->add('startAt', DateTimeType::class, [
            'label' => 'Začátek',
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
        ]);

        $builder->add('endAt', DateTimeType::class, [
            'label' => 'Konec',
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
        ]);

        if (true === $options['with_creation_pin']) {
            $builder->add('creationPin', TextType::class, [
                'label' => 'PIN pro vytváření skupin',
                'required' => false,
                'help' => 'Kdokoliv s tímto PINem bude moci v tomto soukromém turnaji založit novou skupinu. Nech prázdné, pokud chceš zakládání omezit jen na sebe.',
                'attr' => [
                    'placeholder' => 'Např. SKUP2026',
                    'maxlength' => 8,
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TournamentFormData::class,
            'with_creation_pin' => false,
        ]);
        $resolver->setAllowedTypes('with_creation_pin', 'bool');
    }
}
