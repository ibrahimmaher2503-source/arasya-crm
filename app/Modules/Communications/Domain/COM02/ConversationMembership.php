<?php

declare(strict_types=1);

namespace App\Modules\Communications\Domain\COM02;

use InvalidArgumentException;

final readonly class ConversationMembership
{
    /** @param list<string> $memberIds */
    public function __construct(public string $entityId, public string $conversationId, public array $memberIds)
    {
        if (trim($entityId) === '' || trim($conversationId) === '') {
            throw new InvalidArgumentException('Conversation scope is required.');
        }if (count($memberIds) !== count(array_unique($memberIds))) {
            throw new InvalidArgumentException('Conversation members must be unique.');
        }foreach ($memberIds as $id) {
            if (trim($id) === '') {
                throw new InvalidArgumentException('Conversation member is required.');
            }
        }
    }

    public function contains(string $employeeId): bool
    {
        return in_array($employeeId, $this->memberIds, true);
    }
}
