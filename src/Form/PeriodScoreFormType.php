<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PeriodScoreFormData>
 */
final class PeriodScoreFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('homeScore', IntegerType::class, [
            'label' => false,
            'required' => false,
            'invalid_message' => 'Skóre části zadejte jako celé číslo, např. 1.',
            'attr' => ['min' => 0, 'step' => 1, 'inputmode' => 'numeric', 'autocomplete' => 'off'],
        ]);

        $builder->add('awayScore', IntegerType::class, [
            'label' => false,
            'required' => false,
            'invalid_message' => 'Skóre části zadejte jako celé číslo, např. 1.',
            'attr' => ['min' => 0, 'step' => 1, 'inputmode' => 'numeric', 'autocomplete' => 'off'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PeriodScoreFormData::class,
        ]);
    }
}
