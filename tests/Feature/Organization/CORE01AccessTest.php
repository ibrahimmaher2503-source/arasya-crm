<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Modules\Organization\Infrastructure\CORE01\ReferenceDiagnosticAdapter;
use Tests\TestCase;

final class CORE01AccessTest extends TestCase
{
    private const REFERENCE_KINDS = [
        'entity', 'department', 'branch', 'warehouse', 'site', 'currency', 'unit',
        'status', 'expense_category', 'document_numbering', 'approval_policy',
    ];

    public function test_every_supported_kind_matches_only_its_exact_identity(): void
    {
        $adapter = new ReferenceDiagnosticAdapter;

        foreach (self::REFERENCE_KINDS as $kind) {
            $referenceId = $kind === 'entity' ? 'entity-1' : $kind.'-ref';
            $fixture = [
                'operation' => 'match',
                'kind' => $kind,
                'referenceId' => $referenceId,
                'entityId' => 'entity-1',
                'reference' => ['kind' => $kind, 'referenceId' => $referenceId, 'entityId' => 'entity-1'],
            ];
            self::assertSame(['decision' => 'allow', 'code' => 'core01_reference_matched'], $adapter->evaluate($fixture), $kind);
            $fixture['reference']['referenceId'] = 'different-ref';
            self::assertSame(['decision' => 'deny', 'code' => 'core01_reference_not_matched'], $adapter->evaluate($fixture), $kind);
        }
    }

    public function test_linking_and_matching_fail_closed_for_cross_entity_unknown_blank_and_invalid_entity_identity(): void
    {
        $adapter = new ReferenceDiagnosticAdapter;
        self::assertSame(['decision' => 'allow', 'code' => 'core01_link_allowed'], $adapter->evaluate($this->linkFixture()));

        foreach ([
            $this->linkFixture(targetEntityId: 'entity-2'),
            $this->linkFixture(sourceKind: 'unknown_kind'),
            $this->linkFixture(sourceReferenceId: ' '),
            $this->linkFixture(sourceKind: 'entity', sourceReferenceId: 'not-entity-1'),
        ] as $fixture) {
            self::assertSame(['decision' => 'deny', 'code' => 'core01_link_denied'], $adapter->evaluate($fixture));
        }

        $match = [
            'operation' => 'match', 'kind' => 'department', 'referenceId' => 'dept-1', 'entityId' => 'entity-1',
            'reference' => ['kind' => 'department', 'referenceId' => 'dept-1', 'entityId' => 'entity-2'],
        ];
        self::assertSame(['decision' => 'deny', 'code' => 'core01_reference_not_matched'], $adapter->evaluate($match));
    }

    public function test_cli_returns_sanitized_allow_and_deny_for_synthetic_references(): void
    {
        [$status, $output, $error] = $this->runCli(json_encode($this->linkFixture(), JSON_THROW_ON_ERROR));
        self::assertSame(0, $status);
        self::assertSame('{"decision":"allow","code":"core01_link_allowed"}'."\n", $output);
        self::assertSame('', $error);
        self::assertStringNotContainsString('dept-secret', $output);

        [$status, $output] = $this->runCli(json_encode($this->linkFixture(targetEntityId: 'entity-secret'), JSON_THROW_ON_ERROR));
        self::assertSame(0, $status);
        self::assertSame('{"decision":"deny","code":"core01_link_denied"}'."\n", $output);
        self::assertStringNotContainsString('entity-secret', $output);

        [$status, $output] = $this->runCli(json_encode($this->linkFixture(), JSON_THROW_ON_ERROR));
        self::assertSame(0, $status);
        self::assertSame('{"decision":"allow","code":"core01_link_allowed"}'."\n", $output);
    }

    public function test_cli_rejects_malformed_types_size_depth_and_unknown_fields_without_echoing_input(): void
    {
        $invalid = [
            '{bad-json secret',
            '[]',
            json_encode($this->linkFixture() + ['extra' => 'private-payload'], JSON_THROW_ON_ERROR),
            json_encode($this->linkFixture(sourceReferenceId: 42), JSON_THROW_ON_ERROR),
            str_repeat(' ', 4097),
            '{"a":{"b":{"c":{"d":{"e":{"f":{"g":{"h":{"i":1}}}}}}}}}',
        ];

        foreach ($invalid as $payload) {
            [$status, $output, $error] = $this->runCli($payload);
            self::assertSame(2, $status);
            self::assertSame("تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n", $output);
            self::assertSame('', $error);
            self::assertStringNotContainsString('private-payload', $output);
            self::assertStringNotContainsString('Trace', $output);
        }
    }

    public function test_adapter_rejects_extra_fields_and_non_string_identities(): void
    {
        $adapter = new ReferenceDiagnosticAdapter;
        self::assertSame(['decision' => 'deny', 'code' => 'invalid_diagnostic_input'], $adapter->evaluate($this->linkFixture() + ['extra' => 'secret']));
        self::assertSame(['decision' => 'deny', 'code' => 'invalid_diagnostic_input'], $adapter->evaluate($this->linkFixture(sourceReferenceId: ['bad'])));
    }

    private function linkFixture(
        string $sourceKind = 'department',
        mixed $sourceReferenceId = 'dept-secret',
        string $targetEntityId = 'entity-1',
    ): array {
        return [
            'operation' => 'link',
            'source' => ['kind' => $sourceKind, 'referenceId' => $sourceReferenceId, 'entityId' => 'entity-1'],
            'target' => ['kind' => 'branch', 'referenceId' => 'branch-private', 'entityId' => $targetEntityId],
        ];
    }

    /** @return array{int, string, string} */
    private function runCli(string $payload): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('app/Modules/Organization/Infrastructure/CORE01/reference-diagnostic.php')],
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
