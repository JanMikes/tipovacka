<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Command\AcceptGroupInvitation\AcceptGroupInvitationCommand;
use App\Command\CompleteInvitationRegistration\CompleteInvitationRegistrationCommand;
use App\Command\JoinGroupByLink\JoinGroupByLinkCommand;
use App\Command\RegisterUser\RegisterUserCommand;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedTournament;
use App\Exception\GroupInvitationAlreadyAccepted;
use App\Exception\GroupInvitationAlreadyRevoked;
use App\Exception\GroupInvitationExpired;
use App\Exception\InvalidInvitationToken;
use App\Exception\InvalidShareableLink;
use App\Exception\NicknameAlreadyTaken;
use App\Exception\UserAlreadyExists;
use App\Form\CompleteInvitationRegistrationFormData;
use App\Form\CompleteInvitationRegistrationFormType;
use App\Form\InvitationEmailFormData;
use App\Form\InvitationEmailFormType;
use App\Form\InvitationLoginFormData;
use App\Form\InvitationLoginFormType;
use App\Form\InvitationRegisterFormData;
use App\Form\InvitationRegisterFormType;
use App\Repository\UserRepository;
use App\Service\Group\GroupJoinIntentSession;
use App\Service\Security\InvitationIntentSession;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class InvitationLandingProcessor
{
    public function __construct(
        private InvitationContextResolver $contextResolver,
        private FormFactoryInterface $formFactory,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MessageBusInterface $commandBus,
        private Security $security,
        private ClockInterface $clock,
        private InvitationIntentSession $invitationIntent,
        private GroupJoinIntentSession $joinIntent,
        private RequestStack $requestStack,
    ) {
    }

    public function handle(Request $request, InvitationKind $kind, string $token, ?User $currentUser): Response
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        try {
            $context = $this->contextResolver->resolve($kind, $token, $now);
        } catch (InvalidInvitationToken|InvalidShareableLink) {
            return $this->renderInvalid();
        }

        if (InvitationContextStatus::Active !== $context->status) {
            return $this->renderStatus($context);
        }

        if ($currentUser instanceof User) {
            return $this->handleAuthenticated($context, $currentUser);
        }

        return $this->handleAnonymous($request, $context);
    }

    private function handleAuthenticated(InvitationContext $context, User $currentUser): Response
    {
        if (!$currentUser->isVerified) {
            $this->rememberIntent($context);
            $this->flash('warning', 'Nejprve si ověř svou e-mailovou adresu.');

            return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
        }

        if (InvitationKind::Email === $context->kind
            && null !== $context->presetEmail
            && 0 !== strcasecmp($currentUser->email, $context->presetEmail)
        ) {
            return $this->renderTemplate([
                'step' => 'email_mismatch',
                'context' => $context,
                'current_user_email' => $currentUser->email,
            ]);
        }

        return $this->joinGroupAsUser($context, $currentUser);
    }

    private function handleAnonymous(Request $request, InvitationContext $context): Response
    {
        $action = $request->isMethod('POST') ? $request->request->get('_action') : null;

        return match ($action) {
            'check_email' => $this->processCheckEmail($request, $context),
            'login' => $this->processLogin($request, $context),
            'register' => $this->processRegister($request, $context),
            'complete_registration' => $this->processCompleteRegistration($request, $context),
            default => $this->renderEmailStep($context, $context->presetEmail ?? ''),
        };
    }

    private function processCheckEmail(Request $request, InvitationContext $context): Response
    {
        if (InvitationKind::Email === $context->kind && null !== $context->presetEmail) {
            // Email-invite: address is fixed by the token, skip form validation entirely.
            $email = strtolower($context->presetEmail);
        } else {
            $form = $this->createEmailForm($context);
            $form->handleRequest($request);

            if (!$form->isSubmitted() || !$form->isValid()) {
                return $this->renderTemplate([
                    'step' => 'email',
                    'context' => $context,
                    'email_form' => $form->createView(),
                ]);
            }

            $email = $this->resolveEmailForContext($context, $form->getData()->email);
        }

        $user = $this->userRepository->findByEmail($email);

        if (null === $user) {
            return $this->renderRegisterStep($context, $email);
        }

        if (!$user->hasPassword) {
            if (InvitationKind::Email !== $context->kind) {
                // A shareable-link lands on a stub account only when another invitation pre-provisioned it.
                // The stub account has no password, so normal login is impossible — send them to that invitation's completion flow.
                $this->flash('info', 'Na tento e-mail už máš rozpracovanou pozvánku. Dokonči registraci přes odkaz, který ti přišel do e-mailu.');

                return $this->renderEmailStep($context, $email);
            }

            return $this->renderCompleteRegistrationStep($context, $email);
        }

        return $this->renderLoginStep($context, $email);
    }

    private function processLogin(Request $request, InvitationContext $context): Response
    {
        $email = (string) $request->request->get('email', $context->presetEmail ?? '');
        $email = trim($email);

        $form = $this->createLoginForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderLoginStep($context, $email, $form);
        }

        if ('' === $email) {
            return $this->renderEmailStep($context, '');
        }

        if (InvitationKind::Email === $context->kind
            && null !== $context->presetEmail
            && 0 !== strcasecmp($email, $context->presetEmail)
        ) {
            $this->flash('warning', sprintf('Tato pozvánka je určena pro adresu %s.', $context->presetEmail));

            return $this->renderEmailStep($context, $context->presetEmail);
        }

        $user = $this->userRepository->findByEmail($email);

        if (null === $user || !$user->hasPassword) {
            $form->get('password')->addError(new FormError('Nesprávný e-mail nebo heslo.'));

            return $this->renderLoginStep($context, $email, $form);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $form->getData()->password)) {
            $form->get('password')->addError(new FormError('Nesprávný e-mail nebo heslo.'));

            return $this->renderLoginStep($context, $email, $form);
        }

        if (!$user->isVerified) {
            // Legitimate account but unverified — remember the intent and push to verification.
            $this->rememberIntent($context);
            $this->security->login($user);
            $this->flash('warning', 'Nejprve si ověř svou e-mailovou adresu. Zkontroluj schránku.');

            return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
        }

        $this->security->login($user);

        return $this->joinGroupAsUser($context, $user);
    }

    private function processRegister(Request $request, InvitationContext $context): Response
    {
        $email = $this->resolveEmailForContext(
            $context,
            (string) $request->request->get('email', ''),
        );

        if ('' === $email) {
            return $this->renderEmailStep($context, '');
        }

        $form = $this->createRegisterForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderRegisterStep($context, $email, $form);
        }

        $data = $form->getData();

        try {
            $this->commandBus->dispatch(new RegisterUserCommand(
                email: $email,
                nickname: $data->nickname,
                plainPassword: $data->password,
                autoVerify: InvitationKind::Email === $context->kind,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof UserAlreadyExists) {
                $this->flash('info', 'Na tento e-mail už existuje účet. Přihlaš se prosím.');

                return $this->renderLoginStep($context, $email);
            }

            if ($previous instanceof NicknameAlreadyTaken) {
                $form->get('nickname')->addError(new FormError('Tato přezdívka je již obsazena.'));

                return $this->renderRegisterStep($context, $email, $form);
            }

            throw $e;
        }

        $user = $this->userRepository->findByEmail($email);

        if (null === $user) {
            throw new \LogicException('Just-registered user must exist.');
        }

        // Email-invite kind auto-verifies (handler already did that); join immediately.
        // Shareable-link kind leaves the user unverified — we stash the intent and bounce
        // them through email verification, after which LoginSubscriber completes the join.
        if (InvitationKind::Email === $context->kind) {
            $this->security->login($user);

            return $this->joinGroupAsUser($context, $user);
        }

        $this->rememberIntent($context);
        $this->security->login($user);
        $this->flash('success', 'Registrace proběhla úspěšně. Potvrď e-mail, ať tě přidáme do skupiny.');

        return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
    }

    private function processCompleteRegistration(Request $request, InvitationContext $context): Response
    {
        if (InvitationKind::Email !== $context->kind) {
            return $this->renderEmailStep($context, '');
        }

        $email = $this->resolveEmailForContext(
            $context,
            (string) $request->request->get('email', ''),
        );

        $form = $this->createCompleteRegistrationForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderCompleteRegistrationStep($context, $email, $form);
        }

        try {
            $this->commandBus->dispatch(new CompleteInvitationRegistrationCommand(
                token: $context->token,
                plainPassword: $form->getData()->password,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof GroupInvitationExpired
                || $previous instanceof GroupInvitationAlreadyAccepted
                || $previous instanceof GroupInvitationAlreadyRevoked
            ) {
                return $this->renderStatus($this->refreshContext($context));
            }

            throw $e;
        }

        $user = $this->userRepository->findByEmail($email);

        if (null === $user) {
            throw new \LogicException('User with invitation email must exist after completing registration.');
        }

        $this->security->login($user);
        $this->flash('success', 'Registrace dokončena, vítej ve skupině!');

        return new RedirectResponse($this->urlGenerator->generate(
            'portal_group_detail',
            ['id' => $context->groupId->toRfc4122()],
        ));
    }

    private function joinGroupAsUser(InvitationContext $context, User $user): Response
    {
        try {
            $command = InvitationKind::Email === $context->kind
                ? new AcceptGroupInvitationCommand(userId: $user->id, token: $context->token)
                : new JoinGroupByLinkCommand(userId: $user->id, token: $context->token);

            $this->commandBus->dispatch($command);

            $this->flash('success', 'Byl(a) jsi přidán(a) do skupiny.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof AlreadyMember) {
                $this->flash('info', 'Ve skupině již jsi.');
            } elseif ($inner instanceof CannotJoinFinishedTournament) {
                $this->flash('warning', 'Turnaj této skupiny je již ukončen.');

                return new RedirectResponse($this->urlGenerator->generate('portal_dashboard'));
            } elseif ($inner instanceof GroupInvitationExpired
                || $inner instanceof GroupInvitationAlreadyAccepted
                || $inner instanceof GroupInvitationAlreadyRevoked) {
                return $this->renderStatus($this->refreshContext($context));
            } else {
                throw $handlerFailed;
            }
        }

        return new RedirectResponse($this->urlGenerator->generate(
            'portal_group_detail',
            ['id' => $context->groupId->toRfc4122()],
        ));
    }

    private function rememberIntent(InvitationContext $context): void
    {
        match ($context->kind) {
            InvitationKind::Email => $this->invitationIntent->store($context->token),
            InvitationKind::ShareableLink => $this->joinIntent->store($context->token),
        };
    }

    private function resolveEmailForContext(InvitationContext $context, string $submittedEmail): string
    {
        if (InvitationKind::Email === $context->kind && null !== $context->presetEmail) {
            return $context->presetEmail;
        }

        return strtolower(trim($submittedEmail));
    }

    /**
     * @return \Symfony\Component\Form\FormInterface<InvitationEmailFormData>
     */
    private function createEmailForm(InvitationContext $context, string $email = ''): \Symfony\Component\Form\FormInterface
    {
        $data = new InvitationEmailFormData();
        $data->email = $email;

        return $this->formFactory->create(InvitationEmailFormType::class, $data, [
            'lock_email' => InvitationKind::Email === $context->kind && null !== $context->presetEmail,
        ]);
    }

    /**
     * @return \Symfony\Component\Form\FormInterface<InvitationLoginFormData>
     */
    private function createLoginForm(): \Symfony\Component\Form\FormInterface
    {
        return $this->formFactory->create(InvitationLoginFormType::class, new InvitationLoginFormData());
    }

    /**
     * @return \Symfony\Component\Form\FormInterface<InvitationRegisterFormData>
     */
    private function createRegisterForm(): \Symfony\Component\Form\FormInterface
    {
        return $this->formFactory->create(InvitationRegisterFormType::class, new InvitationRegisterFormData());
    }

    /**
     * @return \Symfony\Component\Form\FormInterface<CompleteInvitationRegistrationFormData>
     */
    private function createCompleteRegistrationForm(): \Symfony\Component\Form\FormInterface
    {
        return $this->formFactory->create(
            CompleteInvitationRegistrationFormType::class,
            new CompleteInvitationRegistrationFormData(),
        );
    }

    private function renderEmailStep(InvitationContext $context, string $email): Response
    {
        $form = $this->createEmailForm($context, $email);

        return $this->renderTemplate([
            'step' => 'email',
            'context' => $context,
            'email_form' => $form->createView(),
        ]);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<InvitationLoginFormData>|null $form
     */
    private function renderLoginStep(InvitationContext $context, string $email, ?\Symfony\Component\Form\FormInterface $form = null): Response
    {
        $form ??= $this->createLoginForm();

        return $this->renderTemplate([
            'step' => 'login',
            'context' => $context,
            'email' => $email,
            'login_form' => $form->createView(),
        ]);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<InvitationRegisterFormData>|null $form
     */
    private function renderRegisterStep(InvitationContext $context, string $email, ?\Symfony\Component\Form\FormInterface $form = null): Response
    {
        $form ??= $this->createRegisterForm();

        return $this->renderTemplate([
            'step' => 'register',
            'context' => $context,
            'email' => $email,
            'register_form' => $form->createView(),
        ]);
    }

    /**
     * @param \Symfony\Component\Form\FormInterface<CompleteInvitationRegistrationFormData>|null $form
     */
    private function renderCompleteRegistrationStep(InvitationContext $context, string $email, ?\Symfony\Component\Form\FormInterface $form = null): Response
    {
        $form ??= $this->createCompleteRegistrationForm();

        return $this->renderTemplate([
            'step' => 'complete_registration',
            'context' => $context,
            'email' => $email,
            'complete_registration_form' => $form->createView(),
        ]);
    }

    private function renderStatus(InvitationContext $context): Response
    {
        return $this->renderTemplate([
            'step' => $context->status->value,
            'context' => $context,
        ]);
    }

    private function renderInvalid(): Response
    {
        return new Response(
            $this->twig->render('invitation/landing.html.twig', [
                'step' => 'invalid',
                'context' => null,
            ]),
            Response::HTTP_NOT_FOUND,
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderTemplate(array $variables): Response
    {
        return new Response($this->twig->render('invitation/landing.html.twig', $variables));
    }

    private function refreshContext(InvitationContext $context): InvitationContext
    {
        return $this->contextResolver->resolve(
            $context->kind,
            $context->token,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }

    private function flash(string $type, string $message): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return;
        }

        $request->getSession()->getFlashBag()->add($type, $message);
    }
}
