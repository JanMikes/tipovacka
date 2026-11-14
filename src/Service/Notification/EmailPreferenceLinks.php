<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\User;
use App\Value\UnsubscribeScope;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The links in a notification mail that work WITHOUT logging in: „odhlásit"
 * and the mail client's own unsubscribe button (RFC 8058). A login wall in
 * front of an unsubscribe is the fastest way to a spam complaint.
 *
 * The token is the player's id plus an HMAC of it — it proves „this link came
 * out of a mail we sent to that player" and nothing more. It never expires (a
 * mail read a year later must still unsubscribe) and it is NOT a login: the
 * pages behind it can only ever turn e-mail down, see
 * {@see \App\Controller\Notification\EmailPreferencesController}.
 *
 * Signed by hand rather than with Symfony's UriSigner on purpose — that one
 * signs the whole absolute URL, so a proxy that changes the scheme or host
 * would break every link already sitting in an inbox.
 */
final readonly class EmailPreferenceLinks
{
    private const int SIGNATURE_BYTES = 16;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function tokenFor(Uuid $userId): string
    {
        $id = $userId->toBinary();

        return self::encode($id).'.'.self::encode($this->signature($id));
    }

    /** The player a token was issued for, or null when it is malformed or forged. */
    public function userIdFrom(string $token): ?Uuid
    {
        $parts = explode('.', $token);

        if (2 !== count($parts)) {
            return null;
        }

        $id = self::decode($parts[0]);
        $signature = self::decode($parts[1]);

        if (null === $id || null === $signature || 16 !== strlen($id)) {
            return null;
        }

        if (!hash_equals($this->signature($id), $signature)) {
            return null;
        }

        return Uuid::fromBinary($id);
    }

    /**
     * The landing page — absolute, for a mail body. With a scope it opens on
     * „odhlásit právě tohle"; without one it is the general „změnit frekvenci".
     */
    public function manageUrl(User $user, ?UnsubscribeScope $scope = null): string
    {
        $parameters = ['token' => $this->tokenFor($user->id)];

        if (null !== $scope) {
            $parameters['co'] = $scope->toSlug();
        }

        return $this->urlGenerator->generate('email_preferences', $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** The `List-Unsubscribe` target — a POST here unsubscribes with no further step. */
    public function oneClickUrl(User $user, UnsubscribeScope $scope): string
    {
        return $this->urlGenerator->generate('email_one_click_unsubscribe', [
            'token' => $this->tokenFor($user->id),
            'scope' => $scope->toSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function signature(string $binaryUserId): string
    {
        // Keyed off a purpose string so this HMAC can never be replayed against
        // another feature that happens to sign a user id with the app secret.
        $key = hash_hmac('sha256', 'email-preference-links', $this->secret, true);

        return substr(hash_hmac('sha256', $binaryUserId, $key, true), 0, self::SIGNATURE_BYTES);
    }

    private static function encode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private static function decode(string $encoded): ?string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}
