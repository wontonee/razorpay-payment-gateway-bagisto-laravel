<?php

namespace Wontonee\Razorpay\Services;

use Wontonee\Razorpay\Models\RazorpayPaymentAttempt;
use Wontonee\Razorpay\Models\Razorpay;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderCommentRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Checkout\Facades\Cart;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class RazorpayFallbackService
{
    protected $orderRepository;
    protected $invoiceRepository;
    protected $orderCommentRepository;

    public function __construct(
        OrderRepository $orderRepository,
        InvoiceRepository $invoiceRepository,
        OrderCommentRepository $orderCommentRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderCommentRepository = $orderCommentRepository;
    }

    /**
     * Process fallback payment verification for pending attempts
     */
    public function processFallbackPayments(): array
    {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
            'notifications' => []
        ];

        // Check if fallback processing is enabled
        if (!core()->getConfigData('sales.payment_methods.razorpay.fallback_enabled')) {
            $results['notifications'][] = 'Fallback processing is disabled in configuration';
            return $results;
        }

        $webhookEnabled = core()->getConfigData('sales.payment_methods.razorpay.webhook_enabled');
        if ($webhookEnabled) {
            $results['notifications'][] = 'Processing fallback (webhook backup mode)';
        } else {
            $results['notifications'][] = 'Processing fallback (primary mode - webhooks disabled)';
        }

        // Get eligible payment attempts
        $paymentAttempts = RazorpayPaymentAttempt::eligibleForFallback()
            ->orderBy('initiated_at', 'asc')
            ->limit(50) // Process maximum 50 at a time
            ->get();

        Log::info('Razorpay Fallback: Found ' . $paymentAttempts->count() . ' eligible payment attempts');

        foreach ($paymentAttempts as $attempt) {
            $results['processed']++;
            
            try {
                $result = $this->processPaymentAttempt($attempt);
                
                if ($result['success']) {
                    $results['successful']++;
                    $results['notifications'][] = $result['message'];
                } else {
                    $results['failed']++;
                    $results['errors'][] = $result['message'];
                }
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Cart ID {$attempt->cart_id}: {$e->getMessage()}";
                
                Log::error('Razorpay Fallback Error: ' . $e->getMessage(), [
                    'cart_id' => $attempt->cart_id,
                    'attempt_id' => $attempt->id,
                    'error_type' => get_class($e)
                ]);
                
                $attempt->incrementRetryCount();
            }
        }

        // Clean up expired attempts
        $this->cleanupExpiredAttempts();

        return $results;
    }

    /**
     * Process individual payment attempt
     */
    protected function processPaymentAttempt(RazorpayPaymentAttempt $attempt): array
    {
        // Increment retry count first
        $attempt->incrementRetryCount();

        // Initialize Razorpay API
        $api = new Api(
            core()->getConfigData('sales.payment_methods.razorpay.key_id'),
            core()->getConfigData('sales.payment_methods.razorpay.secret')
        );

        try {
            // Fetch payments for this order from Razorpay
            $razorpayOrder = $api->order->fetch($attempt->razorpay_order_id);
            $payments = $razorpayOrder->payments();

            Log::info("Razorpay Fallback: Checking order {$attempt->razorpay_order_id}", [
                'cart_id' => $attempt->cart_id,
                'payments_count' => $payments->count()
            ]);

            // Check if there are any payments at all
            if ($payments->count() == 0) {
                return [
                    'success' => false,
                    'message' => "Cart ID {$attempt->cart_id}: No payments found for this order in Razorpay"
                ];
            }

            // Find successful payment
            $successfulPayment = null;
            $hasFailedPayments = false;
            
            foreach ($payments->items as $payment) {
                if ($payment->status === 'captured') {
                    $successfulPayment = $payment;
                    break;
                } elseif (in_array($payment->status, ['failed', 'cancelled'])) {
                    $hasFailedPayments = true;
                }
            }

            // If no successful payment but has failed payments, mark as failed
            if (!$successfulPayment && $hasFailedPayments) {
                $attempt->markAsFailed();
                return [
                    'success' => false,
                    'message' => "Cart ID {$attempt->cart_id}: Payment failed in Razorpay"
                ];
            }

            // If no successful payment and no failed payments, it's still pending
            if (!$successfulPayment) {
                return [
                    'success' => false,
                    'message' => "Cart ID {$attempt->cart_id}: Payment still pending in Razorpay"
                ];
            }

            // Check if this payment is already processed in Bagisto
            $existingPayment = Razorpay::where('razorpay_payment_id', $successfulPayment->id)->first();
            if ($existingPayment) {
                $attempt->markAsCompleted($successfulPayment->id);
                return [
                    'success' => true,
                    'message' => "Cart ID {$attempt->cart_id}: Payment already processed (Order ID: {$existingPayment->order_id})"
                ];
            }

            // Check if there's already an order for this cart (regular processing might have succeeded)
            $existingOrder = \Webkul\Sales\Models\Order::whereHas('items', function($query) use ($attempt) {
                // This is a simplified check - you might need to adjust based on your order structure
            })->first();

            // Process the successful payment only if no order exists
            return $this->processSuccessfulPayment($attempt, $successfulPayment, $api);

        } catch (\Exception $e) {
            // Check if it's a "not found" error (order doesn't exist in Razorpay)
            if (strpos($e->getMessage(), 'does not exist') !== false || strpos($e->getMessage(), 'not a valid id') !== false) {
                $attempt->markAsFailed();
                return [
                    'success' => false,
                    'message' => "Cart ID {$attempt->cart_id}: Order not found in Razorpay (likely test data)"
                ];
            }

            Log::error("Razorpay Fallback API Error", [
                'cart_id' => $attempt->cart_id,
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ]);
            
            return [
                'success' => false,
                'message' => "Cart ID {$attempt->cart_id}: API Error - {$e->getMessage()}"
            ];
        }
    }

    /**
     * Process successful payment and create order
     */
    protected function processSuccessfulPayment(
        RazorpayPaymentAttempt $attempt,
        $payment,
        Api $api
    ): array {
        return DB::transaction(function () use ($attempt, $payment, $api) {
            // Restore cart from stored data (avoid logging large data structures) 
            $cartData = $attempt->cart_data;
            
            Log::info("Razorpay Fallback: Processing successful payment", [
                'cart_id' => $attempt->cart_id,
                'razorpay_order_id' => $attempt->razorpay_order_id,
                'payment_id' => $payment->id,
                'amount' => $attempt->amount
            ]);
            
            // Create Razorpay customer (similar to original logic)
            $razorpayCustomer = null;
            try {
                $billingAddress = $cartData['billing_address'] ?? null;
                if ($billingAddress) {
                    $razorpayCustomer = $api->customer->create([
                        'name' => $billingAddress['name'] ?? 'Customer',
                        'email' => $billingAddress['email'] ?? 'customer@example.com',
                        'contact' => $billingAddress['phone'] ?? '9999999999',
                        'notes' => [
                            'cart_id' => $attempt->cart_id,
                            'billing_address' => $billingAddress['address'] ?? '',
                            'created_via' => 'Bagisto Razorpay Fallback'
                        ]
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning("Could not create Razorpay customer for fallback: " . $e->getMessage());
            }

            // Try to create order using original cart if available, otherwise use stored data
            $order = $this->createOrderWithFallback($attempt->cart_id, $cartData);
            
            // Update order status
            $this->orderRepository->update(['status' => 'processing'], $order->id);

            // Add order comments
            $this->orderCommentRepository->create([
                'order_id' => $order->id,
                'comment' => sprintf(
                    'Order created via Razorpay fallback process. Original payment completed at %s but callback was not received.',
                    Carbon::createFromTimestamp($payment->created_at)->format('Y-m-d H:i:s')
                ),
                'customer_notified' => false
            ]);

            // Check for rounding info and add comment if needed
            if (isset($cartData['razorpay_rounding_info'])) {
                $roundingInfo = $cartData['razorpay_rounding_info'];
                $this->orderCommentRepository->create([
                    'order_id' => $order->id,
                    'comment' => sprintf(
                        'Amount rounded for Razorpay payment: Original amount ₹%.2f was rounded to ₹%.0f (difference: ₹%.2f)',
                        $roundingInfo['original_amount'],
                        $roundingInfo['rounded_amount'],
                        $roundingInfo['difference']
                    ),
                    'customer_notified' => false
                ]);
            }

            // Save payment data to razorpay table
            Razorpay::create([
                'order_id' => $order->id,
                'razorpay_customer_id' => $razorpayCustomer ? $razorpayCustomer->id : ($payment->customer_id ?? null),
                'razorpay_payment_id' => $payment->id,
                'payment_status' => 'paid',
                'payment_data' => $payment->toArray(),
                'amount' => $payment->amount / 100,
                'refunded_amount' => 0,
            ]);

            // Create invoice if possible
            if ($order->canInvoice()) {
                $invoiceData = $this->prepareInvoiceData($order);
                $this->invoiceRepository->create($invoiceData);
            }

            // Mark payment attempt as completed
            $attempt->markAsCompleted($payment->id);

            // Send notification email if configured
            $this->sendNotificationEmail($order, $attempt);

            Log::info("Razorpay Fallback: Successfully created order {$order->id} for cart {$attempt->cart_id}");

            return [
                'success' => true,
                'message' => "Cart ID {$attempt->cart_id}: Successfully created Order #{$order->increment_id} (ID: {$order->id})"
            ];
        });
    }

    /**
     * Public method to process webhook payment and create order
     * 
     * @param int $cartId
     * @param string $paymentId
     * @param array $cartData
     * @return array
     */
    public function processWebhookPayment(int $cartId, string $paymentId, array $cartData): array
    {
        try {
            DB::beginTransaction();

            // Create order using fallback method
            $order = $this->createOrderWithFallback($cartId, $cartData);
            
            // Update order status
            $this->orderRepository->update(['status' => 'processing'], $order->id);

            // Create Razorpay record
            $api = new Api(core()->getConfigData('sales.payment_methods.razorpay.key_id'), core()->getConfigData('sales.payment_methods.razorpay.secret'));
            $payment = $api->payment->fetch($paymentId);

            Razorpay::create([
                'order_id' => $order->id,
                'razorpay_payment_id' => $payment->id,
                'payment_status' => 'paid',
                'payment_data' => $payment->toArray(),
                'amount' => $payment->amount / 100,
                'refunded_amount' => 0,
            ]);

            // Create invoice
            $this->invoiceRepository->create($this->prepareInvoiceData($order));

            // Mark payment attempt as completed
            $paymentAttempt = RazorpayPaymentAttempt::where('cart_id', $cartId)
                ->where('status', 'initiated')
                ->first();

            if ($paymentAttempt) {
                $paymentAttempt->markAsCompleted($paymentId);
            }

            DB::commit();

            return [
                'success' => true,
                'order_id' => $order->id,
                'message' => 'Order created successfully via webhook'
            ];

        } catch (\Exception $e) {
            DB::rollback();
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Create order with fallback - first try original cart, then stored data
     */
    protected function createOrderWithFallback(int $cartId, array $cartData): object
    {
        try {
            // First try to find and use the original cart
            $cartRepo = app(\Webkul\Checkout\Repositories\CartRepository::class);
            $originalCart = $cartRepo->find($cartId);
            
            if ($originalCart && $originalCart->items()->count() > 0) {
                // Use the original cart with OrderResource (same as regular flow)
                Log::info("Razorpay Fallback: Using original cart for order creation", ['cart_id' => $cartId]);
                
                $data = (new \Webkul\Sales\Transformers\OrderResource($originalCart))->jsonSerialize();
                return $this->orderRepository->create($data);
            }
        } catch (\Exception $e) {
            Log::warning("Razorpay Fallback: Failed to use original cart", [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);
        }
        
        // If original cart not available, create from stored data
        Log::info("Razorpay Fallback: Creating order from stored cart data", ['cart_id' => $cartId]);
        return $this->createOrderFromCartData($cartData);
    }

    /**
     * Create order from stored cart data
     */
    protected function createOrderFromCartData(array $cartData): object
    {
        // We need to reconstruct the cart or create order data manually
        // Since OrderResource expects a Cart model, let's create order data directly
        
        $billingAddress = $cartData['billing_address'] ?? [];
        $shippingAddress = $cartData['shipping_address'] ?? $billingAddress;
        
        $orderData = [
            'is_guest' => $cartData['is_guest'] ?? 1,
            'customer_id' => $cartData['customer_id'] ?? null,
            'customer_email' => $cartData['customer_email'] ?? $billingAddress['email'] ?? 'guest@example.com',
            'customer_first_name' => $cartData['customer_first_name'] ?? $billingAddress['first_name'] ?? 'Guest',
            'customer_last_name' => $cartData['customer_last_name'] ?? $billingAddress['last_name'] ?? 'Customer',
            'channel_id' => $cartData['channel_id'] ?? 1,
            'channel_name' => $cartData['channel_name'] ?? core()->getCurrentChannel()->name,
            'status' => 'pending',
            'currency_code' => $cartData['cart_currency_code'] ?? core()->getCurrentCurrencyCode(),
            'base_currency_code' => $cartData['base_currency_code'] ?? core()->getBaseCurrencyCode(),
            'grand_total' => $cartData['grand_total'] ?? 0,
            'base_grand_total' => $cartData['base_grand_total'] ?? 0,
            'sub_total' => $cartData['sub_total'] ?? 0,
            'base_sub_total' => $cartData['base_sub_total'] ?? 0,
            'tax_amount' => $cartData['tax_total'] ?? 0,
            'base_tax_amount' => $cartData['base_tax_total'] ?? 0,
            'shipping_amount' => $cartData['selected_shipping_rate']['price'] ?? 0,
            'base_shipping_amount' => $cartData['selected_shipping_rate']['base_price'] ?? 0,
            'discount_amount' => $cartData['discount_amount'] ?? 0,
            'base_discount_amount' => $cartData['base_discount_amount'] ?? 0,
            'items_count' => $cartData['items_count'] ?? 0,
            'items_qty' => $cartData['items_qty'] ?? 0,
        ];

        // Add billing address
        if (!empty($billingAddress)) {
            $orderData['billing_address'] = [
                'first_name' => $billingAddress['first_name'] ?? 'Guest',
                'last_name' => $billingAddress['last_name'] ?? 'Customer',
                'email' => $billingAddress['email'] ?? 'guest@example.com',
                'phone' => $billingAddress['phone'] ?? '9999999999',
                'address' => $billingAddress['address'] ?? 'N/A',
                'city' => $billingAddress['city'] ?? 'N/A',
                'state' => $billingAddress['state'] ?? 'N/A',
                'country' => $billingAddress['country'] ?? 'IN',
                'postcode' => $billingAddress['postcode'] ?? '000000',
            ];
        }

        // Add shipping address
        if (!empty($shippingAddress)) {
            $orderData['shipping_address'] = [
                'first_name' => $shippingAddress['first_name'] ?? 'Guest',
                'last_name' => $shippingAddress['last_name'] ?? 'Customer',
                'email' => $shippingAddress['email'] ?? 'guest@example.com',
                'phone' => $shippingAddress['phone'] ?? '9999999999',
                'address' => $shippingAddress['address'] ?? 'N/A',
                'city' => $shippingAddress['city'] ?? 'N/A',
                'state' => $shippingAddress['state'] ?? 'N/A',
                'country' => $shippingAddress['country'] ?? 'IN',
                'postcode' => $shippingAddress['postcode'] ?? '000000',
            ];
        }

        // Add payment method
        $orderData['payment'] = [
            'method' => 'razorpay',
            'method_title' => 'Razorpay',
        ];

        // Add items if available (may be missing due to size constraints)
        if (isset($cartData['items']) && is_array($cartData['items']) && !empty($cartData['items'])) {
            $orderData['items'] = [];
            foreach ($cartData['items'] as $item) {
                // Validate item has required fields
                if (!isset($item['product_id']) || empty($item['product_id'])) {
                    Log::warning("Razorpay Fallback: Skipping item without product_id", ['item' => $item]);
                    continue;
                }

                $orderData['items'][] = [
                    'product_id' => (int) $item['product_id'],
                    'sku' => $item['sku'] ?? 'FALLBACK-SKU',
                    'name' => $item['name'] ?? 'Product',
                    'price' => (float) ($item['price'] ?? 0),
                    'base_price' => (float) ($item['base_price'] ?? $item['price'] ?? 0),
                    'total' => (float) ($item['total'] ?? $item['price'] ?? 0),
                    'base_total' => (float) ($item['base_total'] ?? $item['total'] ?? $item['price'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'weight' => (float) ($item['weight'] ?? 0),
                    'type' => $item['type'] ?? 'simple',
                    'product_type' => $item['product_type'] ?? 'simple',
                ];
            }
            
            // If no valid items after filtering, treat as empty
            if (empty($orderData['items'])) {
                Log::warning("Razorpay Fallback: No valid items found after filtering", ['cart_id' => $cartData['id'] ?? 'unknown']);
            }
        }
        
        // If no items available or all were invalid, create a fallback item
        if (empty($orderData['items'])) {
            Log::warning("Razorpay Fallback: Creating fallback item for cart", ['cart_id' => $cartData['id'] ?? 'unknown']);
            
            // Use a reasonable fallback amount
            $fallbackAmount = (float) ($cartData['sub_total'] ?? $cartData['grand_total'] ?? 1);
            
            $orderData['items'] = [[
                'product_id' => 1, // Ensure this product exists in your database
                'sku' => 'PAYMENT-RECOVERY',
                'name' => 'Payment Recovery - Order Item',
                'price' => $fallbackAmount,
                'base_price' => $fallbackAmount,
                'total' => $fallbackAmount,
                'base_total' => $fallbackAmount,
                'quantity' => 1,
                'weight' => 0,
                'type' => 'simple',
                'product_type' => 'simple',
            ]];
        }

        try {
            // Log order data structure for debugging (but only keys to avoid large logs)
            Log::info("Razorpay Fallback: Creating order with data structure", [
                'cart_id' => $cartData['id'] ?? 'unknown',
                'data_keys' => array_keys($orderData),
                'items_count' => count($orderData['items'] ?? []),
                'has_billing_address' => isset($orderData['billing_address']),
                'has_shipping_address' => isset($orderData['shipping_address']),
                'customer_id' => $orderData['customer_id'] ?? null,
                'grand_total' => $orderData['grand_total'] ?? 0
            ]);
            
            return $this->orderRepository->create($orderData);
            
        } catch (\Exception $e) {
            Log::error("Razorpay Fallback: Order creation failed", [
                'cart_id' => $cartData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Prepare invoice data (copied from controller)
     */
    protected function prepareInvoiceData($order): array
    {
        $invoiceData = ["order_id" => $order->id];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }

    /**
     * Send notification email to admin
     */
    /**
     * Get safe cart data summary for logging (without large arrays)
     */
    protected function getCartDataSummary(array $cartData): array
    {
        return [
            'cart_id' => $cartData['id'] ?? 'unknown',
            'customer_email' => $cartData['customer_email'] ?? 'unknown',
            'items_count' => $cartData['items_count'] ?? 0,
            'grand_total' => $cartData['grand_total'] ?? 0,
            'currency' => $cartData['cart_currency_code'] ?? 'INR'
        ];
    }

    protected function sendNotificationEmail($order, RazorpayPaymentAttempt $attempt): void
    {
        try {
            $adminEmail = core()->getConfigData('emails.general.notifications.emails.general.admin');
            
            if ($adminEmail) {
                // You can implement email notification here
                // Mail::to($adminEmail)->send(new FallbackOrderCreatedNotification($order, $attempt));
                Log::info("Admin notification sent for fallback order {$order->id}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send admin notification: " . $e->getMessage());
        }
    }

    /**
     * Clean up expired payment attempts
     */
    protected function cleanupExpiredAttempts(): void
    {
        $expiredCount = RazorpayPaymentAttempt::where('status', 'initiated')
            ->where('initiated_at', '<', Carbon::now()->subHours(24))
            ->update(['status' => 'expired']);

        if ($expiredCount > 0) {
            Log::info("Razorpay Fallback: Marked {$expiredCount} payment attempts as expired");
        }
    }
}