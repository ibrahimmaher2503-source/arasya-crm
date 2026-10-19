<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Infrastructure\NFR02\ConcurrentMutationDiagnosticAdapter;
use Tests\TestCase;

final class NFR02AccessTest extends TestCase
{
    public function test_matching_version_commits(): void
    {
        self::assertSame(['decision' => 'allow', 'code' => 'committed'], (new ConcurrentMutationDiagnosticAdapter)->mutate('v1', 'v1'));
    }

    public function test_stale_version_returns_safe_conflict(): void
    {
        $r = (new ConcurrentMutationDiagnosticAdapter)->mutate('v1', 'v2');
        self::assertSame('concurrent_conflict', $r['code']);
        self::assertStringNotContainsString('token', $r['error']);
    }
}
