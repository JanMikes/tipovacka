<?php

declare(strict_types=1);

namespace App\Service\SignUp;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Records WHY a sign-up form was rejected, so „I cannot register" is answerable from logs.
 *
 * A Live form's validation errors travel only to the visitor's browser: on 2026-10-17 a
 * visitor submitted four times, every attempt was a 422, and nothing on the server could
 * say which field failed. The `signup` channel goes to stderr in prod (outside the
 * fingers_crossed buffer) and on to Sentry Logs. Field names and messages only — never
 * a password; the e-mail stays because support has to find the visitor's attempts.
 */
#[WithMonologChannel('signup')]
final readonly class RejectedSignUpLog
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param FormInterface<mixed>       $form
     * @param array<string, scalar|null> $context
     */
    public function record(string $path, FormInterface $form, string $email, array $context = []): void
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            $errors[$this->fieldPath($error->getOrigin())][] = $error->getMessage();
        }

        $this->logger->info('Sign-up rejected', [
            'path' => $path,
            'email' => $email,
            'errors' => $errors,
            ...$context,
        ]);
    }

    /**
     * @param FormInterface<mixed>|null $field
     */
    private function fieldPath(?FormInterface $field): string
    {
        $names = [];

        while (null !== $field && null !== $field->getParent()) {
            $names[] = $field->getName();
            $field = $field->getParent();
        }

        return [] === $names ? '(form)' : implode('.', array_reverse($names));
    }
}
