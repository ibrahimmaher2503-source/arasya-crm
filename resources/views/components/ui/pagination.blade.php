@props(['links' => []])

<nav {{ $attributes->merge(['class' => 'ui-pagination', 'aria-label' => 'Pagination']) }}>
    <ul class="ui-pagination__list">
        @foreach ($links as $link)
            @php
                $url = (string) ($link['url'] ?? '');
                $isSafeUrl = preg_match('/^(?:https?:\/\/|\/(?!\/)|\?(?!\?)|#)/i', $url) === 1;
            @endphp

            <li>
                @if ($isSafeUrl)
                    <a class="ui-pagination__link" href="{{ $url }}" @if (!empty($link['current'])) aria-current="page" @endif>
                        {{ (string) ($link['label'] ?? '') }}
                    </a>
                @else
                    <span class="ui-pagination__link" aria-disabled="true">
                        {{ (string) ($link['label'] ?? '') }}
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
