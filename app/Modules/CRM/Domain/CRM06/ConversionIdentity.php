<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM06;

use InvalidArgumentException;

final readonly class ConversionIdentity
{
    public function __construct(public string $customerId, public string $opportunityId, public string $siteId, public string $quoteId, public string $orderId)
    {
        foreach ([$customerId, $opportunityId, $siteId, $quoteId, $orderId] as $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException('Conversion identity is incomplete.');
            }
        }
    }

    /** @return list<string> */
    public function chain(): array
    {
        return [$this->opportunityId, $this->siteId, $this->quoteId, $this->orderId];
    }
}
