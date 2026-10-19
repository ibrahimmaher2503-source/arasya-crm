@props(['title', 'subtitle' => '', 'icon' => 'building', 'breadcrumbs' => [], 'heading' => 'h1'])
<header {{ $attributes->merge(['class' => 'admin-page-heading', 'data-testid' => 'admin-page-header']) }}>
    <div class="admin-page-heading__copy">
        @if ($breadcrumbs)<x-ui.breadcrumbs :items="$breadcrumbs" />@endif
        <div class="admin-page-heading__title"><span class="admin-icon-tile"><x-ui.icon :name="$icon" /></span><div>@if ($heading === 'h2')<h2>{{ $title }}</h2>@else<h1>{{ $title }}</h1>@endif @if ($subtitle)<p>{{ $subtitle }}</p>@endif</div></div>
    </div>
    @isset($actions)<div class="ui-inline">{{ $actions }}</div>@endisset
</header>
