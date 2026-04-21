<?php

declare(strict_types=1);

namespace App\Twig\Components\Auth;

use App\Command\CompleteInvitationRegistration\CompleteInvitationRegistrationCommand;
use App\Command\RegisterUser\RegisterUserCommand;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\GroupInvitationAlreadyAccepted;
use App\Exception\GroupInvitationAlreadyRevoked;
use App\Exception\GroupInvitationExpired;
use App\Exception\InvitationAlreadyRegistered;
use App\Exception\NicknameAlreadyTaken;
use App\Exception\UserAlreadyExists;
use App\Form\InvitationFormData;
use App\Form\InvitationFormType;
use App\Repository\UserRepository;
use App\Service\Invitation\InvitationAcceptanceService;
use App\Service\Invitation\InvitationContext;
use App\Service\Invitation\InvitationContextResolver;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
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

        $this->submitForm();

        /** @var InvitationFormData $data */
        $data = $this->getForm()->getData();
        $data->userKind = $kind;
        $email = $this->resolveSubmittedEmail($data->email);

        return match ($kind) {
            InvitationFormData::KIND_NEW => $this->handleRegister($data, $email),
            InvitationFormData::KIND_HAS_PASSWORD => $this->handleLogin($data, $email),
            InvitationFormData::KIND_STUB => $this->handleCompleteRegistration($data),
            default => throw new \LogicException(sprintf('Unhandled userKind "%s".', $kind)),
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
                // Race: account was just created. Tell the user and let them try again as login.
                $this->acceptanceService->flash('info', 'Na tento e-mail už existuje účet. Přihlaš se prosím.');

                return null;
            }

            if ($previous instanceof NicknameAlreadyTaken) {
                $this->addInlineFormError('nickname', 'Tato přezdívka je již obsazena.');

                return null;
            }

            throw $e;
        }

        $user = $this->userRepository->findByEmail($email);
        \assert($user instanceof User, 'Just-registered user must exist.');

        if (InvitationKind::Email === $this->context->kind) {
            $this->security->login($user);

            return $this->acceptanceService->joinGroupAsUser($this->context, $user);
        }

        // Shareable-link: the new user is unverified; remember the intent so LoginSubscriber
        // completes the join after they verify their email.
        $this->acceptanceService->rememberIntent($this->context);
        $this->security->login($user);
        $this->acceptanceService->flash('success', 'Registrace proběhla úspěšně. Potvrď e-mail, ať tě přidáme do skupiny.');

        return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
    }

    private function handleLogin(InvitationFormData $data, string $email): ?Response
    {
        \assert(null !== $data->password);

        if (InvitationKind::Email === $this->context->kind
            && null !== $this->context->presetEmail
            && 0 !== strcasecmp($email, $this->context->presetEmail)
        ) {
            $this->acceptanceService->flash('warning', sprintf('Tato pozvánka je určena pro adresu %s.', $this->context->presetEmail));

            return null;
        }

        $user = $this->userRepository->findByEmail($email);

        if (null === $user || !$user->hasPassword) {
            $this->addInlineFormError('password', 'Nesprávný e-mail nebo heslo.');

            return null;
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data->password)) {
            $this->addInlineFormError('password', 'Nesprávný e-mail nebo heslo.');

            return null;
        }

        if (!$user->isVerified) {
            $this->acceptanceService->rememberIntent($this->context);
            $this->security->login($user);
            $this->acceptanceService->flash('warning', 'Nejprve si ověř svou e-mailovou adresu. Zkontroluj schránku.');

            return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
        }

        $this->security->login($user);

        return $this->acceptanceService->joinGroupAsUser($this->context, $user);
    }

    private function handleCompleteRegistration(InvitationFormData $data): ?Response
    {
        \assert(null !== $data->password);
        \assert(InvitationKind::Email === $this->context->kind, 'Stub completion only happens with email-invite kind.');

        try {
            $this->commandBus->dispatch(new CompleteInvitationRegistrationCommand(
                token: $this->context->token,
                plainPassword: $data->password,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof GroupInvitationExpired
                || $previous instanceof GroupInvitationAlreadyAccepted
                || $previous instanceof GroupInvitationAlreadyRevoked
            ) {
                return $this->acceptanceService->renderStatus($this->acceptanceService->refreshContext($this->context));
            }

            if ($previous instanceof InvitationAlreadyRegistered) {
                // Account already has a password — log in instead.
                $this->acceptanceService->flash('info', 'Účet už má heslo. Přihlaš se prosím.');

                return null;
            }

            throw $e;
        }

        \assert(null !== $this->context->presetEmail);
        $user = $this->userRepository->findByEmail($this->context->presetEmail);
        \assert($user instanceof User, 'User with invitation email must exist after completing registration.');

        $this->security->login($user);
        $this->acceptanceService->flash('success', 'Registrace dokončena, vítej ve skupině!');

        return new RedirectResponse($this->urlGenerator->generate(
            'portal_group_detail',
            ['id' => $this->context->groupId->toRfc4122()],
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
     * See RegistrationForm::addInlineFormError() — same workaround for the trait's cached FormView.
     */
    private function addInlineFormError(string $fieldName, string $message): void
    {
        $this->getForm()->get($fieldName)->addError(new FormError($message));

        $reflection = new \ReflectionProperty(self::class, 'formView');
        $reflection->setValue($this, null);
    }
}
