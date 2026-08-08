<?php

declare(strict_types=1);

namespace App\Twig\Components\Profile;

use App\Command\ChangeUserPassword\ChangeUserPasswordCommand;
use App\Entity\User;
use App\Exception\InvalidCurrentPassword;
use App\Form\ChangePasswordFormData;
use App\Form\ChangePasswordFormType;
use App\Voter\ProfileVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Profile:PasswordForm')]
final class PasswordForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @return FormInterface<ChangePasswordFormData>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(ChangePasswordFormType::class, new ChangePasswordFormData());
    }

    #[LiveAction]
    public function save(): ?Response
    {
        $user = $this->getUser();
        \assert($user instanceof User, 'PasswordForm requires an authenticated user.');
        $this->denyAccessUnlessGranted(ProfileVoter::EDIT, $user);

        $this->submitForm();

        /** @var ChangePasswordFormData $data */
        $data = $this->getForm()->getData();
        \assert(null !== $data->currentPassword && null !== $data->newPassword, 'Validated by NotBlank.');

        try {
            $this->commandBus->dispatch(new ChangeUserPasswordCommand(
                userId: $user->id,
                currentPassword: $data->currentPassword,
                newPassword: $data->newPassword,
            ));
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof InvalidCurrentPassword) {
                $this->addInlineFormError('currentPassword', 'Současné heslo není správné.');

                return null;
            }

            throw $e;
        }

        $this->addFlash('success', 'Heslo bylo změněno.');

        // Redirect rather than re-render: it is the only way the typed passwords leave the
        // component's live state instead of being echoed back into the inputs.
        return $this->redirectToRoute('profile_edit');
    }

    /**
     * See RegistrationForm::addInlineFormError() — ComponentWithFormTrait caches the
     * FormView at submit time, so the cached view has to be dropped for the new error
     * to appear in the re-render.
     */
    private function addInlineFormError(string $fieldName, string $message): void
    {
        $this->getForm()->get($fieldName)->addError(new FormError($message));

        $reflection = new \ReflectionProperty(self::class, 'formView');
        $reflection->setValue($this, null);
    }
}
