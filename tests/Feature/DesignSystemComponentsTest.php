<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Tests\TestCase;

final class DesignSystemComponentsTest extends TestCase
{
    public function test_primary_button_renders_its_primary_variant_and_slot(): void
    {
        $html = Blade::render('<x-ui.button variant="primary">Save changes</x-ui.button>');

        $this->assertStringContainsString('ui-button--primary', $html);
        $this->assertMatchesRegularExpression('/<button\b(?=[^>]*\stype="button")/', $html);
        $this->assertStringContainsString('Save changes', $html);
    }

    public function test_danger_button_renders_its_danger_variant_and_slot(): void
    {
        $html = Blade::render('<x-ui.button variant="danger">Delete record</x-ui.button>');

        $this->assertStringContainsString('ui-button--danger', $html);
        $this->assertStringContainsString('Delete record', $html);
    }

    public function test_input_keeps_its_labeling_attributes_and_escapes_the_value(): void
    {
        $value = '"><script>alert(1)</script>';
        $html = Blade::render(
            '<x-ui.input id="account-name" name="account_name" :value="$value" required />',
            compact('value'),
        );

        $this->assertStringContainsString('id="account-name"', $html);
        $this->assertStringContainsString('name="account_name"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_field_associates_invalid_input_with_its_hint_and_error(): void
    {
        $html = Blade::render(
            '<x-ui.field id="email" label="Email address" hint="Used for account notices" error="Enter a valid email"><x-ui.input name="email" /></x-ui.field>',
        );

        $this->assertStringContainsString('for="email"', $html);
        $this->assertStringContainsString('Email address', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="[^"]*email-hint[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="[^"]*email-error[^"]*"/', $html);
        $this->assertStringContainsString('Used for account notices', $html);
        $this->assertStringContainsString('Enter a valid email', $html);
    }

    public function test_field_keeps_caller_describedby_ids_alongside_its_messages(): void
    {
        $html = Blade::render(
            '<x-ui.field id="email" label="Email address" hint="Used for account notices" error="Enter a valid email"><x-ui.input name="email" aria-describedby="external-help" /></x-ui.field>',
        );

        $this->assertMatchesRegularExpression('/aria-describedby="[^"]*external-help[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="[^"]*email-hint[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/aria-describedby="[^"]*email-error[^"]*"/', $html);
    }

    public function test_field_requires_an_id_when_it_renders_a_label(): void
    {
        try {
            Blade::render('<x-ui.field label="Email address"><x-ui.input name="email" /></x-ui.field>');
        } catch (ViewException $exception) {
            $cause = $exception;
            while ($cause !== null && ! $cause instanceof \InvalidArgumentException) {
                $cause = $cause->getPrevious();
            }

            $this->assertInstanceOf(\InvalidArgumentException::class, $cause);
            $this->assertStringContainsString('id', $cause->getMessage());

            return;
        }

        $this->fail('A labeled field without an id must be rejected.');
    }

    public function test_currency_input_accepts_decimal_values_and_preserves_explicit_step(): void
    {
        $default = Blade::render('<x-ui.currency-input id="amount" name="amount" />');
        $explicit = Blade::render('<x-ui.currency-input id="precise-amount" name="precise_amount" step="0.01" />');

        $this->assertStringContainsString('step="any"', $default);
        $this->assertStringContainsString('step="0.01"', $explicit);
    }

    public function test_button_preserves_native_disabled_state(): void
    {
        $html = Blade::render('<x-ui.button variant="danger" disabled>Delete record</x-ui.button>');

        $this->assertMatchesRegularExpression('/<button\b(?=[^>]*\sdisabled(?:="[^"]*")?(?:\s|>))[^>]*>.*?Delete record.*?<\/button>/s', $html);
    }

    public function test_multi_select_keeps_native_name_required_disabled_and_options(): void
    {
        $html = Blade::render(
            '<x-ui.multi-select id="departments" name="departments[]" required disabled><option value="finance" selected>Finance</option><option value="sales">Sales</option></x-ui.multi-select>',
        );

        $this->assertMatchesRegularExpression('/<select\b(?=[^>]*\smultiple(?:="[^"]*")?(?:\s|>))[^>]*>.*?<\/select>/s', $html);
        $this->assertStringContainsString('id="departments"', $html);
        $this->assertStringContainsString('name="departments[]"', $html);
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('<option value="finance" selected>Finance</option>', $html);
        $this->assertStringContainsString('<option value="sales">Sales</option>', $html);
    }

    public function test_switch_is_a_labeled_native_checkbox_with_switch_role(): void
    {
        $html = Blade::render(
            '<x-ui.switch id="notifications" name="notifications" label="Email notifications" checked disabled />',
        );

        $this->assertMatchesRegularExpression('/<input\b(?=[^>]*\stype="checkbox")(?=[^>]*\srole="switch")[^>]*>/', $html);
        $this->assertStringContainsString('id="notifications"', $html);
        $this->assertStringContainsString('name="notifications"', $html);
        $this->assertStringContainsString('checked', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('for="notifications"', $html);
        $this->assertStringContainsString('Email notifications', $html);
    }

    public function test_switch_generates_an_id_linked_to_its_label_when_omitted(): void
    {
        $html = Blade::render('<x-ui.switch name="notifications" label="Email notifications" />');

        $this->assertMatchesRegularExpression('/<input\b(?=[^>]*\bid="([^"]+)")[^>]*>.*?<label\b[^>]*\bfor="\1"/s', $html);
    }

    public function test_icon_button_has_an_accessible_name(): void
    {
        $html = Blade::render('<x-ui.icon-button label="Close panel">×</x-ui.icon-button>');

        $this->assertStringContainsString('aria-label="Close panel"', $html);
    }

    public function test_success_status_badge_renders_its_state_and_slot(): void
    {
        $html = Blade::render('<x-ui.status-badge status="success">Approved</x-ui.status-badge>');

        $this->assertStringContainsString('ui-status-badge--success', $html);
        $this->assertStringContainsString('Approved', $html);
    }

    public function test_table_renders_a_labeled_region_and_escaped_cell_values(): void
    {
        $value = '<script>alert(1)</script>';
        $html = Blade::render(
            '<x-ui.table caption="Records" aria-label="Records table"><thead><tr><th scope="col">Name</th></tr></thead><tbody><tr><td>{{ $value }}</td></tr></tbody></x-ui.table>',
            compact('value'),
        );

        $this->assertStringContainsString('role="region"', $html);
        $this->assertStringContainsString('aria-label="Records table"', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<caption>Records</caption>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_description_list_escapes_plain_values(): void
    {
        $html = Blade::render(
            '<x-ui.description-list :items="$items" />',
            ['items' => ['Reference' => '<script>alert(1)</script>']],
        );

        $this->assertStringContainsString('<dt>Reference</dt>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_tabs_link_each_control_to_its_panel_and_escape_content(): void
    {
        $items = [
            ['id' => 'summary', 'label' => 'Summary', 'content' => '<script>alert(1)</script>'],
            ['id' => 'activity', 'label' => 'Activity', 'content' => 'Recent actions'],
        ];
        $html = Blade::render(
            '<x-ui.tabs id="customer-tabs" :items="$items" aria-label="Customer sections" />',
            compact('items'),
        );

        $this->assertStringContainsString('data-tabs', $html);
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('aria-label="Customer sections"', $html);
        $this->assertStringContainsString('id="customer-tabs-tab-summary"', $html);
        $this->assertStringContainsString('aria-controls="customer-tabs-panel-summary"', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('id="customer-tabs-tab-activity"', $html);
        $this->assertStringContainsString('aria-controls="customer-tabs-panel-activity"', $html);
        $this->assertStringContainsString('aria-selected="false"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('aria-labelledby="customer-tabs-tab-summary"', $html);
        $this->assertStringContainsString('aria-labelledby="customer-tabs-tab-activity"', $html);
        $this->assertStringContainsString('id="customer-tabs-panel-activity"', $html);
        $this->assertStringContainsString('hidden', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_breadcrumbs_link_only_safe_items_and_escape_the_current_item(): void
    {
        $items = [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Unsafe destination', 'url' => '//outside.example/path'],
            ['label' => '<script>alert(1)</script>'],
        ];
        $html = Blade::render(
            '<x-ui.breadcrumbs :items="$items" aria-label="Customer navigation" />',
            compact('items'),
        );

        $this->assertStringContainsString('<nav', $html);
        $this->assertStringContainsString('aria-label="Customer navigation"', $html);
        $this->assertStringContainsString('<ol', $html);
        $this->assertMatchesRegularExpression('/<a\b[^>]*href="\/"[^>]*>Home<\/a>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a\b[^>]*href="\/\/outside\.example/', $html);
        $this->assertStringContainsString('Unsafe destination', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_drawer_renders_a_named_native_dialog_with_a_close_control(): void
    {
        $html = Blade::render('<x-ui.drawer id="demo-drawer" title="More actions">Drawer contents</x-ui.drawer>');

        $this->assertMatchesRegularExpression('/<dialog\b[^>]*id="demo-drawer"/s', $html);
        $this->assertStringContainsString('aria-labelledby="demo-drawer-title"', $html);
        $this->assertStringContainsString('id="demo-drawer-title"', $html);
        $this->assertStringContainsString('data-close-dialog', $html);
        $this->assertStringContainsString('More actions', $html);
        $this->assertStringContainsString('Drawer contents', $html);
    }

    public function test_confirmation_dialog_renders_cancel_and_caller_owned_confirmation(): void
    {
        $html = Blade::render(
            '<x-ui.confirmation-dialog id="remove-record" title="Remove record" confirm-label="Remove" cancel-label="Keep record">
                <x-slot:confirm>
                    <button type="submit">Remove record</button>
                </x-slot:confirm>
                Are you sure you want to remove this record?
            </x-ui.confirmation-dialog>',
        );

        $this->assertMatchesRegularExpression('/<dialog\b[^>]*id="remove-record"/s', $html);
        $this->assertStringContainsString('Remove record', $html);
        $this->assertStringContainsString('Keep record', $html);
        $this->assertStringContainsString('data-close-dialog', $html);
        $this->assertStringContainsString('Are you sure you want to remove this record?', $html);
        $this->assertMatchesRegularExpression('/<button\b[^>]*type="submit"[^>]*>\s*Remove record\s*<\/button>/', $html);
    }

    public function test_confirmation_dialog_default_action_does_not_submit_a_form(): void
    {
        $html = Blade::render(
            '<x-ui.confirmation-dialog id="remove-record" title="Remove record" confirm-label="Remove" cancel-label="Keep record">Are you sure?</x-ui.confirmation-dialog>',
        );

        $this->assertMatchesRegularExpression('/<button\b(?=[^>]*\stype="button")(?=[^>]*\sdata-confirm-action)[^>]*>.*?Remove.*?<\/button>/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<form\b/', $html);
    }

    public function test_modal_renders_a_named_native_dialog_with_title_and_slot(): void
    {
        $html = Blade::render('<x-ui.modal id="demo-modal" title="Record details">Details go here</x-ui.modal>');

        $this->assertMatchesRegularExpression('/<dialog\b[^>]*id="demo-modal"/s', $html);
        $this->assertStringContainsString('aria-labelledby="demo-modal-title"', $html);
        $this->assertStringContainsString('id="demo-modal-title"', $html);
        $this->assertStringContainsString('Record details', $html);
        $this->assertStringContainsString('Details go here', $html);
    }

    public function test_all_ui_components_render_with_minimal_valid_inputs(): void
    {
        $items = [['label' => 'Home', 'url' => '/']];
        $tabs = [['id' => 'overview', 'label' => 'Overview', 'content' => 'Summary']];
        $components = [
            'alert' => ['<x-ui.alert>Notice</x-ui.alert>', []],
            'avatar' => ['<x-ui.avatar name="Rania Saleh" />', []],
            'breadcrumbs' => ['<x-ui.breadcrumbs :items="$items" />', compact('items')],
            'button' => ['<x-ui.button>Save</x-ui.button>', []],
            'checkbox' => ['<x-ui.checkbox id="active" name="active" />', []],
            'confirmation dialog' => ['<x-ui.confirmation-dialog id="confirm" title="Confirm">Continue?</x-ui.confirmation-dialog>', []],
            'currency input' => ['<x-ui.currency-input id="amount" name="amount" />', []],
            'date input' => ['<x-ui.date-input id="date" name="date" />', []],
            'description list' => ['<x-ui.description-list :items="[\'Reference\' => \'R-1\']" />', []],
            'detail panel' => ['<x-ui.detail-panel title="Record">Details</x-ui.detail-panel>', []],
            'drawer' => ['<x-ui.drawer id="drawer" title="Details">Content</x-ui.drawer>', []],
            'dropdown menu' => ['<x-ui.dropdown-menu>Menu items</x-ui.dropdown-menu>', []],
            'empty state' => ['<x-ui.empty-state title="No records" />', []],
            'error state' => ['<x-ui.error-state>Try again</x-ui.error-state>', []],
            'field' => ['<x-ui.field id="name" label="Name"><x-ui.input name="name" /></x-ui.field>', []],
            'form actions' => ['<x-ui.form-actions><x-ui.button>Save</x-ui.button></x-ui.form-actions>', []],
            'form section' => ['<x-ui.form-section title="Contact"><x-ui.input name="email" /></x-ui.form-section>', []],
            'icon button' => ['<x-ui.icon-button label="Close">×</x-ui.icon-button>', []],
            'input' => ['<x-ui.input id="name" name="name" />', []],
            'loading state' => ['<x-ui.loading-state />', []],
            'modal' => ['<x-ui.modal id="modal" title="Details">Content</x-ui.modal>', []],
            'multi-select' => ['<x-ui.multi-select id="teams" name="teams[]"><option value="sales">Sales</option></x-ui.multi-select>', []],
            'number input' => ['<x-ui.number-input id="count" name="count" />', []],
            'pagination' => ['<x-ui.pagination />', []],
            'radio' => ['<x-ui.radio id="choice" name="choice" value="yes" />', []],
            'search input' => ['<x-ui.search-input id="query" name="query" />', []],
            'select' => ['<x-ui.select id="team" name="team"><option value="sales">Sales</option></x-ui.select>', []],
            'skeleton' => ['<x-ui.skeleton />', []],
            'stat card' => ['<x-ui.stat-card label="Open orders" value="12" />', []],
            'status badge' => ['<x-ui.status-badge status="success">Approved</x-ui.status-badge>', []],
            'switch' => ['<x-ui.switch name="notifications" label="Notifications" />', []],
            'table' => ['<x-ui.table><thead><tr><th>Name</th></tr></thead></x-ui.table>', []],
            'table toolbar' => ['<x-ui.table-toolbar>Filters</x-ui.table-toolbar>', []],
            'tabs' => ['<x-ui.tabs id="tabs" :items="$tabs" />', compact('tabs')],
            'textarea' => ['<x-ui.textarea id="notes" name="notes" />', []],
            'toast' => ['<x-ui.toast>Saved</x-ui.toast>', []],
            'tooltip' => ['<x-ui.tooltip id="help" text="More information">Help</x-ui.tooltip>', []],
            'validation message' => ['<x-ui.validation-message>Required</x-ui.validation-message>', []],
        ];

        foreach ($components as $name => [$template, $data]) {
            $html = Blade::render($template, $data);

            $this->assertNotSame('', trim($html), "The {$name} component should render markup.");
        }
    }
}
