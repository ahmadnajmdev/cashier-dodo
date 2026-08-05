<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dodo_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('type')->default('default');
            $table->string('dodo_id')->unique();
            $table->string('dodo_customer_id')->nullable()->index();
            $table->string('status');
            $table->string('product_id')->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('recurring_amount')->nullable();
            $table->boolean('tax_inclusive')->default(false);
            $table->string('payment_frequency_interval')->nullable();
            $table->unsignedInteger('payment_frequency_count')->nullable();
            $table->json('addons')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('on_demand')->default(false);
            $table->boolean('cancel_at_next_billing_date')->default(false);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('previous_billing_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['billable_type', 'billable_id', 'type'], 'dodo_subscriptions_billable_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dodo_subscriptions');
    }
};
