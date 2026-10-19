#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Organization\Infrastructure\CORE02\RecordLinkageDiagnosticAdapter;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';

$payload = '';
while (! feof(STDIN) && strlen($payload) <= 65536) {
    $chunk = fread(STDIN, 8192);
    if ($chunk === false) {
        $payload = false;
        break;
    }
    if ($chunk === '') {
        break;
    }
    $payload .= $chunk;
}

if ($payload === false || strlen($payload) > 65536) {
    fwrite(STDOUT, "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n");
    exit(2);
}

try {
    $input = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    fwrite(STDOUT, "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n");
    exit(2);
}

$result = (new RecordLinkageDiagnosticAdapter)->evaluate($input);
if ($result['code'] === 'invalid_diagnostic_input') {
    fwrite(STDOUT, "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n");
    exit(2);
}

fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
