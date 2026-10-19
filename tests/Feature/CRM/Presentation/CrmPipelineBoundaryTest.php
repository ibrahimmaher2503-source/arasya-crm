<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrmPipelineBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_enabled_local_preview_requires_identity_and_keeps_production_closed(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app['env'] = 'local';
        $this->postJson('/crm/pipeline', [])->assertUnauthorized();
        $this->app['env'] = 'production';
        $this->postJson('/crm/pipeline', [])->assertNotFound();
    }

    public function test_synthetic_pipeline_requires_identity(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $this->postJson('/crm/pipeline', [])->assertUnauthorized();
    }
}
