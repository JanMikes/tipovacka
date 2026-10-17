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
            'help' => sprintf('Celé číslo, 1 kredit = 1 Kč. Kladné číslo kredity přidá, záporné odebere (korekce) — nejvýše %d najednou a zůstatek nesmí klesnout pod nulu.', AdjustCreditsFormData::MAX_ADJUSTMENT),
            'invalid_message' => 'Zadejte celé číslo bez desetinných míst, např. 500 nebo -200.',
            'attr' => [
                'placeholder' => 'Např. 500',
                'min' => -AdjustCreditsFormData::MAX_ADJUSTMENT,
                'max' => AdjustCreditsFormData::MAX_ADJUSTMENT,
                'step' => 1,
            ],
        ]);

        $builder->add('note', TextareaType::class, [
            'label' => 'Poznámka',
            'help' => 'Povinné. Zobrazí se uživateli v historii transakcí.',
            'attr' => [
                'maxlength' => 500,
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
