<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Runtime;

use App\Modules\CRM\Application\CRM05\HandoverLead;
use App\Modules\CRM\Domain\CRM01\LeadLifecycle;
use App\Modules\CRM\Domain\CRM01\LeadRecord;
use App\Modules\CRM\Domain\CRM04\LeadActivity;
use App\Modules\CRM\Domain\CRM05\LeadHandover;
use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Tasks\Application\TASK01\TaskCycle;
use App\Shared\Outbox\OutboxEvent;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class LeadDirectory extends LeadRuntime
{
    public const OPPORTUNITY_STAGES = LeadOpportunities::OPPORTUNITY_STAGES;

    /** @return array<string, mixed> */
    public function pipeline(Request $request): array
    {
        return $this->leadOpportunities->pipeline($request);
    }

    /** @return array<string, mixed> */
    public function saveOpportunity(Request $request, ?string $id = null): array
    {
        return $this->leadOpportunities->saveOpportunity($request, $id);
    }

    public function __construct(
        AccessControl $access,
        private LeadLifecycle $lifecycle,
        private TaskCycle $tasks,
        private RuntimeIdentity $identity,
        private LeadFiles $leadFiles,
        private LeadOpportunities $leadOpportunities,
    ) {
        parent::__construct($access);
    }

    /** @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listing(Request $request, array $filters = []): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($filters): array {
            return array_values($this->leadQuery($actor, $filters)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->get($this->listColumns())
                ->map(fn (stdClass $row): array => $this->presentLead($row))->all());
        });
    }

    /**
     * @return array{
     *     active_total: int,
     *     assigned_count: int,
     *     converted_count: int,
     *     pending_transfers_count: int,
     *     source_counts: list<array{source: string, count: int}>,
     *     stage_counts: list<array{stage_id: ?string, stage: ?string, count: int}>,
     *     recent_activities: list<array{id: string, lead_id: string, lead_name: string, kind: string, outcome_label: string, occurred_at: string}>,
     *     upcoming_followups: list<array{id: string, lead_id: string, lead_name: string, next_step: string, due_at: string}>
     * }
     */
    public function overview(Request $request): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor): array {
            $activeLeads = $this->visibleLeads($actor)->where('archived', false);
            $visibleActiveLeadIds = (clone $activeLeads)->select('id');

            $sourceCounts = (clone $activeLeads)
                ->select('source', DB::raw('COUNT(*) as count'))
                ->groupBy('source')->orderByDesc('count')->orderBy('source')->limit(10)->get()
                ->map(static fn (stdClass $row): array => ['source' => (string) $row->source, 'count' => (int) $row->count])->all();
            $stageCounts = (clone $activeLeads)
                ->leftJoin('crm_taxonomy as stages', function (JoinClause $join): void {
                    $join->on('stages.id', '=', 'crm_leads.stage_id')
                        ->on('stages.company_id', '=', 'crm_leads.company_id')
                        ->where('stages.kind', 'stage');
                })
                ->select('crm_leads.stage_id', 'stages.label as stage', DB::raw('COUNT(*) as count'))
                ->groupBy('crm_leads.stage_id', 'stages.label')->orderByDesc('count')->orderBy('stages.label')->orderBy('crm_leads.stage_id')->limit(10)->get()
                ->map(static fn (stdClass $row): array => [
                    'stage_id' => $row->stage_id === null ? null : (string) $row->stage_id,
                    'stage' => $row->stage === null ? null : (string) $row->stage,
                    'count' => (int) $row->count,
                ])->all();

            $pendingTransfersCount = DB::table('crm_transfers as transfers')
                ->join('crm_leads as leads', function (JoinClause $join): void {
                    $join->on('leads.id', '=', 'transfers.lead_id')->on('leads.company_id', '=', 'transfers.company_id');
                })
                ->where('transfers.company_id', $actor['company_id'])
                ->where('leads.company_id', $actor['company_id'])
                ->where('leads.branch_id', $actor['branch_id'])
                ->where('leads.department_id', $actor['department_id'])
                ->where('leads.archived', false)
                ->where('transfers.state', 'pending')
                ->where(function (Builder $query) use ($actor, $visibleActiveLeadIds): void {
                    $query->where('transfers.recipient_id', $actor['id'])
                        ->orWhereIn('transfers.lead_id', $visibleActiveLeadIds);
                })->count();

            $recentActivities = DB::table('crm_activities as activities')
                ->join('crm_leads as leads', function (JoinClause $join): void {
                    $join->on('leads.id', '=', 'activities.lead_id')->on('leads.company_id', '=', 'activities.company_id');
                })
                ->where('activities.company_id', $actor['company_id'])
                ->whereIn('activities.lead_id', $visibleActiveLeadIds)
                ->orderByDesc('activities.occurred_at')->orderByDesc('activities.id')->limit(10)
                ->get(['activities.id', 'activities.lead_id', 'leads.name as lead_name', 'activities.kind', 'activities.outcome_label', 'activities.occurred_at'])
                ->map(static fn (stdClass $row): array => [
                    'id' => (string) $row->id,
                    'lead_id' => (string) $row->lead_id,
                    'lead_name' => (string) $row->lead_name,
                    'kind' => (string) $row->kind,
                    'outcome_label' => (string) $row->outcome_label,
                    'occurred_at' => (string) $row->occurred_at,
                ])->all();

            $upcomingFollowups = DB::table('crm_activities as activities')
                ->join('crm_leads as leads', function (JoinClause $join): void {
                    $join->on('leads.id', '=', 'activities.lead_id')->on('leads.company_id', '=', 'activities.company_id');
                })
                ->where('activities.company_id', $actor['company_id'])
                ->whereIn('activities.lead_id', $visibleActiveLeadIds)
                ->whereNotNull('activities.next_follow_up')->where('activities.next_follow_up', '>=', now())
                ->orderBy('activities.next_follow_up')->orderBy('activities.id')->limit(10)
                ->get(['activities.id', 'activities.lead_id', 'leads.name as lead_name', 'activities.next_step', 'activities.next_follow_up'])
                ->map(static fn (stdClass $row): array => [
                    'id' => (string) $row->id,
                    'lead_id' => (string) $row->lead_id,
                    'lead_name' => (string) $row->lead_name,
                    'next_step' => (string) $row->next_step,
                    'due_at' => (string) $row->next_follow_up,
                ])->all();

            return [
                'active_total' => (clone $activeLeads)->count(),
                'assigned_count' => (clone $activeLeads)->whereNotNull('assignee_id')->count(),
                'converted_count' => (clone $activeLeads)->whereNotNull('customer_id')->count(),
                'pending_transfers_count' => $pendingTransfersCount,
                'source_counts' => $sourceCounts,
                'stage_counts' => $stageCounts,
                'recent_activities' => $recentActivities,
                'upcoming_followups' => $upcomingFollowups,
            ];
        });
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function paginate(Request $request, array $filters = []): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($filters): array {
            [$page, $perPage] = $this->pageInput($filters);
            if (isset($filters['archived']) && in_array($filters['archived'], ['true', 'false'], true)) {
                $filters['archived'] = $filters['archived'] === 'true';
            }
            $query = $this->leadQuery($actor, array_diff_key($filters, array_flip(['page', 'per_page'])));
            $total = $query->count();
            $rows = $query->orderByDesc('created_at')->orderByDesc('id')
                ->forPage($page, $perPage)->get($this->listColumns());

            return ['data' => $rows->map(fn (stdClass $row): array => $this->presentLead($row))->all(),
                'meta' => $this->pageMeta($page, $perPage, $total)];
        });
    }

    /** Bounded HTML projection; private contacts remain available only through audited reveal.
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function workspace(Request $request, array $filters = []): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($filters): array {
            $this->onlyKeys($filters, ['q', 'stage', 'archived', 'page', 'per_page', 'source', 'assignee', 'priority', 'status']);
            if (isset($filters['archived']) && in_array($filters['archived'], ['true', 'false'], true)) {
                $filters['archived'] = $filters['archived'] === 'true';
            }
            [$page, $perPage] = $this->pageInput($filters);
            $query = $this->leadQuery($actor, array_intersect_key($filters, array_flip(['q', 'stage', 'archived'])));
            foreach (['source' => 'source', 'assignee' => 'assignee_id'] as $filter => $column) {
                $value = $this->nullableString($filters, $filter, $filter === 'source' ? 60 : 26);
                if ($value !== null) {
                    $query->where($column, $value);
                }
            }
            $priority = $this->nullableString($filters, 'priority', 10);
            if ($priority !== null) {
                if (! in_array($priority, ['low', 'normal', 'high'], true)) {
                    $this->invalid('priority', 'Select a valid priority.');
                }
                $query->where('priority', $priority);
            }
            $status = $this->nullableString($filters, 'status', 16);
            if ($status !== null) {
                match ($status) {
                    'new' => $query->whereNull('assignee_id')->whereNull('customer_id'),
                    'assigned' => $query->whereNotNull('assignee_id')->whereNull('customer_id'),
                    'converted' => $query->whereNotNull('customer_id'),
                    default => $this->invalid('status', 'Select a valid status.'),
                };
            }
            $total = (clone $query)->count();
            $rows = $query->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get($this->listColumns());
            $ids = $rows->pluck('id')->all();
            $assignees = DB::table('access_users')->where('company_id', $actor['company_id'])
                ->whereIn('id', $rows->pluck('assignee_id')->filter()->all())->pluck('name', 'id');
            $followups = DB::table('crm_activities')->where('company_id', $actor['company_id'])->whereIn('lead_id', $ids)
                ->selectRaw('lead_id, MIN(next_follow_up) AS due_at, MAX(occurred_at) AS last_activity')->groupBy('lead_id')->get()->keyBy('lead_id');
            $data = $rows->map(function (stdClass $row) use ($assignees, $followups): array {
                return $this->presentLead($row) + [
                    'priority' => (string) $row->priority,
                    'assignee_name' => $assignees[$row->assignee_id] ?? null,
                    'next_follow_up' => $followups[$row->id]->due_at ?? null,
                    'last_activity' => $followups[$row->id]->last_activity ?? null,
                ];
            })->all();
            $visible = $this->visibleLeads($actor)->where('archived', false);
            $count = (clone $visible)->count();
            $converted = (clone $visible)->whereNotNull('customer_id')->count();
            $new = (clone $visible)->where('created_at', '>=', now()->startOfMonth())->count();
            $overdue = DB::table('crm_activities')->where('company_id', $actor['company_id'])
                ->whereIn('lead_id', (clone $visible)->select('id'))->where('next_follow_up', '<', now())->distinct()->count('lead_id');

            return ['data' => $data, 'meta' => $this->pageMeta($page, $perPage, $total),
                'metrics' => ['total' => $count, 'new' => $new, 'overdue' => $overdue, 'conversion' => $count === 0 ? 0 : round($converted / $count * 100, 1)],
                'sources' => (clone $visible)->select('source')->distinct()->orderBy('source')->limit(25)->pluck('source')->all(),
            ];
        });
    }

    /** @param list<string|null> $stageIds
     * @return array<string, string>
     */
    public function stageLabels(Request $request, array $stageIds): array
    {
        $stageIds = array_values(array_unique(array_filter($stageIds, 'is_string')));
        if (count($stageIds) > 100) {
            $this->invalid('data', 'Stage labels require a bounded lead page.');
        }

        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($stageIds): array {
            $labels = [];
            foreach (DB::table('crm_taxonomy')->where('company_id', $actor['company_id'])
                ->where('kind', 'stage')->whereIn('id', $stageIds)->get(['id', 'label']) as $stage) {
                $labels[(string) $stage->id] = (string) $stage->label;
            }

            return $labels;
        });
    }

    /** @return list<string> */
    private function listColumns(): array
    {
        return ['id', 'company_id', 'branch_id', 'department_id', 'name', 'organization', 'source', 'stage_id', 'priority', 'assignee_id', 'customer_id', 'consent', 'archived', 'version', 'created_at', 'updated_at'];
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     */
    private function leadQuery(array $actor, array $filters): Builder
    {
        $this->onlyKeys($filters, ['q', 'stage', 'archived']);
        $query = $this->visibleLeads($actor);

        if (! array_key_exists('archived', $filters)) {
            $query->where('archived', false);
        }

        if (array_key_exists('q', $filters)) {
            $q = $this->nullableString($filters, 'q', 120);
            if ($q !== null) {
                $like = '%'.$q.'%';
                $query->where(function (Builder $search) use ($like): void {
                    $search->where('name', 'like', $like)
                        ->orWhere('organization', 'like', $like)
                        ->orWhere('source', 'like', $like);
                });
            }
        }

        if (array_key_exists('stage', $filters)) {
            $stageId = $this->nullableString($filters, 'stage', 26);
            if ($stageId !== null) {
                $this->assertStage($this->actorString($actor, 'company_id'), $stageId);
                $query->where('stage_id', $stageId);
            }
        }

        if (array_key_exists('archived', $filters)) {
            $query->where('archived', $this->booleanInput($filters['archived'], 'archived'));
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function find(Request $request, string $id, array $filters = []): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($id, $filters): array {
            $lead = $this->visibleLeads($actor)->where('id', $id)->first();
            if ($lead === null) {
                throw new NotFoundHttpException('Lead not found.');
            }
            $this->onlyKeys($filters, ['page', 'per_page']);
            [$page, $perPage] = $this->pageInput($filters);
            $activities = DB::table('crm_activities')->where('company_id', $lead->company_id)->where('lead_id', $id);
            $total = $activities->count();

            return $this->presentLead($lead, true) + [
                'activities' => $activities->orderByDesc('occurred_at')->orderByDesc('id')->forPage($page, $perPage)
                    ->get()->map(static fn (stdClass $row): array => (array) $row)->all(),
                'activities_meta' => $this->pageMeta($page, $perPage, $total),
                'history' => $this->history($actor, $lead),
                'pending_transfer' => DB::table('crm_transfers')->where('company_id', $lead->company_id)->where('lead_id', $id)->where('state', 'pending')->first(),
            ];
        });
    }

    /** @param array<string, mixed> $filters
     * @return array{lead: array<string, mixed>, data: list<array<string, mixed>>, meta: array<string, int>, kind_counts: array<string, int>}
     */
    public function workspaceSection(Request $request, string $id, string $section, array $filters = []): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($id, $section, $filters): array {
            if (! in_array($section, ['activities', 'notes', 'tasks'], true)) {
                throw new NotFoundHttpException('Section not found.');
            }
            $lead = $this->visibleLeads($actor)->where('id', $id)->first();
            if ($lead === null) {
                throw new NotFoundHttpException('Lead not found.');
            }
            $this->onlyKeys($filters, ['q', 'kind', 'page', 'per_page']);
            [$page, $perPage] = $this->pageInput($filters);
            $search = $this->nullableString($filters, 'q', 200);
            $kind = $this->nullableString($filters, 'kind', 24);
            if ($kind !== null && ! in_array($kind, ['call', 'message', 'meeting', 'visit', 'note', 'quotation'], true)) {
                $this->invalid('kind', 'Select a supported activity kind.');
            }
            $query = DB::table('crm_activities as activity')
                ->where('activity.company_id', $actor['company_id'])->where('activity.lead_id', $id);
            $counts = (clone $query)->selectRaw('kind, COUNT(*) as total')->groupBy('kind')->pluck('total', 'kind')
                ->map(static fn ($count): int => (int) $count)->all();
            if ($section === 'notes') {
                $query->where('activity.kind', 'note');
            } elseif ($kind !== null) {
                $query->where('activity.kind', $kind);
            }
            if ($section === 'tasks') {
                $query->whereNotNull('activity.task_id');
            }
            if ($search !== null) {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('activity.note', 'like', '%'.$search.'%')
                        ->orWhere('activity.next_step', 'like', '%'.$search.'%')
                        ->orWhere('activity.outcome_label', 'like', '%'.$search.'%');
                });
            }
            $total = (clone $query)->count();
            $rows = $query->leftJoin('access_users as author', function (JoinClause $join): void {
                $join->on('author.id', '=', 'activity.actor_id')->on('author.company_id', '=', 'activity.company_id');
            })->orderByDesc('activity.occurred_at')->orderByDesc('activity.id')->forPage($page, $perPage)
                ->get(['activity.*', 'author.name as actor_name'])
                ->map(static fn (stdClass $row): array => (array) $row)->all();
            $taskActor = in_array('tasks.read', $actor['permissions'], true) ? $this->identity->fromActor($actor) : null;
            foreach ($rows as &$row) {
                $row['task'] = null;
                if ($taskActor !== null && $row['task_id'] !== null) {
                    try {
                        $row['task'] = $this->tasks->find($taskActor, $row['task_id'], false);
                    } catch (NotFoundHttpException|AccessDeniedHttpException) {
                        // CRM visibility does not grant visibility of the linked task.
                    }
                }
            }
            unset($row);

            return ['lead' => $this->presentLead($lead), 'data' => $rows,
                'meta' => $this->pageMeta($page, $perPage, $total), 'kind_counts' => $counts];
        });
    }

    /** @return array<string, mixed> */
    public function files(Request $request, string $id): array
    {
        return $this->leadFiles->files($request, $id);
    }

    /** @return array<string, mixed> */
    public function uploadFile(Request $request, string $id): array
    {
        return $this->leadFiles->uploadFile($request, $id);
    }

    public function downloadFile(Request $request, string $id, string $file): StreamedResponse
    {
        return $this->leadFiles->downloadFile($request, $id, $file);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function save(Request $request, array $data, ?string $id = null): array
    {
        return $this->withActor($request, ['crm.manage'], function (array $actor) use ($data, $id): array {
            $this->onlyKeys($data, ['name', 'organization', 'source', 'contacts', 'consent', 'preferences', 'notes', 'stage_id', 'priority', 'version']);
            $companyId = $this->actorString($actor, 'company_id');
            $creating = $id === null;
            $lead = null;

            if (! $creating && count(array_diff(array_keys($data), ['version'])) === 0) {
                $this->invalid('data', 'At least one lead field must be changed.');
            }

            if (! $creating) {
                $lead = $this->managerLeads($actor)->where('id', $id)->lockForUpdate()->first();
                if ($lead === null) {
                    throw new NotFoundHttpException('Lead not found.');
                }
                if ((bool) $lead->archived) {
                    throw new ConflictHttpException('Archived leads cannot be edited.');
                }
                $this->assertVersion($this->versionInput($data), (int) $lead->version);
            } elseif (array_key_exists('version', $data) && $this->versionInput($data) !== 0) {
                $this->invalid('version', 'New leads start at version zero.');
            }

            $values = $this->leadValues($data, $lead);
            $priority = $data['priority'] ?? $lead->priority ?? 'normal';
            if (! is_string($priority) || ! in_array($priority, ['low', 'normal', 'high'], true)) {
                $this->invalid('priority', 'Select a valid priority.');
            }
            if ($lead !== null && $lead->customer_id !== null && $values['contacts'] !== $this->decryptContacts((string) $lead->contacts)) {
                throw new ConflictHttpException('Converted contact identity requires a customer change workflow.');
            }
            $this->assertStage($companyId, $values['stage_id'], $lead === null ? null : ($lead->stage_id === null ? null : (string) $lead->stage_id));
            $duplicateKey = $this->duplicateFingerprint($values['contacts']);
            $this->assertUniqueContact($companyId, $duplicateKey, $id, $lead?->customer_id);

            $leadId = $id ?? (string) Str::ulid();
            $version = $lead === null ? 0 : ((int) $lead->version + 1);
            $timestamp = now()->toDateTimeString();
            $stored = [
                'name' => $values['name'],
                'organization' => $values['organization'],
                'source' => $values['source'],
                'contacts' => Crypt::encryptString(json_encode($values['contacts'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
                'duplicate_key' => $duplicateKey,
                'consent' => $values['consent'],
                'preferences' => json_encode($values['preferences'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'notes' => $values['notes'],
                'stage_id' => $values['stage_id'],
                'priority' => $priority,
                'version' => $version,
                'updated_at' => $timestamp,
            ];

            if ($lead === null) {
                DB::table('crm_leads')->insert($stored + [
                    'id' => $leadId,
                    'company_id' => $companyId,
                    'branch_id' => $this->actorString($actor, 'branch_id'),
                    'department_id' => $this->actorString($actor, 'department_id'),
                    'assignee_id' => null,
                    'customer_id' => null,
                    'archived' => false,
                    'created_at' => $timestamp,
                ]);
            } else {
                $updated = DB::table('crm_leads')
                    ->where('company_id', $companyId)
                    ->where('branch_id', $this->actorString($actor, 'branch_id'))
                    ->where('id', $leadId)
                    ->where('version', (int) $lead->version)
                    ->update($stored);
                if ($updated !== 1) {
                    throw new ConflictHttpException('Lead version is stale.');
                }
            }

            $this->recordAudit($actor, $leadId, $lead === null ? 'lead.created' : 'lead.updated', [
                'fields' => array_values(array_diff(array_map('strval', array_keys($data)), ['version'])),
                'version' => $version,
            ]);

            return $this->presentLead($this->leadRow($companyId, $leadId), true);
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function assign(Request $request, string $id, array $data): array
    {
        return $this->withActor($request, ['crm.manage', 'crm.assign'], function (array $actor) use ($id, $data): array {
            $this->onlyKeys($data, ['assignee_id', 'version']);
            $companyId = $this->actorString($actor, 'company_id');
            $lead = $this->managerLeads($actor)->where('id', $id)->lockForUpdate()->first();
            if ($lead === null) {
                throw new NotFoundHttpException('Lead not found.');
            }
            $this->assertVersion($this->versionInput($data), (int) $lead->version);
            if ((bool) $lead->archived) {
                throw new ConflictHttpException('Archived leads cannot be assigned.');
            }
            if ($lead->assignee_id !== null) {
                throw new ConflictHttpException('Lead ownership can change only through an acknowledged transfer.');
            }

            $assigneeId = $this->requiredString($data, 'assignee_id', 26);
            $recipient = DB::table('access_users')
                ->where('id', $assigneeId)
                ->where('company_id', $companyId)
                ->where('branch_id', $lead->branch_id)
                ->where('department_id', $lead->department_id)
                ->where('active', true)
                ->first(['id']);
            $permissions = $recipient === null ? [] : $this->access->permissions((string) $recipient->id);
            if ($recipient === null || ! in_array('crm.read', $permissions, true) || ! in_array('crm.write', $permissions, true)) {
                $this->invalid('assignee_id', 'Select an active eligible CRM user in this lead scope.');
            }

            $version = (int) $lead->version + 1;
            $updated = DB::table('crm_leads')
                ->where('company_id', $companyId)
                ->where('branch_id', $this->actorString($actor, 'branch_id'))
                ->where('id', $id)
                ->where('version', (int) $lead->version)
                ->update(['assignee_id' => $assigneeId, 'version' => $version, 'updated_at' => now()->toDateTimeString()]);
            if ($updated !== 1) {
                throw new ConflictHttpException('Lead version is stale.');
            }

            $this->recordAudit($actor, $id, 'lead.assigned', ['fields' => ['assignee_id'], 'version' => $version]);

            return $this->presentLead($this->leadRow($companyId, $id), true);
        });
    }

    /** @return list<array{id: string, name: string}> */
    public function eligibleRecipients(Request $request): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor): array {
            if (! $this->hasPermission($actor, 'crm.transfer') && ! ($this->hasPermission($actor, 'crm.manage') && $this->hasPermission($actor, 'crm.assign'))) {
                throw new AccessDeniedHttpException('CRM assignment or transfer permission is required.');
            }
            $users = DB::table('access_users')
                ->where('company_id', $this->actorString($actor, 'company_id'))
                ->where('branch_id', $this->actorString($actor, 'branch_id'))
                ->where('department_id', $this->actorString($actor, 'department_id'))
                ->where('active', true)
                ->orderBy('name')
                ->orderBy('id')
                // shortcut: recipient choices cap at 100, add pagination if a department exceeds this.
                ->limit(100)
                ->get(['id', 'name']);

            return array_values($users->filter(function (stdClass $user): bool {
                $permissions = $this->access->permissions((string) $user->id);

                return in_array('crm.read', $permissions, true) && in_array('crm.write', $permissions, true);
            })->map(fn (stdClass $user): array => ['id' => (string) $user->id, 'name' => (string) $user->name])->all());
        });
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function recipientPage(Request $request, array $filters = []): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($filters): array {
            if (! $this->hasPermission($actor, 'crm.transfer') && ! ($this->hasPermission($actor, 'crm.manage') && $this->hasPermission($actor, 'crm.assign'))) {
                throw new AccessDeniedHttpException('CRM assignment or transfer permission is required.');
            }
            $this->onlyKeys($filters, ['q', 'page', 'per_page']);
            [$page, $perPage] = $this->pageInput($filters);
            $q = $this->nullableString($filters, 'q', 120);
            $query = DB::table('access_users as users')
                ->where('users.company_id', $actor['company_id'])
                ->where('users.branch_id', $actor['branch_id'])
                ->where('users.department_id', $actor['department_id'])
                ->where('users.active', true)
                ->whereExists(fn (Builder $organization): Builder => $organization->selectRaw('1')
                    ->from('access_companies as recipient_company')
                    ->whereColumn('recipient_company.id', 'users.company_id'))
                ->whereExists(fn (Builder $branch): Builder => $branch->selectRaw('1')
                    ->from('access_branches as recipient_branch')
                    ->whereColumn('recipient_branch.id', 'users.branch_id')
                    ->whereColumn('recipient_branch.company_id', 'users.company_id')
                    ->where('recipient_branch.active', true))
                ->whereExists(fn (Builder $department): Builder => $department->selectRaw('1')
                    ->from('access_departments as recipient_department')
                    ->whereColumn('recipient_department.id', 'users.department_id')
                    ->whereColumn('recipient_department.company_id', 'users.company_id')
                    ->whereColumn('recipient_department.branch_id', 'users.branch_id')
                    ->where('recipient_department.active', true))
                ->when($q !== null, fn (Builder $query): Builder => $query->where('users.name', 'like', '%'.$q.'%'));

            foreach (['crm.read', 'crm.write'] as $permission) {
                $this->whereHasEffectiveRecipientPermission($query, $permission);
            }

            $countQuery = clone $query;
            $total = $countQuery->count('users.id');
            $rows = $query->orderBy('users.name')->orderBy('users.id')
                ->forPage($page, $perPage)
                ->get(['users.id', 'users.name'])
                ->map(fn (stdClass $user): array => ['id' => (string) $user->id, 'name' => (string) $user->name])
                ->all();

            return ['data' => $rows, 'meta' => $this->pageMeta($page, $perPage, $total)];
        });
    }

    private function whereHasEffectiveRecipientPermission(Builder $query, string $permission): void
    {
        $query->where(function (Builder $effective) use ($permission): void {
            $effective->where('users.is_admin', true)
                ->orWhereExists(function (Builder $roleGrant) use ($permission): void {
                    $roleGrant->selectRaw('1')->from('access_role_permissions as role_permissions')
                        ->join('access_roles as roles', 'roles.id', '=', 'role_permissions.role_id')
                        ->whereColumn('roles.company_id', 'users.company_id')
                        ->where('role_permissions.permission', $permission)
                        ->where(function (Builder $assignment): void {
                            $assignment->whereExists(function (Builder $directRole): void {
                                $directRole->selectRaw('1')->from('access_user_roles as user_roles')
                                    ->whereColumn('user_roles.user_id', 'users.id')
                                    ->whereColumn('user_roles.role_id', 'roles.id');
                            })->orWhereExists(function (Builder $departmentRole): void {
                                $departmentRole->selectRaw('1')->from('access_department_roles as department_roles')
                                    ->whereColumn('department_roles.role_id', 'roles.id')
                                    ->whereColumn('department_roles.department_id', 'users.department_id')
                                    ->whereColumn('department_roles.company_id', 'users.company_id')
                                    ->whereColumn('department_roles.branch_id', 'users.branch_id');
                            });
                        });
                })
                ->orWhereExists(function (Builder $explicitGrant) use ($permission): void {
                    $explicitGrant->selectRaw('1')->from('access_user_permissions as user_permissions')
                        ->whereColumn('user_permissions.user_id', 'users.id')
                        ->where('user_permissions.permission', $permission)
                        ->where('user_permissions.allowed', true);
                });
        })->whereNotExists(function (Builder $explicitDeny) use ($permission): void {
            $explicitDeny->selectRaw('1')->from('access_user_permissions as user_permissions')
                ->whereColumn('user_permissions.user_id', 'users.id')
                ->where('user_permissions.permission', $permission)
                ->where('user_permissions.allowed', false);
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function activity(Request $request, string $id, array $data): array
    {
        return $this->withActor($request, ['crm.read', 'crm.write'], function (array $actor) use ($request, $id, $data): array {
            $this->onlyKeys($data, ['kind', 'outcome_id', 'next_step', 'note', 'occurred_at', 'next_follow_up', 'version']);
            $lead = $this->lockedLead($actor, $id, $data);
            $kind = $this->requiredString($data, 'kind', 24);
            if (! in_array($kind, ['call', 'message', 'meeting', 'visit', 'note', 'quotation'], true)) {
                $this->invalid('kind', 'Select a supported activity kind.');
            }
            $outcomeId = $this->requiredString($data, 'outcome_id', 26);
            $outcome = DB::table('crm_taxonomy')->where('id', $outcomeId)->where('company_id', $lead->company_id)->where('kind', 'outcome')->first();
            if ($outcome === null || DB::table('crm_taxonomy')->where('company_id', $lead->company_id)->where('replaces_id', $outcomeId)->exists()) {
                $this->invalid('outcome_id', 'Select a current activity outcome in this company.');
            }
            $next = $this->requiredString($data, 'next_step', 200);
            $note = $this->nullableString($data, 'note', 10000);
            $occurred = $this->utcDate($this->requiredString($data, 'occurred_at', 64), 'occurred_at');
            $followUp = $this->nullableString($data, 'next_follow_up', 64);
            $due = $followUp === null ? null : $this->utcDate($followUp, 'next_follow_up');
            $domain = new LeadActivity($id, $this->actorString($actor, 'id'), (string) $outcome->label, $next, $occurred);
            $activityId = (string) Str::ulid();
            $taskId = null;
            if ($due !== null) {
                // Tasks currently authorizes self-assignment only; never impersonate a lead owner.
                $this->access->requirePermission($actor, 'tasks.write');
                $task = $this->tasks->create($this->identity->fromRequest($request), ['title' => 'CRM follow-up', 'priority' => 'normal', 'due_at' => $due->format(DATE_ATOM)], 'crm-follow-up:'.$activityId);
                $taskId = $task['id'];
            }
            DB::table('crm_activities')->insert(['id' => $activityId, 'company_id' => $lead->company_id, 'lead_id' => $id, 'actor_id' => $actor['id'], 'kind' => $kind, 'outcome_id' => $outcomeId, 'outcome_label' => $outcome->label, 'note' => $note, 'next_step' => $next, 'occurred_at' => $domain->occurredAtUtc->format('Y-m-d H:i:s'), 'next_follow_up' => $due?->format('Y-m-d H:i:s'), 'task_id' => $taskId, 'created_at' => now()]);
            $this->advanceLead($actor, $lead, 'lead.activity.recorded', ['activity_id' => $activityId, 'task_id' => $taskId]);

            return $this->find($request, $id);
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function requestTransfer(Request $request, string $id, array $data): array
    {
        return $this->withActor($request, ['crm.read', 'crm.transfer'], function (array $actor) use ($id, $data): array {
            $this->onlyKeys($data, ['recipient_id', 'context', 'version']);
            $lead = $this->lockedLead($actor, $id, $data);
            if ($lead->assignee_id === null) {
                throw new ConflictHttpException('Initial assignment is required before transfer.');
            }
            if (DB::table('crm_transfers')->where('company_id', $lead->company_id)->where('lead_id', $id)->where('state', 'pending')->exists()) {
                throw new ConflictHttpException('A transfer is already pending.');
            }
            $recipient = $this->requiredString($data, 'recipient_id', 26);
            $this->requireEligibleRecipient($lead, $recipient);
            if ($recipient === $lead->assignee_id) {
                $this->invalid('recipient_id', 'Choose a different recipient.');
            }
            $context = $this->requiredString($data, 'context', 10000);
            $transfer = (new HandoverLead)->propose(new LeadHandover($id, (string) $lead->assignee_id, $recipient));
            $transferId = (string) Str::ulid();
            DB::table('crm_transfers')->insert(['id' => $transferId, 'company_id' => $lead->company_id, 'lead_id' => $id, 'sender_id' => $transfer->ownerId, 'recipient_id' => $transfer->recipientId, 'context' => $context, 'state' => 'pending', 'requested_version' => (int) $lead->version + 1, 'created_at' => now(), 'responded_at' => null]);
            $this->advanceLead($actor, $lead, 'lead.transfer.requested', ['transfer_id' => $transferId, 'sender_id' => $transfer->ownerId, 'recipient_id' => $recipient]);

            return ['id' => $transferId, 'lead_id' => $id, 'state' => 'pending', 'version' => (int) $lead->version + 1];
        });
    }

    /** @return list<array<string, mixed>> */
    public function inbox(Request $request): array
    {
        return $this->withReadActor($request, ['crm.read', 'crm.write'], function (array $actor): array {
            return array_map(static fn (stdClass $row): array => (array) $row, $this->inboxQuery($actor)
                ->get($this->inboxColumns())->all());
        });
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function inboxPage(Request $request, array $filters = []): array
    {
        return $this->withReadActor($request, ['crm.read', 'crm.write'], function (array $actor) use ($filters): array {
            $this->onlyKeys($filters, ['page', 'per_page']);
            [$page, $perPage] = $this->pageInput($filters);
            $query = $this->inboxQuery($actor);
            $total = $query->count();

            return ['data' => $query->forPage($page, $perPage)->get($this->inboxColumns())
                ->map(static fn (stdClass $row): array => (array) $row)->all(),
                'meta' => $this->pageMeta($page, $perPage, $total)];
        });
    }

    /** @return list<string> */
    private function inboxColumns(): array
    {
        return ['transfers.id', 'transfers.lead_id', 'transfers.sender_id', 'transfers.context', 'transfers.state', 'transfers.created_at', 'leads.name', 'leads.version'];
    }

    /** @param array<string, mixed> $actor */
    private function inboxQuery(array $actor): Builder
    {
        return DB::table('crm_transfers as transfers')
            ->join('crm_leads as leads', 'leads.id', '=', 'transfers.lead_id')
            ->where('transfers.company_id', $actor['company_id'])->where('leads.company_id', $actor['company_id'])
            ->where('leads.branch_id', $actor['branch_id'])->where('leads.department_id', $actor['department_id'])
            ->where('transfers.recipient_id', $actor['id'])->where('transfers.state', 'pending')->where('leads.archived', false)
            ->orderBy('transfers.created_at')->orderBy('transfers.id');
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function respondTransfer(Request $request, string $transferId, array $data): array
    {
        return $this->withActor($request, ['crm.read', 'crm.write'], function (array $actor) use ($transferId, $data): array {
            $this->onlyKeys($data, ['decision', 'version']);
            $transfer = DB::table('crm_transfers')->where('id', $transferId)->where('company_id', $actor['company_id'])->where('recipient_id', $actor['id'])->lockForUpdate()->first();
            if ($transfer === null) {
                throw new NotFoundHttpException('Transfer not found.');
            }
            if ($transfer->state !== 'pending') {
                throw new ConflictHttpException('Transfer has already been answered.');
            }
            $lead = $this->managerLeads($actor)->where('id', $transfer->lead_id)->lockForUpdate()->first();
            if ($lead === null) {
                throw new NotFoundHttpException('Transfer not found.');
            }
            $this->assertVersion($this->versionInput($data), (int) $lead->version);
            $this->requireEligibleRecipient($lead, $this->actorString($actor, 'id'));
            if ((bool) $lead->archived || $lead->assignee_id !== $transfer->sender_id) {
                throw new ConflictHttpException('Lead ownership or availability has changed.');
            }
            $decision = $this->requiredString($data, 'decision', 12);
            if (! in_array($decision, ['accept', 'reject'], true)) {
                $this->invalid('decision', 'Accept or reject the transfer.');
            }
            $changes = [];
            if ($decision === 'accept') {
                $this->assertVersion((int) $transfer->requested_version, (int) $lead->version);
                $accepted = (new HandoverLead)->accept(new LeadHandover((string) $lead->id, (string) $transfer->sender_id, (string) $transfer->recipient_id), $this->actorString($actor, 'id'));
                $changes['assignee_id'] = $accepted->ownerId;
            }
            $state = $decision === 'accept' ? 'accepted' : 'rejected';
            DB::table('crm_transfers')->where('id', $transferId)->where('state', 'pending')->update(['state' => $state, 'responded_at' => now()]);
            $this->advanceLead($actor, $lead, 'lead.transfer.'.$state, ['transfer_id' => $transferId, 'sender_id' => $transfer->sender_id, 'recipient_id' => $transfer->recipient_id], $changes);

            return ['id' => $transferId, 'lead_id' => $lead->id, 'state' => $state, 'version' => (int) $lead->version + 1];
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function convert(Request $request, string $id, array $data): array
    {
        return $this->withActor($request, ['crm.read', 'crm.convert'], function (array $actor) use ($id, $data): array {
            $this->onlyKeys($data, ['version']);
            $lead = $this->visibleLeads($actor)->where('id', $id)->lockForUpdate()->first();
            if ($lead === null) {
                throw new NotFoundHttpException('Lead not found.');
            }
            $this->versionInput($data);
            if ((bool) $lead->archived) {
                throw new ConflictHttpException('Archived leads cannot be converted.');
            }
            if ($lead->customer_id !== null) {
                return $this->presentLead($lead, true);
            }
            $this->assertVersion($this->versionInput($data), (int) $lead->version);
            $customer = $lead->duplicate_key === null ? null : DB::table('crm_customers')->where('company_id', $lead->company_id)->where('duplicate_key', $lead->duplicate_key)->first();
            $customerId = $customer === null ? (string) Str::ulid() : (string) $customer->id;
            if ($customer === null) {
                DB::table('crm_customers')->insert(['id' => $customerId, 'company_id' => $lead->company_id, 'branch_id' => $lead->branch_id, 'department_id' => $lead->department_id, 'name' => $lead->name, 'organization' => $lead->organization, 'duplicate_key' => $lead->duplicate_key, 'created_at' => now()]);
            }
            $this->advanceLead($actor, $lead, 'lead.converted', ['customer_id' => $customerId], ['customer_id' => $customerId]);

            return $this->presentLead($this->leadRow((string) $lead->company_id, $id), true);
        });
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     */
    private function lockedLead(array $actor, string $id, array $data): stdClass
    {
        $lead = $this->visibleLeads($actor)->where('id', $id)->lockForUpdate()->first();
        if ($lead === null) {
            throw new NotFoundHttpException('Lead not found.');
        }
        $this->assertVersion($this->versionInput($data), (int) $lead->version);
        if ((bool) $lead->archived) {
            throw new ConflictHttpException('Archived leads cannot be changed.');
        }

        return $lead;
    }

    private function requireEligibleRecipient(stdClass $lead, string $id): void
    {
        $user = DB::table('access_users')->where('id', $id)->where('company_id', $lead->company_id)->where('branch_id', $lead->branch_id)->where('department_id', $lead->department_id)->where('active', true)->first();
        $grants = $user === null ? [] : $this->access->permissions($id);
        if ($user === null || ! in_array('crm.read', $grants, true) || ! in_array('crm.write', $grants, true)) {
            $this->invalid('recipient_id', 'Select an active eligible CRM recipient in this scope.');
        }
    }

    private function utcDate(string $value, string $field): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})$/D', $value) !== 1) {
            $this->invalid($field, 'Use an ISO date and time with timezone.');
        }
        try {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                $this->invalid($field, 'Use a valid date and time.');
            }

            return $date->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception) {
            $this->invalid($field, 'Use a valid date and time.');
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function archive(Request $request, string $id, array $data): array
    {
        return $this->withActor($request, ['crm.manage'], function (array $actor) use ($id, $data): array {
            $this->onlyKeys($data, ['version']);
            $companyId = $this->actorString($actor, 'company_id');
            $lead = $this->managerLeads($actor)->where('id', $id)->lockForUpdate()->first();
            if ($lead === null) {
                throw new NotFoundHttpException('Lead not found.');
            }
            $this->assertVersion($this->versionInput($data), (int) $lead->version);
            if ((bool) $lead->archived) {
                throw new ConflictHttpException('Lead is already archived.');
            }
            if (DB::table('crm_transfers')->where('company_id', $companyId)->where('lead_id', $id)->where('state', 'pending')->exists()) {
                throw new ConflictHttpException('Resolve the pending transfer before archiving the lead.');
            }

            $contacts = $this->decryptContacts((string) $lead->contacts);
            $domainLead = new LeadRecord(
                $companyId,
                (string) $lead->id,
                (string) $lead->name,
                (string) $lead->organization,
                (string) $lead->source,
                $contacts['email'],
                (bool) $lead->consent,
                (bool) $lead->archived,
            );
            $correlationId = (string) Str::ulid();
            $transition = $this->lifecycle->archive($domainLead, $companyId, $this->actorString($actor, 'id'), $correlationId);
            if (! $transition->accepted) {
                throw new ConflictHttpException('Lead cannot be archived in its current state.');
            }

            $version = (int) $lead->version + 1;
            $updated = DB::table('crm_leads')
                ->where('company_id', $companyId)
                ->where('branch_id', $this->actorString($actor, 'branch_id'))
                ->where('id', $id)
                ->where('version', (int) $lead->version)
                ->update(['archived' => $transition->lead->archived, 'version' => $version, 'updated_at' => now()->toDateTimeString()]);
            if ($updated !== 1) {
                throw new ConflictHttpException('Lead version is stale.');
            }

            $event = $transition->outboxEvent();
            if ($event === null) {
                throw new RuntimeException('Lead archive outbox intent is missing.');
            }
            $intent = new OutboxEvent(
                $event->eventId,
                $event->eventType,
                $event->eventVersion,
                $event->aggregateType,
                $event->aggregateId,
                $version,
                $event->payload,
            );
            $this->recordAudit($actor, $id, 'lead.archived', [
                'fields' => ['archived'],
                'version' => $version,
                'outbox_intent' => $intent->jsonSerialize(),
            ], correlationId: $correlationId);

            return $this->presentLead($this->leadRow($companyId, $id), true);
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function reveal(Request $request, string $id, array $data): array
    {
        return $this->withActor($request, ['crm.read', 'contact.reveal'], function (array $actor) use ($id, $data): array {
            $this->onlyKeys($data, ['reason']);
            $reason = $this->requiredString($data, 'reason', 500);
            if (mb_strlen($reason) < 3) {
                $this->invalid('reason', 'A brief reason is required to reveal contact details.');
            }

            $lead = $this->visibleLeads($actor)->where('id', $id)->lockForUpdate()->first();
            if ($lead === null) {
                throw new NotFoundHttpException('Lead not found.');
            }

            $this->recordAudit($actor, $id, 'lead.contacts.revealed', [
                'reason_provided' => true,
                'encrypted_reason' => Crypt::encryptString($reason),
                'contact_fields' => array_keys(array_filter($this->decryptContacts((string) $lead->contacts), static fn (?string $value): bool => $value !== null)),
            ]);

            return $this->presentLead($lead, true, true);
        });
    }

    /** @return list<array<string, mixed>> */
    public function taxonomy(Request $request): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor): array {
            return $this->currentTaxonomy($this->actorString($actor, 'company_id'));
        });
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function taxonomyPage(Request $request, array $filters = []): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($filters): array {
            $this->onlyKeys($filters, ['q', 'kind', 'page', 'per_page']);
            [$page, $perPage] = $this->pageInput($filters);
            $kind = $this->nullableString($filters, 'kind', 16);
            if ($kind !== null && ! in_array($kind, ['stage', 'outcome'], true)) {
                $this->invalid('kind', 'Choose stage or outcome.');
            }
            $q = $this->nullableString($filters, 'q', 120);
            $query = DB::table('crm_taxonomy as taxonomy')->where('taxonomy.company_id', $actor['company_id'])
                ->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')->from('crm_taxonomy as replacement')
                        ->whereColumn('replacement.company_id', 'taxonomy.company_id')
                        ->whereColumn('replacement.replaces_id', 'taxonomy.id');
                })
                ->when($kind !== null, fn (Builder $query): Builder => $query->where('taxonomy.kind', $kind))
                ->when($q !== null, fn (Builder $query): Builder => $query->where('taxonomy.label', 'like', '%'.$q.'%'));
            $total = $query->count();

            return ['data' => $query->orderBy('taxonomy.kind')->orderBy('taxonomy.label')->orderBy('taxonomy.id')
                ->forPage($page, $perPage)->get(['taxonomy.id', 'taxonomy.kind', 'taxonomy.label', 'taxonomy.replaces_id', 'taxonomy.created_at'])
                ->map(static fn (stdClass $row): array => (array) $row)->all(),
                'meta' => $this->pageMeta($page, $perPage, $total)];
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveTaxonomy(Request $request, array $data): array
    {
        return $this->withActor($request, ['crm.taxonomy'], function (array $actor) use ($data): array {
            $this->onlyKeys($data, ['kind', 'label', 'replaces_id']);
            $companyId = $this->actorString($actor, 'company_id');
            $kind = $this->requiredString($data, 'kind', 16);
            if (! in_array($kind, ['stage', 'outcome'], true)) {
                $this->invalid('kind', 'Taxonomy kind must be stage or outcome.');
            }
            $label = $this->requiredString($data, 'label', 60);
            $replacesId = $this->nullableString($data, 'replaces_id', 26);
            $previous = null;

            if ($replacesId !== null) {
                $previous = DB::table('crm_taxonomy')
                    ->where('company_id', $companyId)
                    ->where('id', $replacesId)
                    ->where('kind', $kind)
                    ->lockForUpdate()
                    ->first();
                if ($previous === null) {
                    $this->invalid('replaces_id', 'Choose a taxonomy value of the same kind in this company.');
                }
                if (DB::table('crm_taxonomy')->where('company_id', $companyId)->where('replaces_id', $replacesId)->exists()) {
                    throw new ConflictHttpException('This taxonomy value has already been replaced.');
                }
            }

            foreach ($this->currentTaxonomy($companyId) as $current) {
                if ($current['kind'] === $kind && mb_strtolower($current['label']) === mb_strtolower($label)) {
                    throw new ConflictHttpException('A current taxonomy value already uses this label.');
                }
            }

            $id = (string) Str::ulid();
            DB::table('crm_taxonomy')->insert([
                'id' => $id,
                'company_id' => $companyId,
                'kind' => $kind,
                'label' => $label,
                'replaces_id' => $replacesId,
                'created_at' => now()->toDateTimeString(),
            ]);

            $this->recordAudit(null, $id, 'taxonomy.saved', [
                'kind' => $kind,
                'replaces_id' => $replacesId,
            ], $actor, $companyId);

            return ['id' => $id, 'kind' => $kind, 'label' => $label, 'replaces_id' => $replacesId];
        });
    }

    /** @param array<string, mixed> $actor */
    private function managerLeads(array $actor): Builder
    {
        return DB::table('crm_leads')
            ->where('company_id', $this->actorString($actor, 'company_id'))
            ->where('branch_id', $this->actorString($actor, 'branch_id'))
            ->where('department_id', $this->actorString($actor, 'department_id'));
    }

    /** @param array<string, mixed> $data
     * @return array{name: string, organization: string, source: string, contacts: array{email: ?string, phone: ?string, whatsapp: ?string}, consent: bool, preferences: array{email: bool, phone: bool, whatsapp: bool}, notes: ?string, stage_id: ?string}
     */
    private function leadValues(array $data, ?stdClass $before): array
    {
        $currentContacts = $before === null ? ['email' => null, 'phone' => null, 'whatsapp' => null] : $this->decryptContacts((string) $before->contacts);
        $providedContacts = array_key_exists('contacts', $data) ? $this->contactInput($data['contacts']) : [];
        $contacts = [
            'email' => array_key_exists('email', $providedContacts) ? $providedContacts['email'] : $currentContacts['email'],
            'phone' => array_key_exists('phone', $providedContacts) ? $providedContacts['phone'] : $currentContacts['phone'],
            'whatsapp' => array_key_exists('whatsapp', $providedContacts) ? $providedContacts['whatsapp'] : $currentContacts['whatsapp'],
        ];

        $preferences = $before === null ? ['email' => false, 'phone' => false, 'whatsapp' => false] : $this->decodePreferences($before->preferences);

        return [
            'name' => array_key_exists('name', $data) ? $this->requiredString($data, 'name', 200) : (string) ($before->name ?? $this->requiredString($data, 'name', 200)),
            'organization' => array_key_exists('organization', $data) ? $this->requiredString($data, 'organization', 200) : (string) ($before->organization ?? $this->requiredString($data, 'organization', 200)),
            'source' => array_key_exists('source', $data) ? $this->requiredString($data, 'source', 60) : (string) ($before->source ?? $this->requiredString($data, 'source', 60)),
            'contacts' => $contacts,
            'consent' => array_key_exists('consent', $data) ? $this->booleanInput($data['consent'], 'consent') : (bool) ($before->consent ?? false),
            'preferences' => array_key_exists('preferences', $data) ? $this->preferencesInput($data['preferences']) : $preferences,
            'notes' => array_key_exists('notes', $data) ? $this->nullableString($data, 'notes', 4000) : ($before?->notes === null ? null : (string) $before->notes),
            'stage_id' => array_key_exists('stage_id', $data) ? $this->nullableString($data, 'stage_id', 26) : ($before?->stage_id === null ? null : (string) $before->stage_id),
        ];
    }

    /** @return array{email?: ?string, phone?: ?string, whatsapp?: ?string} */
    private function contactInput(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $this->invalid('contacts', 'Contacts must be an object with phone, email, and whatsapp fields.');
        }
        $this->onlyKeys($value, ['email', 'phone', 'whatsapp']);

        $contacts = [];
        if (array_key_exists('email', $value)) {
            $email = $this->nullableString($value, 'email', 254);
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->invalid('contacts.email', 'A valid email address is required.');
            }
            $contacts['email'] = $email;
        }
        if (array_key_exists('phone', $value)) {
            $contacts['phone'] = $this->nullableString($value, 'phone', 64);
        }
        if (array_key_exists('whatsapp', $value)) {
            $contacts['whatsapp'] = $this->nullableString($value, 'whatsapp', 64);
        }

        return $contacts;
    }

    /** @param array{email: ?string, phone: ?string, whatsapp: ?string} $contacts */
    private function duplicateFingerprint(array $contacts): ?string
    {
        $email = $contacts['email'] === null ? null : mb_strtolower(trim($contacts['email']));

        return $email === null || $email === ''
            ? null
            : hash_hmac('sha256', 'email:'.$email, (string) config('app.key', ''));
    }

    private function assertUniqueContact(string $companyId, ?string $fingerprint, ?string $leadId, ?string $customerId = null): void
    {
        if ($fingerprint === null) {
            return;
        }
        $leads = DB::table('crm_leads')->where('company_id', $companyId)->where('duplicate_key', $fingerprint);
        if ($leadId !== null) {
            $leads->where('id', '<>', $leadId);
        }
        $customers = DB::table('crm_customers')->where('company_id', $companyId)->where('duplicate_key', $fingerprint);
        if ($customerId !== null) {
            $customers->where('id', '<>', $customerId);
        }
        if ($leads->exists() || $customers->exists()) {
            throw new ConflictHttpException('A lead or customer already uses this contact fingerprint.');
        }
    }

    private function assertStage(string $companyId, ?string $stageId, ?string $existingId = null): void
    {
        if ($stageId === null) {
            return;
        }
        $stage = DB::table('crm_taxonomy')->where('company_id', $companyId)->where('id', $stageId)->where('kind', 'stage')->first();
        if ($stage === null) {
            $this->invalid('stage_id', 'Choose a stage in this company.');
        }
        if ($stageId !== $existingId && DB::table('crm_taxonomy')->where('company_id', $companyId)->where('replaces_id', $stageId)->exists()) {
            $this->invalid('stage_id', 'Choose a current stage.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function currentTaxonomy(string $companyId): array
    {
        $replaced = DB::table('crm_taxonomy')->where('company_id', $companyId)->whereNotNull('replaces_id')->pluck('replaces_id')->all();

        return array_values(DB::table('crm_taxonomy')
            ->where('company_id', $companyId)
            ->when($replaced !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $replaced))
            ->orderBy('kind')
            ->orderBy('label')
            ->orderBy('id')
            ->get(['id', 'kind', 'label', 'replaces_id', 'created_at'])
            ->map(fn (stdClass $row): array => [
                'id' => (string) $row->id,
                'kind' => (string) $row->kind,
                'label' => (string) $row->label,
                'replaces_id' => $row->replaces_id === null ? null : (string) $row->replaces_id,
                'created_at' => (string) $row->created_at,
            ])
            ->all());
    }

    /** @param array<string, mixed> $actor
     * @return list<array<string, mixed>>
     */
    private function history(array $actor, stdClass $lead): array
    {
        return array_values(DB::table('crm_audit')
            ->where('company_id', $this->actorString($actor, 'company_id'))
            ->where('lead_id', $lead->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['action', 'actor_id', 'metadata', 'created_at'])
            ->map(fn (stdClass $entry): array => [
                'action' => (string) $entry->action,
                'actor_id' => (string) $entry->actor_id,
                'metadata' => $this->safeHistoryMetadata($entry->metadata),
                'created_at' => (string) $entry->created_at,
            ])
            ->all());
    }

    /** @return array<string, mixed> */
    private function safeHistoryMetadata(mixed $metadata): array
    {
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        if (! is_array($metadata)) {
            return [];
        }

        $safe = $this->safeAuditMetadata($metadata);
        unset($safe['encrypted_reason']);

        return $safe;
    }

    private function leadRow(string $companyId, string $id): stdClass
    {
        $lead = DB::table('crm_leads')->where('company_id', $companyId)->where('id', $id)->first();
        if ($lead === null) {
            throw new RuntimeException('Lead disappeared during a locked mutation.');
        }

        return $lead;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $field, int $max): string
    {
        $value = $data[$field] ?? null;
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) {
            $this->invalid($field, 'A valid value is required.');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private function nullableString(array $data, string $field, int $max): ?string
    {
        $value = $data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (! is_string($value) || mb_strlen(trim($value)) > $max) {
            $this->invalid($field, 'The value exceeds the allowed length.');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private function versionInput(array $data): int
    {
        $value = $data['version'] ?? null;
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        $this->invalid('version', 'Version must be a non-negative integer.');
    }
}
