<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/prihlaseni', name: 'app_login')]
final class LoginController extends AbstractController
{
    /** A wrong e-mail or password — the visitor can fix it themselves. */
    public const string FAILURE_CREDENTIALS = 'credentials';

    /** The account itself is closed (deleted, blocked) — only we can help. */
    public const string FAILURE_ACCOUNT = 'account';

    /** Anything else (throttling, a stale session) — the translated framework message. */
    public const string FAILURE_OTHER = 'other';

    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('auth/login.html.twig', [
            'error' => $error,
            'failure' => null === $error ? null : $this->classify($error),
            'lastUsername' => $authenticationUtils->getLastUsername(),
        ]);
    }

    /**
     * Each failure tells the visitor the NEXT step, so the template needs to know which
     * kind it is: a wrong password points at the reset, a closed account at the contact.
     * An unknown e-mail is deliberately indistinguishable from a wrong password (the
     * framework turns it into BadCredentials), so the page never reveals who has an account.
     */
    private function classify(AuthenticationException $error): string
    {
        if ($error instanceof CustomUserMessageAuthenticationException
            || $error instanceof CustomUserMessageAccountStatusException
        ) {
            return self::FAILURE_ACCOUNT;
        }

        if ($error instanceof BadCredentialsException) {
            return self::FAILURE_CREDENTIALS;
        }

        return self::FAILURE_OTHER;
    }
}
