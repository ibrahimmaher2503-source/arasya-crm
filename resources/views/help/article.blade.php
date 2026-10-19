@extends('layouts.shell', ['locale' => $locale])

@section('content')
@php
    $sectionKeys = ['purpose', 'information', 'filters', 'actions', 'forms', 'transitions', 'errors', 'conditions', 'next_step'];
@endphp
<style>{!! file_get_contents(resource_path('css/help.css')) !!}</style>

<div class="ui-stack help-article" data-help-article="{{ $page['id'] }}">
            @if ($page['url'])
                <a class="ui-button ui-button--secondary" href="{{ $page['url'] }}?locale={{ $locale }}">{{ __('help.ui.open_page', [], $locale) }}</a>
            @endif

    @if (($page['availability_message'] ?? null) !== null)
        <p class="help-entry__notice" role="status">{{ $page['availability_message'] }}</p>
    @endif

    @if (is_array($page['sections'] ?? null))
        <section class="help-section" aria-labelledby="help-article-details">
            <h2 id="help-article-details">{{ __('help.ui.labels.sections', [], $locale) }}</h2>
            @foreach ($page['sections'] as $section)
                <article class="help-article__section">
                    <h3>{{ $section['title'] }}</h3>
                    <p>{{ $section['text'] }}</p>
                </article>
            @endforeach
        </section>
    @else
        @foreach ($sectionKeys as $key)
            <section class="help-article__section" aria-labelledby="help-section-{{ $key }}">
                <h2 id="help-section-{{ $key }}">{{ __('help.ui.labels.'.$key, [], $locale) }}</h2>
                @if ($key === 'actions')
                    @if (($page['actions'] ?? []) === [])
                        <p class="ui-muted">{{ __('help.ui.no_actions', [], $locale) }}</p>
                    @else
                        <ul class="help-content-list">
                            @foreach ($page['actions'] as $action)
                                <li data-help-action="{{ $page['id'].'.'.$action['id'] }}">{{ $action['text'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                @elseif (in_array($key, ['purpose', 'next_step'], true))
                    <p>{{ $page[$key] !== '' ? $page[$key] : __('help.ui.not_applicable', [], $locale) }}</p>
                @else
                    @php($items = $page[$key] ?? [])
                    @if ($items === [])
                        <p class="ui-muted">{{ __('help.ui.not_applicable', [], $locale) }}</p>
                    @else
                        <ul class="help-content-list">
                            @foreach ($items as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </section>
        @endforeach
    @endif
</div>
@endsection
