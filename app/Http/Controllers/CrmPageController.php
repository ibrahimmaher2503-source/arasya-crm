<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class CrmPageController
{
    public function pipeline(Request $request): View
    {
        $data = $this->directory->pipeline($request);
        if ($data['customerOptions'] !== null) {
            return view('crm.pipeline', $this->context($request, 'الصفقات وخط المبيعات', 'Deals and sales pipeline') + $data + [
                'leadOptions' => array_map(static fn (object $customer): array => (array) $customer, $data['customerOptions']->items()),
                'leadPaginator' => $data['customerOptions'],
            ]);
        }
        $leads = $this->directory->paginate($request, ['q' => $request->query('lead_q'), 'page' => $request->query('lead_page', '1')]);

        return view('crm.pipeline', $this->context($request, 'الصفقات وخط المبيعات', 'Deals and sales pipeline') + $data + ['leadOptions' => $leads['data'], 'leadPaginator' => $this->paginator($request, $leads, 'lead_page')]);
    }

    public function saveOpportunity(Request $request, ?string $opportunity = null): JsonResponse|RedirectResponse
    {
        $data = $this->directory->saveOpportunity($request, $opportunity);
        if ($request->attributes->get('crm_html') !== true) {
            return response()->json(['data' => $data], $opportunity === null ? 201 : 200);
        }

        return redirect('/crm/pipeline?'.http_build_query(['locale' => $request->query('locale') === 'en' ? 'en' : 'ar', 'currency' => $data['currency'], 'selected' => $data['id']]));
    }

    public function transitionOpportunity(Request $request, string $opportunity): JsonResponse|RedirectResponse
    {
        $data = $this->directory->transitionOpportunity($request, $opportunity);
        if ($request->attributes->get('crm_html') !== true) {
            return response()->json(['data' => $data]);
        }

        return redirect('/crm/pipeline?'.http_build_query(['locale' => $request->query('locale') === 'en' ? 'en' : 'ar', 'currency' => $data['currency'], 'selected' => $data['id']]));
    }

    public function reopenOpportunity(Request $request, string $opportunity): never
    {
        $this->directory->reopenOpportunity($request, $opportunity);
    }

    public function __construct(private LeadDirectory $directory, private AccessControl $access) {}

    public function overview(Request $request): View
    {
        $context = $this->context($request, 'إدارة علاقات العملاء', 'Customer relationship management');
        $inbox = in_array('crm.write', $context['permissions'], true)
            ? $this->directory->inboxPage($request, ['page' => $request->query('inbox_page', '1')])
            : ['data' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0]];

        return view('crm.overview', $context + ['overview' => $this->directory->overview($request),
            'inbox' => $inbox['data'], 'inboxPaginator' => $this->paginator($request, $inbox, 'inbox_page')]);
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'stage', 'archived', 'page', 'per_page', 'source', 'assignee', 'priority', 'status']);
        $result = $this->directory->workspace($request, $filters);

        return view('crm.index', $this->context($request, 'العملاء المحتملون', 'Leads') + $this->stages($request, array_column($result['data'], 'stage_id')) + [
            'leads' => $this->paginator($request, $result), 'filters' => $filters,
            'metrics' => $result['metrics'], 'sources' => $result['sources'],
        ]);
    }

    public function workspace(Request $request, string $lead, string $section): View
    {
        $titles = ['activities' => ['الأنشطة', 'Activities'], 'notes' => ['الملاحظات', 'Notes'], 'tasks' => ['المهام', 'Tasks']];
        abort_unless(isset($titles[$section]), 404);
        $context = $this->context($request, ...$titles[$section]);
        $request->validate(['selected' => ['nullable', 'string', 'max:26']]);
        $result = $this->directory->workspaceSection($request, $lead, $section, $request->only(['q', 'kind', 'page', 'per_page']));
        $outcomes = $this->directory->taxonomyPage($request, ['kind' => 'outcome', 'page' => $request->query('outcome_page', '1'), 'q' => $request->query('outcome_q')]);

        return view('crm.workspace', $context + $this->stages($request, [$result['lead']['stage_id']]) + [
            'lead' => $result['lead'], 'section' => $section, 'rows' => $this->paginator($request, $result),
            'kindCounts' => $result['kind_counts'], 'outcomes' => $outcomes['data'],
            'outcomePaginator' => $this->paginator($request, $outcomes, 'outcome_page'),
            'selectedNote' => $section === 'notes' ? collect($result['data'])->firstWhere('id', $request->query('selected')) : null,
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->context($request, 'إضافة عميل محتمل', 'Create lead');
        $this->access->requirePermission($context['actor'], 'crm.manage');

        return view('crm.form', $context + $this->stages($request) + ['lead' => null]);
    }

    public function edit(Request $request, string $lead): View
    {
        $context = $this->context($request, 'تعديل العميل المحتمل', 'Edit lead');
        $this->access->requirePermission($context['actor'], 'crm.manage');
        $record = $this->directory->find($request, $lead, ['per_page' => 1]);
        abort_if($record['archived'], 409);

        return view('crm.form', $context + $this->stages($request, [$record['stage_id']]) + ['lead' => $record]);
    }

    /** @param array<string, mixed>|null $reveal */
    public function show(Request $request, string $lead, ?array $reveal = null): View
    {
        $context = $this->context($request, 'نظرة عامة', 'Overview');
        $context['pageSubtitle'] = $context['t']('ملخص شامل لبيانات العميل والأنشطة والمتابعات', 'A complete view of the lead, activity and follow-ups');
        $record = $this->directory->find($request, $lead, $request->only(['page', 'per_page']));
        $outcomes = $this->directory->taxonomyPage($request, ['kind' => 'outcome', 'page' => $request->query('outcome_page', '1'), 'q' => $request->query('outcome_q')]);
        $recipients = ['data' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0]];
        if (! $record['archived'] && $record['pending_transfer'] === null && (in_array('crm.transfer', $context['permissions'], true) || (in_array('crm.manage', $context['permissions'], true) && in_array('crm.assign', $context['permissions'], true)))) {
            $recipients = $this->directory->recipientPage($request, ['page' => $request->query('recipient_page', '1'), 'q' => $request->query('recipient_q')]);
        }

        return view('crm.show', $context + $this->stages($request, [$record['stage_id']]) + [
            'lead' => $record, 'reveal' => $reveal,
            'overviewLayout' => true,
            'activitySummary' => $this->directory->workspaceSection($request, $lead, 'tasks', ['per_page' => 1]),
            'activities' => $this->paginator($request, ['data' => $record['activities'], 'meta' => $record['activities_meta']]),
            'outcomes' => $outcomes['data'], 'outcomePaginator' => $this->paginator($request, $outcomes, 'outcome_page'),
            'recipients' => $recipients['data'], 'recipientPaginator' => $this->paginator($request, $recipients, 'recipient_page'),
        ]);
    }

    /** @return array<string, mixed> */
    private function context(Request $request, string $arabic, string $english): array
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        abort_unless($request->attributes->get('crm_html') === true, 404);
        $actor = $this->access->actor($request);
        $this->access->requirePermission($actor, 'crm.read');
        $locale = $request->query('locale') === 'en' ? 'en' : 'ar';
        app()->setLocale($locale);
        $t = static fn (string $ar, string $en): string => $locale === 'ar' ? $ar : $en;

        return ['actor' => $actor, 'permissions' => $actor['permissions'], 'locale' => $locale,
            't' => $t, 'title' => $t($arabic, $english), 'pageIcon' => 'users',
            'pageSubtitle' => $t('العملاء والمتابعات ضمن نطاق صلاحياتك', 'Leads and follow-ups within your authorized scope'),
            'breadcrumbs' => [['label' => $t('مركز العمل', 'Workspace'), 'url' => '/dashboard'], ['label' => $t('إدارة علاقات العملاء', 'CRM'), 'url' => '/crm?locale='.$locale], ['label' => $t($arabic, $english)]]];
    }

    /** @param list<string|null> $selectedIds
     * @return array<string, mixed>
     */
    private function stages(Request $request, array $selectedIds = []): array
    {
        $result = $this->directory->taxonomyPage($request, ['kind' => 'stage', 'page' => $request->query('stage_page', '1'), 'q' => $request->query('stage_q')]);

        $labels = array_column($result['data'], 'label', 'id');
        $missing = array_values(array_diff(array_filter($selectedIds, 'is_string'), array_keys($labels)));
        if ($missing !== []) {
            $labels += $this->directory->stageLabels($request, $missing);
        }

        return ['stages' => $result['data'], 'stageLabels' => $labels, 'stagePaginator' => $this->paginator($request, $result, 'stage_page')];
    }

    /** @param array<string, mixed> $result
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginator(Request $request, array $result, string $pageName = 'page'): LengthAwarePaginator
    {
        $meta = $result['meta'];
        $paginator = new LengthAwarePaginator($result['data'], $meta['total'], $meta['per_page'], $meta['page'], ['path' => $request->url(), 'pageName' => $pageName]);
        $paginator->appends($request->only(['locale', 'view', 'opportunity_page', 'q', 'kind', 'stage', 'archived', 'per_page', 'source', 'assignee', 'priority', 'status', 'stage_q', 'stage_page', 'outcome_q', 'outcome_page', 'recipient_q', 'recipient_page', 'inbox_page', 'lead_q', 'currency', 'probability_min', 'from', 'to', 'selected', 'closing_page', 'lead_page', 'contact_page', 'proposal_page', 'negotiation_page', 'review_page', 'closed_page']));

        return $paginator;
    }
}
