<?php

use App\Models\Appointment;
use App\Models\User;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Patient Consultation Queue - HMS')] class extends Component {
    public ?int $selectedDoctorId = null;

    public function mount(): void
    {
        if (Auth::user()?->isDoctor()) {
            $this->selectedDoctorId = Auth::id();
        }
    }

    public function callPatient(int $appointmentId): void
    {
        $apt = Appointment::findOrFail($appointmentId);
        $apt->update([
            'status' => 'in_consultation',
            'doctor_id' => $apt->doctor_id ?? Auth::id(),
        ]);

        AuditService::log('update', 'appointments', (string)$apt->id, "Doctor called patient {$apt->patient->full_name} into consultation");
        Flux::toast(variant: 'success', text: "Called {$apt->patient->full_name} for consultation.");
    }

    public function completeConsultation(int $appointmentId): void
    {
        $apt = Appointment::findOrFail($appointmentId);
        $apt->update(['status' => 'completed']);

        AuditService::log('update', 'appointments', (string)$apt->id, "Completed consultation for {$apt->patient->full_name}");
        Flux::toast(variant: 'info', text: "Consultation marked as completed.");
    }

    public function cancelQueue(int $appointmentId): void
    {
        $apt = Appointment::findOrFail($appointmentId);
        $apt->update(['status' => 'cancelled']);

        AuditService::log('update', 'appointments', (string)$apt->id, "Removed {$apt->patient->full_name} from active queue");
        Flux::toast(variant: 'danger', text: "Patient removed from queue.");
    }

    public function render()
    {
        $query = Appointment::with(['patient.vitals', 'doctor'])
            ->whereIn('status', ['waiting', 'in_consultation'])
            ->whereDate('scheduled_at', '<=', now()->toDateString())
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->orderBy('queue_number');

        if ($this->selectedDoctorId) {
            $query->where(function ($q) {
                $q->where('doctor_id', $this->selectedDoctorId)
                  ->orWhereNull('doctor_id');
            });
        }

        $activeQueue = $query->get();

        return view('pages.appointments.⚡queue', [
            'activeQueue' => $activeQueue,
            'doctors' => User::where('role', 'doctor')->get(),
            'waitingCount' => $activeQueue->where('status', 'waiting')->count(),
            'inConsultCount' => $activeQueue->where('status', 'in_consultation')->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('appointments.index') }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                    Appointments Calendar
                </flux:button>
            </div>
            <flux:heading size="xl" level="1" class="mt-1">Real-Time Patient Consultation Queue</flux:heading>
            <flux:subheading>Triage prioritization, waitlist tracking, and room calls</flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            <flux:select wire:model.live="selectedDoctorId" class="w-56">
                <flux:select.option value="">All Doctors / General</flux:select.option>
                @foreach ($doctors as $d)
                    <flux:select.option value="{{ $d->id }}">{{ $d->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Queue Status Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 flex items-center justify-between border-l-4 border-amber-500">
            <div>
                <span class="text-xs text-zinc-500 uppercase font-semibold">Patients Waiting</span>
                <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $waitingCount }}</div>
            </div>
            <flux:icon name="clock" class="size-8 text-amber-500/30" />
        </flux:card>

        <flux:card class="p-4 flex items-center justify-between border-l-4 border-blue-500">
            <div>
                <span class="text-xs text-zinc-500 uppercase font-semibold">In Consultation</span>
                <div class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">{{ $inConsultCount }}</div>
            </div>
            <flux:icon name="user-group" class="size-8 text-blue-500/30" />
        </flux:card>

        <flux:card class="p-4 flex items-center justify-between border-l-4 border-green-500">
            <div>
                <span class="text-xs text-zinc-500 uppercase font-semibold">Total Queue Load</span>
                <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">{{ count($activeQueue) }}</div>
            </div>
            <flux:icon name="queue-list" class="size-8 text-green-500/30" />
        </flux:card>
    </div>

    <!-- Queue Cards / Table -->
    <flux:card class="p-5">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">Current Triage & Call List</flux:heading>
            <span class="text-xs text-zinc-400">Auto-prioritized: Emergency > Urgent > Routine</span>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
            @forelse ($activeQueue as $item)
                @php
                    $latestVital = $item->patient->vitals->first();
                @endphp
                <div class="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4 {{ $item->status === 'in_consultation' ? 'bg-blue-50/50 dark:bg-blue-950/20 -mx-5 px-5 rounded-lg' : '' }}">
                    <div class="flex items-start gap-4">
                        <div class="flex flex-col items-center justify-center size-12 rounded-lg {{ $item->priority === 'emergency' ? 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-400 font-extrabold' : ($item->priority === 'urgent' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-400 font-bold' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 font-medium') }}">
                            <span class="text-xs uppercase">No.</span>
                            <span class="text-base leading-none">{{ $item->queue_number }}</span>
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('patients.show', ['patient' => $item->patient_id]) }}" class="font-bold text-base text-zinc-900 dark:text-zinc-100 hover:underline">
                                    {{ $item->patient->full_name }}
                                </a>
                                <span class="font-mono text-xs text-zinc-500">({{ $item->patient->file_number }})</span>
                                @if ($item->priority === 'emergency')
                                    <flux:badge color="red" size="sm">Emergency</flux:badge>
                                @elseif ($item->priority === 'urgent')
                                    <flux:badge color="amber" size="sm">Urgent</flux:badge>
                                @endif
                                @if ($item->status === 'in_consultation')
                                    <flux:badge color="blue" size="sm">In Room Now</flux:badge>
                                @endif
                            </div>

                            <p class="text-sm text-zinc-600 dark:text-zinc-300 mt-0.5">
                                <span class="font-medium text-zinc-700 dark:text-zinc-200">Complaint:</span> {{ $item->reason }}
                            </p>

                            @if ($latestVital)
                                <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-zinc-500">
                                    <span class="bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded font-mono">BP: {{ $latestVital->blood_pressure ?? '—' }}</span>
                                    <span class="bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded font-mono">Temp: {{ $latestVital->temperature ? $latestVital->temperature . '°C' : '—' }}</span>
                                    <span class="bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded font-mono">Pulse: {{ $latestVital->pulse_rate ?? '—' }} bpm</span>
                                    <span class="bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded font-mono">SpO2: {{ $latestVital->spo2 ? $latestVital->spo2 . '%' : '—' }}</span>
                                    @if ($latestVital->status_flag === 'critical')
                                        <flux:badge color="red" size="sm">Critical Vitals</flux:badge>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 self-end md:self-center">
                        @if ($item->status === 'waiting')
                            <flux:button wire:click="callPatient({{ $item->id }})" variant="primary" size="sm" icon="megaphone">
                                Call Patient
                            </flux:button>
                        @elseif ($item->status === 'in_consultation')
                            <flux:button href="{{ route('patients.treatment-record', ['patient' => $item->patient_id]) }}" variant="filled" size="sm" icon="pencil-square" wire:navigate>
                                Open EHR Chart
                            </flux:button>
                            <flux:button wire:click="completeConsultation({{ $item->id }})" variant="filled" size="sm" icon="check">
                                Finish
                            </flux:button>
                        @endif
                        <flux:button wire:click="cancelQueue({{ $item->id }})" variant="ghost" size="sm" class="text-red-600 hover:text-red-700">
                            Remove
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-zinc-500">
                    <flux:icon name="check-circle" class="size-10 mx-auto text-green-500/50 mb-2" />
                    <p class="font-medium">No patients currently in consultation queue.</p>
                    <p class="text-xs text-zinc-400 mt-1">New scheduled or triaged patients will appear here automatically.</p>
                </div>
            @endforelse
        </div>
    </flux:card>
</div>
