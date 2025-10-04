<x-shop::layouts>   
     <x-slot:title>
        Secure Payment - Razorpay Checkout
    </x-slot>

    @pushOnce('styles')
    <style>
        .payment-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3f84f7, #00d4ff);
        }
        
        .payment-logo {
            width: 120px;
            height: auto;
            margin: 0 auto 2rem;
            display: block;
        }
        
        .payment-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
        }
        
        .payment-subtitle {
            font-size: 1rem;
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        
        .loading-animation {
            display: inline-block;
            width: 50px;
            height: 50px;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            border-top-color: #3f84f7;
            animation: spin 1s ease-in-out infinite;
            margin: 1rem auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .security-badge {
            display: inline-flex;
            align-items: center;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 50px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            color: #4a5568;
            margin-top: 1.5rem;
        }
        
        .security-icon {
            width: 16px;
            height: 16px;
            margin-right: 0.5rem;
            color: #48bb78;
        }
        
        .progress-steps {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
            gap: 1rem;
        }
        
        .step {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e2e8f0;
            position: relative;
        }
        
        .step.active {
            background: #3f84f7;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(63, 132, 247, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(63, 132, 247, 0); }
            100% { box-shadow: 0 0 0 0 rgba(63, 132, 247, 0); }
        }
        
        .razorpay-branding {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .razorpay-logo {
            font-weight: 600;
            color: #3f84f7;
            font-size: 1.1rem;
        }
        
        @media (max-width: 640px) {
            .payment-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .payment-title {
                font-size: 1.5rem;
            }
        }
    </style>
    @endPushOnce

    <div class="payment-container">
        <div class="payment-card">
            <!-- Site Logo -->
            @if(core()->getCurrentChannel()->logo_url)
                <img src="{{ core()->getCurrentChannel()->logo_url }}" alt="{{ config('app.name') }}" class="payment-logo">
            @endif
            
            <!-- Payment Title -->
            <h1 class="payment-title">
                Redirecting to Payment Gateway
            </h1>
            
            <!-- Payment Subtitle -->
            <p class="payment-subtitle">
                Please wait while we securely redirect you to Razorpay's payment page.<br>
                <strong>Do not close this window or refresh the page.</strong>
            </p>
            
            <!-- Loading Animation -->
            <div class="loading-animation"></div>
            
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="step active"></div>
                <div class="step"></div>
                <div class="step"></div>
            </div>
            
            <!-- Security Badge -->
            <div class="security-badge">
                <svg class="security-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                </svg>
                256-bit SSL Secure Payment
            </div>
            
            <!-- Razorpay Branding -->
            <div class="razorpay-branding">
                <div class="razorpay-logo">Powered by Razorpay</div>
            </div>
        </div>
    </div>

    <!-- Standard Checkout Form - Direct Redirect to Razorpay -->
    <form name='razorpayform' action="https://api.razorpay.com/v1/checkout/embedded" method="POST" style="display: none;">
        <input type="hidden" name="key_id" value="{{ $data['key'] }}">
        <input type="hidden" name="order_id" value="{{ $data['order_id'] }}">
        <input type="hidden" name="name" value="{{ $data['name'] }}">
        <input type="hidden" name="description" value="{{ $data['description'] }}">
        <input type="hidden" name="image" value="{{ $data['image'] }}">
        <input type="hidden" name="prefill[name]" value="{{ $data['prefill']['name'] }}">
        <input type="hidden" name="prefill[email]" value="{{ $data['prefill']['email'] }}">
        <input type="hidden" name="prefill[contact]" value="{{ $data['prefill']['contact'] }}">
        <input type="hidden" name="notes[address]" value="{{ $data['notes']['address'] }}">
        <input type="hidden" name="notes[merchant_order_id]" value="{{ $data['notes']['merchant_order_id'] }}">
        <input type="hidden" name="callback_url" value="{{ $data['callback_url'] }}">
        <input type="hidden" name="cancel_url" value="{{ route('shop.checkout.cart.index') }}">
    </form>

    @pushOnce('scripts')
    <script>
        // Progress animation
        function animateProgress() {
            const steps = document.querySelectorAll('.step');
            let currentStep = 0;
            
            const interval = setInterval(() => {
                if (currentStep < steps.length) {
                    steps[currentStep].classList.add('active');
                    currentStep++;
                } else {
                    clearInterval(interval);
                }
            }, 500);
        }

        // Auto-submit form on page load (Standard Redirect Method)
        window.onload = function() {
            // Start progress animation
            animateProgress();
            
            // Auto-submit form after a brief delay for UX
            setTimeout(function() {
                console.log('Redirecting to Razorpay payment page...');
                document.razorpayform.submit();
            }, 1500);
        }
    </script>
    @endPushOnce
</x-shop::layouts>




