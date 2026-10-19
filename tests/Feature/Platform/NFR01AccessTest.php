<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Domain\NFR01\Nfr01AccessContext;
use App\Modules\Platform\Domain\NFR01\Nfr01Resource;
use App\Modules\Platform\Infrastructure\NFR01\Nfr01AccessDiagnosticAdapter;
use Tests\TestCase;

final class NFR01AccessTest extends TestCase
{
    public function test_secure_authorized_access_masks_sensitive_fields(): void
    {
        $context = new Nfr01AccessContext('actor-1', 'entity-1', ['record.read'], true, true, false, 2);
        $resource = new Nfr01Resource('record-1', 'entity-1', 'actor-1', ['name' => 'A', 'secret' => 'hidden'], ['secret']);
        $result = (new Nfr01AccessDiagnosticAdapter)->inspect($context, $resource, ['name', 'secret']);
        self::assertTrue($result['allowed']);
        self::assertNull($result['fields']['secret']);
    }

    public function test_revoked_and_insecure_access_fail_closed(): void
    {
        $resource = new Nfr01Resource('record-1', 'entity-1', 'actor-1', ['name' => 'A'], []);
        $adapter = new Nfr01AccessDiagnosticAdapter;
        $revoked = new Nfr01AccessContext('actor-1', 'entity-1', ['record.read'], true, true, true, 2);
        $insecure = new Nfr01AccessContext('actor-1', 'entity-1', ['record.read'], false, true, false, 2);
        self::assertFalse($adapter->inspect($revoked, $resource, ['name'])['allowed']);
        self::assertFalse($adapter->inspect($insecure, $resource, ['name'])['allowed']);
    }
}
