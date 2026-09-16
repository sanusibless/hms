<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    // Patients
    Route::livewire('patients', 'pages::patients.index')->name('patients.index');
    Route::livewire('patients/{patient}', 'pages::patients.show')->name('patients.show');
    Route::livewire('patients/{patient}/treatment-record', 'pages::patients.treatment-record')->name('patients.treatment-record');
    Route::get('patients-sample-csv', function () {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="hms_patient_sample.csv"',
        ];

        $columns = ['First Name', 'Last Name', 'Phone Number', 'Email', 'Hospital File Number', 'Date of Birth', 'Gender'];
        $sampleRows = [
            ['Aisha', 'Ibrahim', '08034531250', 'aisha.ibrahim@example.com', 'HSP-00125', '1995-04-12', 'Female'],
            ['Emeka', 'Obi', '08023456789', 'emeka.obi@example.com', 'HSP-00126', '1988-11-23', 'Male'],
            ['Fatima', 'Bello', '08098765432', 'fatima.bello@example.com', 'HSP-00127', '2001-07-19', 'Female'],
        ];

        $callback = function () use ($columns, $sampleRows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($sampleRows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    })->name('patients.sample-csv');

    // Make Payment
    Route::livewire('payments/create', 'pages::payments.create')->name('payments.create');

    // Hospital Main Wallet
    Route::livewire('wallet/main', 'pages::wallet.main')->name('wallet.main');

    // Transactions Ledger
    Route::livewire('transactions', 'pages::transactions.index')->name('transactions.index');

    // Admin Users & Services
    Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
    Route::livewire('admin/services', 'pages::admin.services')->name('admin.services');
    Route::livewire('admin/audit-logs', 'pages::admin.audit-logs')->name('admin.audit-logs');

    // Appointments & Queue
    Route::livewire('appointments', 'pages::appointments.index')->name('appointments.index');
    Route::livewire('appointments/queue', 'pages::appointments.queue')->name('appointments.queue');

    // Wards & Bed Management (ADT)
    Route::livewire('wards', 'pages::wards.index')->name('wards.index');

    // Electronic Health Records (EHR)
    Route::livewire('ehr', 'pages::ehr.index')->name('ehr.index');

    // Laboratory Information System (LIS)
    Route::livewire('lab/orders', 'pages::lab.orders')->name('lab.orders');
    Route::livewire('lab/results', 'pages::lab.results')->name('lab.results');
    Route::livewire('lab/equipment', 'pages::lab.equipment')->name('lab.equipment');

    // Pharmacy & MAR
    Route::livewire('pharmacy/inventory', 'pages::pharmacy.inventory')->name('pharmacy.inventory');
    Route::livewire('pharmacy/prescriptions', 'pages::pharmacy.prescriptions')->name('pharmacy.prescriptions');
    Route::livewire('pharmacy/mar', 'pages::pharmacy.mar')->name('pharmacy.mar');

    // Billing & HMO Claims
    Route::livewire('billing/invoices', 'pages::billing.invoices')->name('billing.invoices');
    Route::livewire('billing/claims', 'pages::billing.claims')->name('billing.claims');

    // Alerts & Notifications
    Route::livewire('alerts', 'pages::alerts.index')->name('alerts.index');

    // Reports & Analytics
    Route::livewire('reports', 'pages::reports.index')->name('reports.index');
});

require __DIR__.'/settings.php';
