<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Verifies the token carried in the URL (and form action) is load-bearing, so the flow
 * does not rely on session cookies alone. Clearing cookies between steps must not orphan the flow.
 */
final class LandingSessionResilienceTest extends WebTestCase
{
    private const string LINK_URL = '/skupiny/pozvanka/'.AppFixtures::VERIFIED_GROUP_LINK_TOKEN;

    public function testShareableLinkLoginStillWorksAfterSessionWipe(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);

        // Step 1 — email check.
        $client->request('GET', self::LINK_URL);
        $client->submitForm('Pokračovat', [
            '_action' => 'check_email',
            'invitation_email_form[email]' => AppFixtures::ADMIN_EMAIL,
        ]);

        self::assertResponseIsSuccessful();

        // Simulate a session loss between steps — cookies wiped completely.
        $client->getCookieJar()->clear();

        // Step 2 — submit login directly at the landing URL. Token is in URL + hidden email field,
        // no session anchor is involved.
        $client->request('POST', self::LINK_URL, [
            '_action' => 'login',
            'email' => AppFixtures::ADMIN_EMAIL,
            'invitation_login_form' => [
                'password' => AppFixtures::DEFAULT_PASSWORD,
            ],
        ]);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);

        $em->clear();
        $memberships = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')
            ->andWhere('m.group = :g')
            ->setParameter('u', $admin->id)
            ->setParameter('g', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()->getResult();

        self::assertCount(1, $memberships);
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /* @var EntityManagerInterface */
        return $client->getContainer()->get('doctrine.orm.entity_manager');
    }
}
