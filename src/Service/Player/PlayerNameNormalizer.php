<?php

declare(strict_types=1);

namespace App\Service\Player;

/**
 * Conservative player-name matching for the roster pool, so a feed's spelling
 * of a scorer resolves to the player tippers already picked instead of
 * silently creating a twin that scores zero on `scorer_hit`.
 *
 * Two rules only, both direction-agnostic:
 *  - normalized equality: case, extra whitespace and diacritics don't matter
 *    („Novak" ≡ „Novák", „jan  novák" ≡ „Jan Novák");
 *  - initial form: „J. Novák" ≡ „Jan Novák" — a single-letter first token
 *    matches a full first name with the same initial and the same surname.
 * Anything fuzzier (typos, transposed names) is deliberately out: a wrong
 * merge credits the wrong player, a missed merge only costs a duplicate row.
 */
final readonly class PlayerNameNormalizer
{
    /** Lowercased, whitespace-collapsed, diacritics stripped, dots removed. */
    public function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace('.', ' ', $name);
        $name = (string) preg_replace('/\s+/u', ' ', $name);

        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $name);

        return trim(is_string($transliterated) ? $transliterated : $name);
    }

    /** Whether two spellings identify the same player under the two rules above. */
    public function matches(string $a, string $b): bool
    {
        $a = $this->normalize($a);
        $b = $this->normalize($b);

        if ('' === $a || '' === $b) {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        return $this->initialFormMatches($a, $b) || $this->initialFormMatches($b, $a);
    }

    /** „j novak" (initial form) vs „jan novak" (full form) — both already normalized. */
    private function initialFormMatches(string $short, string $full): bool
    {
        $shortTokens = explode(' ', $short);
        $fullTokens = explode(' ', $full);

        if (count($shortTokens) < 2 || count($fullTokens) < 2 || count($shortTokens) !== count($fullTokens)) {
            return false;
        }

        // Surname (all tokens after the first) must be identical.
        if (array_slice($shortTokens, 1) !== array_slice($fullTokens, 1)) {
            return false;
        }

        return 1 === mb_strlen($shortTokens[0])
            && str_starts_with($fullTokens[0], $shortTokens[0]);
    }
}
