<?php

use App\Models\LabEquipmentLog;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Laboratory Equipment & QC Logs - HMS')] class extends Component {
    public bool $showLogModal = false;
    public string $equipment_name = '';
    public string $serial_number = '';
    public string $department = 'Hematology';
    public string $status = 'operational';
    public string $last_calibrated_at = '';
    public string $next_calibration_due = '';
    public string $notes = '';

    public function mount(): void
    {
        $this->last_calibrated_at = now()->toDateString();
        $this->next_calibration_due = now()->addDays(30)->toDateString();
    }

    public function openLogModal(): void
    {
        $this->reset(['equipment_name', 'serial_number', 'notes']);
        $this->department = 'Hematology';
        $this->status = 'operational';
        $this->last_calibrated_at = now()->toDateString();
        $this->next_calibration_due = now()->addDays(30)->toDateString();
        $this->showLogModal = true;
    }

    public function logEquipment(): void
    {
        $this->validate([
            'equipment_name' => 'required|string|max:150',
            'serial_number' => 'nullable|string|max:100',
            'department' => 'required|string|max:100',
            'status' => 'required|in:operational,maintenance,calibration_due',
            'last_calibrated_at' => 'nullable|date',
            'next_calibration_due' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $log = LabEquipmentLog::create([
            'equipment_name' => $this->equipment_name,
            'serial_number' => $this->serial_number,
            'department' => $this->department,
            'status' => $this->status,
            'last_calibrated_at' => $this->last_calibrated_at ?: null,
            'next_calibration_due' => $this->next_calibration_due ?: null,
            'notes' => $this->notes,
            'logged_by' => Auth::id(),
        ]);

        AuditService::log('create', 'lab', (string)$log->id, "Recorded equipment calibration / QC log for {$log->equipment_name}");

        $this->showLogModal = false;
        Flux::toast(variant: 'success', text: "Equipment entry {$log->equipment_name} recorded.");
    }

    public function updateStatus(int $id, string $status): void
    {
        $eq = LabEquipmentLog::findOrFail($id);
        $eq->update(['status' => $status]);

        AuditService::log('update', 'lab', (string)$eq->id, "Updated {$eq->equipment_name} status to {$status}");
        Flux::toast(variant: 'info', text: "Equipment status updated to {$status}.");
    }

    public function render()
    {
        $logs = LabEquipmentLog::with('loggedBy')->latest()->get();

        return view('pages.lab.⚡equipment', [
            'logs' => $logs,
            'operationalCount' => $logs->where('status', 'operational')->count(),
            'dueCount' => $logs->where('status', 'calibration_due')->count(),
            'maintenanceCount' => $logs->where('status', 'maintenance')->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('lab.orders') }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                    Lab Orders
                </flux:button>
            </div>
            <flux:heading size="xl" level="1" class="mt-1">Laboratory Equipment & Quality Control (QC)</flux:heading>
            <flux:subheading>Manage analyzer maintenance, calibration cycles, and ISO compliance logs</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="openLogModal" variant="primary" icon="plus">
                Log Equipment / Calibration
            </flux:button>
        </div>
    </div>

    <!-- Status Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 border-l-4 border-green-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Operational Equipment</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">{{ $operationalCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Calibrated and actively running specimens</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-amber-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Calibration Due Soon</div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $dueCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Scheduled standard control required</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-red-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Under Maintenance</div>
            <div class="text-3xl font-extrabold text-red-600 dark:text-red-400 mt-1">{{ $maintenanceCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Biomedical engineering service active</div>
        </flux:card>
    </div>

    <!-- Logs Table -->
    <flux:card class="p-5">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Analyzer Inventory & Maintenance Schedule</flux:heading>
            <span class="text-xs text-zinc-500">Total units: {{ count($logs) }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Equipment Name</th>
                        <th class="py-3 px-4">Serial / Tag</th>
                        <th class="py-3 px-4">Department</th>
                        <th class="py-3 px-4">Last Calibration</th>
                        <th class="py-3 px-4">Next Due</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4">
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $log->equipment_name }}</span>
                                @if ($log->notes)
                                    <span class="block text-xs text-zinc-500">{{ $log->notes }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-zinc-500">
                                {{ $log->serial_number ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-zinc-600 dark:text-zinc-300">
                                {{ $log->department ?? 'Central Diagnostic' }}
                            </td>
                            <td class="py-3 px-4 text-xs font-mono">
                                {{ $log->last_calibrated_at ? $log->last_calibrated_at->format('d M Y') : '—' }}
                            </td>
                            <td class="py-3 px-4 text-xs font-mono">
                                @if ($log->next_calibration_due)
                                    <span class="{{ $log->next_calibration_due->isPast() ? 'text-red-600 font-bold' : 'text-zinc-700 dark:text-zinc-300' }}">
                                        {{ $log->next_calibration_due->format('d M Y') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($log->status === 'operational')
                                    <flux:badge color="green" size="sm">Operational</flux:badge>
                                @elseif ($log->status === 'calibration_due')
                                    <flux:badge color="amber" size="sm">Calibration Due</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Maintenance</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($log->status !== 'operational')
                                        <flux:button wire:click="updateStatus({{ $log->id }}, 'operational')" size="xs" variant="primary">Set Operational</flux:button>
                                    @endif
                                    @if ($log->status !== 'maintenance')
                                        <flux:button wire:click="updateStatus({{ $log->id }}, 'maintenance')" size="xs" variant="ghost" class="text-red-600">Service</flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">No laboratory equipment records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <!-- Modal -->
    <flux:modal wire:model="showLogModal" class="md:w-[500px]">
        <form wire:submit.prevent="logEquipment" class="space-y-4">
            <div>
                <flux:heading size="lg">Log Equipment & QC Calibration</flux:heading>
                <flux:subheading>Record laboratory machine parameters and maintenance intervals</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Equipment / Analyzer Name</flux:label>
                <flux:input wire:model="equipment_name" placeholder="e.g. Mindray BC-5000 Hematology Analyzer" required />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Serial Number / Asset Tag</flux:label>
                    <flux:input wire:model="serial_number" placeholder="e.g. SN-89104" />
                </flux:field>

                <flux:field>
                    <flux:label>Department</flux:label>
                    <flux:select wire:model="department">
                        <flux:select.option value="Hematology">Hematology</flux:select.option>
                        <flux:select.option value="Clinical Chemistry">Clinical Chemistry</flux:select.option>
                        <flux:select.option value="Microbiology">Microbiology</flux:select.option>
                        <flux:select.option value="Parasitology">Parasitology</flux:select.option>
                        <flux:select.option value="Blood Bank">Blood Bank</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model="status">
                        <flux:select.option value="operational">Operational</flux:select.option>
                        <flux:select.option value="calibration_due">Calibration Due</flux:select.option>
                        <flux:select.option value="maintenance">Maintenance</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Last Calibrated</flux:label>
                    <flux:input type="date" wire:model="last_calibrated_at" />
                </flux:field>

                <flux:field>
                    <flux:label>Next Due</flux:label>
                    <flux:input type="date" wire:model="next_calibration_due" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>QC Control & Maintenance Remarks</flux:label>
                <flux:textarea wire:model="notes" placeholder="Quality control standards, reagent batch numbers, or engineer service notes" rows="2" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showLogModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Equipment Log</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
