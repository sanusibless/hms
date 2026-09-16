<?php

use App\Models\HospitalService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Billing & Invoices - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    // Create Invoice Modal
    public bool $showCreateModal = false;
    public ?int $patient_id = null;
    public ?int $selected_service_id = null;
    public string $item_name = 'Doctor Consultation';
    public string $item_amount = '5000';
    public string $discount_amount = '0';
    public string $waiver_amount = '0';
    public string $payment_method = 'wallet';
    public string $notes = '';

    public function openCreateModal(?int $patientId = null): void
    {
        $this->patient_id = $patientId ?? Patient::first()?->id;
        $firstService = HospitalService::first();
        if ($firstService) {
            $this->selected_service_id = $firstService->id;
            $this->item_name = $firstService->name;
            $this->item_amount = (string)($firstService->default_amount ?? 5000);
        }
        $this->discount_amount = '0';
        $this->waiver_amount = '0';
        $this->payment_method = 'wallet';
        $this->notes = '';
        $this->showCreateModal = true;
    }

    public function updatedSelectedServiceId($val): void
    {
        $service = HospitalService::find($val);
        if ($service) {
            $this->item_name = $service->name;
            $this->item_amount = (string)($service->default_amount ?? 0);
        }
    }

    public function createInvoice(): void
    {
        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'item_name' => 'required|string|max:150',
            'item_amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'waiver_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ]);

        $subtotal = (float)$this->item_amount;
        $discount = (float)$this->discount_amount;
        $waiver = (float)$this->waiver_amount;
        $total = max(0, $subtotal - $discount - $waiver);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'patient_id' => $this->patient_id,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'waiver_amount' => $waiver,
            'total_amount' => $total,
            'paid_amount' => $total, // Auto-marked as cleared or partial
            'balance_due' => 0.00,
            'status' => 'paid',
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_by' => Auth::id(),
        ]);

        $invoice->items()->create([
            'service_id' => $this->selected_service_id,
            'item_name' => $this->item_name,
            'quantity' => 1,
            'unit_price' => $subtotal,
            'total_price' => $subtotal,
        ]);

        AuditService::log('create', 'billing', (string)$invoice->id, "Generated hospital invoice {$invoice->invoice_number} for ₦" . number_format($total, 2));

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: "Invoice {$invoice->invoice_number} generated for {$invoice->formatted_total}.");
    }

    public function render()
    {
        $query = Invoice::with(['patient', 'creator', 'items.service'])->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', "%{$this->search}%")
                  ->orWhereHas('patient', function ($p) {
                      $p->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('file_number', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return view('pages.billing.⚡invoices', [
            'invoices' => $query->paginate(10),
            'services' => HospitalService::active()->get(),
            'patients' => Patient::orderBy('first_name')->take(50)->get(),
            'totalBilled' => Invoice::sum('total_amount'),
            'totalCollected' => Invoice::sum('paid_amount'),
            'totalDiscounts' => Invoice::sum('discount_amount') + Invoice::sum('waiver_amount'),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Billing, Invoices & Charge Capture</flux:heading>
            <flux:subheading>Manage consultation, procedure, laboratory charges, discounts, and payment receipts</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('billing.claims') }}" variant="filled" icon="shield-check" wire:navigate>
                Insurance Claims
            </flux:button>
            <flux:button href="{{ route('payments.create') }}" variant="filled" icon="credit-card" wire:navigate>
                Wallet Payment
            </flux:button>
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                Generate Invoice
            </flux:button>
        </div>
    </div>

    <!-- Financial Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 border-l-4 border-blue-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Total Invoiced Amount</div>
            <div class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">₦{{ number_format((float)$totalBilled, 2) }}</div>
            <div class="text-xs text-zinc-400 mt-1">Gross clinical & hospital charges</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-green-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Total Payments Collected</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">₦{{ number_format((float)$totalCollected, 2) }}</div>
            <div class="text-xs text-zinc-400 mt-1">Settled via wallet, cash, pos & insurance</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-purple-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Total Discounts & Waivers</div>
            <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">₦{{ number_format((float)$totalDiscounts, 2) }}</div>
            <div class="text-xs text-zinc-400 mt-1">Subsidies and fee exemptions</div>
        </flux:card>
    </div>

    <!-- Invoices Table -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search invoice #, patient..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="statusFilter" class="w-44">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    <flux:select.option value="paid">Paid</flux:select.option>
                    <flux:select.option value="unpaid">Unpaid</flux:select.option>
                    <flux:select.option value="partially_paid">Partially Paid</flux:select.option>
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Total invoices: {{ $invoices->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Invoice #</th>
                        <th class="py-3 px-4">Patient</th>
                        <th class="py-3 px-4">Items / Description</th>
                        <th class="py-3 px-4">Method</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4 font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $inv->invoice_number }}
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('patients.show', ['patient' => $inv->patient_id]) }}" class="font-medium text-blue-600 hover:underline">
                                    {{ $inv->patient->full_name }}
                                </a>
                                <span class="block text-xs text-zinc-400 font-mono">{{ $inv->patient->file_number }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $inv->items->pluck('item_name')->join(', ') ?: 'Hospital Charges' }}
                                </span>
                                @if ($inv->discount_amount > 0 || $inv->waiver_amount > 0)
                                    <span class="block text-xs text-purple-600">Discount: ₦{{ number_format((float)($inv->discount_amount + $inv->waiver_amount), 2) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <flux:badge color="zinc" size="sm">{{ ucfirst($inv->payment_method ?? 'wallet') }}</flux:badge>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $inv->formatted_total }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($inv->status === 'paid')
                                    <flux:badge color="green" size="sm">Paid</flux:badge>
                                @elseif ($inv->status === 'partially_paid')
                                    <flux:badge color="amber" size="sm">Partial</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Unpaid</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right text-xs text-zinc-400">
                                {{ $inv->created_at->format('d M Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">No invoices recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </flux:card>

    <!-- Create Invoice Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[500px]">
        <form wire:submit.prevent="createInvoice" class="space-y-4">
            <div>
                <flux:heading size="lg">Generate Hospital Invoice</flux:heading>
                <flux:subheading>Capture service charges, apply discounts/waivers, and log receipt</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Select Patient</flux:label>
                <flux:select wire:model="patient_id" required>
                    @foreach ($patients as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->full_name }} ({{ $p->file_number }})</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Predefined Service</flux:label>
                    <flux:select wire:model.live="selected_service_id">
                        @foreach ($services as $s)
                            <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Payment Method</flux:label>
                    <flux:select wire:model="payment_method">
                        <flux:select.option value="wallet">Patient Wallet</flux:select.option>
                        <flux:select.option value="cash">Cash</flux:select.option>
                        <flux:select.option value="pos">POS Terminal</flux:select.option>
                        <flux:select.option value="transfer">Bank Transfer</flux:select.option>
                        <flux:select.option value="insurance">Insurance / HMO</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Item / Charge Description</flux:label>
                <flux:input wire:model="item_name" required />
            </flux:field>

            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Amount (₦)</flux:label>
                    <flux:input type="number" min="0" step="0.01" wire:model="item_amount" required />
                </flux:field>

                <flux:field>
                    <flux:label>Discount (₦)</flux:label>
                    <flux:input type="number" min="0" step="0.01" wire:model="discount_amount" />
                </flux:field>

                <flux:field>
                    <flux:label>Waiver (₦)</flux:label>
                    <flux:input type="number" min="0" step="0.01" wire:model="waiver_amount" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Billing Notes</flux:label>
                <flux:input wire:model="notes" placeholder="e.g. Approved fee discount or HMO authorization code" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Generate & Process</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
