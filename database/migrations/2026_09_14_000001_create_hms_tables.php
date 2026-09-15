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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department')->nullable();
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->string('file_number')->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            $table->string('account_number', 20)->unique();
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('hospital_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Hospital Main Wallet');
            $table->string('account_number', 20)->default('HSP-MAIN-001');
            $table->decimal('balance', 14, 2)->default(0.00);
            $table->decimal('total_received', 14, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('hospital_wallet_id')->nullable()->constrained('hospital_wallets')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->string('type', 20); // 'credit', 'debit'
            $table->decimal('amount', 12, 2);
            $table->decimal('patient_balance_after', 12, 2)->nullable();
            $table->decimal('hospital_balance_after', 14, 2)->nullable();
            $table->string('description');
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('completed'); // 'completed', 'failed', 'pending'
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('hospital_wallets');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('services');
    }
};
