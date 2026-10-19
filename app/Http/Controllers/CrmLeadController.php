<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CrmLeadController
{
    public function index(Request $request, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        if ($request->attributes->get('crm_html') === true) {
            return app(CrmPageController::class)->index($request);
        }

        return response()->json($directory->paginate($request, $request->query->all()));
    }

    public function store(Request $request, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->save($request, $this->payload($request)), 201);
    }

    public function show(Request $request, string $lead, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        if ($request->attributes->get('crm_html') === true) {
            return app(CrmPageController::class)->show($request, $lead);
        }

        return response()->json(['data' => $directory->find($request, $lead, $request->query->all())]);
    }

    public function update(Request $request, string $lead, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->save($request, $this->payload($request), $lead));
    }

    public function archive(Request $request, string $lead, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->archive($request, $lead, $this->payload($request)));
    }

    public function assignment(Request $request, string $lead, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->assign($request, $lead, $this->payload($request)));
    }

    public function reveal(Request $request, string $lead, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        $revealed = $directory->reveal($request, $lead, $this->payload($request));

        return $request->attributes->get('crm_html') === true
            ? app(CrmPageController::class)->show($request, $lead, $revealed)
            : response()->json(['data' => $revealed]);
    }

    public function activity(Request $request, string $lead, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->activity($request, $lead, $this->payload($request)), 201);
    }

    public function transfer(Request $request, string $lead, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->requestTransfer($request, $lead, $this->payload($request)), 201);
    }

    public function convert(Request $request, string $lead, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->convert($request, $lead, $this->payload($request)));
    }

    public function taxonomy(Request $request, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return response()->json($directory->taxonomyPage($request, $request->query->all()));
    }

    public function saveTaxonomy(Request $request, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->saveTaxonomy($request, $this->payload($request)), 201);
    }

    public function recipients(Request $request, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return response()->json($directory->recipientPage($request, $request->query->all()));
    }

    public function inbox(Request $request, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return response()->json($directory->inboxPage($request, $request->query->all()));
    }

    public function respondTransfer(Request $request, string $transfer, LeadDirectory $directory): JsonResponse|RedirectResponse|View
    {
        return $this->result($request, $directory->respondTransfer($request, $transfer, $this->payload($request)));
    }

    /** @param array<string, mixed> $data */
    private function result(Request $request, array $data, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->attributes->get('crm_html') !== true) {
            return response()->json(['data' => $data], $status);
        }
        $locale = $request->query('locale') === 'en' ? 'en' : 'ar';
        $lead = $request->route('lead') ?? $data['lead_id'] ?? ($request->routeIs('crm.leads.store') ? ($data['id'] ?? null) : null);
        $destination = is_string($lead) ? '/crm/leads/'.rawurlencode($lead) : '/crm';
        if ($request->routeIs('crm.transfers.response')) {
            $destination = '/crm';
        }

        return redirect($destination.'?locale='.$locale)->with('status', 'Saved');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $payload = array_replace_recursive(
            $request->isJson() ? $request->json()->all() : $request->request->all(),
            $request->allFiles(),
        );
        unset($payload['_token'], $payload['_method']);

        return $payload;
    }
}
