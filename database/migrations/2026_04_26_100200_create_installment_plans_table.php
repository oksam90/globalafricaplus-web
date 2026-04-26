<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5 — Plan de paiement fractionné (split payment).
 *
 * Un plan groupe N installments (échéances) liés à un investissement, un
 * abonnement annuel ou une formation. Chaque installment est ensuite payé
 * via un nouvel invoice PayDunya.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('payable'); // Investment | Subscription | TrainingPurchase
            $table->enum('payment_type', ['investment', 'subscription', 'training']);

            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('XOF');
            $table->unsignedTinyInteger('total_installments');
            $table->unsignedTinyInteger('paid_installments')->default(0);
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly'])->default('monthly');

            $table->enum('status', ['active', 'completed', 'cancelled', 'defaulted'])
                ->default('active')
                ->index();

            $table->timestamp('starts_at');
            $table->timestamp('next_due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('number'); // 1..N
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('XOF');

            $table->enum('status', ['pending', 'invoiced', 'paid', 'failed', 'skipped'])
                ->default('pending')
                ->index();

            $table->date('due_date')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['installment_plan_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
        Schema::dropIfExists('installment_plans');
    }
};
