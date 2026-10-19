<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Domain\NFR01\Nfr01AccessContext;
use App\Modules\Platform\Domain\NFR01\Nfr01Guard;
use App\Modules\Platform\Domain\NFR01\Nfr01Resource;
use PHPUnit\Framework\TestCase;

final class NFR01DomainTest extends TestCase
{
    public function test_authorized_same_entity_request_allows_public_fields_and_masks_sensitive_fields(): void
    {
        $guard = new Nfr01Guard;
        $context = new Nfr01AccessContext('actor-1', 'entity-1', ['record.read'], secureTransport: true, secretStoreConfigured: true, rateLimitRemaining: 2);
        $resource = new Nfr01Resource('record-1', 'entity-1', 'actor-1', ['name' => 'Ada', 'phone' => '+201000000000'], ['phone']);

        $decision = $guard->authorize($context, $resource, ['name', 'phone']);

        self::assertTrue($decision->allowed);
        self::assertSame(['name' => 'Ada', 'phone' => null], $decision->visibleFields);
        self::assertSame('sensitive_field_masked', $decision->reason);
    }

    public function test_revoked_session_cross_entity_missing_transport_and_exhausted_limit_fail_closed(): void
    {
        $guard = new Nfr01Guard;
        $resource = new Nfr01Resource('record-1', 'entity-2', 'actor-2', ['name' => 'Ada']);
        $contexts = [
            new Nfr01AccessContext('actor-1', 'entity-2', ['record.read'], revoked: true),
            new Nfr01AccessContext('actor-1', 'entity-2', ['record.read'], secureTransport: true, secretStoreConfigured: true),
            new Nfr01AccessContext('actor-1', 'entity-2', [], secureTransport: true, secretStoreConfigured: true),
            new Nfr01AccessContext('actor-1', 'entity-2', ['record.read'], secureTransport: false, secretStoreConfigured: true),
            new Nfr01AccessContext('actor-1', 'entity-2', ['record.read'], secureTransport: true, secretStoreConfigured: false),
            new Nfr01AccessContext('actor-1', 'entity-2', ['record.read'], secureTransport: true, secretStoreConfigured: true, rateLimitRemaining: 0),
        ];

        foreach ($contexts as $context) {
            $decision = $guard->authorize($context, $resource, ['name']);
            self::assertFalse($decision->allowed);
            self::assertSame([], $decision->visibleFields);
        }
    }

    public function test_sensitive_fields_require_explicit_permission_and_object_scope(): void
    {
        $guard = new Nfr01Guard;
        $resource = new Nfr01Resource('record-1', 'entity-1', 'actor-2', ['name' => 'Ada', 'phone' => '+201000000000'], ['phone']);
        $context = new Nfr01AccessContext('actor-1', 'entity-1', ['record.read'], secureTransport: true, secretStoreConfigured: true);

        $decision = $guard->authorize($context, $resource, ['phone']);

        self::assertFalse($decision->allowed);
        self::assertSame([], $decision->visibleFields);
        self::assertSame('object_scope_denied', $decision->reason);
    }

    public function test_sensitive_field_permission_does_not_bypass_entity_scope(): void
    {
        $guard = new Nfr01Guard;
        $resource = new Nfr01Resource('record-1', 'entity-2', 'actor-1', ['phone' => '+201000000000'], ['phone']);
        $context = new Nfr01AccessContext('actor-1', 'entity-1', ['record.read', 'record.read.sensitive'], secureTransport: true, secretStoreConfigured: true);

        self::assertSame('entity_scope_denied', $guard->authorize($context, $resource, ['phone'])->reason);
    }
}
