<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ResetPasswordFormData>
 */
final class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // B17 — same autofill semantics as the registration pair: `new-password` on both
        // halves, or the browser prompts to save after the first field.
        $builder->add('newPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'options' => [
                'always_empty' => false,
            ],
            'first_options' => [
                'label' => 'Nové heslo',
                'attr' => [
                    'placeholder' => 'Zadejte nové heslo',
                    'autocomplete' => 'new-password',
                ],
            ],
            'second_options' => [
                'label' => 'Potvrzení hesla',
                'attr' => [
                    'placeholder' => 'Zopakujte nové heslo',
                    'autocomplete' => 'new-password',
                ],
            ],
            'invalid_message' => 'Hesla se musí shodovat.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ResetPasswordFormData::class,
        ]);
    }
}
