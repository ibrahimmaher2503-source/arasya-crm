@extends('layouts.shell', ['locale' => $locale])

@section('content')
@php
    $tourConfig = $tourConfig ?? app(\App\Modules\Help\Application\HelpCatalog::class)->tourConfig($actor, 'help-center', $locale);
@endphp
<div class="ui-stack help-center">
    <div data-tour="help.index.header">
                <nav class="help-language" aria-label="{{ __('help.ui.language', [], $locale) }}">
                    <a href="{{ route('help.index', ['locale' => 'ar', 'q' => $query]) }}" @if ($locale === 'ar') aria-current="page" @endif>{{ __('help.ui.arabic', [], $locale) }}</a>
                    <a href="{{ route('help.index', ['locale' => 'en', 'q' => $query]) }}" @if ($locale === 'en') aria-current="page" @endif>{{ __('help.ui.english', [], $locale) }}</a>
                </nav>
    </div>

    <section class="help-search" data-tour="help.index.search" aria-labelledby="help-search-heading">
        <h2 id="help-search-heading" class="ui-visually-hidden">{{ __('help.ui.search', [], $locale) }}</h2>
        <form method="get" action="{{ route('help.index') }}" class="help-search__form">
            <input type="hidden" name="locale" value="{{ $locale }}">
            <x-ui.field id="help-search" :label="__('help.ui.search', [], $locale)">
                <x-ui.search-input id="help-search" name="q" :value="$query" :placeholder="__('help.ui.search_placeholder', [], $locale)" maxlength="120" />
            </x-ui.field>
            <div class="help-search__actions">
                <x-ui.button type="submit"><x-ui.icon name="search" />{{ __('help.ui.search_button', [], $locale) }}</x-ui.button>
                @if ($query !== '')
                    <a href="{{ route('help.index', ['locale' => $locale]) }}">{{ __('help.ui.clear_search', [], $locale) }}</a>
                @endif
            </div>
        </form>
    </section>

    <section class="help-tour-summary" data-tour="help.index.tours" aria-labelledby="help-tours-heading">
        <h2 id="help-tours-heading">{{ __('help.ui.tours', [], $locale) }}</h2>
        <p class="ui-muted">{{ $tour['title'] }}</p>
        <x-ui.product-tour :tour="$tourConfig" :locale="$locale" />
        @php($pendingTours = array_values(array_filter($tours, static fn (array $item): bool => ! $item['integrated'])))
        @if ($pendingTours !== [])
            <details class="help-pending-tours">
                <summary>{{ __('help.ui.pending_tours', [], $locale) }}</summary>
                <ul>
                    @foreach ($pendingTours as $pendingTour)
                        <li>{{ $pendingTour['title'] }} <span class="help-status help-status--pending">{{ __('help.ui.pending', [], $locale) }}</span></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    <div class="help-collections" data-tour="help.index.collections">
        <section class="help-section" aria-labelledby="help-pages-heading">
            <h2 id="help-pages-heading">{{ __('help.ui.pages', [], $locale) }}</h2>
            @if ($pages === [])
                <p class="help-empty">{{ __('help.ui.no_results', [], $locale) }}</p>
            @else
                <ul class="help-entry-list">
                    @foreach ($pages as $page)
                        <li>
                            <article class="help-entry" data-help-page="{{ $page['id'] }}">
                                <div class="help-entry__heading">
                                    <h3>{{ $page['title'] }}</h3>
                                    @if ($page['availability'] === 'pending')
                                        <span class="help-status help-status--pending">{{ __('help.ui.pending', [], $locale) }}</span>
                                    @elseif ($page['classification'] === 'testing')
                                        <span class="help-status help-status--testing">{{ __('help.ui.testing_only', [], $locale) }}</span>
                                    @elseif ($page['classification'] === 'development')
                                        <span class="help-status help-status--pending">{{ __('help.ui.development_only', [], $locale) }}</span>
                                    @elseif ($page['classification'] === 'technical')
                                        <span class="help-status">{{ __('help.ui.technical', [], $locale) }}</span>
                                    @endif
                                </div>
                                <p>{{ $page['summary'] }}</p>
                                @if ($page['availability_message'])
                                    <p class="help-entry__notice">{{ $page['availability_message'] }}</p>
                                @endif
                                <div class="help-entry__links">
                                    @if ($page['url'])
                                        <a href="{{ $page['url'] }}?locale={{ $locale }}">{{ __('help.ui.open_page', [], $locale) }}</a>
                                    @endif
                                    <a href="{{ route('help.articles.show', ['article' => $page['id'], 'locale' => $locale]) }}">{{ __('help.ui.read_guide', [], $locale) }}</a>
                                </div>
                                @if ($page['actions'] !== [])
                                    <ul class="help-action-list">
                                        @foreach ($page['actions'] as $action)
                                            <li data-help-action="{{ $page['id'].'.'.$action['id'] }}">{{ $action['text'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="help-section" aria-labelledby="help-workflows-heading">
            <h2 id="help-workflows-heading">{{ __('help.ui.workflows', [], $locale) }}</h2>
            @if ($workflows === [])
                <p class="help-empty">{{ __('help.ui.no_results', [], $locale) }}</p>
            @else
                <ul class="help-entry-list">
                    @foreach ($workflows as $workflow)
                        <li><article class="help-entry" data-help-workflow="{{ $workflow['id'] }}"><h3>{{ $workflow['title'] }}</h3><p>{{ $workflow['summary'] }}</p></article></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="help-section" aria-labelledby="help-articles-heading">
            <h2 id="help-articles-heading">{{ __('help.ui.articles', [], $locale) }}</h2>
            @if ($articles === [])
                <p class="help-empty">{{ __('help.ui.no_results', [], $locale) }}</p>
            @else
                <ul class="help-entry-list">
                    @foreach ($articles as $article)
                        <li><article class="help-entry" data-help-article="{{ $article['id'] }}"><h3><a href="{{ route('help.articles.show', ['article' => $article['id'], 'locale' => $locale]) }}">{{ $article['title'] }}</a></h3><p>{{ $article['summary'] }}</p></article></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="help-section" aria-labelledby="help-faq-heading">
            <h2 id="help-faq-heading">{{ __('help.ui.faqs', [], $locale) }}</h2>
            @if ($faqs === [])
                <p class="help-empty">{{ __('help.ui.no_results', [], $locale) }}</p>
            @else
                <div class="help-faq-list">
                    @foreach ($faqs as $faq)
                        <details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="help-section" aria-labelledby="help-glossary-heading">
            <h2 id="help-glossary-heading">{{ __('help.ui.glossary', [], $locale) }}</h2>
            @if ($glossary === [])
                <p class="help-empty">{{ __('help.ui.no_results', [], $locale) }}</p>
            @else
                <dl class="help-glossary">
                    @foreach ($glossary as $term)
                        <div><dt>{{ $term['term'] }}</dt><dd>{{ $term['definition'] }}</dd></div>
                    @endforeach
                </dl>
            @endif
        </section>
    </div>
</div>
@endsection
