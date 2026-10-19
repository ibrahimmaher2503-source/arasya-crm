<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Application\NFR02\RunConcurrentMutation;
use App\Modules\Platform\Domain\NFR02\ConcurrentMutationHarness;
use PHPUnit\Framework\TestCase;

final class NFR02DomainTest extends TestCase
{
    public function test_matching_version_commits_once(): void
    {
        $calls = 0;
        $r = (new RunConcurrentMutation(new ConcurrentMutationHarness))->execute('v1', 'v1', function () use (&$calls): void {
            $calls++;
        });
        self::assertSame('committed', $r);
        self::assertSame(1, $calls);
    }

    public function test_stale_version_fails_closed(): void
    {
        $this->expectException(\RuntimeException::class);
        (new RunConcurrentMutation(new ConcurrentMutationHarness))->execute('v1', 'v2', static function (): void {});
    }
}
