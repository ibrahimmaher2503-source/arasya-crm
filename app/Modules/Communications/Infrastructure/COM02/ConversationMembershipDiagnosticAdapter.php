<?php

declare(strict_types=1);

namespace App\Modules\Communications\Infrastructure\COM02;

use App\Modules\Communications\Application\COM02\ManageConversationMembership;

final class ConversationMembershipDiagnosticAdapter
{
    /**
     * @param  list<string>  $memberIds
     * @return array{decision:string,code:string,entity_id?:string,conversation_id?:string,members?:list<string>}
     */
    public function create(string $entityId, string $conversationId, array $memberIds, string $actorId, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || ! in_array($actorId, $memberIds, true)) {
            return ['decision' => 'deny', 'code' => 'membership_denied'];
        }
        $membership = (new ManageConversationMembership)->create($entityId, $conversationId, $memberIds);

        return ['decision' => 'allow', 'code' => 'membership_created', 'entity_id' => $membership->entityId, 'conversation_id' => $membership->conversationId, 'members' => $membership->memberIds];
    }
}
