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
        // 1. Wards
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department')->nullable();
            $table->string('type', 50)->default('General'); // General, Male, Female, Pediatric, ICU, Maternity, Surgical
            $table->unsignedInteger('capacity')->default(10);
            $table->decimal('daily_rate', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Beds
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->string('bed_number', 50);
            $table->string('status', 20)->default('available'); // available, occupied, maintenance
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Admissions (ADT Tracking)
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained('beds')->cascadeOnDelete();
            $table->foreignId('admitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('discharged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('admission_date');
            $table->dateTime('discharge_date')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('admitted'); // admitted, discharged, transferred
            $table->text('discharge_notes')->nullable();
            $table->timestamps();
        });

        // 4. Doctor Availabilities
        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->string('day_of_week', 20); // Monday, Tuesday, etc.
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // 5. Appointments & Queues
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_number', 30)->unique();
            $table->date('appointment_date')->nullable();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('department')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status', 25)->default('scheduled'); // scheduled, waiting, in_consultation, completed, cancelled
            $table->string('priority', 20)->default('routine'); // routine, urgent, emergency
            $table->unsignedInteger('queue_number')->default(1);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 6. Patient Vitals
        Schema::create('patient_vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->string('blood_pressure', 20)->nullable(); // e.g. 120/80
            $table->decimal('temperature', 4, 1)->nullable(); // e.g. 37.2 C
            $table->unsignedInteger('pulse_rate')->nullable(); // bpm
            $table->unsignedInteger('respiratory_rate')->nullable(); // bpm
            $table->unsignedInteger('spo2')->nullable(); // oxygen saturation %
            $table->decimal('weight', 5, 2)->nullable(); // kg
            $table->decimal('height', 5, 2)->nullable(); // cm
            $table->decimal('bmi', 4, 1)->nullable();
            $table->decimal('blood_sugar', 5, 2)->nullable(); // mmol/L
            $table->string('status_flag', 20)->default('stable'); // stable, guarded, critical
            $table->text('notes')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();
        });

        // 7. Nursing Notes
        Schema::create('nursing_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('nurse_id')->constrained('users')->cascadeOnDelete();
            $table->string('note_type', 30)->default('routine'); // routine, incident, handover, doctor_flag
            $table->text('note');
            $table->string('patient_status', 20)->default('stable'); // stable, guarded, critical
            $table->boolean('is_flagged_to_doctor')->default(false);
            $table->timestamps();
        });

        // 8. Nurse Shift Handovers
        Schema::create('nurse_shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outgoing_nurse_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('incoming_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->string('shift_type', 20); // morning, afternoon, night
            $table->date('handover_date');
            $table->unsignedInteger('total_patients')->default(0);
            $table->unsignedInteger('critical_patients_count')->default(0);
            $table->text('summary_notes');
            $table->timestamps();
        });

        // 9. Laboratory Test Orders
        Schema::create('lab_test_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('test_name');
            $table->text('clinical_notes')->nullable();
            $table->string('priority', 20)->default('routine'); // routine, urgent, stat
            $table->string('status', 25)->default('ordered'); // ordered, sample_collected, processing, completed, cancelled
            $table->string('sample_id', 50)->nullable(); // Barcode / sample identifier
            $table->string('sample_type', 50)->nullable(); // Blood, Urine, Stool, Swab, Sputum, etc.
            $table->dateTime('sample_collected_at')->nullable();
            $table->foreignId('sample_collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 10. Laboratory Results
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_order_id')->constrained('lab_test_orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->string('parameter_name');
            $table->string('result_value');
            $table->string('unit', 50)->nullable();
            $table->string('reference_range', 100)->nullable();
            $table->string('flag', 20)->default('normal'); // normal, abnormal, critical
            $table->text('notes')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();
        });

        // 11. Laboratory Equipment Logs
        Schema::create('lab_equipment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_name');
            $table->string('serial_number', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('status', 30)->default('operational'); // operational, maintenance, calibration_due
            $table->date('last_calibrated_at')->nullable();
            $table->date('next_calibration_due')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 12. Drugs (Pharmacy Inventory)
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('category', 100)->nullable(); // Antibiotics, Analgesics, Antihypertensives, etc.
            $table->string('dosage_form', 50)->default('Tablet'); // Tablet, Capsule, Syrup, Injection, Ointment, Drops
            $table->string('strength', 50)->nullable(); // 500mg, 250mg/5ml, etc.
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(15);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('active'); // active, low_stock, out_of_stock, expired
            $table->timestamps();
        });

        // 13. Prescriptions
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number', 30)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->text('diagnosis')->nullable();
            $table->string('status', 25)->default('pending'); // pending, dispensed, cancelled
            $table->text('notes')->nullable();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispensed_at')->nullable();
            $table->timestamps();
        });

        // 14. Prescription Items
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('drug_id')->nullable()->constrained('drugs')->nullOnDelete();
            $table->string('drug_name');
            $table->string('dosage', 100); // e.g. 500mg
            $table->string('frequency', 50); // e.g. BD, TDS, QDS, Once Daily
            $table->string('duration', 50); // e.g. 5 days, 2 weeks
            $table->string('route', 50)->nullable(); // Oral, IV, IM, Topical
            $table->text('instructions')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->boolean('is_dispensed')->default(false);
            $table->timestamps();
        });

        // 15. Medication Administration Records (MAR)
        Schema::create('medication_administration_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_item_id')->constrained('prescription_items')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('nurse_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('scheduled_time');
            $table->dateTime('administered_at')->nullable();
            $table->string('dose_administered', 100)->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled, given, missed, refused, held
            $table->string('refusal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 16. Invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('waiver_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('balance_due', 12, 2)->default(0.00);
            $table->string('status', 25)->default('unpaid'); // unpaid, partially_paid, paid, waived
            $table->string('payment_method', 30)->nullable(); // wallet, cash, pos, transfer, insurance
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 17. Invoice Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('item_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('total_price', 12, 2)->default(0.00);
            $table->timestamps();
        });

        // 18. Insurance Claims
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 30)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('provider_name');
            $table->string('policy_number');
            $table->decimal('claim_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->default(0.00);
            $table->string('status', 25)->default('submitted'); // submitted, under_review, approved, rejected
            $table->date('submission_date');
            $table->date('resolution_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 19. HMS Alerts & Notifications
        Schema::create('hms_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_role', 30)->default('all'); // all, doctor, nurse, lab_tech, admin, staff
            $table->string('alert_type', 40); // critical_lab, medication_reminder, bed_capacity, maintenance, policy_notice
            $table->string('title');
            $table->text('message');
            $table->string('priority', 20)->default('normal'); // normal, high, critical
            $table->boolean('is_read')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('link')->nullable();
            $table->timestamps();
        });

        // 20. Audit Logs (HIPAA / NDPR Compliance)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 50); // create, update, delete, view, export, dispense, administer
            $table->string('module', 50); // patients, ehr, lab, pharmacy, billing, users, wards
            $table->string('record_id', 50)->nullable();
            $table->text('description');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // 21. Patient Record Attachments
        Schema::create('patient_record_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('health_record_id')->nullable()->constrained('patient_health_records')->nullOnDelete();
            $table->string('title');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_record_attachments');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('hms_alerts');
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('medication_administration_records');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('drugs');
        Schema::dropIfExists('lab_equipment_logs');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('lab_test_orders');
        Schema::dropIfExists('nurse_shift_handovers');
        Schema::dropIfExists('nursing_notes');
        Schema::dropIfExists('patient_vitals');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('doctor_availabilities');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('wards');
    }
};
