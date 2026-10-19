<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class RtlShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__rtl-shell-fixture/{state?}', function (?string $state = null) {
            $errors = new ViewErrorBag;

            if ($state === 'error' || $state === 'long') {
                $errors->put('default', new MessageBag([
                    'field' => $state === 'long' ? str_repeat('خطأ', 300) : '<رسالة خطأ>',
                ]));
            }

            return view('layouts.shell', [
                'title' => $state === 'long' ? str_repeat('عنوان', 300) : '<عنوان اختبار>',
                'active' => 'overview',
                'content' => '<p>محتوى عربي</p>',
                'email' => $state === 'long' ? str_repeat('support', 40).'@example.test' : 'support@example.test',
                'number' => $state === 'long' ? str_repeat('2026-09-07 / ١٢٣٫٤٥ ', 30) : '2026-09-07 / ١٢٣٫٤٥',
                'errors' => $errors,
            ]);
        });

        Route::get('/__rtl-shell-child', fn () => view('layouts.fixtures.shell-child', [
            'title' => 'واجهة فرعية',
            'childText' => '<نص فرعي>',
            'errors' => new ViewErrorBag,
        ]));
    }

    public function test_renders_an_arabic_rtl_accessible_shell_with_bidi_isolation_and_escaped_content(): void
    {
        $response = $this->get('/__rtl-shell-fixture');

        $response->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('href="#main-content"', false)
            ->assertSee('<main id="main-content"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('<bdi dir="ltr">support@example.test</bdi>', false)
            ->assertSee('<bdi dir="ltr">2026-09-07 / ١٢٣٫٤٥</bdi>', false)
            ->assertSee('&lt;عنوان اختبار&gt;', false)
            ->assertDontSee('<عنوان اختبار>', false)
            ->assertSee('مركز العمل', false)
            ->assertSee('لوحة التحكم', false)
            ->assertDontSee('class="feedback"', false);
    }

    public function test_renders_and_escapes_default_laravel_view_error_messages(): void
    {
        $response = $this->get('/__rtl-shell-fixture/error');

        $response->assertOk()
            ->assertSee('class="feedback" role="alert"', false)
            ->assertSee('تعذّر إكمال الطلب', false)
            ->assertSee('&lt;رسالة خطأ&gt;', false)
            ->assertDontSee('<رسالة خطأ>', false);
    }

    public function test_reusable_child_view_can_supply_a_blade_content_section(): void
    {
        $response = $this->get('/__rtl-shell-child');

        $response->assertOk()
            ->assertSee('<h1>واجهة فرعية</h1>', false)
            ->assertSee('<p class="child-fixture">&lt;نص فرعي&gt;</p>', false);
    }

    public function test_long_arabic_title_email_reference_and_error_are_rendered_for_browser_wrap_review(): void
    {
        $response = $this->get('/__rtl-shell-fixture/long');

        $response->assertOk()
            ->assertSee(str_repeat('عنوان', 300), false)
            ->assertSee(str_repeat('support', 40).'@example.test', false)
            ->assertSee(str_repeat('خطأ', 300), false)
            ->assertSee('overflow-wrap: anywhere', false);
    }

    public function test_embeds_local_responsive_dark_ready_keyboard_and_motion_styles(): void
    {
        $response = $this->get('/__rtl-shell-fixture');

        $response->assertOk()
            ->assertSee('<style>', false)
            ->assertSee('@media (max-width: 48rem)', false)
            ->assertSee(':focus-visible', false)
            ->assertSee('@media (prefers-reduced-motion: reduce)', false)
            ->assertSee('[data-theme="dark"]', false)
            ->assertSee('min-width: 0', false)
            ->assertSee('overflow-wrap: anywhere', false);
    }
}
