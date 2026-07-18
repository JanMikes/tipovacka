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
            'attr' => ['min' => 0],
        ]);

        $builder->add('awayScore', IntegerType::class, [
            'label' => false,
            'required' => false,
            'attr' => ['min' => 0],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PeriodScoreFormData::class,
        ]);
    }
}
