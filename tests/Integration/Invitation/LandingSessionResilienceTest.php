<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\InvitationKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * The token in the URL (and the live component's `token` LiveProp) is load-bearing,
 * so the flow does not rely on session state. Verifies that the InvitationContext is
 * re-resolved from props on each request, not anchored in a session.
 */
final class LandingSessionResilienceTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testShareableLinkLoginWorksFromAFreshComponentMount(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);

        // No prior request, no session state — submit straight against the freshly-mounted component.
        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::ShareableLink->value,
            'token' => AppFixtures::VERIFIED_GROUP_LINK_TOKEN,
        ], $client);

        $response = $component->submitForm([
            'invitation_form' => [
                'email' => AppFixtures::ADMIN_EMAIL,
                'password' => AppFixtures::DEFAULT_PASSWORD,
            ],
        ], 'submit')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID, $response->headers->get('Location'));

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
