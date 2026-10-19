<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DesignSystemTest extends TestCase
{
    public function test_design_system_is_hidden_in_production_before_identity_is_required(): void
    {
        $this->app['env'] = 'production';

        $this->get('/dev/design-system')->assertNotFound();
    }

    public function test_design_system_requires_session_identity_in_local_environment(): void
    {
        $this->get('/dev/design-system')->assertUnauthorized();
    }

    public function test_catalog_defaults_to_an_arabic_rtl_shell_with_unset_theme(): void
    {
        $response = $this->withSession($this->runtimeIdentitySession())->get('/dev/design-system');

        $response->assertOk();
        $response->assertSee('<html lang="ar" dir="rtl">', false);
        $response->assertSee('data-testid="theme-toggle"', false);
        $response->assertSee('data-testid="direction-toggle"', false);

        preg_match('/<html\b[^>]*>/', (string) $response->getContent(), $htmlTag);
        $this->assertNotEmpty($htmlTag);
        $this->assertDoesNotMatchRegularExpression('/\bdata-theme\s*=/', $htmlTag[0]);
        $this->assertSame(1, $this->countActiveScreenLinks((string) $response->getContent(), 'catalog', 'ar'));
    }

    public function test_english_locale_changes_the_document_language_and_direction(): void
    {
        $response = $this->withSession($this->runtimeIdentitySession())->get('/dev/design-system?locale=en');

        $response->assertOk();
        $response->assertSee('<html lang="en" dir="ltr">', false);
        $this->assertSame(1, $this->countActiveScreenLinks((string) $response->getContent(), 'catalog', 'en'));
    }

    public function test_unknown_screen_and_locale_are_not_found(): void
    {
        $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=unknown')
            ->assertNotFound();

        $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?locale=fr')
            ->assertNotFound();
    }

    #[DataProvider('supportedScreens')]
    public function test_each_supported_screen_is_rendered_as_the_current_navigation_item(string $screen): void
    {
        $response = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen='.$screen);

        $response->assertOk();
        $this->assertSame(1, $this->countActiveScreenLinks((string) $response->getContent(), $screen, 'ar'));
    }

    public static function supportedScreens(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'orders' => ['orders'],
            'order detail' => ['order'],
            'form' => ['form'],
            'approvals' => ['approvals'],
        ];
    }

    public function test_orders_screen_uses_six_synthetic_records_and_marks_its_table(): void
    {
        $firstPage = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=orders');

        $firstPage->assertOk();
        $firstPage->assertSee('data-testid="orders-table"', false);

        $secondPage = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=orders&page=2');
        $secondPage->assertOk();

        $allRows = (string) $firstPage->getContent().(string) $secondPage->getContent();
        foreach (['015', '014', '013', '012', '011', '010'] as $suffix) {
            $this->assertStringContainsString('data-order-id="SO-2026-'.$suffix.'"', $allRows);
        }

        $this->assertSame(4, $this->countOrderRows((string) $firstPage->getContent()));
        $this->assertSame(2, $this->countOrderRows((string) $secondPage->getContent()));
        foreach (['success', 'info', 'warning', 'danger'] as $status) {
            $this->assertStringContainsString('ui-status-badge--'.$status, $allRows);
        }
    }

    public function test_orders_search_and_status_filters_reduce_the_synthetic_rows(): void
    {
        $search = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=orders&search='.urlencode('النصر'));

        $search->assertOk();
        $search->assertSee('النصر');
        $this->assertSame(1, $this->countOrderRows((string) $search->getContent()));

        $warning = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=orders&status=warning');

        $warning->assertOk();
        $this->assertSame(1, $this->countOrderRows((string) $warning->getContent()));
    }

    public function test_orders_amount_sort_and_second_page_preserve_expected_fixture_order(): void
    {
        $sorted = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=orders&sort=amount_desc');

        $sorted->assertOk();
        $this->assertSame('SO-2026-013', $this->firstOrderId((string) $sorted->getContent()));
        $firstRow = $this->firstOrderRow((string) $sorted->getContent());
        $this->assertMatchesRegularExpression('/245[,.\x{066C}]?300/u', $firstRow);

        $secondPage = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=orders&page=2');

        $secondPage->assertOk();
        $this->assertSame(2, $this->countOrderRows((string) $secondPage->getContent()));
    }

    public function test_approvals_screen_connects_stepper_and_timeline_data_to_component_contracts(): void
    {
        $response = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=approvals');
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertSame(1, preg_match('/<p\b[^>]*aria-current="step"[^>]*>.*?<span>مراجعة<\/span>/su', $html), 'The Review step should be marked current.');
        $this->assertSame(1, preg_match('/<li\b[^>]*>.*?<h3\b[^>]*>إنشاء الطلب<\/h3>.*?<time\b[^>]*>2026-10-17 09:30<\/time>.*?<\/li>/su', $html), 'The first timeline event should expose its title and date together.');
    }

    public function test_order_detail_screen_renders_its_status_and_product_line_items(): void
    {
        $response = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=order');
        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertTrue(str_contains($html, 'مكتمل · تجريبي'), 'The order status should remain visible.');
        foreach (['قاطع كهربائي', 'كابل نحاس', 'لوحة توزيع'] as $product) {
            $this->assertTrue(str_contains($html, $product), "The order should render its {$product} line item.");
        }
    }

    public function test_catalog_localizes_the_ai_preview_only_safety_label(): void
    {
        $arabic = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=catalog');
        $arabic->assertOk();
        $this->assertTrue(str_contains((string) $arabic->getContent(), 'معاينة فقط'), 'Arabic previews should show the localized safety label.');

        $english = $this->withSession($this->runtimeIdentitySession())
            ->get('/dev/design-system?screen=catalog&locale=en');
        $english->assertOk();
        $this->assertTrue(str_contains((string) $english->getContent(), 'Preview only'), 'English previews should keep the English safety label.');
    }

    public function test_entity_header_uses_a_heading_below_the_shell_page_title(): void
    {
        $html = Blade::render('<x-erp.entity-header title="Customer" identifier="CUS-100" />');

        $this->assertMatchesRegularExpression('/<h2\b[^>]*>Customer<\/h2>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<h1\b/', $html);
    }

    public function test_money_display_isolates_the_formatted_value_from_its_currency(): void
    {
        $html = Blade::render('<x-erp.money-display value="125,000.50" currency="SAR" />');

        $this->assertMatchesRegularExpression('/<bdi\b(?=[^>]*dir="ltr")[^>]*>125,000\.50<\/bdi>/', $html);
        $this->assertStringContainsString('SAR', $html);
        $this->assertDoesNotMatchRegularExpression('/125,000\.50\s*SAR<\/bdi>/', $html);
    }

    #[DataProvider('erpAiComponentFixtures')]
    public function test_erp_ai_components_render_with_their_public_apis(string $template, array $data): void
    {
        $html = Blade::render($template, $data);

        $this->assertNotSame('', trim($html));
    }

    public static function erpAiComponentFixtures(): array
    {
        $events = [[
            'title' => 'Manager review',
            'actor' => 'Nadia Hassan',
            'date' => '2026-10-17',
            'status' => 'complete',
            'note' => 'Reviewed within the branch',
        ]];
        $entries = [[
            'action' => 'Order updated',
            'actor' => 'Nadia Hassan',
            'date' => '2026-10-17',
            'description' => 'Changed delivery date',
        ]];
        $steps = [
            ['label' => 'Draft', 'state' => 'complete'],
            ['label' => 'Review', 'state' => 'current'],
            ['label' => 'Approved', 'state' => 'upcoming'],
        ];
        $lines = [['label' => 'Subtotal', 'value' => '1,250.00']];

        return [
            'erp entity header' => ['<x-erp.entity-header title="Customer" subtitle="Business account" identifier="CUS-100"><x-slot:status>Active</x-slot:status><x-slot:actions><button type="button">Open</button></x-slot:actions></x-erp.entity-header>', []],
            'erp entity status' => ['<x-erp.entity-status status="success">Approved</x-erp.entity-status>', []],
            'erp money display' => ['<x-erp.money-display value="1,250.00" currency="SAR" />', []],
            'erp date time display' => ['<x-erp.date-time-display value="17 Oct 2026" datetime="2026-10-17T12:00:00Z" />', []],
            'erp approval timeline' => ['<x-erp.approval-timeline :events="$events" />', compact('events')],
            'erp audit history' => ['<x-erp.audit-history :entries="$entries" />', compact('entries')],
            'erp workflow stepper' => ['<x-erp.workflow-stepper :steps="$steps" />', compact('steps')],
            'erp line items table' => ['<x-erp.line-items-table caption="Order items"><thead><tr><th>Item</th></tr></thead><tbody><tr><td>{{ $item }}</td></tr></tbody></x-erp.line-items-table>', ['item' => 'Desk lamp']],
            'erp totals summary' => ['<x-erp.totals-summary :lines="$lines" total-label="Total" total-value="1,250.00" />', compact('lines')],
            'erp attachment uploader' => ['<x-erp.attachment-uploader id="invoice-file" name="invoice" label="Invoice" accept="application/pdf" multiple />', []],
            'erp permission aware action' => ['<x-erp.permission-aware-action :allowed="true"><button type="button">Open customer</button></x-erp.permission-aware-action>', []],
            'ai assistant panel' => ['<x-ai.assistant-panel title="Review assistant">Local sample content</x-ai.assistant-panel>', []],
            'ai message' => ['<x-ai.message role="user" name="Amina" time="10:30">Please review this order.</x-ai.message>', []],
            'ai finding card' => ['<x-ai.finding-card severity="warning" title="Missing document" summary="A required file is missing.">Order CUS-100 has no invoice.</x-ai.finding-card>', []],
            'ai action preview' => ['<x-ai.action-preview title="Update order" description="Change the expected delivery date.">Preview details</x-ai.action-preview>', []],
            'ai confirmation' => ['<x-ai.confirmation title="Confirm update" confirm-label="Confirm">Apply the proposed date.</x-ai.confirmation>', []],
            'ai execution receipt' => ['<x-ai.execution-receipt state="completed" label="Completed" details="Order SO-2026-015 was updated." />', []],
            'ai error state' => ['<x-ai.error-state title="Assistant unavailable" message="The sample could not be shown." />', []],
        ];
    }

    public function test_erp_permission_aware_action_hides_content_when_not_allowed(): void
    {
        $html = Blade::render(
            '<x-erp.permission-aware-action :allowed="false"><button type="button">Delete customer</button></x-erp.permission-aware-action>',
        );

        $this->assertStringNotContainsString('Delete customer', $html);
    }

    public function test_ai_message_escapes_untrusted_message_and_name(): void
    {
        $html = Blade::render(
            '<x-ai.message role="user" :name="$name" time="10:30">{{ $body }}</x-ai.message>',
            ['name' => '<img src=x onerror=alert(1)>', 'body' => '<script>alert(1)</script>'],
        );

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_ai_action_preview_and_confirmation_are_explicitly_preview_only_and_inert(): void
    {
        $preview = Blade::render('<x-ai.action-preview title="Update order" description="Change the delivery date.">Preview details</x-ai.action-preview>');
        $confirmation = Blade::render('<x-ai.confirmation title="Confirm update" confirm-label="Confirm">Apply the proposed date.</x-ai.confirmation>');

        $this->assertStringContainsString('Preview only', $preview);
        $this->assertStringContainsString('Preview only', $confirmation);
        $this->assertMatchesRegularExpression('/<button\b(?=[^>]*\bdisabled(?:="[^"]*")?)(?=[^>]*\btype="button")[^>]*>/', $confirmation);
        $this->assertDoesNotMatchRegularExpression('/<form\b|<button\b[^>]*\btype="submit"|\b(?:href|action|onclick)=/i', $confirmation);
    }

    public function test_ai_execution_receipt_is_marked_as_sample_and_shows_its_state(): void
    {
        $html = Blade::render('<x-ai.execution-receipt state="completed" label="Completed" details="Order SO-2026-015 was updated." />');

        $this->assertStringContainsString('Sample receipt', $html);
        $this->assertStringContainsString('completed', $html);
        $this->assertStringContainsString('Completed', $html);
    }

    public function test_ai_error_state_has_no_retry_action(): void
    {
        $html = Blade::render('<x-ai.error-state title="Assistant unavailable" message="The sample could not be shown." />');

        $this->assertStringContainsString('Assistant unavailable', $html);
        $this->assertStringContainsString('The sample could not be shown.', $html);
        $this->assertDoesNotMatchRegularExpression('/<button\b|<a\b/', $html);
    }

    /** @return array<string, mixed> */
    private function runtimeIdentitySession(): array
    {
        return [
            'actor_id' => 'design-system-test-actor',
            'entity_id' => 'design-system-test-entity',
            'department_id' => 'design-system-test-branch',
            'permissions' => ['design-system.view'],
        ];
    }

    private function countActiveScreenLinks(string $html, string $screen, string $locale): int
    {
        $pattern = '/<a\b(?=[^>]*href="\/dev\/design-system\?screen='.preg_quote($screen, '/').'&amp;locale='.preg_quote($locale, '/').'\")(?=[^>]*aria-current="page")[^>]*>/s';

        return preg_match_all($pattern, $html);
    }

    private function countOrderRows(string $html): int
    {
        return preg_match_all('/<tr\b[^>]*\bdata-order-id="SO-2026-\d{3}"[^>]*>/s', $html);
    }

    private function firstOrderId(string $html): ?string
    {
        return preg_match('/<tr\b[^>]*\bdata-order-id="(SO-2026-\d{3})"[^>]*>/s', $html, $match)
            ? $match[1]
            : null;
    }

    private function firstOrderRow(string $html): string
    {
        return preg_match('/(<tr\b[^>]*\bdata-order-id="SO-2026-\d{3}"[^>]*>.*?<\/tr>)/s', $html, $match)
            ? $match[1]
            : '';
    }
}
