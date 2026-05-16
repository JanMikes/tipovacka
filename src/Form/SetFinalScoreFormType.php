<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SetFinalScoreFormData>
 */
final class SetFinalScoreFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('homeScore', IntegerType::class, [
            'label' => sprintf('Skóre %s', $options['home_team']),
            'attr' => ['min' => 0],
        ]);

        $builder->add('awayScore', IntegerType::class, [
            'label' => sprintf('Skóre %s', $options['away_team']),
            'attr' => ['min' => 0],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SetFinalScoreFormData::class,
        ]);
        $resolver->setRequired(['home_team', 'away_team']);
        $resolver->setAllowedTypes('home_team', 'string');
        $resolver->setAllowedTypes('away_team', 'string');
    }
}
