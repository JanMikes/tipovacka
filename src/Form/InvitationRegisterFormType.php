<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InvitationRegisterFormData>
 */
final class InvitationRegisterFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nickname', TextType::class, [
            'label' => 'Přezdívka',
            'attr' => [
                'placeholder' => 'vase_prezdivka',
                'autocomplete' => 'nickname',
                'autofocus' => true,
            ],
        ]);

        $builder->add('password', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options' => [
                'label' => 'Heslo',
                'attr' => [
                    'placeholder' => 'Zvolte si heslo',
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvitationRegisterFormData::class,
            'csrf_protection' => false,
        ]);
    }
}
