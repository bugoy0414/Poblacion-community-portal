<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('contact_number', 40)->nullable();
            $table->string('address')->nullable();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('resident');
            $table->timestamps();
            $table->unique(['user_id', 'role']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('barangay_name')->default('Barangay Community Portal');
            $table->string('hotline_label')->default('Barangay hotline');
            $table->string('hotline_number')->default('PLACEHOLDER');
            $table->string('police_number')->default('911');
            $table->string('office_hours')->default('PLACEHOLDER — set office hours');
            $table->string('acknowledgment_target')->default('PLACEHOLDER — set target time');
            $table->unsignedSmallInteger('overdue_days')->default(3);
            $table->text('privacy_notice')->nullable();
            $table->timestamps();
        });

        Schema::create('urgent_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique();
            $table->string('language', 20);
            $table->timestamps();
        });

        Schema::create('report_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_number')->default(1);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('category');
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('priority', 20)->default('normal');
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('consent_given')->default(false);
            $table->string('confidentiality', 20)->default('standard');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'category']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('report_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->default('call_resident');
            $table->string('status', 20)->default('open');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('receiving_office');
            $table->string('contact_person')->nullable();
            $table->text('reason');
            $table->text('shared_data');
            $table->string('status', 30)->default('referred');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('referral_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('note');
            $table->timestamps();
        });

        Schema::create('partner_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('break_glass_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('entity', 50);
            $table->string('entity_id', 100)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('prev_hash', 64)->nullable();
            $table->string('row_hash', 64);
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END");
            DB::unprepared("CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        }

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('break_glass_access');
        Schema::dropIfExists('partner_links');
        Schema::dropIfExists('referral_status_history');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('report_status_history');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('report_sequences');
        Schema::dropIfExists('urgent_keywords');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('user_roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'address']);
        });
    }
};
