<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = $argv[2] ?? '';
$runPath = $argv[3] ?? '';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') === 'worker') {
    [$mode, $key, $barrier, $ready] = array_slice($argv, 4, 4);
    file_put_contents($ready, 'ready');
    $deadline = microtime(true) + 12;
    while (! is_file($barrier) && microtime(true) < $deadline) {
        usleep(20000);
    }
    if (! is_file($barrier)) {
        fwrite(STDERR, "WORKER_BARRIER_TIMEOUT\n");
        exit(20);
    }
    try {
        $result = DB::transaction(function () use ($mode, $key): string {
            if ($mode === 'reserve' && DB::table('adrv_reservations')->where('request_key', $key)->exists()) {
                return 'SUCCESS';
            }
            $item = $mode === 'reserve' ? 'reserve-item' : 'issue-item';
            $stock = DB::table('adrv_stock_state')->where('item', $item)->lockForUpdate()->first();
            if ($mode === 'reserve') {
                if ($stock->available < 4) {
                    return 'REJECTED';
                }
                DB::table('adrv_stock_state')->where('item', $item)->update(['available' => $stock->available - 4, 'reserved' => $stock->reserved + 4]);
                DB::table('adrv_reservations')->insert(['request_key' => $key, 'item' => $item, 'quantity' => 4]);
            } else {
                if ($stock->reserved < 4 || $stock->on_hand < 4) {
                    return 'REJECTED';
                }
                DB::table('adrv_stock_state')->where('item', $item)->update(['reserved' => $stock->reserved - 4, 'on_hand' => $stock->on_hand - 4]);
                DB::table('adrv_movements')->insert(['request_key' => $key, 'item' => $item, 'quantity' => 4]);
            }
            DB::table('adrv_outbox')->insert(['event_id' => 'race-'.$mode.'-'.$key, 'event_type' => $mode === 'reserve' ? 'reserved' : 'issued', 'aggregate_id' => $key]);

            return 'SUCCESS';
        });
        fwrite(STDOUT, $result."\n");
        exit(0);
    } catch (Throwable $failure) {
        fwrite(STDERR, "WORKER_DB_FAILURE\n");
        exit(21);
    }
}

function runRace(string $mode, string $root, string $runPath): array
{
    $token = bin2hex(random_bytes(6));
    $barrier = $runPath.DIRECTORY_SEPARATOR.'race-'.$token.'.go';
    $processes = [];
    foreach (['a', 'b'] as $suffix) {
        $key = 'race-'.$mode.'-'.$suffix;
        $ready = $runPath.DIRECTORY_SEPARATOR.'race-'.$token.'-'.$suffix.'.ready';
        $pipes = [];
        $command = [PHP_BINARY, __FILE__, 'worker', $root, $runPath, $mode, $key, $barrier, $ready];
        $proc = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (! is_resource($proc)) {
            throw new RuntimeException('RACE_CHILD_START_FAILED');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $processes[] = ['proc' => $proc, 'pipes' => $pipes, 'ready' => $ready, 'output' => '', 'error' => '', 'exit' => null];
    }
    try {
        $deadline = microtime(true) + 10;
        while ((! is_file($processes[0]['ready']) || ! is_file($processes[1]['ready'])) && microtime(true) < $deadline) {
            usleep(20000);
        }
        if (! is_file($processes[0]['ready']) || ! is_file($processes[1]['ready'])) {
            throw new RuntimeException('RACE_READY_TIMEOUT');
        }
        file_put_contents($barrier, 'release');
        $deadline = microtime(true) + 12;
        while (microtime(true) < $deadline) {
            $running = false;
            foreach ($processes as &$child) {
                $child['output'] .= stream_get_contents($child['pipes'][1]);
                $child['error'] .= stream_get_contents($child['pipes'][2]);
                $status = proc_get_status($child['proc']);
                if ($status['running']) {
                    $running = true;
                } else {
                    $child['exit'] = $status['exitcode'];
                }
            }
            unset($child);
            if (! $running) {
                break;
            }
            usleep(20000);
        }
        foreach ($processes as &$child) {
            if ($child['exit'] === null) {
                proc_terminate($child['proc']);
                $child['exit'] = 124;
            }
            $child['output'] .= stream_get_contents($child['pipes'][1]);
            $child['error'] .= stream_get_contents($child['pipes'][2]);
            fclose($child['pipes'][1]);
            fclose($child['pipes'][2]);
            proc_close($child['proc']);
            @unlink($child['ready']);
        }
        unset($child);
        $results = [];
        foreach ($processes as $child) {
            if ($child['exit'] !== 0 || ! preg_match('/^(SUCCESS|REJECTED)\s*$/', trim($child['output']), $match)) {
                throw new RuntimeException('RACE_CHILD_FAILED');
            } $results[] = $match[1];
        }

        return $results;
    } finally {
        @unlink($barrier);
        foreach ($processes as $child) {
            if (is_resource($child['proc'])) {
                $status = proc_get_status($child['proc']);
                if ($status['running']) {
                    proc_terminate($child['proc']);
                }
                foreach ($child['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($child['proc']);
            }
            @unlink($child['ready']);
        }
    }
}

try {
    $db = DB::connection();
    $db->table('adrv_stock_state')->where('item', 'reserve-item')->update(['available' => 4, 'reserved' => 0, 'on_hand' => 4]);
    $db->table('adrv_reservations')->where('request_key', 'like', 'race-reserve-%')->delete();
    $db->table('adrv_outbox')->where('event_id', 'like', 'race-reserve-%')->delete();
    $reserveResults = runRace('reserve', $root, $runPath);
    if (count(array_filter($reserveResults, fn ($result) => $result === 'SUCCESS')) !== 1 || count(array_filter($reserveResults, fn ($result) => $result === 'REJECTED')) !== 1 || (int) $db->table('adrv_stock_state')->where('item', 'reserve-item')->value('available') !== 0 || $db->table('adrv_reservations')->where('request_key', 'like', 'race-reserve-%')->count() !== 1 || $db->table('adrv_outbox')->where('event_id', 'like', 'race-reserve-%')->count() !== 1) {
        throw new RuntimeException('RACE_RESERVE_ASSERTION');
    }
    $winner = $db->table('adrv_reservations')->where('request_key', 'like', 'race-reserve-%')->value('request_key');
    $priorOutbox = $db->table('adrv_outbox')->where('event_id', 'like', 'race-reserve-%')->count();
    $replay = $db->transaction(function () use ($db, $winner): bool {
        if ($db->table('adrv_reservations')->where('request_key', $winner)->exists()) {
            return true;
        }

        return false;
    });
    if (! $replay || $db->table('adrv_outbox')->where('event_id', 'like', 'race-reserve-%')->count() !== $priorOutbox) {
        throw new RuntimeException('RACE_RESERVE_REPLAY_ASSERTION');
    }
    fwrite(STDOUT, "PASS: concurrent reserve4 race has one reservation/outbox and replay has no extra effect\n");

    $db->table('adrv_stock_state')->where('item', 'issue-item')->update(['reserved' => 5, 'on_hand' => 5]);
    $db->table('adrv_movements')->where('request_key', 'like', 'race-issue-%')->delete();
    $db->table('adrv_outbox')->where('event_id', 'like', 'race-issue-%')->delete();
    $issueResults = runRace('issue', $root, $runPath);
    if (count(array_filter($issueResults, fn ($result) => $result === 'SUCCESS')) !== 1 || count(array_filter($issueResults, fn ($result) => $result === 'REJECTED')) !== 1 || (int) $db->table('adrv_stock_state')->where('item', 'issue-item')->value('reserved') !== 1 || (int) $db->table('adrv_stock_state')->where('item', 'issue-item')->value('on_hand') !== 1 || $db->table('adrv_movements')->where('request_key', 'like', 'race-issue-%')->count() !== 1 || $db->table('adrv_outbox')->where('event_id', 'like', 'race-issue-%')->count() !== 1) {
        throw new RuntimeException('RACE_ISSUE_ASSERTION');
    }
    fwrite(STDOUT, "PASS: concurrent issue4 race has one movement/outbox and remaining/on_hand each one\nRESULT: PASS assertions=2\n");
} catch (Throwable $failure) {
    fwrite(STDOUT, 'FAIL: '.preg_replace('/[^A-Za-z0-9_-]/', '_', $failure->getMessage())."\nRESULT: FAIL assertions=0\n");
    exit(1);
}
