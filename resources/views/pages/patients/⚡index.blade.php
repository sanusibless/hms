<?php

use App\Models\Patient;
use App\Models\Wallet;
use App\Services\PatientImportService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Patients - Hospital Payment App')] class extends Component {
    use WithPagination, WithFileUploads;

    #[Url]
    public string $search = '';

    // Create Patient Form Properties
    public bool $showCreateModal = false;
    public string $first_name = '';
    public string $last_name = '';
    public string $phone_number = '';
    public string $email = '';
    public string $file_number = '';
    public string $date_of_birth = '';
    public string $gender = 'Female';
    public string $address = '';
    public string $blood_group = '';
    public string $genotype = '';
    public string $emergency_contact_name = '';
    public string $emergency_contact_phone = '';
    public string $emergency_contact_relationship = '';
    public string $insurance_provider = '';
    public string $insurance_policy_number = '';
    public string $insurance_coverage_type = 'Self-Pay';
    public string $allergies = '';
    public string $chronic_conditions = '';

    #[Url]
    public string $admissionFilter = ''; // all, admitted, outpatient

    // Upload Patient CSV Properties
    public bool $showUploadModal = false;
    public $csvFile = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'first_name', 'last_name', 'phone_number', 'email', 'date_of_birth',
            'address', 'blood_group', 'genotype', 'emergency_contact_name', 'emergency_contact_phone',
            'emergency_contact_relationship', 'insurance_provider', 'insurance_policy_number',
            'allergies', 'chronic_conditions'
        ]);
        $this->gender = 'Female';
        $this->insurance_coverage_type = 'Self-Pay';

        // Auto-generate suggested file number
        do {
            $suggested = 'HSP-' . str_pad((string) random_int(100, 99999), 5, '0', STR_PAD_LEFT);
        } while (Patient::where('file_number', $suggested)->exists());

        $this->file_number = $suggested;
        $this->showCreateModal = true;
    }

    public function createPatient(): void
    {
        $validated = $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone_number' => 'required|string|max:25',
            'email' => 'nullable|email|max:150',
            'file_number' => 'required|string|max:50|unique:patients,file_number',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'address' => 'nullable|string|max:255',
            'blood_group' => 'nullable|string|max:10',
            'genotype' => 'nullable|string|max:10',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:25',
            'emergency_contact_relationship' => 'nullable|string|max:50',
            'insurance_provider' => 'nullable|string|max:100',
            'insurance_policy_number' => 'nullable|string|max:50',
            'insurance_coverage_type' => 'nullable|string|max:50',
            'allergies' => 'nullable|string|max:255',
            'chronic_conditions' => 'nullable|string|max:255',
        ]);

        $patient = Patient::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
            'email' => $validated['email'] ?: null,
            'file_number' => $validated['file_number'],
            'date_of_birth' => $validated['date_of_birth'] ?: null,
            'gender' => $validated['gender'] ?: null,
            'address' => $validated['address'] ?: null,
            'blood_group' => $validated['blood_group'] ?: null,
            'genotype' => $validated['genotype'] ?: null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?: null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?: null,
            'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?: null,
            'insurance_provider' => $validated['insurance_provider'] ?: null,
            'insurance_policy_number' => $validated['insurance_policy_number'] ?: null,
            'insurance_coverage_type' => $validated['insurance_coverage_type'] ?: 'Self-Pay',
            'allergies' => $validated['allergies'] ?: null,
            'chronic_conditions' => $validated['chronic_conditions'] ?: null,
            'admission_status' => 'outpatient',
            'created_by' => Auth::id(),
        ]);

        \App\Services\AuditService::log('create', 'patients', (string)$patient->id, "Registered patient {$patient->full_name} ({$patient->file_number})");

        // Wallet is automatically created via Patient booted model hook
        $walletNumber = $patient->wallet->account_number ?? 'Auto-generated';

        $this->showCreateModal = false;
        Flux::toast(
            variant: 'success',
            text: "Patient {$patient->full_name} registered. Wallet Account: {$walletNumber}"
        );
    }

    public function uploadPatientList(PatientImportService $importService): void
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        try {
            $result = $importService->import($this->csvFile, Auth::user());
            $this->showUploadModal = false;
            $this->reset('csvFile');

            if (!empty($result['errors'])) {
                Flux::toast(
                    variant: 'warning',
                    text: "Imported {$result['imported']} patients with " . count($result['errors']) . " skipped rows."
                );
            } else {
                Flux::toast(
                    variant: 'success',
                    text: "Successfully imported {$result['imported']} patients and generated wallet accounts."
                );
            }
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: 'CSV Import failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = Patient::with(['wallet', 'creator'])->latest();

        if (trim($this->search) !== '') {
            $searchTerm = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('file_number', 'like', $searchTerm)
                    ->orWhere('phone_number', 'like', $searchTerm)
                    ->orWhereHas('wallet', function ($wq) use ($searchTerm) {
                        $wq->where('account_number', 'like', $searchTerm);
                    });
            });
        }

        return view('pages.patients.⚡index', [
            'patients' => $query->paginate(10),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Patients</flux:heading>
            <flux:subheading>Manage patient records, unique file numbers, and automated wallet accounts</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="$set('showUploadModal', true)" icon="arrow-up-tray" variant="outline">
                Upload Patient List
            </flux:button>
            <flux:button wire:click="openCreateModal" icon="plus" variant="primary">
                New Patient
            </flux:button>
        </div>
    </div>

    <!-- Search bar -->
    <flux:card class="p-4">
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="w-full sm:max-w-md">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by File No, Name, Phone, or Wallet Account..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>
            <div class="text-xs text-zinc-500">
                Tip: Click on a patient's name to view their profile, wallet balance, and full transaction history.
            </div>
        </div>
    </flux:card>

    <!-- Patients Table -->
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Hospital File No</th>
                        <th class="py-3 px-4">Patient Name</th>
                        <th class="py-3 px-4">Clinical / ADT</th>
                        <th class="py-3 px-4">Phone / Insurance</th>
                        <th class="py-3 px-4">Wallet Account No</th>
                        <th class="py-3 px-4 text-right">Wallet Balance</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($patients as $patient)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3.5 px-4 font-mono font-bold text-blue-600 dark:text-blue-400">
                                <a href="{{ route('patients.show', $patient) }}" class="hover:underline" wire:navigate>
                                    {{ $patient->file_number }}
                                </a>
                                @if ($patient->blood_group)
                                    <span class="block text-[10px] text-zinc-400 font-normal">Blood: {{ $patient->blood_group }} ({{ $patient->genotype ?? '—' }})</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <a href="{{ route('patients.show', $patient) }}" class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-blue-600" wire:navigate>
                                    {{ $patient->full_name }}
                                </a>
                                @if ($patient->allergies)
                                    <span class="block text-[10px] text-red-600 dark:text-red-400 truncate max-w-xs">Allergy: {{ $patient->allergies }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($patient->admission_status === 'admitted')
                                    <flux:badge color="red" size="sm">Admitted</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Outpatient</flux:badge>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <div class="font-mono">{{ $patient->phone_number }}</div>
                                <span class="text-zinc-400">{{ $patient->insurance_provider ?? 'Self-Pay' }}</span>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ $patient->wallet->account_number ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $patient->wallet ? $patient->wallet->formatted_balance : '₦0.00' }}
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                                <flux:button href="{{ route('patients.treatment-record', $patient) }}" size="xs" variant="filled" icon="document-text" wire:navigate title="Digital EHR Chart">
                                    EHR Chart
                                </flux:button>
                                <flux:button href="{{ route('patients.show', $patient) }}" size="xs" variant="ghost" wire:navigate>
                                    Profile
                                </flux:button>
                                <flux:button href="{{ route('payments.create', ['patient_id' => $patient->id]) }}" size="xs" variant="primary" wire:navigate>
                                    Pay
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-zinc-500">
                                No patients found. Click "New Patient" to add one or "Upload Patient List".
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
            {{ $patients->links() }}
        </div>
    </flux:card>

    <!-- Create Patient Modal (Page 2 Requirements) -->
    <flux:modal wire:model="showCreateModal" class="md:w-[38rem]">
        <form wire:submit="createPatient" class="space-y-4">
            <div>
                <flux:heading size="lg">Register New Patient</flux:heading>
                <flux:subheading>Full demographic, emergency contact, and insurance data capture</flux:subheading>
            </div>

            <!-- Primary Identification -->
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>First Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="first_name" placeholder="e.g. Aisha" required />
                    <flux:error name="first_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Last Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="last_name" placeholder="e.g. Ibrahim" required />
                    <flux:error name="last_name" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Phone Number <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="phone_number" placeholder="e.g. 08034531250" required />
                    <flux:error name="phone_number" />
                </flux:field>

                <flux:field>
                    <flux:label>Hospital File No (MRN) <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="file_number" placeholder="e.g. HSP-00125" required />
                    <flux:error name="file_number" />
                </flux:field>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Date of Birth</flux:label>
                    <flux:input wire:model="date_of_birth" type="date" />
                    <flux:error name="date_of_birth" />
                </flux:field>

                <flux:field>
                    <flux:label>Gender</flux:label>
                    <flux:select wire:model="gender">
                        <flux:select.option value="Female">Female</flux:select.option>
                        <flux:select.option value="Male">Male</flux:select.option>
                        <flux:select.option value="Other">Other</flux:select.option>
                    </flux:select>
                    <flux:error name="gender" />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="patient@example.com" />
                    <flux:error name="email" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Residential Address</flux:label>
                <flux:input wire:model="address" placeholder="e.g. 14 Ahmadu Bello Way, Lagos" />
                <flux:error name="address" />
            </flux:field>

            <!-- Clinical Baseline: Blood Group & Genotype -->
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Blood Group</flux:label>
                    <flux:select wire:model="blood_group">
                        <flux:select.option value="">Select Blood Group</flux:select.option>
                        <flux:select.option value="O+">O Positive (O+)</flux:select.option>
                        <flux:select.option value="O-">O Negative (O-)</flux:select.option>
                        <flux:select.option value="A+">A Positive (A+)</flux:select.option>
                        <flux:select.option value="A-">A Negative (A-)</flux:select.option>
                        <flux:select.option value="B+">B Positive (B+)</flux:select.option>
                        <flux:select.option value="B-">B Negative (B-)</flux:select.option>
                        <flux:select.option value="AB+">AB Positive (AB+)</flux:select.option>
                        <flux:select.option value="AB-">AB Negative (AB-)</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Genotype</flux:label>
                    <flux:select wire:model="genotype">
                        <flux:select.option value="">Select Genotype</flux:select.option>
                        <flux:select.option value="AA">AA</flux:select.option>
                        <flux:select.option value="AS">AS</flux:select.option>
                        <flux:select.option value="SS">SS</flux:select.option>
                        <flux:select.option value="AC">AC</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <!-- Emergency Contact -->
            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Next of Kin / Contact</flux:label>
                    <flux:input wire:model="emergency_contact_name" placeholder="Full name" />
                </flux:field>

                <flux:field>
                    <flux:label>Contact Phone</flux:label>
                    <flux:input wire:model="emergency_contact_phone" placeholder="Phone" />
                </flux:field>

                <flux:field>
                    <flux:label>Relationship</flux:label>
                    <flux:input wire:model="emergency_contact_relationship" placeholder="e.g. Spouse" />
                </flux:field>
            </div>

            <!-- Insurance & Coverage -->
            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Coverage Type</flux:label>
                    <flux:select wire:model="insurance_coverage_type">
                        <flux:select.option value="Self-Pay">Self-Pay</flux:select.option>
                        <flux:select.option value="Private HMO">Private HMO</flux:select.option>
                        <flux:select.option value="Corporate HMO">Corporate HMO</flux:select.option>
                        <flux:select.option value="NHIS">NHIS / State Scheme</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>HMO / Provider</flux:label>
                    <flux:input wire:model="insurance_provider" placeholder="e.g. Reliance HMO" />
                </flux:field>

                <flux:field>
                    <flux:label>Policy / Enrollee ID</flux:label>
                    <flux:input wire:model="insurance_policy_number" placeholder="e.g. REL-8921" />
                </flux:field>
            </div>

            <!-- Baseline Alerts -->
            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Known Drug Allergies</flux:label>
                    <flux:input wire:model="allergies" placeholder="e.g. Penicillin, Sulfa" />
                </flux:field>

                <flux:field>
                    <flux:label>Chronic Conditions</flux:label>
                    <flux:input wire:model="chronic_conditions" placeholder="e.g. Hypertension, Asthma" />
                </flux:field>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showCreateModal', false)" variant="ghost" type="button">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit">
                    Register Patient & Generate Wallet
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Upload Patient List Modal (Page 2 Requirements) -->
    <flux:modal wire:model="showUploadModal" class="md:w-[28rem]">
        <form wire:submit="uploadPatientList" class="space-y-5">
            <div>
                <flux:heading size="lg">Upload Patient List</flux:heading>
                <flux:subheading>Batch import patients from a CSV spreadsheet. Wallet accounts will be assigned automatically.</flux:subheading>
            </div>

            <flux:field>
                <flux:label>CSV File</flux:label>
                <input
                    type="file"
                    wire:model="csvFile"
                    accept=".csv,text/csv"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-zinc-800 dark:file:text-zinc-300"
                    required
                />
                <flux:error name="csvFile" />
            </flux:field>

            <div class="rounded-lg p-3 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-500 space-y-1">
                <div class="font-semibold text-zinc-700 dark:text-zinc-300">Expected CSV Columns:</div>
                <div>First Name, Last Name, Phone Number, Email, Hospital File Number, Date of Birth, Gender</div>
                <div class="pt-2">
                    <a href="{{ route('patients.sample-csv') }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium inline-flex items-center gap-1">
                        <flux:icon name="arrow-down-tray" class="size-3.5" /> Download Sample CSV Template
                    </a>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showUploadModal', false)" variant="ghost" type="button">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                    Upload & Import
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
