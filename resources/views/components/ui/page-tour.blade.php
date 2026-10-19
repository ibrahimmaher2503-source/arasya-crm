@props(['id', 'locale' => 'ar', 'autostart' => false, 'actor' => null, 'permissions' => null])

@php
    $tourConfig = null;
    $tourActor = $actor ?? null;

    if (! $tourActor instanceof \App\Modules\Identity\Domain\SEC01\ActorAccess && is_array($tourActor) && isset($tourActor['id'])) {
        $grants = is_array($permissions ?? null) ? $permissions : (is_array($tourActor['permissions'] ?? null) ? $tourActor['permissions'] : []);
        $tourActor = new \App\Modules\Identity\Domain\SEC01\ActorAccess(
            (string) $tourActor['id'],
            (string) ($tourActor['company_id'] ?? ''),
            (string) ($tourActor['department_id'] ?? ''),
            array_values(array_filter($grants, 'is_string')),
            [],
            isset($tourActor['branch_id']) ? (string) $tourActor['branch_id'] : null,
        );
    }

    if ($tourActor instanceof \App\Modules\Identity\Domain\SEC01\ActorAccess
        && \Illuminate\Support\Facades\Route::has('help.progress.show')) {
        try {
            $tourConfig = app(\App\Modules\Help\Application\HelpCatalog::class)->tourConfig($tourActor, $id, $locale);
        } catch (\Throwable) {
            $tourConfig = null;
        }
    }
@endphp

@if ($tourConfig !== null && ($tourConfig['integrated'] ?? false) === true && $tourConfig['steps'] !== [])
    <x-ui.product-tour :tour="$tourConfig" :locale="$locale" :autostart="$autostart" />
@endif
