<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('session.driver') !== 'file') {
    throw new RuntimeException('Browser fixtures require APP_ENV=testing and SESSION_DRIVER=file.');
}

$destination = $argv[1] ?? throw new RuntimeException('Pass a storage state output path.');
$mode = $argv[2] ?? 'authorized';
if (! in_array($mode, ['authorized', 'denied', 'other-entity'], true)) {
    throw new RuntimeException('Unknown synthetic identity mode.');
}
$baseUrl = getenv('ARASYA_BROWSER_BASE_URL') ?: 'http://127.0.0.1:8765';
if (! in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Synthetic sessions may target loopback only.');
}
$session = $app->make('session')->driver();
$session->start();
$session->put([
    'actor_id' => 'synthetic-browser-actor',
    'entity_id' => $mode === 'other-entity' ? 'synthetic-browser-other-entity' : 'synthetic-browser-entity',
    'department_id' => 'synthetic-browser-branch',
    'permissions' => $mode === 'denied' ? [] : ['cycles.write'],
]);
$session->save();
$name = config('session.cookie');
$encrypter = $app->make('encrypter');
$cookie = $encrypter->encrypt(
    CookieValuePrefix::create($name, $encrypter->getKey()).$session->getId(),
    EncryptCookies::serialized($name),
);
$state = ['cookies' => [[
    'name' => $name,
    'value' => $cookie,
    'domain' => parse_url($baseUrl, PHP_URL_HOST),
    'path' => '/',
    'expires' => -1,
    'httpOnly' => true,
    'secure' => false,
    'sameSite' => 'Lax',
]], 'origins' => []];
if (file_put_contents($destination, json_encode($state, JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Could not write browser state.');
}
echo "Synthetic {$mode} browser state created.\n";
