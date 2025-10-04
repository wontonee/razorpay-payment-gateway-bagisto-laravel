<?php

namespace Wontonee\Razorpay\Console\Commands;

use Illuminate\Console\Command;
use Wontonee\Razorpay\Models\RazorpayPaymentAttempt;
use Webkul\Checkout\Models\Cart;
use Carbon\Carbon;

class CreateTestPaymentAttempt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'razorpay:create-test-attempt 
                            {--cart-id= : Specific cart ID to use}
                            {--amount=100 : Payment amount}
                            {--minutes-ago=20 : How many minutes ago the payment was initiated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test payment attempt for fallback system testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Creating Test Payment Attempt...');
        $this->newLine();

        // Get or find cart
        $cartId = $this->option('cart-id');
        
        if ($cartId) {
            $cart = Cart::find($cartId);
            if (!$cart) {
                $this->error("❌ Cart with ID {$cartId} not found!");
                return 1;
            }
        } else {
            $cart = Cart::latest()->first();
            if (!$cart) {
                $this->error('❌ No carts found in the system!');
                $this->line('Please create a cart first by going through checkout process.');
                return 1;
            }
        }

        $this->info("📦 Using Cart ID: {$cart->id}");

        // Create test payment attempt
        $attempt = RazorpayPaymentAttempt::create([
            'cart_id' => $cart->id,
            'razorpay_order_id' => 'order_test_' . uniqid(),
            'status' => 'initiated',
            'amount' => floatval($this->option('amount')),
            'cart_data' => $cart->toArray(),
            'initiated_at' => Carbon::now()->subMinutes(intval($this->option('minutes-ago'))),
        ]);

        $this->info("✅ Created Test Payment Attempt");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $attempt->id],
                ['Cart ID', $attempt->cart_id],
                ['Razorpay Order ID', $attempt->razorpay_order_id],
                ['Amount', '₹' . number_format($attempt->amount, 2)],
                ['Status', $attempt->status],
                ['Initiated At', $attempt->initiated_at->format('Y-m-d H:i:s')],
            ]
        );

        $this->newLine();
        $this->info('📋 Next Steps:');
        $this->line('1. Run: php artisan razorpay:process-fallback --verbose');
        $this->line('2. Check if order was created successfully');
        $this->line('3. Verify payment attempt status changed to "completed"');

        $this->newLine();
        $this->info('🔍 Quick Check Commands:');
        $this->line("- Check attempt: php artisan tinker --execute=\"echo Wontonee\\Razorpay\\Models\\RazorpayPaymentAttempt::find({$attempt->id})->status\"");
        $this->line("- Check orders: php artisan tinker --execute=\"echo Webkul\\Sales\\Models\\Order::count()\"");

        return 0;
    }
}