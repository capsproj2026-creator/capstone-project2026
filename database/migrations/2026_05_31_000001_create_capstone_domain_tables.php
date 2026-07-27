<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference MySQL schema for the Capstone VMS domain.
 *
 * The application runtime uses MongoDB collections (see app/Models).
 * Run this migration only if you configure DB_CONNECTION=mysql and migrate
 * Eloquent models to the mysql connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('role_name', 50)->unique();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->string('departmentcode', 20)->primary();
            $table->string('departmentname');
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('vehicle_type', 50);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('fullname');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('user_role_id');
            $table->string('department_code', 20)->nullable();
            $table->unsignedSmallInteger('vehicle_id')->nullable();
            $table->string('id_number', 50)->nullable();
            $table->string('plate_number', 20)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('profile_pic')->nullable();
            $table->string('driver_license')->nullable();
            $table->string('or_cr_photo')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->unsignedTinyInteger('strike_count')->default(0);
            $table->string('Gate_access', 20)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreign('user_role_id')->references('id')->on('user_roles');
        });

        Schema::create('parking_areas', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('area_name');
            $table->unsignedInteger('capacity')->default(0);
            $table->text('designation_notes')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->json('allowed_roles')->nullable();
        });

        Schema::create('parking_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedSmallInteger('area_id');
            $table->string('slot_number', 20);
            $table->string('status', 20)->default('Available');
            $table->unsignedBigInteger('parked_user_id')->nullable();
            $table->foreign('area_id')->references('id')->on('parking_areas');
            $table->foreign('parked_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('gate_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedInteger('daily_log_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('action', 10);
            $table->date('log_date');
            $table->timestamp('timestamp');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['log_date', 'action']);
        });

        Schema::create('violation_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('violation_name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Active');
        });

        Schema::create('violations_log', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('violator_name');
            $table->string('id_number', 50)->nullable();
            $table->string('user_type', 30)->nullable();
            $table->string('plate_number', 20);
            $table->string('violation_type');
            $table->text('description')->nullable();
            $table->string('guard_id', 20)->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamp('created_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('type', 30)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('parking_rules', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->text('description');
        });

        Schema::create('general_information', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->text('description');
        });

        Schema::create('user_suspensions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('reason');
            $table->timestamp('suspended_at');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('counters', function (Blueprint $table) {
            $table->string('collection', 64)->primary();
            $table->unsignedBigInteger('seq')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_suspensions');
        Schema::dropIfExists('counters');
        Schema::dropIfExists('general_information');
        Schema::dropIfExists('parking_rules');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('violations_log');
        Schema::dropIfExists('violation_types');
        Schema::dropIfExists('gate_logs');
        Schema::dropIfExists('parking_slots');
        Schema::dropIfExists('parking_areas');
        Schema::dropIfExists('users');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('user_roles');
    }
};
