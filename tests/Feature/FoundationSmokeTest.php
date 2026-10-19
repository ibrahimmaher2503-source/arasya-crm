<?php

namespace Tests\Feature;

use App\Modules\Shared\Application\ModuleCatalog;
use Tests\TestCase;

final class FoundationSmokeTest extends TestCase
{
    public function test_health_route_returns_a_non_sensitive_status(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame('Application up', $response->getContent());
        $this->assertSame('.env.example', $this->app->environmentFile());
    }

    public function test_identity_authentication_is_deferred_without_a_default_model(): void
    {
        $this->assertNull(config('auth.defaults.guard'));
        $this->assertSame([], config('auth.guards'));
        $this->assertSame([], config('auth.providers'));
        $this->assertSame([], config('auth.passwords'));
    }

    public function test_module_catalog_is_available_from_the_composition_provider(): void
    {
        $this->assertSame([
            'Identity',
            'Organization',
            'Workflow',
            'Audit',
            'Documents',
            'CRM',
            'Tasks',
            'Performance',
            'Sales',
            'Projects',
            'Inventory',
            'Delivery',
            'Quality',
            'Production',
            'Finance',
            'Employees',
            'Compensation',
            'Communications',
            'Reporting',
            'AI',
        ], app(ModuleCatalog::class)->names());
    }
}
