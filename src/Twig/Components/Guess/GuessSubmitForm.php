<?php

declare(strict_types=1);

namespace App\Twig\Components\Guess;

use App\Command\DeleteGuess\DeleteGuessCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateGuess\UpdateGuessCommand;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Repository\GuessRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent(name: 'Guess:GuessSubmitForm')]
final class GuessSubmitForm
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp]
    public SportMatch $sportMatch;

    #[LiveProp]
    public string $groupId = '';

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $homeScore = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $awayScore = null;

    #[LiveProp]
    public ?string $errorMessage = null;

    #[LiveProp]
    public ?string $successMessage = null;

    public function __construct(
        private readonly Security $security,
        private readonly GuessRepository $guessRepository,
        private readonly ClockInterface $clock,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    #[PostMount]
    public function prefillFromExistingGuess(): void
    {
        $existing = $this->findExistingGuess();

        if (null !== $existing) {
            $this->homeScore = $existing->homeScore;
            $this->awayScore = $existing->awayScore;
        }
    }

    public bool $hasExistingGuess {
        get => null !== $this->findExistingGuess();
    }

    public bool $isLocked {
        get {
            if (!$this->sportMatch->isOpenForGuesses) {
                return true;
            }

            $now = \DateTimeImmutable::createFromInterface($this->clock->now());

            return $now >= $this->sportMatch->kickoffAt;
        }
    }

    public ?Guess $existingGuess {
        get => $this->findExistingGuess();
    }

    private function findExistingGuess(): ?Guess
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $this->guessRepository->findActiveByUserMatchGroup(
            $user->id,
            $this->sportMatch->id,
            Uuid::fromString($this->groupId),
        );
    }

    #[LiveAction]
    public function submit(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            $this->errorMessage = 'Musíš být přihlášen.';

            return;
        }

        $existing = $this->findExistingGuess();
        $homeScore = $this->homeScore;
        $awayScore = $this->awayScore;
        $bothCleared = null === $homeScore && null === $awayScore;

        try {
            if ($bothCleared && null !== $existing) {
                $this->dispatchCommand(new DeleteGuessCommand(
                    userId: $user->id,
                    guessId: $existing->id,
                ));
                $this->successMessage = 'Tip smazán.';

                return;
            }

            if (null === $homeScore || null === $awayScore) {
                $this->errorMessage = 'Vyplň prosím oba tipy.';

                return;
            }

            $this->validate();

            if (null === $existing) {
                $this->dispatchCommand(new SubmitGuessCommand(
                    userId: $user->id,
                    groupId: Uuid::fromString($this->groupId),
                    sportMatchId: $this->sportMatch->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                ));
                $this->successMessage = 'Tip uložen.';
            } else {
                $this->dispatchCommand(new UpdateGuessCommand(
                    userId: $user->id,
                    guessId: $existing->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                ));
                $this->successMessage = 'Tip upraven.';
            }
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            $this->errorMessage = null !== $previous ? $previous->getMessage() : $e->getMessage();

            return;
        } catch (\DomainException|\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }
    }

    private function dispatchCommand(object $message): Envelope
    {
        $envelope = $this->commandBus->dispatch($message);
        $handled = $envelope->last(HandledStamp::class);

        if (null === $handled) {
            throw new \LogicException(sprintf('Command "%s" was not handled.', $message::class));
        }

        return $envelope;
    }
}
