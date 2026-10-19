<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Modules\CRM\Application\CRM06\LinkConversionIdentity;
use App\Modules\CRM\Domain\CRM06\ConversionIdentity;
use PHPUnit\Framework\TestCase;

final class CRM06DomainTest extends TestCase
{
    public function test_existing_customer_is_reused_across_chain(): void
    {
        $r = (new LinkConversionIdentity)->execute(new ConversionIdentity('c1', 'o1', 's1', 'q1', 'ord1'));
        self::assertSame('c1', $r->customerId);
        self::assertSame(['o1', 's1', 'q1', 'ord1'], $r->chain());
    }

    public function test_missing_chain_identity_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConversionIdentity('c1', 'o1', '', 'q1', 'ord1');
    }
}
