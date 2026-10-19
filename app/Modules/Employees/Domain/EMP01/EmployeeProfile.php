<?php

declare(strict_types=1);

namespace App\Modules\Employees\Domain\EMP01;

use App\Shared\Outbox\OutboxEvent;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

final readonly class EmployeeProfile implements JsonSerializable
{
    public DateTimeImmutable $hireDateUtc;

    /**
     * @param  list<string>  $documents
     * @param  list<string>  $evaluations
     */
    public function __construct(
        public string $entityId,
        public string $employeeId,
        public string $name,
        public string $role,
        public string $departmentId,
        public ?string $managerId,
        DateTimeImmutable $hireDate,
        public EmployeeStatus $status,
        public array $documents = [],
        public array $evaluations = [],
        public ?string $privateNotes = null,
    ) {
        foreach ([$entityId, $employeeId, $name, $role, $departmentId] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Employee identity fields must be non-empty.');
            }
        }
        if ($managerId !== null && trim($managerId) === '') {
            throw new InvalidArgumentException('Manager ID must be non-empty when supplied.');
        }
        foreach ([...$documents, ...$evaluations] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Employee profile entries must be non-empty strings.');
            }
        }
        $this->hireDateUtc = $hireDate->setTimezone(new DateTimeZone('UTC'));
    }

    public function transitionStatus(
        EmployeeStatus $to,
        string $actorId,
        string $requestId,
        DateTimeImmutable $occurredAt,
    ): EmployeeTransitionResult {
        if (trim($actorId) === '' || trim($requestId) === '' || ! $this->canTransitionTo($to)) {
            return new EmployeeTransitionResult(false, $this, null);
        }

        $next = new self(
            $this->entityId,
            $this->employeeId,
            $this->name,
            $this->role,
            $this->departmentId,
            $this->managerId,
            $this->hireDateUtc,
            $to,
            $this->documents,
            $this->evaluations,
            $this->privateNotes,
        );
        $intent = new OutboxEvent(
            'employee-status-'.$requestId,
            'employee.status_changed',
            1,
            'EmployeeProfile',
            $this->employeeId,
            1,
            [
                'employee_id' => $this->employeeId,
                'from' => $this->status->value,
                'to' => $to->value,
                'actor_id' => $actorId,
                'occurred_at_utc' => $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('c'),
            ],
        );

        return new EmployeeTransitionResult(true, $next, $intent);
    }

    /** @return array<string, mixed> */
    public function toArray(bool $includePrivateNotes = false): array
    {
        $result = [
            'entity_id' => $this->entityId,
            'employee_id' => $this->employeeId,
            'name' => $this->name,
            'role' => $this->role,
            'department_id' => $this->departmentId,
            'manager_id' => $this->managerId,
            'hire_date_utc' => $this->hireDateUtc->format('c'),
            'status' => $this->status->value,
            'documents' => $this->documents,
            'evaluations' => $this->evaluations,
        ];
        if ($includePrivateNotes) {
            $result['private_notes'] = $this->privateNotes;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function canTransitionTo(EmployeeStatus $to): bool
    {
        return match ($this->status) {
            EmployeeStatus::Active => in_array($to, [EmployeeStatus::OnLeave, EmployeeStatus::Suspended, EmployeeStatus::Terminated], true),
            EmployeeStatus::OnLeave => in_array($to, [EmployeeStatus::Active, EmployeeStatus::Suspended], true),
            EmployeeStatus::Suspended => in_array($to, [EmployeeStatus::Active, EmployeeStatus::Terminated], true),
            EmployeeStatus::Terminated => false,
        };
    }
}
