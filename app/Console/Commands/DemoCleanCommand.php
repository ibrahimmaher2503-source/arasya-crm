<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Demo\DemoSeeder;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class DemoCleanCommand extends Command
{
    protected $signature = 'demo:clean {--force : Allow cleanup outside local/testing; production is always forbidden}';

    protected $description = 'Remove the synthetic Egyptian demo dataset (owned rows only)';

    public function handle(): int
    {
        $seeder = new DemoSeeder((int) config('demo.seed'));

        try {
            $result = $seeder->purge((bool) $this->option('force'));
            $credentials = storage_path('app/private/demo-credentials.json');
            if (is_file($credentials) && ! unlink($credentials)) {
                throw new RuntimeException('Demo rows were removed, but the private credentials file could not be removed.');
            }
        } catch (Throwable $exception) {
            $this->error('Purge failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Removed '.$result['removed'].' demo rows.');

        return self::SUCCESS;
    }
}
