<?php

namespace Wontonee\Razorpay\Console\Commands;

use Illuminate\Console\Command;
use Wontonee\Razorpay\Services\RazorpayFallbackService;

class ProcessRazorpayFallback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'razorpay:process-fallback 
                            {--dry-run : Run without making changes}
                            {--limit=50 : Maximum attempts to process}
                            {--show-details : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process Razorpay payment fallback for failed callbacks';

    protected $fallbackService;

    /**
     * Create a new command instance.
     */
    public function __construct(RazorpayFallbackService $fallbackService)
    {
        parent::__construct();
        $this->fallbackService = $fallbackService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        
        $this->info('🚀 Starting Razorpay Fallback Payment Processing...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        if ($this->option('show-details')) {
            $this->info('🔍 Detailed output enabled');
            $this->newLine();
        }

        try {
            // Process fallback payments
            $results = $this->fallbackService->processFallbackPayments();

            // Display results
            $this->displayResults($results, $startTime);

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Fatal error during fallback processing: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display processing results
     */
    protected function displayResults(array $results, float $startTime): void
    {
        $executionTime = round(microtime(true) - $startTime, 2);

        $this->info('📊 Processing Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $results['processed']],
                ['Successful', $results['successful']],
                ['Failed', $results['failed']],
                ['Execution Time', $executionTime . 's'],
            ]
        );

        if ($results['successful'] > 0) {
            $this->newLine();
            $this->info('✅ Successful Operations:');
            foreach ($results['notifications'] as $notification) {
                $this->line('  • ' . $notification);
            }
        }

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('❌ Errors Encountered:');
            foreach ($results['errors'] as $error) {
                $this->line('  • ' . $error);
            }
        }

        if ($results['processed'] === 0) {
            $this->info('✨ No eligible payment attempts found for processing');
        }

        $this->newLine();
        $this->info('🏁 Razorpay Fallback Processing Complete!');
    }


}