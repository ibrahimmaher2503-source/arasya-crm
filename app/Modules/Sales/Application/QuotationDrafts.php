<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use App\Modules\Identity\Application\SEC04\AccessControl;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class QuotationDrafts
{
    public function __construct(private LeadDirectory $leads, private AccessControl $access) {}

    /** @param array<mixed> $lines
     * @return array<string, string>
     */
    public function calculate(array $lines): array
    {
        Validator::make(['lines' => $lines], ['lines' => 'required|array|min:1|max:50', 'lines.*' => 'required|array:description,quantity,unit_price,discount,tax_rate', 'lines.*.description' => 'required|string|max:200'])->validate();
        $subtotal = $discount = $tax = BigDecimal::of('0');
        foreach ($lines as $index => $line) {
            $numbers = [];
            foreach (['quantity', 'unit_price', 'discount', 'tax_rate'] as $field) {
                $value = $line[$field] ?? null;
                if (! is_string($value) || preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?$/D', $value) !== 1) {
                    throw ValidationException::withMessages(['lines.'.$index.'.'.$field => 'Use an explicit non-negative decimal string with up to six decimal places.']);
                }
                $numbers[$field] = BigDecimal::of($value);
            }
            $gross = $numbers['quantity']->multipliedBy($numbers['unit_price']);
            if ($numbers['quantity']->isZero() || $numbers['discount']->compareTo($gross) > 0 || $numbers['tax_rate']->compareTo('100') > 0) {
                throw ValidationException::withMessages(['lines.'.$index => 'Check quantity, discount and tax rate.']);
            }
            $subtotal = $subtotal->plus($gross);
            $discount = $discount->plus($numbers['discount']);
            $tax = $tax->plus($gross->minus($numbers['discount'])->multipliedBy($numbers['tax_rate'])->dividedByExact('100'));
        }

        return ['subtotal' => (string) $subtotal, 'discount' => (string) $discount, 'tax' => (string) $tax, 'total' => (string) $subtotal->minus($discount)->plus($tax)];
    }

    /** @return array<string, mixed> */
    public function context(Request $request, string $lead, bool $write = false): array
    {
        abort_unless(app()->environment('testing') && config('sales.synthetic_drafts') === true, 404);
        $actor = $this->access->actor($request);
        $this->access->requirePermission($actor, 'crm.read');
        if ($write) {
            $this->access->requirePermission($actor, 'crm.write');
        }
        $record = $this->leads->find($request, $lead, ['per_page' => 1]);
        abort_if($write && $record['archived'], 409);

        return ['actor' => $actor, 'lead' => $record, 'permissions' => $this->access->permissions((string) $actor['id'])];
    }

    /** @return array<string, mixed> */
    public function page(Request $request, string $lead): array
    {
        $context = $this->context($request, $lead);
        $filters = $request->validate(['q' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1|max:1000000']);
        $query = DB::table('quotation_drafts')->where('company_id', $context['actor']['company_id'])->where('lead_id', $lead);
        if (filled($filters['q'] ?? null)) {
            $query->where('title', 'like', '%'.$filters['q'].'%');
        }
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $rows->through(fn ($row): array => $this->present($row));

        return $context + ['quotations' => $rows];
    }

    /** @return array<string, mixed> */
    public function find(Request $request, string $lead, string $id): array
    {
        $context = $this->context($request, $lead);
        $row = DB::table('quotation_drafts')->where('company_id', $context['actor']['company_id'])->where('lead_id', $lead)->where('id', $id)->first();
        if ($row === null) {
            abort(404);
        }

        return $context + ['quotation' => $this->present($row)];
    }

    /** @return array<string, mixed> */
    public function save(Request $request, string $lead, ?string $id = null): array
    {
        $initial = $this->context($request, $lead, true);

        return DB::transaction(function () use ($request, $lead, $id, $initial): array {
            DB::table('access_companies')->where('id', $initial['actor']['company_id'])->lockForUpdate()->first();
            $context = $this->context($request, $lead, true);
            abort_unless($initial['actor']['id'] === $context['actor']['id'] && $initial['actor']['company_id'] === $context['actor']['company_id'], 403);
            $input = $request->except(['_token', '_method', 'locale']);
            if (array_diff(array_keys($input), ['title', 'currency', 'lines', 'version']) !== []) {
                throw ValidationException::withMessages(['data' => 'Only draft fields can be changed.']);
            }
            $data = Validator::make($input, ['title' => 'required|string|max:200', 'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'], 'lines' => 'required|array|min:1|max:50', 'version' => ($id === null ? 'nullable' : 'required').'|integer|min:0'])->validate();
            $data['lines'] = array_values($data['lines']);
            $totals = $this->calculate($data['lines']);
            $before = null;
            if ($id !== null) {
                $before = DB::table('quotation_drafts')->where('id', $id)->where('company_id', $context['actor']['company_id'])->where('lead_id', $lead)->lockForUpdate()->first();
                if ($before === null) {
                    abort(404);
                }
                abort_unless($before->status === 'draft' && (int) $before->version === (int) $data['version'], 409);
            }
            $values = ['title' => $data['title'], 'currency' => $data['currency'], 'lines' => json_encode($data['lines'], JSON_THROW_ON_ERROR), 'totals' => json_encode($totals, JSON_THROW_ON_ERROR), 'status' => 'draft', 'version' => $before === null ? 0 : (int) $before->version + 1, 'updated_at' => now()->toDateTimeString()];
            if ($id === null) {
                $id = (string) Str::ulid();
                DB::table('quotation_drafts')->insert($values + ['id' => $id, 'company_id' => $context['actor']['company_id'], 'lead_id' => $lead, 'created_by' => $context['actor']['id'], 'created_at' => now()->toDateTimeString()]);
            } else {
                DB::table('quotation_drafts')->where('id', $id)->where('company_id', $context['actor']['company_id'])->update($values);
            }
            $this->access->record($context['actor'], 'quotation.draft.saved', $id, ['version' => $values['version']]);

            return $this->find($request, $lead, $id)['quotation'];
        }, 3);
    }

    /** @return array<string, mixed> */
    private function present(\stdClass $row): array
    {
        $result = (array) $row;
        $result['lines'] = array_values(json_decode($row->lines, true, 512, JSON_THROW_ON_ERROR));
        $result['totals'] = json_decode($row->totals, true, 512, JSON_THROW_ON_ERROR);

        return $result;
    }
}
