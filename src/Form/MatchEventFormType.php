<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MatchEventFormData>
 */
final class MatchEventFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('type', EnumType::class, [
            'label' => 'Typ',
            'class' => MatchEventType::class,
            'choice_label' => static fn (MatchEventType $type): string => match ($type) {
                MatchEventType::Goal => 'Gól',
                MatchEventType::YellowCard => 'Žlutá karta',
                MatchEventType::RedCard => 'Červená karta',
            },
        ]);

        $builder->add('side', EnumType::class, [
            'label' => 'Tým',
            'class' => MatchSide::class,
            'choice_label' => fn (MatchSide $side): string => match ($side) {
                MatchSide::Home => $options['home_team'],
                MatchSide::Away => $options['away_team'],
            },
        ]);

        $builder->add('minute', IntegerType::class, [
            'label' => 'Minuta',
            'required' => false,
            'attr' => ['min' => 0, 'max' => 150],
        ]);

        $builder->add('playerName', TextType::class, [
            'label' => 'Hráč',
            'attr' => ['placeholder' => 'Jméno hráče…'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MatchEventFormData::class,
        ]);
        $resolver->setRequired(['home_team', 'away_team']);
        $resolver->setAllowedTypes('home_team', 'string');
        $resolver->setAllowedTypes('away_team', 'string');
    }
}
