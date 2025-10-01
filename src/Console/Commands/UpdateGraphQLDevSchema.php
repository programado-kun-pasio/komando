<?php declare(strict_types=1);

namespace Programado\Komando\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class UpdateGraphQLDevSchema extends Command
{
    protected $signature = 'komando:graphql:update-dev-schema';

    protected $description = 'Updates the lighthouse GraphQL development schema and generates IDE helper files.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->updateGraphQLSchema();
        $this->generateIdeHelperFiles();
    }

    /**
     * Update the GraphQL development schema by printing it with the Lighthouse package.
     */
    private function updateGraphQLSchema(): void
    {
        Artisan::call('lighthouse:print-schema', ['--write' => true], $this->getOutput());
        $this->info('GraphQL schema updated successfully.');
    }

    /**
     * Generate IDE helper files for better development experience.
     */
    private function generateIdeHelperFiles(): void
    {
        Artisan::call('lighthouse:ide-helper', [], $this->getOutput());

        $this->info('IDE helper files generated successfully.');
    }
}
