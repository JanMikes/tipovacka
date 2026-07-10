<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AdjustCreditsFormData>
 */
final class AdjustCreditsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('amount', IntegerType::class, [
            'label' => 'Počet kreditů',
            'help' => 'Kladné číslo kredity přidá, záporné odebere (korekce).',
            'attr' => [
                'placeholder' => 'Např. 500',
            ],
        ]);

        $builder->add('note', TextareaType::class, [
            'label' => 'Poznámka',
            'help' => 'Zobrazí se uživateli v historii transakcí.',
            'attr' => [
                'placeholder' => 'Např. Kompenzace za výpadek, výhra v soutěži…',
                'rows' => 2,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdjustCreditsFormData::class,
        ]);
    }
}
