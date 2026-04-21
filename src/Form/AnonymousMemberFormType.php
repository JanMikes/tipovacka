<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AnonymousMemberFormData>
 */
final class AnonymousMemberFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Jméno',
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Příjmení',
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('nickname', TextType::class, [
                'label' => 'Přezdívka (volitelné)',
                'required' => false,
                'help' => 'Pokud nevyplníš, v přehledu se zobrazí celé jméno.',
                'attr' => ['autocomplete' => 'off'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AnonymousMemberFormData::class,
        ]);
    }
}
