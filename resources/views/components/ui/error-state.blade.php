@props(['title' => 'Something went wrong', 'message' => null])

<section {{ $attributes->merge(['class' => 'ui-error-state', 'role' => 'alert', 'aria-live' => 'assertive']) }}>
    <h2 class="ui-error-state__title">{{ $title }}</h2>
    <p class="ui-error-state__message">{{ filled($message) ? $message : $slot }}</p>
</section>
