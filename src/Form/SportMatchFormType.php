<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SportMatchFormData>
 */
final class SportMatchFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('homeTeam', TextType::class, [
            'label' => 'Domácí tým',
            'attr' => ['placeholder' => 'Např. Sparta Praha'],
        ]);

        $builder->add('awayTeam', TextType::class, [
            'label' => 'Hostující tým',
            'attr' => ['placeholder' => 'Např. Slavia Praha'],
        ]);

        $builder->add('kickoffAt', DateTimeType::class, [
            'label' => 'Začátek zápasu',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'with_seconds' => false,
            'html5' => false,
            'format' => 'yyyy-MM-dd HH:mm',
        ]);

        $builder->add('venue', TextType::class, [
            'label' => 'Místo',
            'required' => false,
            'attr' => ['placeholder' => 'Např. Generali Arena'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SportMatchFormData::class,
        ]);
    }
}
