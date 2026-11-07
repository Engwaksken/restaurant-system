<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('role_label')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'starts_at']);
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('clock_in_at');
            $table->dateTime('clock_out_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'clock_in_at']);
            $table->index(['branch_id', 'clock_in_at']);
        });

        Schema::create('staff_compensations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('pay_type', ['monthly', 'hourly']);
            $table->decimal('rate', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'approved', 'paid', 'void'])->default('draft');
            $table->decimal('gross_total', 15, 2)->default(0);
            $table->decimal('deductions_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'period_start', 'status']);
        });

        Schema::create('payroll_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_name');
            $table->enum('pay_type', ['monthly', 'hourly']);
            $table->decimal('rate', 15, 2);
            $table->decimal('hours', 10, 2)->default(0);
            $table->decimal('base_pay', 15, 2)->default(0);
            $table->decimal('allowances', 15, 2)->default(0);
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['payroll_run_id', 'user_id']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['allowance', 'deduction']);
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_statutory')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('staff_compensations');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('shifts');
    }
};
