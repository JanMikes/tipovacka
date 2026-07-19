<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PremiumSettingsFormData>
 */
final class PremiumSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('showDistribution', CheckboxType::class, [
            'label' => 'Lišta tipů ostatních',
            'required' => false,
            'help' => 'Členové uvidí anonymní přehled, jak tipuje skupina (bez jmen).',
        ]);

        $builder->add('showOthersTips', CheckboxType::class, [
            'label' => 'Konkrétní tipy kolegů',
            'required' => false,
            'help' => 'Členové uvidí jmenovité tipy ostatních. Zahrnuje i Lištu tipů.',
        ]);

        $builder->add('allowTipChanges', CheckboxType::class, [
            'label' => 'Měnit tip během turnaje',
            'required' => false,
            'help' => 'Členové mohou měnit tip až do nastaveného předstihu před prvním zápasem dne.',
        ]);

        $builder->add('tipChangeOffsetMinutes', IntegerType::class, [
            'label' => 'Předstih pro změnu tipu (minuty)',
            'help' => 'O kolik minut před prvním zápasem dne se zamknou změny tipů.',
            'attr' => ['min' => 0, 'max' => 1440],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PremiumSettingsFormData::class,
        ]);
    }
}
