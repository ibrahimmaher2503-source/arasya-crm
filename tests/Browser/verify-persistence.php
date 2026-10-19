<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'arasya_windows_browser'
    || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Persistence verification requires the isolated loopback browser database.');
}
$records = DB::table('vertical_cycle_records')->get();
$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
};
$check($records->count() === 8, '8 browser records durably persisted');
$check($records->filter(fn ($record) => str_starts_with($record->record_type, 'synthetic-browser-')
    && ! in_array($record->record_type, ['synthetic-browser-replay', 'synthetic-browser-isolation', 'synthetic-browser-denied'], true))->count() === 2,
    '2 visible form records persisted');
$check($records->where('record_type', 'synthetic-browser-replay')->count() === 2, '2 replay records persisted without duplicates');
$check($records->where('record_type', 'synthetic-browser-isolation')->count() === 4, '4 entity-isolation records persisted');
$check($records->where('entity_id', 'synthetic-browser-entity')->count() === 6, '6 original entity records persisted');
$check($records->where('entity_id', 'synthetic-browser-other-entity')->count() === 2, '2 second entity records persisted');
$check($records->where('record_type', 'synthetic-browser-denied')->count() === 0, 'Denied browser requests persisted no records');
$check($records->every(fn ($record) => $record->branch_id === 'synthetic-browser-branch'
    && json_decode($record->payload, true, flags: JSON_THROW_ON_ERROR) === ['state' => 'created']),
    'All durable payloads and branch scopes match synthetic inputs');
$deleted = DB::table('vertical_cycle_records')->whereIn('record_id', $records->pluck('record_id'))->delete();
$check($deleted === 8 && DB::table('vertical_cycle_records')->count() === 0, 'Only the 8 captured synthetic record IDs were deleted; database contains 0 cycle records');
