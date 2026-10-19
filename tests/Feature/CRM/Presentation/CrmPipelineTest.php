<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CrmPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_opportunity_reads_preserve_exact_decimal_totals_without_mutating_audit_or_versions(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $lead = $this->fixtureId(950);
        $this->seedLead($world, $lead);
        $id = $this->seedOpportunity($world, $lead, 951, 'Characterized opportunity', '0.010001');
        $this->seedOpportunity($world, $lead, 952, 'Characterized second opportunity', '0.000001');
        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&selected='.$id)->assertOk()
            ->assertViewHas('openAmount', '0.010002')
            ->assertViewHas('selected', fn ($row): bool => $row?->id === $id && (int) $row->version === 0);
        self::assertSame(0, DB::table('crm_audit')->count());
        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'version' => 0]);
    }

    public function test_lead_option_pagination_retains_pipeline_filters_and_selected_opportunity(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        foreach (range(1, 27) as $number) {
            $this->seedLead($world, $this->fixtureId($number), 'Choice '.$number);
        }
        $selected = $this->seedOpportunity($world, $this->fixtureId(1), 901, 'Selected opportunity', '10', 'SAR', 'proposal', 80, '2026-10-15');
        $filters = ['locale' => 'en', 'currency' => 'SAR', 'stage' => 'proposal', 'probability_min' => '70', 'from' => '2026-10-01', 'to' => '2026-10-31', 'selected' => $selected];
        $response = $this->htmlAs($world['manager_id'], '/crm/pipeline?'.http_build_query($filters))->assertOk();
        $url = $response->viewData('leadPaginator')->url(2);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach ($filters as $key => $value) {
            self::assertSame($value, $query[$key] ?? null, 'Lost pipeline context: '.$key);
        }
        $this->htmlAs($world['manager_id'], $url)->assertOk()
            ->assertViewHas('selected', fn ($row): bool => $row?->id === $selected)
            ->assertViewHas('total', fn ($total): bool => $total === 1);
    }

    public function test_pipeline_rejects_invalid_filter_and_selection_shapes(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        foreach ([['probability_min' => 101], ['probability_min' => ['70']], ['from' => '2026-02-30'], ['to' => ['2026-10-31']], ['selected' => ['id']], ['selected' => str_repeat('x', 27)], ['lead_page' => 'invalid'], ['view' => 'unknown'], ['opportunity_page' => 'invalid'], ['opportunity_page' => 0]] as $invalid) {
            $this->htmlAs($world['manager_id'], '/crm/pipeline?'.http_build_query($invalid))->assertStatus(422);
        }
    }

    public function test_opportunity_directory_paginates_filtered_visible_rows_in_stable_order(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $readerId = $this->seedUser($world, 'Synthetic pipeline directory reader', ['crm.read']);
        $createdAt = '2026-10-19 09:00:00';
        $ids = [];

        foreach (range(1, 27) as $number) {
            $leadId = $this->fixtureId(2000 + $number);
            $this->seedLead($world, $leadId, 'Directory lead '.$number);
            DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $readerId]);
            $ids[] = $this->seedOpportunity(
                $world,
                $leadId,
                2000 + $number,
                'Directory Match '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                '10.00',
                'SAR',
                'proposal',
                40,
                '2026-10-31',
                $createdAt,
            );
        }

        $filteredLead = $this->fixtureId(2100);
        $this->seedLead($world, $filteredLead, 'Directory filtered lead');
        DB::table('crm_leads')->where('id', $filteredLead)->update(['assignee_id' => $readerId]);
        $this->seedOpportunity($world, $filteredLead, 2100, 'Directory excluded by search', '10.00', 'SAR', 'proposal', 40, '2026-10-31', $createdAt);

        $wrongStageLead = $this->fixtureId(2101);
        $this->seedLead($world, $wrongStageLead, 'Directory wrong-stage lead');
        DB::table('crm_leads')->where('id', $wrongStageLead)->update(['assignee_id' => $readerId]);
        $this->seedOpportunity($world, $wrongStageLead, 2101, 'Directory Match wrong stage', '10.00', 'SAR', 'lead', 40, '2026-10-31', $createdAt);

        $outsideScopeLead = $this->fixtureId(2102);
        $this->seedLead($world, $outsideScopeLead, 'Directory outside-scope lead');
        $this->seedOpportunity($world, $outsideScopeLead, 2102, 'Directory Match inaccessible', '10.00', 'SAR', 'proposal', 40, '2026-10-31', $createdAt);

        $filters = [
            'locale' => 'en',
            'view' => 'directory',
            'q' => 'Directory Match',
            'currency' => 'SAR',
            'stage' => 'proposal',
            'opportunity_page' => 2,
        ];
        $response = $this->htmlAs($readerId, '/crm/pipeline?'.http_build_query($filters))->assertOk();
        $expectedPageTwo = array_slice(array_reverse($ids), 25);

        $response->assertViewHas('directory', static fn (mixed $directory): bool => $directory instanceof LengthAwarePaginator
            && $directory->perPage() === 25
            && $directory->currentPage() === 2
            && $directory->total() === 27
            && collect($directory->items())->pluck('id')->map(static fn ($id): string => (string) $id)->all() === $expectedPageTwo);

        $url = $response->viewData('directory')->url(1);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach (['locale', 'view', 'q', 'currency', 'stage'] as $key) {
            self::assertSame($filters[$key], $query[$key] ?? null, 'Lost directory context: '.$key);
        }
        $response->assertSeeText('Directory Match 02')
            ->assertDontSeeText('Directory Match 27')
            ->assertDontSeeText('Directory excluded by search')
            ->assertDontSeeText('Directory Match wrong stage')
            ->assertDontSeeText('Directory Match inaccessible');
    }

    public function test_pipeline_renders_localized_scoped_totals_search_and_at_most_25_cards_per_stage(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(100);
        $this->seedLead($world, $leadId);

        $titles = [];
        foreach (range(1, 26) as $number) {
            $title = 'Pipeline proposal '.chr(64 + $number);
            $titles[] = $title;
            $this->seedOpportunity(
                $world,
                $leadId,
                $number,
                $title,
                '0.01',
                'SAR',
                'proposal',
                $number,
                null,
                '2020-01-01 00:00:'.sprintf('%02d', $number),
            );
        }
        $this->seedOpportunity($world, $leadId, 30, 'Currency-only opportunity', '5.00', 'USD');

        $outsideScope = $this->seedScope($world['company_id'], 'Pipeline Outside Scope');
        $outsideLeadId = $this->fixtureId(101);
        $this->seedLead($world, $outsideLeadId, 'Out of branch synthetic lead', $outsideScope['branch_id'], $outsideScope['department_id']);
        $this->seedOpportunity($world, $outsideLeadId, 32, 'Hidden out-of-scope opportunity', '75.00');

        $foreign = $this->seedWorld();
        $foreignLeadId = $this->fixtureId(200);
        $this->seedLead($foreign, $foreignLeadId);
        $this->seedOpportunity($foreign, $foreignLeadId, 31, 'Hidden foreign opportunity', '50.00', 'SAR');

        $english = $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en')->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('<main id="main-content"', false)
            ->assertSee('<h1', false)
            ->assertSeeText('26')
            ->assertSeeText('0.26')
            ->assertDontSeeText('Currency-only opportunity')
            ->assertDontSeeText('Hidden out-of-scope opportunity')
            ->assertDontSeeText('Hidden foreign opportunity');
        $englishBody = (string) $english->getContent();
        self::assertSame(1, substr_count($englishBody, '<h1'));
        self::assertCount(25, $this->visibleTitles($englishBody, $titles));

        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=ar')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('<h1', false);

        $search = $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&q='.urlencode('Pipeline proposal Z'))
            ->assertOk()
            ->assertSeeText('Pipeline proposal Z')
            ->assertDontSeeText('Pipeline proposal Y');

        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&currency=USD')
            ->assertOk()
            ->assertSeeText('Currency-only opportunity')
            ->assertDontSeeText('Pipeline proposal Z');

        $leadPage = $this->htmlAs($world['manager_id'], '/crm/leads/'.$leadId.'?locale=en')->assertOk();
        $identityHeader = $this->entityIdentityHeader((string) $leadPage->getContent());
        self::assertNotSame('', $identityHeader);
        self::assertStringContainsString($leadId, strip_tags($identityHeader));
    }

    public function test_pipeline_filters_by_stage_probability_and_expected_close_date_range(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(11);
        $this->seedLead($world, $leadId);

        $matchingTitles = ['At range start', 'At range end'];
        $this->seedOpportunity($world, $leadId, 110, $matchingTitles[0], '1.00', 'SAR', 'proposal', 75, '2026-10-01');
        $this->seedOpportunity($world, $leadId, 111, $matchingTitles[1], '2.00', 'SAR', 'proposal', 80, '2026-10-31');
        $this->seedOpportunity($world, $leadId, 112, 'Below minimum probability', '3.00', 'SAR', 'proposal', 74, '2026-10-15');
        $this->seedOpportunity($world, $leadId, 113, 'Other stage', '4.00', 'SAR', 'negotiation', 90, '2026-10-15');
        $this->seedOpportunity($world, $leadId, 114, 'Before date range', '5.00', 'SAR', 'proposal', 90, '2026-09-30');
        $this->seedOpportunity($world, $leadId, 115, 'After date range', '6.00', 'SAR', 'proposal', 90, '2026-11-01');

        $response = $this->htmlAs($world['manager_id'], '/crm/pipeline?'.http_build_query([
            'locale' => 'en',
            'stage' => 'proposal',
            'probability_min' => 75,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]))->assertOk()
            ->assertSee('name="stage"', false)
            ->assertSee('name="probability_min"', false)
            ->assertSee('name="from"', false)
            ->assertSee('name="to"', false);

        foreach ($matchingTitles as $title) {
            $response->assertSeeText($title);
        }
        foreach (['Below minimum probability', 'Other stage', 'Before date range', 'After date range'] as $title) {
            $response->assertDontSeeText($title);
        }
        $response->assertViewHas('total', static fn (int $total): bool => $total === 2)
            ->assertViewHas('columns', static fn (array $columns): bool => $columns['proposal']->total() === 2 && $columns['negotiation']->total() === 0);
        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&to=2026-10-31')->assertOk();
        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&from=2026-11-01&to=2026-10-31')->assertStatus(422);
    }

    public function test_closing_table_paginates_ten_rows_with_the_named_closing_page_parameter(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(12);
        $this->seedLead($world, $leadId);

        $ids = [];
        foreach (range(1, 12) as $number) {
            $ids[] = $this->seedOpportunity(
                $world,
                $leadId,
                120 + $number,
                'Scheduled closing '.$number,
                '10.00',
                'SAR',
                'proposal',
                60,
                now()->addDays($number)->toDateString(),
            );
        }

        $pageOne = $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en')->assertOk()
            ->assertViewHas('upcoming', static fn (mixed $upcoming): bool => $upcoming instanceof LengthAwarePaginator
                && $upcoming->perPage() === 10
                && $upcoming->currentPage() === 1
                && $upcoming->total() === 12
                && collect($upcoming->items())->pluck('id')->map(static fn ($id): string => (string) $id)->all() === array_slice($ids, 0, 10));
        $pageOne->assertSee('closing_page=2', false);

        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&closing_page=2')->assertOk()
            ->assertViewHas('upcoming', static fn (mixed $upcoming): bool => $upcoming instanceof LengthAwarePaginator
                && $upcoming->perPage() === 10
                && $upcoming->currentPage() === 2
                && $upcoming->total() === 12
                && collect($upcoming->items())->pluck('id')->map(static fn ($id): string => (string) $id)->all() === array_slice($ids, 10));
    }

    public function test_overdue_rows_are_bounded_and_stay_within_visible_open_leads(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(13);
        $this->seedLead($world, $leadId);

        $visibleOverdueIds = [];
        foreach (range(1, 12) as $number) {
            $visibleOverdueIds[] = $this->seedOpportunity(
                $world,
                $leadId,
                130 + $number,
                'Visible overdue '.$number,
                '10.00',
                'SAR',
                'proposal',
                60,
                now()->subDays($number)->toDateString(),
            );
        }
        $closedId = $this->seedOpportunity($world, $leadId, 143, 'Closed overdue', '10.00', 'SAR', 'closed', 100, now()->subDays(20)->toDateString());

        $outsideScope = $this->seedScope($world['company_id'], 'Pipeline Overdue Outside Scope');
        $outsideLeadId = $this->fixtureId(14);
        $this->seedLead($world, $outsideLeadId, 'Outside overdue lead', $outsideScope['branch_id'], $outsideScope['department_id']);
        $outsideId = $this->seedOpportunity($world, $outsideLeadId, 144, 'Out-of-scope overdue', '10.00', 'SAR', 'proposal', 60, now()->subDays(30)->toDateString());

        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en')->assertOk()
            ->assertViewHas('overdueRows', static function (mixed $rows) use ($visibleOverdueIds, $closedId, $outsideId): bool {
                if (! is_iterable($rows)) {
                    return false;
                }

                $ids = collect($rows)->pluck('id')->map(static fn ($id): string => (string) $id)->all();

                return count($ids) === 10
                    && array_diff($ids, $visibleOverdueIds) === []
                    && ! in_array($closedId, $ids, true)
                    && ! in_array($outsideId, $ids, true);
            });
    }

    public function test_create_stores_a_scoped_opportunity_with_the_exact_decimal_string(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(1);
        $this->seedLead($world, $leadId);

        $response = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayload($leadId))
            ->assertCreated()
            ->assertJsonPath('data.lead_id', $leadId)
            ->assertJsonPath('data.title', 'Synthetic pipeline opportunity')
            ->assertJsonPath('data.amount', '12345.670001')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.stage', 'lead')
            ->assertJsonPath('data.probability', 40)
            ->assertJsonPath('data.expected_close', '2026-10-31')
            ->assertJsonPath('data.version', 0);

        $opportunityId = (string) $response->json('data.id');
        self::assertNotSame('', $opportunityId);
        self::assertIsString($response->json('data.amount'));
        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $opportunityId,
            'company_id' => $world['company_id'],
            'lead_id' => $leadId,
            'created_by' => $world['manager_id'],
            'title' => 'Synthetic pipeline opportunity',
            'amount' => '12345.670001',
            'currency' => 'SAR',
            'stage' => 'lead',
            'probability' => 40,
            'expected_close' => '2026-10-31',
            'version' => 0,
        ]);
    }

    public function test_optional_work_type_and_private_requirements_persist_edit_reload_and_hash_with_submissions(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(2201);
        $this->seedLead($world, $leadId);
        $requirements = str_repeat('R', 4000);
        $payload = array_replace($this->opportunityPayload($leadId), [
            'work_type' => 'site_inspection',
            'requirements' => $requirements,
            'submission_key' => (string) Str::uuid(),
        ]);

        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)
            ->assertCreated()
            ->assertJsonPath('data.work_type', 'site_inspection')
            ->assertJsonMissingPath('data.requirements');
        $opportunityId = (string) $created->json('data.id');
        $row = DB::table('crm_opportunities')->where('id', $opportunityId)->first();
        self::assertNotSame($requirements, $row->requirements);
        self::assertSame($requirements, Crypt::decryptString((string) $row->requirements));

        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $opportunityId);
        self::assertSame(1, DB::table('crm_opportunities')->where('submission_key', $payload['submission_key'])->count());
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', array_replace($payload, ['requirements' => 'Changed requirements']))
            ->assertStatus(409);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', array_replace($payload, ['work_type' => 'maintenance']))
            ->assertStatus(409);

        $updatedRequirements = 'Edited private requirements';
        $update = array_diff_key($payload, ['lead_id' => true, 'submission_key' => true]);
        $update = array_replace($update, [
            'title' => 'Edited synthetic pipeline opportunity',
            'version' => 0,
            'work_type' => 'maintenance',
            'requirements' => $updatedRequirements,
        ]);
        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$opportunityId, $update)
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.work_type', 'maintenance')
            ->assertJsonMissingPath('data.requirements');

        $updatedRow = DB::table('crm_opportunities')->where('id', $opportunityId)->first();
        self::assertSame($updatedRequirements, Crypt::decryptString((string) $updatedRow->requirements));

        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&selected='.$opportunityId)->assertOk()
            ->assertViewHas('selectedContext', static fn (mixed $context): bool => data_get($context, 'requirements') === $updatedRequirements
                && data_get($context, 'work_type') === 'maintenance')
            ->assertViewHas('columns', static function (mixed $columns) use ($requirements, $updatedRequirements): bool {
                if (! is_array($columns)) {
                    return false;
                }
                foreach ($columns as $column) {
                    $items = (string) json_encode($column->items(), JSON_THROW_ON_ERROR);
                    if (str_contains($items, $requirements) || str_contains($items, $updatedRequirements)) {
                        return false;
                    }
                }

                return true;
            })
            ->assertViewHas('history', static function (mixed $history) use ($requirements, $updatedRequirements): bool {
                if (! $history instanceof LengthAwarePaginator) {
                    return false;
                }
                foreach ($history->items() as $entry) {
                    if (str_contains((string) $entry->snapshot, $requirements)
                        || str_contains((string) $entry->snapshot, $updatedRequirements)) {
                        return false;
                    }
                }

                return true;
            });
    }

    public function test_customer_only_create_uses_the_visible_converted_lead_and_retains_customer_identity(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $customerId = $this->seedCustomer($world, 'Synthetic canonical customer');
        $leadId = $this->fixtureId(2202);
        $this->seedLead($world, $leadId, 'Converted customer lead', customerId: $customerId);
        $customerCount = DB::table('crm_customers')->count();

        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayloadForCustomer($customerId))
            ->assertCreated()
            ->assertJsonPath('data.lead_id', $leadId);
        $opportunityId = (string) $created->json('data.id');
        self::assertSame($customerCount, DB::table('crm_customers')->count());
        $this->assertDatabaseHas('crm_opportunities', ['id' => $opportunityId, 'lead_id' => $leadId]);

        $update = array_replace(array_diff_key($this->opportunityPayload($leadId), ['lead_id' => true]), [
            'title' => 'Edited customer opportunity',
            'version' => 0,
        ]);
        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$opportunityId, $update)
            ->assertOk()
            ->assertJsonPath('data.lead_id', $leadId);
        $this->assertDatabaseHas('crm_opportunities', ['id' => $opportunityId, 'lead_id' => $leadId]);

        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&selected='.$opportunityId)->assertOk()
            ->assertViewHas('selectedContext', static fn (mixed $context): bool => data_get($context, 'customer_id') === $customerId);
        self::assertSame($customerCount, DB::table('crm_customers')->count());
    }

    public function test_invalid_html_submission_never_flashes_private_requirements(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(2210);
        $this->seedLead($world, $leadId);
        $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/crm/pipeline', array_replace($this->opportunityPayload($leadId), [
                'title' => '', 'requirements' => 'PRIVATE_REQUIREMENTS_SENTINEL',
            ]))->assertStatus(422)->assertSessionMissing('_old_input')
            ->assertDontSee('PRIVATE_REQUIREMENTS_SENTINEL');
        self::assertSame(0, DB::table('crm_opportunities')->count());
    }

    public function test_customer_only_create_fails_closed_for_unavailable_or_ambiguous_links(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();

        $unlinkedCustomerId = $this->seedCustomer($world, 'Synthetic unlinked customer');
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayloadForCustomer($unlinkedCustomerId))->assertNotFound();

        $archivedCustomerId = $this->seedCustomer($world, 'Synthetic archived customer');
        $archivedLeadId = $this->fixtureId(2203);
        $this->seedLead($world, $archivedLeadId, 'Archived customer lead', customerId: $archivedCustomerId);
        DB::table('crm_leads')->where('id', $archivedLeadId)->update(['archived' => true]);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayloadForCustomer($archivedCustomerId))->assertNotFound();

        $unassignedCustomerId = $this->seedCustomer($world, 'Synthetic unassigned customer');
        $unassignedLeadId = $this->fixtureId(2204);
        $this->seedLead($world, $unassignedLeadId, 'Unassigned customer lead', customerId: $unassignedCustomerId);
        $writerId = $this->seedUser($world, 'Synthetic scoped pipeline writer', ['crm.read', 'crm.write']);
        $this->postJsonAs($writerId, '/crm/pipeline', $this->opportunityPayloadForCustomer($unassignedCustomerId))->assertNotFound();

        $foreignWorld = $this->seedWorld();
        $foreignCustomerId = $this->seedCustomer($foreignWorld, 'Synthetic foreign customer');
        $foreignLeadId = $this->fixtureId(2205);
        $this->seedLead($foreignWorld, $foreignLeadId, 'Foreign customer lead', customerId: $foreignCustomerId);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayloadForCustomer($foreignCustomerId))->assertNotFound();

        $ambiguousCustomerId = $this->seedCustomer($world, 'Synthetic ambiguous customer');
        foreach ([2206, 2207] as $number) {
            $this->seedLead($world, $this->fixtureId($number), 'Multiple active customer lead '.$number, customerId: $ambiguousCustomerId);
        }
        $customerCount = DB::table('crm_customers')->count();
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayloadForCustomer($ambiguousCustomerId))->assertStatus(409);
        $this->htmlAs($world['manager_id'], '/crm/pipeline?source=customer')->assertOk()
            ->assertViewHas('leadOptions', static fn (array $options): bool => ! in_array($ambiguousCustomerId, array_column($options, 'customer_id'), true));

        self::assertSame($customerCount, DB::table('crm_customers')->count());
        self::assertSame(0, DB::table('crm_opportunities')->count());
    }

    public function test_edit_requires_version_rejects_stale_edits_and_keeps_the_lead_immutable(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(2);
        $foreignLeadId = $this->fixtureId(3);
        $this->seedLead($world, $leadId);
        $this->seedLead($world, $foreignLeadId, 'Other Synthetic Lead');
        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayload($leadId))
            ->assertCreated();
        $opportunityId = (string) $created->json('data.id');
        $url = '/crm/pipeline/'.$opportunityId;

        $update = array_replace(array_diff_key($this->opportunityPayload($leadId), ['lead_id' => true]), [
            'title' => 'Updated synthetic pipeline opportunity',
            'amount' => '8.125001',
            'stage' => 'lead',
            'probability' => 70,
            'expected_close' => null,
            'version' => 0,
        ]);
        $this->patchJsonAs($world['manager_id'], $url, $update)
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.lead_id', $leadId)
            ->assertJsonPath('data.amount', '8.125001');

        $this->patchJsonAs($world['manager_id'], $url, array_replace($update, ['version' => 0, 'title' => 'Stale pipeline edit']))
            ->assertStatus(409);
        $withoutVersion = $update;
        unset($withoutVersion['version']);
        $this->patchJsonAs($world['manager_id'], $url, $withoutVersion)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('version');

        $attemptedMove = array_replace($update, ['version' => 1, 'lead_id' => $foreignLeadId]);
        $this->patchJsonAs($world['manager_id'], $url, $attemptedMove)->assertUnprocessable();
        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $opportunityId,
            'lead_id' => $leadId,
            'title' => 'Updated synthetic pipeline opportunity',
            'version' => 1,
        ]);
    }

    public function test_reads_and_mutations_use_existing_crm_permissions_and_visible_lead_scope(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(4);
        $this->seedLead($world, $leadId);
        $opportunityId = $this->seedOpportunity($world, $leadId, 40, 'Assigned synthetic opportunity', '15.00');

        $readerId = $this->seedUser($world, 'Synthetic pipeline reader', ['crm.read']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $readerId]);
        $this->htmlAs($readerId, '/crm/pipeline?locale=en')->assertOk()->assertSeeText('Assigned synthetic opportunity');
        $this->postJsonAs($readerId, '/crm/pipeline', $this->opportunityPayload($leadId))->assertForbidden();
        $this->patchJsonAs($readerId, '/crm/pipeline/'.$opportunityId, array_replace($this->opportunityPayload($leadId), ['version' => 0]))
            ->assertForbidden();

        $writeOnlyId = $this->seedUser($world, 'Synthetic pipeline write-only user', ['crm.write']);
        $this->postJsonAs($writeOnlyId, '/crm/pipeline', $this->opportunityPayload($leadId))->assertForbidden();

        $unassignedWriterId = $this->seedUser($world, 'Synthetic unassigned pipeline writer', ['crm.read', 'crm.write']);
        $this->postJsonAs($unassignedWriterId, '/crm/pipeline', $this->opportunityPayload($leadId))->assertNotFound();

        $foreignWorld = $this->seedWorld();
        $foreignLeadId = $this->fixtureId(5);
        $this->seedLead($foreignWorld, $foreignLeadId);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayload($foreignLeadId))->assertNotFound();
    }

    public function test_invalid_pipeline_fields_and_disabled_or_production_switch_do_not_write(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(6);
        $this->seedLead($world, $leadId);
        $payload = $this->opportunityPayload($leadId);

        config(['sales.synthetic_drafts' => false]);
        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en')->assertNotFound();
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertNotFound();

        config(['sales.synthetic_drafts' => true]);
        $environment = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en')->assertNotFound();
            $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertNotFound();
        } finally {
            $this->app['env'] = $environment;
        }

        foreach ([
            ['amount' => 12.5],
            ['amount' => '-1'],
            ['amount' => '1.1234567'],
            ['amount' => '1000000000000'],
            ['currency' => 'sar'],
            ['stage' => 'sent'],
            ['probability' => 101],
            ['expected_close' => '2026-02-30'],
            ['title' => str_repeat('x', 201)],
            ['status' => 'approved'],
            ['work_type' => 'unrecognized-work-type'],
            ['requirements' => str_repeat('x', 4001)],
        ] as $invalid) {
            $this->postJsonAs($world['manager_id'], '/crm/pipeline', array_replace($payload, $invalid))
                ->assertUnprocessable();
        }

        $customerId = $this->seedCustomer($world, 'Synthetic validation customer');
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', array_replace($payload, ['customer_id' => $customerId]))
            ->assertUnprocessable();
        $withoutSource = $payload;
        unset($withoutSource['lead_id']);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $withoutSource)->assertUnprocessable();

        self::assertSame(0, DB::table('crm_opportunities')->count());
    }

    /** @return array{lead_id: string, title: string, amount: string, currency: string, stage: string, probability: int, expected_close: string} */
    private function opportunityPayload(string $leadId): array
    {
        return [
            'lead_id' => $leadId,
            'title' => 'Synthetic pipeline opportunity',
            'amount' => '12345.670001',
            'currency' => 'SAR',
            'stage' => 'lead',
            'probability' => 40,
            'expected_close' => '2026-10-31',
        ];
    }

    /** @return array{customer_id: string, title: string, amount: string, currency: string, stage: string, probability: int, expected_close: string} */
    private function opportunityPayloadForCustomer(string $customerId): array
    {
        $payload = $this->opportunityPayload($this->fixtureId(1));
        unset($payload['lead_id']);

        return ['customer_id' => $customerId] + $payload;
    }

    /** @param list<string> $titles
     * @return list<string>
     */
    private function visibleTitles(string $html, array $titles): array
    {
        return array_values(array_filter($titles, static fn (string $title): bool => str_contains($html, $title)));
    }

    private function entityIdentityHeader(string $html): string
    {
        preg_match('/<header\b[^>]*class="[^"]*\bcrm-reference-identity\b[^"]*"[^>]*>.*?<\/header>/s', $html, $matches);

        return $matches[0] ?? '';
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function seedLead(
        array $world,
        string $id,
        string $name = 'Synthetic Pipeline Lead',
        ?string $branchId = null,
        ?string $departmentId = null,
        ?string $customerId = null,
    ): void {
        DB::table('crm_leads')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $branchId ?? $world['branch_id'],
            'department_id' => $departmentId ?? $world['department_id'],
            'name' => $name,
            'organization' => 'Synthetic Organization '.$name,
            'source' => 'website',
            'contacts' => Crypt::encryptString(json_encode(['phone' => null, 'email' => null, 'whatsapp' => null], JSON_THROW_ON_ERROR)),
            'duplicate_key' => null,
            'consent' => false,
            'preferences' => '{}',
            'notes' => null,
            'stage_id' => null,
            'assignee_id' => null,
            'customer_id' => $customerId,
            'archived' => false,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function seedCustomer(array $world, string $name): string
    {
        $customerId = (string) Str::ulid();
        DB::table('crm_customers')->insert([
            'id' => $customerId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'organization' => $name.' Organization',
            'duplicate_key' => null,
            'created_at' => now()->toDateTimeString(),
        ]);

        return $customerId;
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function seedOpportunity(
        array $world,
        string $leadId,
        int $number,
        string $title,
        string $amount,
        string $currency = 'SAR',
        string $stage = 'proposal',
        int $probability = 40,
        ?string $expectedClose = null,
        ?string $createdAt = null,
    ): string {
        $id = $this->fixtureId(1000 + $number);
        DB::table('crm_opportunities')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'lead_id' => $leadId,
            'created_by' => $world['manager_id'],
            'title' => $title,
            'amount' => $amount,
            'currency' => $currency,
            'stage' => $stage,
            'probability' => $probability,
            'expected_close' => $expectedClose,
            'version' => 0,
            'created_at' => $createdAt ?? now()->toDateTimeString(),
            'updated_at' => $createdAt ?? now()->toDateTimeString(),
        ]);

        return $id;
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, manager_id: string} */
    private function seedWorld(): array
    {
        $companyId = (string) Str::ulid();
        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic CRM Pipeline Company', 'version' => 0]);
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM Pipeline Branch',
            'code' => 'pipeline-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Synthetic CRM Pipeline Department',
            'code' => 'pipeline-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        $world = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
        ];

        return $world + ['manager_id' => $this->seedUser($world, 'Synthetic CRM Pipeline Manager', ['crm.read', 'crm.manage', 'crm.write'])];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $world
     * @param  list<string>  $permissions
     */
    private function seedUser(array $world, string $name, array $permissions): string
    {
        $userId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $world['company_id'],
            'name' => 'Synthetic CRM Pipeline '.$name.' Role',
            'version' => 0,
        ]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'email' => 'crm-pipeline-'.strtolower(substr($userId, -8)).'@example.test',
            'password' => Hash::make('unused-synthetic-pipeline-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    }

    /** @return array{branch_id: string, department_id: string} */
    private function seedScope(string $companyId, string $name): array
    {
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => $name,
            'code' => 'pipeline-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $name.' Department',
            'code' => 'pipeline-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);

        return ['branch_id' => $branchId, 'department_id' => $departmentId];
    }

    /** @return array{access_user_id: string, access_session_version: int} */
    private function sessionFor(string $userId): array
    {
        return [
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ];
    }

    private function fixtureId(int $number): string
    {
        return sprintf('01ARASYA%018d', $number);
    }

    private function htmlAs(string $userId, string $uri): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))
            ->withHeaders(['Accept' => 'text/html'])
            ->get($uri);
    }

    /** @param array<string, mixed> $payload */
    private function postJsonAs(string $userId, string $uri, array $payload): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))->postJson($uri, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function patchJsonAs(string $userId, string $uri, array $payload): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))->patchJson($uri, $payload);
    }
}
