<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Runtime;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class LeadFiles extends LeadRuntime
{
    /** @return array<string, mixed> */
    public function files(Request $request, string $id): array
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($request, $id): array {
            $lead = $this->visibleLeads($actor)->where('id', $id)->first();
            if ($lead === null) {
                throw new NotFoundHttpException('Lead not found.');
            }
            $filters = $request->validate(['q' => 'nullable|string|max:200', 'category' => 'nullable|string|max:80', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100']);
            [$page, $perPage] = $this->pageInput(array_filter($filters, static fn ($value): bool => $value !== null));
            $query = DB::table('crm_files')->where('company_id', $actor['company_id'])->where('lead_id', $id);
            $bytes = (clone $query)->sum('size');
            $categories = (clone $query)->selectRaw('category, COUNT(*) as total')->groupBy('category')->orderBy('category')->limit(25)->get()->map(static fn ($row): array => (array) $row)->all();
            if (filled($filters['q'] ?? null)) {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('name', 'like', '%'.$filters['q'].'%')->orWhere('description', 'like', '%'.$filters['q'].'%');
                });
            }
            if (filled($filters['category'] ?? null)) {
                $query->where('category', $filters['category']);
            }
            $total = (clone $query)->count();
            $rows = $query->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)
                ->get(['id', 'name', 'mime', 'size', 'description', 'category', 'created_at', 'uploader_id'])->map(static fn ($row): array => (array) $row)->all();

            return ['lead' => $this->presentLead($lead), 'data' => $rows, 'meta' => $this->pageMeta($page, $perPage, $total), 'bytes' => (int) $bytes, 'categories' => $categories];
        });
    }

    /** @return array<string, mixed> */
    public function uploadFile(Request $request, string $id): array
    {
        $path = null;
        try {
            return $this->withActor($request, ['crm.read', 'crm.write'], function (array $actor) use ($request, $id, &$path): array {
                $lead = $this->visibleLeads($actor)->where('id', $id)->lockForUpdate()->first();
                if ($lead === null) {
                    throw new NotFoundHttpException('Lead not found.');
                }
                if ($lead->archived) {
                    throw new ConflictHttpException('Archived leads cannot be changed.');
                }
                $data = $request->validate(['version' => 'required|integer|min:0', 'description' => 'nullable|string|max:500', 'category' => 'nullable|string|max:80', 'file' => 'required|file|max:51200|mimes:pdf,png,jpg,jpeg,txt,docx,xlsx|extensions:pdf,png,jpg,jpeg,txt,docx,xlsx']);
                $this->assertVersion((int) $data['version'], (int) $lead->version);
                $upload = $request->file('file');
                if (! $upload instanceof UploadedFile) {
                    $this->invalid('file', 'Choose one file.');
                }
                $name = $upload->getClientOriginalName();
                if (mb_strlen($name) > 200 || preg_match('/[\x00-\x1f\x7f\\\\\/]/', $name)) {
                    $this->invalid('file', 'Use a safe file name.');
                }
                $fileId = $path === null ? (string) Str::ulid() : basename($path);
                $path = 'crm/'.$actor['company_id'].'/'.$id.'/'.$fileId;
                if ($upload->storeAs(dirname($path), $fileId, 'local') === false) {
                    throw new RuntimeException('Private file storage failed.');
                }
                $row = ['id' => $fileId, 'company_id' => $actor['company_id'], 'lead_id' => $id, 'uploader_id' => $actor['id'], 'name' => $name, 'path' => $path, 'mime' => $upload->getMimeType(), 'size' => $upload->getSize(), 'description' => $data['description'] ?? null, 'category' => $data['category'] ?? 'Other', 'created_at' => now()->toDateTimeString()];
                DB::table('crm_files')->insert($row);
                $this->advanceLead($actor, $lead, 'lead.file.uploaded', ['fields' => ['files']]);
                unset($row['path']);

                return $row;
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function downloadFile(Request $request, string $id, string $file): StreamedResponse
    {
        return $this->withReadActor($request, ['crm.read'], function (array $actor) use ($id, $file) {
            if (! $this->visibleLeads($actor)->where('id', $id)->exists()) {
                throw new NotFoundHttpException('Lead not found.');
            }
            $row = DB::table('crm_files')->where('company_id', $actor['company_id'])->where('lead_id', $id)->where('id', $file)->first();
            if ($row === null || ! Storage::disk('local')->exists($row->path)) {
                throw new NotFoundHttpException('File not found.');
            }

            $stream = Storage::disk('local')->readStream($row->path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Private file cannot be read.');
            }

            return response()->streamDownload(static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            }, $row->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        });
    }
}
