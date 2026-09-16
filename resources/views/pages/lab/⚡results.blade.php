<?php

use App\Models\HmsAlert;
use App\Models\LabResult;
use App\Models\LabTestOrder;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Laboratory Results & Validation - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $flagFilter = '';

    // Result Entry Modal
    public bool $showEntryModal = false;
    public ?int $selectedOrderId = null;
    public string $parameter_name = '';
    public string $result_value = '';
    public string $unit = '';
    public string $reference_range = '';
    public string $flag = 'normal';
    public string $notes = '';

    public function openEntryModal(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $order = LabTestOrder::findOrFail($orderId);
        $this->parameter_name = $order->test_name;
        $this->result_value = '';
        $this->unit = '';
        $this->reference_range = '';
        $this->flag = 'normal';
        $this->notes = '';
        $this->showEntryModal = true;
    }

    public function saveResult(): void
    {
        $this->validate([
            'selectedOrderId' => 'required|exists:lab_test_orders,id',
            'parameter_name' => 'required|string|max:150',
            'result_value' => 'required|string|max:100',
            'unit' => 'nullable|string|max:50',
            'reference_range' => 'nullable|string|max:100',
            'flag' => 'required|in:normal,abnormal,critical',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = LabTestOrder::with(['patient', 'doctor'])->findOrFail($this->selectedOrderId);

        $result = LabResult::create([
            'lab_test_order_id' => $order->id,
            'patient_id' => $order->patient_id,
            'technician_id' => Auth::id(),
            'parameter_name' => $this->parameter_name,
            'result_value' => $this->result_value,
            'unit' => $this->unit,
            'reference_range' => $this->reference_range,
            'flag' => $this->flag,
            'notes' => $this->notes,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        // Complete the order
        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => Auth::id(),
        ]);

        // If flagged critical or abnormal, dispatch automated alert to doctor
        if (in_array($this->flag, ['abnormal', 'critical'])) {
            HmsAlert::create([
                'user_id' => $order->doctor_id,
                'target_role' => 'doctor',
                'alert_type' => 'critical_lab',
                'title' => ($this->flag === 'critical' ? 'CRITICAL LAB ALERT: ' : 'Abnormal Lab Finding: ') . $this->parameter_name,
                'message' => "Patient {$order->patient->full_name} ({$order->patient->file_number}) returned {$this->flag} result: {$this->result_value} {$this->unit}. Ref: {$this->reference_range}",
                'priority' => $this->flag === 'critical' ? 'critical' : 'high',
                'is_read' => false,
                'link' => route('patients.show', ['patient' => $order->patient_id]),
            ]);
        }

        AuditService::log('create', 'lab', (string)$result->id, "Entered and validated lab result for {$order->test_name} ({$this->flag})");

        $this->showEntryModal = false;
        Flux::toast(variant: 'success', text: "Lab result validated and released to patient EHR.");
    }

    public function render()
    {
        $query = LabResult::with(['testOrder', 'patient', 'technician', 'validator'])->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('parameter_name', 'like', "%{$this->search}%")
                  ->orWhere('result_value', 'like', "%{$this->search}%")
                  ->orWhereHas('patient', function ($p) {
                      $p->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('file_number', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->flagFilter) {
            $query->where('flag', $this->flagFilter);
        }

        $pendingOrders = LabTestOrder::with(['patient', 'doctor'])
            ->whereIn('status', ['sample_collected', 'processing'])
            ->latest()
            ->get();

        return view('pages.lab.⚡results', [
            'results' => $query->paginate(10),
            'pendingOrders' => $pendingOrders,
            'criticalCount' => LabResult::where('flag', 'critical')->count(),
            'abnormalCount' => LabResult::where('flag', 'abnormal')->count(),
            'normalCount' => LabResult::where('flag', 'normal')->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('lab.orders') }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                    Orders & Accessioning
                </flux:button>
            </div>
            <flux:heading size="xl" level="1" class="mt-1">Laboratory Results & Clinical Validation</flux:heading>
            <flux:subheading>Enter test parameters, reference ranges, flag critical values, and release reports</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('lab.equipment') }}" variant="filled" icon="wrench-screwdriver" wire:navigate>
                Equipment Logs
            </flux:button>
        </div>
    </div>

    <!-- Flag Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 border-l-4 border-red-500 cursor-pointer" wire:click="$set('flagFilter', 'critical')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Critical Value Alerts</div>
            <div class="text-3xl font-extrabold text-red-600 dark:text-red-400 mt-1">{{ $criticalCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Immediate physician review flagged</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-amber-500 cursor-pointer" wire:click="$set('flagFilter', 'abnormal')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Abnormal Findings</div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $abnormalCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Outside standard biological limits</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-green-500 cursor-pointer" wire:click="$set('flagFilter', 'normal')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Within Normal Limits</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">{{ $normalCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Standard clinical reference ranges</div>
        </flux:card>
    </div>

    <!-- Pending Result Entry Queue -->
    @if (count($pendingOrders) > 0)
        <flux:card class="p-5 bg-gradient-to-r from-blue-50/50 to-indigo-50/50 dark:from-blue-950/20 dark:to-indigo-950/20 border-blue-200 dark:border-blue-900/50">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <flux:heading size="lg">Pending Bench Results Queue</flux:heading>
                    <flux:subheading>Samples ready for analysis, result entry, and validation</flux:subheading>
                </div>
                <flux:badge color="blue">{{ count($pendingOrders) }} Awaiting Entry</flux:badge>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                @foreach ($pendingOrders as $pOrd)
                    <div class="p-3 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">{{ $pOrd->order_number }}</span>
                                <flux:badge color="zinc" size="sm">{{ $pOrd->sample_id ?? 'No barcode' }}</flux:badge>
                            </div>
                            <div class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $pOrd->test_name }}</div>
                            <div class="text-xs text-zinc-500 mt-0.5">
                                Patient: {{ $pOrd->patient->full_name }} ({{ $pOrd->patient->file_number }})
                            </div>
                        </div>
                        <div class="mt-3 pt-2 border-t border-zinc-100 dark:border-zinc-700 flex justify-end">
                            <flux:button wire:click="openEntryModal({{ $pOrd->id }})" size="xs" variant="primary">
                                Enter Result
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <!-- Validated Lab Results Table -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search result, test parameter, or patient..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="flagFilter" class="w-48">
                    <flux:select.option value="">All Flags</flux:select.option>
                    <flux:select.option value="critical">Critical Only</flux:select.option>
                    <flux:select.option value="abnormal">Abnormal Only</flux:select.option>
                    <flux:select.option value="normal">Normal Only</flux:select.option>
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Total validated results: {{ $results->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Patient / MRN</th>
                        <th class="py-3 px-4">Test Parameter</th>
                        <th class="py-3 px-4">Result Value</th>
                        <th class="py-3 px-4">Reference Range</th>
                        <th class="py-3 px-4">Clinical Flag</th>
                        <th class="py-3 px-4">Validated By</th>
                        <th class="py-3 px-4 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($results as $res)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 {{ $res->flag === 'critical' ? 'bg-red-50/30 dark:bg-red-950/10' : '' }}">
                            <td class="py-3 px-4">
                                <a href="{{ route('patients.show', ['patient' => $res->patient_id]) }}" class="font-medium text-blue-600 hover:underline">
                                    {{ $res->patient->full_name }}
                                </a>
                                <span class="block text-xs text-zinc-400 font-mono">{{ $res->patient->file_number }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $res->parameter_name }}</span>
                                @if ($res->notes)
                                    <span class="block text-xs text-zinc-500 italic">{{ $res->notes }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $res->result_value }} {{ $res->unit }}
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-zinc-500">
                                {{ $res->reference_range ?? '—' }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($res->flag === 'critical')
                                    <flux:badge color="red" size="sm">CRITICAL</flux:badge>
                                @elseif ($res->flag === 'abnormal')
                                    <flux:badge color="amber" size="sm">Abnormal</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">Normal</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs">
                                <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $res->validator?->name ?? 'Technician' }}</div>
                                <span class="text-zinc-400">{{ $res->validated_at ? $res->validated_at->format('d M, h:i A') : '—' }}</span>
                            </td>
                            <td class="py-3 px-4 text-right text-xs text-zinc-400">
                                {{ $res->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">No laboratory test results recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $results->links() }}</div>
    </flux:card>

    <!-- Result Entry Modal -->
    <flux:modal wire:model="showEntryModal" class="md:w-[500px]">
        <form wire:submit.prevent="saveResult" class="space-y-4">
            <div>
                <flux:heading size="lg">Enter Diagnostic Test Result</flux:heading>
                <flux:subheading>Review laboratory analysis and flag biological range</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Investigation Parameter</flux:label>
                <flux:input wire:model="parameter_name" required />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Result Value</flux:label>
                    <flux:input wire:model="result_value" placeholder="e.g. 13.8 or Positive" required />
                </flux:field>

                <flux:field>
                    <flux:label>Unit of Measure</flux:label>
                    <flux:input wire:model="unit" placeholder="e.g. x10^9/L, mg/dL, mmol/L" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Biological Reference Range</flux:label>
                    <flux:input wire:model="reference_range" placeholder="e.g. 4.0 - 11.0, Negative" />
                </flux:field>

                <flux:field>
                    <flux:label>Clinical Flag</flux:label>
                    <flux:select wire:model="flag">
                        <flux:select.option value="normal">Normal</flux:select.option>
                        <flux:select.option value="abnormal">Abnormal</flux:select.option>
                        <flux:select.option value="critical">Critical (Doctor Alert)</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Technician / Pathologist Notes</flux:label>
                <flux:textarea wire:model="notes" placeholder="Microscopic observations, repeat test verification, or clinical remarks" rows="2" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showEntryModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Validate & Release</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
