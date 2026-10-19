<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Modules\Identity\Application\SEC01\AuthorizeScopedRecord;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedPermissionGuard;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;
use App\Modules\Organization\Domain\CORE02\RecordKind;
use Tests\TestCase;

final class CORE02AccessTest extends TestCase
{
    private const INVALID_MESSAGE = "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n";

    public function test_cli_projects_each_allowlisted_kind_from_an_explicit_catalog(): void
    {
        foreach (RecordKind::cases() as $sourceKind) {
            $catalog = [];
            foreach (RecordKind::cases() as $kind) {
                $catalog[] = $this->identity($kind->value, $kind->value.'-id', 'entity-fixture');
            }

            $source = $this->identity($sourceKind->value, $sourceKind->value.'-id', 'entity-fixture');
            $links = array_values(array_filter(
                $catalog,
                static fn (array $identity): bool => $identity['kind'] !== $source['kind'],
            ));
            $payload = json_encode(['source' => $source, 'links' => $links, 'catalog' => $catalog], JSON_THROW_ON_ERROR);
            [$status, $output, $error] = $this->runCli($payload);

            self::assertSame(0, $status, $sourceKind->value);
            self::assertSame('{"decision":"allow","code":"core02_linkage_projected"}'."\n", $output);
            self::assertSame('', $error);
            $this->assertNoIdentities($payload, $output.$error);
        }
    }

    public function test_duplicate_conflicting_dangling_wrong_kind_and_cross_entity_catalogs_deny_safely(): void
    {
        $source = $this->identity('customer', 'customer-secret', 'entity-secret');
        $target = $this->identity('project', 'target-secret', 'entity-secret');
        $wrongKind = $this->identity('contract', 'target-secret', 'entity-secret');
        $otherEntity = $this->identity('project', 'target-secret', 'other-entity-secret');
        $cases = [
            'duplicate' => [$source, [$target], [$source, $target, $target]],
            'conflicting kind' => [$source, [], [$source, $target, $wrongKind]],
            'dangling' => [$source, [$target], [$source]],
            'wrong kind' => [$source, [$target], [$source, $wrongKind]],
            'cross entity' => [$source, [$otherEntity], [$source, $otherEntity]],
        ];

        foreach ($cases as $label => [$record, $links, $catalog]) {
            $payload = json_encode(['source' => $record, 'links' => $links, 'catalog' => $catalog], JSON_THROW_ON_ERROR);
            [$status, $output, $error] = $this->runCli($payload);

            self::assertSame(0, $status, $label);
            self::assertSame('{"decision":"deny","code":"core02_linkage_denied"}'."\n", $output, $label);
            self::assertSame('', $error, $label);
            $this->assertNoIdentities($payload, $output.$error);
        }
    }

    public function test_cli_denies_wrong_kind_links_for_every_allowlisted_kind(): void
    {
        $kinds = RecordKind::cases();
        foreach ($kinds as $index => $kind) {
            $wrongKind = $kinds[($index + 1) % count($kinds)];
            $source = $this->identity($kind->value, 'source-secret', 'entity-secret');
            $catalogTarget = $this->identity($kind->value, 'target-secret', 'entity-secret');
            $linkedTarget = $this->identity($wrongKind->value, 'target-secret', 'entity-secret');
            $payload = json_encode([
                'source' => $source,
                'links' => [$linkedTarget],
                'catalog' => [$source, $catalogTarget],
            ], JSON_THROW_ON_ERROR);
            [$status, $output, $error] = $this->runCli($payload);

            self::assertSame(0, $status, $kind->value);
            self::assertSame('{"decision":"deny","code":"core02_linkage_denied"}'."\n", $output, $kind->value);
            self::assertSame('', $error);
            self::assertStringNotContainsString('source-secret', $output.$error);
            self::assertStringNotContainsString('target-secret', $output.$error);
            self::assertStringNotContainsString('entity-secret', $output.$error);
        }
    }

    public function test_cli_replay_and_changed_catalog_fail_closed_without_leaking_snapshot_values(): void
    {
        $fixture = $this->validFixture();
        $payload = json_encode($fixture, JSON_THROW_ON_ERROR);
        $first = $this->runCli($payload);
        self::assertSame($first, $this->runCli($payload));
        self::assertSame([0, '{"decision":"allow","code":"core02_linkage_projected"}'."\n", ''], $first);

        $fixture['catalog'] = [$fixture['source']];
        [$status, $output, $error] = $this->runCli(json_encode($fixture, JSON_THROW_ON_ERROR));
        self::assertSame(0, $status);
        self::assertSame('{"decision":"deny","code":"core02_linkage_denied"}'."\n", $output);
        self::assertSame('', $error);
        $this->assertNoIdentities(json_encode($fixture, JSON_THROW_ON_ERROR), $output.$error);
    }

    public function test_cli_rejects_malformed_schema_types_limits_and_claim_fields_without_echoing_input(): void
    {
        $valid = $this->validFixture();
        $overlongId = $valid;
        $overlongId['source']['id'] = str_repeat('x', 129);
        $malformedId = $valid;
        $malformedId['source']['id'] = 'bad/id-secret';
        $wrongIdType = $valid;
        $wrongIdType['source']['entityId'] = 12;
        $invalidPayloads = [
            '{"source":',
            '[]',
            json_encode($valid + ['actor' => ['id' => 'actor-secret']], JSON_THROW_ON_ERROR),
            json_encode($valid + ['role' => 'administrator'], JSON_THROW_ON_ERROR),
            json_encode($valid + ['grant' => 'link.read'], JSON_THROW_ON_ERROR),
            json_encode(array_replace($valid, ['source' => ['kind' => 'unknown-secret', 'id' => 'id', 'entityId' => 'entity']]), JSON_THROW_ON_ERROR),
            json_encode(array_replace($valid, ['links' => ['not-an-object-secret']]), JSON_THROW_ON_ERROR),
            json_encode($overlongId, JSON_THROW_ON_ERROR),
            json_encode($malformedId, JSON_THROW_ON_ERROR),
            json_encode($wrongIdType, JSON_THROW_ON_ERROR),
            json_encode(array_replace($valid, ['catalog' => array_fill(0, 257, $valid['source'])]), JSON_THROW_ON_ERROR),
            str_repeat('x', 65537),
            '{"source":{"kind":"customer","id":"secret","entityId":"entity"},"links":[],"catalog":[],"nested":{"a":{"b":{"c":{"d":{"e":{"f":{"g":{"h":{"i":1}}}}}}}}}}',
        ];

        foreach ($invalidPayloads as $payload) {
            [$status, $output, $error] = $this->runCli($payload);
            self::assertSame(2, $status);
            self::assertSame(self::INVALID_MESSAGE, $output);
            self::assertSame('', $error);
            $this->assertNoIdentities($payload, $output.$error);
            self::assertStringNotContainsString('Trace', $output.$error);
        }
    }

    public function test_explicit_synthetic_sec01_guard_scenarios_are_separate_from_cli_diagnostic_assertions(): void
    {
        $authorization = new AuthorizeScopedRecord(new ScopedPermissionGuard);
        $fixtures = [
            'own allowed' => [new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']), new ScopedRecord('record-own', 'entity-1', 'department-1', ownerId: 'actor-1'), true],
            'assigned allowed' => [new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']), new ScopedRecord('record-assigned', 'entity-1', 'department-1', assignedActorId: 'actor-1'), true],
            'other actor denied' => [new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']), new ScopedRecord('record-other', 'entity-1', 'department-1', ownerId: 'actor-2'), false],
            'unassigned denied' => [new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']), new ScopedRecord('record-unassigned', 'entity-1', 'department-1'), false],
            'cross entity denied' => [new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read']), new ScopedRecord('record-cross-entity', 'entity-2', 'department-1', assignedActorId: 'actor-1'), false],
            'false override denied' => [new ActorAccess('actor-1', 'entity-1', 'department-1', ['crm.lead.read'], ['crm.lead.read' => false]), new ScopedRecord('record-revoked-fixture', 'entity-1', 'department-1', assignedActorId: 'actor-1'), false],
        ];
        $diagnosticPayload = json_encode($this->validFixture(), JSON_THROW_ON_ERROR);

        foreach ($fixtures as $scenario => [$actor, $record, $guardDecision]) {
            self::assertSame($guardDecision, $authorization->allows($actor, 'crm.lead.read', $record), $scenario);

            [$status, $output, $error] = $this->runCli($diagnosticPayload);
            self::assertSame(0, $status, $scenario);
            self::assertSame('{"decision":"allow","code":"core02_linkage_projected"}'."\n", $output, $scenario);
            self::assertSame('', $error, $scenario);
            $this->assertNoIdentities($diagnosticPayload, $output.$error);
        }
    }

    /** @return array{kind:string,id:string,entityId:string} */
    private function identity(string $kind, string $id, string $entityId): array
    {
        return ['kind' => $kind, 'id' => $id, 'entityId' => $entityId];
    }

    /** @return array{source:array{kind:string,id:string,entityId:string},links:list<array{kind:string,id:string,entityId:string}>,catalog:list<array{kind:string,id:string,entityId:string}>} */
    private function validFixture(): array
    {
        $source = $this->identity('customer', 'customer-secret', 'entity-secret');
        $target = $this->identity('project', 'project-secret', 'entity-secret');

        return ['source' => $source, 'links' => [$target], 'catalog' => [$source, $target]];
    }

    private function assertNoIdentities(string $input, string $output): void
    {
        foreach (['customer-secret', 'project-secret', 'entity-secret', 'other-entity-secret', 'target-secret', 'actor-secret'] as $value) {
            self::assertStringNotContainsString($value, $output, 'Input included: '.$input);
        }
    }

    /** @return array{int,string,string} */
    private function runCli(string $payload): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('app/Modules/Organization/Infrastructure/CORE02/record-linkage-diagnostic.php')],
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
