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
                Processing Your Payment
            </h1>
            
            <!-- Payment Subtitle -->
            <p class="payment-subtitle">
                Please wait while we securely redirect you to Razorpay.<br>
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

    <!-- Hidden Form -->
    <form name='razorpayform' action="razorpaycheck" method="POST" style="display: none;">
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    </form>


 @pushOnce('scripts')
   <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
 <script>
        // Checkout details as a json
        var options = <?php echo $json?>;

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
            }, 800);
        }

        /**
         * The entire list of Checkout fields is available at
         * https://docs.razorpay.com/docs/checkout-form#checkout-fields
         */
        options.handler = function (response){
            // Show completion message
            document.querySelector('.payment-title').textContent = 'Payment Successful!';
            document.querySelector('.payment-subtitle').innerHTML = 'Your payment has been processed successfully.<br><strong>Please wait while we complete your order...</strong>';
            
            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
            document.getElementById('razorpay_signature').value = response.razorpay_signature;
            document.razorpayform.submit();
        };

        // Boolean whether to show image inside a white frame. (default: true)
        options.theme.image_padding = false;

        options.modal = {
            ondismiss: function() {
                // Show cancellation message
                document.querySelector('.payment-title').textContent = 'Payment Cancelled';
                document.querySelector('.payment-subtitle').innerHTML = 'Your payment was cancelled.<br><strong>Redirecting you back to cart...</strong>';
                document.querySelector('.loading-animation').style.display = 'none';
                
                setTimeout(() => {
                    window.location.href = "checkout/cart";
                }, 2000);
            },
            // Boolean indicating whether pressing escape key 
            // should close the checkout form. (default: true)
            escape: false,
            // Boolean indicating whether clicking translucent blank
            // space outside checkout form should close the form. (default: false)
            backdropclose: false
        };

        var rzp = new Razorpay(options);

        window.onload = (event) => {
            // Start progress animation
            animateProgress();
            
            // Open Razorpay after a short delay for better UX
            setTimeout(() => {
                rzp.open();
            }, 1500);
            
            event.preventDefault();
        }

    </script>

 @endPushOnce
</x-shop::layouts>




