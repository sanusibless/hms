<?php

namespace Tests\Feature;

use App\Models\HospitalService;
use App\Models\HospitalWallet;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PatientImportService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class HospitalPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected HospitalWallet $hospitalWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hospitalWallet = HospitalWallet::getSingleton();

        $this->admin = User::create([
            'name' => 'Admin User',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'username' => 'admin',
            'email' => 'admin@test.com',
            'phone_number' => '08011112222',
            'role' => 'admin',
            'department' => 'Administration',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Staff Nurse',
            'first_name' => 'Staff',
            'last_name' => 'Nurse',
            'username' => 'staff',
            'email' => 'staff@test.com',
            'phone_number' => '08033334444',
            'role' => 'staff',
            'department' => 'Billing',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    public function test_user_can_login_with_email_and_with_username(): void
    {
        // Login with email
        $response = $this->post('/login', [
            'email' => 'staff@test.com',
            'password' => 'password',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->staff);

        $this->post('/logout');
        $this->assertGuest();

        // Login with username
        $response = $this->post('/login', [
            'email' => 'admin', // username passed in email field
            'password' => 'password',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $this->staff->update(['is_active' => false]);

        $response = $this->post('/login', [
            'email' => 'staff',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_creating_patient_automatically_assigns_unique_wallet_account(): void
    {
        $patient = Patient::create([
            'first_name' => 'Aisha',
            'last_name' => 'Ibrahim',
            'phone_number' => '08034531250',
            'email' => 'aisha@example.com',
            'file_number' => 'HSP-00125',
            'date_of_birth' => '1995-04-12',
            'gender' => 'Female',
            'created_by' => $this->staff->id,
        ]);

        $this->assertNotNull($patient->wallet);
        $this->assertEquals(10, strlen($patient->wallet->account_number));
        $this->assertStringStartsWith('000', $patient->wallet->account_number);
        $this->assertEquals(0.00, (float) $patient->wallet->balance);
        $this->assertEquals('active', $patient->wallet->status);
    }

    public function test_wallet_funding_credits_patient_wallet_and_records_transaction(): void
    {
        $patient = Patient::create([
            'first_name' => 'Aisha',
            'last_name' => 'Ibrahim',
            'phone_number' => '08034531250',
            'file_number' => 'HSP-00125',
        ]);

        $service = new PaymentService;
        $txn = $service->fundPatientWallet($patient, 70000.00, 'Wallet Funding', 'REF-001', $this->staff);

        $patient->refresh();
        $this->assertEquals(70000.00, (float) $patient->wallet->balance);
        $this->assertEquals('credit', $txn->type);
        $this->assertEquals(70000.00, (float) $txn->amount);
        $this->assertEquals(70000.00, (float) $txn->patient_balance_after);
        $this->assertEquals($this->staff->id, $txn->processed_by);
    }

    public function test_service_payment_deducts_patient_wallet_and_credits_hospital_wallet(): void
    {
        $patient = Patient::create([
            'first_name' => 'Aisha',
            'last_name' => 'Ibrahim',
            'phone_number' => '08034531250',
            'file_number' => 'HSP-00125',
        ]);

        $hospitalService = HospitalService::create([
            'name' => 'Laboratory',
            'department' => 'Pathology',
            'default_amount' => 20000.00,
        ]);

        $paymentService = new PaymentService;

        // 1. Initial funding: +₦70,000
        $paymentService->fundPatientWallet($patient, 70000.00, 'Wallet Funding', null, $this->staff);

        // 2. Pay for Laboratory: -₦20,000
        $paymentTxn = $paymentService->payForService(
            $patient,
            $hospitalService,
            20000.00,
            'Laboratory blood tests',
            'LAB-REF-101',
            $this->staff
        );

        $patient->refresh();
        $this->hospitalWallet->refresh();

        // Check patient balance: ₦70,000 - ₦20,000 = ₦50,000 (matching PDF page 3 & 4)
        $this->assertEquals(50000.00, (float) $patient->wallet->balance);

        // Check hospital main wallet: +₦20,000 (matching PDF page 4)
        $this->assertEquals(20000.00, (float) $this->hospitalWallet->balance);
        $this->assertEquals(20000.00, (float) $this->hospitalWallet->total_received);

        // Check transaction details
        $this->assertEquals('debit', $paymentTxn->type);
        $this->assertEquals(20000.00, (float) $paymentTxn->amount);
        $this->assertEquals(50000.00, (float) $paymentTxn->patient_balance_after);
        $this->assertEquals(20000.00, (float) $paymentTxn->hospital_balance_after);
        $this->assertEquals('completed', $paymentTxn->status);

        // Check both ledgers include this transaction
        $this->assertTrue($patient->transactions->contains($paymentTxn));
        $this->assertTrue($this->hospitalWallet->transactions->contains($paymentTxn));
    }

    public function test_service_payment_fails_when_patient_wallet_has_insufficient_funds(): void
    {
        $patient = Patient::create([
            'first_name' => 'Emeka',
            'last_name' => 'Obi',
            'phone_number' => '08022223333',
            'file_number' => 'HSP-00126',
        ]);

        $hospitalService = HospitalService::create([
            'name' => 'Surgery',
            'department' => 'Theatre',
            'default_amount' => 150000.00,
        ]);

        $paymentService = new PaymentService;
        // Fund only ₦10,000
        $paymentService->fundPatientWallet($patient, 10000.00, 'Initial Deposit', null, $this->staff);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $paymentService->payForService($patient, $hospitalService, 150000.00, 'Surgery', null, $this->staff);
    }

    public function test_patient_csv_import_creates_patients_and_wallets(): void
    {
        $csvContent = "First Name,Last Name,Phone Number,Email,Hospital File Number,Date of Birth,Gender\n".
                      "Fatima,Bello,08098765432,fatima@test.com,HSP-00991,1998-05-15,Female\n".
                      "Chidi,Eze,08011223344,chidi@test.com,HSP-00992,1985-08-20,Male\n";

        $tempFile = tmpfile();
        fwrite($tempFile, $csvContent);
        $meta = stream_get_meta_data($tempFile);
        $uploadedFile = new UploadedFile($meta['uri'], 'patients.csv', 'text/csv', null, true);

        $importService = new PatientImportService;
        $result = $importService->import($uploadedFile, $this->staff);

        $this->assertEquals(2, $result['imported']);
        $this->assertEmpty($result['errors']);

        $fatima = Patient::where('file_number', 'HSP-00991')->first();
        $this->assertNotNull($fatima);
        $this->assertNotNull($fatima->wallet);
        $this->assertEquals(10, strlen($fatima->wallet->account_number));

        $chidi = Patient::where('file_number', 'HSP-00992')->first();
        $this->assertNotNull($chidi);
        $this->assertNotNull($chidi->wallet);
    }

    public function test_authenticated_pages_render_successfully(): void
    {
        $patient = Patient::create([
            'first_name' => 'Aisha',
            'last_name' => 'Ibrahim',
            'phone_number' => '08034531250',
            'file_number' => 'HSP-00125',
        ]);

        $this->actingAs($this->admin);

        $this->get('/dashboard')->assertOk();
        $this->get('/patients')->assertOk();
        $this->get("/patients/{$patient->id}")->assertOk();
        $this->get('/payments/create')->assertOk();
        $this->get('/wallet/main')->assertOk();
        $this->get('/transactions')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/services')->assertOk();
        $this->get('/patients-sample-csv')->assertOk();
    }
}
