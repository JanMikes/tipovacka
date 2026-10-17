<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<JoinByPinFormData>
 */
final class JoinByPinFormType extends AbstractType
{
    /**
     * A PIN is read out loud and copied from chats, so it arrives as „1234 5678",
     * „1234-5678" or with a stray space — all of them the same PIN. Room for one
     * separator; anything that is not a digit is dropped before validation.
     */
    public const int INPUT_MAX_LENGTH = 9;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('pin', TextType::class, [
            'label' => 'PIN soutěže',
            'empty_data' => '',
            'help' => '8 číslic od organizátora soutěže. Mezery a pomlčky nevadí.',
            'attr' => [
                'placeholder' => '12345678',
                'inputmode' => 'numeric',
                'autocomplete' => 'off',
                'autocapitalize' => 'none',
                'spellcheck' => 'false',
                'maxlength' => self::INPUT_MAX_LENGTH,
                'autofocus' => true,
            ],
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();

            if (!\is_array($data) || !\is_string($data['pin'] ?? null)) {
                return;
            }

            $data['pin'] = self::normalize($data['pin']);
            $event->setData($data);
        });
    }

    /**
     * Strips the separators people type into a PIN (spaces, dashes). Letters stay, so
     * „1234567O" still fails validation with a message about digits instead of silently
     * turning into a 7-digit PIN.
     */
    public static function normalize(string $pin): string
    {
        return (string) preg_replace('/[\s\-–—]+/u', '', $pin);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => JoinByPinFormData::class,
        ]);
    }
}
