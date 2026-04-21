<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PostponeSportMatchFormData>
 */
final class PostponeSportMatchFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('newKickoffAt', DateTimeType::class, [
            'label' => 'Nový termín',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'with_seconds' => false,
            'html5' => false,
            'format' => 'yyyy-MM-dd HH:mm',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PostponeSportMatchFormData::class,
        ]);
    }
}
