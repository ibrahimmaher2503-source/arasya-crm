@props(['locale' => 'ar'])
<main id="main-content" {{ $attributes->class(['auth-layout']) }} data-auth-layout>
    <aside class="auth-layout__visual" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" aria-label="{{ $locale === 'ar' ? 'عن أراسيا' : 'About Arasya' }}">
        <div class="auth-layout__message">{{ $visual }}</div>
    </aside>
    <div class="auth-layout__entry" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
        <section class="auth-layout__card" aria-labelledby="auth-title">{{ $slot }}</section>
        <footer class="auth-layout__footer">{{ $footer ?? '' }}</footer>
    </div>
</main>
