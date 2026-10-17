<?php

declare(strict_types=1);

namespace App\Twig\Components\Auth;

use App\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\Form\RequestPasswordResetFormData;
use App\Form\RequestPasswordResetFormType;
use App\Service\SignUp\EmailTypoSuggester;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Auth:RequestPasswordResetForm')]
final class RequestPasswordResetForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    /**
     * The address with its mailbox-domain typo corrected (seznam.cy → seznam.cz), if any —
     * a mistyped domain is a valid address, so the reset mail would silently go nowhere.
     */
    public ?string $emailSuggestion {
        get => $this->emailTypoSuggester->suggest((string) ($this->formValues['email'] ?? ''));
    }

    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
        private readonly EmailTypoSuggester $emailTypoSuggester,
    ) {
    }

    /**
     * @return FormInterface<RequestPasswordResetFormData>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(RequestPasswordResetFormType::class, new RequestPasswordResetFormData());
    }

    #[LiveAction]
    public function submit(): Response
    {
        $this->submitForm();

        /** @var RequestPasswordResetFormData $data */
        $data = $this->getForm()->getData();

        // Always dispatch and redirect — no enumeration of registered emails.
        $this->commandBus->dispatch(new RequestPasswordResetCommand(email: $data->email));

        return $this->redirectToRoute('app_check_email');
    }
}
