<?php

declare(strict_types=1);

namespace App\Twig\Components\Auth;

use App\Command\CompleteInvitationRegistration\CompleteInvitationRegistrationCommand;
use App\Command\RegisterUser\RegisterUserCommand;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\CompetitionInvitationAlreadyAccepted;
use App\Exception\CompetitionInvitationAlreadyRevoked;
use App\Exception\CompetitionInvitationExpired;
use App\Exception\InvitationAlreadyRegistered;
use App\Exception\NicknameAlreadyTaken;
use App\Exception\UserAlreadyExists;
use App\Form\InvitationFormData;
use App\Form\InvitationFormType;
use App\Repository\UserRepository;
use App\Service\Invitation\InvitationAcceptanceService;
use App\Service\Invitation\InvitationContext;
use App\Service\Invitation\InvitationContextResolver;
use App\Service\SignUp\EmailTypoSuggester;
use App\Service\SignUp\NicknameSuggester;
use App\Service\SignUp\RejectedSignUpLog;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Single adaptive form for the invitation landing flow.
 *
 * Always renders email + password. Re-renders on email change to look up the user:
 *   - userKind === 'new'           → reveals nickname / firstName / lastName / passwordConfirm; submits a registration
 *   - userKind === 'has_password'  → password-only login flow
 *   - userKind === 'stub'          → password + confirm; sets password on a pre-provisioned account (email-kind only)
 *
 * For email-kind invitations with a presetEmail, the email field is locked and
 * userKind is determined from that fixed email (no auto-detection from typing).
 */
#[AsLiveComponent(name: 'Auth:InvitationForm')]
final class InvitationForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public string $kind = '';

    #[LiveProp]
    public string $token = '';


    private ?InvitationContext $resolvedContext = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Security $security,
        private readonly InvitationAcceptanceService $acceptanceService,
        private readonly InvitationContextResolver $contextResolver,
        private readonly ClockInterface $clock,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly NicknameSuggester $nicknameSuggester,
        private readonly EmailTypoSuggester $emailTypoSuggester,
        private readonly RejectedSignUpLog $rejectedSignUpLog,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public InvitationContext $context {
        get => $this->resolvedContext ??= $this->contextResolver->resolve(
            InvitationKind::from($this->kind),
            $this->token,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }

    public string $userKind {
        get => $this->resolveUserKind();
    }

    /**
     * A free nickname the rules accept, offered while the typed one is rejected.
     */
    public ?string $nicknameSuggestion {
        get {
            $form = $this->getForm();

            if (!$form->has('nickname') || 0 === \count($form->get('nickname')->getErrors())) {
                return null;
            }

            return $this->nicknameSuggester->suggest(
                (string) ($this->formValues['nickname'] ?? ''),
                (string) ($this->formValues['firstName'] ?? ''),
                (string) ($this->formValues['lastName'] ?? ''),
            );
        }
    }

    /**
     * The address with its mailbox-domain typo corrected (seznam.cy → seznam.cz), if any.
     */
    public ?string $emailSuggestion {
        get => $this->emailLocked ? null : $this->emailTypoSuggester->suggest((string) ($this->formValues['email'] ?? ''));
    }

    public bool $emailLocked {
        get => InvitationKind::Email === $this->context->kind && null !== $this->context->presetEmail;
    }

    /**
     * @return FormInterface<InvitationFormData>
     */
    protected function instantiateForm(): FormInterface
    {
        $data = new InvitationFormData();
        $data->userKind = $this->userKind;

        if ($this->emailLocked) {
            \assert(null !== $this->context->presetEmail);
            $data->email = $this->context->presetEmail;
        }

        return $this->createForm(InvitationFormType::class, $data, [
            'user_kind' => $data->userKind,
            'lock_email' => $this->emailLocked,
        ]);
    }

    #[LiveAction]
    public function submit(): ?Response
    {
        $kind = $this->userKind;

        if ($this->emailLocked) {
            \assert(null !== $this->context->presetEmail);
            $this->formValues['email'] = $this->context->presetEmail;
        }

        try {
            $this->submitForm();
        } catch (UnprocessableEntityHttpException $e) {
            $this->logRejection();

            throw $e;
        }

        /** @var InvitationFormData $data */
        $data = $this->getForm()->getData();
        $data->userKind = $kind;
        $email = $this->resolveSubmittedEmail($data->email);

        $response = match ($kind) {
            InvitationFormData::KIND_NEW => $this->handleRegister($data, $email),
            InvitationFormData::KIND_HAS_PASSWORD => $this->handleLogin($data, $email),
            InvitationFormData::KIND_STUB => $this->handleCompleteRegistration($data),
            default => throw new \LogicException(sprintf('Unhandled userKind "%s".', $kind)),
        };

        // A status page (the invitation expired / was revoked / accepted meanwhile) is a
        // whole document, but the answer to a Live action replaces only this component —
        // the visitor would get a landing page nested in the form, or a broken render.
        // Reload the landing instead: it re-resolves the invitation and shows that status.
        if (null !== $response && !$response->isRedirection()) {
            return new RedirectResponse($this->landingUrl());
        }

        return $response;
    }

    private function landingUrl(): string
    {
        return match ($this->context->kind) {
            InvitationKind::Email => $this->urlGenerator->generate('competition_accept_invitation', ['token' => $this->context->token]),
            InvitationKind::ShareableLink => $this->urlGenerator->generate('competition_join_by_link', ['token' => $this->context->token]),
            InvitationKind::Pin => $this->urlGenerator->generate('competition_join_by_pin'),
            InvitationKind::GlobalCompetition => $this->urlGenerator->generate('competition_global_invitation', ['id' => $this->context->competitionId->toRfc4122()]),
        };
    }

    private function handleRegister(InvitationFormData $data, string $email): ?Response
    {
        \assert(null !== $data->password);

        try {
            $this->commandBus->dispatch(new RegisterUserCommand(
                email: $email,
                nickname: $data->nickname,
                plainPassword: $data->password,
                firstName: $data->firstName,
                lastName: $data->lastName,
                autoVerify: InvitationKind::Email === $this->context->kind,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof UserAlreadyExists) {
                // Race: account was just created. Say so at the field — a flash would only
                // surface on the NEXT full page load, never in this Live re-render.
                $this->reject('email', 'Na tento e-mail už existuje účet. Zadejte k němu heslo a přihlaste se.');

                return null;
            }

            if ($previous instanceof NicknameAlreadyTaken) {
                $this->reject('nickname', 'Tato přezdívka je již obsazena — zvolte si prosím jinou.');

                return null;
            }

            throw $e;
        }

        $user = $this->userRepository->findByEmail($email);
        \assert($user instanceof User, 'Just-registered user must exist.');

        if (InvitationKind::Email === $this->context->kind) {
            $this->security->login($user);

            return $this->acceptanceService->joinCompetitionAsUser($this->context, $user);
        }

        // Shareable link / PIN: the new user is unverified, so the join has to wait. The
        // intent is recorded ON THE ACCOUNT (not only in the session) because the next
        // step happens in a mailbox — possibly in another browser (B15).
        $this->acceptanceService->rememberIntent($this->context, $user);
        $this->security->login($user);
        $this->acceptanceService->flash('success', sprintf(
            'Registrace proběhla úspěšně. Potvrďte e-mail a rovnou vás přidáme do soutěže %s.',
            $this->context->competitionName,
        ));

        return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
    }

    private function handleLogin(InvitationFormData $data, string $email): ?Response
    {
        \assert(null !== $data->password);

        if (InvitationKind::Email === $this->context->kind
            && null !== $this->context->presetEmail
            && 0 !== strcasecmp($email, $this->context->presetEmail)
        ) {
            $this->reject('email', sprintf('Tato pozvánka je určena pro adresu %s — přihlaste se k ní.', $this->context->presetEmail));

            return null;
        }

        $user = $this->userRepository->findByEmail($email);

        if (null === $user || !$user->hasPassword || !$this->passwordHasher->isPasswordValid($user, $data->password)) {
            $this->reject('password', 'Nesprávný e-mail nebo heslo. Zapomenuté heslo si můžete obnovit odkazem nad polem.');

            return null;
        }

        // Email-kind invitations are addressed to a specific mailbox; accepting one
        // verifies the account in the handler, so we don't need to gate login here.
        if (!$user->isVerified && InvitationKind::Email !== $this->context->kind) {
            $this->acceptanceService->rememberIntent($this->context, $user);
            $this->security->login($user);
            $this->acceptanceService->flash('warning', sprintf(
                'Nejprve si prosím ověřte e-mailovou adresu — pak vás rovnou přidáme do soutěže %s.',
                $this->context->competitionName,
            ));

            return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
        }

        $this->security->login($user);

        return $this->acceptanceService->joinCompetitionAsUser($this->context, $user);
    }

    private function handleCompleteRegistration(InvitationFormData $data): ?Response
    {
        \assert(null !== $data->password);

        // Only an e-mail invitation carries the token that can set a password on a
        // pre-provisioned account. Reached through a shareable link or a PIN, the same
        // address belongs to an account we cannot prove the visitor owns — send them
        // through password reset instead of handing it over.
        if (InvitationKind::Email !== $this->context->kind) {
            $this->reject(
                'email',
                'Na tento e-mail už účet existuje, ale ještě nemá heslo. Nastavte si ho přes Zapomenuté heslo na přihlašovací stránce a pak se přihlaste.',
            );

            return null;
        }

        try {
            $this->commandBus->dispatch(new CompleteInvitationRegistrationCommand(
                token: $this->context->token,
                plainPassword: $data->password,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof CompetitionInvitationExpired
                || $previous instanceof CompetitionInvitationAlreadyAccepted
                || $previous instanceof CompetitionInvitationAlreadyRevoked
            ) {
                return $this->acceptanceService->renderStatus($this->acceptanceService->refreshContext($this->context));
            }

            if ($previous instanceof InvitationAlreadyRegistered) {
                // Account already has a password — log in instead.
                $this->reject('password', 'Tento účet už heslo má. Obnovte stránku a přihlaste se svým heslem.');

                return null;
            }

            throw $e;
        }

        \assert(null !== $this->context->presetEmail);
        $user = $this->userRepository->findByEmail($this->context->presetEmail);
        \assert($user instanceof User, 'User with invitation email must exist after completing registration.');

        $this->security->login($user);
        $this->acceptanceService->flash('success', 'Registrace je dokončená. Vítejte v soutěži!');

        // `?pripojeno=1` (item 28) states the fact „you have just joined" — completing a
        // pre-provisioned account's registration creates the membership right here, so
        // this is a join landing too, and the boost-price modal waits for the next visit.
        return new RedirectResponse($this->urlGenerator->generate(
            'competition_detail',
            ['id' => $this->context->competitionId->toRfc4122(), 'pripojeno' => 1],
        ));
    }

    private function resolveUserKind(): string
    {
        $email = $this->emailLocked
            ? ($this->context->presetEmail ?? '')
            : (string) ($this->formValues['email'] ?? '');

        $email = strtolower(trim($email));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return InvitationFormData::KIND_NEW;
        }

        $user = $this->userRepository->findByEmail($email);

        if (null === $user) {
            return InvitationFormData::KIND_NEW;
        }

        return $user->hasPassword ? InvitationFormData::KIND_HAS_PASSWORD : InvitationFormData::KIND_STUB;
    }

    private function resolveSubmittedEmail(string $submitted): string
    {
        if ($this->emailLocked) {
            \assert(null !== $this->context->presetEmail);

            return strtolower($this->context->presetEmail);
        }

        return strtolower(trim($submitted));
    }

    /**
     * A rejection found after validation passed: shown at the field (and in the summary),
     * and logged like a validation failure.
     */
    private function reject(string $fieldName, string $message): void
    {
        $this->addInlineFormError($fieldName, $message);
        $this->logRejection();
    }

    private function logRejection(): void
    {
        $this->rejectedSignUpLog->record(
            'invitation:'.$this->kind,
            $this->getForm(),
            strtolower(trim((string) ($this->formValues['email'] ?? ''))),
            ['user_kind' => $this->userKind],
        );
    }

    /**
     * See RegistrationForm::addInlineFormError() — same workaround for the trait's cached FormView.
     */
    private function addInlineFormError(string $fieldName, string $message): void
    {
        $this->getForm()->get($fieldName)->addError(new FormError($message));

        $reflection = new \ReflectionProperty(self::class, 'formView');
        $reflection->setValue($this, null);
    }
}
