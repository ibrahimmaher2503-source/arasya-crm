<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrmPipelineBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_pipeline_requires_identity(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $this->postJson('/crm/pipeline', [])->assertUnauthorized();
    }
}
