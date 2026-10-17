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
                'empty_data' => '',
                'attr' => ['autocomplete' => 'off', 'maxlength' => 100],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Příjmení',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'off', 'maxlength' => 100],
            ])
            ->add('nickname', TextType::class, [
                'label' => 'Přezdívka (volitelné)',
                'required' => false,
                'help' => 'Nepovinné. 3–30 znaků: písmena bez diakritiky, číslice, tečka, pomlčka nebo podtržítko (např. petr.novak). Když ji nevyplníte, v přehledu se zobrazí celé jméno.',
                'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'maxlength' => 30],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AnonymousMemberFormData::class,
        ]);
    }
}
