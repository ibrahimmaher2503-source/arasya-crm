<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Application\SEC01\AuthorizeScopedRecord;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedPermissionGuard;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;
use Tests\TestCase;

final class SEC02AccessTest extends TestCase
{
    private const SAFE_OUTPUT = "{\"contact\":null,\"contact_visibility\":\"hidden\"}\n";

    private AuthorizeScopedRecord $authorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorization = new AuthorizeScopedRecord(new ScopedPermissionGuard);
    }

    public function test_cli_hides_contacts_for_explicit_synthetic_actor_and_record_contexts(): void
    {
        $fixtures = [
            'own allowed' => [
                new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']),
                new ScopedRecord('record-own', 'entity-1', 'department-1', ownerId: 'actor-1'),
                true,
                ['phone' => '+1 415 555 0100', 'whatsapp' => '+1 415 555 0101', 'email' => 'own@example.test'],
            ],
            'assigned allowed' => [
                new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']),
                new ScopedRecord('record-assigned', 'entity-1', 'department-1', assignedActorId: 'actor-1'),
                true,
                ['phone' => '+1 415 555 0102', 'whatsapp' => '+1 415 555 0103', 'email' => 'assigned@example.test'],
            ],
            'other actor denied' => [
                new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']),
                new ScopedRecord('record-other', 'entity-1', 'department-1', ownerId: 'actor-2', assignedActorId: 'actor-3'),
                false,
                ['phone' => '+1 415 555 0104', 'whatsapp' => '+1 415 555 0105', 'email' => 'other@example.test'],
            ],
            'unassigned denied' => [
                new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']),
                new ScopedRecord('record-unassigned', 'entity-1', 'department-1'),
                false,
                ['phone' => '+1 415 555 0106', 'whatsapp' => '+1 415 555 0107', 'email' => 'unassigned@example.test'],
            ],
            'cross-entity denied' => [
                new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']),
                new ScopedRecord('record-cross-entity', 'entity-2', 'department-1', assignedActorId: 'actor-1'),
                false,
                ['phone' => '+1 415 555 0108', 'whatsapp' => '+1 415 555 0109', 'email' => 'entity@example.test'],
            ],
            'inherited grant revoked by override' => [
                new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read'], ['crm.lead.read' => false]),
                new ScopedRecord('record-revoked', 'entity-1', 'department-1', assignedActorId: 'actor-1'),
                false,
                ['phone' => '+1 415 555 0110', 'whatsapp' => '+1 415 555 0111', 'email' => 'revoked@example.test'],
            ],
            'privileged identity has no grant bypass' => [
                new ActorAccess('super-administrator', 'entity-1', 'department-1'),
                new ScopedRecord('record-role', 'entity-1', 'department-1', ownerId: 'super-administrator'),
                false,
                ['phone' => '+1 415 555 0112', 'whatsapp' => '+1 415 555 0113', 'email' => 'role@example.test'],
            ],
        ];

        foreach ($fixtures as $scenario => [$actor, $record, $expectedDecision, $contact]) {
            self::assertSame($expectedDecision, $this->authorization->allows($actor, 'crm.lead.read', $record), $scenario);

            [$status, $output, $error] = $this->runCli(json_encode(
                ['channel' => 'ui', 'contact' => $contact],
                JSON_THROW_ON_ERROR,
            ));

            self::assertSame(0, $status, $scenario);
            self::assertSame(self::SAFE_OUTPUT, $output, $scenario);
            self::assertSame('', $error, $scenario);
            foreach ($contact as $value) {
                self::assertStringNotContainsString($value, $output.$error);
            }
        }
    }

    public function test_every_supported_channel_and_replay_are_deterministic_and_hidden(): void
    {
        $payload = json_encode([
            'channel' => 'notification_preview',
            'contact' => ['phone' => '+1 415 555 0199', 'whatsapp' => '+1 415 555 0198', 'email' => 'replay@example.test'],
        ], JSON_THROW_ON_ERROR);

        foreach (['ui', 'api', 'search', 'export', 'log', 'notification_preview', 'file_download'] as $channel) {
            $case = json_encode(['channel' => $channel, 'contact' => json_decode($payload, true)['contact']], JSON_THROW_ON_ERROR);
            self::assertSame([0, self::SAFE_OUTPUT, ''], $this->runCli($case));
        }

        self::assertSame($this->runCli($payload), $this->runCli($payload));
    }

    public function test_injected_actor_record_role_claim_and_grant_fields_are_rejected_without_leak(): void
    {
        $injectedFields = [
            'actor' => ['id' => 'actor-secret'],
            'record' => ['id' => 'record-secret'],
            'role' => 'super-administrator',
            'claim' => 'contact.reveal',
            'grant' => 'contact.reveal',
        ];

        foreach ($injectedFields as $field => $value) {
            $payload = [
                'channel' => 'api',
                'contact' => ['phone' => '+1 415 555 0120', 'whatsapp' => '+1 415 555 0121', 'email' => 'bypass@example.test'],
                $field => $value,
            ];
            [$status, $output, $error] = $this->runCli(json_encode($payload, JSON_THROW_ON_ERROR));

            self::assertSame(2, $status, $field);
            self::assertSame("تعذّر عرض البيانات. استخدم fixture تشخيصية صحيحة.\n", $output, $field);
            self::assertSame('', $error, $field);
            foreach (['actor-secret', 'record-secret', 'bypass@example.test'] as $secret) {
                self::assertStringNotContainsString($secret, $output.$error, $field);
            }
        }
    }

    public function test_invalid_json_schema_size_depth_and_channel_never_echo_contact_data(): void
    {
        $secret = '+1 415 555 0130 private@example.test';
        $payloads = [
            '{"channel":"ui","contact":{"phone":"'.$secret.'"}',
            '[]',
            json_encode(['channel' => 'ui', 'contact' => ['phone' => $secret]], JSON_THROW_ON_ERROR),
            json_encode(['channel' => 'reveal', 'contact' => ['phone' => $secret, 'whatsapp' => null, 'email' => null]], JSON_THROW_ON_ERROR),
            str_repeat(' ', 4097),
            '{"channel":"ui","contact":{"phone":{"a":{"b":{"c":{"d":{"e":{"f":{"g":{"h":{"i":1}}}}}}}}},"whatsapp":null,"email":null}}',
        ];

        foreach ($payloads as $payload) {
            [$status, $output, $error] = $this->runCli($payload);
            self::assertSame(2, $status);
            self::assertSame("تعذّر عرض البيانات. استخدم fixture تشخيصية صحيحة.\n", $output);
            self::assertSame('', $error);
            self::assertStringNotContainsString($secret, $output.$error);
            self::assertStringNotContainsString('Trace', $output.$error);
        }
    }

    /** @return array{int, string, string} */
    private function runCli(string $payload): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('app/Modules/Identity/Infrastructure/SEC02/contact-diagnostic.php')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        self::assertIsResource($process);
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $error];
    }
}
