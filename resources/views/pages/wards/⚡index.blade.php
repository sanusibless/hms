<?php

use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\Ward;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Wards & Bed Management - HMS')] class extends Component {
    // Admit Modal
    public bool $showAdmitModal = false;
    public ?int $selectedBedId = null;
    public ?int $admitPatientId = null;
    public string $admitReason = '';

    // Discharge Modal
    public bool $showDischargeModal = false;
    public ?int $dischargeBedId = null;
    public string $dischargeNotes = '';

    // Create Ward Modal
    public bool $showCreateWardModal = false;
    public string $newWardName = '';
    public string $newWardDept = 'General Medicine';
    public string $newWardType = 'General';
    public int $newWardCapacity = 10;
    public string $newWardRate = '15000';

    public function openAdmitModal(int $bedId): void
    {
        $this->selectedBedId = $bedId;
        $this->admitPatientId = Patient::where('admission_status', '!=', 'admitted')->first()?->id;
        $this->admitReason = '';
        $this->showAdmitModal = true;
    }

    public function processAdmission(): void
    {
        $this->validate([
            'selectedBedId' => 'required|exists:beds,id',
            'admitPatientId' => 'required|exists:patients,id',
            'admitReason' => 'required|string|max:500',
        ]);

        $bed = Bed::with('ward')->findOrFail($this->selectedBedId);
        $patient = Patient::findOrFail($this->admitPatientId);

        // Update bed
        $bed->update([
            'status' => 'occupied',
            'patient_id' => $patient->id,
        ]);

        // Update patient ADT status
        $patient->update([
            'admission_status' => 'admitted',
            'current_ward_id' => $bed->ward_id,
            'current_bed_id' => $bed->id,
            'admitted_at' => now(),
            'discharged_at' => null,
        ]);

        // Create Admission record
        $admission = Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $bed->ward_id,
            'bed_id' => $bed->id,
            'admitted_by' => Auth::id(),
            'admission_date' => now(),
            'reason' => $this->admitReason,
            'status' => 'admitted',
        ]);

        AuditService::log('create', 'wards', (string)$admission->id, "Admitted patient {$patient->full_name} to {$bed->ward->name} ({$bed->bed_number})");

        $this->showAdmitModal = false;
        Flux::toast(variant: 'success', text: "Patient {$patient->full_name} admitted to Bed {$bed->bed_number}.");
    }

    public function openDischargeModal(int $bedId): void
    {
        $this->dischargeBedId = $bedId;
        $this->dischargeNotes = '';
        $this->showDischargeModal = true;
    }

    public function processDischarge(): void
    {
        $this->validate([
            'dischargeBedId' => 'required|exists:beds,id',
            'dischargeNotes' => 'nullable|string|max:500',
        ]);

        $bed = Bed::with(['ward', 'patient'])->findOrFail($this->dischargeBedId);
        $patient = $bed->patient;

        if ($patient) {
            $patient->update([
                'admission_status' => 'discharged',
                'current_ward_id' => null,
                'current_bed_id' => null,
                'discharged_at' => now(),
            ]);

            $latestAdmission = Admission::where('patient_id', $patient->id)
                ->where('status', 'admitted')
                ->latest()
                ->first();

            if ($latestAdmission) {
                $latestAdmission->update([
                    'status' => 'discharged',
                    'discharge_date' => now(),
                    'discharged_by' => Auth::id(),
                    'discharge_notes' => $this->dischargeNotes,
                ]);
            }

            AuditService::log('update', 'wards', (string)$bed->id, "Discharged patient {$patient->full_name} from Bed {$bed->bed_number}");
        }

        $bed->update([
            'status' => 'available',
            'patient_id' => null,
        ]);

        $this->showDischargeModal = false;
        Flux::toast(variant: 'info', text: "Bed {$bed->bed_number} is now freed and available.");
    }

    public function toggleMaintenance(int $bedId): void
    {
        $bed = Bed::findOrFail($bedId);
        if ($bed->status === 'occupied') {
            Flux::toast(variant: 'danger', text: 'Cannot set occupied bed to maintenance.');
            return;
        }

        $bed->status = $bed->status === 'maintenance' ? 'available' : 'maintenance';
        $bed->save();

        Flux::toast(variant: 'info', text: "Bed {$bed->bed_number} status changed to {$bed->status}.");
    }

    public function createWard(): void
    {
        $this->validate([
            'newWardName' => 'required|string|max:100',
            'newWardDept' => 'required|string|max:100',
            'newWardType' => 'required|string',
            'newWardCapacity' => 'required|integer|min:1|max:50',
            'newWardRate' => 'required|numeric|min:0',
        ]);

        $ward = Ward::create([
            'name' => $this->newWardName,
            'department' => $this->newWardDept,
            'type' => $this->newWardType,
            'capacity' => $this->newWardCapacity,
            'daily_rate' => (float)$this->newWardRate,
            'is_active' => true,
        ]);

        $prefix = strtoupper(substr($ward->name, 0, 3));
        for ($i = 1; $i <= $ward->capacity; $i++) {
            $ward->beds()->create([
                'bed_number' => "{$prefix}-BED-" . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                'status' => 'available',
            ]);
        }

        AuditService::log('create', 'wards', (string)$ward->id, "Created new ward {$ward->name} with {$ward->capacity} beds");

        $this->showCreateWardModal = false;
        Flux::toast(variant: 'success', text: "Ward {$ward->name} created with {$ward->capacity} beds.");
    }

    public function render()
    {
        $wards = Ward::with(['beds.patient'])->get();
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'occupied')->count();
        $availableBeds = Bed::where('status', 'available')->count();
        $maintenanceBeds = Bed::where('status', 'maintenance')->count();
        $overallOccupancy = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

        $unadmittedPatients = Patient::where('admission_status', '!=', 'admitted')
            ->orderBy('first_name')
            ->take(50)
            ->get();

        return view('pages.wards.⚡index', [
            'wards' => $wards,
            'totalBeds' => $totalBeds,
            'occupiedBeds' => $occupiedBeds,
            'availableBeds' => $availableBeds,
            'maintenanceBeds' => $maintenanceBeds,
            'overallOccupancy' => $overallOccupancy,
            'unadmittedPatients' => $unadmittedPatients,
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Wards & Bed Allocation (ADT)</flux:heading>
            <flux:subheading>Manage inpatient admissions, transfers, discharges, and bed capacity</flux:subheading>
        </div>
        @if (auth()->user()?->isAdmin() || auth()->user()?->isNurse())
            <flux:button wire:click="$set('showCreateWardModal', true)" variant="primary" icon="plus">
                Add Ward
            </flux:button>
        @endif
    </div>

    <!-- Ward Capacity Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="p-4">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Total Bed Inventory</div>
            <div class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 mt-1">{{ $totalBeds }}</div>
            <div class="text-xs text-zinc-400 mt-1">Across {{ count($wards) }} hospital units</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-red-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Occupied Beds</div>
            <div class="text-3xl font-extrabold text-red-600 dark:text-red-400 mt-1">{{ $occupiedBeds }}</div>
            <div class="text-xs text-zinc-400 mt-1">{{ $overallOccupancy }}% overall occupancy rate</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-green-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Available Beds</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">{{ $availableBeds }}</div>
            <div class="text-xs text-zinc-400 mt-1">Ready for patient admission</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-amber-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Maintenance / Cleaning</div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $maintenanceBeds }}</div>
            <div class="text-xs text-zinc-400 mt-1">Temporarily blocked</div>
        </flux:card>
    </div>

    <!-- Wards Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ($wards as $ward)
            <flux:card class="p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">{{ $ward->name }}</flux:heading>
                            <div class="flex items-center gap-2 mt-1">
                                <flux:badge color="zinc" size="sm">{{ $ward->type }}</flux:badge>
                                <span class="text-xs text-zinc-500">{{ $ward->department }}</span>
                                <span class="text-xs font-mono text-zinc-400">₦{{ number_format((float)$ward->daily_rate, 2) }}/day</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold {{ $ward->occupancy_rate > 80 ? 'text-red-600' : 'text-zinc-600 dark:text-zinc-400' }}">
                                {{ $ward->occupied_beds_count }} / {{ $ward->beds->count() }} Beds
                            </span>
                            <div class="w-24 h-2 bg-zinc-200 dark:bg-zinc-700 rounded-full mt-1.5 overflow-hidden">
                                <div class="h-full bg-blue-600 rounded-full" style="width: {{ $ward->occupancy_rate }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Beds Grid for this Ward -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-5">
                        @forelse ($ward->beds as $bed)
                            <div class="p-3 rounded-lg border {{ $bed->status === 'occupied' ? 'border-red-300 dark:border-red-900/60 bg-red-50/50 dark:bg-red-950/20' : ($bed->status === 'maintenance' ? 'border-amber-300 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/20' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800') }}">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100">{{ $bed->bed_number }}</span>
                                    @if ($bed->status === 'occupied')
                                        <span class="size-2 rounded-full bg-red-500"></span>
                                    @elseif ($bed->status === 'maintenance')
                                        <span class="size-2 rounded-full bg-amber-500"></span>
                                    @else
                                        <span class="size-2 rounded-full bg-green-500"></span>
                                    @endif
                                </div>

                                @if ($bed->patient)
                                    <div class="mt-2">
                                        <a href="{{ route('patients.show', ['patient' => $bed->patient_id]) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline line-clamp-1">
                                            {{ $bed->patient->full_name }}
                                        </a>
                                        <span class="text-[10px] text-zinc-500 block font-mono">{{ $bed->patient->file_number }}</span>
                                    </div>
                                    <div class="mt-3 pt-2 border-t border-red-200 dark:border-red-900/40 flex items-center justify-between">
                                        <flux:button href="{{ route('patients.treatment-record', ['patient' => $bed->patient_id]) }}" size="xs" variant="ghost" icon="document-text" wire:navigate title="EHR Chart" />
                                        <flux:button wire:click="openDischargeModal({{ $bed->id }})" size="xs" variant="ghost" class="text-red-600">Discharge</flux:button>
                                    </div>
                                @elseif ($bed->status === 'maintenance')
                                    <div class="mt-2 text-xs text-amber-700 dark:text-amber-400 italic">Under Maintenance</div>
                                    <div class="mt-3 pt-2 border-t border-amber-200 dark:border-amber-900/40">
                                        <flux:button wire:click="toggleMaintenance({{ $bed->id }})" size="xs" variant="ghost" class="w-full">Set Available</flux:button>
                                    </div>
                                @else
                                    <div class="mt-2 text-xs text-green-600 dark:text-green-400 font-medium">Available</div>
                                    <div class="mt-3 pt-2 border-t border-zinc-200 dark:border-zinc-700/60 flex items-center justify-between gap-1">
                                        <flux:button wire:click="openAdmitModal({{ $bed->id }})" size="xs" variant="primary" class="w-full">Admit</flux:button>
                                        <flux:button wire:click="toggleMaintenance({{ $bed->id }})" size="xs" variant="ghost" icon="wrench" title="Set Maintenance" />
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="col-span-3 text-sm text-zinc-500 italic py-4 text-center">No beds configured for this ward.</div>
                        @endforelse
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>

    <!-- Admit Patient Modal -->
    <flux:modal wire:model="showAdmitModal" class="md:w-[500px]">
        <form wire:submit.prevent="processAdmission" class="space-y-4">
            <div>
                <flux:heading size="lg">Admit Patient to Bed</flux:heading>
                <flux:subheading>Assign an outpatient or emergency patient to inpatient ward bed</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Select Patient</flux:label>
                <flux:select wire:model="admitPatientId" required>
                    @foreach ($unadmittedPatients as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->full_name }} ({{ $p->file_number }})</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Admission Diagnosis / Reason</flux:label>
                <flux:textarea wire:model="admitReason" placeholder="Indicate reason for admission, vital status, or clinical observations" rows="3" required />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showAdmitModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Confirm Admission</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Discharge Patient Modal -->
    <flux:modal wire:model="showDischargeModal" class="md:w-[500px]">
        <form wire:submit.prevent="processDischarge" class="space-y-4">
            <div>
                <flux:heading size="lg">Patient Discharge Protocol</flux:heading>
                <flux:subheading>Release patient from ward and free bed inventory</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Discharge Summary / Condition</flux:label>
                <flux:textarea wire:model="dischargeNotes" placeholder="Condition at discharge, medication instructions, or follow-up schedule" rows="3" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showDischargeModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" class="!bg-red-600 hover:!bg-red-500">Confirm Discharge</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Create Ward Modal -->
    <flux:modal wire:model="showCreateWardModal" class="md:w-[500px]">
        <form wire:submit.prevent="createWard" class="space-y-4">
            <div>
                <flux:heading size="lg">Configure New Ward</flux:heading>
                <flux:subheading>Add ward unit and auto-generate bed numbers</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Ward Name</flux:label>
                <flux:input wire:model="newWardName" placeholder="e.g. Surgical Recovery Ward" required />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Department</flux:label>
                    <flux:input wire:model="newWardDept" placeholder="e.g. Surgery" required />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model="newWardType">
                        <flux:select.option value="General">General</flux:select.option>
                        <flux:select.option value="Male">Male</flux:select.option>
                        <flux:select.option value="Female">Female</flux:select.option>
                        <flux:select.option value="Pediatric">Pediatric</flux:select.option>
                        <flux:select.option value="ICU">ICU</flux:select.option>
                        <flux:select.option value="Maternity">Maternity</flux:select.option>
                        <flux:select.option value="Surgical">Surgical</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Bed Capacity</flux:label>
                    <flux:input type="number" min="1" max="50" wire:model="newWardCapacity" required />
                </flux:field>

                <flux:field>
                    <flux:label>Daily Rate (₦)</flux:label>
                    <flux:input type="number" min="0" wire:model="newWardRate" required />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateWardModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create Ward & Beds</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
