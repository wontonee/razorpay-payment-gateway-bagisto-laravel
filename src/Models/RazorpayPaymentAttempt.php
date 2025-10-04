<?php

namespace Wontonee\Razorpay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Checkout\Models\Cart;
use Carbon\Carbon;

class RazorpayPaymentAttempt extends Model
{
    protected $table = 'razorpay_payment_attempts';

    protected $fillable = [
        'cart_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'status',
        'amount',
        'cart_data',
        'initiated_at',
        'last_checked_at',
        'retry_count',
    ];

    protected $casts = [
        'cart_data' => 'array',
        'amount' => 'decimal:4',
        'initiated_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    /**
     * Get the cart that owns this payment attempt.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    /**
     * Check if payment attempt is eligible for fallback check
     */
    public function isEligibleForFallbackCheck(): bool
    {
        // Check if status is initiated (not completed/failed/expired)
        if ($this->status !== 'initiated') {
            return false;
        }

        // Check if initiated within last 24 hours
        if ($this->initiated_at->lt(Carbon::now()->subHours(24))) {
            return false;
        }

        // Check if enough time has passed since last check (minimum 15 minutes)
        if ($this->last_checked_at && $this->last_checked_at->gt(Carbon::now()->subMinutes(15))) {
            return false;
        }

        // Check retry count limit (max 96 retries = 24 hours with 15-min intervals)
        if ($this->retry_count >= 96) {
            return false;
        }

        return true;
    }

    /**
     * Mark as expired if older than 24 hours
     */
    public function markAsExpiredIfOld(): void
    {
        if ($this->initiated_at->lt(Carbon::now()->subHours(24)) && $this->status === 'initiated') {
            $this->update(['status' => 'expired']);
        }
    }

    /**
     * Increment retry count and update last checked time
     */
    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
        $this->update(['last_checked_at' => Carbon::now()]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(string $paymentId): void
    {
        $this->update([
            'status' => 'completed',
            'razorpay_payment_id' => $paymentId,
            'last_checked_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'last_checked_at' => Carbon::now(),
        ]);
    }

    /**
     * Scope for eligible fallback checks
     */
    public function scopeEligibleForFallback($query)
    {
        return $query->where('status', 'initiated')
                    ->where('initiated_at', '>=', Carbon::now()->subHours(24))
                    ->where(function ($q) {
                        $q->whereNull('last_checked_at')
                          ->orWhere('last_checked_at', '<=', Carbon::now()->subMinutes(15));
                    })
                    ->where('retry_count', '<', 96);
    }
}