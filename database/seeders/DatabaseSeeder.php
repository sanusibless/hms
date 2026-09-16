<?php

namespace Database\Seeders;

use App\Models\HospitalService;
use App\Models\HospitalWallet;
use App\Models\Patient;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Initialize Hospital Main Wallet
        $hospitalWallet = HospitalWallet::getSingleton();

        // 2. Seed Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@hms.test'],
            [
                'name' => 'Hospital Admin',
                'first_name' => 'Hospital',
                'last_name' => 'Admin',
                'username' => 'admin',
                'phone_number' => '08011223344',
                'role' => 'admin',
                'department' => 'Administration',
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );

        // 3. Seed Staff User
        $staff = User::firstOrCreate(
            ['email' => 'staff@hms.test'],
            [
                'name' => 'Chioma Okafor',
                'first_name' => 'Chioma',
                'last_name' => 'Okafor',
                'username' => 'staff',
                'phone_number' => '08055667788',
                'role' => 'staff',
                'department' => 'Billing & Accounts',
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );

        // 3b. Seed Doctor, Nurse, and Lab Tech Users
        $doctor = User::firstOrCreate(
            ['email' => 'doctor@hms.test'],
            [
                'name' => 'Dr. Anthony Chukwu',
                'first_name' => 'Anthony',
                'last_name' => 'Chukwu',
                'username' => 'dr.chukwu',
                'phone_number' => '08031112233',
                'role' => 'doctor',
                'department' => 'General Medicine',
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );

        $nurse = User::firstOrCreate(
            ['email' => 'nurse@hms.test'],
            [
                'name' => 'Nurse Grace Alabi',
                'first_name' => 'Grace',
                'last_name' => 'Alabi',
                'username' => 'nurse.grace',
                'phone_number' => '08042223344',
                'role' => 'nurse',
                'department' => 'Inpatient Nursing Ward',
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );

        $labTech = User::firstOrCreate(
            ['email' => 'labtech@hms.test'],
            [
                'name' => 'Peter Adeleke',
                'first_name' => 'Peter',
                'last_name' => 'Adeleke',
                'username' => 'peter.lab',
                'phone_number' => '08053334455',
                'role' => 'lab_tech',
                'department' => 'Diagnostic Pathology Lab',
                'is_active' => true,
                'password' => Hash::make('password'),
            ]
        );

        // 4. Seed Predefined Services (Page 3 of MVP document)
        $services = [
            [
                'name' => 'Consultation',
                'department' => 'General Medicine',
                'default_amount' => 5000.00,
                'description' => 'General Doctor / Specialist consultation',
            ],
            [
                'name' => 'Laboratory',
                'department' => 'Diagnostic Services',
                'default_amount' => 20000.00,
                'description' => 'Blood tests, pathology, and medical laboratory investigations',
            ],
            [
                'name' => 'Pharmacy',
                'department' => 'Pharmacy',
                'default_amount' => 15000.00,
                'description' => 'Prescription medication and dispensing',
            ],
            [
                'name' => 'Radiology',
                'department' => 'Imaging',
                'default_amount' => 25000.00,
                'description' => 'X-Ray, Ultrasound, CT Scan and imaging diagnostics',
            ],
            [
                'name' => 'Surgery',
                'department' => 'Surgical Theatre',
                'default_amount' => 150000.00,
                'description' => 'Minor and major surgical procedures and theatre charges',
            ],
            [
                'name' => 'Admission',
                'department' => 'Inpatient Ward',
                'default_amount' => 30000.00,
                'description' => 'Hospital ward accommodation and inpatient admission',
            ],
            [
                'name' => 'Other',
                'department' => 'General',
                'default_amount' => 10000.00,
                'description' => 'Sundry and miscellaneous hospital services',
            ],
        ];

        foreach ($services as $serviceData) {
            HospitalService::firstOrCreate(
                ['name' => $serviceData['name']],
                $serviceData
            );
        }

        // 5. Seed Wards & Beds
        $maleWard = \App\Models\Ward::firstOrCreate(
            ['name' => 'Male Medical Ward'],
            [
                'department' => 'Internal Medicine',
                'type' => 'Male',
                'capacity' => 12,
                'daily_rate' => 15000.00,
                'is_active' => true,
            ]
        );

        $femaleWard = \App\Models\Ward::firstOrCreate(
            ['name' => 'Female Medical Ward'],
            [
                'department' => 'Internal Medicine',
                'type' => 'Female',
                'capacity' => 12,
                'daily_rate' => 15000.00,
                'is_active' => true,
            ]
        );

        $pediatricWard = \App\Models\Ward::firstOrCreate(
            ['name' => 'Pediatric Ward'],
            [
                'department' => 'Pediatrics',
                'type' => 'Pediatric',
                'capacity' => 8,
                'daily_rate' => 12000.00,
                'is_active' => true,
            ]
        );

        $icuWard = \App\Models\Ward::firstOrCreate(
            ['name' => 'Intensive Care Unit (ICU)'],
            [
                'department' => 'Critical Care',
                'type' => 'ICU',
                'capacity' => 4,
                'daily_rate' => 75000.00,
                'is_active' => true,
            ]
        );

        // Populate beds for wards if not present
        foreach ([$maleWard, $femaleWard, $pediatricWard, $icuWard] as $w) {
            if ($w->beds()->count() === 0) {
                $prefix = strtoupper(substr($w->name, 0, 3));
                for ($i = 1; $i <= min($w->capacity, 6); $i++) {
                    $w->beds()->create([
                        'bed_number' => "{$prefix}-BED-" . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                        'status' => 'available',
                    ]);
                }
            }
        }

        // 6. Seed Patients matching PDF example & enrich with clinical/demographic/insurance data
        $paymentService = new PaymentService;

        $aisha = Patient::firstOrCreate(
            ['file_number' => 'HSP-00125'],
            [
                'first_name' => 'Aisha',
                'last_name' => 'Ibrahim',
                'phone_number' => '08034531250',
                'email' => 'aisha.ibrahim@example.com',
                'date_of_birth' => '1995-04-12',
                'gender' => 'Female',
                'address' => '14 Ahmadu Bello Way, Victoria Island, Lagos',
                'blood_group' => 'O+',
                'genotype' => 'AA',
                'emergency_contact_name' => 'Musa Ibrahim',
                'emergency_contact_phone' => '08039998877',
                'emergency_contact_relationship' => 'Spouse',
                'insurance_provider' => 'Reliance HMO',
                'insurance_policy_number' => 'REL-89210-A',
                'insurance_coverage_type' => 'Private HMO',
                'allergies' => 'Penicillin, Sulfa drugs',
                'chronic_conditions' => 'Mild Asthma',
                'admission_status' => 'outpatient',
                'created_by' => $staff->id,
            ]
        );

        if ($aisha->wallet) {
            $aisha->wallet->update(['account_number' => '0003453125']);
        }

        if ($aisha->transactions()->count() === 0) {
            $paymentService->fundPatientWallet(
                $aisha,
                70000.00,
                'Wallet Funding',
                'REF-DEP-001',
                $staff
            );

            $labService = HospitalService::where('name', 'Laboratory')->first();
            $paymentService->payForService(
                $aisha,
                $labService,
                20000.00,
                'Laboratory',
                'REF-LAB-001',
                $staff
            );
        }

        // Second demo patient: Emeka Obi (Admitted to Male Medical Ward)
        $emekaBed = $maleWard->beds()->where('status', 'available')->first();
        $emeka = Patient::firstOrCreate(
            ['file_number' => 'HSP-00126'],
            [
                'first_name' => 'Emeka',
                'last_name' => 'Obi',
                'phone_number' => '08023456789',
                'email' => 'emeka.obi@example.com',
                'date_of_birth' => '1988-11-23',
                'gender' => 'Male',
                'address' => '7 Awolowo Road, Ikoyi, Lagos',
                'blood_group' => 'B+',
                'genotype' => 'AS',
                'emergency_contact_name' => 'Ngozi Obi',
                'emergency_contact_phone' => '08021112222',
                'emergency_contact_relationship' => 'Sister',
                'insurance_provider' => 'Hygeia HMO',
                'insurance_policy_number' => 'HYG-55412-B',
                'insurance_coverage_type' => 'Corporate HMO',
                'allergies' => 'NSAIDs (Ibuprofen)',
                'chronic_conditions' => 'Hypertension Stage 1',
                'admission_status' => 'admitted',
                'current_ward_id' => $maleWard->id,
                'current_bed_id' => $emekaBed?->id,
                'admitted_at' => now()->subDays(2),
                'created_by' => $admin->id,
            ]
        );

        if ($emekaBed) {
            $emekaBed->update(['status' => 'occupied', 'patient_id' => $emeka->id]);
            \App\Models\Admission::firstOrCreate(
                ['patient_id' => $emeka->id, 'bed_id' => $emekaBed->id, 'status' => 'admitted'],
                [
                    'ward_id' => $maleWard->id,
                    'admitted_by' => $doctor->id,
                    'admission_date' => now()->subDays(2),
                    'reason' => 'Acute hypertensive urgency and severe headache with electrolyte imbalance',
                ]
            );
        }

        if ($emeka->transactions()->count() === 0) {
            $paymentService->fundPatientWallet(
                $emeka,
                50000.00,
                'Initial Wallet Deposit',
                'REF-DEP-002',
                $admin
            );

            $consultService = HospitalService::where('name', 'Consultation')->first();
            $paymentService->payForService(
                $emeka,
                $consultService,
                5000.00,
                'Doctor Consultation',
                'REF-CON-001',
                $staff
            );
        }

        // 7. Seed Doctor Availability
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        foreach ($days as $day) {
            \App\Models\DoctorAvailability::firstOrCreate(
                ['doctor_id' => $doctor->id, 'day_of_week' => $day],
                [
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'is_available' => true,
                    'notes' => 'General Outpatient & Inpatient Ward Rounds',
                ]
            );
        }

        // 8. Seed Appointments & Queues
        \App\Models\Appointment::firstOrCreate(
            ['appointment_number' => 'APT-20260915-0001'],
            [
                'patient_id' => $aisha->id,
                'doctor_id' => $doctor->id,
                'department' => 'General Medicine',
                'scheduled_at' => now()->setTime(10, 0),
                'status' => 'waiting',
                'priority' => 'urgent',
                'queue_number' => 1,
                'reason' => 'Persistent high fever and recurrent cough',
                'notes' => 'Patient triaged by nurse Grace. Vitals flagged elevated temp.',
                'booked_by' => $staff->id,
            ]
        );

        \App\Models\Appointment::firstOrCreate(
            ['appointment_number' => 'APT-20260915-0002'],
            [
                'patient_id' => $emeka->id,
                'doctor_id' => $doctor->id,
                'department' => 'Internal Medicine',
                'scheduled_at' => now()->setTime(11, 30),
                'status' => 'in_consultation',
                'priority' => 'routine',
                'queue_number' => 2,
                'reason' => 'Daily inpatient ward round review',
                'notes' => 'Monitor BP response to Amlodipine 10mg.',
                'booked_by' => $nurse->id,
            ]
        );

        // 9. Seed Patient Vitals
        \App\Models\PatientVital::firstOrCreate(
            ['patient_id' => $aisha->id, 'status_flag' => 'guarded'],
            [
                'recorded_by' => $nurse->id,
                'blood_pressure' => '125/82',
                'temperature' => 38.6,
                'pulse_rate' => 98,
                'respiratory_rate' => 20,
                'spo2' => 97,
                'weight' => 64.5,
                'height' => 165.0,
                'bmi' => 23.7,
                'blood_sugar' => 5.4,
                'notes' => 'Elevated temperature, patient shivering slightly.',
                'recorded_at' => now()->subHours(1),
            ]
        );

        \App\Models\PatientVital::firstOrCreate(
            ['patient_id' => $emeka->id, 'status_flag' => 'stable'],
            [
                'recorded_by' => $nurse->id,
                'blood_pressure' => '138/88',
                'temperature' => 36.8,
                'pulse_rate' => 74,
                'respiratory_rate' => 16,
                'spo2' => 99,
                'weight' => 82.0,
                'height' => 178.0,
                'bmi' => 25.9,
                'blood_sugar' => 6.1,
                'notes' => 'Blood pressure stabilizing following morning dose.',
                'recorded_at' => now()->subHours(3),
            ]
        );

        // 10. Seed EHR Clinical Record
        \App\Models\PatientHealthRecord::firstOrCreate(
            ['patient_id' => $aisha->id, 'icd_code' => 'J06.9'],
            [
                'doctor_id' => $doctor->id,
                'nurse_id' => $nurse->id,
                'visit_type' => 'outpatient',
                'chief_complaint' => 'Fever, sore throat and dry cough for 3 days',
                'diagnosis' => 'Acute upper respiratory tract infection with moderate febrile response',
                'treatment' => 'Symptomatic therapy, oral antibiotics after lab workup',
                'treatment_plan' => 'Hydration, paracetamol 1g TDS, Full Blood Count & Malaria MP ordered',
                'clinical_notes' => 'Patient has penicillin allergy. Avoid amoxicillin/ampicillin.',
                'allergies' => 'Penicillin, Sulfa drugs',
                'chronic_conditions' => 'Mild Asthma',
                'medications' => 'Paracetamol 500mg, Azithromycin 500mg',
                'version' => 1,
                'last_appointment' => now()->subWeeks(2),
                'next_appointment' => now()->addDays(5),
            ]
        );

        // 11. Seed Lab Test Orders & Results
        $labOrder = \App\Models\LabTestOrder::firstOrCreate(
            ['order_number' => 'LAB-20260915-0001'],
            [
                'patient_id' => $aisha->id,
                'doctor_id' => $doctor->id,
                'test_name' => 'Full Blood Count (FBC) & Malaria Parasite',
                'clinical_notes' => 'Rule out severe malaria and sepsis',
                'priority' => 'urgent',
                'status' => 'completed',
                'sample_id' => 'SMP-FBC-8812',
                'sample_type' => 'Blood',
                'sample_collected_at' => now()->subHours(2),
                'sample_collected_by' => $labTech->id,
                'completed_at' => now()->subHour(),
                'completed_by' => $labTech->id,
            ]
        );

        \App\Models\LabResult::firstOrCreate(
            ['lab_test_order_id' => $labOrder->id, 'parameter_name' => 'White Blood Cell (WBC)'],
            [
                'patient_id' => $aisha->id,
                'technician_id' => $labTech->id,
                'result_value' => '13.8',
                'unit' => 'x10^9/L',
                'reference_range' => '4.0 - 11.0',
                'flag' => 'abnormal',
                'notes' => 'Leukocytosis consistent with acute infection',
                'validated_by' => $labTech->id,
                'validated_at' => now()->subHour(),
            ]
        );

        \App\Models\LabResult::firstOrCreate(
            ['lab_test_order_id' => $labOrder->id, 'parameter_name' => 'Malaria Parasite (MP)'],
            [
                'patient_id' => $aisha->id,
                'technician_id' => $labTech->id,
                'result_value' => '2+ Plasmodium falciparum trophozoites',
                'unit' => 'Score',
                'reference_range' => 'Negative',
                'flag' => 'critical',
                'notes' => 'CRITICAL FLAG: Moderate to heavy parasitaemia. Immediate antimalarial therapy required.',
                'validated_by' => $labTech->id,
                'validated_at' => now()->subHour(),
            ]
        );

        // 12. Seed Lab Equipment
        \App\Models\LabEquipmentLog::firstOrCreate(
            ['equipment_name' => 'Sysmex XN-550 Automated Hematology Analyzer'],
            [
                'serial_number' => 'SYX-89210-NG',
                'department' => 'Hematology',
                'status' => 'operational',
                'last_calibrated_at' => now()->subDays(10),
                'next_calibration_due' => now()->addDays(20),
                'notes' => '3-level daily control within acceptable +/- 2SD range.',
                'logged_by' => $labTech->id,
            ]
        );

        \App\Models\LabEquipmentLog::firstOrCreate(
            ['equipment_name' => 'Cobas c311 Clinical Chemistry Analyzer'],
            [
                'serial_number' => 'ROCHE-C311-042',
                'department' => 'Clinical Chemistry',
                'status' => 'operational',
                'last_calibrated_at' => now()->subDays(15),
                'next_calibration_due' => now()->addDays(15),
                'notes' => 'Reagents calibrated for LFT, RFT, and Serum Electrolytes.',
                'logged_by' => $labTech->id,
            ]
        );

        // 13. Seed Pharmacy Drugs (Inventory)
        $drugs = [
            ['name' => 'Coartem (Artemether/Lumefantrine)', 'generic_name' => 'Artemether + Lumefantrine', 'category' => 'Antimalarials', 'dosage_form' => 'Tablet', 'strength' => '20/120mg', 'stock_quantity' => 85, 'reorder_level' => 20, 'unit_price' => 3500.00, 'expiry_date' => now()->addMonths(18)],
            ['name' => 'Amlodipine Besylate', 'generic_name' => 'Amlodipine', 'category' => 'Antihypertensives', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'stock_quantity' => 120, 'reorder_level' => 30, 'unit_price' => 1800.00, 'expiry_date' => now()->addMonths(24)],
            ['name' => 'Paracetamol (Acetaminophen)', 'generic_name' => 'Paracetamol', 'category' => 'Analgesics', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'stock_quantity' => 350, 'reorder_level' => 50, 'unit_price' => 500.00, 'expiry_date' => now()->addMonths(20)],
            ['name' => 'Azithromycin', 'generic_name' => 'Azithromycin', 'category' => 'Antibiotics', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'stock_quantity' => 40, 'reorder_level' => 15, 'unit_price' => 4500.00, 'expiry_date' => now()->addMonths(14)],
            ['name' => 'Metformin HCl', 'generic_name' => 'Metformin', 'category' => 'Antidiabetics', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'stock_quantity' => 95, 'reorder_level' => 25, 'unit_price' => 2200.00, 'expiry_date' => now()->addMonths(22)],
            ['name' => 'Ceftriaxone Injection', 'generic_name' => 'Ceftriaxone', 'category' => 'Antibiotics (IV)', 'dosage_form' => 'Injection', 'strength' => '1g Vial', 'stock_quantity' => 8, 'reorder_level' => 15, 'unit_price' => 5500.00, 'expiry_date' => now()->addMonths(10)], // Low stock alert!
        ];

        foreach ($drugs as $d) {
            \App\Models\Drug::firstOrCreate(
                ['name' => $d['name']],
                $d
            );
        }

        // 14. Seed Prescription & Prescription Item & MAR
        $coartem = \App\Models\Drug::where('name', 'like', '%Coartem%')->first();
        $paracetamol = \App\Models\Drug::where('name', 'like', '%Paracetamol%')->first();
        $amlodipine = \App\Models\Drug::where('name', 'like', '%Amlodipine%')->first();

        $rxAisha = \App\Models\Prescription::firstOrCreate(
            ['prescription_number' => 'RX-20260915-0001'],
            [
                'patient_id' => $aisha->id,
                'doctor_id' => $doctor->id,
                'diagnosis' => 'Falciparum Malaria with Febrile Illness',
                'status' => 'dispensed',
                'notes' => 'Patient advised on proper hydration and nutrition.',
                'dispensed_by' => $staff->id,
                'dispensed_at' => now()->subMinutes(30),
            ]
        );

        if ($rxAisha->items()->count() === 0 && $coartem && $paracetamol) {
            $rxAisha->items()->create([
                'drug_id' => $coartem->id,
                'drug_name' => $coartem->name,
                'dosage' => '4 tablets',
                'frequency' => 'BD (Twice Daily with food)',
                'duration' => '3 days',
                'route' => 'Oral',
                'instructions' => 'Complete full 6-dose course without skipping',
                'quantity' => 1,
                'unit_price' => $coartem->unit_price,
                'is_dispensed' => true,
            ]);

            $rxAisha->items()->create([
                'drug_id' => $paracetamol->id,
                'drug_name' => $paracetamol->name,
                'dosage' => '2 tablets (1g)',
                'frequency' => 'TDS (Three times daily)',
                'duration' => '3 days',
                'route' => 'Oral',
                'instructions' => 'For fever and pain relief',
                'quantity' => 2,
                'unit_price' => $paracetamol->unit_price,
                'is_dispensed' => true,
            ]);
        }

        // Inpatient Prescription for Emeka + MAR
        $rxEmeka = \App\Models\Prescription::firstOrCreate(
            ['prescription_number' => 'RX-20260915-0002'],
            [
                'patient_id' => $emeka->id,
                'doctor_id' => $doctor->id,
                'diagnosis' => 'Essential Hypertension Stage 1',
                'status' => 'dispensed',
                'notes' => 'Daily inpatient administration on ward.',
                'dispensed_by' => $staff->id,
                'dispensed_at' => now()->subDays(1),
            ]
        );

        if ($rxEmeka->items()->count() === 0 && $amlodipine) {
            $item = $rxEmeka->items()->create([
                'drug_id' => $amlodipine->id,
                'drug_name' => $amlodipine->name,
                'dosage' => '1 tablet (10mg)',
                'frequency' => 'Once Daily in Morning',
                'duration' => '14 days',
                'route' => 'Oral',
                'instructions' => 'Take with water at 08:00 AM daily',
                'quantity' => 1,
                'unit_price' => $amlodipine->unit_price,
                'is_dispensed' => true,
            ]);

            // Seed MAR for nurse
            \App\Models\MedicationAdministrationRecord::firstOrCreate(
                ['prescription_item_id' => $item->id, 'scheduled_time' => now()->setTime(8, 0)],
                [
                    'patient_id' => $emeka->id,
                    'nurse_id' => $nurse->id,
                    'administered_at' => now()->setTime(8, 15),
                    'dose_administered' => '10mg oral',
                    'status' => 'given',
                    'notes' => 'Administered smoothly, BP pre-dose 142/90, post-dose checked later.',
                ]
            );
        }

        // 15. Seed Nursing Notes & Shift Handover
        \App\Models\NursingNote::firstOrCreate(
            ['patient_id' => $emeka->id, 'note_type' => 'routine'],
            [
                'nurse_id' => $nurse->id,
                'note' => 'Patient resting comfortably in Bed MMW-BED-01. Fluid intake adequate. No chest pain reported.',
                'patient_status' => 'stable',
                'is_flagged_to_doctor' => false,
            ]
        );

        \App\Models\NurseShiftHandover::firstOrCreate(
            ['shift_type' => 'morning', 'handover_date' => now()->toDateString()],
            [
                'outgoing_nurse_id' => $nurse->id,
                'ward_id' => $maleWard->id,
                'total_patients' => 1,
                'critical_patients_count' => 0,
                'summary_notes' => 'Male Medical Ward currently has 1 inpatient (Emeka Obi). Medication administered on schedule. Ward quiet and order maintained.',
            ]
        );

        // 16. Seed Invoices & Claims
        $inv = \App\Models\Invoice::firstOrCreate(
            ['invoice_number' => 'INV-20260915-0001'],
            [
                'patient_id' => $emeka->id,
                'subtotal' => 35000.00,
                'discount_amount' => 0.00,
                'waiver_amount' => 0.00,
                'total_amount' => 35000.00,
                'paid_amount' => 35000.00,
                'balance_due' => 0.00,
                'status' => 'paid',
                'payment_method' => 'insurance',
                'notes' => 'Inpatient admission and laboratory workup covered by Hygeia HMO',
                'created_by' => $staff->id,
            ]
        );

        \App\Models\InsuranceClaim::firstOrCreate(
            ['claim_number' => 'CLM-20260915-0001'],
            [
                'patient_id' => $emeka->id,
                'invoice_id' => $inv->id,
                'provider_name' => 'Hygeia HMO',
                'policy_number' => 'HYG-55412-B',
                'claim_amount' => 35000.00,
                'approved_amount' => 35000.00,
                'status' => 'approved',
                'submission_date' => now()->subDay(),
                'resolution_date' => now(),
                'notes' => 'Pre-authorization code HYG-AUTH-9021 confirmed and claim reconciled.',
                'handled_by' => $staff->id,
            ]
        );

        // 17. Seed HMS Alerts & Notifications
        \App\Models\HmsAlert::firstOrCreate(
            ['title' => 'CRITICAL LAB ALERT: Heavy Parasitaemia'],
            [
                'user_id' => $doctor->id,
                'target_role' => 'doctor',
                'alert_type' => 'critical_lab',
                'message' => 'Critical lab result released for patient Aisha Ibrahim (HSP-00125): 2+ Plasmodium falciparum.',
                'priority' => 'critical',
                'is_read' => false,
                'link' => route('patients.show', ['patient' => $aisha->id]),
            ]
        );

        \App\Models\HmsAlert::firstOrCreate(
            ['title' => 'Pharmacy Low Stock Notice: Ceftriaxone 1g'],
            [
                'target_role' => 'admin',
                'alert_type' => 'maintenance',
                'message' => 'Ceftriaxone Injection 1g stock (8 units) has fallen below reorder threshold (15 units).',
                'priority' => 'high',
                'is_read' => false,
            ]
        );

        // 18. Seed Audit Logs
        \App\Services\AuditService::log(
            action: 'login',
            module: 'users',
            recordId: (string)$admin->id,
            description: 'Administrator logged into Hospital Management System',
            user: $admin
        );

        \App\Services\AuditService::log(
            action: 'create',
            module: 'patients',
            recordId: (string)$aisha->id,
            description: 'Registered patient Aisha Ibrahim (MRN: HSP-00125) with Reliance HMO insurance',
            user: $staff
        );

        \App\Services\AuditService::log(
            action: 'create',
            module: 'lab',
            recordId: (string)$labOrder->id,
            description: 'Lab test ordered and critical malaria result released and flagged',
            user: $labTech
        );
    }
}
