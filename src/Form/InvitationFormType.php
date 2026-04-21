<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InvitationFormData>
 */
final class InvitationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $userKind = $options['user_kind'];
        \assert(\is_string($userKind));
        $lockEmail = (bool) $options['lock_email'];

        $builder->add('email', EmailType::class, [
            'label' => 'E-mailová adresa',
            'empty_data' => '',
            'disabled' => $lockEmail,
            'attr' => [
                'placeholder' => 'vas@email.cz',
                'autocomplete' => 'email',
                'autofocus' => !$lockEmail,
            ],
        ]);

        $builder->add('password', PasswordType::class, [
            'label' => InvitationFormData::KIND_HAS_PASSWORD === $userKind ? 'Heslo' : 'Zvolte si heslo',
            'always_empty' => false,
            'attr' => [
                'placeholder' => 'Heslo',
                'autocomplete' => InvitationFormData::KIND_HAS_PASSWORD === $userKind
                    ? 'current-password'
                    : 'new-password',
            ],
        ]);

        if (InvitationFormData::KIND_HAS_PASSWORD !== $userKind) {
            $builder->add('passwordConfirm', PasswordType::class, [
                'label' => 'Heslo znovu',
                'always_empty' => false,
                'mapped' => true,
                'attr' => [
                    'placeholder' => 'Zopakujte heslo',
                    'autocomplete' => 'new-password',
                ],
            ]);
        }

        if (InvitationFormData::KIND_NEW === $userKind) {
            $builder->add('nickname', TextType::class, [
                'label' => 'Přezdívka',
                'empty_data' => '',
                'attr' => [
                    'placeholder' => 'vase_prezdivka',
                    'autocomplete' => 'nickname',
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
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvitationFormData::class,
            'csrf_protection' => false,
            // Form shape changes between renders (e.g. user enters an existing email
            // and the nickname/name inputs disappear). Tolerate stale field names in
            // the submitted payload — they're meaningful for the previous shape only.
            'allow_extra_fields' => true,
            'user_kind' => InvitationFormData::KIND_NEW,
            'lock_email' => false,
        ]);

        $resolver->setAllowedValues('user_kind', [
            InvitationFormData::KIND_NEW,
            InvitationFormData::KIND_HAS_PASSWORD,
            InvitationFormData::KIND_STUB,
        ]);

        $resolver->setAllowedTypes('lock_email', 'bool');
    }
}
