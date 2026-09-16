<?php

use App\Models\HospitalService;
use App\Models\LabTestOrder;
use App\Models\Patient;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Laboratory Test Orders - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    // Create Order Modal
    public bool $showCreateModal = false;
    public ?int $patient_id = null;
    public string $test_name = '';
    public string $priority = 'routine';
    public string $sample_type = 'Blood';
    public string $clinical_notes = '';

    // Sample Collection Modal
    public bool $showSampleModal = false;
    public ?int $collectOrderId = null;
    public string $sample_id = '';
    public string $collection_type = 'Blood';

    public function openCreateModal(?int $patientId = null): void
    {
        $this->patient_id = $patientId ?? Patient::first()?->id;
        $this->test_name = '';
        $this->priority = 'routine';
        $this->sample_type = 'Blood';
        $this->clinical_notes = '';
        $this->showCreateModal = true;
    }

    public function createOrder(): void
    {
        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'test_name' => 'required|string|max:200',
            'priority' => 'required|in:routine,urgent,stat',
            'sample_type' => 'required|string',
            'clinical_notes' => 'nullable|string|max:500',
        ]);

        $order = LabTestOrder::create([
            'order_number' => LabTestOrder::generateOrderNumber(),
            'patient_id' => $this->patient_id,
            'doctor_id' => Auth::id(),
            'test_name' => $this->test_name,
            'priority' => $this->priority,
            'status' => 'ordered',
            'sample_type' => $this->sample_type,
            'clinical_notes' => $this->clinical_notes,
        ]);

        AuditService::log('create', 'lab', (string)$order->id, "Ordered lab investigation {$order->test_name} ({$order->order_number})");

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: "Lab investigation {$order->order_number} ordered.");
    }

    public function openSampleModal(int $orderId): void
    {
        $this->collectOrderId = $orderId;
        $order = LabTestOrder::findOrFail($orderId);
        $this->sample_id = LabTestOrder::generateSampleBarcode();
        $this->collection_type = $order->sample_type ?: 'Blood';
        $this->showSampleModal = true;
    }

    public function recordSampleCollection(): void
    {
        $this->validate([
            'collectOrderId' => 'required|exists:lab_test_orders,id',
            'sample_id' => 'required|string|max:50',
            'collection_type' => 'required|string',
        ]);

        $order = LabTestOrder::findOrFail($this->collectOrderId);
        $order->update([
            'status' => 'sample_collected',
            'sample_id' => $this->sample_id,
            'sample_type' => $this->collection_type,
            'sample_collected_at' => now(),
            'sample_collected_by' => Auth::id(),
        ]);

        AuditService::log('update', 'lab', (string)$order->id, "Sample {$this->sample_id} collected for order {$order->order_number}");

        $this->showSampleModal = false;
        Flux::toast(variant: 'success', text: "Sample {$this->sample_id} logged into laboratory.");
    }

    public function markProcessing(int $orderId): void
    {
        $order = LabTestOrder::findOrFail($orderId);
        $order->update(['status' => 'processing']);

        Flux::toast(variant: 'info', text: "Order {$order->order_number} moved to testing & processing.");
    }

    public function render()
    {
        $query = LabTestOrder::with(['patient', 'doctor', 'sampleCollector', 'results'])->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', "%{$this->search}%")
                  ->orWhere('test_name', 'like', "%{$this->search}%")
                  ->orWhere('sample_id', 'like', "%{$this->search}%")
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

        return view('pages.lab.⚡orders', [
            'orders' => $query->paginate(10),
            'patients' => Patient::orderBy('first_name')->take(50)->get(),
            'pendingCount' => LabTestOrder::where('status', 'ordered')->count(),
            'collectedCount' => LabTestOrder::where('status', 'sample_collected')->count(),
            'processingCount' => LabTestOrder::where('status', 'processing')->count(),
            'completedCount' => LabTestOrder::where('status', 'completed')->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Laboratory Test Orders & Accessioning</flux:heading>
            <flux:subheading>Manage diagnostic test requisitions, sample collection, and barcode identifiers</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('lab.results') }}" variant="filled" icon="clipboard-document-check" wire:navigate>
                Results Entry
            </flux:button>
            <flux:button href="{{ route('lab.equipment') }}" variant="filled" icon="wrench-screwdriver" wire:navigate>
                Equipment & QC
            </flux:button>
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                New Lab Order
            </flux:button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="p-4 border-l-4 border-indigo-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Ordered (Pending Sample)</div>
            <div class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ $pendingCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Awaiting phlebotomy / specimen</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-amber-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Sample Collected</div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $collectedCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Accessioned into lab bench</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-blue-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Processing / Testing</div>
            <div class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">{{ $processingCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">In analytical analyzer</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-green-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Completed Tests</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">{{ $completedCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Validated & released to EHR</div>
        </flux:card>
    </div>

    <!-- Orders Table -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search order #, test, barcode, patient..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="statusFilter" class="w-48">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    <flux:select.option value="ordered">Ordered</flux:select.option>
                    <flux:select.option value="sample_collected">Sample Collected</flux:select.option>
                    <flux:select.option value="processing">Processing</flux:select.option>
                    <flux:select.option value="completed">Completed</flux:select.option>
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Total orders: {{ $orders->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Order #</th>
                        <th class="py-3 px-4">Patient</th>
                        <th class="py-3 px-4">Investigation</th>
                        <th class="py-3 px-4">Sample Barcode</th>
                        <th class="py-3 px-4">Priority</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($orders as $ord)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4 font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $ord->order_number }}
                                <span class="block text-[10px] text-zinc-400 font-normal">{{ $ord->created_at->format('d M, h:i A') }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('patients.show', ['patient' => $ord->patient_id]) }}" class="font-medium text-blue-600 hover:underline">
                                    {{ $ord->patient->full_name }}
                                </a>
                                <span class="block text-xs text-zinc-400 font-mono">{{ $ord->patient->file_number }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $ord->test_name }}</span>
                                <span class="block text-xs text-zinc-500">Type: {{ $ord->sample_type }} • Dr. {{ $ord->doctor->name }}</span>
                            </td>
                            <td class="py-3 px-4">
                                @if ($ord->sample_id)
                                    <span class="inline-flex items-center gap-1 font-mono text-xs font-bold bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded border border-zinc-300 dark:border-zinc-700">
                                        <flux:icon name="qr-code" class="size-3.5 text-zinc-500" />
                                        {{ $ord->sample_id }}
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400 italic">Not collected</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($ord->priority === 'stat')
                                    <flux:badge color="red" size="sm">STAT</flux:badge>
                                @elseif ($ord->priority === 'urgent')
                                    <flux:badge color="amber" size="sm">Urgent</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Routine</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($ord->status === 'completed')
                                    <flux:badge color="green" size="sm">Completed</flux:badge>
                                @elseif ($ord->status === 'processing')
                                    <flux:badge color="blue" size="sm">Processing</flux:badge>
                                @elseif ($ord->status === 'sample_collected')
                                    <flux:badge color="amber" size="sm">Sample In Lab</flux:badge>
                                @else
                                    <flux:badge color="indigo" size="sm">Ordered</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if ($ord->status === 'ordered')
                                        <flux:button wire:click="openSampleModal({{ $ord->id }})" size="xs" variant="filled">Collect Sample</flux:button>
                                    @elseif ($ord->status === 'sample_collected')
                                        <flux:button wire:click="markProcessing({{ $ord->id }})" size="xs" variant="primary">Start Test</flux:button>
                                    @elseif ($ord->status === 'processing')
                                        <flux:button href="{{ route('lab.results') }}" size="xs" variant="primary" wire:navigate>Enter Results</flux:button>
                                    @elseif ($ord->status === 'completed')
                                        <flux:button href="{{ route('lab.results') }}" size="xs" variant="ghost" icon="eye" wire:navigate title="View Results" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">No laboratory test orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $orders->links() }}</div>
    </flux:card>

    <!-- Create Lab Order Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[500px]">
        <form wire:submit.prevent="createOrder" class="space-y-4">
            <div>
                <flux:heading size="lg">New Laboratory Test Requisition</flux:heading>
                <flux:subheading>Order clinical pathology, microbiology, or hematology tests</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Select Patient</flux:label>
                <flux:select wire:model="patient_id" required>
                    @foreach ($patients as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->full_name }} ({{ $p->file_number }})</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Investigation / Test Name</flux:label>
                <flux:input wire:model="test_name" placeholder="e.g. Full Blood Count, Lipid Profile, Liver Function Test" required />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Specimen Type</flux:label>
                    <flux:select wire:model="sample_type">
                        <flux:select.option value="Blood">Blood (Whole/Serum/Plasma)</flux:select.option>
                        <flux:select.option value="Urine">Urine</flux:select.option>
                        <flux:select.option value="Stool">Stool</flux:select.option>
                        <flux:select.option value="Swab">Swab (Throat/Wound)</flux:select.option>
                        <flux:select.option value="Sputum">Sputum</flux:select.option>
                        <flux:select.option value="Biopsy">Biopsy / Tissue</flux:select.option>
                        <flux:select.option value="Other">Other</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Urgency / Priority</flux:label>
                    <flux:select wire:model="priority">
                        <flux:select.option value="routine">Routine</flux:select.option>
                        <flux:select.option value="urgent">Urgent</flux:select.option>
                        <flux:select.option value="stat">STAT / Emergency</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Clinical Indications / Suspected Diagnosis</flux:label>
                <flux:textarea wire:model="clinical_notes" placeholder="Notes for pathologist or lab technician" rows="2" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Submit Order</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Collect Sample Modal -->
    <flux:modal wire:model="showSampleModal" class="md:w-[450px]">
        <form wire:submit.prevent="recordSampleCollection" class="space-y-4">
            <div>
                <flux:heading size="lg">Log Sample Collection</flux:heading>
                <flux:subheading>Generate barcode label and confirm specimen intake</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Sample Identifier / Barcode</flux:label>
                <flux:input wire:model="sample_id" class="font-mono font-bold" required />
            </flux:field>

            <flux:field>
                <flux:label>Specimen Material</flux:label>
                <flux:select wire:model="collection_type">
                    <flux:select.option value="Blood">Blood</flux:select.option>
                    <flux:select.option value="Urine">Urine</flux:select.option>
                    <flux:select.option value="Stool">Stool</flux:select.option>
                    <flux:select.option value="Swab">Swab</flux:select.option>
                    <flux:select.option value="Sputum">Sputum</flux:select.option>
                </flux:select>
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showSampleModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Confirm Collection</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
