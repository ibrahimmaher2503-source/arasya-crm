<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Application\ARCH01\EvaluateModuleBoundary;
use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryDecision;
use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryGuard;
use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryRequest;
use App\Modules\Platform\Domain\ARCH01\ProviderSdkDependencyGuard;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ARCH01DomainTest extends TestCase
{
    private EvaluateModuleBoundary $boundary;

    protected function setUp(): void
    {
        $this->boundary = new EvaluateModuleBoundary(new ModuleBoundaryGuard);
    }

    public function test_module_owned_write_is_allowed_for_matching_entity_scope_and_registered_resource(): void
    {
        self::assertTrue($this->boundary->evaluate(new ModuleBoundaryRequest(
            'Organization', 'entity-1', 'Organization', 'entity-1', 'organization_references', 'write',
        ))->allowed);
    }

    public function test_direct_cross_module_write_is_denied_without_mutating_the_intent(): void
    {
        $request = new ModuleBoundaryRequest(
            'Platform', 'entity-1', 'Organization', 'entity-1', 'organization_references', 'write',
        );
        $before = [
            $request->sourceModule,
            $request->sourceEntityId,
            $request->targetModule,
            $request->targetEntityId,
            $request->targetTable,
            $request->operation,
            $request->boundaryContractId,
        ];

        $firstDecision = $this->boundary->evaluate($request);
        $secondDecision = $this->boundary->evaluate($request);

        self::assertFalse($firstDecision->allowed);
        self::assertSame(ModuleBoundaryDecision::DENIED, $firstDecision->route);
        self::assertEquals($firstDecision, $secondDecision);
        self::assertSame($before, [
            $request->sourceModule,
            $request->sourceEntityId,
            $request->targetModule,
            $request->targetEntityId,
            $request->targetTable,
            $request->operation,
            $request->boundaryContractId,
        ]);
    }

    public function test_same_module_cannot_claim_a_table_owned_by_another_or_unknown_resource(): void
    {
        foreach (['payroll_entries', 'unknown_table'] as $table) {
            self::assertFalse($this->boundary->evaluate(new ModuleBoundaryRequest(
                'Organization', 'entity-1', 'Organization', 'entity-1', $table, 'write',
            ))->allowed, $table);
        }

        self::assertFalse($this->boundary->evaluate(new ModuleBoundaryRequest(
            'Platform', 'entity-1', 'Platform', 'entity-1', 'organization_references', 'write',
        ))->allowed);
    }

    public function test_mismatched_entity_scope_is_denied(): void
    {
        self::assertFalse($this->boundary->evaluate(new ModuleBoundaryRequest(
            'Organization', 'entity-1', 'Organization', 'entity-2', 'organization_references', 'write',
        ))->allowed);
    }

    public function test_blank_unknown_and_mismatched_boundary_data_fail_closed(): void
    {
        foreach ([
            new ModuleBoundaryRequest(' ', 'entity-1', 'Organization', 'entity-1', 'organization_references', 'write'),
            new ModuleBoundaryRequest('Organization', '', 'Organization', 'entity-1', 'organization_references', 'write'),
            new ModuleBoundaryRequest('Organization', 'entity-1', 'Organization', ' ', 'organization_references', 'write'),
            new ModuleBoundaryRequest('Unknown', 'entity-1', 'Organization', 'entity-1', 'organization_references', 'write'),
            new ModuleBoundaryRequest('Organization', 'entity-1', 'Unknown', 'entity-1', 'organization_references', 'write'),
            new ModuleBoundaryRequest('Organization', 'entity-1', 'Organization', 'entity-1', '', 'write'),
            new ModuleBoundaryRequest('Organization', 'entity-1', 'Organization', 'entity-1', 'unknown_table', 'write'),
            new ModuleBoundaryRequest('Organization', 'entity-1', 'Organization', 'entity-1', 'organization_references', ''),
            new ModuleBoundaryRequest('Organization', 'entity-1', 'Organization', 'entity-1', 'organization_references', 'update'),
        ] as $request) {
            self::assertFalse($this->boundary->evaluate($request)->allowed);
        }
    }

    public function test_cross_module_contract_identifiers_fail_closed_until_a_real_owner_contract_exists(): void
    {
        foreach ([null, 'organization.reference.link.v1', 'unregistered.v1'] as $contractId) {
            self::assertFalse($this->boundary->evaluate(new ModuleBoundaryRequest(
                'Platform',
                'entity-1',
                'Organization',
                'entity-1',
                'organization_references',
                'write',
                $contractId,
            ))->allowed);
        }
    }

    public function test_provider_sdk_detector_checks_name_tokens_and_ignores_comments_and_strings(): void
    {
        $guard = new ProviderSdkDependencyGuard;
        $prohibitedFixtures = [
            "<?php\nuse OpenAI\\Client;\n",
            "<?php\nuse \\OpenAI\\Client as ProviderClient;\n",
            "<?php\nnew \\OpenAI\\Client();\n",
            "<?php\nOpenAI\\Client::make();\n",
            "<?php\nfunction accept(\\Anthropic\\Client \$client): \\Google\\Cloud\\AIPlatform\\Response {}\n",
            "<?php\n#[\\OpenAI\\Trace]\nclass Instrumented {}\n",
            "<?php\nuse Twilio\\Rest\\Client;\n",
            "<?php\nnew \\Aws\\BedrockRuntime\\BedrockRuntimeClient();\n",
            "<?php\nuse Google\\Cloud as Cloud;\nnew Cloud\\AIPlatform\\Client();\n",
            "<?php\nuse Aws\\{BedrockRuntime};\n",
        ];

        foreach ($prohibitedFixtures as $source) {
            self::assertFalse($guard->allowsSource($source), $source);
        }

        self::assertTrue($guard->allowsSource("<?php\n// use OpenAI\\Client;\n/* new Twilio\\Rest\\Client(); */\n"));
        self::assertTrue($guard->allowsSource("<?php\n\$example = 'OpenAI\\\\Client Twilio\\\\Rest\\\\Client';\n"));
        self::assertTrue($guard->allowsSource("<?php\nuse App\\Modules\\Shared\\Domain\\Value;\n"));
        self::assertFalse($guard->allowsSource('<?php class Broken {'));
    }

    public function test_all_platform_domain_php_sources_are_checked_recursively_and_non_empty(): void
    {
        $guard = new ProviderSdkDependencyGuard;
        $domainPath = dirname(__DIR__, 3).'/app/Modules/Platform/Domain';
        $directory = new RecursiveDirectoryIterator($domainPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory);
        $sourcePaths = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $sourcePaths[] = $file->getPathname();
            }
        }

        self::assertNotEmpty($sourcePaths);

        foreach ($sourcePaths as $sourcePath) {
            $source = file_get_contents($sourcePath);
            self::assertIsString($source, $sourcePath);
            self::assertTrue($guard->allowsSource($source), $sourcePath);
        }
    }
}
