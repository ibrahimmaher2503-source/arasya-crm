<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class UserProfilePresentationTest extends TestCase
{
    private const CSRF_TOKEN = 'synthetic-user-profile-csrf-token';

    protected function setUp(): void
    {
        parent::setUp();

        $compiledViews = storage_path('framework/testing/user-profile/phpunit-'.Str::uuid());
        if (! is_dir($compiledViews) && ! mkdir($compiledViews, 0700, true) && ! is_dir($compiledViews)) {
            throw new \RuntimeException('Could not create the isolated user-profile compiled-view directory.');
        }

        config(['view.compiled' => $compiledViews]);
        $this->prepareSession();
    }

    public function test_arabic_and_english_render_one_shared_shell_header_and_real_section_targets(): void
    {
        foreach ([
            ['ar', '<html lang="ar" dir="rtl">', 'تعديل بيانات المستخدم'],
            ['en', '<html lang="en" dir="ltr">', 'Edit user details'],
        ] as [$locale, $htmlDirection, $heading]) {
            $html = $this->renderUser(['locale' => $locale]);

            self::assertStringContainsString($htmlDirection, $html);
            self::assertStringContainsString('<main id="main-content"', $html);
            self::assertStringContainsString('data-testid="admin-page-header"', $html);
            self::assertSame(1, preg_match_all('/<h1\b/i', $html));
            self::assertSame(1, preg_match_all('/class="dashboard-sidebar"/', $html));
            self::assertSame(1, preg_match_all('/data-testid="admin-page-header"/', $html));
            self::assertStringContainsString('>'.$heading.'</h1>', $html);
            self::assertStringContainsString('href="#user-basic-data" aria-current="location"', $html);

            foreach (['user-basic-data', 'user-organization', 'user-access', 'user-security'] as $sectionId) {
                self::assertStringContainsString('href="#'.$sectionId.'"', $html);
                self::assertStringContainsString('id="'.$sectionId.'"', $html);
            }
        }
    }

    public function test_account_and_access_forms_keep_separate_post_actions_csrf_versions_and_submit_controls(): void
    {
        $html = $this->renderUser([
            'user' => [
                'version' => 8,
                'branch_id' => 'branch-retained',
                'branch_name' => 'Retained branch',
                'department_id' => 'department-retained',
                'department_name' => 'Retained department',
                'role_ids' => ['visible-role', 'off-page-role'],
            ],
        ], [
            'name' => 'Edited <Mona & Co>',
            'branch_id' => 'branch-retained',
            'department_id' => 'department-retained',
            'role_ids' => ['visible-role', 'off-page-role'],
        ]);

        self::assertSame(1, preg_match('/<form\b(?=[^>]*id="user-form")([^>]*)>(.*?)<\/form>/s', $html, $accountForm));
        self::assertSame(1, preg_match('/<form\b(?=[^>]*id="user-access")([^>]*)>(.*?)<\/form>/s', $html, $accessForm));
        self::assertStringContainsString('method="post"', $accountForm[1]);
        self::assertStringContainsString('action="/access/users/target-user"', $accountForm[1]);
        self::assertStringContainsString('method="post"', $accessForm[1]);
        self::assertStringContainsString('action="/access/users/target-user/access"', $accessForm[1]);

        foreach ([$accountForm[2], $accessForm[2]] as $formBody) {
            self::assertStringContainsString('name="_token" value="'.self::CSRF_TOKEN.'"', $formBody);
            self::assertStringContainsString('name="version" value="8"', $formBody);
        }

        self::assertStringContainsString('Save user details', $accountForm[2]);
        self::assertStringContainsString('Save roles and permissions', $accessForm[2]);
        self::assertStringContainsString('name="_access_assignment" value="1"', $accessForm[2]);
        self::assertStringContainsString('value="Edited &lt;Mona &amp; Co&gt;"', $accountForm[2]);
        self::assertMatchesRegularExpression('/<option\b(?=[^>]*value="branch-retained")(?=[^>]*selected)[^>]*>Retained branch<\/option>/', $accountForm[2]);
        self::assertMatchesRegularExpression('/<option\b(?=[^>]*value="department-retained")(?=[^>]*selected)[^>]*>Retained department<\/option>/', $accountForm[2]);
        self::assertStringContainsString('name="role_ids[]" value="off-page-role"', $accessForm[2]);
        self::assertMatchesRegularExpression('/<input\b(?=[^>]*name="role_ids\[\]")(?=[^>]*value="visible-role")(?=[^>]*checked)[^>]*>/', $accessForm[2]);

        self::assertDoesNotMatchRegularExpression('/name="(?:phone|notes|job_title|photo|last_login)"/', $html);
        self::assertDoesNotMatchRegularExpression('/>\s*(?:Delete user|Reset password)\s*</i', $html);
    }

    public function test_read_only_and_self_views_do_not_expose_access_assignment_mutations(): void
    {
        $readOnly = $this->renderUser([
            'permissions' => ['users.read'],
        ]);
        self::assertStringContainsString('>User details</h1>', $readOnly);
        self::assertStringContainsString('id="user-basic-data"', $readOnly);
        self::assertStringNotContainsString('id="user-form"', $readOnly);
        self::assertStringNotContainsString('id="user-access"', $readOnly);
        self::assertStringNotContainsString('href="#user-access"', $readOnly);

        $self = $this->renderUser([
            'actor' => ['id' => 'target-user', 'name' => 'Synthetic self editor'],
        ]);
        self::assertStringContainsString('id="user-form"', $self);
        self::assertStringNotContainsString('id="user-access"', $self);
        self::assertStringNotContainsString('href="#user-access"', $self);
        self::assertDoesNotMatchRegularExpression('/<input\b(?=[^>]*name="role_ids\[\]")[^>]*>/', $self);
    }

    public function test_incomplete_assignment_scope_displays_a_warning_without_access_controls(): void
    {
        $html = $this->renderUser([
            'user' => ['assignments_editable' => false],
        ]);

        self::assertStringContainsString('href="#user-access"', $html);
        self::assertStringContainsString('id="user-access"', $html);
        self::assertStringContainsString('requires an administrator who can manage all existing assignments', $html);
        self::assertStringNotContainsString('id="user-access-form"', $html);
        self::assertDoesNotMatchRegularExpression('/<input\b(?=[^>]*name="role_ids\[\]")[^>]*>/', $html);
        self::assertStringNotContainsString('name="permissions[', $html);
    }

    public function test_user_values_are_escaped_and_optional_password_and_active_switch_render_truthfully(): void
    {
        $active = $this->renderUser([
            'user' => [
                'name' => '<img src=x onerror=alert(1)>',
                'branch_name' => '<script>branch</script>',
                'department_name' => 'Operations & <Sales>',
                'active' => true,
            ],
        ]);

        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $active);
        self::assertStringContainsString('&lt;script&gt;branch&lt;/script&gt;', $active);
        self::assertStringContainsString('Operations &amp; &lt;Sales&gt;', $active);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $active);
        self::assertStringNotContainsString('<script>branch</script>', $active);
        self::assertMatchesRegularExpression('/<input\b(?=[^>]*id="user-password")(?=[^>]*type="password")[^>]*>/', $active);
        self::assertDoesNotMatchRegularExpression('/<input\b(?=[^>]*id="user-password")[^>]*\bvalue=/', $active);
        self::assertMatchesRegularExpression('/<input\b(?=[^>]*type="checkbox")(?=[^>]*name="active")(?=[^>]*checked)[^>]*>/', $active);

        $inactive = $this->renderUser(['user' => ['active' => false]]);
        self::assertDoesNotMatchRegularExpression('/<input\b(?=[^>]*type="checkbox")(?=[^>]*name="active")[^>]*checked/', $inactive);
        self::assertStringContainsString('name="active" value="0"', $inactive);
    }

    public function test_failed_validation_preserves_direct_permission_choices_with_dotted_keys(): void
    {
        $html = $this->renderUser([], [
            'permissions' => ['organization.read' => '0', 'users.write' => 'inherit', 'roles.read' => '1'],
        ]);

        foreach (['organization.read' => '0', 'users.write' => 'inherit', 'roles.read' => '1'] as $permission => $expected) {
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $options = (new \DOMXPath($document))->query('//select[@name="permissions['.$permission.']"]/option[@selected]');
            self::assertNotFalse($options);
            self::assertSame(1, $options->length);
            self::assertSame($expected, $options->item(0)?->attributes?->getNamedItem('value')?->nodeValue, $permission);
        }
    }

    public function test_missing_location_names_do_not_display_internal_identifiers(): void
    {
        foreach ([['users.read'], ['users.read', 'users.write']] as $permissions) {
            $html = $this->renderUser([
                'permissions' => $permissions,
                'user' => [
                    'branch_id' => 'internal-branch-uuid', 'branch_name' => null,
                    'department_id' => 'internal-department-uuid', 'department_name' => null,
                ],
            ]);
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $profile = (new \DOMXPath($document))->query('//div[contains(concat(" ", normalize-space(@class), " "), " user-profile ")]')->item(0);
            self::assertNotNull($profile);
            self::assertStringNotContainsString('internal-branch-uuid', $profile->textContent);
            self::assertStringNotContainsString('internal-department-uuid', $profile->textContent);
            self::assertStringContainsString('Unavailable', $profile->textContent);
        }
    }

    public function test_failed_validation_preserves_submitted_off_page_roles_without_restoring_removed_roles(): void
    {
        $html = $this->renderUser([
            'user' => ['role_ids' => ['visible-role', 'removed-off-page-role']],
        ], ['role_ids' => ['visible-role', 'selected-off-page-role']]);

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $inputs = (new \DOMXPath($document))->query('//form[@id="user-access"]//input[@name="role_ids[]" and (@type="hidden" or @checked)]');
        self::assertNotFalse($inputs);
        $roles = [];
        foreach ($inputs as $input) {
            $roles[] = $input->attributes?->getNamedItem('value')?->nodeValue;
        }
        self::assertEqualsCanonicalizing(['visible-role', 'selected-off-page-role'], $roles);
    }

    public function test_failed_access_validation_does_not_restore_roles_when_none_were_selected(): void
    {
        $html = $this->renderUser([], [
            '_access_assignment' => '1',
            'version' => 'invalid',
        ]);

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $inputs = (new \DOMXPath($document))->query('//form[@id="user-access"]//input[@name="role_ids[]" and (@type="hidden" or @checked)]');

        self::assertNotFalse($inputs);
        self::assertSame(0, $inputs->length);
    }

    public function test_failed_access_validation_ignores_malformed_old_permission_and_keeps_stored_override(): void
    {
        $html = $this->renderUser([], [
            '_access_assignment' => '1',
            'version' => 'invalid',
            'permissions' => ['users.write' => 'invalid'],
        ]);

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $options = (new \DOMXPath($document))->query('//select[@name="permissions[users.write]"]/option[@selected]');

        self::assertNotFalse($options);
        self::assertSame(1, $options->length);
        self::assertSame('1', $options->item(0)?->attributes?->getNamedItem('value')?->nodeValue);
    }

    /** @param array<string, mixed> $overrides
     * @param  array<string, mixed>  $oldInput
     */
    private function renderUser(array $overrides = [], array $oldInput = []): string
    {
        $this->prepareSession($oldInput);
        $data = [
            'actor' => ['id' => 'synthetic-editor', 'name' => 'Synthetic editor'],
            'permissions' => ['users.read', 'users.write', 'roles.read', 'roles.write'],
            'catalog' => ['organization.read', 'users.read', 'users.write', 'roles.read', 'roles.write'],
            'locale' => 'en',
            'user' => [
                'id' => 'target-user',
                'name' => 'Maha Saleh',
                'email' => 'maha.saleh@example.test',
                'branch_id' => 'branch-one',
                'branch_name' => 'North Branch',
                'department_id' => 'department-one',
                'department_name' => 'Operations',
                'version' => 7,
                'active' => true,
                'assignments_editable' => true,
                'role_ids' => ['visible-role'],
                'permissions' => ['users.write' => true],
            ],
            'branches' => [
                ['id' => 'branch-one', 'name' => 'North Branch'],
                ['id' => 'branch-two', 'name' => 'South Branch'],
            ],
            'departments' => [
                ['id' => 'department-one', 'name' => 'Operations'],
                ['id' => 'department-two', 'name' => 'Sales'],
            ],
            'roles' => [
                ['id' => 'visible-role', 'name' => 'Operations role'],
            ],
            'errors' => new ViewErrorBag,
        ];

        foreach (['user', 'actor'] as $key) {
            if (isset($overrides[$key])) {
                $data[$key] = array_replace($data[$key], $overrides[$key]);
                unset($overrides[$key]);
            }
        }

        if (isset($overrides['permissions'])) {
            $data['permissions'] = $overrides['permissions'];
        }

        return view('access.user', array_replace($data, $overrides))->render();
    }

    /** @param array<string, mixed> $oldInput */
    private function prepareSession(array $oldInput = []): void
    {
        app()->setLocale('en');
        $session = app('session')->driver();
        $session->start();
        $session->put('_token', self::CSRF_TOKEN);
        $session->put('_old_input', $oldInput);
        app('request')->setLaravelSession($session);
    }
}
