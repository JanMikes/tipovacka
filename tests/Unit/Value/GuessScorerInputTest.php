<?php

declare(strict_types=1);

namespace App\Tests\Unit\Value;

use App\Entity\Player;
use App\Enum\MatchSide;
use App\Exception\InvalidScorerName;
use App\Value\GuessScorerInput;
use PHPUnit\Framework\TestCase;

final class GuessScorerInputTest extends TestCase
{
    public function testTrimsAndKeepsValidName(): void
    {
        $input = new GuessScorerInput(MatchSide::Home, '  Jan Novák  ');

        self::assertSame('Jan Novák', $input->playerName);
        self::assertSame(MatchSide::Home, $input->side);
    }

    public function testAcceptsNameAtMaxLength(): void
    {
        $input = new GuessScorerInput(MatchSide::Away, str_repeat('a', Player::NAME_MAX_LENGTH));

        self::assertSame(Player::NAME_MAX_LENGTH, mb_strlen($input->playerName));
    }

    public function testRejectsBlankName(): void
    {
        $this->expectException(InvalidScorerName::class);
        $this->expectExceptionMessage('Zadejte prosím jméno hráče.');

        new GuessScorerInput(MatchSide::Home, '   ');
    }

    public function testRejectsOverlongName(): void
    {
        $this->expectException(InvalidScorerName::class);
        $this->expectExceptionMessage('Jméno hráče nesmí být delší než 120 znaků.');

        new GuessScorerInput(MatchSide::Home, str_repeat('a', Player::NAME_MAX_LENGTH + 1));
    }
}
