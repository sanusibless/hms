<?php

use App\Models\HospitalService;
use App\Models\HospitalWallet;
use App\Models\Transaction;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Hospital Main Wallet - Hospital Payment App')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $service_id = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedServiceId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $mainWallet = HospitalWallet::getSingleton();

        // Main wallet query: All service payments credited to hospital wallet
        $query = Transaction::with(['patient', 'service', 'processor'])
            ->whereNotNull('hospital_wallet_id')
            ->latest();

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('transaction_id', 'like', $term)
                    ->orWhere('service_name', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhereHas('patient', function ($pq) use ($term) {
                        $pq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('file_number', 'like', $term);
                    });
            });
        }

        if ($this->service_id) {
            $query->where('service_id', $this->service_id);
        }

        $totalPaymentsCount = Transaction::whereNotNull('hospital_wallet_id')->count();

        return view('pages.wallet.⚡main', [
            'wallet' => $mainWallet,
            'transactions' => $query->paginate(15),
            'totalPaymentsCount' => $totalPaymentsCount,
            'services' => HospitalService::active()->get(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Hospital Main Wallet</flux:heading>
            <flux:subheading>Central revenue account receiving payments for hospital services from patient wallets</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('payments.create') }}" icon="credit-card" variant="primary" wire:navigate>
                Make Service Payment
            </flux:button>
        </div>
    </div>

    <!-- Summary Metrics (Page 4 Requirements) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Current Balance -->
        <flux:card class="p-5 bg-gradient-to-br from-green-900 to-zinc-900 text-white shadow-md">
            <div class="flex items-center justify-between text-green-200">
                <span class="text-xs font-semibold uppercase tracking-wider">Current Balance</span>
                <flux:icon name="banknotes" class="size-5" />
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-white">
                    {{ $wallet->formatted_balance }}
                </div>
                <div class="text-xs text-green-300 mt-1">Available Hospital Funds</div>
            </div>
        </flux:card>

        <!-- Total Amount Received -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Amount Received</span>
                <flux:icon name="arrow-down-tray" class="size-5 text-blue-500" />
            </div>
            <div class="mt-4">
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ $wallet->formatted_total_received }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Cumulative service revenue</div>
            </div>
        </flux:card>

        <!-- Total Payments -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Payments</span>
                <flux:icon name="check-circle" class="size-5 text-indigo-500" />
            </div>
            <div class="mt-4">
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ number_format($totalPaymentsCount) }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Settled medical bills</div>
            </div>
        </flux:card>

        <!-- Transaction Count -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Transaction Count</span>
                <flux:icon name="receipt-percent" class="size-5 text-teal-500" />
            </div>
            <div class="mt-4">
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ number_format($transactions->total()) }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Active ledger entries</div>
            </div>
        </flux:card>
    </div>

    <!-- Filter & Search -->
    <flux:card class="p-4">
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="w-full sm:max-w-md">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by Transaction ID, Patient Name, File No..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>
            <div class="w-full sm:w-64">
                <flux:select wire:model.live="service_id">
                    <flux:select.option value="">All Services</flux:select.option>
                    @foreach ($services as $svc)
                        <flux:select.option value="{{ $svc->id }}">{{ $svc->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </flux:card>

    <!-- Main Wallet Transactions Table (Page 4 Requirements) -->
    <flux:card class="p-0 overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
            <div>
                <flux:heading size="lg">Main Wallet Transactions Table</flux:heading>
                <flux:subheading>All incoming service payment transactions credited from patient wallets</flux:subheading>
            </div>
            <span class="text-xs text-zinc-400 font-mono">Wallet No: {{ $wallet->account_number }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Transaction ID</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Patient Name</th>
                        <th class="py-3 px-4">Service</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4">Transaction Type</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Staff</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($transactions as $txn)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3.5 px-4 font-mono font-semibold text-xs text-zinc-800 dark:text-zinc-200">
                                {{ $txn->transaction_id }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap text-zinc-500">
                                {{ $txn->created_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($txn->patient)
                                    <a href="{{ route('patients.show', $txn->patient) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline" wire:navigate>
                                        {{ $txn->patient->full_name }}
                                    </a>
                                    <span class="block text-xs text-zinc-400 font-mono">{{ $txn->patient->file_number }}</span>
                                @else
                                    <span class="text-zinc-400">Direct Operation</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $txn->service_name ?? $txn->description }}
                                @if ($txn->reference)
                                    <span class="text-xs text-zinc-400 block font-mono">Ref: {{ $txn->reference }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-green-600 dark:text-green-400 font-mono">
                                +{{ $txn->formatted_amount }}
                            </td>
                            <td class="py-3.5 px-4">
                                <flux:badge color="blue" size="sm">Service Payment</flux:badge>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <flux:badge color="green" size="sm">{{ ucfirst($txn->status) }}</flux:badge>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-500">
                                {{ $txn->processor->name ?? 'System' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-zinc-500">
                                No hospital payments recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
            {{ $transactions->links() }}
        </div>
    </flux:card>
</div>
