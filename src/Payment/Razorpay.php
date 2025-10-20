<?php

namespace Wontonee\Razorpay\Payment;

use Webkul\Payment\Payment\Payment;
use Illuminate\Support\Facades\Storage;

class Razorpay extends Payment
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $code  = 'razorpay';

    public function getRedirectUrl()
    {
        return route('razorpay.process');
    }

    /**
     * Is available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        if (!$this->cart) {
            $this->setCart();
        }

        return $this->getConfigData('active');
    }

    /**
     * Get payment method image.
     *
     * @return array
     */
    public function getImage()
    {
       $url = $this->getConfigData('image');

        if ($url) {
            return Storage::url($url);
        }

        // Fallback to default Razorpay logo
        return asset('vendor/wontonee/razorpay/razor.png');

    }
}
