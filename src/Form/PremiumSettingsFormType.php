<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\BoostType;
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
        // The prémium toggles switch on exactly the three boosters, for everyone —
        // so they carry the boosters' ONE canonical name (item 23). The help text
        // says what changes for the MEMBERS, which is the organizer's question.
        $builder->add('showDistribution', CheckboxType::class, [
            'label' => BoostType::TipDistribution->label(),
            'required' => false,
            'help' => 'Členové uvidí anonymní přehled, jak tipuje skupina (bez jmen).',
        ]);

        $builder->add('showOthersTips', CheckboxType::class, [
            'label' => BoostType::OthersTips->label(),
            'required' => false,
            'help' => sprintf('Členové uvidí jmenovité tipy ostatních. Zahrnuje i „%s“.', BoostType::TipDistribution->label()),
        ]);

        $builder->add('allowTipChanges', CheckboxType::class, [
            'label' => BoostType::TipChange->label(),
            'required' => false,
            'help' => 'Členové mohou měnit tip až do nastaveného předstihu před začátkem každého zápasu.',
        ]);

        $builder->add('tipChangeOffsetMinutes', IntegerType::class, [
            'label' => 'Předstih pro změnu tipu (minuty)',
            'help' => 'O kolik minut před začátkem zápasu se zamknou změny tipů.',
            'empty_data' => '60',
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
