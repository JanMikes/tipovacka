<?php

declare(strict_types=1);

namespace App\Twig\Components\Auth;

use App\Command\ResetUserPassword\ResetUserPasswordCommand;
use App\Entity\User;
use App\Form\ResetPasswordFormData;
use App\Form\ResetPasswordFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[AsLiveComponent(name: 'Auth:ResetPasswordForm')]
final class ResetPasswordForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @return FormInterface<ResetPasswordFormData>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(ResetPasswordFormType::class, new ResetPasswordFormData());
    }

    #[LiveAction]
    public function submit(): Response
    {
        $token = $this->getTokenFromSession();

        if (null === $token) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->cleanSessionAfterReset();

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $this->submitForm();

        /** @var ResetPasswordFormData $data */
        $data = $this->getForm()->getData();
        \assert(null !== $data->newPassword, 'Validated by NotBlank.');

        $this->resetPasswordHelper->removeResetRequest($token);

        $this->commandBus->dispatch(new ResetUserPasswordCommand(
            userId: $user->id,
            plainPassword: $data->newPassword,
        ));

        $this->cleanSessionAfterReset();
        $this->addFlash('success', 'Heslo bylo úspěšně obnoveno. Nyní se můžete přihlásit.');

        return $this->redirectToRoute('app_login');
    }
}
