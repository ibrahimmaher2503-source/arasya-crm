<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Modules\CRM\Domain\CRM06\ConversionIdentity;
use App\Modules\CRM\Infrastructure\CRM06\ConversionIdentityAccessDiagnosticAdapter;
use Tests\TestCase;

final class CRM06AccessTest extends TestCase
{
    public function test_conversion_identity_is_scoped_and_reuses_customer(): void
    {
        $identity = new ConversionIdentity('c1', 'o1', 's1', 'q1', 'ord1');
        $result = (new ConversionIdentityAccessDiagnosticAdapter)->inspect('u1', 'e1', 'e1', $identity);
        self::assertSame('allow', $result['decision']);
        self::assertSame('conversion_ready', $result['code']);
        self::assertSame('c1', $result['identity']->customerId);
    }

    public function test_other_entity_and_revoked_access_are_denied(): void
    {
        $identity = new ConversionIdentity('c1', 'o1', 's1', 'q1', 'ord1');
        $adapter = new ConversionIdentityAccessDiagnosticAdapter;
        self::assertSame('deny', $adapter->inspect('u1', 'other', 'e1', $identity)['decision']);
        self::assertSame('deny', $adapter->inspect('u1', 'e1', 'e1', $identity, true)['decision']);
    }
}
