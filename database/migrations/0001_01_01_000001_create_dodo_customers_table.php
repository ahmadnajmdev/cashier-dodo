<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dodo_customers', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('dodo_id')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['billable_type', 'billable_id'], 'dodo_customers_billable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dodo_customers');
    }
};
