<?php

use App\Models\Drug;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pharmacy Prescriptions Queue - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public function dispensePrescription(int $prescriptionId): void
    {
        $prescription = Prescription::with('items.drug')->findOrFail($prescriptionId);

        if ($prescription->status === 'dispensed') {
            Flux::toast(variant: 'warning', text: 'This prescription has already been dispensed.');
            return;
        }

        // Deduct inventory where drug_id is matched
        foreach ($prescription->items as $item) {
            if ($item->drug) {
                if ($item->drug->stock_quantity < $item->quantity) {
                    Flux::toast(variant: 'danger', text: "Insufficient stock for {$item->drug_name}. Current balance: {$item->drug->stock_quantity}");
                    return;
                }
                $item->drug->decrement('stock_quantity', $item->quantity);
            }
            $item->update(['is_dispensed' => true]);
        }

        $prescription->update([
            'status' => 'dispensed',
            'dispensed_by' => Auth::id(),
            'dispensed_at' => now(),
        ]);

        AuditService::log('dispense', 'pharmacy', (string)$prescription->id, "Dispensed prescription {$prescription->prescription_number} for {$prescription->patient->full_name}");

        Flux::toast(variant: 'success', text: "Prescription {$prescription->prescription_number} dispensed successfully.");
    }

    public function cancelPrescription(int $prescriptionId): void
    {
        $prescription = Prescription::findOrFail($prescriptionId);
        $prescription->update(['status' => 'cancelled']);

        AuditService::log('update', 'pharmacy', (string)$prescription->id, "Cancelled prescription {$prescription->prescription_number}");
        Flux::toast(variant: 'info', text: "Prescription marked as cancelled.");
    }

    public function render()
    {
        $query = Prescription::with(['patient', 'doctor', 'dispenser', 'items.drug'])->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('prescription_number', 'like', "%{$this->search}%")
                  ->orWhere('diagnosis', 'like', "%{$this->search}%")
                  ->orWhereHas('patient', function ($p) {
                      $p->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('file_number', 'like', "%{$this->search}%");
                  })
                  ->orWhereHas('items', function ($i) {
                      $i->where('drug_name', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return view('pages.pharmacy.⚡prescriptions', [
            'prescriptions' => $query->paginate(10),
            'pendingCount' => Prescription::where('status', 'pending')->count(),
            'dispensedCount' => Prescription::where('status', 'dispensed')->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('pharmacy.inventory') }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                    Inventory & Formulary
                </flux:button>
            </div>
            <flux:heading size="xl" level="1" class="mt-1">Prescription Dispensing & Routing Queue</flux:heading>
            <flux:subheading>Manage doctor e-prescriptions, allergy cross-checks, and medication dispensing</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('pharmacy.mar') }}" variant="filled" icon="check-badge" wire:navigate>
                Nurse MAR Log
            </flux:button>
        </div>
    </div>

    <!-- Status Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <flux:card class="p-4 border-l-4 border-amber-500 cursor-pointer" wire:click="$set('statusFilter', 'pending')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Pending Dispensing</div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $pendingCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Awaiting pharmacist verification and dispensing</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-green-500 cursor-pointer" wire:click="$set('statusFilter', 'dispensed')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Completed Dispenses</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">{{ $dispensedCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Stock deducted and released to patient / ward</div>
        </flux:card>
    </div>

    <!-- Prescriptions List -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search RX #, patient, drug, or doctor..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="statusFilter" class="w-48">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    <flux:select.option value="pending">Pending Only</flux:select.option>
                    <flux:select.option value="dispensed">Dispensed Only</flux:select.option>
                    <flux:select.option value="cancelled">Cancelled</flux:select.option>
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Total prescriptions: {{ $prescriptions->total() }}</span>
        </div>

        <div class="space-y-4">
            @forelse ($prescriptions as $rx)
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/60 dark:bg-zinc-800/40">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $rx->prescription_number }}</span>
                            @if ($rx->status === 'dispensed')
                                <flux:badge color="green" size="sm">Dispensed</flux:badge>
                            @elseif ($rx->status === 'cancelled')
                                <flux:badge color="zinc" size="sm">Cancelled</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">Pending Pharmacy</flux:badge>
                            @endif
                        </div>
                        <div class="text-xs text-zinc-500">
                            Prescribed by <strong class="text-zinc-800 dark:text-zinc-200">{{ $rx->doctor->name }}</strong> • {{ $rx->created_at->format('d M Y, h:i A') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                        <!-- Patient Info & Allergies -->
                        <div>
                            <span class="text-xs text-zinc-400 block uppercase font-medium">Patient Details</span>
                            <a href="{{ route('patients.show', ['patient' => $rx->patient_id]) }}" class="font-bold text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $rx->patient->full_name }}
                            </a>
                            <span class="block text-xs font-mono text-zinc-500">{{ $rx->patient->file_number }}</span>

                            @if ($rx->patient->allergies)
                                <div class="mt-2 text-xs bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-400 p-2 rounded border border-red-200 dark:border-red-900/60">
                                    <strong>Allergy Alert:</strong> {{ $rx->patient->allergies }}
                                </div>
                            @endif

                            @if ($rx->diagnosis)
                                <div class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">
                                    <span class="text-zinc-400">Diagnosis:</span> {{ $rx->diagnosis }}
                                </div>
                            @endif
                        </div>

                        <!-- Prescribed Medications Items -->
                        <div class="md:col-span-2">
                            <span class="text-xs text-zinc-400 block uppercase font-medium mb-1.5">Prescribed Items</span>
                            <div class="space-y-2">
                                @foreach ($rx->items as $item)
                                    <div class="p-2.5 rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700/80 flex items-center justify-between text-xs">
                                        <div>
                                            <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $item->drug_name }}</div>
                                            <div class="text-zinc-500 mt-0.5">
                                                <span>{{ $item->dosage }}</span> • 
                                                <span>{{ $item->frequency }}</span> • 
                                                <span>{{ $item->duration }}</span>
                                                @if ($item->route) • <span>{{ $item->route }}</span> @endif
                                            </div>
                                            @if ($item->instructions)
                                                <div class="text-zinc-400 italic mt-0.5">{{ $item->instructions }}</div>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono font-bold text-zinc-800 dark:text-zinc-200">Qty: {{ $item->quantity }}</span>
                                            @if ($item->drug)
                                                <span class="block text-[10px] {{ $item->drug->stock_quantity >= $item->quantity ? 'text-green-600' : 'text-red-600 font-bold' }}">
                                                    In Stock: {{ $item->drug->stock_quantity }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Actions -->
                    <div class="mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="text-xs text-zinc-500">
                            @if ($rx->dispensed_at)
                                Dispensed by {{ $rx->dispenser?->name ?? 'Pharmacist' }} at {{ $rx->dispensed_at->format('d M Y, h:i A') }}
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($rx->status === 'pending')
                                <flux:button wire:click="dispensePrescription({{ $rx->id }})" variant="primary" size="sm" icon="check">
                                    Dispense Medication
                                </flux:button>
                                <flux:button wire:click="cancelPrescription({{ $rx->id }})" variant="ghost" size="sm" class="text-red-600">
                                    Cancel
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-zinc-500">
                    No prescriptions found in queue.
                </div>
            @endforelse
        </div>

        <div class="mt-4">{{ $prescriptions->links() }}</div>
    </flux:card>
</div>
