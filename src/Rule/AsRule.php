<?php

declare(strict_types=1);

namespace App\Rule;

/**
 * Declarative marker for scoring rules.
 *
 * Note: the `app.rule` service tag is applied by the `_instanceof` block in
 * config/services.php, which matches on `RuleInterface` (not on this attribute).
 * This attribute is purely documentation — implementing `RuleInterface` is what
 * registers a class with the `RuleRegistry`. Apply both for clarity.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsRule
{
}
