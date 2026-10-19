<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CrmFileBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_operations_require_an_authenticated_actor(): void
    {
        $this->postJson('/crm/leads/01JARASYAFILETEST0000000000/files', [])->assertUnauthorized();
        $this->getJson('/crm/leads/01JARASYAFILETEST0000000000/files/01JARASYAFILETEST0000000001/download')->assertUnauthorized();
    }
}
