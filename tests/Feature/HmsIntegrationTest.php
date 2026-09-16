<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Bed;
use App\Models\Drug;
use App\Models\HmsAlert;
use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LabResult;
use App\Models\LabTestOrder;
use App\Models\MedicationAdministrationRecord;
use App\Models\Patient;
use App\Models\PatientHealthRecord;
use App\Models\PatientVital;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\Ward;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $doctor;
    protected User $nurse;
    protected User $labTech;
    protected User $staff;
    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Dr. Admin',
            'first_name' => 'Hospital',
            'last_name' => 'Admin',
            'username' => 'admin_test',
            'email' => 'admin@hms-test.com',
            'phone_number' => '08010000001',
            'role' => User::ROLE_ADMIN,
            'department' => 'Administration',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $this->doctor = User::create([
            'name' => 'Dr. Emeka Okonkwo',
            'first_name' => 'Emeka',
            'last_name' => 'Okonkwo',
            'username' => 'doctor_test',
            'email' => 'doctor@hms-test.com',
            'phone_number' => '08010000002',
            'role' => User::ROLE_DOCTOR,
            'department' => 'General Medicine',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $this->nurse = User::create([
            'name' => 'Nurse Mary Adebayo',
            'first_name' => 'Mary',
            'last_name' => 'Adebayo',
            'username' => 'nurse_test',
            'email' => 'nurse@hms-test.com',
            'phone_number' => '08010000003',
            'role' => User::ROLE_NURSE,
            'department' => 'Nursing',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $this->labTech = User::create([
            'name' => 'Tariq Bello',
            'first_name' => 'Tariq',
            'last_name' => 'Bello',
            'username' => 'labtech_test',
            'email' => 'labtech@hms-test.com',
            'phone_number' => '08010000004',
            'role' => User::ROLE_LAB_TECH,
            'department' => 'Pathology',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Grace Cashier',
            'first_name' => 'Grace',
            'last_name' => 'Cashier',
            'username' => 'staff_test',
            'email' => 'staff@hms-test.com',
            'phone_number' => '08010000005',
            'role' => User::ROLE_STAFF,
            'department' => 'Billing & Accounts',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'first_name' => 'Chinedu',
            'last_name' => 'Eze',
            'full_name' => 'Chinedu Eze',
            'email' => 'chinedu@example.com',
            'phone_number' => '08099887766',
            'file_number' => 'HSP-TEST-001',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'address' => '14 Ahmadu Bello Way, Abuja',
            'blood_group' => 'O+',
            'genotype' => 'AA',
            'allergies' => 'Penicillin',
            'chronic_conditions' => 'Mild Asthma',
            'admission_status' => 'outpatient',
            'insurance_provider' => 'Hygeia HMO',
            'insurance_policy_number' => 'HYG-2026-9921',
            'insurance_coverage_type' => 'Private HMO',
        ]);
    }

    public function test_user_roles_and_rbac_helpers(): void
    {
        $this->assertTrue($this->admin->isAdmin());
        $this->assertTrue($this->admin->hasRole('doctor'));
        $this->assertTrue($this->admin->hasRole('nurse'));
        $this->assertTrue($this->admin->hasRole('lab_tech'));

        $this->assertTrue($this->doctor->isDoctor());
        $this->assertFalse($this->doctor->isAdmin());
        $this->assertTrue($this->doctor->hasRole(['doctor', 'nurse']));
        $this->assertFalse($this->doctor->hasRole('lab_tech'));

        $this->assertTrue($this->nurse->isNurse());
        $this->assertFalse($this->nurse->isDoctor());

        $this->assertTrue($this->labTech->isLabTech());
        $this->assertFalse($this->labTech->isDoctor());

        $this->assertTrue($this->staff->isStaff());
        $this->assertFalse($this->staff->isDoctor());
    }

    public function test_patient_demographics_and_insurance_fields(): void
    {
        $this->assertEquals('O+', $this->patient->blood_group);
        $this->assertEquals('AA', $this->patient->genotype);
        $this->assertEquals('Hygeia HMO', $this->patient->insurance_provider);
        $this->assertEquals('HYG-2026-9921', $this->patient->insurance_policy_number);
        $this->assertEquals('outpatient', $this->patient->admission_status);
        $this->assertNotNull($this->patient->wallet);
    }

    public function test_adt_ward_bed_admission_and_discharge_flow(): void
    {
        $ward = Ward::create([
            'name' => 'Male Surgical Ward',
            'department' => 'Surgery',
            'type' => 'Surgical',
            'capacity' => 10,
            'daily_rate' => 15000.00,
            'is_active' => true,
        ]);

        $bed = Bed::create([
            'ward_id' => $ward->id,
            'bed_number' => 'MSW-B01',
            'status' => 'available',
        ]);

        // Admit Patient
        $admission = Admission::create([
            'patient_id' => $this->patient->id,
            'ward_id' => $ward->id,
            'bed_id' => $bed->id,
            'admitted_by' => $this->doctor->id,
            'admission_date' => Carbon::now(),
            'status' => 'admitted',
            'reason' => 'Acute appendicitis',
        ]);

        $bed->update(['status' => 'occupied', 'patient_id' => $this->patient->id]);
        $this->patient->update([
            'admission_status' => 'admitted',
            'current_ward_id' => $ward->id,
            'current_bed_id' => $bed->id,
        ]);

        $this->assertEquals('occupied', $bed->fresh()->status);
        $this->assertEquals('admitted', $this->patient->fresh()->admission_status);
        $this->assertEquals($bed->id, $this->patient->fresh()->current_bed_id);

        // Discharge Patient
        $admission->update([
            'discharge_date' => Carbon::now(),
            'discharged_by' => $this->doctor->id,
            'status' => 'discharged',
            'discharge_notes' => 'Patient stabilized and successfully discharged post-op.',
        ]);

        $bed->update(['status' => 'available', 'patient_id' => null]);
        $this->patient->update([
            'admission_status' => 'discharged',
            'current_ward_id' => null,
            'current_bed_id' => null,
        ]);

        $this->assertEquals('available', $bed->fresh()->status);
        $this->assertEquals('discharged', $this->patient->fresh()->admission_status);
        $this->assertNull($this->patient->fresh()->current_bed_id);
    }

    public function test_appointment_booking_and_queue_triage(): void
    {
        $appointment = Appointment::create([
            'appointment_number' => 'APT-'.time(),
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'booked_by' => $this->staff->id,
            'scheduled_at' => Carbon::today()->setHour(10)->setMinute(0),
            'status' => 'scheduled',
            'priority' => 'routine',
            'queue_number' => 1,
            'reason' => 'Persistent high fever and cough',
        ]);

        $this->assertEquals('scheduled', $appointment->status);

        // Patient arrives -> Nurse triages and updates queue priority
        $appointment->update([
            'status' => 'waiting',
            'queue_number' => 1,
            'priority' => 'urgent',
        ]);

        $this->assertEquals('waiting', $appointment->fresh()->status);
        $this->assertEquals('urgent', $appointment->fresh()->priority);

        // Doctor completes consultation
        $appointment->update([
            'status' => 'completed',
        ]);

        $this->assertEquals('completed', $appointment->fresh()->status);
    }

    public function test_ehr_clinical_chart_and_vitals_recording(): void
    {
        // Record vital signs
        $vital = PatientVital::create([
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->nurse->id,
            'temperature' => 38.5,
            'blood_pressure' => '125/82',
            'pulse_rate' => 88,
            'respiratory_rate' => 18,
            'spo2' => 98,
            'weight' => 72.0,
            'height' => 175.0,
            'bmi' => 23.5,
            'recorded_at' => Carbon::now(),
        ]);

        $this->assertDatabaseHas('patient_vitals', [
            'id' => $vital->id,
            'temperature' => 38.5,
            'blood_pressure' => '125/82',
        ]);

        // Doctor creates EHR Clinical Encounter Note
        $ehr = PatientHealthRecord::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visit_date' => Carbon::today(),
            'visit_type' => 'outpatient',
            'chief_complaint' => 'Fever and general malaise for 3 days',
            'icd_code' => 'A90',
            'diagnosis' => 'Dengue fever, suspected / Acute febrile illness',
            'treatment_plan' => 'Hydration, paracetamol, bed rest, full blood count ordered.',
            'clinical_notes' => 'Patient alert, no signs of hemorrhagic fever.',
            'is_confidential' => false,
            'version' => 1,
        ]);

        $this->assertDatabaseHas('patient_health_records', [
            'id' => $ehr->id,
            'icd_code' => 'A90',
            'diagnosis' => 'Dengue fever, suspected / Acute febrile illness',
        ]);
        $this->assertEquals('A90', $ehr->icd_code);
    }

    public function test_lab_order_and_critical_result_alert(): void
    {
        $labOrder = LabTestOrder::create([
            'order_number' => 'ORD-LAB-'.time(),
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'test_name' => 'Serum Potassium',
            'priority' => 'urgent',
            'sample_type' => 'Serum Blood',
            'sample_id' => 'BC-POT-'.rand(1000, 9999),
            'status' => 'sample_collected',
            'clinical_notes' => 'Suspected electrolyte imbalance',
        ]);

        $this->assertNotNull($labOrder->sample_id);

        // Lab Tech enters result with CRITICAL flag
        $labResult = LabResult::create([
            'lab_test_order_id' => $labOrder->id,
            'patient_id' => $this->patient->id,
            'technician_id' => $this->labTech->id,
            'parameter_name' => 'Potassium (K+)',
            'result_value' => '6.8',
            'reference_range' => '3.5 - 5.0 mmol/L',
            'unit' => 'mmol/L',
            'flag' => 'critical',
            'notes' => 'Severe hyperkalemia detected. Immediate doctor intervention recommended.',
        ]);

        $labOrder->update(['status' => 'completed']);

        // System creates critical alert
        $alert = HmsAlert::create([
            'target_role' => 'doctor',
            'alert_type' => 'critical_lab',
            'title' => 'Critical Lab Flag: Serum Potassium',
            'message' => "Patient {$this->patient->full_name} has critical Potassium value of 6.8 mmol/L.",
            'priority' => 'critical',
            'is_read' => false,
        ]);

        $this->assertEquals('critical', $labResult->flag);
        $this->assertDatabaseHas('hms_alerts', [
            'priority' => 'critical',
            'alert_type' => 'critical_lab',
        ]);
    }

    public function test_pharmacy_prescription_dispense_and_mar_flow(): void
    {
        // 1. Create drug in formulary
        $drug = Drug::create([
            'name' => 'Artemether-Lumefantrine 80/480mg',
            'generic_name' => 'Artemether + Lumefantrine',
            'category' => 'Antimalarial',
            'dosage_form' => 'Tablet',
            'strength' => '80/480mg',
            'unit_price' => 2500.00,
            'stock_quantity' => 100,
            'reorder_level' => 20,
            'status' => 'active',
        ]);

        // 2. Doctor prescribes
        $prescription = Prescription::create([
            'prescription_number' => 'RX-'.time(),
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'pending',
            'notes' => 'Complete full 3-day course with food.',
        ]);

        $item = PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'drug_id' => $drug->id,
            'drug_name' => $drug->name,
            'dosage' => '1 tablet',
            'frequency' => 'Twice daily',
            'duration' => '3 days',
            'quantity' => 6,
            'instructions' => 'Take with fatty meal or milk.',
            'is_dispensed' => false,
        ]);

        // 3. Pharmacy dispenses
        $item->update([
            'is_dispensed' => true,
        ]);
        $prescription->update(['status' => 'dispensed', 'dispensed_by' => $this->staff->id, 'dispensed_at' => Carbon::now()]);
        $drug->decrement('stock_quantity', 6);

        $this->assertEquals(94, $drug->fresh()->stock_quantity);
        $this->assertEquals('dispensed', $prescription->fresh()->status);

        // 4. Nurse administers dose in MAR
        $mar = MedicationAdministrationRecord::create([
            'prescription_item_id' => $item->id,
            'patient_id' => $this->patient->id,
            'nurse_id' => $this->nurse->id,
            'scheduled_time' => Carbon::now(),
            'administered_at' => Carbon::now(),
            'dose_administered' => '1 tablet',
            'status' => 'given',
            'notes' => 'Tolerated well, no vomiting.',
        ]);

        $this->assertEquals('given', $mar->status);
        $this->assertEquals($this->nurse->id, $mar->nurse_id);
    }

    public function test_billing_invoice_and_insurance_claim(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.time(),
            'patient_id' => $this->patient->id,
            'subtotal' => 25000.00,
            'discount_amount' => 0.00,
            'waiver_amount' => 0.00,
            'total_amount' => 25000.00,
            'paid_amount' => 5000.00,
            'balance_due' => 20000.00,
            'status' => 'partially_paid',
            'payment_method' => 'insurance',
            'created_by' => $this->staff->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_name' => 'Specialist Clinical Consultation',
            'quantity' => 1,
            'unit_price' => 25000.00,
            'total_price' => 25000.00,
        ]);

        // Submit HMO Claim for the remaining balance
        $claim = InsuranceClaim::create([
            'claim_number' => 'CLM-'.time(),
            'patient_id' => $this->patient->id,
            'invoice_id' => $invoice->id,
            'provider_name' => 'Hygeia HMO',
            'policy_number' => $this->patient->insurance_policy_number,
            'claim_amount' => 20000.00,
            'status' => 'submitted',
            'submission_date' => Carbon::today(),
        ]);

        $this->assertEquals('submitted', $claim->status);
        $this->assertEquals(20000.00, (float)$claim->claim_amount);
        $this->assertDatabaseHas('insurance_claims', [
            'id' => $claim->id,
            'provider_name' => 'Hygeia HMO',
        ]);
    }

    public function test_hipaa_ndpr_audit_logging(): void
    {
        $log = AuditService::log(
            action: 'EHR_ACCESS',
            module: 'EHR',
            recordId: (string)$this->patient->id,
            description: "Dr. {$this->doctor->name} accessed confidential medical chart for patient {$this->patient->file_number}.",
            user: $this->doctor
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'EHR_ACCESS',
            'module' => 'EHR',
            'record_id' => (string)$this->patient->id,
            'user_id' => $this->doctor->id,
        ]);
    }
}
