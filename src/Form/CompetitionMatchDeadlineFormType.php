<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompetitionMatchDeadlineFormData::class,
        ]);
    }
}
