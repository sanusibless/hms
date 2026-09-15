<?php

use App\Models\HospitalService;
use App\Models\HospitalWallet;
use App\Models\Patient;
use App\Models\Transaction;
use App\Services\PaymentService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Make Payment - Hospital Payment App')] class extends Component {
    #[Url]
    public ?int $patient_id = null;

    public string $patientSearch = '';
    public ?Patient $selectedPatient = null;

    public ?int $serviceId = null;
    public string $amount = '';
    public string $description = '';
    public string $reference = '';

    // Quick funding state if needed
    public bool $showQuickFundModal = false;
    public string $quickFundAmount = '';

    // Receipt Modal state
    public bool $showReceiptModal = false;
    public ?Transaction $completedTransaction = null;

    public function mount(): void
    {
        if ($this->patient_id) {
            $patient = Patient::with('wallet')->find($this->patient_id);
            if ($patient) {
                $this->selectPatient($patient->id);
            }
        }

        $defaultService = HospitalService::active()->first();
        if ($defaultService) {
            $this->serviceId = $defaultService->id;
            $this->amount = (string)($defaultService->default_amount ?? '');
            $this->description = $defaultService->name;
        }
    }

    public function selectPatient(int $id): void
    {
        $this->selectedPatient = Patient::with('wallet')->find($id);
        $this->patient_id = $id;
        $this->patientSearch = '';
    }

    public function clearSelectedPatient(): void
    {
        $this->selectedPatient = null;
        $this->patient_id = null;
    }

    public function updatedServiceId($id): void
    {
        $service = HospitalService::find($id);
        if ($service) {
            $this->amount = (string)($service->default_amount ?? '');
            $this->description = $service->name;
        }
    }

    public function quickFundWallet(PaymentService $paymentService): void
    {
        $this->validate([
            'quickFundAmount' => 'required|numeric|min:1',
        ]);

        if (!$this->selectedPatient) {
            return;
        }

        try {
            $paymentService->fundPatientWallet(
                $this->selectedPatient,
                (float)$this->quickFundAmount,
                'Quick Wallet Deposit',
                'DEP-' . strtoupper(bin2hex(random_bytes(3))),
                Auth::user()
            );

            $this->selectedPatient->load('wallet');
            $this->showQuickFundModal = false;
            $this->reset('quickFundAmount');

            Flux::toast(variant: 'success', text: "Wallet funded successfully! Current balance: " . $this->selectedPatient->wallet->formatted_balance);
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function submitPayment(PaymentService $paymentService): void
    {
        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'serviceId' => 'required|exists:services,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:150',
            'reference' => 'nullable|string|max:100',
        ]);

        $service = HospitalService::findOrFail($this->serviceId);
        $patient = Patient::with('wallet')->findOrFail($this->patient_id);

        try {
            $transaction = $paymentService->payForService(
                $patient,
                $service,
                (float)$this->amount,
                $this->description ?: $service->name,
                $this->reference,
                Auth::user()
            );

            $this->completedTransaction = $transaction->load(['patient.wallet', 'service', 'processor']);
            $this->selectedPatient->load('wallet');
            $this->showReceiptModal = true;

            Flux::toast(variant: 'success', text: "Payment of ₦" . number_format((float)$this->amount, 2) . " processed successfully!");
        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function resetForNextPayment(): void
    {
        $this->showReceiptModal = false;
        $this->completedTransaction = null;
        $this->reset(['amount', 'description', 'reference']);

        $defaultService = HospitalService::active()->first();
        if ($defaultService) {
            $this->serviceId = $defaultService->id;
            $this->amount = (string)($defaultService->default_amount ?? '');
            $this->description = $defaultService->name;
        }
    }

    public function getPatientSearchResultsProperty()
    {
        if (strlen(trim($this->patientSearch)) < 2) {
            return collect();
        }

        $term = '%' . trim($this->patientSearch) . '%';
        return Patient::with('wallet')
            ->where('first_name', 'like', $term)
            ->orWhere('last_name', 'like', $term)
            ->orWhere('file_number', 'like', $term)
            ->orWhere('phone_number', 'like', $term)
            ->orWhereHas('wallet', function ($q) use ($term) {
                $q->where('account_number', 'like', $term);
            })
            ->take(5)
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div>
        <flux:heading size="xl" level="1">Make Hospital Payment</flux:heading>
        <flux:subheading>Debit patient wallet and credit hospital main wallet for medical services</flux:subheading>
    </div>

    <!-- Main Payment Form Card -->
    <flux:card class="p-6">
        <form wire:submit="submitPayment" class="space-y-6">
            <!-- Step 1: Patient Selection -->
            <div>
                <flux:heading size="base" class="text-zinc-800 dark:text-zinc-200 font-semibold mb-2">
                    1. Search & Select Patient
                </flux:heading>

                @if ($selectedPatient)
                    <div class="p-4 rounded-xl border border-blue-200 dark:border-blue-900 bg-blue-50/50 dark:bg-blue-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <flux:avatar :name="$selectedPatient->full_name" size="md" />
                            <div>
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedPatient->full_name }}</div>
                                <div class="text-xs text-zinc-500 font-mono">
                                    File: <span class="text-blue-600 dark:text-blue-400 font-semibold">{{ $selectedPatient->file_number }}</span> |
                                    Wallet: <span class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ $selectedPatient->wallet->account_number }}</span> |
                                    Phone: {{ $selectedPatient->phone_number }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <span class="text-xs uppercase text-zinc-500 block font-medium">Available Balance</span>
                                <span class="text-xl font-extrabold text-zinc-900 dark:text-zinc-100">
                                    {{ $selectedPatient->wallet->formatted_balance }}
                                </span>
                            </div>
                            <flux:button wire:click="clearSelectedPatient" variant="ghost" size="xs" icon="x-mark">
                                Change
                            </flux:button>
                        </div>
                    </div>

                    @if ((float)$selectedPatient->wallet->balance <= 0)
                        <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 rounded-lg text-xs text-amber-800 dark:text-amber-300 flex items-center justify-between">
                            <span>Patient wallet has ₦0.00 balance. Please deposit funds before making payments.</span>
                            <flux:button wire:click="$set('showQuickFundModal', true)" size="xs" variant="filled" type="button">
                                + Fund Wallet
                            </flux:button>
                        </div>
                    @endif
                @else
                    <div class="relative">
                        <flux:input
                            wire:model.live.debounce.250ms="patientSearch"
                            placeholder="Type Patient Name, File Number (e.g. HSP-00125), or Phone..."
                            icon="magnifying-glass"
                        />
                        <flux:error name="patient_id" />

                        @if ($this->patientSearchResults->isNotEmpty())
                            <div class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-700">
                                @foreach ($this->patientSearchResults as $p)
                                    <button
                                        type="button"
                                        wire:click="selectPatient({{ $p->id }})"
                                        class="w-full text-left px-4 py-2.5 hover:bg-blue-50 dark:hover:bg-zinc-700 flex items-center justify-between transition-colors"
                                    >
                                        <div>
                                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $p->full_name }}</div>
                                            <div class="text-xs text-zinc-500 font-mono">File: {{ $p->file_number }} | Wallet: {{ $p->wallet->account_number }}</div>
                                        </div>
                                        <div class="text-right font-mono text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                            {{ $p->wallet->formatted_balance }}
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Step 2: Service & Amount -->
            <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:heading size="base" class="text-zinc-800 dark:text-zinc-200 font-semibold mb-3">
                    2. Service & Billing Details
                </flux:heading>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Hospital Service <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="serviceId" required>
                            @foreach (\App\Models\HospitalService::active()->get() as $svc)
                                <flux:select.option value="{{ $svc->id }}">
                                    {{ $svc->name }} ({{ $svc->department ?? 'General' }} - {{ $svc->formatted_default_amount ?? 'Custom' }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="serviceId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Payment Amount (₦) <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model.live="amount" type="number" step="0.01" min="1" placeholder="e.g. 20000" required />
                        <flux:error name="amount" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <flux:field>
                        <flux:label>Description / Details</flux:label>
                        <flux:input wire:model="description" placeholder="e.g. Laboratory tests, Blood analysis" />
                        <flux:error name="description" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Reference / Invoice No (Optional)</flux:label>
                        <flux:input wire:model="reference" placeholder="e.g. INV-90412" />
                        <flux:error name="reference" />
                    </flux:field>
                </div>
            </div>

            <!-- Calculation & Impact Preview -->
            @if ($selectedPatient && (float)$amount > 0)
                <div class="p-4 rounded-xl border {{ (float)$selectedPatient->wallet->balance >= (float)$amount ? 'border-green-200 bg-green-50/40 dark:border-green-900/60 dark:bg-green-950/20' : 'border-red-200 bg-red-50/40 dark:border-red-900/60 dark:bg-red-950/20' }}">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-xs text-zinc-500 block uppercase">Current Patient Balance</span>
                            <span class="font-bold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedPatient->wallet->formatted_balance }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-zinc-500 block uppercase">Amount to Deduct</span>
                            <span class="font-bold text-red-600 dark:text-red-400 font-mono">-₦{{ number_format((float)$amount, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-zinc-500 block uppercase">Estimated Balance After</span>
                            <span class="font-bold font-mono {{ (float)$selectedPatient->wallet->balance >= (float)$amount ? 'text-green-700 dark:text-green-300' : 'text-red-600' }}">
                                ₦{{ number_format((float)$selectedPatient->wallet->balance - (float)$amount, 2) }}
                            </span>
                        </div>
                    </div>

                    @if ((float)$selectedPatient->wallet->balance < (float)$amount)
                        <div class="mt-3 text-xs text-red-600 dark:text-red-400 flex items-center justify-between">
                            <span>Insufficient funds in patient wallet for this service payment.</span>
                            <flux:button wire:click="$set('showQuickFundModal', true)" size="xs" variant="filled" type="button">
                                Fund Wallet Now
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button href="{{ route('dashboard') }}" variant="ghost" wire:navigate>
                    Cancel
                </flux:button>
                <flux:button
                    variant="primary"
                    type="submit"
                    wire:loading.attr="disabled"
                    :disabled="!$selectedPatient || (float)$amount <= 0 || ((float)($selectedPatient->wallet->balance ?? 0) < (float)$amount)"
                >
                    Confirm & Complete Payment
                </flux:button>
            </div>
        </form>
    </flux:card>

    <!-- Quick Fund Modal -->
    <flux:modal wire:model="showQuickFundModal" class="md:w-[26rem]">
        <form wire:submit="quickFundWallet" class="space-y-4">
            <div>
                <flux:heading size="lg">Quick Fund Patient Wallet</flux:heading>
                <flux:subheading>Add funds instantly to complete this service payment</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Amount to Deposit (₦) <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="quickFundAmount" type="number" step="0.01" min="1" placeholder="e.g. 20000" required />
                <flux:error name="quickFundAmount" />
            </flux:field>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showQuickFundModal', false)" variant="ghost" type="button">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit">
                    Deposit Funds
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Payment Receipt Modal -->
    @if ($completedTransaction)
        <flux:modal wire:model="showReceiptModal" class="md:w-[32rem]">
            <div class="space-y-5">
                <div class="text-center pb-3 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="size-12 rounded-full bg-green-100 dark:bg-green-950/60 text-green-600 dark:text-green-400 mx-auto flex items-center justify-center mb-2">
                        <flux:icon name="check-circle" class="size-8" />
                    </div>
                    <flux:heading size="lg">Payment Successful</flux:heading>
                    <flux:text class="text-xs text-zinc-500 font-mono mt-0.5">Transaction ID: {{ $completedTransaction->transaction_id }}</flux:text>
                </div>

                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Patient Name:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $completedTransaction->patient->full_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Hospital File No:</span>
                        <span class="font-mono text-zinc-900 dark:text-zinc-100">{{ $completedTransaction->patient->file_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Service:</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $completedTransaction->service_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Amount Deducted:</span>
                        <span class="font-bold text-red-600 dark:text-red-400 font-mono">-{{ $completedTransaction->formatted_amount }}</span>
                    </div>
                    <div class="flex justify-between border-t border-zinc-200 dark:border-zinc-700 pt-2">
                        <span class="text-zinc-500">Remaining Wallet Balance:</span>
                        <span class="font-bold text-green-700 dark:text-green-400 font-mono">{{ $completedTransaction->formatted_patient_balance }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Hospital Wallet Credited:</span>
                        <span class="font-bold text-green-700 dark:text-green-400 font-mono">+{{ $completedTransaction->formatted_amount }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-zinc-400 pt-1">
                        <span>Staff: {{ $completedTransaction->processor->name ?? 'System' }}</span>
                        <span>{{ $completedTransaction->created_at->format('d M Y, h:i A') }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <flux:button href="{{ route('patients.show', $completedTransaction->patient) }}" variant="outline" size="sm" wire:navigate>
                        View Patient Profile
                    </flux:button>
                    <flux:button wire:click="resetForNextPayment" variant="primary" size="sm">
                        Make Another Payment
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
