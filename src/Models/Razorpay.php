<?php

namespace Wontonee\Razorpay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Sales\Models\Order;

class Razorpay extends Model
{
    protected $table = 'razorpay';

    protected $fillable = [
        'order_id',
        'razorpay_customer_id',
        'razorpay_payment_id',
        'payment_status',
        'payment_data',
        'refund_data',
        'amount',
        'refunded_amount',
    ];

    protected $casts = [
        'payment_data' => 'array',
        'refund_data' => 'array',
        'amount' => 'decimal:4',
        'refunded_amount' => 'decimal:4',
    ];

    /**
     * Get the order that owns the razorpay payment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if payment can be refunded
     */
    public function canRefund(): bool
    {
        return $this->payment_status === 'paid' && $this->refunded_amount < $this->amount;
    }

    /**
     * Get remaining refundable amount
     */
    public function getRefundableAmount(): float
    {
        return $this->amount - $this->refunded_amount;
    }

    /**
     * Check if payment is fully refunded
     */
    public function isFullyRefunded(): bool
    {
        return $this->refunded_amount >= $this->amount;
    }
}
