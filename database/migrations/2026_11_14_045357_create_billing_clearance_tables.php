<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('year_level');
            $t->string('name', 50);
            $t->timestamps();
            $t->unique(['program_id', 'year_level', 'name']);
        });

        Schema::create('school_terms', function (Blueprint $t) {
            $t->id();
            $t->string('school_year', 9);
            $t->unsignedTinyInteger('semester');
            $t->boolean('is_current')->default(false);
            $t->timestamps();
            $t->unique(['school_year', 'semester']);
        });

        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('student_no', 20)->unique();
            $t->string('first_name');
            $t->string('last_name');
            $t->foreignId('program_id')->constrained();
            $t->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('year_level')->default(1);
            $t->enum('status', ['active', 'graduated', 'transferred', 'dropped'])->default('active');
            $t->boolean('is_activated')->default(false);
            $t->timestamps();
        });

        Schema::create('coe_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_term_id')->constrained();
            $t->string('code', 20)->unique();
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'school_term_id']);
        });

        Schema::create('student_terms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_term_id')->constrained();
            $t->unsignedTinyInteger('year_level');
            $t->decimal('tuition_total', 10, 2)->default(10000);
            $t->timestamp('unlocked_at')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'school_term_id']);
        });

        Schema::create('billing_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_term_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('period_no');
            $t->string('label', 30);
            $t->decimal('amount_due', 10, 2);
            $t->decimal('amount_paid', 10, 2)->default(0);
            $t->date('due_date')->nullable();
            $t->timestamps();
            $t->unique(['student_term_id', 'period_no']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_term_id')->constrained()->cascadeOnDelete();
            $t->foreignId('billing_period_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 10, 2);
            $t->enum('channel', ['cash', 'gcash', 'maya', 'bank']);
            $t->string('reference_no', 60)->nullable();
            $t->string('proof_path')->nullable();
            $t->enum('status', ['pending', 'verified', 'flagged', 'void'])->default('pending');
            $t->string('flag_reason')->nullable();
            $t->string('receipt_no', 30)->nullable()->unique();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->string('void_reason')->nullable();
            $t->timestamps();
            $t->unique(['channel', 'reference_no']);
        });

        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('billing_period_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
            $t->timestamps();
        });

        Schema::create('promissory_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_term_id')->constrained()->cascadeOnDelete();
            $t->foreignId('billing_period_id')->constrained()->cascadeOnDelete();
            $t->date('due_date');
            $t->enum('status', ['active', 'honored', 'expired', 'cancelled'])->default('active');
            $t->foreignId('approved_by')->constrained('users');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('title');
            $t->unsignedTinyInteger('units')->default(3);
            $t->timestamps();
        });

        Schema::create('class_offerings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_term_id')->constrained();
            $t->foreignId('subject_id')->constrained();
            $t->foreignId('block_id')->constrained();
            $t->foreignId('teacher_id')->constrained('users');
            $t->string('loading_code', 20)->unique();
            $t->string('schedule')->nullable();
            $t->timestamp('claimed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('class_enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_offering_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['class_offering_id', 'student_id']);
        });

        Schema::create('exam_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_term_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('period_no');
            $t->string('name', 30);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->timestamps();
            $t->unique(['school_term_id', 'period_no']);
        });

        Schema::create('exam_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_enrollment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exam_period_id')->constrained()->cascadeOnDelete();
            $t->enum('status', ['pending', 'completed', 'absent', 'missed', 'special_completed'])->default('pending');
            $t->enum('clearance_status', ['green', 'yellow', 'red'])->nullable();
            $t->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('marked_at')->nullable();
            $t->timestamps();
            $t->unique(['class_enrollment_id', 'exam_period_id']);
        });

        Schema::create('clearance_holds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->enum('reason', ['year_end', 'transfer']);
            $t->foreignId('placed_by')->constrained('users');
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 100);
            $t->text('description')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();
        });

        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('message');
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'settings', 'user_notifications', 'activity_logs', 'clearance_holds', 'exam_records',
            'exam_periods', 'class_enrollments', 'class_offerings', 'subjects', 'promissory_notes',
            'payment_allocations', 'payments', 'billing_periods', 'student_terms', 'coe_codes',
            'students', 'school_terms', 'blocks', 'programs',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};