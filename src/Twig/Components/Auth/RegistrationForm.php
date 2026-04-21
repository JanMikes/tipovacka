<?php

declare(strict_types=1);

namespace App\Twig\Components\Auth;

use App\Command\RegisterUser\RegisterUserCommand;
use App\Entity\User;
use App\Exception\NicknameAlreadyTaken;
use App\Exception\UserAlreadyExists;
use App\Form\RegistrationFormData;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
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

#[AsLiveComponent(name: 'Auth:RegistrationForm')]
final class RegistrationForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
        private readonly UserRepository $userRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return FormInterface<RegistrationFormData>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(RegistrationFormType::class, new RegistrationFormData());
    }

    #[LiveAction]
    public function register(): ?Response
    {
        $this->submitForm();

        /** @var RegistrationFormData $data */
        $data = $this->getForm()->getData();
        \assert(null !== $data->password, 'Validated by NotBlank.');

        try {
            $this->commandBus->dispatch(new RegisterUserCommand(
                email: $data->email,
                nickname: $data->nickname,
                plainPassword: $data->password,
                firstName: $data->firstName,
                lastName: $data->lastName,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof UserAlreadyExists) {
                $this->addInlineFormError('email', 'Tento e-mail je již zaregistrován.');

                return null;
            }

            if ($previous instanceof NicknameAlreadyTaken) {
                $this->addInlineFormError('nickname', 'Tato přezdívka je již obsazena.');

                return null;
            }

            throw $e;
        }

        $this->addFlash('success', 'Registrace proběhla úspěšně. Zkontrolujte svoji e-mailovou schránku pro ověření.');

        $user = $this->userRepository->findByEmail($data->email);
        \assert($user instanceof User, 'Just-registered user must exist.');

        $this->security->login($user);

        return $this->redirectToRoute('app_verify_email_pending');
    }

    /**
     * Adds a per-field FormError after the form has already been submitted.
     *
     * ComponentWithFormTrait caches the FormView at submit time, so simply calling
     * addError() on the form would not surface the error in the re-render. We invalidate
     * the cached view via the trait's private $formView so it is rebuilt with the new error.
     */
    private function addInlineFormError(string $fieldName, string $message): void
    {
        $this->getForm()->get($fieldName)->addError(new FormError($message));

        $reflection = new \ReflectionProperty(self::class, 'formView');
        $reflection->setValue($this, null);
    }
}
