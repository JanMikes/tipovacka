<?php

declare(strict_types=1);

namespace App\Tests\Integration\Scheduler;

use App\Scheduler\MainSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Regression guard for the dead-scheduler bug: without an `App\Scheduler\`
 * resource entry in config/services.php the #[AsSchedule] attribute on
 * {@see MainSchedule} is never applied — the service is never registered, the
 * `scheduler_default` transport never materializes, reconciliation never runs,
 * and the prod worker `messenger:consume async scheduler_default` crash-loops.
 */
final class SchedulerRegistrationTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function schedulerServiceIds(): iterable
    {
        // The schedule provider must be a registered service so autoconfiguration
        // applies its #[AsSchedule] tag…
        yield 'schedule provider' => [MainSchedule::class];
        // …and the transport it materializes is exactly what the prod worker consumes.
        yield 'scheduler_default transport' => ['messenger.transport.scheduler_default'];
    }

    #[DataProvider('schedulerServiceIds')]
    public function testSchedulerServiceIsRegistered(string $serviceId): void
    {
        self::bootKernel();

        self::assertTrue(
            self::getContainer()->has($serviceId),
            sprintf('Service "%s" must be registered — otherwise the scheduler is dead and the worker crash-loops.', $serviceId),
        );
    }
}
