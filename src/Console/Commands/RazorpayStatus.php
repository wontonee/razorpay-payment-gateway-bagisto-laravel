<?php

namespace Wontonee\Razorpay\Console\Commands;

use Illuminate\Console\Command;
use Wontonee\Razorpay\Models\RazorpayPaymentAttempt;
use Wontonee\Razorpay\Models\Razorpay;
use Webkul\Sales\Models\Order;
use Carbon\Carbon;

class RazorpayStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'razorpay:status 
                            {--show-detailed : Show detailed information}
                            {--hours=24 : Hours to look back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Razorpay payment system status and statistics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = intval($this->option('hours'));
        $since = Carbon::now()->subHours($hours);

        $this->info("📊 Razorpay Payment System Status (Last {$hours} hours)");
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        // Payment Attempts Statistics
        $this->showPaymentAttemptStats($since);
        $this->newLine();

        // Payment Success Statistics
        $this->showPaymentSuccessStats($since);
        $this->newLine();

        // System Health Check
        $this->showSystemHealth();

        if ($this->option('show-detailed')) {
            $this->newLine();
            $this->showDetailedInfo($since);
        }

        return 0;
    }

    protected function showPaymentAttemptStats(Carbon $since): void
    {
        $total = RazorpayPaymentAttempt::where('created_at', '>=', $since)->count();
        $initiated = RazorpayPaymentAttempt::where('created_at', '>=', $since)->where('status', 'initiated')->count();
        $completed = RazorpayPaymentAttempt::where('created_at', '>=', $since)->where('status', 'completed')->count();
        $failed = RazorpayPaymentAttempt::where('created_at', '>=', $since)->where('status', 'failed')->count();
        $expired = RazorpayPaymentAttempt::where('created_at', '>=', $since)->where('status', 'expired')->count();

        $this->info('💳 Payment Attempts:');
        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total', $total, '100%'],
                ['Initiated (Pending)', $initiated, $total > 0 ? round(($initiated / $total) * 100, 1) . '%' : '0%'],
                ['Completed', $completed, $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%'],
                ['Failed', $failed, $total > 0 ? round(($failed / $total) * 100, 1) . '%' : '0%'],
                ['Expired', $expired, $total > 0 ? round(($expired / $total) * 100, 1) . '%' : '0%'],
            ]
        );
    }

    protected function showPaymentSuccessStats(Carbon $since): void
    {
        $totalOrders = Order::where('created_at', '>=', $since)->count();
        $razorpayPayments = Razorpay::where('created_at', '>=', $since)->count();
        $processingOrders = Order::where('created_at', '>=', $since)->where('status', 'processing')->count();

        $this->info('✅ Payment Success:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Orders Created', $totalOrders],
                ['Razorpay Payments', $razorpayPayments],
                ['Processing Orders', $processingOrders],
            ]
        );
    }

    protected function showSystemHealth(): void
    {
        $eligibleForFallback = RazorpayPaymentAttempt::eligibleForFallback()->count();
        $oldInitiated = RazorpayPaymentAttempt::where('status', 'initiated')
            ->where('initiated_at', '<', Carbon::now()->subHours(24))
            ->count();
        $highRetryCount = RazorpayPaymentAttempt::where('retry_count', '>', 50)->count();

        $this->info('🏥 System Health:');
        $this->table(
            ['Check', 'Status', 'Value'],
            [
                [
                    'Eligible for Fallback',
                    $eligibleForFallback > 0 ? '⚠️  Needs Attention' : '✅ Good',
                    $eligibleForFallback
                ],
                [
                    'Old Initiated Payments',
                    $oldInitiated > 0 ? '⚠️  Should be Expired' : '✅ Good',
                    $oldInitiated
                ],
                [
                    'High Retry Count',
                    $highRetryCount > 0 ? '⚠️  Investigate' : '✅ Good',
                    $highRetryCount
                ],
            ]
        );

        if ($eligibleForFallback > 0) {
            $this->newLine();
            $this->warn("⚠️  There are {$eligibleForFallback} payment attempts waiting for fallback processing.");
            $this->line('   Run: php artisan razorpay:process-fallback');
        }
    }

    protected function showDetailedInfo(Carbon $since): void
    {
        $this->info('🔍 Detailed Information:');
        
        // Recent failed attempts
        $recentFailed = RazorpayPaymentAttempt::where('status', 'failed')
            ->where('updated_at', '>=', $since)
            ->latest()
            ->limit(5)
            ->get();

        if ($recentFailed->count() > 0) {
            $this->line('Recent Failed Attempts:');
            $this->table(
                ['ID', 'Cart ID', 'Amount', 'Failed At', 'Retries'],
                $recentFailed->map(function ($attempt) {
                    return [
                        $attempt->id,
                        $attempt->cart_id,
                        '₹' . number_format($attempt->amount, 2),
                        $attempt->updated_at->format('Y-m-d H:i:s'),
                        $attempt->retry_count
                    ];
                })->toArray()
            );
        }

        // Recent successful recoveries
        $recentRecovered = RazorpayPaymentAttempt::where('status', 'completed')
            ->where('updated_at', '>=', $since)
            ->where('retry_count', '>', 0) // Only fallback recoveries
            ->latest()
            ->limit(5)
            ->get();

        if ($recentRecovered->count() > 0) {
            $this->newLine();
            $this->line('Recent Fallback Recoveries:');
            $this->table(
                ['ID', 'Cart ID', 'Amount', 'Recovered At', 'After Retries'],
                $recentRecovered->map(function ($attempt) {
                    return [
                        $attempt->id,
                        $attempt->cart_id,
                        '₹' . number_format($attempt->amount, 2),
                        $attempt->updated_at->format('Y-m-d H:i:s'),
                        $attempt->retry_count
                    ];
                })->toArray()
            );
        }

        // Recommendations
        $this->newLine();
        $this->info('💡 Recommendations:');
        
        if ($eligibleForFallback = RazorpayPaymentAttempt::eligibleForFallback()->count()) {
            $this->line("• Run fallback processing: php artisan razorpay:process-fallback");
        }
        
        if ($oldInitiated = RazorpayPaymentAttempt::where('status', 'initiated')->where('initiated_at', '<', Carbon::now()->subHours(24))->count()) {
            $this->line("• Clean up old attempts: Update {$oldInitiated} old attempts to 'expired' status");
        }
        
        $this->line('• Monitor logs: tail -f storage/logs/laravel.log | grep "Razorpay Fallback"');
        $this->line('• Set up cron job for automatic processing every 15 minutes');
    }
}