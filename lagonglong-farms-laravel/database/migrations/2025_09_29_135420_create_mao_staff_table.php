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
        Schema::create('mao_staff', function (Blueprint $table) {
            $table->id('staff_id');
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50);
            $table->string('suffix', 10)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('contact_number', 15)->nullable();
            $table->string('email', 100)->nullable()->unique();
            $table->enum('role', ['admin', 'staff', 'manager'])->default('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mao_staff');
    }
};
