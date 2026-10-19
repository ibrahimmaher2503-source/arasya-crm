<?php

declare(strict_types=1);

namespace App\Modules\Help\Application;

use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class HelpCatalog
{
    /** @var array<string, array{url: ?string, all?: list<string>, any?: list<string>, kind?: string, gate?: string}> */
    private const PAGES = [
        'dashboard-work-center' => ['url' => '/dashboard', 'any' => AccessControl::PERMISSIONS, 'kind' => 'standard'],
        'access-overview' => ['url' => '/access', 'any' => ['organization.read', 'users.read', 'roles.read', 'audit.read'], 'kind' => 'standard'],
        'access-organization' => ['url' => '/access/organization', 'all' => ['organization.read'], 'kind' => 'standard'],
        'access-users' => ['url' => '/access/users', 'all' => ['users.read'], 'kind' => 'standard'],
        'access-user-detail' => ['url' => null, 'all' => ['users.read'], 'kind' => 'record'],
        'access-roles' => ['url' => '/access/roles', 'all' => ['roles.read'], 'kind' => 'standard'],
        'access-role-detail' => ['url' => null, 'all' => ['roles.read'], 'kind' => 'record'],
        'access-audit' => ['url' => '/access/audit', 'all' => ['audit.read'], 'kind' => 'standard'],
        'employees-directory' => ['url' => '/employees', 'all' => ['employees.read'], 'kind' => 'standard'],
        'employees-workers' => ['url' => '/employees/workers', 'all' => ['employees.read'], 'kind' => 'standard'],
        'employee-create' => ['url' => '/employees/create', 'all' => ['employees.read', 'employees.write'], 'kind' => 'form'],
        'employee-edit' => ['url' => null, 'all' => ['employees.read', 'employees.write'], 'kind' => 'form'],
        'employee-profile' => ['url' => null, 'all' => ['employees.read'], 'kind' => 'record'],
        'employee-work' => ['url' => null, 'all' => ['employees.read'], 'kind' => 'pending'],
        'tasks-list' => ['url' => '/tasks', 'all' => ['tasks.read'], 'kind' => 'standard'],
        'task-create' => ['url' => '/tasks/create', 'all' => ['tasks.write'], 'kind' => 'form'],
        'task-detail' => ['url' => null, 'all' => ['tasks.read'], 'kind' => 'record'],
        'crm-overview' => ['url' => '/crm', 'all' => ['crm.read'], 'kind' => 'standard'],
        'crm-leads-list' => ['url' => '/crm/leads', 'all' => ['crm.read'], 'kind' => 'standard'],
        'crm-lead-create' => ['url' => '/crm/leads/create', 'all' => ['crm.read', 'crm.manage'], 'kind' => 'form'],
        'crm-lead-edit' => ['url' => null, 'all' => ['crm.read', 'crm.manage'], 'kind' => 'form'],
        'crm-lead-detail' => ['url' => null, 'all' => ['crm.read'], 'kind' => 'record'],
        'crm-activities' => ['url' => null, 'all' => ['crm.read'], 'kind' => 'record'],
        'crm-notes' => ['url' => null, 'all' => ['crm.read'], 'kind' => 'record'],
        'crm-follow-ups' => ['url' => null, 'all' => ['crm.read'], 'kind' => 'workflow'],
        'crm-files' => ['url' => null, 'all' => ['crm.read'], 'kind' => 'record'],
        'crm-pipeline' => ['url' => '/crm/pipeline', 'all' => ['crm.read'], 'kind' => 'testing', 'gate' => 'sales'],
        'quotation-list' => ['url' => null, 'all' => ['crm.read'], 'kind' => 'testing', 'gate' => 'sales'],
        'quotation-create' => ['url' => null, 'all' => ['crm.read', 'crm.write'], 'kind' => 'testing', 'gate' => 'sales'],
        'quotation-edit' => ['url' => null, 'all' => ['crm.read', 'crm.write'], 'kind' => 'testing', 'gate' => 'sales'],
        'quotation-preview' => ['url' => null, 'all' => ['crm.read'], 'kind' => 'testing', 'gate' => 'sales'],
        'vertical-cycle-records' => ['url' => '/vertical/cycle-records', 'kind' => 'technical'],
        'design-system-showcase' => ['url' => '/dev/design-system', 'kind' => 'development', 'gate' => 'development'],
    ];

    /** @var array<string, array<string, list<string>>> */
    private const ACTIONS = [
        'dashboard-work-center' => ['open-employees' => ['employees.read'], 'open-tasks' => ['tasks.read'], 'open-crm' => ['crm.read']],
        'access-overview' => ['open-organization' => ['organization.read'], 'open-users' => ['users.read'], 'open-roles' => ['roles.read'], 'open-audit' => ['audit.read']],
        'access-organization' => ['edit' => ['organization.write']],
        'access-users' => ['create' => ['users.write'], 'edit' => ['users.write'], 'assign' => ['users.write', 'roles.write']],
        'access-user-detail' => ['edit' => ['users.write'], 'assign' => ['users.write', 'roles.write']],
        'access-roles' => ['create' => ['roles.write'], 'edit' => ['roles.write']],
        'access-role-detail' => ['edit' => ['roles.write']],
        'employees-directory' => ['create' => ['employees.write'], 'private-fields' => ['employee.private']],
        'employees-workers' => ['create-worker' => ['employees.write'], 'manage-all-workers' => ['employees.manage_all']],
        'employee-create' => ['save' => ['employees.write'], 'manage-all-fields' => ['employees.manage_all']],
        'employee-edit' => ['save' => ['employees.write'], 'manage-all-fields' => ['employees.manage_all']],
        'employee-profile' => ['edit' => ['employees.write'], 'record-evidence' => ['employees.evidence.write'], 'view-private-fields' => ['employee.private'], 'look-up-task' => ['tasks.read']],
        'tasks-list' => ['create' => ['tasks.write']],
        'task-create' => ['save' => ['tasks.write']],
        'task-detail' => ['start' => ['tasks.write'], 'attach-evidence' => ['tasks.write'], 'complete' => ['tasks.write']],
        'crm-overview' => ['open-transfers' => ['crm.write']],
        'crm-leads-list' => ['create' => ['crm.manage'], 'edit' => ['crm.manage']],
        'crm-lead-create' => ['save' => ['crm.manage', 'crm.write']],
        'crm-lead-edit' => ['save' => ['crm.manage', 'crm.write']],
        'crm-lead-detail' => ['edit' => ['crm.manage'], 'activity' => ['crm.write'], 'assign' => ['crm.manage', 'crm.assign'], 'transfer' => ['crm.transfer'], 'convert' => ['crm.convert'], 'reveal-contact' => ['contact.reveal']],
        'crm-activities' => ['record-activity' => ['crm.write']],
        'crm-notes' => ['record-note' => ['crm.write']],
        'crm-follow-ups' => ['record-follow-up' => ['crm.write']],
        'crm-files' => ['upload' => ['crm.write'], 'download' => ['crm.read']],
        'crm-pipeline' => ['save-opportunity' => ['crm.write']],
        'quotation-list' => ['create-draft' => ['crm.write']],
        'quotation-create' => ['save-draft' => ['crm.write']],
        'quotation-edit' => ['save-draft' => ['crm.write']],
    ];

    /** @var array<string, array<string, list<string>>> */
    private const SECTION_PERMISSIONS = [
        'access-organization' => ['forms' => ['organization.write']],
        'access-users' => ['forms' => ['users.write']],
        'access-user-detail' => ['forms' => ['users.write']],
        'access-roles' => ['forms' => ['roles.write']],
        'access-role-detail' => ['forms' => ['roles.write']],
        'task-detail' => ['transitions' => ['tasks.write'], 'forms' => ['tasks.write']],
        'crm-activities' => ['forms' => ['crm.write']],
        'crm-notes' => ['forms' => ['crm.write']],
        'crm-follow-ups' => ['forms' => ['crm.write', 'tasks.write']],
        'crm-files' => ['forms' => ['crm.write']],
        'crm-pipeline' => ['forms' => ['crm.write']],
        'quotation-list' => ['forms' => ['crm.write']],
        'quotation-create' => ['forms' => ['crm.write']],
        'quotation-edit' => ['forms' => ['crm.write']],
    ];

    /** @var array<string, list<string>> */
    private const WORKFLOW_PERMISSIONS = [
        'create-employee' => ['employees.read', 'employees.write'],
        'employee-work-evidence' => ['employees.read', 'employees.evidence.write'],
        'create-task' => ['tasks.write'],
        'complete-task' => ['tasks.read', 'tasks.write'],
        'create-lead' => ['crm.read', 'crm.manage', 'crm.write'],
        'record-follow-up' => ['crm.read', 'crm.write', 'tasks.write'],
        'convert-lead' => ['crm.read', 'crm.convert'],
        'manage-access' => ['users.read', 'users.write', 'roles.write'],
        'synthetic-quotation' => ['crm.read', 'crm.write'],
    ];

    /** @var array<string, list<string>> */
    private const ARTICLE_PERMISSIONS = [
        'transfer-inbox' => ['crm.read', 'crm.write'],
        'lead-conversion' => ['crm.read', 'crm.convert'],
        'contact-privacy' => ['crm.read', 'contact.reveal'],
        'record-scope' => [],
    ];

    /** @var array<string, array{version: int, all?: list<string>, any?: list<string>, kind?: string, gate?: string, integrated: bool, steps: array<string, string>}> */
    private const TOURS = [
        'help-center' => ['version' => 1, 'kind' => 'standard', 'integrated' => true, 'steps' => ['welcome' => 'help.index.header', 'search' => 'help.index.search', 'collections' => 'help.index.collections', 'tour-controls' => 'help.index.tours']],
        'dashboard' => ['version' => 1, 'any' => AccessControl::PERMISSIONS, 'integrated' => true, 'steps' => ['welcome' => 'dashboard.heading', 'summary' => 'dashboard.summary', 'records' => 'dashboard.records']],
        'access-overview' => ['version' => 1, 'any' => ['organization.read', 'users.read', 'roles.read', 'audit.read'], 'integrated' => true, 'steps' => ['sections' => 'access.overview.sections']],
        'access-users' => ['version' => 1, 'all' => ['users.read'], 'integrated' => true, 'steps' => ['filters' => 'access.users.filters', 'results' => 'access.users.results']],
        'access-organization' => ['version' => 1, 'all' => ['organization.read'], 'integrated' => true, 'steps' => ['filters' => 'access.organization.filters', 'results' => 'access.organization.results']],
        'access-roles' => ['version' => 1, 'all' => ['roles.read'], 'integrated' => true, 'steps' => ['filters' => 'access.roles.filters', 'results' => 'access.roles.results']],
        'access-audit' => ['version' => 1, 'all' => ['audit.read'], 'integrated' => true, 'steps' => ['filters' => 'access.audit.filters', 'results' => 'access.audit.results']],
        'employees-directory' => ['version' => 1, 'all' => ['employees.read'], 'integrated' => true, 'steps' => ['filters' => 'employees.filters', 'results' => 'employees.results']],
        'employees-workers' => ['version' => 1, 'all' => ['employees.read'], 'integrated' => true, 'steps' => ['filters' => 'employees.workers.filters', 'results' => 'employees.workers.results']],
        'crm-overview' => ['version' => 1, 'all' => ['crm.read'], 'integrated' => true, 'steps' => ['nav' => 'crm.overview.nav', 'summary' => 'crm.overview.summary']],
        'crm-leads-list' => ['version' => 1, 'all' => ['crm.read'], 'integrated' => true, 'steps' => ['filters' => 'crm.leads.filters', 'results' => 'crm.leads.results']],
        'tasks-list' => ['version' => 1, 'all' => ['tasks.read'], 'integrated' => false, 'steps' => ['filters' => 'tasks.filters', 'results' => 'tasks.results']],
        'crm-pipeline' => ['version' => 1, 'all' => ['crm.read'], 'kind' => 'testing', 'gate' => 'sales', 'integrated' => false, 'steps' => ['stages' => 'crm.pipeline.stages', 'filters' => 'crm.pipeline.filters']],
        'quotation-list' => ['version' => 1, 'all' => ['crm.read'], 'kind' => 'testing', 'gate' => 'sales', 'integrated' => false, 'steps' => ['drafts' => 'sales.quotations.list']],
    ];

    /** @return array<string, mixed> */
    public function index(ActorAccess $actor, string $locale, string $search = ''): array
    {
        $pages = $this->pages($actor, $locale);
        $workflows = $this->filteredTranslations('help.workflows', self::WORKFLOW_PERMISSIONS, $actor, $locale);
        $articles = $this->filteredTranslations('help.articles', self::ARTICLE_PERMISSIONS, $actor, $locale);
        $faqs = $this->filteredTranslations('help.faqs', [
            'transfer-inbox' => ['crm.read', 'crm.write'],
            'customer-screen' => ['crm.read'],
            'permissions' => [],
            'tutorial-safety' => [],
        ], $actor, $locale);
        $glossary = $this->translations('help.glossary', $locale);

        if ($search !== '') {
            $pages = array_filter($pages, fn (array $page): bool => $this->contains($page, $search));
            $workflows = array_filter($workflows, fn (array $item): bool => $this->contains($item, $search));
            $articles = array_filter($articles, fn (array $item): bool => $this->contains($item, $search));
            $faqs = array_filter($faqs, fn (array $item): bool => $this->contains($item, $search));
            $glossary = array_filter($glossary, fn (array $item): bool => $this->contains($item, $search));
        }

        return [
            'pages' => array_values($pages),
            'workflows' => array_values($workflows),
            'articles' => array_values($articles),
            'faqs' => array_values($faqs),
            'glossary' => array_values($glossary),
            'tours' => $this->tours($actor, $locale),
            'query' => $search,
        ];
    }

    /** @return array<string, mixed> */
    public function page(ActorAccess $actor, string $id, string $locale): array
    {
        foreach ($this->pages($actor, $locale) as $page) {
            if ($page['id'] === $id) {
                return $page;
            }
        }
        foreach ($this->filteredTranslations('help.articles', self::ARTICLE_PERMISSIONS, $actor, $locale) as $article) {
            if ($article['id'] === $id) {
                return ['classification' => 'guide', 'url' => null, 'availability' => 'available'] + $article;
            }
        }

        throw new NotFoundHttpException('Help article not found.');
    }

    /** @return array<string, mixed> */
    public function tour(ActorAccess $actor, string $id, string $locale): array
    {
        $definition = self::TOURS[$id] ?? null;
        if ($definition === null) {
            throw new NotFoundHttpException('Help tour not found.');
        }
        if (! $this->allows($actor, $definition)) {
            throw new AccessDeniedHttpException('Help tour access is not available.');
        }
        if (! $this->gateIsOpen($definition['gate'] ?? null)) {
            throw new NotFoundHttpException('Help tour not found.');
        }

        $copy = $this->translations('help.tours.'.$id, $locale);
        $stepsCopy = is_array($copy['steps'] ?? null) ? $copy['steps'] : [];
        $steps = [];
        foreach ($definition['steps'] as $key => $anchor) {
            $text = $stepsCopy[$key] ?? [];
            if (! is_array($text)) {
                continue;
            }
            $steps[] = [
                'key' => $key,
                'anchor' => $anchor,
                'title' => is_string($text['title'] ?? null) ? $text['title'] : '',
                'body' => is_string($text['body'] ?? null) ? $text['body'] : '',
            ];
        }

        return ['id' => $id, 'version' => $definition['version'], 'integrated' => $definition['integrated'], 'title' => $copy['title'] ?? '', 'steps' => $steps];
    }

    /**
     * Server-authored configuration for the browser tour component, including copy and CSRF.
     *
     * @return array<string, mixed>
     */
    public function tourConfig(ActorAccess $actor, string $id, string $locale): array
    {
        $tour = $this->tour($actor, $id, $locale);

        return [
            'tour_id' => $tour['id'],
            'version' => $tour['version'],
            'integrated' => $tour['integrated'],
            'title' => $tour['title'],
            'steps' => $tour['steps'],
            'csrf_token' => csrf_token(),
            'messages' => $this->tourMessages($locale),
        ];
    }

    /** @return array<string, mixed> */
    private function tourMessages(string $locale): array
    {
        return [
            'status' => [
                'not_started' => __('help.ui.status_not_started', [], $locale),
                'in_progress' => __('help.ui.status_in_progress', [], $locale),
                'paused' => __('help.ui.status_paused', [], $locale),
                'completed' => __('help.ui.status_completed', [], $locale),
                'skipped' => __('help.ui.status_skipped', [], $locale),
            ],
            'resume' => __('help.ui.tour_resume', [], $locale),
            'start' => __('help.ui.tour_start', [], $locale),
            'next' => __('help.ui.tour_next', [], $locale),
            'finish' => __('help.ui.tour_finish', [], $locale),
            'step_count' => __('help.ui.tour_step_count', [], $locale),
            'done' => __('help.ui.tour_done', [], $locale),
            'missing_anchors' => __('help.ui.tour_missing_anchors', [], $locale),
            'save_failed' => __('help.ui.tour_save_failed', [], $locale),
            'status_failed' => __('help.ui.tour_status_failed', [], $locale),
        ];
    }

    /** @return list<string> */
    public function stepKeys(string $id): array
    {
        $definition = self::TOURS[$id] ?? null;
        if ($definition === null) {
            throw new NotFoundHttpException('Help tour not found.');
        }

        return array_keys($definition['steps']);
    }

    /** @return list<array<string, mixed>> */
    private function pages(ActorAccess $actor, string $locale): array
    {
        $copy = $this->translations('help.pages', $locale);
        $pages = [];

        foreach (self::PAGES as $id => $definition) {
            if (! $this->allows($actor, $definition)) {
                continue;
            }
            $entry = $copy[$id] ?? null;
            if (! is_array($entry)) {
                continue;
            }

            $classification = $definition['kind'];
            $available = $this->gateIsOpen($definition['gate'] ?? null) && $classification !== 'pending';
            $entryActions = is_array($entry['actions'] ?? null) ? $entry['actions'] : [];
            $visibleActions = [];
            foreach ($entryActions as $actionId => $description) {
                $requirements = is_string($actionId) ? (self::ACTIONS[$id][$actionId] ?? null) : null;
                if (is_string($description) && is_array($requirements) && $this->hasPermissions($actor, $requirements)) {
                    $visibleActions[] = ['id' => $actionId, 'text' => $description];
                }
            }
            foreach (self::SECTION_PERMISSIONS[$id] ?? [] as $section => $requirements) {
                if (! $this->hasPermissions($actor, $requirements)) {
                    $entry[$section] = [];
                }
            }
            $entry += ['purpose' => '', 'information' => [], 'filters' => [], 'actions' => [], 'forms' => [], 'transitions' => [], 'errors' => [], 'conditions' => [], 'next_step' => ''];
            $entry['actions'] = $available ? $visibleActions : [];
            $entry['id'] = $id;
            $entry['url'] = $available ? $definition['url'] : null;
            $entry['classification'] = $classification;
            $entry['availability'] = ! $available ? ($classification === 'pending' ? 'pending' : 'unavailable') : 'available';
            $entry['availability_message'] = ! $available
                ? ($classification === 'pending' ? __('help.availability.pending', [], $locale) : __('help.availability.gated', [], $locale))
                : null;
            $pages[] = $entry;
        }

        return $pages;
    }

    /** @param array<string, list<string>> $requirementsById
     * @return list<array<string, mixed>>
     */
    private function filteredTranslations(string $key, array $requirementsById, ActorAccess $actor, string $locale): array
    {
        $copy = $this->translations($key, $locale);
        $items = [];
        foreach ($copy as $id => $entry) {
            $requirements = $requirementsById[$id] ?? null;
            if (is_array($entry) && is_array($requirements) && $this->hasPermissions($actor, $requirements)) {
                $items[] = ['id' => $id] + $entry;
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function translations(string $key, string $locale): array
    {
        $value = trans($key, [], $locale);

        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $definition */
    private function allows(ActorAccess $actor, array $definition): bool
    {
        return $this->hasPermissions($actor, $definition['all'] ?? [])
            && (($definition['any'] ?? []) === [] || count(array_intersect($definition['any'], $actor->inheritedPermissions)) > 0);
    }

    /** @param list<string> $permissions */
    private function hasPermissions(ActorAccess $actor, array $permissions): bool
    {
        return array_diff($permissions, $actor->inheritedPermissions) === [];
    }

    /** @param array<string, mixed> $value */
    private function contains(array $value, string $search): bool
    {
        foreach ($value as $item) {
            if (is_array($item) && $this->contains($item, $search)) {
                return true;
            }
            if (is_string($item) && mb_stripos($item, $search) !== false) {
                return true;
            }
        }

        return false;
    }

    private function gateIsOpen(?string $gate): bool
    {
        return match ($gate) {
            'sales' => app()->environment('testing') && config('sales.synthetic_drafts') === true,
            'development' => app()->environment(['local', 'testing']) === true,
            default => true,
        };
    }

    /** @return list<array<string, mixed>> */
    private function tours(ActorAccess $actor, string $locale): array
    {
        $tours = [];
        foreach (self::TOURS as $id => $definition) {
            if (! $this->allows($actor, $definition) || ! $this->gateIsOpen($definition['gate'] ?? null)) {
                continue;
            }
            $copy = $this->translations('help.tours.'.$id, $locale);
            $tours[] = ['id' => $id, 'title' => $copy['title'] ?? '', 'integrated' => $definition['integrated']];
        }

        return $tours;
    }
}
