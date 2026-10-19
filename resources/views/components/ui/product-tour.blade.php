@props(['tour', 'locale' => 'ar', 'autostart' => false])

<div class="help-tour" data-product-tour data-tour-id="{{ $tour['tour_id'] }}" @if ($autostart) data-tour-autostart @endif>
    <script type="application/json" data-product-tour-config>{!! json_encode($tour, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <div class="help-tour__launcher">
        <x-ui.button type="button" data-tour-start>{{ __('help.ui.tour_start', [], $locale) }}</x-ui.button>
        <x-ui.button type="button" variant="secondary" data-tour-restart>{{ __('help.ui.tour_restart', [], $locale) }}</x-ui.button>
        <p class="help-tour__status" data-tour-status role="status" aria-live="polite">{{ __('help.ui.tour_loading', [], $locale) }}</p>
        <p class="help-tour__message" data-tour-message role="status" aria-live="polite"></p>
    </div>

    <div class="help-tour__shades" aria-hidden="true">
        <span class="help-tour__shade" data-tour-shade="top"></span>
        <span class="help-tour__shade" data-tour-shade="right"></span>
        <span class="help-tour__shade" data-tour-shade="bottom"></span>
        <span class="help-tour__shade" data-tour-shade="left"></span>
        <span class="help-tour__spotlight" data-tour-spotlight></span>
    </div>

    <div class="help-tour__popover" data-tour-popover role="dialog" aria-modal="true" aria-labelledby="help-tour-title-{{ $tour['tour_id'] }}" aria-describedby="help-tour-body-{{ $tour['tour_id'] }}" hidden tabindex="-1">
        <div class="help-tour__popover-header">
            <p class="help-tour__step-count" data-tour-step-count aria-live="polite"></p>
            <x-ui.button type="button" variant="ghost" data-tour-close>{{ __('help.ui.tour_close', [], $locale) }}</x-ui.button>
        </div>
        <h2 id="help-tour-title-{{ $tour['tour_id'] }}" data-tour-title></h2>
        <p id="help-tour-body-{{ $tour['tour_id'] }}" data-tour-body aria-live="polite"></p>
        <div class="help-tour__controls">
            <x-ui.button type="button" variant="secondary" data-tour-previous>{{ __('help.ui.tour_previous', [], $locale) }}</x-ui.button>
            <x-ui.button type="button" variant="secondary" data-tour-skip>{{ __('help.ui.tour_skip', [], $locale) }}</x-ui.button>
            <x-ui.button type="button" data-tour-next>{{ __('help.ui.tour_next', [], $locale) }}</x-ui.button>
            <x-ui.button type="button" variant="ghost" data-tour-pause>{{ __('help.ui.tour_pause', [], $locale) }}</x-ui.button>
        </div>
    </div>
</div>

@once
    <style>{!! file_get_contents(resource_path('css/help.css')) !!}</style>
    <script>{!! file_get_contents(resource_path('js/product-tour.js')) !!}</script>
@endonce
