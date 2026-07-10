<?php

declare(strict_types=1);

namespace App\Form;

use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<BuyCreditsFormData>
 */
final class BuyCreditsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('credits', IntegerType::class, [
            'label' => 'Počet kreditů',
            'attr' => [
                'min' => InitiateCreditPurchaseCommand::MINIMUM_CREDITS,
                'max' => InitiateCreditPurchaseCommand::MAXIMUM_CREDITS,
                'step' => 1,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BuyCreditsFormData::class,
        ]);
    }
}
