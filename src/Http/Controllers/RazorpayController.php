<?php


namespace Wontonee\Razorpay\Http\Controllers;

use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Sales\Repositories\InvoiceRepository;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Illuminate\Support\Facades\Http;

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
        $payment = $api->payment->fetch($request->input('razorpay_payment_id'));
        // Check if the payment is successful
        if ($payment->status == 'captured') {
            $cart = Cart::getCart(); 
            $data = (new OrderResource($cart))->jsonSerialize(); // new class v2.2
            $order = $this->orderRepository->create($data);
            $this->orderRepository->update(['status' => 'processing'], $order->id);
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
}
