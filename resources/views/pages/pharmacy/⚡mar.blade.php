<?php

use App\Models\MedicationAdministrationRecord;
use App\Models\Patient;
use App\Models\PrescriptionItem;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Medication Administration Record (MAR) - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    // Schedule Administration Modal
    public bool $showAdministerModal = false;
    public ?int $selectedMarId = null;
    public string $dose_administered = '';
    public string $status = 'given';
    public string $refusal_reason = '';
    public string $notes = '';

    public function openAdministerModal(int $marId): void
    {
        $this->selectedMarId = $marId;
        $mar = MedicationAdministrationRecord::with('prescriptionItem')->findOrFail($marId);
        $this->dose_administered = $mar->prescriptionItem->dosage;
        $this->status = 'given';
        $this->refusal_reason = '';
        $this->notes = '';
        $this->showAdministerModal = true;
    }

    public function recordAdministration(): void
    {
        $this->validate([
            'selectedMarId' => 'required|exists:medication_administration_records,id',
            'status' => 'required|in:given,missed,refused,held',
            'dose_administered' => 'nullable|string|max:100',
            'refusal_reason' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
        ]);

        $mar = MedicationAdministrationRecord::with(['patient', 'prescriptionItem'])->findOrFail($this->selectedMarId);
        $mar->update([
            'nurse_id' => Auth::id(),
            'administered_at' => now(),
            'dose_administered' => $this->dose_administered,
            'status' => $this->status,
            'refusal_reason' => $this->refusal_reason ?: null,
            'notes' => $this->notes,
        ]);

        AuditService::log('administer', 'pharmacy', (string)$mar->id, "Nurse logged MAR medication administration ({$this->status}) for {$mar->patient->full_name}");

        $this->showAdministerModal = false;
        Flux::toast(variant: 'success', text: "MAR updated. Dose marked as {$this->status}.");
    }

    public function render()
    {
        $query = MedicationAdministrationRecord::with(['patient', 'nurse', 'prescriptionItem.prescription.doctor'])->latest('scheduled_time');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('dose_administered', 'like', "%{$this->search}%")
                  ->orWhereHas('patient', function ($p) {
                      $p->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('file_number', 'like', "%{$this->search}%");
                  })
                  ->orWhereHas('prescriptionItem', function ($i) {
                      $i->where('drug_name', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return view('pages.pharmacy.⚡mar', [
            'records' => $query->paginate(12),
            'scheduledCount' => MedicationAdministrationRecord::where('status', 'scheduled')->count(),
            'givenCount' => MedicationAdministrationRecord::where('status', 'given')->count(),
            'missedCount' => MedicationAdministrationRecord::whereIn('status', ['missed', 'refused', 'held'])->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('pharmacy.prescriptions') }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                    Prescriptions
                </flux:button>
            </div>
            <flux:heading size="xl" level="1" class="mt-1">Medication Administration Record (MAR)</flux:heading>
            <flux:subheading>Nursing shift administration log, dose verification, and adherence tracking</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('wards.index') }}" variant="filled" icon="building-office-2" wire:navigate>
                Inpatient Wards
            </flux:button>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 border-l-4 border-indigo-500 cursor-pointer" wire:click="$set('statusFilter', 'scheduled')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Doses Due / Scheduled</div>
            <div class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ $scheduledCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Pending nurse administration round</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-green-500 cursor-pointer" wire:click="$set('statusFilter', 'given')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Doses Administered</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">{{ $givenCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Confirmed given with nurse signoff</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-red-500 cursor-pointer" wire:click="$set('statusFilter', 'held')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Missed / Held / Refused</div>
            <div class="text-3xl font-extrabold text-red-600 dark:text-red-400 mt-1">{{ $missedCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Patient refusal or medical hold</div>
        </flux:card>
    </div>

    <!-- MAR Table -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search patient, MRN, medication..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="statusFilter" class="w-44">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    <flux:select.option value="scheduled">Due / Scheduled</flux:select.option>
                    <flux:select.option value="given">Given</flux:select.option>
                    <flux:select.option value="missed">Missed</flux:select.option>
                    <flux:select.option value="refused">Refused</flux:select.option>
                    <flux:select.option value="held">Held</flux:select.option>
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Total doses: {{ $records->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Patient / Ward</th>
                        <th class="py-3 px-4">Medication & Route</th>
                        <th class="py-3 px-4">Scheduled Time</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Administered Time</th>
                        <th class="py-3 px-4">Nurse Attending</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($records as $mar)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4">
                                <a href="{{ route('patients.show', ['patient' => $mar->patient_id]) }}" class="font-medium text-blue-600 hover:underline">
                                    {{ $mar->patient->full_name }}
                                </a>
                                <span class="block text-xs text-zinc-400 font-mono">{{ $mar->patient->file_number }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $mar->prescriptionItem->drug_name }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $mar->prescriptionItem->dosage }} • {{ $mar->prescriptionItem->frequency }}
                                    @if ($mar->prescriptionItem->route) ({{ $mar->prescriptionItem->route }}) @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-zinc-700 dark:text-zinc-300">
                                {{ $mar->scheduled_time->format('d M, h:i A') }}
                            </td>
                            <td class="py-3 px-4">
                                @if ($mar->status === 'given')
                                    <flux:badge color="green" size="sm">Given</flux:badge>
                                @elseif ($mar->status === 'scheduled')
                                    <flux:badge color="indigo" size="sm">Scheduled</flux:badge>
                                @elseif ($mar->status === 'missed')
                                    <flux:badge color="amber" size="sm">Missed</flux:badge>
                                @elseif ($mar->status === 'refused')
                                    <flux:badge color="red" size="sm">Refused</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Held</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs font-mono">
                                {{ $mar->administered_at ? $mar->administered_at->format('d M, h:i A') : '—' }}
                                @if ($mar->dose_administered)
                                    <span class="block text-[10px] text-zinc-400 font-normal">Dose: {{ $mar->dose_administered }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs">
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $mar->nurse?->name ?? '—' }}</span>
                                @if ($mar->notes)
                                    <span class="block text-[10px] text-zinc-400 italic">{{ $mar->notes }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if ($mar->status === 'scheduled')
                                    <flux:button wire:click="openAdministerModal({{ $mar->id }})" size="xs" variant="primary">
                                        Log Dose
                                    </flux:button>
                                @else
                                    <flux:button wire:click="openAdministerModal({{ $mar->id }})" size="xs" variant="ghost">
                                        Edit
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">No medication administration records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $records->links() }}</div>
    </flux:card>

    <!-- Administer Modal -->
    <flux:modal wire:model="showAdministerModal" class="md:w-[450px]">
        <form wire:submit.prevent="recordAdministration" class="space-y-4">
            <div>
                <flux:heading size="lg">Log Medication Administration</flux:heading>
                <flux:subheading>Record bedside dose delivery and patient response</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Administration Status</flux:label>
                <flux:select wire:model.live="status" required>
                    <flux:select.option value="given">Given as Prescribed</flux:select.option>
                    <flux:select.option value="held">Held (Clinical Reason)</flux:select.option>
                    <flux:select.option value="refused">Patient Refused</flux:select.option>
                    <flux:select.option value="missed">Missed Dose</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Dose Delivered</flux:label>
                <flux:input wire:model="dose_administered" placeholder="e.g. 10mg oral or 1g IV push" />
            </flux:field>

            @if ($status === 'refused' || $status === 'held')
                <flux:field>
                    <flux:label>Reason for Hold / Refusal</flux:label>
                    <flux:input wire:model="refusal_reason" placeholder="e.g. Patient nauseous, BP too low, patient refused" required />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>Nursing Remarks</flux:label>
                <flux:textarea wire:model="notes" placeholder="Observations, vital sign checks pre/post dose" rows="2" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showAdministerModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Confirm Administration</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
