<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ProfileFormData>
 */
final class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // maxlength mirrors the Length constraints of ProfileFormData, so the browser stops
        // the typing where the server would reject it.
        $builder->add('firstName', TextType::class, [
            'label' => 'Jméno',
            'required' => false,
            'attr' => [
                'placeholder' => 'Jan',
                'autocomplete' => 'given-name',
                'maxlength' => ProfileFormData::NAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('lastName', TextType::class, [
            'label' => 'Příjmení',
            'required' => false,
            'attr' => [
                'placeholder' => 'Novák',
                'autocomplete' => 'family-name',
                'maxlength' => ProfileFormData::NAME_MAX_LENGTH,
            ],
        ]);

        $builder->add('phone', TelType::class, [
            'label' => 'Telefon',
            'required' => false,
            'help' => 'Číslo s předvolbou, např. +420 123 456 789.',
            'help_attr' => ['class' => 'mt-1 text-xs leading-relaxed text-white/45'],
            'attr' => [
                'placeholder' => '+420 123 456 789',
                'autocomplete' => 'tel',
                'inputmode' => 'tel',
                'maxlength' => ProfileFormData::PHONE_MAX_LENGTH,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfileFormData::class,
        ]);
    }
}
