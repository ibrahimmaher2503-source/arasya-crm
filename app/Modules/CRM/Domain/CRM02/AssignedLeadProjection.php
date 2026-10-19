<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM02;

use App\Modules\CRM\Domain\CRM01\LeadRecord;
use InvalidArgumentException;

final readonly class AssignedLeadProjection
{
    /** @param list<LeadRecord> $leads */
    public function __construct(public string $entityId, public string $actorId, public array $leads)
    {
        if (trim($entityId) === '' || trim($actorId) === '') {
            throw new InvalidArgumentException('Lead projection scope is malformed.');
        }
        foreach ($leads as $lead) {
            if ($lead->entityId !== $entityId) {
                throw new InvalidArgumentException('Lead is outside entity scope.');
            }
        }
    }

    /** @return list<array{id:string,name:string,organization:string}> */
    public function toArray(): array
    {
        return array_map(fn (LeadRecord $lead): array => ['id' => $lead->id, 'name' => $lead->name, 'organization' => $lead->organization], $this->leads);
    }
}
