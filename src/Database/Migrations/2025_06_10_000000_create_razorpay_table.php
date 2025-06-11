<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('razorpay', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('razorpay_customer_id')->nullable();
            $table->string('razorpay_payment_id')->unique();
            $table->enum('payment_status', ['paid', 'refund'])->default('paid');
            $table->json('payment_data')->nullable();
            $table->json('refund_data')->nullable();
            $table->decimal('amount', 12, 4);
            $table->decimal('refunded_amount', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['order_id', 'payment_status']);
        });
    }    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('razorpay');
    }
};
