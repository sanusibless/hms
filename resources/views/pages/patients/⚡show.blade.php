<?php

use App\Models\HospitalService;
use App\Models\Patient;
use App\Services\PaymentService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Patient Profile - Hospital Payment App')] class extends Component {
    public Patient $patient;

    // Fund Wallet Modal State
    public bool $showFundModal = false;
    public string $fundAmount = '';
    public string $fundDescription = 'Wallet Funding';
    public string $fundReference = '';

    // Make Payment Modal State
    public bool $showPayModal = false;
    public ?int $selectedServiceId = null;
    public string $payAmount = '';
    public string $payDescription = '';
    public string $payReference = '';

    public string $activeTab = 'wallet';

    public function mount(Patient $patient): void
    {
        $this->patient = $patient->load([
            'wallet',
            'creator',
            'transactions.service',
            'healthRecords.doctor',
            'vitals.recordedBy',
            'labOrders.results',
            'prescriptions.items',
            'admissions.ward',
            'currentWard',
            'currentBed',
            'invoices',
        ]);
    }

    public function openFundModal(): void
    {
        $this->reset(['fundAmount', 'fundReference']);
        $this->fundDescription = 'Wallet Funding';
        $this->showFundModal = true;
    }

    public function processWalletFunding(PaymentService $paymentService): void
    {
        $this->validate([
            'fundAmount' => 'required|numeric|min:1',
            'fundDescription' => 'required|string|max:150',
            'fundReference' => 'nullable|string|max:100',
        ]);

        try {
            $txn = $paymentService->fundPatientWallet(
                $this->patient,
                (float)$this->fundAmount,
                $this->fundDescription,
                $this->fundReference,
                Auth::user()
            );

            $this->patient->load(['wallet', 'transactions.service']);
            $this->showFundModal = false;

            Flux::toast(
                variant: 'success',
                text: "Wallet credited with ₦" . number_format((float)$this->fundAmount, 2) . ". New Balance: " . $this->patient->wallet->formatted_balance
            );
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openPayModal(): void
    {
        $this->reset(['selectedServiceId', 'payAmount', 'payDescription', 'payReference']);
        $firstService = HospitalService::active()->first();
        if ($firstService) {
            $this->selectedServiceId = $firstService->id;
            $this->payAmount = (string)($firstService->default_amount ?? '');
            $this->payDescription = $firstService->name;
        }
        $this->showPayModal = true;
    }

    public function updatedSelectedServiceId($id): void
    {
        $service = HospitalService::find($id);
        if ($service) {
            $this->payAmount = (string)($service->default_amount ?? '');
            $this->payDescription = $service->name;
        }
    }

    public function processServicePayment(PaymentService $paymentService): void
    {
        $this->validate([
            'selectedServiceId' => 'required|exists:services,id',
            'payAmount' => 'required|numeric|min:1',
            'payDescription' => 'nullable|string|max:150',
            'payReference' => 'nullable|string|max:100',
        ]);

        $service = HospitalService::findOrFail($this->selectedServiceId);

        try {
            $txn = $paymentService->payForService(
                $this->patient,
                $service,
                (float)$this->payAmount,
                $this->payDescription ?: $service->name,
                $this->payReference,
                Auth::user()
            );

            $this->patient->load(['wallet', 'transactions.service']);
            $this->showPayModal = false;

            Flux::toast(
                variant: 'success',
                text: "Payment of ₦" . number_format((float)$this->payAmount, 2) . " completed for {$service->name}. Patient Balance: " . $this->patient->wallet->formatted_balance
            );
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Breadcrumb & Back -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('patients.index') }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                Back to Patients
            </flux:button>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="openFundModal" icon="banknotes" variant="filled">
                Fund Wallet
            </flux:button>
            <flux:button wire:click="openPayModal" icon="credit-card" variant="primary">
                Make Payment
            </flux:button>
        </div>
    </div>

    <!-- Patient Profile & Wallet Grid (Page 3 Requirements) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Patient Information Card -->
        <flux:card class="md:col-span-2 p-6">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <flux:avatar :name="$patient->full_name" size="lg" />
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="xl">{{ $patient->full_name }}</flux:heading>
                            @if ($patient->admission_status === 'admitted')
                                <flux:badge color="red" size="sm">Admitted ({{ $patient->currentWard?->name }})</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Outpatient</flux:badge>
                            @endif
                        </div>
                        <span class="inline-block mt-0.5 font-mono text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 rounded border border-blue-200 dark:border-blue-900">
                            MRN: {{ $patient->file_number }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <flux:badge color="zinc">{{ $patient->gender ?? 'Unspecified' }}</flux:badge>
                    <flux:button href="{{ route('patients.treatment-record', ['patient' => $patient]) }}" variant="primary" icon="document-text" size="sm" wire:navigate>
                        Digital EHR Chart
                    </flux:button>
                </div>
            </div>

            <!-- Demographics & Insurance Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 block uppercase">Phone Number</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200 font-mono">{{ $patient->phone_number }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 block uppercase">Email Address</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $patient->email ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 block uppercase">Date of Birth</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">
                        {{ $patient->date_of_birth ? $patient->date_of_birth->format('d M Y') : '—' }}
                    </span>
                </div>

                <div>
                    <span class="text-xs text-zinc-500 block uppercase">Blood Group & Genotype</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-100 font-mono">
                        {{ $patient->blood_group ?? '—' }} ({{ $patient->genotype ?? '—' }})
                    </span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 block uppercase">HMO / Insurance</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">
                        {{ $patient->insurance_provider ?? 'Self-Pay' }}
                        @if ($patient->insurance_policy_number)
                            <span class="block text-xs font-mono text-zinc-500">{{ $patient->insurance_policy_number }}</span>
                        @endif
                    </span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 block uppercase">Next of Kin / Contact</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">
                        {{ $patient->emergency_contact_name ?? '—' }}
                        @if ($patient->emergency_contact_phone)
                            <span class="block text-xs font-mono text-zinc-500">{{ $patient->emergency_contact_phone }}</span>
                        @endif
                    </span>
                </div>
            </div>

            @if ($patient->address)
                <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800 text-xs text-zinc-600 dark:text-zinc-400">
                    <strong>Address:</strong> {{ $patient->address }}
                </div>
            @endif

            <!-- Clinical Alerts (Allergies & Chronic Conditions) -->
            @if ($patient->allergies || $patient->chronic_conditions)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-700 text-xs">
                    @if ($patient->allergies)
                        <div class="p-2.5 rounded-lg bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-900/60 flex items-center gap-2">
                            <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-red-500" />
                            <span><strong>Allergies:</strong> {{ $patient->allergies }}</span>
                        </div>
                    @endif
                    @if ($patient->chronic_conditions)
                        <div class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/60 flex items-center gap-2">
                            <flux:icon name="information-circle" class="size-4 shrink-0 text-amber-500" />
                            <span><strong>Chronic Conditions:</strong> {{ $patient->chronic_conditions }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </flux:card>

        <!-- Wallet Card (Page 3 Requirements) -->
        <flux:card class="p-6 bg-gradient-to-br from-indigo-900 to-zinc-900 text-white flex flex-col justify-between shadow-lg relative overflow-hidden">
            <div class="absolute -right-6 -top-6 size-28 bg-white/5 rounded-full blur-xl pointer-events-none"></div>

            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-widest text-indigo-200 font-semibold">Patient Wallet</span>
                    <flux:badge color="green" size="sm">Active</flux:badge>
                </div>

                <div class="mt-4">
                    <span class="text-xs text-indigo-300">Account Number</span>
                    <div class="font-mono text-xl tracking-wider font-bold text-white mt-0.5">
                        {{ $patient->wallet->account_number ?? '0000000000' }}
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-4 border-t border-indigo-800/60">
                <span class="text-xs text-indigo-300">Current Balance</span>
                <div class="text-3xl font-extrabold text-white mt-1">
                    {{ $patient->wallet ? $patient->wallet->formatted_balance : '₦0.00' }}
                </div>
            </div>

            <div class="flex items-center gap-2 mt-6">
                <flux:button wire:click="openFundModal" size="sm" class="w-full !bg-white/20 hover:!bg-white/30 !text-white border-none">
                    + Fund Wallet
                </flux:button>
                <flux:button wire:click="openPayModal" size="sm" class="w-full !bg-blue-600 hover:!bg-blue-500 !text-white border-none">
                    Pay Service
                </flux:button>
            </div>
        </flux:card>
    </div>

    <!-- Navigation Tabs for Patient Chart & Transactions -->
    <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-2">
        <flux:button wire:click="$set('activeTab', 'wallet')" variant="{{ $activeTab === 'wallet' ? 'filled' : 'ghost' }}" size="sm" icon="credit-card">
            Wallet Activity ({{ $patient->transactions->count() }})
        </flux:button>
        <flux:button wire:click="$set('activeTab', 'ehr')" variant="{{ $activeTab === 'ehr' ? 'filled' : 'ghost' }}" size="sm" icon="document-text">
            Clinical EHR ({{ $patient->healthRecords->count() }})
        </flux:button>
        <flux:button wire:click="$set('activeTab', 'vitals')" variant="{{ $activeTab === 'vitals' ? 'filled' : 'ghost' }}" size="sm" icon="heart">
            Vitals History ({{ $patient->vitals->count() }})
        </flux:button>
        <flux:button wire:click="$set('activeTab', 'lab')" variant="{{ $activeTab === 'lab' ? 'filled' : 'ghost' }}" size="sm" icon="beaker">
            Lab Tests ({{ $patient->labOrders->count() }})
        </flux:button>
        <flux:button wire:click="$set('activeTab', 'rx')" variant="{{ $activeTab === 'rx' ? 'filled' : 'ghost' }}" size="sm" icon="clipboard-document-list">
            Prescriptions ({{ $patient->prescriptions->count() }})
        </flux:button>
        <flux:button wire:click="$set('activeTab', 'admissions')" variant="{{ $activeTab === 'admissions' ? 'filled' : 'ghost' }}" size="sm" icon="building-office-2">
            Inpatient ADT ({{ $patient->admissions->count() }})
        </flux:button>
    </div>

    @if ($activeTab === 'wallet')

    <!-- Patient Wallet Transactions Table (Page 3 & Page 4 Requirements) -->
    <flux:card class="p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <flux:heading size="lg">Wallet Activity & Transactions</flux:heading>
                <flux:subheading>Full history of funds credited into patient wallet and debits for hospital services</flux:subheading>
            </div>
            <div class="text-xs text-zinc-500">
                Total Transactions: {{ $patient->transactions->count() }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Transaction ID</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Description</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-right">Balance</th>
                        <th class="py-3 px-4">Staff / Processed By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($patient->transactions as $txn)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3.5 px-4 whitespace-nowrap text-zinc-500">
                                {{ $txn->created_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $txn->transaction_id }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($txn->type === 'credit')
                                    <flux:badge color="green" size="sm">Credit</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">Debit</flux:badge>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $txn->description }}
                                @if ($txn->reference)
                                    <span class="text-xs text-zinc-400 block">Ref: {{ $txn->reference }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold {{ $txn->type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $txn->type === 'credit' ? '+' : '-' }}{{ $txn->formatted_amount }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $txn->formatted_patient_balance ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-500">
                                {{ $txn->processor->name ?? 'System' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-zinc-500">
                                No wallet transactions recorded yet. Click "Fund Wallet" to add initial deposit.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
    @elseif ($activeTab === 'ehr')
        <!-- Clinical EHR Tab -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Clinical Visit Notes & Consultations</flux:heading>
                    <flux:subheading>Full physician diagnoses, ICD-10 codings, and treatment plans</flux:subheading>
                </div>
                <flux:button href="{{ route('patients.treatment-record', ['patient' => $patient]) }}" size="sm" variant="primary" icon="pencil-square" wire:navigate>
                    Open Digital EHR Chart
                </flux:button>
            </div>

            <div class="space-y-4">
                @forelse ($patient->healthRecords as $rec)
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <flux:badge color="blue" size="sm">{{ ucfirst($rec->visit_type) }}</flux:badge>
                                @if ($rec->icd_code)
                                    <span class="font-mono text-xs font-bold bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 rounded">ICD: {{ $rec->icd_code }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-zinc-400">{{ $rec->created_at->format('d M Y, h:i A') }} • Dr. {{ $rec->doctor?->name ?? 'Physician' }}</span>
                        </div>
                        <h4 class="font-bold text-base text-zinc-900 dark:text-zinc-100 mt-2">{{ $rec->diagnosis }}</h4>
                        <p class="text-xs text-zinc-600 dark:text-zinc-300 mt-1"><strong>Complaint:</strong> {{ $rec->chief_complaint }}</p>
                        @if ($rec->treatment_plan)
                            <p class="text-xs text-zinc-600 dark:text-zinc-300 mt-1"><strong>Plan:</strong> {{ $rec->treatment_plan }}</p>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center text-zinc-500 text-sm">No clinical consultation notes recorded.</div>
                @endforelse
            </div>
        </flux:card>
    @elseif ($activeTab === 'vitals')
        <!-- Vitals History Tab -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Vital Signs Flowsheet</flux:heading>
                    <flux:subheading>Chronological log of physiological parameters</flux:subheading>
                </div>
                <flux:button href="{{ route('patients.treatment-record', ['patient' => $patient]) }}" size="sm" variant="primary" wire:navigate>
                    Record Vitals
                </flux:button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="py-3 px-4">Recorded At</th>
                            <th class="py-3 px-4">BP</th>
                            <th class="py-3 px-4">Temp</th>
                            <th class="py-3 px-4">Pulse</th>
                            <th class="py-3 px-4">SpO2</th>
                            <th class="py-3 px-4">Resp Rate</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Nurse / Staff</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60 font-mono text-xs">
                        @forelse ($patient->vitals as $vit)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                <td class="py-3 px-4 text-zinc-500">{{ $vit->recorded_at->format('d M Y, h:i A') }}</td>
                                <td class="py-3 px-4 font-bold text-zinc-900 dark:text-zinc-100">{{ $vit->blood_pressure ?? '—' }}</td>
                                <td class="py-3 px-4">{{ $vit->temperature ? $vit->temperature.'°C' : '—' }}</td>
                                <td class="py-3 px-4">{{ $vit->pulse_rate ? $vit->pulse_rate.' bpm' : '—' }}</td>
                                <td class="py-3 px-4">{{ $vit->spo2 ? $vit->spo2.'%' : '—' }}</td>
                                <td class="py-3 px-4">{{ $vit->respiratory_rate ? $vit->respiratory_rate.' bpm' : '—' }}</td>
                                <td class="py-3 px-4 font-sans">
                                    <flux:badge color="{{ $vit->status_flag === 'critical' ? 'red' : ($vit->status_flag === 'guarded' ? 'amber' : 'green') }}" size="sm">
                                        {{ ucfirst($vit->status_flag) }}
                                    </flux:badge>
                                </td>
                                <td class="py-3 px-4 font-sans text-zinc-600 dark:text-zinc-300">{{ $vit->recordedBy?->name ?? 'Nurse' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-8 text-center text-zinc-500 font-sans">No vital signs logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @elseif ($activeTab === 'lab')
        <!-- Lab Tests Tab -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Diagnostic Laboratory Investigations</flux:heading>
                    <flux:subheading>Pathology, hematology, and biochemical test results</flux:subheading>
                </div>
                <flux:button href="{{ route('lab.orders') }}" size="sm" variant="primary" wire:navigate>
                    Laboratory Bench
                </flux:button>
            </div>

            <div class="space-y-4">
                @forelse ($patient->labOrders as $lo)
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-bold text-zinc-800 dark:text-zinc-200">{{ $lo->order_number }} • {{ $lo->test_name }}</span>
                            <flux:badge color="{{ $lo->status === 'completed' ? 'green' : 'amber' }}" size="sm">{{ ucfirst($lo->status) }}</flux:badge>
                        </div>
                        @if ($lo->results->count() > 0)
                            <div class="mt-3 bg-white dark:bg-zinc-800 rounded-lg p-3 space-y-2 border border-zinc-200 dark:border-zinc-700 text-xs">
                                @foreach ($lo->results as $r)
                                    <div class="flex items-center justify-between">
                                        <span><strong>{{ $r->parameter_name }}:</strong> <span class="font-mono font-bold {{ $r->flag === 'critical' ? 'text-red-600' : '' }}">{{ $r->result_value }} {{ $r->unit }}</span> (Ref: {{ $r->reference_range ?? '—' }})</span>
                                        <flux:badge color="{{ $r->flag === 'critical' ? 'red' : ($r->flag === 'abnormal' ? 'amber' : 'green') }}" size="sm">{{ ucfirst($r->flag) }}</flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center text-zinc-500 text-sm">No lab orders logged.</div>
                @endforelse
            </div>
        </flux:card>
    @elseif ($activeTab === 'rx')
        <!-- Prescriptions Tab -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Prescription & Medication History</flux:heading>
                <flux:button href="{{ route('pharmacy.prescriptions') }}" size="sm" variant="primary" wire:navigate>
                    Pharmacy Dispensing
                </flux:button>
            </div>

            <div class="space-y-4">
                @forelse ($patient->prescriptions as $rx)
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700">
                            <span class="font-mono font-bold">{{ $rx->prescription_number }}</span>
                            <flux:badge color="{{ $rx->status === 'dispensed' ? 'green' : 'amber' }}" size="sm">{{ ucfirst($rx->status) }}</flux:badge>
                        </div>
                        <div class="mt-2 space-y-2">
                            @foreach ($rx->items as $it)
                                <div class="p-2 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                                    <div>
                                        <strong class="text-zinc-900 dark:text-zinc-100">{{ $it->drug_name }}</strong> ({{ $it->dosage }})
                                        <span class="text-zinc-500 block">{{ $it->frequency }} • {{ $it->duration }}</span>
                                    </div>
                                    <span class="font-mono font-bold">Qty: {{ $it->quantity }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-zinc-500 text-sm">No prescriptions issued for this patient.</div>
                @endforelse
            </div>
        </flux:card>
    @elseif ($activeTab === 'admissions')
        <!-- Admissions Tab -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Admission, Discharge & Transfer (ADT) History</flux:heading>
                <flux:button href="{{ route('wards.index') }}" size="sm" variant="primary" wire:navigate>
                    Wards & Beds
                </flux:button>
            </div>

            <div class="space-y-4">
                @forelse ($patient->admissions as $adm)
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700">
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $adm->ward->name }} (Bed {{ $adm->bed->bed_number }})</span>
                            <flux:badge color="{{ $adm->status === 'admitted' ? 'red' : 'green' }}" size="sm">{{ ucfirst($adm->status) }}</flux:badge>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-zinc-600 dark:text-zinc-400">
                            <div>Admitted: <strong>{{ $adm->admission_date->format('d M Y, h:i A') }}</strong></div>
                            <div>Discharged: <strong>{{ $adm->discharge_date ? $adm->discharge_date->format('d M Y, h:i A') : 'Still Inpatient' }}</strong></div>
                        </div>
                        <div class="mt-2 text-zinc-700 dark:text-zinc-300"><strong>Reason:</strong> {{ $adm->reason }}</div>
                    </div>
                @empty
                    <div class="py-8 text-center text-zinc-500 text-sm">No ward admission records.</div>
                @endforelse
            </div>
        </flux:card>
    @endif

    <!-- Fund Wallet Modal (Page 1 & 4 Requirements) -->
    <flux:modal wire:model="showFundModal" class="md:w-[28rem]">
        <form wire:submit="processWalletFunding" class="space-y-5">
            <div>
                <flux:heading size="lg">Fund Patient Wallet</flux:heading>
                <flux:subheading>Transfer or deposit funds into {{ $patient->full_name }}'s wallet account ({{ $patient->wallet->account_number }}).</flux:subheading>
            </div>

            <div class="p-3 bg-blue-50 dark:bg-blue-950/30 rounded-lg border border-blue-200 dark:border-blue-900 text-sm">
                <span class="text-xs text-blue-700 dark:text-blue-300 uppercase block font-semibold">Current Wallet Balance</span>
                <div class="text-xl font-bold text-blue-900 dark:text-blue-100 mt-0.5">
                    {{ $patient->wallet->formatted_balance }}
                </div>
            </div>

            <flux:field>
                <flux:label>Amount to Credit (₦) <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="fundAmount" type="number" step="0.01" min="1" placeholder="e.g. 50000" required />
                <flux:error name="fundAmount" />
            </flux:field>

            <!-- Quick preset buttons -->
            <div class="flex items-center gap-2">
                <button type="button" wire:click="$set('fundAmount', '10000')" class="px-2.5 py-1 text-xs rounded border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    +₦10,000
                </button>
                <button type="button" wire:click="$set('fundAmount', '20000')" class="px-2.5 py-1 text-xs rounded border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    +₦20,000
                </button>
                <button type="button" wire:click="$set('fundAmount', '50000')" class="px-2.5 py-1 text-xs rounded border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    +₦50,000
                </button>
                <button type="button" wire:click="$set('fundAmount', '100000')" class="px-2.5 py-1 text-xs rounded border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    +₦100,000
                </button>
            </div>

            <flux:field>
                <flux:label>Description / Deposit Method <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="fundDescription" placeholder="e.g. Wallet Funding, Cash Deposit, POS Transfer" required />
                <flux:error name="fundDescription" />
            </flux:field>

            <flux:field>
                <flux:label>Reference Number (Optional)</flux:label>
                <flux:input wire:model="fundReference" placeholder="e.g. BANK-TRF-98234" />
                <flux:error name="fundReference" />
            </flux:field>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showFundModal', false)" variant="ghost" type="button">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                    Confirm & Fund Wallet
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Make Payment Modal (Page 3 Requirements) -->
    <flux:modal wire:model="showPayModal" class="md:w-[30rem]">
        <form wire:submit="processServicePayment" class="space-y-5">
            <div>
                <flux:heading size="lg">Pay for Hospital Service</flux:heading>
                <flux:subheading>Amount will be deducted from patient wallet and credited to hospital main wallet.</flux:subheading>
            </div>

            <div class="p-3 bg-zinc-50 dark:bg-zinc-800/80 rounded-lg border border-zinc-200 dark:border-zinc-700 flex items-center justify-between text-sm">
                <div>
                    <span class="text-xs text-zinc-500 block uppercase">Available Patient Balance</span>
                    <span class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $patient->wallet->formatted_balance }}</span>
                </div>
                <div class="text-right">
                    <span class="text-xs text-zinc-500 block uppercase">Wallet Account</span>
                    <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $patient->wallet->account_number }}</span>
                </div>
            </div>

            <flux:field>
                <flux:label>Hospital Service <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model.live="selectedServiceId" required>
                    @foreach (\App\Models\HospitalService::active()->get() as $svc)
                        <flux:select.option value="{{ $svc->id }}">
                            {{ $svc->name }} ({{ $svc->formatted_default_amount ?? 'Custom' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedServiceId" />
            </flux:field>

            <flux:field>
                <flux:label>Payment Amount (₦) <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="payAmount" type="number" step="0.01" min="1" placeholder="e.g. 20000" required />
                <flux:error name="payAmount" />
            </flux:field>

            @if ((float)$payAmount > (float)$patient->wallet->balance)
                <div class="p-3 bg-red-50 dark:bg-red-950/40 rounded-lg border border-red-200 dark:border-red-900 text-red-700 dark:text-red-400 text-xs">
                    Warning: Requested amount (₦{{ number_format((float)$payAmount, 2) }}) exceeds available patient balance ({{ $patient->wallet->formatted_balance }}). Please fund wallet first.
                </div>
            @endif

            <flux:field>
                <flux:label>Description / Note</flux:label>
                <flux:input wire:model="payDescription" placeholder="e.g. Laboratory, Full blood count" />
                <flux:error name="payDescription" />
            </flux:field>

            <flux:field>
                <flux:label>Payment Reference / Bill ID (Optional)</flux:label>
                <flux:input wire:model="payReference" placeholder="e.g. LAB-INV-0091" />
                <flux:error name="payReference" />
            </flux:field>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showPayModal', false)" variant="ghost" type="button">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" :disabled="(float)$payAmount > (float)$patient->wallet->balance">
                    Confirm & Complete Payment
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
