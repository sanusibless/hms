<?php

use App\Models\Patient;
use App\Models\PatientHealthRecord;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Electronic Health Records (EHR) - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all'; // all, chronic, allergies, admitted

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Patient::with(['healthRecords.doctor', 'vitals', 'admissions.ward'])
            ->latest('updated_at');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('file_number', 'like', "%{$this->search}%")
                  ->orWhere('allergies', 'like', "%{$this->search}%")
                  ->orWhere('chronic_conditions', 'like', "%{$this->search}%")
                  ->orWhereHas('healthRecords', function ($hr) {
                      $hr->where('diagnosis', 'like', "%{$this->search}%")
                         ->orWhere('icd_code', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->filter === 'chronic') {
            $query->whereNotNull('chronic_conditions')->where('chronic_conditions', '!=', '');
        } elseif ($this->filter === 'allergies') {
            $query->whereNotNull('allergies')->where('allergies', '!=', '');
        } elseif ($this->filter === 'admitted') {
            $query->where('admission_status', 'admitted');
        }

        return view('pages.ehr.⚡index', [
            'patients' => $query->paginate(12),
            'totalRecords' => PatientHealthRecord::count(),
            'admittedCount' => Patient::where('admission_status', 'admitted')->count(),
            'chronicCount' => Patient::whereNotNull('chronic_conditions')->where('chronic_conditions', '!=', '')->count(),
            'allergiesCount' => Patient::whereNotNull('allergies')->where('allergies', '!=', '')->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Electronic Health Records (EHR)</flux:heading>
            <flux:subheading>Centralized clinical charts, diagnoses, medical history, and ICD-coded records</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('patients.index') }}" variant="filled" icon="users" wire:navigate>
                Patient Registry
            </flux:button>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="p-4 cursor-pointer hover:border-blue-500 transition" wire:click="$set('filter', 'all')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Total Clinical Consultations</div>
            <div class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">{{ $totalRecords }}</div>
            <div class="text-xs text-zinc-400 mt-1">Logged doctor visit records</div>
        </flux:card>

        <flux:card class="p-4 cursor-pointer hover:border-red-500 transition" wire:click="$set('filter', 'admitted')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Current Inpatients</div>
            <div class="text-3xl font-extrabold text-red-600 dark:text-red-400 mt-1">{{ $admittedCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Active ward admissions</div>
        </flux:card>

        <flux:card class="p-4 cursor-pointer hover:border-amber-500 transition" wire:click="$set('filter', 'chronic')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Chronic Conditions</div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $chronicCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Hypertension, Diabetes, Asthma</div>
        </flux:card>

        <flux:card class="p-4 cursor-pointer hover:border-purple-500 transition" wire:click="$set('filter', 'allergies')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Documented Allergies</div>
            <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">{{ $allergiesCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Drug and substance contraindications</div>
        </flux:card>
    </div>

    <!-- Search & Filter Bar -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search patient, MRN, diagnosis, or ICD code..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="filter" class="w-48">
                    <flux:select.option value="all">All Patients</flux:select.option>
                    <flux:select.option value="admitted">Admitted Inpatients</flux:select.option>
                    <flux:select.option value="chronic">With Chronic Conditions</flux:select.option>
                    <flux:select.option value="allergies">With Allergies Flagged</flux:select.option>
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Showing {{ $patients->total() }} matching patient files</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($patients as $patient)
                @php
                    $latestRecord = $patient->healthRecords->first();
                    $latestVital = $patient->vitals->first();
                @endphp
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/40 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="flex items-start justify-between">
                            <div>
                                <a href="{{ route('patients.show', ['patient' => $patient->id]) }}" class="font-bold text-base text-zinc-900 dark:text-zinc-100 hover:underline">
                                    {{ $patient->full_name }}
                                </a>
                                <span class="font-mono text-xs text-blue-600 dark:text-blue-400 block font-semibold">
                                    {{ $patient->file_number }}
                                </span>
                            </div>
                            @if ($patient->admission_status === 'admitted')
                                <flux:badge color="red" size="sm">Admitted</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Outpatient</flux:badge>
                            @endif
                        </div>

                        <!-- Vitals Pill -->
                        @if ($latestVital)
                            <div class="flex items-center gap-2 mt-3 text-xs bg-white dark:bg-zinc-800 p-2 rounded-lg border border-zinc-200/80 dark:border-zinc-700/80">
                                <span class="text-zinc-500">BP: <strong class="text-zinc-800 dark:text-zinc-200">{{ $latestVital->blood_pressure ?? '—' }}</strong></span>
                                <span class="text-zinc-500">SpO2: <strong class="text-zinc-800 dark:text-zinc-200">{{ $latestVital->spo2 ? $latestVital->spo2.'%' : '—' }}</strong></span>
                                <span class="text-zinc-500">Temp: <strong class="text-zinc-800 dark:text-zinc-200">{{ $latestVital->temperature ? $latestVital->temperature.'°C' : '—' }}</strong></span>
                            </div>
                        @endif

                        <!-- Clinical Alerts (Allergies & Chronic) -->
                        <div class="mt-3 space-y-1.5">
                            @if ($patient->allergies)
                                <div class="text-xs flex items-center gap-1.5 text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 px-2 py-1 rounded">
                                    <flux:icon name="exclamation-triangle" class="size-3.5 shrink-0" />
                                    <span class="truncate">Allergy: {{ $patient->allergies }}</span>
                                </div>
                            @endif

                            @if ($patient->chronic_conditions)
                                <div class="text-xs flex items-center gap-1.5 text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-1 rounded">
                                    <flux:icon name="information-circle" class="size-3.5 shrink-0" />
                                    <span class="truncate">Chronic: {{ $patient->chronic_conditions }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Latest Diagnosis -->
                        @if ($latestRecord)
                            <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 text-xs">
                                <div class="flex items-center justify-between text-zinc-500">
                                    <span>Latest Diagnosis</span>
                                    <span class="font-mono font-bold text-zinc-700 dark:text-zinc-300">{{ $latestRecord->icd_code ?? 'Clinical' }}</span>
                                </div>
                                <p class="text-zinc-800 dark:text-zinc-200 mt-1 line-clamp-2 font-medium">
                                    {{ $latestRecord->diagnosis }}
                                </p>
                                <span class="text-[10px] text-zinc-400 block mt-1">
                                    By {{ $latestRecord->doctor?->name ?? 'Attending Doctor' }} • {{ $latestRecord->created_at->diffForHumans() }}
                                </span>
                            </div>
                        @else
                            <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 text-xs text-zinc-400 italic">
                                No consultation visits recorded yet.
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-700 flex items-center justify-between gap-2">
                        <flux:button href="{{ route('patients.show', ['patient' => $patient->id]) }}" size="sm" variant="ghost" wire:navigate>
                            Profile
                        </flux:button>
                        <flux:button href="{{ route('patients.treatment-record', ['patient' => $patient->id]) }}" size="sm" variant="primary" icon="document-text" wire:navigate>
                            Open EHR Chart
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="col-span-3 py-12 text-center text-zinc-500">
                    No EHR records matching your query.
                </div>
            @endforelse
        </div>

        <div class="mt-5">{{ $patients->links() }}</div>
    </flux:card>
</div>
