<?php

declare(strict_types=1);

namespace App\Modules\Performance\Domain\KPI02;

use InvalidArgumentException;

final readonly class ApprovedResultProjection
{
    public function __construct(public string $entityId, public string $ownerId, public string $metric, public string $value, public string $period)
    {
        foreach ([$entityId, $ownerId, $metric, $period] as $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException('Approved result dimensions are required.');
            }
        } if (preg_match('/\A\d+\.\d{2}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Approved result value must be decimal.');
        }
    }

    /** @return array{entity_id:string,owner_id:string,metric:string,value:string,period:string} */
    public function toArray(): array
    {
        return ['entity_id' => $this->entityId, 'owner_id' => $this->ownerId, 'metric' => $this->metric, 'value' => $this->value, 'period' => $this->period];
    }
}
