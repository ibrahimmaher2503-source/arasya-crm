#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Organization\Infrastructure\CORE03\TransitionProposalDiagnosticAdapter;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';

$error = "تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n";
$payload = '';
while (! feof(STDIN) && strlen($payload) <= 65536) {
    $chunk = fread(STDIN, 8192);
    if ($chunk === false) {
        fwrite(STDOUT, $error);
        exit(2);
    }
    if ($chunk === '') {
        break;
    }
    $payload .= $chunk;
}
if (strlen($payload) > 65536) {
    fwrite(STDOUT, $error);
    exit(2);
}

try {
    $input = json_decode($payload, true, 12, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    fwrite(STDOUT, $error);
    exit(2);
}

$result = (new TransitionProposalDiagnosticAdapter)->evaluate($input);
if ($result === null) {
    fwrite(STDOUT, $error);
    exit(2);
}

fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
