<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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
        // „Probíhá" is offered only for matches that can (still) run live —
        // correcting a finished match keeps the implicit „Ukončený" state, and a
        // raw POST with state=live is rejected as an invalid choice.
        $choices = ['Ukončený' => SetFinalScoreFormData::STATE_FINISHED];

        if (true === $options['allow_live']) {
            $choices = ['Probíhá' => SetFinalScoreFormData::STATE_LIVE] + $choices;
        }

        $builder->add('state', ChoiceType::class, [
            'label' => 'Stav zápasu',
            'choices' => $choices,
            'expanded' => true,
            'multiple' => false,
            // The toggle is not rendered at all when live is not allowed; a missing
            // submitted value then falls back to the only sensible state.
            'empty_data' => SetFinalScoreFormData::STATE_FINISHED,
            'invalid_message' => 'Zvolený stav zápasu není platný.',
        ]);

        $builder->add('homeScore', IntegerType::class, [
            'label' => sprintf('Skóre %s', $options['home_team']),
            'attr' => ['min' => 0],
        ]);

        $builder->add('awayScore', IntegerType::class, [
            'label' => sprintf('Skóre %s', $options['away_team']),
            'attr' => ['min' => 0],
        ]);

        $builder->add('periods', CollectionType::class, [
            'label' => false,
            'entry_type' => PeriodScoreFormType::class,
            'allow_add' => false,
            'allow_delete' => false,
        ]);

        $builder->add('overtimeHomeScore', IntegerType::class, [
            'label' => sprintf('Po prodloužení %s', $options['home_team']),
            'required' => false,
            'attr' => ['min' => 0],
        ]);

        $builder->add('overtimeAwayScore', IntegerType::class, [
            'label' => sprintf('Po prodloužení %s', $options['away_team']),
            'required' => false,
            'attr' => ['min' => 0],
        ]);

        $builder->add('events', CollectionType::class, [
            'label' => false,
            'entry_type' => MatchEventFormType::class,
            'entry_options' => [
                'home_team' => $options['home_team'],
                'away_team' => $options['away_team'],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => true,
        ]);

        $builder->add('isLastMatch', CheckboxType::class, [
            'label' => 'Toto byl poslední zápas zdroje',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SetFinalScoreFormData::class,
            'allow_live' => true,
        ]);
        $resolver->setRequired(['home_team', 'away_team']);
        $resolver->setAllowedTypes('home_team', 'string');
        $resolver->setAllowedTypes('away_team', 'string');
        $resolver->setAllowedTypes('allow_live', 'bool');
    }
}
