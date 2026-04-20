<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InvitationEmailFormData>
 */
final class InvitationEmailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'E-mailová adresa',
            'attr' => [
                'placeholder' => 'vas@email.cz',
                'autocomplete' => 'email',
                'autofocus' => true,
            ],
            'disabled' => true === $options['lock_email'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvitationEmailFormData::class,
            'lock_email' => false,
            // The secret lives in the URL token (unguessable by an attacker), so CSRF
            // protection would only hurt reliability when the session is wiped between steps.
            'csrf_protection' => false,
        ]);
        $resolver->setAllowedTypes('lock_email', 'bool');
    }
}
