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
});

require __DIR__.'/settings.php';
