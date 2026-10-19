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
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CrmFileController
{
    public function __construct(private LeadDirectory $directory, private AccessControl $access) {}

    public function index(Request $request, string $lead): View
    {
        abort_unless($request->attributes->get('crm_html') === true, 404);
        $result = $this->directory->files($request, $lead);
        $actor = $this->access->actor($request);
        $locale = $request->query('locale') === 'en' ? 'en' : 'ar';
        app()->setLocale($locale);
        $t = static fn (string $ar, string $en): string => $locale === 'ar' ? $ar : $en;
        $meta = $result['meta'];
        $files = new LengthAwarePaginator($result['data'], $meta['total'], $meta['per_page'], $meta['page'], ['path' => $request->url()]);
        $files->appends($request->only(['q', 'category', 'locale', 'per_page']));

        return view('crm.files', ['actor' => $actor, 'permissions' => $this->access->permissions((string) $actor['id']), 'locale' => $locale, 't' => $t, 'title' => $t('الملفات', 'Files'), 'pageIcon' => 'audit', 'lead' => $result['lead'], 'files' => $files, 'categories' => $result['categories'], 'bytes' => $result['bytes'],
            'stageLabels' => $this->directory->stageLabels($request, array_filter([$result['lead']['stage_id']])),
            'breadcrumbs' => [['label' => $t('مركز العمل', 'Workspace'), 'url' => '/dashboard'], ['label' => $t('إدارة علاقات العملاء', 'CRM'), 'url' => '/crm?locale='.$locale], ['label' => $t('الملفات', 'Files')]]]);
    }

    public function store(Request $request, string $lead): JsonResponse|RedirectResponse
    {
        $result = $this->directory->uploadFile($request, $lead);
        if ($request->attributes->get('crm_html') !== true) {
            return response()->json(['data' => $result], 201);
        }

        return redirect('/crm/leads/'.$lead.'/files?locale='.($request->query('locale') === 'en' ? 'en' : 'ar'))->with('status', 'Saved');
    }

    public function download(Request $request, string $lead, string $file): StreamedResponse
    {
        return $this->directory->downloadFile($request, $lead, $file);
    }
}
