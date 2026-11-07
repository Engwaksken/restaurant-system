<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('opening_float', 15, 2);
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('counted_cash', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->boolean('review_required')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['opened_by', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['pay_in', 'pay_out']);
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('cash_register_session_id')->nullable()->after('received_by')->constrained()->nullOnDelete();
            $table->enum('reconciliation_status', ['unreconciled', 'matched', 'disputed'])->default('unreconciled')->after('status');
            $table->string('statement_reference')->nullable()->after('reconciliation_status');
            $table->text('reconciliation_note')->nullable()->after('statement_reference');
            $table->foreignId('reconciled_by')->nullable()->after('reconciliation_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable()->after('reconciled_by');
            $table->index(['payment_method', 'reconciliation_status', 'paid_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('balance');
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('refund_number')->unique();
            $table->string('method');
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('refunded_amount');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['payment_method', 'reconciliation_status', 'paid_at']);
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropConstrainedForeignId('cash_register_session_id');
            $table->dropColumn(['reconciliation_status', 'statement_reference', 'reconciliation_note', 'reconciled_at']);
        });
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_register_sessions');
    }
};
