<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ChangePasswordFormData>
 */
final class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, [
            'label' => 'Současné heslo',
            'always_empty' => false,
            'attr' => [
                'placeholder' => 'Zadejte současné heslo',
                'autocomplete' => 'current-password',
            ],
        ]);

        // B17 — both halves of the pair say `new-password`, same as registration and the
        // reset form: a lone password box makes the browser offer to save what was typed
        // before the confirmation exists.
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
            'data_class' => ChangePasswordFormData::class,
        ]);
    }
}
