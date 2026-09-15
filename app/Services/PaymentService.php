<?php

namespace App\Services;

use App\Models\HospitalService;
use App\Models\HospitalWallet;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    /**
     * Fund a patient's wallet (Credit).
     */
    public function fundPatientWallet(
        Patient $patient,
        float $amount,
        string $description = 'Wallet Funding',
        ?string $reference = null,
        ?User $staff = null
    ): Transaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Funding amount must be greater than zero.');
        }

        return DB::transaction(function () use ($patient, $amount, $description, $reference, $staff) {
            // Lock patient wallet for update
            $wallet = Wallet::where('id', $patient->wallet->id)->lockForUpdate()->firstOrFail();

            $newBalance = (float) $wallet->balance + $amount;
            $wallet->balance = $newBalance;
            $wallet->save();

            return Transaction::create([
                'transaction_id' => Transaction::generateTransactionId(),
                'patient_id' => $patient->id,
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $amount,
                'patient_balance_after' => $newBalance,
                'description' => $description ?: 'Wallet Funding',
                'reference' => $reference,
                'status' => 'completed',
                'processed_by' => $staff?->id,
            ]);
        });
    }

    /**
     * Process hospital service payment from patient wallet to hospital main wallet (Debit).
     */
    public function payForService(
        Patient $patient,
        HospitalService $service,
        float $amount,
        ?string $description = null,
        ?string $reference = null,
        ?User $staff = null
    ): Transaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($patient, $service, $amount, $description, $reference, $staff) {
            // Ensure hospital main wallet exists and lock it
            $hospitalWallet = HospitalWallet::getSingleton();
            $lockedHospitalWallet = HospitalWallet::where('id', $hospitalWallet->id)->lockForUpdate()->firstOrFail();

            // Lock patient wallet for update
            $lockedPatientWallet = Wallet::where('id', $patient->wallet->id)->lockForUpdate()->firstOrFail();

            $currentBalance = (float) $lockedPatientWallet->balance;
            if ($currentBalance < $amount) {
                throw new InvalidArgumentException(
                    'Insufficient wallet balance. Available balance: ₦'.number_format($currentBalance, 2).', requested: ₦'.number_format($amount, 2)
                );
            }

            // Deduct from patient wallet
            $newPatientBalance = $currentBalance - $amount;
            $lockedPatientWallet->balance = $newPatientBalance;
            $lockedPatientWallet->save();

            // Credit hospital main wallet
            $newHospitalBalance = (float) $lockedHospitalWallet->balance + $amount;
            $lockedHospitalWallet->balance = $newHospitalBalance;
            $lockedHospitalWallet->total_received = (float) $lockedHospitalWallet->total_received + $amount;
            $lockedHospitalWallet->save();

            // Create transaction record
            return Transaction::create([
                'transaction_id' => Transaction::generateTransactionId(),
                'patient_id' => $patient->id,
                'wallet_id' => $lockedPatientWallet->id,
                'hospital_wallet_id' => $lockedHospitalWallet->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'type' => 'debit',
                'amount' => $amount,
                'patient_balance_after' => $newPatientBalance,
                'hospital_balance_after' => $newHospitalBalance,
                'description' => $description ?: $service->name,
                'reference' => $reference,
                'status' => 'completed',
                'processed_by' => $staff?->id,
            ]);
        });
    }
}
