<?php

use App\Models\Transaction;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Transactions Ledger - Hospital Payment App')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all'; // all, payments, funding

    #[Url]
    public string $period = 'all'; // all, today, week, month

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Transaction::with(['patient.wallet', 'service', 'processor'])->latest();

        if ($this->filter === 'payments') {
            $query->where('type', 'debit');
        } elseif ($this->filter === 'funding') {
            $query->where('type', 'credit');
        }

        if ($this->period === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($this->period === 'week') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($this->period === 'month') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('transaction_id', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('service_name', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhereHas('patient', function ($pq) use ($term) {
                        $pq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('file_number', 'like', $term);
                    });
            });
        }

        return view('pages.transactions.⚡index', [
            'transactions' => $query->paginate(15),
            'totalCreditSum' => Transaction::where('type', 'credit')->sum('amount'),
            'totalDebitSum' => Transaction::where('type', 'debit')->sum('amount'),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Transactions Ledger</flux:heading>
            <flux:subheading>Comprehensive financial audit trail of all patient wallet fundings and hospital payments</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('payments.create') }}" icon="credit-card" variant="primary" wire:navigate>
                Make Payment
            </flux:button>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <flux:card class="p-4">
            <span class="text-xs text-zinc-500 uppercase font-medium">Total Hospital Payments Received</span>
            <div class="text-2xl font-extrabold text-green-600 dark:text-green-400 mt-1">
                ₦{{ number_format((float)$totalDebitSum, 2) }}
            </div>
            <div class="text-xs text-zinc-400 mt-1">Paid services from patient wallets</div>
        </flux:card>

        <flux:card class="p-4">
            <span class="text-xs text-zinc-500 uppercase font-medium">Total Patient Wallet Funding</span>
            <div class="text-2xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">
                ₦{{ number_format((float)$totalCreditSum, 2) }}
            </div>
            <div class="text-xs text-zinc-400 mt-1">Deposits received into patient accounts</div>
        </flux:card>

        <flux:card class="p-4">
            <span class="text-xs text-zinc-500 uppercase font-medium">Total Recorded Transactions</span>
            <div class="text-2xl font-extrabold text-zinc-900 dark:text-zinc-100 mt-1">
                {{ number_format($transactions->total()) }}
            </div>
            <div class="text-xs text-zinc-400 mt-1">All debit & credit entries</div>
        </flux:card>
    </div>

    <!-- Filters & Search -->
    <flux:card class="p-4 space-y-4">
        <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="w-full md:max-w-md">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by Transaction ID, Patient, Service, Reference..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <flux:radio.group wire:model.live="filter" variant="segmented">
                    <flux:radio value="all" label="All Types" />
                    <flux:radio value="payments" label="Hospital Payments" />
                    <flux:radio value="funding" label="Wallet Funding" />
                </flux:radio.group>

                <flux:select wire:model.live="period" class="w-36">
                    <flux:select.option value="all">All Time</flux:select.option>
                    <flux:select.option value="today">Today</flux:select.option>
                    <flux:select.option value="week">Past 7 Days</flux:select.option>
                    <flux:select.option value="month">Past 30 Days</flux:select.option>
                </flux:select>
            </div>
        </div>
    </flux:card>

    <!-- Transactions Table -->
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Transaction ID</th>
                        <th class="py-3 px-4">Date & Time</th>
                        <th class="py-3 px-4">Patient</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Description / Service</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-right">Patient Balance</th>
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
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($txn->type === 'credit')
                                    <flux:badge color="green" size="sm">Credit (Funding)</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">Debit (Payment)</flux:badge>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $txn->description }}
                                @if ($txn->reference)
                                    <span class="text-xs text-zinc-400 block font-mono">Ref: {{ $txn->reference }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold font-mono {{ $txn->type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-zinc-900 dark:text-zinc-100' }}">
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
                            <td colspan="8" class="py-10 text-center text-zinc-500">
                                No transactions match the current filter criteria.
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
