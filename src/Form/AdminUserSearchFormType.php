<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AdminUserSearchFormData>
 */
final class AdminUserSearchFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('search', SearchType::class, [
            'label' => 'Hledat (e-mail nebo přezdívka)',
            'required' => false,
        ]);

        $builder->add('verified', ChoiceType::class, [
            'label' => 'Ověření',
            'expanded' => true,
            'multiple' => false,
            'choices' => [
                'Vše' => AdminUserSearchFormData::VERIFIED_ALL,
                'Ověření' => AdminUserSearchFormData::VERIFIED_VERIFIED,
                'Neověření' => AdminUserSearchFormData::VERIFIED_UNVERIFIED,
            ],
        ]);

        $builder->add('active', ChoiceType::class, [
            'label' => 'Stav',
            'expanded' => true,
            'multiple' => false,
            'choices' => [
                'Vše' => AdminUserSearchFormData::ACTIVE_ALL,
                'Aktivní' => AdminUserSearchFormData::ACTIVE_ACTIVE,
                'Zablokovaní' => AdminUserSearchFormData::ACTIVE_BLOCKED,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdminUserSearchFormData::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
