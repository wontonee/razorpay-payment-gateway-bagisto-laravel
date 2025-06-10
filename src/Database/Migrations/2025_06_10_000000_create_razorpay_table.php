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
        Schema::create('razorpay', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('razorpay_customer_id')->nullable();
            $table->string('razorpay_payment_id')->unique();
            $table->enum('payment_status', ['paid', 'refund'])->default('paid');
            $table->json('payment_data')->nullable(); // Store full payment response
            $table->json('refund_data')->nullable(); // Store refund response when refunded
            $table->decimal('amount', 12, 4);
            $table->decimal('refunded_amount', 12, 4)->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index(['order_id', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('razorpay');
    }
};
