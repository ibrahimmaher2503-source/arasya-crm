<?php

declare(strict_types=1);

namespace App\Modules\Help\Application;

use App\Modules\Identity\Domain\SEC01\ActorAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class TutorialProgress
{
    public function __construct(private HelpCatalog $catalog) {}

    /** @return array{tour_id: string, version: int, step_key: ?string, status: ?string} */
    public function read(ActorAccess $actor, string $tourId, string $locale): array
    {
        $tour = $this->catalog->tour($actor, $tourId, $locale);
        $version = (int) $tour['version'];
        $row = DB::table('tutorial_progress')
            ->where('company_id', $actor->entityId)
            ->where('user_id', $actor->actorId)
            ->where('tour_id', $tourId)
            ->first(['version', 'step_key', 'status']);

        if ($row === null
            || (int) $row->version !== $version
            || ! in_array((string) $row->step_key, $this->catalog->stepKeys($tourId), true)
            || ! in_array((string) $row->status, ['paused', 'in_progress', 'completed', 'skipped'], true)) {
            return ['tour_id' => $tourId, 'version' => $version, 'step_key' => null, 'status' => null];
        }

        return [
            'tour_id' => $tourId,
            'version' => $version,
            'step_key' => (string) $row->step_key,
            'status' => (string) $row->status,
        ];
    }

    /** @param array{version: int, step_key: string, status: string} $input
     * @return array{tour_id: string, version: int, step_key: string, status: string}
     */
    public function save(ActorAccess $actor, string $tourId, array $input, string $locale): array
    {
        $tour = $this->catalog->tour($actor, $tourId, $locale);
        $version = (int) $tour['version'];
        if ($input['version'] !== $version) {
            throw new ConflictHttpException('The tutorial version has changed.');
        }
        $stepKeys = $this->catalog->stepKeys($tourId);
        if (! in_array($input['step_key'], $stepKeys, true)) {
            throw ValidationException::withMessages(['step_key' => __('help.errors.invalid_progress', [], $locale)]);
        }
        if (! in_array($input['status'], ['paused', 'in_progress', 'completed', 'skipped'], true)) {
            throw ValidationException::withMessages(['status' => __('help.errors.invalid_progress', [], $locale)]);
        }
        if ($input['status'] === 'completed' && $input['step_key'] !== end($stepKeys)) {
            throw ValidationException::withMessages(['status' => __('help.errors.invalid_progress', [], $locale)]);
        }

        $now = now()->toDateTimeString();
        DB::table('tutorial_progress')->upsert([[
            'company_id' => $actor->entityId,
            'user_id' => $actor->actorId,
            'tour_id' => $tourId,
            'version' => $version,
            'step_key' => $input['step_key'],
            'status' => $input['status'],
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['company_id', 'user_id', 'tour_id'], ['version', 'step_key', 'status', 'updated_at']);

        return ['tour_id' => $tourId, 'version' => $version, 'step_key' => $input['step_key'], 'status' => $input['status']];
    }
}
