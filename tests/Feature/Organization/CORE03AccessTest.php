<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Modules\Identity\Application\SEC01\AuthorizeScopedRecord;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedPermissionGuard;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;
use Tests\TestCase;

final class CORE03AccessTest extends TestCase
{
    private const ERROR = "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n";

    public function test_cli_proposes_allowed_and_denied_transitions_without_exposing_fixture_data(): void
    {
        $allowed = $this->fixture();
        $this->assertCli($allowed, 0, ['decision' => 'allow', 'code' => 'transition_proposed', 'replayed' => false, 'intent_present' => true]);

        foreach ([
            ['permission' => [], 'code' => 'permission_missing'],
            ['validatorsPassed' => false, 'code' => 'validation_failed'],
            ['entityId' => 'different-entity-secret', 'code' => 'entity_mismatch'],
            ['expectedVersion' => 7, 'code' => 'stale_version'],
            ['to' => 'forbidden-secret', 'code' => 'transition_not_allowed'],
        ] as $case) {
            $fixture = $this->fixture();
            if (array_key_exists('permission', $case)) {
                $fixture['permissions'] = $case['permission'];
            }
            foreach (['validatorsPassed', 'entityId', 'expectedVersion', 'to'] as $key) {
                if (array_key_exists($key, $case)) {
                    $fixture[$key] = $case[$key];
                }
            }
            $this->assertCli($fixture, 0, ['decision' => 'deny', 'code' => $case['code'], 'replayed' => false, 'intent_present' => false]);
        }
    }

    public function test_cli_accepts_rfc3339_utc_and_valid_numeric_offsets_with_fractional_seconds(): void
    {
        foreach ([
            '2026-01-02T00:00:00Z',
            '2026-01-02T00:00:00+00:00',
            '2026-01-02T00:00:00-05:30',
            '2026-01-02T00:00:00.1Z',
            '2026-01-02T00:00:00.12+01:00',
            '2026-01-02T00:00:00.123-04:00',
            '2026-01-02T00:00:00.1234Z',
            '2026-01-02T00:00:00.12345+05:45',
            '2026-01-02T00:00:00.123456-03:30',
        ] as $occurredAt) {
            $fixture = $this->fixture();
            $fixture['occurredAt'] = $occurredAt;
            $this->assertCli($fixture, 0, ['decision' => 'allow', 'code' => 'transition_proposed', 'replayed' => false, 'intent_present' => true]);
        }
    }

    public function test_cli_conflict_and_matching_replay_return_sanitized_outcomes(): void
    {
        $replay = $this->fixture();
        $replay['state']['status'] = 'review-secret';
        $replay['state']['version'] = 1;
        $replay['state']['history'] = [[
            'from' => 'draft-secret', 'to' => 'review-secret', 'actorId' => 'actor-secret', 'correlationId' => 'correlation-secret',
            'requestId' => 'request-secret', 'entityId' => 'entity-secret', 'subjectId' => 'subject-secret', 'sourceVersion' => 0,
            'occurredAt' => '2026-01-02T04:00:00+04:00',
        ]];
        $this->assertCli($replay, 0, ['decision' => 'allow', 'code' => 'transition_replayed', 'replayed' => true, 'intent_present' => false]);
        $conflict = $replay;
        $conflict['to'] = 'other-secret';
        $this->assertCli($conflict, 0, ['decision' => 'deny', 'code' => 'request_id_conflict', 'replayed' => false, 'intent_present' => false]);
    }

    public function test_cli_rejects_malformed_history_overflow_schema_types_and_untrusted_claims(): void
    {
        $badHistory = $this->fixture();
        $badHistory['state']['version'] = 1;
        $badHistory['state']['status'] = 'review';
        $badHistory['state']['history'] = [[
            'from' => 'draft', 'to' => 'review', 'actorId' => 'a', 'correlationId' => 'c', 'requestId' => 'r',
            'entityId' => 'entity-secret', 'subjectId' => 'subject-secret', 'sourceVersion' => 999, 'occurredAt' => '2026-01-01T00:00:00Z',
        ]];
        $max = $this->fixture();
        $max['state']['version'] = PHP_INT_MAX;
        $max['expectedVersion'] = PHP_INT_MAX;
        $wrongHistory = $this->fixture();
        $wrongHistory['state']['history'] = [['unexpected' => 'secret']];
        $invalidHistoryOffset = $this->fixture();
        $invalidHistoryOffset['state']['version'] = 1;
        $invalidHistoryOffset['state']['status'] = 'review-secret';
        $invalidHistoryOffset['state']['history'] = [[
            'from' => 'draft-secret', 'to' => 'review-secret', 'actorId' => 'actor-secret', 'correlationId' => 'correlation-secret',
            'requestId' => 'request-secret', 'entityId' => 'entity-secret', 'subjectId' => 'subject-secret', 'sourceVersion' => 0,
            'occurredAt' => '2026-01-02T00:00:00+99:99',
        ]];
        $malformed = [
            '{"state":', '[]', json_encode($this->fixture() + ['authorization' => true], JSON_THROW_ON_ERROR),
            json_encode(array_replace($this->fixture(), ['expectedVersion' => '0']), JSON_THROW_ON_ERROR),
            json_encode(array_replace($this->fixture(), ['validatorsPassed' => 1]), JSON_THROW_ON_ERROR),
            json_encode($wrongHistory, JSON_THROW_ON_ERROR),
            json_encode($badHistory, JSON_THROW_ON_ERROR),
            json_encode($invalidHistoryOffset, JSON_THROW_ON_ERROR),
            json_encode(array_replace($this->fixture(), ['occurredAt' => '2026-01-02T00:00:00+99:99']), JSON_THROW_ON_ERROR),
            json_encode(array_replace($this->fixture(), ['occurredAt' => '2026-01-02T00:00:00-99:99']), JSON_THROW_ON_ERROR),
            json_encode(array_replace($this->fixture(), ['occurredAt' => '2026-01-02T00:00:00+24:00']), JSON_THROW_ON_ERROR),
            json_encode(array_replace($this->fixture(), ['occurredAt' => '2026-01-02T00:00:00+01:60']), JSON_THROW_ON_ERROR),
            json_encode(array_replace($this->fixture(), ['occurredAt' => '2026-02-30T00:00:00Z']), JSON_THROW_ON_ERROR),
            json_encode(array_replace($this->fixture(), ['occurredAt' => '2026-01-01 00:00:00']), JSON_THROW_ON_ERROR),
            str_repeat('x', 65537),
            '{"state":{"entityId":"e","subjectId":"s","status":"d","version":0,"history":[]},"nested":{"a":{"b":{"c":{"d":{"e":{"f":{"g":{"h":{"i":{"j":{"k":1}}}}}}}}}}}}',
        ];
        $this->assertCli($max, 0, ['decision' => 'deny', 'code' => 'version_exhausted', 'replayed' => false, 'intent_present' => false]);
        foreach ($malformed as $payload) {
            [$status, $output, $error] = $this->runCli($payload);
            self::assertSame(2, $status);
            self::assertSame(self::ERROR, $output);
            self::assertSame('', $error);
            self::assertStringNotContainsString('secret', $output.$error);
            self::assertStringNotContainsString('Trace', $output.$error);
        }
    }

    public function test_sec01_synthetic_own_assignment_and_denials_are_separate_guard_assertions(): void
    {
        $guard = new AuthorizeScopedRecord(new ScopedPermissionGuard);
        $actor = new ActorAccess('actor-1', 'entity-1', 'department-1', ['fixture.read']);
        $record = static fn (string $id, string $entity = 'entity-1', ?string $owner = null, ?string $assigned = null): ScopedRecord => new ScopedRecord($id, $entity, 'department-1', $owner, $assigned);
        $cases = [
            [$actor, $record('own', owner: 'actor-1'), true],
            [$actor, $record('assigned', assigned: 'actor-1'), true],
            [$actor, $record('other', owner: 'actor-2'), false],
            [$actor, $record('unassigned'), false],
            [$actor, $record('entity', 'entity-2', assigned: 'actor-1'), false],
            [new ActorAccess('actor-1', 'entity-1', 'department-1', ['fixture.read'], ['fixture.read' => false]), $record('override', assigned: 'actor-1'), false],
        ];
        foreach ($cases as [$access, $scopedRecord, $expected]) {
            self::assertSame($expected, $guard->allows($access, 'fixture.read', $scopedRecord));
        }
    }

    private function fixture(): array
    {
        return [
            'state' => ['entityId' => 'entity-secret', 'subjectId' => 'subject-secret', 'status' => 'draft-secret', 'version' => 0, 'history' => []],
            'edges' => ['draft-secret' => ['review-secret' => 'fixture.permission-secret']],
            'permissions' => ['fixture.permission-secret'], 'validatorsPassed' => true, 'entityId' => 'entity-secret',
            'to' => 'review-secret', 'expectedVersion' => 0, 'actorId' => 'actor-secret', 'correlationId' => 'correlation-secret',
            'requestId' => 'request-secret', 'occurredAt' => '2026-01-02T04:00:00+04:00',
        ];
    }

    private function assertCli(array $fixture, int $statusExpected, array $result): void
    {
        $payload = json_encode($fixture, JSON_THROW_ON_ERROR);
        [$status, $output, $error] = $this->runCli($payload);
        self::assertSame($statusExpected, $status);
        self::assertSame(json_encode($result, JSON_THROW_ON_ERROR)."\n", $output);
        self::assertSame('', $error);
        foreach (['secret', 'entity-secret', 'subject-secret', 'actor-secret'] as $sensitive) {
            self::assertStringNotContainsString($sensitive, $output.$error);
        }
    }

    private function runCli(string $payload): array
    {
        $pipes = [];
        $process = proc_open([PHP_BINARY, base_path('app/Modules/Organization/Infrastructure/CORE03/transition-proposal-diagnostic.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
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
