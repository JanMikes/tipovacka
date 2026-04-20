<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<BulkInvitationFormData>
 */
final class BulkInvitationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('emails', TextareaType::class, [
            'label' => 'E-maily (jeden na řádek nebo oddělené čárkou)',
            'attr' => [
                'rows' => 6,
                'placeholder' => "jan@example.com\nlucie@example.com, petr@example.com",
                'autocomplete' => 'off',
                'inputmode' => 'email',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BulkInvitationFormData::class,
        ]);
    }
}
