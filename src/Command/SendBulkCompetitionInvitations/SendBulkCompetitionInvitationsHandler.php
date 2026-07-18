<?php

declare(strict_types=1);

namespace App\Command\SendBulkCompetitionInvitations;

use App\Entity\CompetitionInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\CompetitionInvitationRepository;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\InvitationTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use App\Service\User\UserNicknameGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
final readonly class SendBulkCompetitionInvitationsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionInvitationRepository $invitationRepository,
        private MembershipRepository $membershipRepository,
        private InvitationTokenGenerator $tokenGenerator,
        private UserNicknameGenerator $nicknameGenerator,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(SendBulkCompetitionInvitationsCommand $command): BulkInvitationResult
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $inviter = $this->userRepository->get($command->inviterId);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $expiresAt = $now->modify('+7 days');

        $pendingInvitations = $this->invitationRepository->findPendingByCompetition($competition->id, $now);
        $pendingEmails = array_map(
            static fn (CompetitionInvitation $invitation): string => strtolower($invitation->email),
            $pendingInvitations,
        );
        $pendingSet = array_fill_keys($pendingEmails, true);

        $invited = [];
        $alreadyMembers = [];
        $alreadyPending = [];
        $invalid = [];
        $seen = [];

        foreach ($this->parseEmails($command->rawEmails) as $rawEntry) {
            $email = strtolower(trim($rawEntry));

            if ('' === $email) {
                continue;
            }

            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            $violations = $this->validator->validate($email, [
                new Assert\Email(message: 'Neplatná e-mailová adresa.'),
                new Assert\Length(max: 180, maxMessage: 'E-mail je příliš dlouhý.'),
            ]);

            if (count($violations) > 0) {
                $violation = $violations[0];
                $reason = null !== $violation ? (string) $violation->getMessage() : 'Neplatná e-mailová adresa.';
                $invalid[] = ['email' => $email, 'reason' => $reason];

                continue;
            }

            $existingUser = $this->userRepository->findByEmail($email);

            if (null !== $existingUser && $this->membershipRepository->hasActiveMembership($existingUser->id, $competition->id)) {
                $alreadyMembers[] = $email;

                continue;
            }

            if (isset($pendingSet[$email])) {
                $alreadyPending[] = $email;

                continue;
            }

            if (null === $existingUser) {
                $existingUser = $this->createStubUser($email, $now);
            }

            // Active membership up front so competition managers can submit guesses on the
            // invitee's behalf before they accept the invitation.
            if (!$this->membershipRepository->hasActiveMembership($existingUser->id, $competition->id)) {
                $this->membershipRepository->save(new Membership(
                    id: $this->identity->next(),
                    competition: $competition,
                    user: $existingUser,
                    joinedAt: $now,
                ));
            }

            $invitation = new CompetitionInvitation(
                id: $this->identity->next(),
                competition: $competition,
                inviter: $inviter,
                email: $email,
                token: $this->tokenGenerator->generate(),
                createdAt: $now,
                expiresAt: $expiresAt,
            );

            $this->invitationRepository->save($invitation);
            $pendingSet[$email] = true;
            $invited[] = $email;
        }

        return new BulkInvitationResult(
            invited: $invited,
            alreadyMembers: $alreadyMembers,
            alreadyPending: $alreadyPending,
            invalid: $invalid,
        );
    }

    /**
     * @return list<string>
     */
    private function parseEmails(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/u', $raw) ?: [];

        return array_values(array_filter($parts, static fn (string $p): bool => '' !== $p));
    }

    private function createStubUser(string $email, \DateTimeImmutable $now): User
    {
        $user = new User(
            id: $this->identity->next(),
            email: $email,
            password: null,
            nickname: $this->nicknameGenerator->forEmail($email),
            createdAt: $now,
        );

        $this->userRepository->save($user);

        return $user;
    }
}
