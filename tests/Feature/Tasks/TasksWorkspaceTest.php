<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class TasksWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_detail_keeps_one_heading_and_scoped_evidence_actions(): void
    {
        $id = $this->seedTask('Reference task detail', '2026-10-20 15:00:00', 'in_progress');
        $response = $this->withSession($this->actorSession())->get('/tasks/'.$id.'?locale=en');
        $response->assertOk()->assertSeeText('Task details')->assertSeeText('Task progress');
        $xpath = $this->htmlXPath($response);
        self::assertSame(1, $xpath->query('//h1')->length);
        self::assertSame(1, $xpath->query('//*[@data-testid="task-detail-summary"]')->length);
        self::assertSame(1, $xpath->query('//input[@type="file" and @accept="application/pdf,image/png,image/jpeg,text/plain"]')->length);
        self::assertSame(1, $xpath->query('//form[contains(@action,"/complete")]//button[@disabled]')->length);
        self::assertSame(1, $xpath->query('//*[@aria-current="step"]')->length);

        $readOnly = $this->withSession($this->actorSession(['tasks.read']))->get('/tasks/'.$id.'?locale=en');
        $readOnly->assertOk()->assertSeeText('Task details');
        self::assertSame(0, $this->htmlXPath($readOnly)->query('//main//form')->length);
        self::assertSame('in_progress', DB::table('tasks_cycle')->where('id', $id)->value('status'));
    }

    public function test_html_workspace_uses_the_exact_authorized_scope_for_rows_and_summaries(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));

        $today = $this->seedTask('Assigned today', '2026-10-20 15:00:00');
        $completed = $this->seedTask('Completed in scope', '2026-10-19 17:00:00', 'completed');
        $this->seedCompletionAudit($completed, '2026-10-20 11:00:00');
        $this->seedTask('Other entity', '2026-10-20 13:00:00', 'pending', [
            'entity_id' => 'entity-elsewhere',
        ]);
        $this->seedTask('Other department', '2026-10-20 13:00:00', 'pending', [
            'department_id' => 'department-elsewhere',
        ]);
        $this->seedTask('Other owner and assignee', '2026-10-20 13:00:00', 'pending', [
            'owner_id' => 'actor-elsewhere',
            'assignee_id' => 'actor-elsewhere',
        ]);

        $response = $this->withSession($this->actorSession())->get('/tasks?locale=ar');

        $response->assertOk()
            ->assertSeeText('مهامي اليومية')
            ->assertSeeText('Assigned today')
            ->assertSeeText('Completed in scope')
            ->assertDontSeeText('Other entity')
            ->assertDontSeeText('Other department')
            ->assertDontSeeText('Other owner and assignee');

        self::assertSame([
            'إجمالي المهام' => '2',
            'تستحق اليوم' => '1',
            'متأخرة' => '0',
            'مكتملة هذا الأسبوع' => '1',
            'إنجاز مهام الأسبوع' => '50%',
        ], $this->summaryValues($response));

        self::assertSame(5, DB::table('tasks_cycle')->count(), 'GET /tasks must not alter any seeded task.');
    }

    public function test_html_filters_search_status_priority_and_inclusive_due_range_without_filtering_summaries(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
        $this->seedTask('Alpha target', '2026-10-21 10:00:00', 'pending', ['priority' => 'high']);
        $this->seedTask('Alpha completed', '2026-10-21 10:00:00', 'completed', ['priority' => 'high']);
        $this->seedTask('Alpha low', '2026-10-21 10:00:00', 'pending', ['priority' => 'low']);
        $this->seedTask('Alpha before range', '2026-10-19 23:59:59', 'pending', ['priority' => 'high']);
        $this->seedTask('Beta high', '2026-10-21 10:00:00', 'pending', ['priority' => 'high']);

        $response = $this->withSession($this->actorSession())->get('/tasks?locale=en&q=Alpha&status=pending&priority=high&due_from=2026-10-20&due_to=2026-10-21&sort=title&direction=asc&per_page=10');

        $response->assertOk();
        self::assertSame(['Alpha target'], $this->tableTaskTitles($response));
        self::assertSame('5', $this->summaryValues($response)['Total tasks']);
    }

    public function test_due_presets_include_only_open_work_at_half_open_day_and_upcoming_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
        $this->seedTask('Overdue active', '2026-10-19 23:59:59');
        $this->seedTask('Overdue completed', '2026-10-19 23:59:59', 'completed');
        $this->seedTask('Overdue cancelled', '2026-10-19 23:59:59', 'cancelled');
        $this->seedTask('Today at start', '2026-10-20 00:00:00');
        $this->seedTask('Today at end', '2026-10-20 23:59:59', 'in_progress');
        $this->seedTask('Today completed', '2026-10-20 12:00:00', 'completed');
        $this->seedTask('Upcoming at start', '2026-10-21 00:00:00');
        $this->seedTask('Upcoming last second', '2026-10-27 23:59:59', 'in_progress');
        $this->seedTask('Outside upcoming', '2026-10-28 00:00:00');

        $overdue = $this->withSession($this->actorSession())->get('/tasks?locale=en&due_preset=overdue');
        $overdue->assertOk();
        self::assertSame(['Overdue active'], $this->tableTaskTitles($overdue));

        $today = $this->withSession($this->actorSession())->get('/tasks?locale=en&due_preset=today');
        $today->assertOk();
        self::assertSame(['Today at start', 'Today at end'], $this->tableTaskTitles($today));
        self::assertSame('2', $this->summaryValues($today)['Due today']);
        self::assertSame('1', $this->summaryValues($today)['Overdue']);

        $upcoming = $this->withSession($this->actorSession())->get('/tasks?locale=en&due_preset=upcoming');
        $upcoming->assertOk();
        self::assertSame(['Upcoming at start', 'Upcoming last second'], $this->tableTaskTitles($upcoming));
    }

    public function test_week_completion_uses_audit_events_and_monday_to_monday_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
        $completedInsideWeek = $this->seedTask('Completed Sunday', '2026-10-25 23:59:59', 'completed');
        $this->seedCompletionAudit($completedInsideWeek, '2026-10-25 23:59:59');
        $completedNextWeek = $this->seedTask('Completed next Monday', '2026-10-26 00:00:00', 'completed');
        $this->seedCompletionAudit($completedNextWeek, '2026-10-26 00:00:00');
        $this->seedTask('Pending this week', '2026-10-20 15:00:00');
        $this->seedTask('Cancelled this week', '2026-10-20 16:00:00', 'cancelled');

        $response = $this->withSession($this->actorSession())->get('/tasks?locale=en&status=pending');
        $values = $this->summaryValues($response);

        self::assertSame('4', $values['Total tasks']);
        self::assertSame('1', $values['Completed this week']);
        self::assertSame('50%', $values['Weekly completion']);
    }

    public function test_invalid_filters_are_rejected_but_due_to_without_due_from_is_valid(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
        $this->seedTask('Date range task', '2026-10-20 10:00:00');
        $dueToOnly = $this->withSession($this->actorSession())->get('/tasks?locale=en&due_to=2026-10-20');
        $dueToOnly->assertOk()->assertSeeText('Date range task');

        $invalidInputs = [
            'status' => 'status=invalid',
            'priority' => 'priority=urgent',
            'due_preset' => 'due_preset=tomorrow',
            'sort' => 'sort=owner_id',
            'direction' => 'direction=sideways',
            'per_page' => 'per_page=100',
            'page' => 'page=0',
            'due_to' => 'due_from=2026-10-21&due_to=2026-10-20',
            'due_from' => 'due_from=not-a-date',
        ];

        foreach ($invalidInputs as $field => $query) {
            $this->from('/tasks')->withSession($this->actorSession())
                ->get('/tasks?locale=ar&'.$query)
                ->assertSessionHasErrors($field);
        }
    }

    public function test_pagination_is_bounded_stable_and_preserves_filters_while_json_stays_unbounded(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
        for ($index = 1; $index <= 23; $index++) {
            $this->seedTask(sprintf('Paged %02d', $index), '2026-10-20 15:00:00');
        }

        $query = 'locale=en&q=Paged&status=pending&priority=normal&due_preset=today&due_from=2026-10-20&due_to=2026-10-20&per_page=10&sort=title&direction=asc&page=2';
        $response = $this->withSession($this->actorSession())->get('/tasks?'.$query);
        $response->assertOk()->assertSeeText('Paged 11')->assertSeeText('Paged 20')->assertDontSeeText('Paged 09');
        $xpath = $this->htmlXPath($response);
        self::assertSame(10, $xpath->query('//*[@data-testid="tasks-table"]//tr[@data-task-id]')->length);
        foreach (['q=Paged', 'locale=en', 'per_page=10', 'status=pending', 'due_preset=today', 'sort=title', 'direction=asc'] as $persisted) {
            self::assertStringContainsString($persisted, $response->getContent());
        }

        $json = $this->withSession($this->actorSession())->getJson('/tasks?status=completed&per_page=1&page=1');
        $json->assertOk()->assertJsonCount(23, 'data')->assertJsonMissingPath('meta');
    }

    public function test_empty_state_and_read_only_english_view_are_honest_and_permission_gated(): void
    {
        $empty = $this->withSession($this->actorSession())->get('/tasks?locale=ar');
        $empty->assertOk()->assertSeeText('لا توجد مهام بعد')->assertSee('data-testid="tasks-create-action"', false);
        self::assertSame('—', $this->summaryValues($empty)['إنجاز مهام الأسبوع']);

        $this->seedTask('Read-only task', '2026-10-21 09:00:00');
        $readOnly = $this->withSession($this->actorSession(['tasks.read']))->get('/tasks?locale=en');
        $readOnly->assertOk()->assertSeeText('My daily tasks')->assertSeeText('Read-only task')
            ->assertSee('lang="en" dir="ltr"', false)
            ->assertDontSee('data-testid="tasks-create-action"', false);
        self::assertSame(1, $this->htmlXPath($readOnly)->query('//h1')->length);
    }

    public function test_today_upcoming_and_evidence_panels_are_bounded_and_scoped(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
        $evidenceTask = null;
        for ($index = 1; $index <= 7; $index++) {
            $id = $this->seedTask(sprintf('Today work %02d', $index), '2026-10-20 '.sprintf('%02d', $index).':00:00');
            $evidenceTask ??= $id;
            $this->seedTask(sprintf('Upcoming work %02d', $index), '2026-10-21 '.sprintf('%02d', $index).':00:00');
        }
        $this->seedEvidence($evidenceTask, 'visible-proof.txt');
        $foreign = $this->seedTask('Foreign evidence task', '2026-10-20 10:00:00', 'pending', [
            'owner_id' => 'actor-elsewhere',
            'assignee_id' => 'actor-elsewhere',
        ]);
        $this->seedEvidence($foreign, 'foreign-proof.txt');

        $response = $this->withSession($this->actorSession())->get('/tasks?locale=en');
        $response->assertOk()->assertSeeText('visible-proof.txt')->assertDontSeeText('foreign-proof.txt');
        $xpath = $this->htmlXPath($response);
        self::assertSame(5, $xpath->query('//*[@data-testid="tasks-today-list"]/li')->length);
        self::assertSame(5, $xpath->query('//*[@data-testid="tasks-upcoming-list"]/li')->length);
    }

    public function test_owned_and_assigned_records_are_visible_but_unrelated_records_are_not(): void
    {
        $this->seedTask('Owned task', '2026-10-20 15:00:00', 'pending', ['assignee_id' => 'different-assignee']);
        $this->seedTask('Assigned task', '2026-10-20 15:00:00', 'pending', ['owner_id' => 'different-owner']);
        $this->seedTask('Out of actor scope', '2026-10-20 15:00:00', 'pending', [
            'owner_id' => 'different-owner',
            'assignee_id' => 'different-assignee',
        ]);

        $response = $this->withSession($this->actorSession())->get('/tasks?locale=en');
        $response->assertOk()->assertSeeText('Owned task')->assertSeeText('Assigned task')
            ->assertDontSeeText('Out of actor scope');
        self::assertSame('2', $this->summaryValues($response)['Total tasks']);
    }

    public function test_whitespace_only_identifiers_are_excluded_from_workspace_and_legacy_json(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
        $owned = $this->seedTask('Valid owned task', '2026-10-20 10:00:00', 'pending', [
            'assignee_id' => 'different-assignee',
        ]);
        $assigned = $this->seedTask('Valid assigned task', '2026-10-21 10:00:00', 'pending', [
            'owner_id' => 'different-owner',
        ]);
        $blankOwner = $this->seedTask('Whitespace owner task', '2026-10-20 11:00:00', 'pending', [
            'owner_id' => " \t\n\r\0\v",
            'assignee_id' => 'actor-1',
        ]);
        $blankAssignee = $this->seedTask('Whitespace assignee task', '2026-10-20 12:00:00', 'pending', [
            'owner_id' => 'actor-1',
            'assignee_id' => " \t\n\r\0\v",
        ]);
        $blankId = $this->seedTask('Whitespace id task', '2026-10-21 12:00:00', 'pending', [
            'id' => str_repeat("\t", 26),
        ]);
        $this->seedEvidence($owned, 'valid-owned-proof.txt');
        $this->seedEvidence($blankOwner, 'blank-owner-proof.txt');
        $this->seedEvidence($blankAssignee, 'blank-assignee-proof.txt');
        $this->seedEvidence($blankId, 'blank-id-proof.txt');

        $html = $this->withSession($this->actorSession())->get('/tasks?locale=en');
        $html->assertOk()->assertSeeText('Valid owned task')->assertSeeText('Valid assigned task')
            ->assertSeeText('valid-owned-proof.txt')
            ->assertDontSeeText('Whitespace owner task')->assertDontSeeText('Whitespace assignee task')
            ->assertDontSeeText('Whitespace id task')->assertDontSeeText('blank-owner-proof.txt')
            ->assertDontSeeText('blank-assignee-proof.txt')->assertDontSeeText('blank-id-proof.txt');
        self::assertSame('2', $this->summaryValues($html)['Total tasks']);
        $xpath = $this->htmlXPath($html);
        self::assertSame(1, $xpath->query('//*[@data-testid="tasks-today-list"]/li')->length);
        self::assertSame(1, $xpath->query('//*[@data-testid="tasks-upcoming-list"]/li')->length);
        self::assertSame(1, $xpath->query('//*[@data-testid="tasks-evidence"]//li')->length);

        $json = $this->withSession($this->actorSession())->getJson('/tasks');
        $json->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Valid owned task'])
            ->assertJsonFragment(['title' => 'Valid assigned task'])
            ->assertJsonMissing(['title' => 'Whitespace owner task'])
            ->assertJsonMissing(['title' => 'Whitespace assignee task'])
            ->assertJsonMissing(['title' => 'Whitespace id task']);
    }

    /** @param array<string, string> $overrides */
    private function seedTask(string $title, ?string $dueAt, string $status = 'pending', array $overrides = []): string
    {
        $id = (string) ($overrides['id'] ?? Str::ulid());
        $timestamp = now()->format('Y-m-d H:i:s');

        DB::table('tasks_cycle')->insert(array_merge([
            'id' => $id,
            'entity_id' => 'entity-1',
            'department_id' => 'department-1',
            'owner_id' => 'actor-1',
            'assignee_id' => 'actor-1',
            'title' => $title,
            'priority' => 'normal',
            'due_at' => $dueAt,
            'status' => $status,
            'version' => 0,
            'create_key_hash' => hash('sha256', 'create-'.$id),
            'create_payload_hash' => hash('sha256', 'payload-'.$id),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $overrides));

        return $id;
    }

    private function seedEvidence(string $taskId, string $name): void
    {
        $evidenceId = (string) Str::ulid();
        DB::table('tasks_cycle_evidence')->insert([
            'id' => $evidenceId,
            'task_id' => $taskId,
            'path' => 'task-evidence/'.$taskId.'/'.$evidenceId.'.txt',
            'name' => $name,
            'mime' => 'text/plain',
            'size' => 1,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function seedCompletionAudit(string $taskId, string $createdAt): void
    {
        DB::table('tasks_cycle_audit')->insert([
            'id' => (string) Str::ulid(),
            'task_id' => $taskId,
            'actor_id' => 'actor-1',
            'action' => 'complete',
            'request_key' => 'complete-'.$taskId,
            'payload_hash' => hash('sha256', 'complete-'.$taskId),
            'receipt_id' => (string) Str::ulid(),
            'payload' => '{}',
            'created_at' => $createdAt,
        ]);
    }

    /** @return list<string> */
    private function tableTaskTitles(TestResponse $response): array
    {
        $rows = $this->htmlXPath($response)->query('//*[@data-testid="tasks-table"]//tbody/tr[@data-task-id]/th[@scope="row"]');

        return array_map(static fn ($row): string => trim($row->textContent), iterator_to_array($rows ?: []));
    }

    /** @return array<string, string> */
    private function summaryValues(TestResponse $response): array
    {
        $xpath = $this->htmlXPath($response);
        $items = $xpath->query('//*[@data-testid="tasks-summary"]//*[contains(concat(" ", normalize-space(@class), " "), " admin-summary__item ")]');
        $values = [];

        foreach ($items ?: [] as $item) {
            $label = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ui-stat-card__label ")]', $item)?->item(0);
            $value = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ui-stat-card__value ")]', $item)?->item(0);
            if ($label !== null && $value !== null) {
                $values[trim($label->textContent)] = trim($value->textContent);
            }
        }

        return $values;
    }

    private function htmlXPath(TestResponse $response): DOMXPath
    {
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    /** @param list<string> $permissions
     * @return array<string, mixed>
     */
    private function actorSession(array $permissions = ['tasks.read', 'tasks.write']): array
    {
        return [
            'actor_id' => 'actor-1',
            'entity_id' => 'entity-1',
            'department_id' => 'department-1',
            'permissions' => $permissions,
        ];
    }
}
