<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\ARCH01;

use App\Modules\Platform\Application\ARCH01\EvaluateModuleBoundary;
use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryRequest;
use App\Modules\Platform\Domain\ARCH01\ProviderSdkDependencyGuard;
use JsonException;

final readonly class InspectArchitecturePolicy
{
    public function __construct(
        private EvaluateModuleBoundary $boundary,
        private ProviderSdkDependencyGuard $dependencies,
    ) {}

    public function inspect(string $json): bool
    {
        if (strlen($json) > 8192) {
            return false;
        }

        try {
            $intent = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! is_array($intent) || array_is_list($intent) || ! is_string($intent['kind'] ?? null)) {
            return false;
        }

        if ($intent['kind'] === 'dependency') {
            $keys = array_keys($intent);
            sort($keys);
            if ($keys !== ['kind', 'source'] || ! is_string($intent['source']) || strlen($intent['source']) > 4096) {
                return false;
            }

            return $this->dependencies->allowsSource($intent['source']);
        }

        if ($intent['kind'] !== 'boundary') {
            return false;
        }

        $required = ['kind', 'sourceModule', 'sourceEntityId', 'targetModule', 'targetEntityId', 'targetTable', 'operation'];
        $keys = array_keys($intent);
        sort($required);
        sort($keys);
        $optional = [...$required, 'boundaryContractId'];
        sort($optional);
        if ($keys !== $required && $keys !== $optional) {
            return false;
        }

        foreach (['sourceModule', 'sourceEntityId', 'targetModule', 'targetEntityId', 'targetTable', 'operation'] as $key) {
            if (! is_string($intent[$key]) || $intent[$key] === '' || strlen($intent[$key]) > 128) {
                return false;
            }
        }

        $contract = $intent['boundaryContractId'] ?? null;
        if ($contract !== null && (! is_string($contract) || $contract === '' || strlen($contract) > 128)) {
            return false;
        }

        $decision = $this->boundary->evaluate(new ModuleBoundaryRequest(
            $intent['sourceModule'], $intent['sourceEntityId'], $intent['targetModule'],
            $intent['targetEntityId'], $intent['targetTable'], $intent['operation'], $contract,
        ));

        return $decision->allowed;
    }
}
