<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Command\SendBulkCompetitionInvitations\BulkInvitationResult;
use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\InvalidInvitationEmails;
use App\Repository\CompetitionInvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\InvitationTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use App\Service\User\UserNicknameGenerator;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * THE shared bulk-invitation pipeline: parse → validate → skip
 * members/pending → create stub users + active memberships + invitations.
 * Reused by both {@see \App\Command\SendBulkCompetitionInvitations\SendBulkCompetitionInvitationsHandler}
 * (lenient — malformed addresses collected and reported) and the
 * create-competition wizard handler (strict — a malformed address throws
 * {@see InvalidInvitationEmails} and rolls back the whole competition creation).
 *
 * Emails travel to their recipients through the buffered `CompetitionInvitationSent`
 * domain event dispatched AFTER the command transaction commits, so real SMTP
 * failures never roll back — only synchronous validation here does. That is why
 * atomicity for the wizard depends on this strict validation, not on the mailer.
 */
final readonly class CompetitionInviter
{
    public function __construct(
        private UserRepository $userRepository,
        private CompetitionInvitationRepository $invitationRepository,
        private MembershipRepository $membershipRepository,
        private InvitationTokenGenerator $tokenGenerator,
        private UserNicknameGenerator $nicknameGenerator,
        private ProvideIdentity $identity,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param list<string> $rawEntries each entry may itself contain several
     *                                 addresses (whitespace/comma/semicolon-separated)
     */
    public function invite(
        Competition $competition,
        User $inviter,
        array $rawEntries,
        \DateTimeImmutable $now,
        bool $strict,
    ): BulkInvitationResult {
        $expiresAt = $now->modify('+7 days');

        $pendingSet = [];

        foreach ($this->invitationRepository->findPendingByCompetition($competition->id, $now) as $invitation) {
            $pendingSet[strtolower($invitation->email)] = true;
        }

        $invited = [];
        $alreadyMembers = [];
        $alreadyPending = [];
        $invalid = [];
        $seen = [];

        foreach ($this->parseEmails($rawEntries) as $rawEntry) {
            $email = strtolower(trim($rawEntry));

            if ('' === $email || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            // The inviter is (or is about to become) a member — inviting their own
            // address must never create a second Membership. In the wizard the owner's
            // Membership is not flushed yet, so hasActiveMembership() below would miss it
            // and the duplicate would hit the partial unique index at flush.
            if (null !== $inviter->email && $email === strtolower(trim($inviter->email))) {
                $alreadyMembers[] = $email;

                continue;
            }

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

        if ($strict && [] !== $invalid) {
            throw InvalidInvitationEmails::withEmails(array_map(static fn (array $entry): string => $entry['email'], $invalid));
        }

        return new BulkInvitationResult(
            invited: $invited,
            alreadyMembers: $alreadyMembers,
            alreadyPending: $alreadyPending,
            invalid: $invalid,
        );
    }

    /**
     * @param list<string> $rawEntries
     *
     * @return list<string>
     */
    private function parseEmails(array $rawEntries): array
    {
        $emails = [];

        foreach ($rawEntries as $entry) {
            $parts = preg_split('/[\s,;]+/u', $entry) ?: [];

            foreach ($parts as $part) {
                if ('' !== $part) {
                    $emails[] = $part;
                }
            }
        }

        return $emails;
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
