<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Runtime;

use App\Modules\Sales\Application\OpportunityTransitions;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final readonly class LeadOpportunities extends LeadRuntime
{
    public const OPPORTUNITY_STAGES = OpportunityTransitions::STAGES;

    public const WORK_TYPES = ['product_only', 'supply_installation', 'maintenance', 'site_inspection'];

    private const SNAPSHOT_FIELDS = ['id', 'lead_id', 'title', 'amount', 'currency', 'stage', 'probability', 'expected_close', 'work_type', 'version', 'updated_at'];

    private const PUBLIC_FIELDS = [...self::SNAPSHOT_FIELDS, 'company_id', 'created_by', 'created_at'];

    /** @return array<string, mixed> */
    public function pipeline(Request $request): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('sales.synthetic_drafts') === true, 404);

        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($request): array {
            $rules = ['q' => 'nullable|string|max:200', 'currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/D'], 'selected' => 'nullable|string|max:26', 'stage' => 'nullable|in:'.implode(',', self::OPPORTUNITY_STAGES), 'probability_min' => 'nullable|integer|min:0|max:100', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'closing_page' => 'nullable|integer|min:1|max:1000000', 'history_page' => 'nullable|integer|min:1|max:1000000'];
            foreach (self::OPPORTUNITY_STAGES as $stage) {
                $rules[$stage.'_page'] = 'nullable|integer|min:1|max:1000000';
            }
            $rules['view'] = 'nullable|in:board,directory';
            $rules['opportunity_page'] = 'nullable|integer|min:1|max:1000000';
            $rules['source'] = 'nullable|in:lead,customer';
            $rules['lead_q'] = 'nullable|string|max:120';
            $filters = $request->validate($rules);
            $currency = $filters['currency'] ?? 'SAR';
            $customerOptions = null;
            if (($filters['source'] ?? null) === 'customer') {
                $uniqueLinks = $this->visibleLeads($actor)->where('archived', false)->whereNotNull('customer_id')
                    ->select('customer_id')->groupBy('customer_id')->havingRaw('COUNT(*) = 1');
                $customers = DB::table('crm_customers')->where('company_id', $actor['company_id'])->whereIn('id', $uniqueLinks);
                if (filled($filters['lead_q'] ?? null)) {
                    $customers->where(function ($query) use ($filters): void {
                        $query->where('name', 'like', '%'.$filters['lead_q'].'%')->orWhere('organization', 'like', '%'.$filters['lead_q'].'%');
                    });
                }
                $customerOptions = $customers->orderByDesc('created_at')->orderByDesc('id')
                    ->paginate(25, ['id as customer_id', 'name', 'organization'], 'lead_page')->withQueryString();
            }
            $visible = $this->visibleLeads($actor)->where('archived', false)->select('crm_leads.id');
            $query = DB::table('crm_opportunities as opportunities')
                ->where('opportunities.company_id', $actor['company_id'])->whereIn('opportunities.lead_id', $visible)
                ->join('crm_leads as leads', 'leads.id', '=', 'opportunities.lead_id')->where('opportunities.currency', $currency);
            if (filled($filters['q'] ?? null)) {
                $query->where('opportunities.title', 'like', '%'.$filters['q'].'%');
            }
            if (filled($filters['stage'] ?? null)) {
                $query->where('opportunities.stage', $filters['stage']);
            }
            if (isset($filters['probability_min'])) {
                $query->where('opportunities.probability', '>=', $filters['probability_min']);
            }
            if (isset($filters['from'])) {
                $query->where('opportunities.expected_close', '>=', $filters['from']);
            }
            if (isset($filters['to'])) {
                $query->where('opportunities.expected_close', '<=', $filters['to']);
            }
            $total = (clone $query)->count();
            $openAmount = BigDecimal::of('0');
            $stageTotals = array_fill_keys(self::OPPORTUNITY_STAGES, '0');
            foreach ((clone $query)->select(['opportunities.amount', 'opportunities.stage'])->cursor() as $row) {
                $stageTotals[$row->stage] = (string) BigDecimal::of($stageTotals[$row->stage])->plus($row->amount);
                if ($row->stage !== 'closed') {
                    $openAmount = $openAmount->plus($row->amount);
                }
            }
            $publicColumns = array_map(static fn (string $field): string => 'opportunities.'.$field, self::PUBLIC_FIELDS);
            $columns = [];
            foreach (self::OPPORTUNITY_STAGES as $stage) {
                $columns[$stage] = (clone $query)->where('opportunities.stage', $stage)->orderByDesc('opportunities.created_at')->orderByDesc('opportunities.id')
                    ->paginate(25, [...$publicColumns, 'leads.name as lead_name', 'leads.organization'], $stage.'_page')->withQueryString();
            }
            $selected = isset($filters['selected']) ? (clone $query)->where('opportunities.id', $filters['selected'])->first($publicColumns) : null;
            $directory = (clone $query)->orderByDesc('opportunities.created_at')->orderByDesc('opportunities.id')
                ->paginate(25, [...$publicColumns, 'leads.name as lead_name', 'leads.organization'], 'opportunity_page')->withQueryString();
            $selectedContext = null;
            if ($selected !== null) {
                $selectedContext = (clone $query)->where('opportunities.id', $selected->id)
                    ->first(['opportunities.work_type', 'opportunities.requirements', 'leads.customer_id', 'leads.assignee_id', 'leads.branch_id']);
                if ($selectedContext === null) {
                    abort(404);
                }
                $selectedContext->requirements = $selectedContext->requirements === null ? null : Crypt::decryptString($selectedContext->requirements);
            }
            $upcoming = (clone $query)->where('opportunities.stage', '!=', 'closed')->whereNotNull('opportunities.expected_close')->orderBy('opportunities.expected_close')->orderBy('opportunities.id')->paginate(10, [...$publicColumns, 'leads.organization', 'leads.name as lead_name'], 'closing_page')->withQueryString();
            $overdue = (clone $query)->where('opportunities.stage', '!=', 'closed')->where('opportunities.expected_close', '<', now()->toDateString())->count();
            $overdueRows = (clone $query)->where('opportunities.stage', '!=', 'closed')->where('opportunities.expected_close', '<', now()->toDateString())->orderBy('opportunities.expected_close')->orderBy('opportunities.id')->limit(10)->get([...$publicColumns, 'leads.organization', 'leads.name as lead_name']);

            $history = $selected === null ? null : DB::table('crm_opportunity_history')
                ->where('company_id', $actor['company_id'])->where('opportunity_id', $selected->id)
                ->orderByDesc('version')->paginate(25, ['id', 'actor_id', 'version', 'snapshot', 'created_at', 'action', 'from_stage', 'reason_provided'], 'history_page')->withQueryString();

            return ['customerOptions' => $customerOptions, 'directory' => $directory, 'selectedContext' => $selectedContext, 'transitionOptions' => $selected === null ? [] : app(OpportunityTransitions::class)->openTargets($selected->stage), 'history' => $history, 'columns' => $columns, 'stageTotals' => $stageTotals, 'total' => $total, 'openAmount' => (string) $openAmount, 'currency' => $currency, 'selected' => $selected, 'upcoming' => $upcoming, 'overdue' => $overdue, 'overdueRows' => $overdueRows];
        });
    }

    /** @return array<string, mixed> */
    public function saveOpportunity(Request $request, ?string $id = null): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('sales.synthetic_drafts') === true, 404);

        return $this->withActor($request, ['crm.read', 'crm.write'], function (array $actor) use ($request, $id): array {
            $input = $request->except(['_token', '_method', 'locale']);
            $this->onlyKeys($input, ['lead_id', 'title', 'amount', 'currency', 'stage', 'probability', 'expected_close', 'version', 'work_type', 'requirements', ...($id === null ? ['submission_key', 'customer_id'] : [])]);
            $before = null;
            if ($id !== null) {
                $before = DB::table('crm_opportunities')->where('id', $id)->where('company_id', $actor['company_id'])->whereIn('lead_id', $this->visibleLeads($actor)->select('id'))->lockForUpdate()->first();
                if ($before === null) {
                    abort(404);
                }
                abort_if($before->stage === 'closed', 409);
                if (isset($input['lead_id']) && $input['lead_id'] !== $before->lead_id) {
                    $this->invalid('lead_id', 'The opportunity lead cannot be changed.');
                }
                $input['lead_id'] = $before->lead_id;
            }
            $data = Validator::make($input, ['lead_id' => 'required_without:customer_id|prohibits:customer_id|nullable|string|max:26', 'customer_id' => 'required_without:lead_id|nullable|string|max:26', 'work_type' => 'nullable|in:'.implode(',', self::WORK_TYPES), 'requirements' => 'nullable|string|max:4000', 'title' => 'required|string|max:200', 'amount' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?$/D'], 'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'], 'stage' => 'required|in:'.implode(',', self::OPPORTUNITY_STAGES), 'probability' => 'required|integer|min:0|max:100', 'expected_close' => 'nullable|date_format:Y-m-d', 'version' => ($id === null ? 'nullable' : 'required').'|integer|min:0', 'submission_key' => 'nullable|uuid'])->validate();
            if (isset($data['customer_id'])) {
                $links = $this->visibleLeads($actor)->where('archived', false)
                    ->where('customer_id', $data['customer_id'])
                    ->whereIn('customer_id', DB::table('crm_customers')->where('company_id', $actor['company_id'])->select('id'))
                    ->limit(2)->lockForUpdate()->pluck('id');
                abort_if($links->isEmpty(), 404);
                abort_unless($links->count() === 1, 409);
                $data['lead_id'] = $links->first();
            }
            $lead = $this->visibleLeads($actor)->where('id', $data['lead_id'])->lockForUpdate()->first();
            if ($lead === null) {
                abort(404);
            }
            abort_if($lead->archived, 409);
            if ($before !== null) {
                abort_unless((int) $before->version === (int) $data['version'], 409);
                if ($data['stage'] !== $before->stage) {
                    $this->invalid('stage', 'Use the dedicated stage transition operation.');
                }
            }
            $values = ['lead_id' => $data['lead_id'], 'title' => $data['title'], 'amount' => $data['amount'], 'currency' => $data['currency'], 'stage' => $data['stage'], 'probability' => (int) $data['probability'], 'expected_close' => $data['expected_close'] ?? null, 'version' => $before === null ? 0 : (int) $before->version + 1, 'updated_at' => now()->toDateTimeString()];
            $values['work_type'] = array_key_exists('work_type', $data) ? $data['work_type'] : ($before->work_type ?? null);
            $requirements = array_key_exists('requirements', $data) ? $data['requirements'] : null;
            $values['requirements'] = array_key_exists('requirements', $data)
                ? ($requirements === null ? null : Crypt::encryptString($requirements))
                : ($before->requirements ?? null);
            $submission = ['submission_key' => null, 'submission_hash' => null];
            if ($id === null && isset($data['submission_key'])) {
                $canonical = array_intersect_key($values, array_flip(['lead_id', 'title', 'amount', 'currency', 'stage', 'probability', 'expected_close']));
                $canonical['amount'] = (string) BigDecimal::of($canonical['amount'])->strippedOfTrailingZeros();
                // Omitted optional context keeps hashes from existing submissions valid.
                if ($values['work_type'] !== null || $requirements !== null) {
                    $canonical['work_type'] = $values['work_type'];
                    $canonical['requirements'] = $requirements;
                }
                $canonicalJson = json_encode($canonical, JSON_THROW_ON_ERROR);
                $submission = ['submission_key' => strtolower($data['submission_key']), 'submission_hash' => array_key_exists('requirements', $canonical)
                    ? hash_hmac('sha256', $canonicalJson, config('app.key'))
                    : hash('sha256', $canonicalJson)];
                $existing = DB::table('crm_opportunities')->where('company_id', $actor['company_id'])
                    ->where('created_by', $actor['id'])->where('submission_key', $submission['submission_key'])->lockForUpdate()->first();
                if ($existing !== null) {
                    abort_unless(hash_equals((string) $existing->submission_hash, $submission['submission_hash']), 409);

                    return array_intersect_key((array) $existing, array_flip(self::SNAPSHOT_FIELDS));
                }
            }
            if ($id === null) {
                app(OpportunityTransitions::class)->assertInitialStage($data['stage']);
                $id = (string) Str::ulid();
                DB::table('crm_opportunities')->insert($values + $submission + ['id' => $id, 'company_id' => $actor['company_id'], 'created_by' => $actor['id'], 'created_at' => now()->toDateTimeString()]);
            } else {
                DB::table('crm_opportunities')->where('id', $id)->where('company_id', $actor['company_id'])->update($values);
            }
            DB::table('crm_opportunity_history')->insert([
                'id' => (string) Str::ulid(), 'company_id' => $actor['company_id'], 'opportunity_id' => $id,
                'actor_id' => $actor['id'], 'version' => $values['version'],
                'snapshot' => json_encode(array_intersect_key(['id' => $id] + $values, array_flip(self::SNAPSHOT_FIELDS)), JSON_THROW_ON_ERROR), 'created_at' => now()->toDateTimeString(),
            ]);
            $this->recordAudit($actor, $id, 'opportunity.saved', ['version' => $values['version']]);

            return array_intersect_key(['id' => $id] + $values, array_flip(self::SNAPSHOT_FIELDS));
        });
    }

    /** @return array<string, mixed> */
    public function transitionOpportunity(Request $request, string $id): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('sales.synthetic_drafts') === true, 404);

        return $this->withActor($request, ['crm.read', 'crm.write'], function (array $actor) use ($request, $id): array {
            $input = $request->except(['_token', '_method', 'locale']);
            $this->onlyKeys($input, ['target_stage', 'version', 'submission_key', 'reason', 'outcome']);
            $data = Validator::make($input, [
                'target_stage' => 'required|in:'.implode(',', self::OPPORTUNITY_STAGES),
                'version' => 'required|integer|min:0', 'submission_key' => 'required|uuid',
                'reason' => 'nullable|string|max:1000', 'outcome' => 'nullable|in:won,lost',
            ])->validate();
            $before = DB::table('crm_opportunities')->where('id', $id)->where('company_id', $actor['company_id'])
                ->whereIn('lead_id', $this->visibleLeads($actor)->select('id'))->lockForUpdate()->first();
            if ($before === null) {
                abort(404);
            }
            $lead = $this->visibleLeads($actor)->where('id', $before->lead_id)->lockForUpdate()->first();
            if ($lead === null) {
                abort(404);
            }
            abort_if($lead->archived, 409);
            $reason = trim($data['reason'] ?? '');
            $canonical = ['opportunity_id' => $id, 'target_stage' => $data['target_stage'], 'version' => (int) $data['version'], 'reason' => $reason, 'outcome' => $data['outcome'] ?? null];
            $key = strtolower($data['submission_key']);
            $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
            $previous = DB::table('crm_opportunity_history')->where('company_id', $actor['company_id'])
                ->where('actor_id', $actor['id'])->where('transition_key', $key)->first();
            if ($previous !== null) {
                abort_unless(hash_equals((string) $previous->transition_hash, $hash), 409);

                return array_intersect_key((array) $before, array_flip(self::SNAPSHOT_FIELDS));
            }
            abort_unless((int) $before->version === (int) $data['version'], 409);
            app(OpportunityTransitions::class)->assertAllowed($before->stage, $data['target_stage'], $reason, $data['outcome'] ?? null);
            $values = ['stage' => $data['target_stage'], 'version' => (int) $before->version + 1, 'updated_at' => now()->toDateTimeString()];
            $updated = DB::table('crm_opportunities')->where('id', $id)->where('company_id', $actor['company_id'])
                ->where('version', $before->version)->update($values);
            abort_unless($updated === 1, 409);
            $snapshot = array_intersect_key(array_replace((array) $before, $values), array_flip(self::SNAPSHOT_FIELDS));
            DB::table('crm_opportunity_history')->insert([
                'id' => (string) Str::ulid(), 'company_id' => $actor['company_id'], 'opportunity_id' => $id,
                'actor_id' => $actor['id'], 'version' => $values['version'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'created_at' => now()->toDateTimeString(), 'action' => 'transitioned', 'from_stage' => $before->stage,
                'reason_provided' => $reason !== '', 'transition_key' => $key, 'transition_hash' => $hash,
            ]);
            $metadata = ['version' => $values['version'], 'reason_provided' => $reason !== ''];
            if ($reason !== '') {
                $metadata['encrypted_reason'] = Crypt::encryptString($reason);
            }
            $this->recordAudit($actor, $id, 'opportunity.transitioned', $metadata);

            return $snapshot;
        });
    }

    public function reopenOpportunity(Request $request, string $id): never
    {
        abort_unless(app()->environment(['local', 'testing']) && config('sales.synthetic_drafts') === true, 404);

        $this->withActor($request, ['crm.read', 'crm.write'], function (array $actor) use ($request, $id): never {
            $input = $request->except(['_token', '_method', 'locale']);
            $this->onlyKeys($input, ['version', 'submission_key', 'reason']);
            Validator::make($input, ['version' => 'required|integer|min:0', 'submission_key' => 'required|uuid', 'reason' => 'required|string|max:1000'])->validate();
            $record = DB::table('crm_opportunities')->where('company_id', $actor['company_id'])->where('id', $id)
                ->whereIn('lead_id', $this->visibleLeads($actor)->select('id'))->lockForUpdate()->first();
            abort_if($record === null, 404);
            // Closing/reopening authority is unresolved; no role can activate it.
            abort(403);
        });
        abort(403);
    }
}
