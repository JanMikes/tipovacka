<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Command\RegisterUser\RegisterUserCommand;
use App\Entity\User;
use App\Exception\NicknameAlreadyTaken;
use App\Exception\UserAlreadyExists;
use App\Form\RegistrationFormData;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/registrace', name: 'app_register')]
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly UserRepository $userRepository,
        private readonly Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('portal_dashboard');
        }

        $formData = new RegistrationFormData();
        $form = $this->createForm(RegistrationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                \assert(null !== $formData->password, 'Validated by NotBlank.');
                $this->commandBus->dispatch(new RegisterUserCommand(
                    email: $formData->email,
                    nickname: $formData->nickname,
                    plainPassword: $formData->password,
                ));

                $this->addFlash('success', 'Registrace proběhla úspěšně. Zkontrolujte svoji e-mailovou schránku pro ověření.');

                $user = $this->userRepository->findByEmail($formData->email);
                assert($user instanceof User, 'Just-registered user must exist.');

                $this->security->login($user);

                return $this->redirectToRoute('app_verify_email_pending');
            } catch (HandlerFailedException $e) {
                $previous = $e->getPrevious();

                if ($previous instanceof UserAlreadyExists) {
                    $form->get('email')->addError(new FormError('Tento e-mail je již zaregistrován.'));
                } elseif ($previous instanceof NicknameAlreadyTaken) {
                    $form->get('nickname')->addError(new FormError('Tato přezdívka je již obsazena.'));
                } else {
                    throw $e;
                }
            }
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form,
        ]);
    }
}
