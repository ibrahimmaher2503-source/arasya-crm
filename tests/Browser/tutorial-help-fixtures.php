<?php

declare(strict_types=1);

use App\Modules\Help\Application\HelpCatalog;
use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Providers\HelpServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\URL;
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

$locale = $argv[1] ?? '';
$outputPath = $argv[2] ?? '';
$outputDirectory = $argv[3] ?? '';
$ownerToken = $argv[4] ?? '';
$articleId = $argv[5] ?? '';
$assert(in_array($locale, ['ar', 'en'], true), 'Pass locale ar or en.');
$assert($articleId === '' || preg_match('/^[a-z0-9-]{1,80}$/D', $articleId) === 1, 'Pass a valid optional article ID.');
$assert($app->environment('testing'), 'The synthetic Help fixture requires APP_ENV=testing.');
$assert(! $app->configurationIsCached(), 'The synthetic Help fixture requires uncached configuration.');
$assert(config('database.default') === 'sqlite'
    && config('database.connections.sqlite.database') === ':memory:'
    && config('session.driver') === 'array',
    'The synthetic Help fixture must not connect to a database or write runtime sessions.');

$allowedOutputRoot = realpath($projectRoot.'/.local/windows-qa');
$resolvedOutputDirectory = realpath($outputDirectory);
$resolvedTargetDirectory = realpath(dirname($outputPath));
$markerPath = $resolvedOutputDirectory === false ? '' : $resolvedOutputDirectory.'/.tutorial-help-owner';
$assert($allowedOutputRoot !== false
    && $resolvedOutputDirectory !== false
    && str_starts_with($resolvedOutputDirectory, $allowedOutputRoot.DIRECTORY_SEPARATOR)
    && $resolvedTargetDirectory === $resolvedOutputDirectory,
    'Rendered HTML may be written only directly inside a caller-owned, unique Playwright output directory.');
$assert($ownerToken !== ''
    && is_file($markerPath)
    && hash_equals($ownerToken, (string) file_get_contents($markerPath)),
    'The unique output directory owner marker did not match.');
$assert(! file_exists($outputPath), 'Refusing to overwrite an existing rendered Help fixture.');

$compiledViews = $resolvedOutputDirectory.DIRECTORY_SEPARATOR.'compiled-views';
if (! is_dir($compiledViews) && ! mkdir($compiledViews, 0700, true) && ! is_dir($compiledViews)) {
    throw new RuntimeException('Could not create the isolated compiled-view directory.');
}
config(['view.compiled' => $compiledViews]);
config(['session.driver' => 'array']);
URL::forceRootUrl('http://tutorial-help.test');
URL::forceScheme('http');
app()->setLocale($locale);
app()->register(HelpServiceProvider::class);
app('router')->getRoutes()->refreshNameLookups();

$actor = new ActorAccess(
    'synthetic-help-browser-user',
    'synthetic-help-browser-company',
    'synthetic-help-browser-department',
    AccessControl::PERMISSIONS,
    [],
    'synthetic-help-browser-branch',
);
$catalog = app(HelpCatalog::class);
$csrfToken = 'synthetic-help-csrf-token';
$session = app('session')->driver();
$session->start();
$session->put('_token', $csrfToken);
$tour = $catalog->tour($actor, 'help-center', $locale);
$tour['csrf_token'] = csrf_token();
$assert($tour['csrf_token'] === $csrfToken, 'The rendered tour must carry the synthetic CSRF token.');

$errors = new ViewErrorBag;
if ($articleId === '') {
    $view = 'help.index';
    $data = $catalog->index($actor, $locale) + [
        'actor' => $actor,
        'permissions' => $actor->inheritedPermissions,
        'locale' => $locale,
        'title' => __('help.ui.title', [], $locale),
        'pageSubtitle' => __('help.ui.subtitle', [], $locale),
        'pageIcon' => 'help',
        'breadcrumbs' => [['label' => __('help.ui.title', [], $locale)]],
        'tour' => $tour,
        'errors' => $errors,
    ];
} else {
    $view = 'help.article';
    $page = $catalog->page($actor, $articleId, $locale);
    $data = [
        'actor' => $actor,
        'permissions' => $actor->inheritedPermissions,
        'locale' => $locale,
        'title' => $page['title'],
        'pageSubtitle' => $page['summary'] ?? '',
        'pageIcon' => 'help',
        'breadcrumbs' => [
            ['label' => __('help.ui.title', [], $locale), 'url' => route('help.index', ['locale' => $locale])],
            ['label' => $page['title']],
        ],
        'page' => $page,
        'errors' => $errors,
    ];
}
$html = view($view, $data)->render();
$handle = fopen($outputPath, 'x');
if ($handle === false) {
    throw new RuntimeException('Could not create the unique rendered Help HTML file.');
}
try {
    if (fwrite($handle, $html) !== strlen($html)) {
        throw new RuntimeException('Could not write the complete rendered Help HTML file.');
    }
} finally {
    fclose($handle);
}

echo "Rendered synthetic {$locale} Help Blade fixture; no database or runtime session was used.\n";
