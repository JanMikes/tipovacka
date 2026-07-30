<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CompetitionMatchSetting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Both ends of one match's tip window. The deadline belongs to the competition
 * manager; the opening („tipování otevřeno od" + the note shown while the match
 * waits) is ADMIN-only and is therefore not even BUILT unless `with_opening` is
 * true — a hidden field would still accept a submitted value, a missing one
 * cannot. The handler re-checks the role regardless.
 *
 * @extends AbstractType<CompetitionMatchDeadlineFormData>
 */
final class CompetitionMatchDeadlineFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('deadline', DateTimeType::class, [
            'label' => 'Uzávěrka pro tento zápas',
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'html5' => false,
            'with_seconds' => false,
            'format' => 'yyyy-MM-dd HH:mm',
            // Stored in UTC, entered/displayed in Czech local time.
            'model_timezone' => 'UTC',
            'view_timezone' => 'Europe/Prague',
            'help' => 'Vlastní uzávěrka tohoto zápasu — přepíše uzamčení soutěže, nejpozději do výkopu. Nechte prázdné pro výchozí pravidlo.',
        ]);

        if (true !== $options['with_opening']) {
            return;
        }

        $builder->add('opensAt', DateTimeType::class, [
            'label' => 'Tipování otevřeno od',
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'html5' => false,
            'with_seconds' => false,
            'format' => 'yyyy-MM-dd HH:mm',
            'model_timezone' => 'UTC',
            'view_timezone' => 'Europe/Prague',
            'help' => 'Do té doby zápas nikdo netipuje — je vidět v seznamu, ale bez políček na tip. Nechte prázdné, aby šel tipovat hned.',
        ]);

        $builder->add('openingNote', TextareaType::class, [
            'label' => 'Text během čekání',
            'required' => false,
            'attr' => [
                'rows' => 2,
                'maxlength' => CompetitionMatchSetting::OPENING_NOTE_MAX_LENGTH,
                'placeholder' => 'Např. Tipování otevřeme hned po losu skupin.',
            ],
            'help' => 'Nepovinné vysvětlení, které uvidí hráči, dokud se tipování neotevře.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompetitionMatchDeadlineFormData::class,
            'with_opening' => false,
        ]);
        $resolver->setAllowedTypes('with_opening', 'bool');
    }
}
