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

        // 5. Seed Patients matching PDF example
        $paymentService = new PaymentService;

        // Example from PDF: Aisha Ibrahim, File No: HSP-00125, Wallet No: 0003453125
        $aisha = Patient::firstOrCreate(
            ['file_number' => 'HSP-00125'],
            [
                'first_name' => 'Aisha',
                'last_name' => 'Ibrahim',
                'phone_number' => '08034531250',
                'email' => 'aisha.ibrahim@example.com',
                'date_of_birth' => '1995-04-12',
                'gender' => 'Female',
                'created_by' => $staff->id,
            ]
        );

        // Ensure wallet account number matches example if desired
        if ($aisha->wallet) {
            $aisha->wallet->update(['account_number' => '0003453125']);
        }

        // Demo transactions matching Page 3 & 4 example:
        // Patient wallet balance: 70,000 (after credit of 70,000)
        // Laboratory payment: 20,000
        // Patient wallet balance after: 50,000, Hospital wallet: +20,000
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

        // Second demo patient
        $emeka = Patient::firstOrCreate(
            ['file_number' => 'HSP-00126'],
            [
                'first_name' => 'Emeka',
                'last_name' => 'Obi',
                'phone_number' => '08023456789',
                'email' => 'emeka.obi@example.com',
                'date_of_birth' => '1988-11-23',
                'gender' => 'Male',
                'created_by' => $admin->id,
            ]
        );

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
    }
}
