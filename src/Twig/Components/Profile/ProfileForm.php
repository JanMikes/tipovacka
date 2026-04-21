<?php

declare(strict_types=1);

namespace App\Twig\Components\Profile;

use App\Command\UpdateUserProfile\UpdateUserProfileCommand;
use App\Entity\User;
use App\Form\ProfileFormData;
use App\Form\ProfileFormType;
use App\Voter\ProfileVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Profile:ProfileForm')]
final class ProfileForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @return FormInterface<ProfileFormData>
     */
    protected function instantiateForm(): FormInterface
    {
        $user = $this->getUser();
        \assert($user instanceof User, 'ProfileForm requires an authenticated user.');

        return $this->createForm(ProfileFormType::class, ProfileFormData::fromUser($user));
    }

    #[LiveAction]
    public function save(): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $this->denyAccessUnlessGranted(ProfileVoter::EDIT, $user);

        $this->submitForm();

        /** @var ProfileFormData $data */
        $data = $this->getForm()->getData();

        $this->commandBus->dispatch(new UpdateUserProfileCommand(
            userId: $user->id,
            firstName: $data->firstName ?: null,
            lastName: $data->lastName ?: null,
            phone: $data->phone ?: null,
        ));

        $this->addFlash('success', 'Profil byl úspěšně uložen.');

        return $this->redirectToRoute('portal_profile_edit');
    }
}
