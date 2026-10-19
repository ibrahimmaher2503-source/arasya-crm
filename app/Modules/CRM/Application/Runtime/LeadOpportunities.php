<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Runtime;

use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final readonly class LeadOpportunities extends LeadRuntime
{
    public const OPPORTUNITY_STAGES = ['lead', 'contact', 'proposal', 'negotiation', 'review', 'closed'];

    /** @return array<string, mixed> */
    public function pipeline(Request $request): array
    {
        abort_unless(app()->environment('testing') && config('sales.synthetic_drafts') === true, 404);

        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($request): array {
            $rules = ['q' => 'nullable|string|max:200', 'currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/D'], 'selected' => 'nullable|string|max:26', 'stage' => 'nullable|in:'.implode(',', self::OPPORTUNITY_STAGES), 'probability_min' => 'nullable|integer|min:0|max:100', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'closing_page' => 'nullable|integer|min:1|max:1000000'];
            foreach (self::OPPORTUNITY_STAGES as $stage) {
                $rules[$stage.'_page'] = 'nullable|integer|min:1|max:1000000';
            }
            $filters = $request->validate($rules);
            $currency = $filters['currency'] ?? 'SAR';
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
            $columns = [];
            foreach (self::OPPORTUNITY_STAGES as $stage) {
                $columns[$stage] = (clone $query)->where('opportunities.stage', $stage)->orderByDesc('opportunities.created_at')->orderByDesc('opportunities.id')
                    ->paginate(25, ['opportunities.*', 'leads.name as lead_name', 'leads.organization'], $stage.'_page')->withQueryString();
            }
            $selected = isset($filters['selected']) ? (clone $query)->where('opportunities.id', $filters['selected'])->first(['opportunities.*']) : null;
            $upcoming = (clone $query)->where('opportunities.stage', '!=', 'closed')->whereNotNull('opportunities.expected_close')->orderBy('opportunities.expected_close')->orderBy('opportunities.id')->paginate(10, ['opportunities.*', 'leads.organization', 'leads.name as lead_name'], 'closing_page')->withQueryString();
            $overdue = (clone $query)->where('opportunities.stage', '!=', 'closed')->where('opportunities.expected_close', '<', now()->toDateString())->count();
            $overdueRows = (clone $query)->where('opportunities.stage', '!=', 'closed')->where('opportunities.expected_close', '<', now()->toDateString())->orderBy('opportunities.expected_close')->orderBy('opportunities.id')->limit(10)->get(['opportunities.*', 'leads.organization', 'leads.name as lead_name']);

            return ['columns' => $columns, 'stageTotals' => $stageTotals, 'total' => $total, 'openAmount' => (string) $openAmount, 'currency' => $currency, 'selected' => $selected, 'upcoming' => $upcoming, 'overdue' => $overdue, 'overdueRows' => $overdueRows];
        });
    }

    /** @return array<string, mixed> */
    public function saveOpportunity(Request $request, ?string $id = null): array
    {
        abort_unless(app()->environment('testing') && config('sales.synthetic_drafts') === true, 404);

        return $this->withActor($request, ['crm.read', 'crm.write'], function (array $actor) use ($request, $id): array {
            $input = $request->except(['_token', '_method', 'locale']);
            $this->onlyKeys($input, ['lead_id', 'title', 'amount', 'currency', 'stage', 'probability', 'expected_close', 'version']);
            $before = null;
            if ($id !== null) {
                $before = DB::table('crm_opportunities')->where('id', $id)->where('company_id', $actor['company_id'])->whereIn('lead_id', $this->visibleLeads($actor)->select('id'))->lockForUpdate()->first();
                if ($before === null) {
                    abort(404);
                }
                if (isset($input['lead_id']) && $input['lead_id'] !== $before->lead_id) {
                    $this->invalid('lead_id', 'The opportunity lead cannot be changed.');
                }
                $input['lead_id'] = $before->lead_id;
            }
            $data = Validator::make($input, ['lead_id' => 'required|string|max:26', 'title' => 'required|string|max:200', 'amount' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?$/D'], 'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'], 'stage' => 'required|in:'.implode(',', self::OPPORTUNITY_STAGES), 'probability' => 'required|integer|min:0|max:100', 'expected_close' => 'nullable|date_format:Y-m-d', 'version' => ($id === null ? 'nullable' : 'required').'|integer|min:0'])->validate();
            $lead = $this->visibleLeads($actor)->where('id', $data['lead_id'])->lockForUpdate()->first();
            if ($lead === null) {
                abort(404);
            }
            abort_if($lead->archived, 409);
            if ($before !== null) {
                abort_unless((int) $before->version === (int) $data['version'], 409);
            }
            $values = ['lead_id' => $data['lead_id'], 'title' => $data['title'], 'amount' => $data['amount'], 'currency' => $data['currency'], 'stage' => $data['stage'], 'probability' => (int) $data['probability'], 'expected_close' => $data['expected_close'] ?? null, 'version' => $before === null ? 0 : (int) $before->version + 1, 'updated_at' => now()->toDateTimeString()];
            if ($id === null) {
                $id = (string) Str::ulid();
                DB::table('crm_opportunities')->insert($values + ['id' => $id, 'company_id' => $actor['company_id'], 'created_by' => $actor['id'], 'created_at' => now()->toDateTimeString()]);
            } else {
                DB::table('crm_opportunities')->where('id', $id)->where('company_id', $actor['company_id'])->update($values);
            }
            $this->recordAudit($actor, $id, 'opportunity.saved', ['version' => $values['version']]);

            return ['id' => $id] + $values;
        });
    }
}
