<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dodo_transactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('dodo_id')->unique();
            $table->string('dodo_customer_id')->nullable()->index();
            $table->string('dodo_subscription_id')->nullable()->index();
            $table->string('status');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('tax')->nullable();
            $table->string('currency', 3);
            $table->string('refund_status')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_network')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('billed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dodo_transactions');
    }
};
