<?php

declare(strict_types=1);

namespace App\Service\SignUp;

/**
 * Spots a mistyped mailbox domain (seznam.cy, gmial.com) and offers the intended one.
 *
 * A typo in the domain is a valid address, so validation lets it through — and the
 * verification mail then never arrives. A non-blocking hint at the field catches it
 * while the visitor is still on the page.
 */
final class EmailTypoSuggester
{
    private const array KNOWN_DOMAINS = [
        'seznam.cz', 'email.cz', 'post.cz', 'centrum.cz', 'atlas.cz', 'volny.cz', 'tiscali.cz', 'quick.cz',
        'gmail.com', 'icloud.com', 'me.com', 'outlook.com', 'outlook.cz', 'hotmail.com', 'live.com',
        'yahoo.com', 'email.com', 'azet.sk', 'centrum.sk', 'zoznam.sk',
    ];

    private const int MAX_DISTANCE = 2;

    public function suggest(string $email): ?string
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');

        if (false === $at) {
            return null;
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);

        if ('' === $local || \strlen($domain) < 4 || \in_array($domain, self::KNOWN_DOMAINS, true)) {
            return null;
        }

        // The mailbox name is right, only the ending is wrong (gmail.cz): fix the ending.
        // Edit distance alone would pick email.cz here — one letter away.
        $name = strstr($domain, '.', true);

        foreach (self::KNOWN_DOMAINS as $known) {
            if (false !== $name && $name === strstr($known, '.', true)) {
                return $local.'@'.$known;
            }
        }

        $best = null;
        $bestDistance = self::MAX_DISTANCE + 1;

        foreach (self::KNOWN_DOMAINS as $known) {
            $distance = levenshtein($domain, $known);

            if ($distance < $bestDistance) {
                $best = $known;
                $bestDistance = $distance;
            }
        }

        return null === $best ? null : $local.'@'.$best;
    }
}
