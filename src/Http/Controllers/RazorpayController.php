<?php

namespace Wontonee\Razorpay\Http\Controllers;

use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\RefundRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Sales\Repositories\InvoiceRepository;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Controller;
use Wontonee\Razorpay\Models\Razorpay;

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
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\OrderRepository  $orderRepository
     * @return void
     */
    public function __construct(OrderRepository $orderRepository,  InvoiceRepository $invoiceRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
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

        $apiData = [
            'key' => core()->getConfigData('sales.payment_methods.razorpay.key_id'),
            'secret' => core()->getConfigData('sales.payment_methods.razorpay.secret'),
            'license' => core()->getConfigData('sales.payment_methods.razorpay.license_keyid'),
            'product_id' => 'RazorPayBagisto',
            'receipt' => "Receipt no. " . $cart->id,
            'amount' => $total_amount * 100,
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

                return view('razorpay::razorpay-redirect')->with(compact('data', 'json'));
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
        $api = new Api(core()->getConfigData('sales.payment_methods.razorpay.key_id'), core()->getConfigData('sales.payment_methods.razorpay.secret'));
        
        // Razorpay customer creation
        $razorpayCustomer = null;
        // Open the cart
        $cart = Cart::getCart();
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

            //Payorder ID of RazorPay
            if ($order->canInvoice()) {
                $this->invoiceRepository->create($this->prepareInvoiceData($order));
            }
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
                'amount' => $refundAmount * 100, // Convert to paise
                'speed' => 'normal',
                'notes' => [
                    'reason' => 'Admin refund for order #' . $order->id,
                    'order_id' => $order->id,
                    'refund_date' => now()->toDateTimeString(),
                ]
            ]);

            // Update payment record
            $newRefundedAmount = $razorpayPayment->refunded_amount + $refundAmount;
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
            session()->flash('success', 'Refund processed successfully! Refund ID: ' . $refund->id . '. Amount: ₹' . number_format($refundAmount, 2));

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully.',
                'refund_amount' => $refundAmount,
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
}
