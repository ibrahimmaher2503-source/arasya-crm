<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use App\Modules\Sales\Application\QuotationDrafts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class QuotationController
{
    public function __construct(private QuotationDrafts $drafts) {}

    public function index(Request $request, string $lead): View
    {
        $data = $this->drafts->page($request, $lead);
        $request->validate(['selected' => 'nullable|string|max:26']);
        $selected = $data['quotations']->firstWhere('id', $request->query('selected'));

        return $this->view($request, 'sales.index', $data + ['selected' => $selected]);
    }

    public function create(Request $request, string $lead): View
    {
        return $this->view($request, 'sales.form', $this->drafts->context($request, $lead, true) + ['quotation' => null]);
    }

    public function edit(Request $request, string $lead, string $quotation): View
    {
        $this->drafts->context($request, $lead, true);

        return $this->view($request, 'sales.form', $this->drafts->find($request, $lead, $quotation));
    }

    public function preview(Request $request, string $lead, string $quotation): View
    {
        return $this->view($request, 'sales.preview', $this->drafts->find($request, $lead, $quotation));
    }

    public function store(Request $request, string $lead): JsonResponse|RedirectResponse
    {
        return $this->saved($request, $lead, $this->drafts->save($request, $lead), 201);
    }

    public function update(Request $request, string $lead, string $quotation): JsonResponse|RedirectResponse
    {
        return $this->saved($request, $lead, $this->drafts->save($request, $lead, $quotation), 200);
    }

    public function disabled(Request $request, string $lead, string $quotation): never
    {
        $this->drafts->find($request, $lead, $quotation);
        abort(403, 'Quotation approval and sending are disabled.');
    }

    /** @param array<string, mixed> $data */
    private function view(Request $request, string $template, array $data): View
    {
        abort_unless($request->attributes->get('crm_html') === true, 404);
        $locale = $request->query('locale') === 'en' ? 'en' : 'ar';
        app()->setLocale($locale);
        $t = static fn (string $ar, string $en): string => $locale === 'ar' ? $ar : $en;

        return view($template, $data + ['locale' => $locale, 't' => $t, 'title' => $t('العروض', 'Quotations'), 'pageIcon' => 'audit',
            'stageLabels' => app(LeadDirectory::class)->stageLabels($request, array_filter([$data['lead']['stage_id']])),
            'breadcrumbs' => [['label' => $t('مركز العمل', 'Workspace'), 'url' => '/dashboard'], ['label' => $t('إدارة علاقات العملاء', 'CRM'), 'url' => '/crm?locale='.$locale], ['label' => $t('العروض', 'Quotations')]]]);
    }

    /** @param array<string, mixed> $quotation */
    private function saved(Request $request, string $lead, array $quotation, int $status): JsonResponse|RedirectResponse
    {
        if ($request->attributes->get('crm_html') !== true) {
            return response()->json(['data' => $quotation], $status);
        }

        return redirect('/crm/leads/'.$lead.'/quotations?'.http_build_query(['selected' => $quotation['id'], 'locale' => $request->query('locale') === 'en' ? 'en' : 'ar']));
    }
}
