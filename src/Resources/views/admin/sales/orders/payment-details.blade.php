{{-- Razorpay Payment Details Display for Admin Order View --}}
@if(isset($order) && $order->payment->method === 'razorpay')
    @php
        $razorpayPayment = \Wontonee\Razorpay\Models\Razorpay::where('order_id', $order->id)->first();
    @endphp

    @if($razorpayPayment)
        <div class="page-content">
            <div class="grid">
                <div class="bg-white dark:bg-gray-900 rounded box-shadow">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                        <div class="flex items-center">
                            <i class="icon-payment text-2xl mr-2.5 text-blue-600"></i>
                            <p class="text-lg text-gray-800 dark:text-white font-bold">
                                @lang('Razorpay Payment Details')
                            </p>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Payment Information -->
                            <div class="space-y-4">
                                <h4 class="font-semibold text-gray-800 dark:text-white text-base">
                                    @lang('Payment Information')
                                </h4>
                                
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-300">@lang('Payment ID'):</span>
                                        <span class="font-medium text-gray-800 dark:text-white">
                                            {{ $razorpayPayment->razorpay_payment_id ?? '-' }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-300">@lang('Customer ID'):</span>
                                        <span class="font-medium text-gray-800 dark:text-white">
                                            {{ $razorpayPayment->razorpay_customer_id ?? '-' }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-300">@lang('Payment Status'):</span>
                                        <span class="font-medium {{ $razorpayPayment->payment_status === 'captured' ? 'text-green-600' : 'text-orange-600' }}">
                                            {{ ucfirst($razorpayPayment->payment_status ?? 'Unknown') }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-300">@lang('Total Amount'):</span>
                                        <span class="font-medium text-gray-800 dark:text-white">
                                            ₹{{ number_format($razorpayPayment->amount / 100, 2) }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-300">@lang('Refunded Amount'):</span>
                                        <span class="font-medium text-gray-800 dark:text-white">
                                            ₹{{ number_format($razorpayPayment->refunded_amount / 100, 2) }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-300">@lang('Available for Refund'):</span>
                                        <span class="font-bold text-green-600">
                                            ₹{{ number_format(($razorpayPayment->amount - $razorpayPayment->refunded_amount) / 100, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Refund History -->
                            <div class="space-y-4">
                                <h4 class="font-semibold text-gray-800 dark:text-white text-base">
                                    @lang('Refund History')
                                </h4>
                                
                                @if($razorpayPayment->refund_data && count($razorpayPayment->refund_data) > 0)
                                    <div class="space-y-3">
                                        @foreach($razorpayPayment->refund_data as $refund)
                                            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-800 dark:text-white">
                                                            @lang('Refund ID'): {{ $refund['id'] ?? 'N/A' }}
                                                        </p>
                                                        <p class="text-xs text-gray-600 dark:text-gray-300">
                                                            {{ isset($refund['created_at']) ? date('M d, Y H:i', $refund['created_at']) : 'N/A' }}
                                                        </p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-sm font-bold text-green-600">
                                                            ₹{{ number_format(($refund['amount'] ?? 0) / 100, 2) }}
                                                        </p>
                                                        <p class="text-xs text-gray-600 dark:text-gray-300">
                                                            {{ ucfirst($refund['status'] ?? 'Unknown') }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <i class="icon-information text-gray-400 text-3xl mb-2"></i>
                                        <p class="text-gray-500 dark:text-gray-400">
                                            @lang('No refunds processed yet')
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Payment Data (for debugging - only show in dev) -->
                        @if(config('app.debug') && $razorpayPayment->payment_data)
                            <div class="mt-6 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
                                <h5 class="font-medium text-gray-800 dark:text-white mb-2">
                                    @lang('Payment Data') (@lang('Debug'))
                                </h5>
                                <pre class="text-xs text-gray-600 dark:text-gray-300 overflow-auto">{{ json_encode($razorpayPayment->payment_data, JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
