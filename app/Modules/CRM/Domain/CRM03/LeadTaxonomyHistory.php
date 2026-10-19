<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM03;

use InvalidArgumentException;

final readonly class LeadTaxonomyHistory
{
    /** @param list<array{stage:string,outcome:string,version:int}> $entries */
    public function __construct(public string $leadId, public array $entries)
    {
        if (trim($leadId) === '') {
            throw new InvalidArgumentException('Lead ID is required.');
        }$last = 0;
        foreach ($entries as $entry) {
            if (trim($entry['stage']) === '' || trim($entry['outcome']) === '' || $entry['version'] < 1 || $entry['version'] <= $last) {
                throw new InvalidArgumentException('Taxonomy history must be ordered and complete.');
            }$last = $entry['version'];
        }
    }

    public function append(string $stage, string $outcome): self
    {
        $version = count($this->entries) + 1;

        return new self($this->leadId, [...$this->entries, ['stage' => $stage, 'outcome' => $outcome, 'version' => $version]]);
    }
}
