<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Demo\DemoSeeder;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed {--size=medium : Dataset size (small|medium|large)} {--force : Allow seeding outside local/testing}';

    protected $description = 'Seed a realistic Egyptian demo dataset for the CRM, Employees, Tasks and Access modules';

    public function handle(): int
    {
        $size = $this->option('size');
        if (! is_string($size)) {
            $this->error('Choose small, medium, or large.');

            return self::FAILURE;
        }
        $force = (bool) $this->option('force');
        $seeder = new DemoSeeder((int) config('demo.seed'));

        try {
            $seeder->assertEnvironmentAllowed($force);
            $this->prepareCredentialsPath();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $result = $seeder->seed($size, $force);
        } catch (Throwable $exception) {
            $this->error('Seeding failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        try {
            $path = $this->writeCredentials($result['credentials']);
        } catch (Throwable $exception) {
            $this->error('Demo rows were seeded, but credentials could not be saved; repair the private path and rerun demo:seed.');

            return self::FAILURE;
        }

        $this->info("Demo dataset [{$result['size']}] seeded under company {$result['company_id']}.");
        $this->table(['table', 'rows'], array_map(
            static fn (string $table, int $rows): array => [$table, $rows],
            array_keys($result['counts']),
            array_values($result['counts']),
        ));
        $this->line('Demo login credentials: '.$path.' (git-ignored).');

        return self::SUCCESS;
    }

    private function prepareCredentialsPath(): void
    {
        $directory = storage_path('app/private');
        if (! is_dir($directory) && ! mkdir($directory, 0o700, true)) {
            throw new RuntimeException('Cannot create the private credentials directory.');
        }
        $path = $directory.'/demo-credentials.json';
        if (is_link($path) || is_dir($path) || ! is_writable($directory) || (is_file($path) && ! is_writable($path))) {
            throw new RuntimeException('The private credentials path is not writable.');
        }
    }

    /**
     * @param  array<string, array{email: string, password: string, role: string}>  $credentials
     */
    private function writeCredentials(array $credentials): string
    {
        $path = storage_path('app/private/demo-credentials.json');
        $json = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ((! is_file($path) && ! touch($path)) || ! chmod($path, 0o600)) {
            throw new RuntimeException('Could not protect private credentials before writing.');
        }
        if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('Could not save private credentials.');
        }

        return $path;
    }
}
