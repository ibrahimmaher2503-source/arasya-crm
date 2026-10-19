#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Organization\Infrastructure\CORE01\ReferenceDiagnosticAdapter;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';

$payload = fread(STDIN, 4097);
if ($payload === false || strlen($payload) > 4096) {
    fwrite(STDOUT, "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n");
    exit(2);
}

try {
    $input = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    fwrite(STDOUT, "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n");
    exit(2);
}

$result = (new ReferenceDiagnosticAdapter)->evaluate($input);
if ($result['code'] === 'invalid_diagnostic_input') {
    fwrite(STDOUT, "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n");
    exit(2);
}

fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
