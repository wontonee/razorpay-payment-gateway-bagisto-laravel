{{-- Razorpay Refund Button Template for Admin Order Actions --}}
@php
    $orderId = request()->route('id');
    $order = app('Webkul\Sales\Repositories\OrderRepository')->findOrFail($orderId);
    $razorpayPayment = \Wontonee\Razorpay\Models\Razorpay::where('order_id', $order->id)->first();
@endphp

@if ($order->payment->method === 'razorpay' && $razorpayPayment && $razorpayPayment->canRefund())
    <!-- Razorpay Refund Drawer Component -->
    <v-create-razorpay-refund order-id="{{ $order->id }}" csrf-token="{{ csrf_token() }}">
        <div class="transparent-button px-1 py-1.5 hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800">
            <span class="icon-refund text-2xl"></span>
            @lang('wontonee-razorpay::app.razorpay-refund')
        </div>
    </v-create-razorpay-refund>@pushOnce('scripts')
        <script type="text/x-template" id="v-create-razorpay-refund-template">
            <div>
                <div
                    class="transparent-button px-1 py-1.5 hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                    @click="$refs.razorpayRefund.open(); loadPaymentInfo()"
                >                <span class="icon-refund text-2xl" role="presentation" tabindex="0"></span>
                    @lang('wontonee-razorpay::app.razorpay-refund')
                </div>

                <!-- Razorpay Refund Drawer -->
                <x-admin::form 
                    v-slot="{ meta, errors, handleSubmit }" 
                    as="div"
                >
                    <form @submit="handleSubmit($event, processRefund)">
                        <x-admin::drawer ref="razorpayRefund">
                            <!-- Drawer Header -->
                            <x-slot:header>
                                <div class="grid h-8 gap-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xl font-medium dark:text-white">
                                            @lang('Process Razorpay Refund')
                                        </p>

                                        <div class="flex gap-x-2.5">
                                            <!-- Process Refund Button -->                                            <button
                                                type="submit"
                                                class="primary-button ltr:mr-11 rtl:ml-11"
                                                :disabled="isButtonDisabled"
                                            >
                                                <span v-if="!isProcessing">@lang('Process Refund')</span>
                                                <span v-else class="flex items-center">
                                                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                                    @lang('Processing...')
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </x-slot>

                            <!-- Drawer Content -->
                            <x-slot:content class="!p-0">
                                <div class="grid p-4 !pt-0">
                                    <!-- Loading State -->
                                    <div v-if="isLoading" class="flex items-center justify-center p-8">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                        <span class="ml-3 text-gray-600 dark:text-gray-300">@lang('Loading payment information...')</span>
                                    </div>

                                    <!-- Error State -->
                                    <div v-else-if="loadError" class="text-center p-8">
                                        <span class="icon-cancel-1 text-red-500 text-4xl mb-4"></span>
                                        <p class="text-gray-600 dark:text-gray-300 mb-4">@lang('Failed to load payment information')</p>
                                        <button 
                                            type="button" 
                                            class="secondary-button"
                                            @click="loadPaymentInfo"
                                        >
                                            @lang('Retry')
                                        </button>
                                    </div>

                                    <!-- Payment Information Content -->
                                    <div v-else-if="paymentInfo" class="space-y-6">
                                        <!-- Payment Details Card -->
                                        <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-800">
                                            <h4 class="font-semibold text-gray-800 dark:text-white mb-3">@lang('Payment Information')</h4>
                                            <div class="grid grid-cols-2 gap-3 text-sm">
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-300">@lang('Payment ID'):</span>
                                                    <p class="font-medium text-gray-800 dark:text-white" v-text="paymentInfo.payment_id"></p>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-300">@lang('Total Amount'):</span>
                                                    <p class="font-medium text-gray-800 dark:text-white">₹<span v-text="paymentInfo.total_amount"></span></p>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-300">@lang('Refunded Amount'):</span>
                                                    <p class="font-medium text-gray-800 dark:text-white">₹<span v-text="paymentInfo.refunded_amount"></span></p>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-300">@lang('Available for Refund'):</span>
                                                    <p class="font-bold text-green-600">₹<span v-text="paymentInfo.refundable_amount"></span></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Refund Amount Input -->
                                        <x-admin::form.control-group class="!mb-4">
                                            <x-admin::form.control-group.label class="required">
                                                @lang('Refund Amount') (₹)
                                            </x-admin::form.control-group.label>                                            <x-admin::form.control-group.control
                                                type="number"
                                                name="refund_amount"
                                                v-model="refundAmount"
                                                placeholder="Enter refund amount or leave empty for full refund"
                                                step="0.01"
                                                min="0"
                                            />

                                            <x-admin::form.control-group.error control-name="refund_amount" />

                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                @lang('Leave empty for full refund of remaining amount')
                                            </p>
                                        </x-admin::form.control-group>

                                        <!-- Success Message -->
                                        <div v-if="successMessage" class="p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-700">
                                            <div class="flex">
                                                <span class="icon-done text-green-500 text-xl mr-3"></span>
                                                <div>
                                                    <p class="text-green-800 dark:text-green-300 font-medium">@lang('Success')</p>
                                                    <p class="text-green-600 dark:text-green-400 text-sm mt-1" v-text="successMessage"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Error Message -->
                                        <div v-if="errorMessage" class="p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-700">
                                            <div class="flex">
                                                <span class="icon-cancel-1 text-red-500 text-xl mr-3"></span>
                                                <div>
                                                    <p class="text-red-800 dark:text-red-300 font-medium">@lang('Error')</p>
                                                    <p class="text-red-600 dark:text-red-400 text-sm mt-1" v-text="errorMessage"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </x-slot>
                        </x-admin::drawer>
                    </form>
                </x-admin::form>
            </div>
        </script>        <script type="module">
            app.component('v-create-razorpay-refund', {
                template: '#v-create-razorpay-refund-template',
                
                props: {
                    orderId: {
                        type: String,
                        required: true
                    },
                    csrfToken: {
                        type: String,
                        required: true
                    }
                },

                data() {
                    return {
                        paymentInfo: null,
                        refundAmount: '',
                        isLoading: false,
                        isProcessing: false,
                        errorMessage: '',
                        successMessage: '',
                        loadError: false
                    }
                },

                computed: {
                    isButtonDisabled() {
                        return this.isProcessing || !this.paymentInfo || (this.paymentInfo && !this.paymentInfo.can_refund);
                    }
                },

                methods: {
                    async loadPaymentInfo() {
                        this.isLoading = true;
                        this.loadError = false;
                        this.errorMessage = '';
                        this.successMessage = '';
                        
                        try {
                            const response = await fetch(`/admin/razorpay/refund-status/${this.orderId}`, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                }
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                this.paymentInfo = data;
                                this.loadError = false;
                            } else {
                                this.loadError = true;
                                this.errorMessage = data.message || '@lang('Failed to load payment information')';
                            }
                        } catch (error) {
                            this.loadError = true;
                            this.errorMessage = '@lang('Error loading payment information'): ' + error.message;
                        } finally {
                            this.isLoading = false;
                        }
                    },                    async processRefund() {
                        if (!this.paymentInfo || !this.paymentInfo.can_refund) {
                            this.errorMessage = '@lang('This payment cannot be refunded')';
                            return;
                        }

                        // Validate refund amount if provided
                        if (this.refundAmount && this.refundAmount <= 0) {
                            this.errorMessage = '@lang('wontonee-razorpay::app.refund-amount-must-be-greater-than-zero')';
                            return;
                        }

                        if (this.refundAmount && parseFloat(this.refundAmount) > parseFloat(this.paymentInfo.refundable_amount)) {
                            this.errorMessage = `@lang('wontonee-razorpay::app.refund-amount-cannot-exceed-refundable-amount-of') ₹${this.paymentInfo.refundable_amount}`;
                            return;
                        }

                        this.isProcessing = true;
                        this.errorMessage = '';
                        this.successMessage = '';
                        
                        try {
                            const formData = new FormData();
                            formData.append('order_id', this.orderId);
                            if (this.refundAmount) {
                                formData.append('amount', this.refundAmount);
                            }
                            formData.append('_token', this.csrfToken);
                            
                            const response = await fetch('/admin/razorpay/refund', {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                body: formData
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                this.successMessage = `@lang('Refund processed successfully')! @lang('Refund ID'): ${data.refund_id}. @lang('Amount'): ₹${data.refund_amount}`;
                                
                                // Reload payment info to update available amounts
                                await this.loadPaymentInfo();
                                
                                // Clear the refund amount input
                                this.refundAmount = '';
                                
                                // Auto-close drawer and refresh page after 3 seconds
                                setTimeout(() => {
                                    this.$refs.razorpayRefund.close();
                                    location.reload();
                                }, 3000);
                            } else {
                                this.errorMessage = data.message || '@lang('Refund failed')';
                            }
                        } catch (error) {
                            this.errorMessage = '@lang('Error processing refund'): ' + error.message;
                        } finally {
                            this.isProcessing = false;
                        }
                    }
                }
            });
        </script>
    @endPushOnce
@endif
