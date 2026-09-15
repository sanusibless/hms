<?php

use App\Models\HospitalWallet;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\Wallet;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard - Hospital Payment App')] class extends Component {
    #[Computed]
    public function totalPatients(): int
    {
        return Patient::count();
    }

    #[Computed]
    public function totalWallets(): int
    {
        return Wallet::count();
    }

    #[Computed]
    public function totalWalletBalance(): string
    {
        $sum = Wallet::sum('balance');
        return '₦' . number_format((float)$sum, 2);
    }

    #[Computed]
    public function hospitalWallet(): HospitalWallet
    {
        return HospitalWallet::getSingleton();
    }

    #[Computed]
    public function todayPayments(): string
    {
        $sum = Transaction::where('type', 'debit')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        return '₦' . number_format((float)$sum, 2);
    }

    #[Computed]
    public function recentTransactions()
    {
        return Transaction::with(['patient', 'service'])
            ->latest()
            ->take(8)
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Page Header & Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Hospital Payment Dashboard</flux:heading>
            <flux:subheading>Welcome back, {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('patients.index') }}" icon="user-plus" variant="filled" wire:navigate>
                New Patient
            </flux:button>
            <flux:button href="{{ route('payments.create') }}" icon="credit-card" variant="primary" wire:navigate>
                Make Payment
            </flux:button>
        </div>
    </div>

    <!-- Summary Cards Grid (Page 5 Requirements) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <!-- Total Patients -->
        <flux:card class="p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-sm font-medium">Total Patients</span>
                <flux:icon name="users" class="size-5 text-blue-500" />
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ number_format($this->totalPatients) }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Registered hospital files</div>
            </div>
        </flux:card>

        <!-- Total Wallets -->
        <flux:card class="p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-sm font-medium">Total Wallets</span>
                <flux:icon name="identification" class="size-5 text-indigo-500" />
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ number_format($this->totalWallets) }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Active patient accounts</div>
            </div>
        </flux:card>

        <!-- Total Patient Wallet Balance -->
        <flux:card class="p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-sm font-medium">Total Wallet Balance</span>
                <flux:icon name="banknotes" class="size-5 text-teal-500" />
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ $this->totalWalletBalance }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Total patient deposit pool</div>
            </div>
        </flux:card>

        <!-- Hospital Wallet Balance -->
        <flux:card class="p-4 flex flex-col justify-between border-green-200 dark:border-green-900 bg-green-50/40 dark:bg-green-950/20">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-sm font-semibold text-green-700 dark:text-green-400">Hospital Wallet</span>
                <flux:icon name="wallet" class="size-5 text-green-600 dark:text-green-400" />
            </div>
            <div class="mt-3">
                <div class="text-2xl font-extrabold text-green-700 dark:text-green-300">
                    {{ $this->hospitalWallet->formatted_balance }}
                </div>
                <div class="text-xs text-green-600 dark:text-green-500 mt-1">Total Revenue Collected</div>
            </div>
        </flux:card>

        <!-- Today's Payments -->
        <flux:card class="p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-sm font-medium">Today's Payments</span>
                <flux:icon name="calendar" class="size-5 text-amber-500" />
            </div>
            <div class="mt-3">
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ $this->todayPayments }}
                </div>
                <div class="text-xs text-zinc-500 mt-1">Paid services today</div>
            </div>
        </flux:card>
    </div>

    <!-- Quick Flow Banner matching Page 5 -->
    <flux:card class="p-5 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-zinc-900 dark:to-zinc-800 border-blue-100 dark:border-zinc-700">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <flux:heading size="base" class="text-blue-900 dark:text-blue-300 font-semibold">Standard Patient Payment Journey</flux:heading>
                <flux:text class="text-sm mt-1 text-blue-800/80 dark:text-zinc-300">
                    Create Patient → Auto Wallet Generated → Fund Patient Wallet → Pay for Hospital Service → Main Wallet Credited
                </flux:text>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <flux:button href="{{ route('patients.index') }}" size="sm" variant="outline" wire:navigate>
                    Manage Patients
                </flux:button>
                <flux:button href="{{ route('wallet.main') }}" size="sm" variant="outline" wire:navigate>
                    Main Wallet Ledger
                </flux:button>
            </div>
        </div>
    </flux:card>

    <!-- Recent Transactions Table (Page 5) -->
    <flux:card class="p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <flux:heading size="lg">Recent Transactions</flux:heading>
                <flux:subheading>Latest patient wallet fundings and hospital payments</flux:subheading>
            </div>
            <flux:button href="{{ route('transactions.index') }}" variant="ghost" size="sm" icon-trailing="arrow-right" wire:navigate>
                View all
            </flux:button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Transaction ID</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Patient</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Description / Service</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($this->recentTransactions as $txn)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4 font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $txn->transaction_id }}
                            </td>
                            <td class="py-3 px-4 text-zinc-500 whitespace-nowrap">
                                {{ $txn->created_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($txn->patient)
                                    <a href="{{ route('patients.show', $txn->patient) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline" wire:navigate>
                                        {{ $txn->patient->full_name }}
                                    </a>
                                    <span class="block text-xs text-zinc-400">{{ $txn->patient->file_number }}</span>
                                @else
                                    <span class="text-zinc-400">Direct Operation</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($txn->type === 'credit')
                                    <flux:badge color="green" size="sm" inset="top bottom">Credit (Funding)</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm" inset="top bottom">Debit (Payment)</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $txn->description }}
                                @if ($txn->reference)
                                    <span class="text-xs text-zinc-400 block">Ref: {{ $txn->reference }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-semibold {{ $txn->type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $txn->type === 'credit' ? '+' : '-' }}{{ $txn->formatted_amount }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <flux:badge color="zinc" size="sm">{{ ucfirst($txn->status) }}</flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">
                                No transactions recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
