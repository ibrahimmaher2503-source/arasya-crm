<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Modules\Sales\Application\QuotationDrafts;
use Tests\TestCase;

final class QuotationBoundaryTest extends TestCase
{
    public function test_draft_money_is_calculated_without_float_rounding(): void
    {
        $result = app(QuotationDrafts::class)->calculate([['description' => 'Synthetic item', 'quantity' => '3', 'unit_price' => '0.1', 'discount' => '0', 'tax_rate' => '0']]);
        self::assertSame('0.3', $result['total']);
    }
}
