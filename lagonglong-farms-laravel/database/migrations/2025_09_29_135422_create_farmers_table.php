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
        Schema::create('farmers', function (Blueprint $table) {
            $table->string('farmer_id', 20)->primary();
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50);
            $table->string('suffix', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('contact_number', 15)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('barangay_id')->nullable()->constrained('barangays', 'barangay_id')->onDelete('set null');
            
            // Registration status fields
            $table->boolean('is_rsbsa')->default(false);
            $table->boolean('is_ncfrs')->default(false);
            $table->boolean('is_fisherfolk')->default(false);
            $table->boolean('is_boat')->default(false);
            
            // RSBSA specific fields
            $table->string('rsbsa_control_number', 50)->nullable();
            $table->date('rsbsa_registration_date')->nullable();
            
            // NCFRS specific fields
            $table->string('ncfrs_registration_number', 50)->nullable();
            $table->date('ncfrs_registration_date')->nullable();
            
            // FishR specific fields
            $table->string('fishr_registration_number', 50)->nullable();
            $table->date('fishr_registration_date')->nullable();
            
            // Household information
            $table->integer('household_size')->nullable();
            $table->decimal('annual_income', 12, 2)->nullable();
            $table->string('education_level', 50)->nullable();
            
            // Farming information
            $table->decimal('total_farm_area', 8, 4)->nullable();
            $table->integer('years_farming')->nullable();
            $table->enum('farm_type', ['Rice', 'Corn', 'Vegetables', 'Mixed', 'Other'])->nullable();
            
            // System fields
            $table->boolean('archived')->default(false);
            $table->text('archive_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['barangay_id']);
            $table->index(['is_rsbsa', 'is_ncfrs', 'is_fisherfolk']);
            $table->index(['archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};
