<form {{ $attributes->merge(['method' => 'get', 'class' => 'ui-filter-bar', 'role' => 'search']) }}>
    {{ $slot }}
</form>
