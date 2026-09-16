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
        Schema::table('patients', function (Blueprint $table) {
            // Demographics
            $table->text('address')->nullable()->after('gender');
            $table->string('blood_group', 10)->nullable()->after('address'); // A+, B+, AB+, O+, etc.
            $table->string('genotype', 10)->nullable()->after('blood_group'); // AA, AS, SS, etc.
            $table->string('emergency_contact_name', 100)->nullable()->after('genotype');
            $table->string('emergency_contact_phone', 25)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship', 50)->nullable()->after('emergency_contact_phone');

            // Insurance Data Capture
            $table->string('insurance_provider', 100)->nullable()->after('emergency_contact_relationship');
            $table->string('insurance_policy_number', 50)->nullable()->after('insurance_provider');
            $table->string('insurance_coverage_type', 50)->nullable()->after('insurance_policy_number'); // HMO, NHIS, Private, Self-Pay

            // Centralized Medical Alerts
            $table->text('allergies')->nullable()->after('insurance_coverage_type');
            $table->text('chronic_conditions')->nullable()->after('allergies');

            // Admission, Discharge, and Transfer (ADT) tracking
            $table->string('admission_status', 20)->default('outpatient')->after('chronic_conditions'); // outpatient, admitted, discharged, transferred
            $table->unsignedBigInteger('current_ward_id')->nullable()->after('admission_status');
            $table->unsignedBigInteger('current_bed_id')->nullable()->after('current_ward_id');
            $table->dateTime('admitted_at')->nullable()->after('current_bed_id');
            $table->dateTime('discharged_at')->nullable()->after('admitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'blood_group',
                'genotype',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'insurance_provider',
                'insurance_policy_number',
                'insurance_coverage_type',
                'allergies',
                'chronic_conditions',
                'admission_status',
                'current_ward_id',
                'current_bed_id',
                'admitted_at',
                'discharged_at',
            ]);
        });
    }
};
