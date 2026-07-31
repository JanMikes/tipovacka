<?php

declare(strict_types=1);

namespace App\Tests\Unit\Value;

use App\Value\Country;
use PHPUnit\Framework\TestCase;

final class CountryTest extends TestCase
{
    public function testResolvesCodeToCzechNameAndFlagAsset(): void
    {
        $country = Country::tryFrom('CZ');

        self::assertNotNull($country);
        self::assertSame('CZ', $country->alpha2);
        self::assertSame('CZE', $country->alpha3);
        self::assertSame('Česká republika', $country->name);
        self::assertSame('flags/CZE.webp', $country->flagAssetPath);
    }

    public function testAcceptsLowerCaseAndPaddedInput(): void
    {
        $country = Country::tryFrom(' sk ');

        self::assertNotNull($country);
        self::assertSame('SK', $country->alpha2);
        self::assertSame('Slovensko', $country->name);
    }

    public function testReturnsNullForNullBlankOrUnknownCode(): void
    {
        self::assertNull(Country::tryFrom(null));
        self::assertNull(Country::tryFrom(''));
        self::assertNull(Country::tryFrom('XX'));
        self::assertNull(Country::tryFrom('CZE')); // we store alpha-2, not alpha-3
    }

    public function testDirectoryIsOrderedByCzechNameAndHasNoDuplicates(): void
    {
        $countries = Country::all();

        self::assertGreaterThan(240, count($countries));
        self::assertCount(count($countries), array_unique(array_column($countries, 'alpha2')));
        self::assertCount(count($countries), array_unique(array_column($countries, 'alpha3')));

        // Albánie first, Zimbabwe last — diacritics fold, so „Česká republika" sorts under C.
        self::assertSame('Albánie', $countries[0]->name);
        self::assertSame('Zimbabwe', $countries[count($countries) - 1]->name);
    }

    public function testChoicesAndCodesAgreeWithTheDirectory(): void
    {
        $choices = Country::choices();
        $codes = Country::codes();

        self::assertCount(count(Country::all()), $choices);
        self::assertCount(count(Country::all()), $codes);
        self::assertSame('CZ', $choices['Česká republika']);
        self::assertContains('CZ', $codes);
        self::assertNotContains('XX', $codes);
    }

    public function testEveryCountryHasAFlagAssetOnDisk(): void
    {
        $flagsDir = __DIR__.'/../../../assets/flags';

        foreach (Country::all() as $country) {
            self::assertFileExists(
                $flagsDir.'/'.$country->alpha3.'.webp',
                sprintf('Chybí vlajka pro %s (%s).', $country->name, $country->alpha3),
            );
        }
    }
}
