<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Repository\UserRepository;

final readonly class UserNicknameGenerator
{
    private const int MAX_LENGTH = 30;

    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function forEmail(string $email): string
    {
        $localPart = strtolower(strstr($email, '@', true) ?: $email);

        return $this->resolveUnique($this->sanitize($localPart));
    }

    public function forName(string $firstName, string $lastName): string
    {
        $combined = strtolower(trim($firstName.'.'.$lastName, '.'));

        return $this->resolveUnique($this->sanitize($combined));
    }

    private function sanitize(string $input): string
    {
        $sanitized = preg_replace('/[^a-z0-9._-]/', '', $input) ?? '';
        $sanitized = trim($sanitized, '._-');

        if ('' === $sanitized) {
            $sanitized = 'clen';
        }

        return substr($sanitized, 0, self::MAX_LENGTH);
    }

    private function resolveUnique(string $base): string
    {
        if (null === $this->userRepository->findByNickname($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix < 10_000; ++$suffix) {
            $candidate = $this->withSuffix($base, (string) $suffix);

            if (null === $this->userRepository->findByNickname($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(sprintf('Could not generate a unique nickname based on "%s".', $base));
    }

    private function withSuffix(string $base, string $suffix): string
    {
        $separator = '-';
        $maxBaseLength = self::MAX_LENGTH - strlen($suffix) - strlen($separator);

        return substr($base, 0, $maxBaseLength).$separator.$suffix;
    }
}
