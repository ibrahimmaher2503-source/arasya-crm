<?php

declare(strict_types=1);

namespace App\Modules\Communications\Application\COM02;

use App\Modules\Communications\Domain\COM02\ConversationMembership;

final class ManageConversationMembership
{
    /** @param list<string> $memberIds */
    public function create(string $entityId, string $conversationId, array $memberIds): ConversationMembership
    {
        return new ConversationMembership($entityId, $conversationId, $memberIds);
    }
}
