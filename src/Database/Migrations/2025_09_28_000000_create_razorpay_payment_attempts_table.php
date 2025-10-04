<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('razorpay_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable(); // Will be filled when payment is made
            $table->enum('status', ['initiated', 'completed', 'failed', 'expired'])->default('initiated');
            $table->decimal('amount', 12, 4);
            $table->json('cart_data')->nullable(); // Store cart snapshot for fallback
            $table->timestamp('initiated_at');
            $table->timestamp('last_checked_at')->nullable();
            $table->tinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'initiated_at']);
            $table->index(['razorpay_order_id', 'status']);
            $table->index('cart_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('razorpay_payment_attempts');
    }
};