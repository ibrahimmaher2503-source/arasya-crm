@props(['items' => []])

<nav {{ $attributes->merge(['class' => 'ui-breadcrumbs', 'aria-label' => 'Breadcrumbs']) }}>
    <ol class="ui-breadcrumbs__list">
        @foreach ($items as $item)
            @php
                $label = (string) ($item['label'] ?? '');
                $url = (string) ($item['url'] ?? '');
                $isSafeUrl = preg_match('/^(?:https?:\/\/|\/(?!\/)|\?(?!\?)|#)/i', $url) === 1;
            @endphp

            <li class="ui-breadcrumbs__item">
                @if (!$loop->last && $isSafeUrl)
                    <a class="ui-breadcrumbs__link" href="{{ $url }}">{{ $label }}</a>
                @elseif ($loop->last)
                    <span class="ui-breadcrumbs__current" aria-current="page">{{ $label }}</span>
                @else
                    <span class="ui-breadcrumbs__text">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
