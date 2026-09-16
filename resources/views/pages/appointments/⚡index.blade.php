<?php

use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Appointments & Scheduling - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public bool $showBookModal = false;
    public ?int $patient_id = null;
    public ?int $doctor_id = null;
    public string $department = 'General Medicine';
    public string $scheduled_at = '';
    public string $priority = 'routine';
    public string $reason = '';
    public string $notes = '';

    public function mount(): void
    {
        $this->scheduled_at = now()->addHour()->format('Y-m-d\TH:i');
        $firstDoc = User::where('role', 'doctor')->first();
        if ($firstDoc) {
            $this->doctor_id = $firstDoc->id;
        }
    }

    public function openBookModal(?int $patientId = null): void
    {
        $this->patient_id = $patientId ?? Patient::first()?->id;
        $this->scheduled_at = now()->addHour()->format('Y-m-d\TH:i');
        $this->priority = 'routine';
        $this->reason = '';
        $this->notes = '';
        $this->showBookModal = true;
    }

    public function bookAppointment(): void
    {
        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'nullable|exists:users,id',
            'department' => 'required|string|max:100',
            'scheduled_at' => 'required|date',
            'priority' => 'required|in:routine,urgent,emergency',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:500',
        ]);

        $maxQueue = Appointment::whereDate('scheduled_at', date('Y-m-d', strtotime($this->scheduled_at)))->max('queue_number') ?? 0;

        $apt = Appointment::create([
            'appointment_number' => Appointment::generateAppointmentNumber(),
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'department' => $this->department,
            'scheduled_at' => $this->scheduled_at,
            'status' => 'scheduled',
            'priority' => $this->priority,
            'queue_number' => $maxQueue + 1,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'booked_by' => Auth::id(),
        ]);

        AuditService::log('create', 'appointments', (string)$apt->id, "Booked appointment {$apt->appointment_number} for patient #{$this->patient_id}");

        $this->showBookModal = false;
        Flux::toast(variant: 'success', text: "Appointment {$apt->appointment_number} scheduled successfully.");
    }

    public function updateStatus(int $appointmentId, string $newStatus): void
    {
        $apt = Appointment::findOrFail($appointmentId);
        $apt->update(['status' => $newStatus]);

        AuditService::log('update', 'appointments', (string)$apt->id, "Updated appointment {$apt->appointment_number} status to {$newStatus}");
        Flux::toast(variant: 'info', text: "Appointment status updated to {$newStatus}.");
    }

    public function render()
    {
        $query = Appointment::with(['patient', 'doctor', 'bookedBy'])->latest('scheduled_at');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('appointment_number', 'like', "%{$this->search}%")
                  ->orWhere('reason', 'like', "%{$this->search}%")
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

        return view('pages.appointments.⚡index', [
            'appointments' => $query->paginate(10),
            'doctors' => User::where('role', 'doctor')->get(),
            'patients' => Patient::orderBy('first_name')->take(50)->get(),
            'availabilities' => DoctorAvailability::with('doctor')->where('is_available', true)->get(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Appointments & Scheduling</flux:heading>
            <flux:subheading>Manage patient consultations, doctor calendars, and front-desk booking</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('appointments.queue') }}" variant="filled" icon="queue-list" wire:navigate>
                Triage Queue
            </flux:button>
            <flux:button wire:click="openBookModal" variant="primary" icon="plus">
                Book Appointment
            </flux:button>
        </div>
    </div>

    <!-- Doctor Availability Summary Cards -->
    <flux:card class="p-5">
        <div class="flex items-center justify-between mb-3">
            <flux:heading size="lg">Doctor Availability Schedule</flux:heading>
            <span class="text-xs text-zinc-500">Weekly Duty Roster</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
            @forelse ($availabilities as $avail)
                <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                    <div class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wide">{{ $avail->day_of_week }}</div>
                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $avail->doctor->name }}</div>
                    <div class="text-xs text-zinc-500 mt-0.5">
                        {{ date('h:i A', strtotime($avail->start_time)) }} - {{ date('h:i A', strtotime($avail->end_time)) }}
                    </div>
                </div>
            @empty
                <div class="col-span-5 text-sm text-zinc-500 italic py-2">No active doctor schedules recorded.</div>
            @endforelse
        </div>
    </flux:card>

    <!-- Appointment Table & Filters -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by patient, file no, or apt #" icon="magnifying-glass" class="w-full sm:w-72" />
                <flux:select wire:model.live="statusFilter" class="w-40">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    <flux:select.option value="scheduled">Scheduled</flux:select.option>
                    <flux:select.option value="waiting">Waiting</flux:select.option>
                    <flux:select.option value="in_consultation">In Consultation</flux:select.option>
                    <flux:select.option value="completed">Completed</flux:select.option>
                    <flux:select.option value="cancelled">Cancelled</flux:select.option>
                </flux:select>
            </div>
            <div class="text-xs text-zinc-500">
                Showing {{ $appointments->total() }} appointments
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Apt #</th>
                        <th class="py-3 px-4">Patient</th>
                        <th class="py-3 px-4">Doctor / Dept</th>
                        <th class="py-3 px-4">Date & Time</th>
                        <th class="py-3 px-4">Priority</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($appointments as $apt)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4 font-mono text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $apt->appointment_number }}
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('patients.show', ['patient' => $apt->patient_id]) }}" class="font-medium text-blue-600 hover:underline">
                                    {{ $apt->patient->full_name }}
                                </a>
                                <span class="block text-xs text-zinc-400 font-mono">{{ $apt->patient->file_number }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $apt->doctor?->name ?? 'Unassigned' }}</div>
                                <div class="text-xs text-zinc-500">{{ $apt->department }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium">{{ $apt->scheduled_at->format('d M Y') }}</div>
                                <div class="text-xs text-zinc-400">{{ $apt->scheduled_at->format('h:i A') }}</div>
                            </td>
                            <td class="py-3 px-4">
                                @if ($apt->priority === 'emergency')
                                    <flux:badge color="red" size="sm">Emergency</flux:badge>
                                @elseif ($apt->priority === 'urgent')
                                    <flux:badge color="amber" size="sm">Urgent</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Routine</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($apt->status === 'completed')
                                    <flux:badge color="green" size="sm">Completed</flux:badge>
                                @elseif ($apt->status === 'in_consultation')
                                    <flux:badge color="blue" size="sm">In Consultation</flux:badge>
                                @elseif ($apt->status === 'waiting')
                                    <flux:badge color="amber" size="sm">Waiting</flux:badge>
                                @elseif ($apt->status === 'cancelled')
                                    <flux:badge color="zinc" size="sm">Cancelled</flux:badge>
                                @else
                                    <flux:badge color="indigo" size="sm">Scheduled</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($apt->status === 'scheduled')
                                        <flux:button wire:click="updateStatus({{ $apt->id }}, 'waiting')" size="xs" variant="ghost">Queue</flux:button>
                                    @elseif ($apt->status === 'waiting')
                                        <flux:button wire:click="updateStatus({{ $apt->id }}, 'in_consultation')" size="xs" variant="primary">Start</flux:button>
                                    @elseif ($apt->status === 'in_consultation')
                                        <flux:button wire:click="updateStatus({{ $apt->id }}, 'completed')" size="xs" variant="filled">Complete</flux:button>
                                    @endif
                                    <flux:button href="{{ route('patients.treatment-record', ['patient' => $apt->patient_id]) }}" size="xs" variant="ghost" icon="document-text" wire:navigate title="EHR Chart" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">No appointments found matching your criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $appointments->links() }}</div>
    </flux:card>

    <!-- Book Appointment Modal -->
    <flux:modal wire:model="showBookModal" class="md:w-[500px]">
        <form wire:submit.prevent="bookAppointment" class="space-y-4">
            <div>
                <flux:heading size="lg">Book Patient Appointment</flux:heading>
                <flux:subheading>Schedule an outpatient consultation or specialist visit</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Select Patient</flux:label>
                <flux:select wire:model="patient_id">
                    @foreach ($patients as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->full_name }} ({{ $p->file_number }})</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Doctor</flux:label>
                    <flux:select wire:model="doctor_id">
                        <flux:select.option value="">Any Available</flux:select.option>
                        @foreach ($doctors as $d)
                            <flux:select.option value="{{ $d->id }}">{{ $d->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Department</flux:label>
                    <flux:select wire:model="department">
                        <flux:select.option value="General Medicine">General Medicine</flux:select.option>
                        <flux:select.option value="Internal Medicine">Internal Medicine</flux:select.option>
                        <flux:select.option value="Pediatrics">Pediatrics</flux:select.option>
                        <flux:select.option value="Surgery">Surgery</flux:select.option>
                        <flux:select.option value="Obstetrics & Gynae">Obstetrics & Gynae</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Date & Time</flux:label>
                    <flux:input type="datetime-local" wire:model="scheduled_at" required />
                </flux:field>

                <flux:field>
                    <flux:label>Priority Level</flux:label>
                    <flux:select wire:model="priority">
                        <flux:select.option value="routine">Routine</flux:select.option>
                        <flux:select.option value="urgent">Urgent</flux:select.option>
                        <flux:select.option value="emergency">Emergency</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Reason for Visit</flux:label>
                <flux:textarea wire:model="reason" placeholder="Symptoms or chief complaint" rows="2" required />
            </flux:field>

            <flux:field>
                <flux:label>Special Instructions / Notes</flux:label>
                <flux:input wire:model="notes" placeholder="Optional clinical triage notes" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showBookModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Schedule Appointment</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
