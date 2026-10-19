<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM04;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class LeadActivity
{
    public DateTimeImmutable $occurredAtUtc;

    public function __construct(public string $leadId, public string $authorId, public string $outcome, public string $nextStep, DateTimeImmutable $occurredAt)
    {
        foreach ([$leadId, $authorId, $outcome, $nextStep] as $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException('Activity fields are required.');
            }
        }$this->occurredAtUtc = $occurredAt->setTimezone(new DateTimeZone('UTC'));
    }

    public function reminderKey(): string
    {
        return $this->leadId.':'.$this->occurredAtUtc->format('c').':'.$this->nextStep;
    }
}
