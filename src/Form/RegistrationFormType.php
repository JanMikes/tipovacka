<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RegistrationFormData>
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'E-mailová adresa',
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'vas@email.cz',
                'autocomplete' => 'email',
            ],
        ]);

        $builder->add('firstName', TextType::class, [
            'label' => 'Jméno',
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'Jan',
                'autocomplete' => 'given-name',
            ],
        ]);

        $builder->add('lastName', TextType::class, [
            'label' => 'Příjmení',
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'Novák',
                'autocomplete' => 'family-name',
            ],
        ]);

        $builder->add('nickname', TextType::class, [
            'label' => 'Přezdívka',
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'vase_prezdivka',
                'autocomplete' => 'nickname',
            ],
        ]);

        // B17 — both halves of the pair must say `new-password`. Without it the browser
        // sees a lone password box, decides the user just signed in and offers to save
        // the password before the confirmation has even been typed.
        $builder->add('password', RepeatedType::class, [
            'type' => PasswordType::class,
            'options' => [
                'always_empty' => false,
            ],
            'first_options' => [
                'label' => 'Heslo',
                'attr' => [
                    'placeholder' => 'Zadejte heslo',
                    'autocomplete' => 'new-password',
                ],
            ],
            'second_options' => [
                'label' => 'Heslo znovu',
                'attr' => [
                    'placeholder' => 'Zopakujte heslo',
                    'autocomplete' => 'new-password',
                ],
            ],
            'invalid_message' => 'Hesla se musí shodovat.',
        ]);

        $builder->add('gdprConsent', CheckboxType::class, [
            'label' => 'Souhlasím se zpracováním osobních údajů',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationFormData::class,
        ]);
    }
}
