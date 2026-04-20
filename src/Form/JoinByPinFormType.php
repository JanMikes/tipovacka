<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<JoinByPinFormData>
 */
final class JoinByPinFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pin', TextType::class, [
            'label' => 'PIN skupiny (8 číslic)',
            'attr' => [
                'placeholder' => '12345678',
                'inputmode' => 'numeric',
                'autocomplete' => 'off',
                'maxlength' => 8,
                'minlength' => 8,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => JoinByPinFormData::class,
        ]);
    }
}
