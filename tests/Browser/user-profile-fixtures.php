<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$locale = $argv[2] ?? '';
$outputPath = $argv[1] ?? '';
$runId = getenv('ARASYA_USER_PROFILE_RUN_ID') ?: '';
$runDirectory = getenv('ARASYA_USER_PROFILE_RUN_DIRECTORY') ?: '';
$ownerToken = 'A1-CORE-016 user-profile '.$runId;
$assert(in_array($locale, ['ar', 'en'], true), 'Pass locale ar or en.');
$assert($app->environment('testing') && ! $app->configurationIsCached(), 'The user-profile fixture requires testing with uncached configuration.');
$assert(config('database.default') === 'sqlite'
    && config('database.connections.sqlite.database') === ':memory:'
    && config('session.driver') === 'array',
    'The user-profile fixture must use an in-memory database configuration and array sessions.');

$allowedRoot = realpath($projectRoot.'/storage/framework/testing/user-profile');
$resolvedRunDirectory = realpath($runDirectory);
$resolvedOutputDirectory = realpath(dirname($outputPath));
$assert($allowedRoot !== false
    && $resolvedRunDirectory !== false
    && ! is_link($runDirectory)
    && str_starts_with($resolvedRunDirectory, $allowedRoot.DIRECTORY_SEPARATOR)
    && $resolvedOutputDirectory === $resolvedRunDirectory,
    'Rendered user-profile HTML may be written only directly inside its owned isolated run directory.');
$assert(is_file($resolvedRunDirectory.DIRECTORY_SEPARATOR.'.owner')
    && hash_equals($ownerToken, (string) file_get_contents($resolvedRunDirectory.DIRECTORY_SEPARATOR.'.owner')),
    'The user-profile fixture run owner marker did not match.');
$assert(! file_exists($outputPath), 'Refusing to overwrite an existing rendered user-profile fixture.');

$compiledViews = $resolvedRunDirectory.DIRECTORY_SEPARATOR.'compiled-views';
if (! is_dir($compiledViews) && ! mkdir($compiledViews, 0700, true) && ! is_dir($compiledViews)) {
    throw new RuntimeException('Could not create the isolated user-profile compiled-view directory.');
}
$assert(realpath((string) config('view.compiled')) === realpath($compiledViews), 'The Blade compiler must use the isolated user-profile cache.');
$assert(app('db')->getConnections() === [], 'The user-profile presentation fixture must not open a database connection.');

app()->setLocale($locale);
$request = Request::create('/access/users/SYNTHETIC-USER?locale='.$locale, 'GET');
$session = app('session')->driver();
$session->start();
$session->put('_token', 'synthetic-user-profile-csrf-token');
$request->setLaravelSession($session);
$app->instance('request', $request);

$makePaginator = static fn (array $items, string $pageName): LengthAwarePaginator => new LengthAwarePaginator(
    $items,
    11,
    10,
    1,
    ['path' => '/access/users/SYNTHETIC-USER', 'pageName' => $pageName],
);
$branches = $makePaginator(array_map(
    static fn (int $id): array => ['id' => 'branch-'.$id, 'name' => 'Branch option '.$id],
    range(1, 10),
), 'branch_page');
$departments = $makePaginator(array_map(
    static fn (int $id): array => ['id' => 'department-'.$id, 'name' => 'Department option '.$id],
    range(1, 10),
), 'department_page');
$roles = $makePaginator(array_map(
    static fn (int $id): array => ['id' => 'role-'.$id, 'name' => 'Role option '.$id],
    range(1, 10),
), 'role_page');

$data = [
    'actor' => ['id' => 'SYNTHETIC-ADMIN', 'name' => 'Synthetic administrator'],
    'permissions' => ['organization.read', 'users.read', 'users.write', 'roles.read', 'roles.write', 'audit.read'],
    'catalog' => ['organization.read', 'users.read', 'users.write', 'roles.read', 'roles.write', 'audit.read'],
    'locale' => $locale,
    'user' => [
        'id' => 'SYNTHETIC-USER',
        'name' => $locale === 'ar' ? 'مها صالح' : 'Maha Saleh',
        'email' => 'maha.saleh@example.test',
        'branch_id' => 'branch-retained',
        'branch_name' => $locale === 'ar' ? 'فرع محفوظ خارج الصفحة' : 'Retained branch outside this page',
        'department_id' => 'department-retained',
        'department_name' => $locale === 'ar' ? 'قسم محفوظ خارج الصفحة' : 'Retained department outside this page',
        'version' => 12,
        'active' => true,
        'assignments_editable' => true,
        'role_ids' => ['role-1', 'role-retained'],
        'permissions' => ['users.write' => true],
    ],
    'branches' => $branches,
    'departments' => $departments,
    'roles' => $roles,
    'errors' => new ViewErrorBag,
];

$html = view('access.user', $data)->render();
$assert(app('db')->getConnections() === [], 'Rendering the user-profile page must not open a database connection.');
$handle = fopen($outputPath, 'x');
if ($handle === false) {
    throw new RuntimeException('Could not create the unique rendered user-profile HTML fixture.');
}
try {
    if (fwrite($handle, $html) !== strlen($html)) {
        throw new RuntimeException('Could not write the complete rendered user-profile HTML fixture.');
    }
} finally {
    fclose($handle);
}

echo "Rendered synthetic {$locale} user-profile Blade shell without database or runtime session access.\n";
