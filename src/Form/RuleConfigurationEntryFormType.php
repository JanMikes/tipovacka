<?php

declare(strict_types=1);

namespace App\Form;

use App\Service\Scoring\RulePresetProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RuleConfigurationEntryFormData>
 */
final class RuleConfigurationEntryFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('enabled', CheckboxType::class, [
            'label' => 'Aktivní',
            'required' => false,
        ]);

        $builder->add('points', IntegerType::class, [
            'label' => 'Body',
            'empty_data' => '0',
            'invalid_message' => sprintf('Zadejte počet bodů jako celé číslo od 0 do %d.', RulePresetProvider::MAX_POINTS),
            'attr' => [
                'min' => 0,
                'max' => RulePresetProvider::MAX_POINTS,
                'step' => 1,
                'inputmode' => 'numeric',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RuleConfigurationEntryFormData::class,
        ]);
    }
}
