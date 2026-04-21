<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[Route('/overeni-ceka/znovu-odeslat', name: 'app_resend_verification_email', methods: ['POST'])]
final class ResendVerificationEmailController extends AbstractController
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('resend_verification', (string) $request->request->get('_token'))) {
            throw new InvalidCsrfTokenException();
        }

        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Reload from DB to ensure fresh state
        $user = $this->userRepository->find($currentUser->id);

        if (null === $user || $user->isVerified) {
            $this->addFlash('info', 'Váš e-mail byl již ověřen.');

            return $this->redirectToRoute('portal_dashboard');
        }

        if (null === $user->email) {
            throw $this->createNotFoundException();
        }

        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            routeName: 'app_verify_email',
            userId: (string) $user->id,
            userEmail: $user->email,
            extraParams: ['id' => (string) $user->id],
        );

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@tipovacka.cz', 'Tipovačka'))
            ->to(new Address($user->email, $user->displayName))
            ->subject('Ověřte prosím svou e-mailovou adresu')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'nickname' => $user->displayName,
                'verificationUrl' => $signatureComponents->getSignedUrl(),
                'expiresAt' => $signatureComponents->getExpiresAt(),
            ]);

        $this->mailer->send($email);

        $this->addFlash('success', 'Ověřovací e-mail byl znovu odeslán. Zkontrolujte svoji schránku.');

        return $this->redirectToRoute('app_verify_email_pending');
    }
}
