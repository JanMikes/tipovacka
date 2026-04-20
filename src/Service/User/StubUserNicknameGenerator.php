<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Repository\UserRepository;

final readonly class StubUserNicknameGenerator
{
    private const int MAX_LENGTH = 30;

    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function forEmail(string $email): string
    {
        $base = $this->deriveBase($email);

        if (null === $this->userRepository->findByNickname($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix < 10_000; ++$suffix) {
            $candidate = $this->withSuffix($base, (string) $suffix);

            if (null === $this->userRepository->findByNickname($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(sprintf('Could not generate a unique nickname for "%s".', $email));
    }

    private function deriveBase(string $email): string
    {
        $localPart = strtolower(strstr($email, '@', true) ?: $email);
        $sanitized = preg_replace('/[^a-z0-9._-]/', '', $localPart) ?? '';
        $sanitized = trim($sanitized, '._-');

        if ('' === $sanitized) {
            $sanitized = 'clen';
        }

        return substr($sanitized, 0, self::MAX_LENGTH);
    }

    private function withSuffix(string $base, string $suffix): string
    {
        $separator = '-';
        $maxBaseLength = self::MAX_LENGTH - strlen($suffix) - strlen($separator);

        return substr($base, 0, $maxBaseLength).$separator.$suffix;
    }
}
