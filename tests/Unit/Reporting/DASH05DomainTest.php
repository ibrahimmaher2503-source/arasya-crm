<?php

declare(strict_types=1);

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Application\DASH05\BuildMaskedExport;
use PHPUnit\Framework\TestCase;

final class DASH05DomainTest extends TestCase
{
    public function test_export_applies_scope_mask_and_audit(): void
    {
        $r = (new BuildMaskedExport)->execute('e1', 'u1', ['sales'], [['entityId' => 'e1', 'sales' => '10', 'secret' => 'x']], ['secret']);
        self::assertSame(['sales' => '10'], $r['rows'][0]);
        self::assertSame('u1', $r['audit']['actorId']);
        self::assertSame('e1', $r['sourceEntityId']);
    }

    public function test_other_entity_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BuildMaskedExport)->execute('other', 'u1', ['sales'], [['entityId' => 'e1', 'sales' => '10']], []);
    }
}
