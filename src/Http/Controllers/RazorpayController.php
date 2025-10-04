<?php

namespace Wontonee\Razorpay\Http\Controllers;

use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\RefundRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderCommentRepository;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;
use Wontonee\Razorpay\Models\Razorpay;
use Wontonee\Razorpay\Models\RazorpayPaymentAttempt;
use Carbon\Carbon;

class RazorpayController extends Controller
{
    /**
     * OrderRepository $orderRepository
     *
     * @var \Webkul\Sales\Repositories\OrderRepository
     */
    protected $orderRepository;
    /**
     * InvoiceRepository $invoiceRepository
     *
     * @var \Webkul\Sales\Repositories\InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * OrderCommentRepository $orderCommentRepository
     *
     * @var \Webkul\Sales\Repositories\OrderCommentRepository
     */
    protected $orderCommentRepository;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\OrderRepository  $orderRepository
     * @return void
     */
    public function __construct(OrderRepository $orderRepository, InvoiceRepository $invoiceRepository, OrderCommentRepository $orderCommentRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderCommentRepository = $orderCommentRepository;
    }

    /**
     * Redirects to the paytm server.
     *
     * @return \Illuminate\View\View
     */

    public function redirect(Request $request)
    {
        // Check license segment
        $cart = Cart::getCart();
        $billingAddress = $cart->billing_address;
        $shipping_rate = $cart->selected_shipping_rate ? $cart->selected_shipping_rate->price : 0; // shipping rate      
        $discount_amount = $cart->discount_amount; // discount amount// total amount   
        // Calculate total amount
        $total_amount =  ($cart->sub_total + $cart->tax_total + $shipping_rate) - $discount_amount;          // Logo priority: 1) Custom gateway logo, 2) Site logo, 3) Empty if none available
        $gatewayLogo = core()->getConfigData('sales.payment_methods.razorpay.gateway_logo');
        $siteLogo = core()->getCurrentChannel()->logo_url;

        if ($gatewayLogo) {
            $paymentLogo = asset('storage/' . $gatewayLogo);
        } elseif ($siteLogo) {
            $paymentLogo = $siteLogo;
        } else {
            $paymentLogo = '';
        }


        // Get configurable theme color with fallback to default
        $themeColor = core()->getConfigData('sales.payment_methods.razorpay.theme_color') ?: '#F37254';
        
        // Check if rounding occurred and store for order comment
        $originalAmount = $total_amount;
        $roundedAmount = round($total_amount);
        $roundingOccurred = $originalAmount != $roundedAmount;
        
        // Store rounding info in session for order creation
        if ($roundingOccurred) {
            session(['razorpay_rounding_info' => [
                'original_amount' => $originalAmount,
                'rounded_amount' => $roundedAmount,
                'difference' => abs($originalAmount - $roundedAmount)
            ]]);
        }

        $apiData = [
            'key' => core()->getConfigData('sales.payment_methods.razorpay.key_id'),
            'secret' => core()->getConfigData('sales.payment_methods.razorpay.secret'),
            'license' => core()->getConfigData('sales.payment_methods.razorpay.license_keyid'),
            'product_id' => 'RazorPayBagisto',
            'receipt' => "Receipt no. " . $cart->id,
            'amount' => $roundedAmount * 100,
            'currency' => 'INR',
            'name' => $billingAddress->name,
            'description' => 'RazorPay payment collection for the order - ' . $cart->id,
            'image' => $paymentLogo,
            'prefill' => [
                'name' => $billingAddress->name,
                'email' => $billingAddress->email,
                'contact' => $billingAddress->phone,
            ],
            'notes' => [
                'address' => $billingAddress->address,
                'merchant_order_id' => $cart->id,
            ],
            'theme' => [
                'color' => $themeColor
            ],
        ];

        $response = Http::post('https://myapps.wontonee.com/api/process-razorpay-data', $apiData);
        if ($response->successful()) {
            $responseData = $response->json();
            $razorpayOrderId = $responseData['razorpayOrderId'] ?? null;

            if ($razorpayOrderId) {
                $_SESSION['razorpay_order_id'] = $razorpayOrderId;

                $request->session()->put('razorpay_order_id', $razorpayOrderId);

                // Create payment attempt record for fallback tracking
                // Only store essential cart data to avoid large database entries
                $cartSnapshot = [
                    'id' => $cart->id,
                    'customer_id' => $cart->customer_id,
                    'customer_email' => $cart->customer_email,
                    'customer_first_name' => $cart->customer_first_name,
                    'customer_last_name' => $cart->customer_last_name,
                    'is_guest' => $cart->is_guest,
                    'channel_id' => $cart->channel_id,
                    'channel_name' => core()->getCurrentChannel()->name,
                    'items_count' => $cart->items_count,
                    'items_qty' => $cart->items_qty,
                    'cart_currency_code' => $cart->cart_currency_code,
                    'base_currency_code' => $cart->base_currency_code,
                    'sub_total' => $cart->sub_total,
                    'base_sub_total' => $cart->base_sub_total,
                    'tax_total' => $cart->tax_total,
                    'base_tax_total' => $cart->base_tax_total,
                    'discount_amount' => $cart->discount_amount,
                    'base_discount_amount' => $cart->base_discount_amount,
                    'grand_total' => $cart->grand_total,
                    'base_grand_total' => $cart->base_grand_total,
                    'billing_address' => $cart->billing_address ? $cart->billing_address->toArray() : null,
                    'shipping_address' => $cart->shipping_address ? $cart->shipping_address->toArray() : null,
                    'selected_shipping_rate' => $cart->selected_shipping_rate ? [
                        'price' => $cart->selected_shipping_rate->price,
                        'base_price' => $cart->selected_shipping_rate->base_price,
                        'method' => $cart->selected_shipping_rate->method,
                        'method_title' => $cart->selected_shipping_rate->method_title,
                    ] : null,
                    'payment' => $cart->payment ? $cart->payment->toArray() : ['method' => 'razorpay'],
                    'items' => $cart->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'sku' => $item->sku,
                            'name' => mb_substr($item->name, 0, 100), // Limit name length to prevent huge entries
                            'price' => $item->price,
                            'base_price' => $item->base_price,
                            'total' => $item->total,
                            'base_total' => $item->base_total,
                            'quantity' => $item->quantity,
                            'weight' => $item->weight,
                        ];
                    })->take(20)->toArray(), // Limit to 20 items to prevent huge data storage
                ];

                // Check cart data size before storing
                $cartDataSize = mb_strlen(json_encode($cartSnapshot), '8bit');
                if ($cartDataSize > 65000) { // MySQL TEXT field limit is ~65KB
                    // Remove items array if cart data is too large
                    unset($cartSnapshot['items']);
                    
                    // Add a note about truncated data
                    $cartSnapshot['_note'] = 'Items data truncated due to size constraints';
                }

                RazorpayPaymentAttempt::create([
                    'cart_id' => $cart->id,
                    'razorpay_order_id' => $razorpayOrderId,
                    'status' => 'initiated',
                    'amount' => $roundedAmount,
                    'cart_data' => $cartSnapshot,
                    'initiated_at' => Carbon::now(),
                ]);

                $displayAmount = $amount =  $apiData['amount'];
                $data = [
                    "key"               => core()->getConfigData('sales.payment_methods.razorpay.key_id'),
                    "amount"            =>  $apiData['amount'],
                    "name"              => $billingAddress->name,
                    "description"       => "RazorPay payment collection for the order - " . $cart->id,
                    "image"             => $paymentLogo,
                    "prefill"           => [
                        "name"              => $billingAddress->name,
                        "email"             => $billingAddress->email,
                        "contact"           => $billingAddress->phone,
                    ],
                    "notes"             => [
                        "address"           => $billingAddress->address,
                        "merchant_order_id" => $cart->id,
                    ],
                    "theme"             => [
                        "color"             => $themeColor
                    ],
                    "order_id"          => $razorpayOrderId,
                    "callback_url" => route('razorpay.callback')
                ];

                $json = json_encode($data);

                // Get checkout type from config (default: standard)
                $checkoutType = core()->getConfigData('sales.payment_methods.razorpay.checkout_type') ?? 'standard';
                
                // Select view based on checkout type
                $viewName = ($checkoutType === 'js') 
                    ? 'razorpay::razorpay-redirect' 
                    : 'razorpay::razorpay-redirect-standard';

                return view($viewName)->with(compact('data', 'json'));
            } else {
                session()->flash('error', $responseData['error']);
                return redirect()->route('shop.checkout.cart.index');
            }
        } else {
            $responseData = $response->json();
            session()->flash('error', $responseData['error']);
            return redirect()->route('shop.checkout.cart.index');
        }
    }
    /**
     * verify for automatic 
     */
    public function verify(Request $request)
    {
        // Handle both GET and POST requests for better browser compatibility
        $paymentId = $request->input('razorpay_payment_id');
      //  $signature = $request->input('razorpay_signature');
        
        // If no payment data, redirect to cart
        if (!$paymentId) {
            session()->flash('error', 'Payment information not received. Please try again.');
            return redirect()->route('shop.checkout.cart.index');
        }
        
        $api = new Api(core()->getConfigData('sales.payment_methods.razorpay.key_id'), core()->getConfigData('sales.payment_methods.razorpay.secret'));
        
        // Razorpay customer creation
        $razorpayCustomer = null;
        // Open the cart
        $cart = Cart::getCart();
        
        // If cart is null or empty, try to recover from payment attempt
        if (!$cart || !$cart->id) {
            try {
                $payment = $api->payment->fetch($paymentId);
                $paymentAttempt = RazorpayPaymentAttempt::where('razorpay_order_id', $payment->order_id)
                    ->where('status', 'initiated')
                    ->first();
                
                if ($paymentAttempt && $payment->status == 'captured') {
                    // Use fallback service to process this payment
                    $fallbackService = app(\Wontonee\Razorpay\Services\RazorpayFallbackService::class);
                    $result = $fallbackService->processWebhookPayment(
                        $paymentAttempt->cart_id,
                        $paymentId,
                        $paymentAttempt->cart_data
                    );
                    
                    if ($result['success']) {
                        session()->flash('order_id', $result['order_id']);
                        return redirect()->route('shop.checkout.onepage.success');
                    }
                }
            } catch (\Exception $e) {
                Log::error('Cart recovery failed in verify method', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage()
                ]);
            }
            
            session()->flash('error', 'Your session has expired. Please check your orders or contact support.');
            return redirect()->route('shop.checkout.cart.index');
        }
        
        try {
            
            $billingAddress = $cart->billing_address;
            $razorpayCustomer = $api->customer->create([
                'name' => $billingAddress->name,
                'email' => $billingAddress->email,
                'contact' => $billingAddress->phone,
                'notes' => [
                    'cart_id' => $cart->id,
                    'billing_address' => $billingAddress->address,
                    'created_via' => 'Bagisto Razorpay Integration'
                ]
            ]);
        } catch (\Exception $e) {
            // Continue without customer creation if it fails
            // Customer creation is optional and shouldn't break the payment flow
        }        
        $payment = $api->payment->fetch($request->input('razorpay_payment_id'));

        // Check if the payment is successful
        if ($payment->status == 'captured') {
            $data = (new OrderResource($cart))->jsonSerialize(); // new class v2.2
            $order = $this->orderRepository->create($data);
            $this->orderRepository->update(['status' => 'processing'], $order->id);

            // Mark payment attempt as completed
            $paymentAttempt = RazorpayPaymentAttempt::where('cart_id', $cart->id)
                ->where('status', 'initiated')
                ->first();
            
            if ($paymentAttempt) {
                $paymentAttempt->markAsCompleted($payment->id);
            }

            // Add order comment if rounding occurred
            $roundingInfo = session('razorpay_rounding_info');
            if ($roundingInfo) {
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
                // Clear the session data
                session()->forget('razorpay_rounding_info');
            }

            // Save payment data to razorpay table for refund functionality
            Razorpay::create([
                'order_id' => $order->id,
                'razorpay_customer_id' => $razorpayCustomer ? $razorpayCustomer->id : ($payment->customer_id ?? null),
                'razorpay_payment_id' => $payment->id,
                'payment_status' => 'paid',
                'payment_data' => $payment->toArray(),
                'amount' => $payment->amount / 100, // Convert from paise to rupees
                'refunded_amount' => 0,
            ]);

            // Create invoice for the order
            $this->invoiceRepository->create($this->prepareInvoiceData($order));
            Cart::deActivateCart();
            session()->flash('order_id', $order->id); // line instead of $order in v2.1
            // Order and prepare invoice
            return redirect()->route('shop.checkout.onepage.success');
        } else {
            session()->flash('error', 'Razorpay payment either cancelled or transaction failure.');
            return redirect()->route('shop.checkout.cart.index');
        }
    }
    /**
     * Prepares order's invoice data for creation.
     *
     * @return array
     */
    protected function prepareInvoiceData($order)
    {
        $invoiceData = ["order_id" => $order->id,];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }
    /**
     * Process Razorpay refund
     */
    public function refund(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $order = $this->orderRepository->find($request->order_id);
            $razorpayPayment = Razorpay::where('order_id', $order->id)->first();

            if (!$razorpayPayment) {
                session()->flash('error', 'No Razorpay payment found for this order.');
                return response()->json([
                    'success' => false,
                    'message' => 'No Razorpay payment found for this order.'
                ], 404);
            }

            if (!$razorpayPayment->canRefund()) {
                session()->flash('error', 'This payment cannot be refunded or is already fully refunded.');
                return response()->json([
                    'success' => false,
                    'message' => 'This payment cannot be refunded or is already fully refunded.'
                ], 400);
            }

            // Determine refund amount (empty or 0 means full refund)
            $refundAmount = ($request->amount && $request->amount > 0) ? floatval($request->amount) : $razorpayPayment->getRefundableAmount();

            // Check if rounding will occur for refund
            $originalRefundAmount = $refundAmount;
            $roundedRefundAmount = round($refundAmount);
            $refundRoundingOccurred = $originalRefundAmount != $roundedRefundAmount;

            if ($refundAmount > $razorpayPayment->getRefundableAmount()) {
                $errorMessage = 'Refund amount cannot exceed refundable amount of ₹' . number_format($razorpayPayment->getRefundableAmount(), 2);
                session()->flash('error', $errorMessage);
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 400);
            }

            // Initialize Razorpay API
            $api = new Api(
                core()->getConfigData('sales.payment_methods.razorpay.key_id'),
                core()->getConfigData('sales.payment_methods.razorpay.secret')
            );

            // Create refund
            $refund = $api->payment->fetch($razorpayPayment->razorpay_payment_id)->refund([
                'amount' => $roundedRefundAmount * 100, // Convert to paise
                'speed' => 'normal',
                'notes' => [
                    'reason' => 'Admin refund for order #' . $order->id,
                    'order_id' => $order->id,
                    'refund_date' => now()->toDateTimeString(),
                ]
            ]);

            // Add order comment if rounding occurred for refund
            if ($refundRoundingOccurred) {
                $this->orderCommentRepository->create([
                    'order_id' => $order->id,
                    'comment' => sprintf(
                        'Refund amount rounded for Razorpay processing: Original refund amount ₹%.2f was rounded to ₹%.0f (difference: ₹%.2f)',
                        $originalRefundAmount,
                        $roundedRefundAmount,
                        abs($originalRefundAmount - $roundedRefundAmount)
                    ),
                    'customer_notified' => false
                ]);
            }

            // Update payment record
            $newRefundedAmount = $razorpayPayment->refunded_amount + $roundedRefundAmount;
            $razorpayPayment->update([
                'refunded_amount' => $newRefundedAmount,
                'payment_status' => $newRefundedAmount >= $razorpayPayment->amount ? 'refund' : 'paid',
                'refund_data' => array_merge($razorpayPayment->refund_data ?? [], [$refund->toArray()])
            ]);

            // Create Bagisto refund record for consistency (with status fix)
            $this->createBagistoRefund($order, $refundAmount);

            // Update order status if fully refunded
            if ($razorpayPayment->isFullyRefunded()) {
                $this->orderRepository->update(['status' => 'closed'], $order->id);
            }

            // Flash success message
            session()->flash('success', 'Refund processed successfully! Refund ID: ' . $refund->id . '. Amount: ₹' . number_format($roundedRefundAmount, 2));

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully.',
                'refund_amount' => $roundedRefundAmount,
                'refund_id' => $refund->id,
                'total_refunded' => $newRefundedAmount,
                'remaining_amount' => $razorpayPayment->amount - $newRefundedAmount
            ]);
        } catch (\Exception $e) {
            session()->flash('error', 'Refund failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Create Bagisto refund record to maintain consistency with native refund system
     */
    protected function createBagistoRefund($order, $refundAmount)
    {
        try {
            // Store the original order status to restore it later
            $originalStatus = $order->status;

            // Initialize Bagisto RefundRepository
            $refundRepository = app(\Webkul\Sales\Repositories\RefundRepository::class);

            // Calculate proportional refund for each item based on refund amount
            $refundItems = [];
            $totalOrderAmount = $order->sub_total;

            foreach ($order->items as $item) {
                if ($item->qty_to_refund > 0) {
                    // Calculate proportional quantity to refund based on amount
                    $itemProportion = ($item->base_total / $totalOrderAmount);
                    $itemRefundAmount = $refundAmount * $itemProportion;
                    $qtyToRefund = min(
                        $item->qty_to_refund,
                        floor($itemRefundAmount / $item->base_price) ?: 1
                    );

                    if ($qtyToRefund > 0) {
                        $refundItems[$item->id] = $qtyToRefund;
                    }
                }
            }

            // If no items to refund, refund the first available item
            if (empty($refundItems)) {
                $firstRefundableItem = $order->items->where('qty_to_refund', '>', 0)->first();
                if ($firstRefundableItem) {
                    $refundItems[$firstRefundableItem->id] = 1;
                }
            }

            // Prepare refund data for Bagisto
            $refundData = [
                'order_id' => $order->id,
                'refund' => [
                    'items' => $refundItems,
                    'shipping' => 0,
                    'adjustment_refund' => $refundAmount - array_sum(array_map(function ($itemId, $qty) use ($order) {
                        $item = $order->items->find($itemId);
                        return $item ? $item->base_price * $qty : 0;
                    }, array_keys($refundItems), $refundItems)),
                    'adjustment_fee' => 0,
                ]
            ];

            // Create Bagisto refund record
            $bagistoRefund = $refundRepository->create($refundData);

            // Restore the original order status after refund creation
            $this->orderRepository->update(['status' => $originalStatus], $order->id);
        } catch (\Exception $e) {
            // Silently handle errors to prevent disrupting the main refund process
        }
    }

    /**
     * Get refund status for an order
     */
    public function getRefundStatus($orderId)
    {
        $razorpayPayment = Razorpay::where('order_id', $orderId)->first();

        if (!$razorpayPayment) {
            return response()->json([
                'success' => false,
                'message' => 'No Razorpay payment found for this order.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'payment_id' => $razorpayPayment->razorpay_payment_id,
            'total_amount' => $razorpayPayment->amount,
            'refunded_amount' => $razorpayPayment->refunded_amount,
            'refundable_amount' => $razorpayPayment->getRefundableAmount(),
            'can_refund' => $razorpayPayment->canRefund(),
            'is_fully_refunded' => $razorpayPayment->isFullyRefunded(),
            'payment_status' => $razorpayPayment->payment_status,
            'refund_history' => $razorpayPayment->refund_data ?? []
        ]);
    }

    /**
     * Handle Razorpay webhook events for real-time payment processing
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request)
    {
        // Check if webhooks are enabled
        if (!core()->getConfigData('sales.payment_methods.razorpay.webhook_enabled')) {
            return response()->json(['status' => 'webhooks_disabled'], 200);
        }

        try {
            // Get webhook secret for signature verification
            $webhookSecret = core()->getConfigData('sales.payment_methods.razorpay.webhook_secret');
            
            if (!$webhookSecret) {
                Log::warning('Razorpay webhook received but no webhook secret configured');
                return response()->json(['status' => 'no_secret'], 200);
            }

            // Verify webhook signature
            $webhookSignature = $request->header('X-Razorpay-Signature');
            $webhookBody = $request->getContent();
            
            if (!$this->verifyWebhookSignature($webhookBody, $webhookSignature, $webhookSecret)) {
                Log::error('Razorpay webhook signature verification failed', [
                    'signature' => $webhookSignature,
                    'ip' => $request->ip()
                ]);
                return response()->json(['status' => 'invalid_signature'], 401);
            }

            // Parse webhook payload
            $event = $request->all();
            
            Log::info('Razorpay webhook received', [
                'event' => $event['event'] ?? 'unknown',
                'payment_id' => $event['payload']['payment']['entity']['id'] ?? null
            ]);

            // Process different event types
            switch ($event['event']) {
                case 'payment.captured':
                    return $this->handlePaymentCaptured($event['payload']['payment']['entity']);
                    
                case 'payment.failed':
                    return $this->handlePaymentFailed($event['payload']['payment']['entity']);
                    
                case 'order.paid':
                    return $this->handleOrderPaid($event['payload']['order']['entity']);
                    
                default:
                    Log::info('Razorpay webhook event not handled', ['event' => $event['event']]);
                    return response()->json(['status' => 'event_not_handled'], 200);
            }

        } catch (\Exception $e) {
            Log::error('Razorpay webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    protected function verifyWebhookSignature($body, $signature, $secret)
    {
        $expectedSignature = hash_hmac('sha256', $body, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle payment.captured webhook event
     */
    protected function handlePaymentCaptured($payment)
    {
        try {
            // Find payment attempt by payment ID or order ID
            $paymentAttempt = RazorpayPaymentAttempt::where('razorpay_order_id', $payment['order_id'])
                ->where('status', 'initiated')
                ->first();

            if (!$paymentAttempt) {
                Log::warning('Payment attempt not found for webhook', [
                    'payment_id' => $payment['id'],
                    'order_id' => $payment['order_id']
                ]);
                return response()->json(['status' => 'payment_attempt_not_found'], 200);
            }

            // Use fallback service to process the payment
            $fallbackService = app(\Wontonee\Razorpay\Services\RazorpayFallbackService::class);
            
            $result = $fallbackService->processWebhookPayment(
                $paymentAttempt->cart_id,
                $payment['id'],
                $paymentAttempt->cart_data
            );

            if ($result['success']) {
                Log::info('Webhook payment processed successfully', [
                    'payment_id' => $payment['id'],
                    'order_id' => $result['order_id']
                ]);
                
                return response()->json(['status' => 'processed', 'order_id' => $result['order_id']], 200);
            } else {
                Log::error('Webhook payment processing failed', [
                    'payment_id' => $payment['id'],
                    'error' => $result['message']
                ]);
                
                return response()->json(['status' => 'processing_failed'], 200);
            }

        } catch (\Exception $e) {
            Log::error('Webhook payment.captured handling failed', [
                'payment_id' => $payment['id'],
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle payment.failed webhook event
     */
    protected function handlePaymentFailed($payment)
    {
        try {
            // Find and update payment attempt status
            $paymentAttempt = RazorpayPaymentAttempt::where('razorpay_order_id', $payment['order_id'])
                ->where('status', 'initiated')
                ->first();

            if ($paymentAttempt) {
                $paymentAttempt->markAsFailed($payment['id'], $payment['error_description'] ?? 'Payment failed');
                
                Log::info('Payment marked as failed via webhook', [
                    'payment_id' => $payment['id'],
                    'cart_id' => $paymentAttempt->cart_id
                ]);
            }

            return response()->json(['status' => 'failed_recorded'], 200);

        } catch (\Exception $e) {
            Log::error('Webhook payment.failed handling failed', [
                'payment_id' => $payment['id'],
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Handle order.paid webhook event
     */
    protected function handleOrderPaid($order)
    {
        // This event is fired when an order is fully paid
        // For now, we'll just log it as payment.captured handles the main processing
        Log::info('Order paid webhook received', [
            'order_id' => $order['id'],
            'amount' => $order['amount']
        ]);

        return response()->json(['status' => 'logged'], 200);
    }


}