<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
        // The team-picker (tom-select autocomplete + create-new) is a progressive
        // enhancement over these plain text inputs; with JS off the typed name still
        // posts and the server resolves it to a Team. The URL is per-source so the
        // picker autocompletes the right scope (global directory / local teams).
        $homeAttr = ['placeholder' => 'Např. Sparta Praha'];
        $awayAttr = ['placeholder' => 'Např. Slavia Praha'];

        if (null !== $options['teams_url']) {
            $picker = [
                'data-controller' => 'team-picker',
                'data-team-picker-url-value' => $options['teams_url'],
                'autocomplete' => 'off',
            ];
            $homeAttr += $picker;
            $awayAttr += $picker;
        }

        $builder->add('homeTeam', TextType::class, [
            'label' => 'Domácí tým',
            'empty_data' => '',
            'attr' => $homeAttr,
        ]);

        $builder->add('awayTeam', TextType::class, [
            'label' => 'Hostující tým',
            'empty_data' => '',
            'attr' => $awayAttr,
        ]);

        $builder->add('kickoffAt', DateTimeType::class, [
            'label' => 'Začátek zápasu',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'with_seconds' => false,
            'html5' => false,
            'format' => 'yyyy-MM-dd HH:mm',
            // Stored in UTC, entered/displayed in Czech local time.
            'model_timezone' => 'UTC',
            'view_timezone' => 'Europe/Prague',
        ]);

        $builder->add('venue', TextType::class, [
            'label' => 'Místo',
            'required' => false,
            'attr' => ['placeholder' => 'Např. Generali Arena'],
        ]);

        $builder->add('round', TextType::class, [
            'label' => 'Kolo / fáze',
            'required' => false,
            'help' => 'Nepovinné. Např. „Skupina A", „Čtvrtfinále".',
            'attr' => ['placeholder' => 'Např. Čtvrtfinále'],
        ]);

        $builder->add('isPlayoff', CheckboxType::class, [
            'label' => 'Playoff zápas',
            'required' => false,
            'help' => 'Soutěže si mohou zvolit, zda playoff zápasy zahrnou.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SportMatchFormData::class,
            'teams_url' => null,
        ]);
        $resolver->setAllowedTypes('teams_url', ['string', 'null']);
    }
}
