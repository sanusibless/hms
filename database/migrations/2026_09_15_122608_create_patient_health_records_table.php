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
        Schema::create('patient_health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('nurse_id')->nullable()->constrained('users')->nullOnDelete();

            // Health Record & Clinical Data
            $table->string('visit_type', 50)->default('outpatient'); // outpatient, inpatient, emergency, follow_up
            $table->string('icd_code', 50)->nullable(); // ICD-10 Code
            $table->text('chief_complaint')->nullable();
            $table->text('diagnosis');
            $table->text('treatment')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->text('notes')->nullable();
            $table->text('medications')->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->dateTime('last_appointment')->nullable();
            $table->dateTime('next_appointment')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_health_records');
    }
};
