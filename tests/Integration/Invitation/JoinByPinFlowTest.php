<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * `/pripojit` for somebody who already has a verified account — the PIN still joins in
 * one step. (The anonymous half of the page lives in InviteFunnelJourneyTest.).
 */
final class JoinByPinFlowTest extends WebTestCase
{
    public function testJoiningWithValidPin(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::v7(),
            email: 'joiner-flow@tipovacka.test',
            password: null,
            nickname: 'joiner_flow_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        $client->request('GET', '/pripojit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Pokračovat', [
            'join_by_pin_form[pin]' => AppFixtures::VERIFIED_COMPETITION_PIN,
        ]);

        self::assertResponseRedirects('/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'?pripojeno=1');

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->setParameter('userId', $user->id)
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
    }

    public function testInvalidPinKeepsFormWithError(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/pripojit');

        $client->submitForm('Pokračovat', [
            'join_by_pin_form[pin]' => '99999999',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field', 'Soutěž s tímto PINem jsme nenašli.');
        self::assertSelectorExists('input[name="join_by_pin_form[pin]"][aria-invalid="true"]');
    }

    /**
     * A PIN is dictated and pasted from chats as „1234 5678" or „1234-5678" — the same PIN.
     */
    public function testPinWithSeparatorsStillJoins(): void
    {
        $client = static::createClient();
        $client->loginUser($this->admin($client));

        $client->request('GET', '/pripojit');
        $client->submitForm('Pokračovat', [
            'join_by_pin_form[pin]' => '1234 5678',
        ]);

        self::assertResponseRedirects('/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'?pripojeno=1');
    }

    public function testMalformedPinSaysWhatAPinLooksLike(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pripojit');

        self::assertSelectorTextContains('body', 'Mezery a pomlčky nevadí.');

        $client->submitForm('Pokračovat', [
            'join_by_pin_form[pin]' => '1234567',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field', 'PIN tvoří přesně 8 číslic');
    }

    /**
     * The 8-box bar sits in the middle of a busy page — a flash at the top of that page
     * read as „nothing happened". A rejected PIN is answered on „Znám PIN" instead: the
     * reason under the field and the PIN still typed in.
     */
    public function testQuickBarWithUnknownPinExplainsAtTheField(): void
    {
        $client = static::createClient();
        $client->request('GET', '/souteze');

        $crawler = $client->submitForm('Připojit se', ['pin' => '99999999']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field', 'Soutěž s tímto PINem jsme nenašli.');
        $input = $crawler->filter('input[name="join_by_pin_form[pin]"]');
        self::assertSame('99999999', $input->attr('value'));
        self::assertSame('true', $input->attr('aria-invalid'));
        // The re-rendered form posts to the real page, not back to the bar's endpoint.
        self::assertCount(1, $crawler->filter('form[action="/pripojit"]'));
    }

    private function admin(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($user);

        return $user;
    }
}
