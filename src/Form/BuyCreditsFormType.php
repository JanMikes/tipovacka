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
            // A non-number (12,5 / „sto") never reaches the validator — without this the
            // visitor gets the framework's generic sentence instead of what to type.
            'invalid_message' => 'Zadejte prosím celé číslo bez mezer a desetinné čárky, např. 250.',
            'help' => sprintf(
                'Celé číslo od %d do %s. 1 kredit = 1 Kč.',
                InitiateCreditPurchaseCommand::MINIMUM_CREDITS,
                number_format(InitiateCreditPurchaseCommand::MAXIMUM_CREDITS, 0, ',', ' '),
            ),
            'help_attr' => ['class' => 'mt-1 text-xs leading-relaxed text-white/45'],
            'attr' => [
                'min' => InitiateCreditPurchaseCommand::MINIMUM_CREDITS,
                'max' => InitiateCreditPurchaseCommand::MAXIMUM_CREDITS,
                'step' => 1,
                'inputmode' => 'numeric',
                'autocomplete' => 'off',
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
