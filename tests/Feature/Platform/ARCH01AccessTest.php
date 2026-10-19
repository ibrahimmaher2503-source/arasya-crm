<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Application\ARCH01\EvaluateModuleBoundary;
use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryGuard;
use App\Modules\Platform\Domain\ARCH01\ProviderSdkDependencyGuard;
use App\Modules\Platform\Infrastructure\ARCH01\InspectArchitecturePolicy;
use App\Modules\Platform\Presentation\ARCH01\InspectArchitecturePolicyCli;
use Tests\TestCase;

final class ARCH01AccessTest extends TestCase
{
    private InspectArchitecturePolicyCli $cli;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cli = new InspectArchitecturePolicyCli(new InspectArchitecturePolicy(
            new EvaluateModuleBoundary(new ModuleBoundaryGuard),
            new ProviderSdkDependencyGuard,
        ));
    }

    public function test_owned_resource_reports_generic_allow_without_echoing_intent(): void
    {
        [$exit, $output, $error] = $this->invoke($this->boundaryIntent());

        self::assertSame(0, $exit);
        self::assertSame(['{"decision":"allowed","code":"ARCH01_ALLOWED"}'], $output);
        self::assertSame([], $error);
    }

    public function test_cross_module_entity_and_explicit_contract_denials_are_sanitized(): void
    {
        $crossModule = $this->boundaryIntent(['sourceModule' => 'Platform', 'sourceEntityId' => 'secret-ref']);
        $mismatchedEntity = $this->boundaryIntent(['targetEntityId' => 'other-secret-ref']);
        $contract = $this->boundaryIntent(['boundaryContractId' => 'future.contract.v1']);

        foreach ([$crossModule, $mismatchedEntity, $contract] as $intent) {
            [$exit, $output, $error] = $this->invoke($intent);
            $this->assertDenied($exit, $output, $error);
        }
    }

    public function test_malformed_shapes_unknown_fields_types_and_size_fail_closed(): void
    {
        $invalidInputs = [
            '{not-json',
            '[]',
            '"scalar"',
            '{"kind":"boundary","sourceModule":"Organization","sourceEntityId":"x","targetModule":"Organization","targetEntityId":"x","targetTable":"organization_references","operation":"write","extra":"secret"}',
            '{"kind":"boundary","sourceModule":7,"sourceEntityId":"x","targetModule":"Organization","targetEntityId":"x","targetTable":"organization_references","operation":"write"}',
            '{"kind":"boundary","sourceModule":"Organization","sourceEntityId":"x","targetModule":"Organization","targetEntityId":"x","targetTable":"organization_references","operation":"write","boundaryContractId":7}',
            str_repeat(' ', 8193),
            '{"a":{"b":{"c":{"d":{"e":{"f":{"g":{"h":{"i":1}}}}}}}}}',
        ];

        foreach ($invalidInputs as $intent) {
            [$exit, $output, $error] = $this->invoke($intent);
            $this->assertDenied($exit, $output, $error);
        }

        [$exit, $output, $error] = $this->invoke('%%%not-base64%%%', true);
        $this->assertDenied($exit, $output, $error);
    }

    public function test_dependency_scan_accepts_safe_inline_source_and_denies_provider_or_malformed_php(): void
    {
        [$exit, $output, $error] = $this->invoke('{"kind":"dependency","source":"<?php class Safe {}"}');
        self::assertSame(0, $exit);
        self::assertSame(['{"decision":"allowed","code":"ARCH01_ALLOWED"}'], $output);
        self::assertSame([], $error);

        foreach ([
            '{"kind":"dependency","source":"<?php new \\\\OpenAI\\\\Client();"}',
            '{"kind":"dependency","source":"<?php class Broken {"}',
            '{"kind":"dependency","source":"<?php class Safe {}","path":"secret.php"}',
        ] as $intent) {
            [$exit, $output, $error] = $this->invoke($intent);
            $this->assertDenied($exit, $output, $error);
        }
    }

    public function test_repeated_invocations_are_stateless_and_denial_is_localized_without_input_details(): void
    {
        $intent = $this->boundaryIntent(['sourceEntityId' => 'private-id', 'sourceModule' => 'Platform']);
        [$firstExit, $firstOutput, $firstError] = $this->invoke($intent);
        [$secondExit, $secondOutput, $secondError] = $this->invoke($intent);

        self::assertSame([$firstExit, $firstOutput, $firstError], [$secondExit, $secondOutput, $secondError]);
        self::assertSame(1, $firstExit);
        self::assertSame(['{"decision":"denied","code":"ARCH01_DENIED"}'], $firstOutput);
        self::assertSame(['تم رفض فحص السياسة؛ راجع صيغة الطلب المسموح بها.'], $firstError);
        self::assertStringNotContainsString('private-id', implode($firstOutput).implode($firstError));
    }

    /** @return array{int, list<string>, list<string>} */
    private function invoke(string $intent, bool $base64 = false): array
    {
        $output = [];
        $error = [];
        $arguments = [$intent];
        if ($base64) {
            $arguments[] = '--base64';
        }

        $exit = $this->cli->run(
            $arguments,
            static function (string $line) use (&$output): void {
                $output[] = $line;
            },
            static function (string $line) use (&$error): void {
                $error[] = $line;
            },
        );

        return [$exit, $output, $error];
    }

    /** @param array<string, mixed> $overrides */
    private function boundaryIntent(array $overrides = []): string
    {
        return json_encode(array_replace([
            'kind' => 'boundary',
            'sourceModule' => 'Organization',
            'sourceEntityId' => 'org-7',
            'targetModule' => 'Organization',
            'targetEntityId' => 'org-7',
            'targetTable' => 'organization_references',
            'operation' => 'write',
        ], $overrides), JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $output @param list<string> $error */
    private function assertDenied(int $exit, array $output, array $error): void
    {
        self::assertSame(1, $exit);
        self::assertSame(['{"decision":"denied","code":"ARCH01_DENIED"}'], $output);
        self::assertSame(['تم رفض فحص السياسة؛ راجع صيغة الطلب المسموح بها.'], $error);
    }
}
