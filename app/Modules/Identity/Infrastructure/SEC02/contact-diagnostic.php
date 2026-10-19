#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\SEC02\ContactDiagnosticAdapter;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';

$error = "تعذّر عرض البيانات. استخدم fixture تشخيصية صحيحة.\n";
$payload = fread(STDIN, 4097);
if ($payload === false || strlen($payload) > 4096) {
    fwrite(STDOUT, $error);
    exit(2);
}

try {
    $input = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    fwrite(STDOUT, $error);
    exit(2);
}

$result = (new ContactDiagnosticAdapter)->project($input);
if ($result === null) {
    fwrite(STDOUT, $error);
    exit(2);
}

fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
