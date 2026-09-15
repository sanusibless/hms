<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PatientImportService
{
    /**
     * Import patients from a CSV file.
     *
     * @return array{imported: int, errors: array<string>}
     */
    public function import(UploadedFile $file, ?User $staff = null): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            throw new Exception('Unable to open uploaded file.');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new Exception('CSV file is empty.');
        }

        // Normalize header keys: lowercase, trim, remove non-alphanumeric
        $normalizedHeader = array_map(function ($col) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $col)));
        }, $header);

        $imported = 0;
        $errors = [];
        $rowNumber = 1;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $data = @array_combine($normalizedHeader, $row);
                if (! $data) {
                    $errors[] = "Row {$rowNumber}: Column count does not match header.";

                    continue;
                }

                $firstName = trim($data['firstname'] ?? $data['first_name'] ?? '');
                $lastName = trim($data['lastname'] ?? $data['last_name'] ?? '');
                $phone = trim($data['phonenumber'] ?? $data['phone'] ?? '');
                $email = trim($data['email'] ?? '') ?: null;
                $fileNo = trim($data['hospitalfilenumber'] ?? $data['filenumber'] ?? $data['fileno'] ?? '');
                $dob = trim($data['dateofbirth'] ?? $data['dob'] ?? '') ?: null;
                $gender = trim($data['gender'] ?? '') ?: null;

                if (empty($firstName) || empty($lastName) || empty($phone)) {
                    $errors[] = "Row {$rowNumber}: First name, last name, and phone number are required.";

                    continue;
                }

                // If file number is missing, auto-generate
                if (empty($fileNo)) {
                    do {
                        $fileNo = 'HSP-'.str_pad((string) random_int(100, 99999), 5, '0', STR_PAD_LEFT);
                    } while (Patient::where('file_number', $fileNo)->exists());
                } else {
                    if (Patient::where('file_number', $fileNo)->exists()) {
                        $errors[] = "Row {$rowNumber}: Hospital File Number '{$fileNo}' is already in use.";

                        continue;
                    }
                }

                Patient::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone_number' => $phone,
                    'email' => $email,
                    'file_number' => $fileNo,
                    'date_of_birth' => $dob ? date('Y-m-d', strtotime($dob)) : null,
                    'gender' => $gender,
                    'created_by' => $staff?->id,
                ]);

                $imported++;
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}
