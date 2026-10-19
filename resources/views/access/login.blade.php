@php
    $locale = request()->query('locale', $locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
    $title = $t('تسجيل الدخول', 'Sign in');
@endphp
@extends('layouts.shell', ['locale' => $locale, 'title' => $title, 'authShell' => true])

@section('content')
    <x-ui.auth-layout :locale="$locale" data-testid="login-page">
        <x-slot:visual>
            <x-ui.brand-lockup />
            <p class="auth-layout__eyebrow">{{ $t('نظام التشغيل الداخلي', 'Your internal workspace') }}</p>
            <h2>{{ $t('إدارة واضحة .. تعكس جودة أعمالنا', 'Clear management. Quality in every detail.') }}</h2>
            <p class="auth-layout__description">{{ $t('من المبيعات إلى التنفيذ، ومن المخزون إلى التحصيل.', 'From sales to delivery, and inventory to collection.') }}<br>{{ $t('كل عمليات أراسيا في نظام واحد متكامل.', 'All Arasya operations in one connected workspace.') }}</p>
            <ul class="auth-layout__features">
                @foreach ([['grid', 'تنفيذ المشاريع', 'Project delivery'], ['users', 'الموظفون والفرق', 'People and teams'], ['target', 'متابعة المبيعات', 'Sales tracking'], ['building', 'إدارة المخزون', 'Inventory management']] as [$icon, $arabic, $english])
                    <li><span><x-ui.icon :name="$icon" /></span>{{ $t($arabic, $english) }}</li>
                @endforeach
            </ul>
        </x-slot:visual>
        <header class="auth-layout__heading">
            <h1 id="auth-title">{{ $title }}</h1>
            <p>{{ $t('مرحباً بعودتك إلى نظام أراسيا', 'Welcome back to My Arasya') }}</p>
            <span>{{ $t('أدخل بريدك الإلكتروني وكلمة المرور للمتابعة.', 'Enter your email and password to continue.') }}</span>
        </header>
            <form class="ui-stack auth-form" method="post" action="/login?locale={{ $locale }}">
                @csrf
                <x-ui.field id="login-email" :label="$t('البريد الإلكتروني', 'Email address')" :error="$errors->first('email')">
                    <div class="auth-form__control"><x-ui.input id="login-email" name="email" type="email" :value="old('email')" autocomplete="username" placeholder="name@company.com" dir="ltr" required /><x-ui.icon name="mail" class="auth-form__leading" /></div>
                </x-ui.field>
                <x-ui.field id="login-password" :label="$t('كلمة المرور', 'Password')" :error="$errors->first('password')">
                    <div class="auth-form__control">
                        <x-ui.input id="login-password" name="password" type="password" autocomplete="current-password" dir="ltr" required />
                        <x-ui.icon name="lock" class="auth-form__leading" />
                        <button type="button" class="auth-form__visibility" data-password-toggle="login-password" aria-controls="login-password" aria-pressed="false" aria-label="{{ $t('إظهار كلمة المرور', 'Show password') }}" data-show-label="{{ $t('إظهار كلمة المرور', 'Show password') }}" data-hide-label="{{ $t('إخفاء كلمة المرور', 'Hide password') }}"><x-ui.icon name="eye" /></button>
                    </div>
                </x-ui.field>
                <p class="auth-form__security"><x-ui.icon name="shield" />{{ $t('دخول آمن إلى حساب العمل الخاص بك', 'Secure access to your work account') }}</p>
                <x-ui.button type="submit" class="auth-form__submit">{{ $t('تسجيل الدخول', 'Sign in') }}<x-ui.icon name="arrow" /></x-ui.button>
                <p class="auth-form__help">{{ $t('تحتاج مساعدة في الدخول؟ تواصل مع مسؤول النظام.', 'Need help signing in? Contact your system administrator.') }}</p>
            </form>
        <x-slot:footer>
            <span>{{ $t('جميع الحقوق محفوظة', 'All rights reserved') }} © {{ now()->year }} {{ $t('أراسيا', 'Arasya') }}</span>
            <a href="/login?locale={{ $locale === 'ar' ? 'en' : 'ar' }}" lang="{{ $locale === 'ar' ? 'en' : 'ar' }}">{{ $locale === 'ar' ? 'English' : 'العربية' }}</a>
        </x-slot:footer>
    </x-ui.auth-layout>
@endsection
