<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<GroupMatchDeadlineFormData>
 */
final class GroupMatchDeadlineFormType extends AbstractType
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
            'help' => 'Musí být dříve nebo přesně v okamžiku začátku zápasu. Nech prázdné pro použití skupinového nastavení.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GroupMatchDeadlineFormData::class,
        ]);
    }
}
