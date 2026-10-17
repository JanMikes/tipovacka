<?php

declare(strict_types=1);

namespace App\Service\SignUp;

use App\Repository\UserRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Turns what a visitor typed as a nickname (or their name) into one the rules accept.
 *
 * The nickname allows only ASCII letters, digits, underscore, dot and hyphen — a Czech
 * visitor naturally types „Petr Svoboda" and was told only what is forbidden. Offering
 * a concrete free alternative („petr.svoboda") turns the rejection into a one-tap fix.
 */
final readonly class NicknameSuggester
{
    private const int MIN_LENGTH = 3;
    private const int MAX_LENGTH = 30;

    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function suggest(string $typed, string $firstName = '', string $lastName = ''): ?string
    {
        $candidates = [$typed, trim($firstName.' '.$lastName)];

        foreach ($candidates as $candidate) {
            $base = $this->normalize($candidate);

            if (null === $base) {
                continue;
            }

            $free = $this->firstFree($base);

            if (null !== $free) {
                return $free;
            }
        }

        return null;
    }

    private function normalize(string $value): ?string
    {
        $slug = (new AsciiSlugger('cs'))->slug($value, '.')->lower()->toString();
        $slug = (string) preg_replace('/[^a-z0-9_.\-]+/', '', $slug);
        $slug = trim(substr($slug, 0, self::MAX_LENGTH), '.-_');

        return \strlen($slug) >= self::MIN_LENGTH ? $slug : null;
    }

    private function firstFree(string $base): ?string
    {
        if (null === $this->userRepository->findByNickname($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix <= 99; ++$suffix) {
            $candidate = substr($base, 0, self::MAX_LENGTH - \strlen((string) $suffix)).$suffix;

            if (null === $this->userRepository->findByNickname($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
