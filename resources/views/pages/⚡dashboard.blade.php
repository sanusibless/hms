<?php

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Bed;
use App\Models\Drug;
use App\Models\HmsAlert;
use App\Models\HospitalWallet;
use App\Models\LabEquipmentLog;
use App\Models\LabResult;
use App\Models\LabTestOrder;
use App\Models\MedicationAdministrationRecord;
use App\Models\Patient;
use App\Models\PatientVital;
use App\Models\Prescription;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Ward;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hospital Command Center - Dashboard')] class extends Component {
    public string $activeRoleView = '';

    public function mount(): void
    {
        $this->activeRoleView = Auth::user()?->role ?? 'staff';
    }

    public function switchRoleView(string $role): void
    {
        if (Auth::user()?->isAdmin()) {
            $this->activeRoleView = $role;
        }
    }

    #[Computed]
    public function totalPatients(): int
    {
        return Patient::count();
    }

    #[Computed]
    public function totalWallets(): int
    {
        return Wallet::count();
    }

    #[Computed]
    public function totalWalletBalance(): string
    {
        $sum = Wallet::sum('balance');
        return '₦' . number_format((float)$sum, 2);
    }

    #[Computed]
    public function hospitalWallet(): HospitalWallet
    {
        return HospitalWallet::getSingleton();
    }

    #[Computed]
    public function todayPayments(): string
    {
        $sum = Transaction::where('type', 'debit')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        return '₦' . number_format((float)$sum, 2);
    }

    #[Computed]
    public function recentTransactions()
    {
        return Transaction::with(['patient', 'service'])
            ->latest()
            ->take(6)
            ->get();
    }

    #[Computed]
    public function todayAppointments()
    {
        return Appointment::with(['patient', 'doctor'])
            ->whereDate('appointment_date', Carbon::today())
            ->orderBy('appointment_date', 'asc')
            ->take(6)
            ->get();
    }

    #[Computed]
    public function todayAppointmentsCount(): int
    {
        return Appointment::whereDate('appointment_date', Carbon::today())->count();
    }

    #[Computed]
    public function waitingQueueCount(): int
    {
        return Appointment::whereDate('appointment_date', Carbon::today())
            ->whereIn('status', ['checked_in', 'scheduled'])
            ->count();
    }

    #[Computed]
    public function pendingLabsCount(): int
    {
        return LabTestOrder::whereIn('status', ['ordered', 'sample_collected', 'in_progress'])->count();
    }

    #[Computed]
    public function criticalLabAlertsCount(): int
    {
        return LabResult::where('flag', 'critical')->count();
    }

    #[Computed]
    public function activeInpatientsCount(): int
    {
        return Admission::where('status', 'admitted')->count();
    }

    #[Computed]
    public function bedOccupancyRate(): int
    {
        $total = Bed::count();
        if ($total === 0) return 0;
        $occupied = Bed::where('is_occupied', true)->count();
        return (int) round(($occupied / $total) * 100);
    }

    #[Computed]
    public function totalBeds(): int
    {
        return Bed::count();
    }

    #[Computed]
    public function occupiedBeds(): int
    {
        return Bed::where('is_occupied', true)->count();
    }

    #[Computed]
    public function dueMarCount(): int
    {
        return MedicationAdministrationRecord::whereDate('scheduled_time', Carbon::today())
            ->where('status', 'scheduled')
            ->count();
    }

    #[Computed]
    public function lowStockDrugsCount(): int
    {
        return Drug::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->count();
    }

    #[Computed]
    public function activeAlerts()
    {
        return HmsAlert::where('is_active', true)
            ->latest()
            ->take(4)
            ->get();
    }

    #[Computed]
    public function recentVitals()
    {
        return PatientVital::with(['patient', 'recordedBy'])
            ->latest('recorded_at')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentPrescriptions()
    {
        return Prescription::with(['patient', 'doctor', 'items.drug'])
            ->latest()
            ->take(5)
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Page Header & Quick Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">Hospital Command Center</flux:heading>
                @if (auth()->user()->role === 'admin')
                    <flux:badge color="indigo" size="sm">Executive Admin</flux:badge>
                @elseif (auth()->user()->role === 'doctor')
                    <flux:badge color="sky" size="sm">Doctor Portal</flux:badge>
                @elseif (auth()->user()->role === 'nurse')
                    <flux:badge color="emerald" size="sm">Nursing Station</flux:badge>
                @elseif (auth()->user()->role === 'lab_tech')
                    <flux:badge color="amber" size="sm">Laboratory Bench</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Billing & Cashier</flux:badge>
                @endif
            </div>
            <flux:subheading>Welcome, {{ auth()->user()->name }} &bull; Department: {{ auth()->user()->department ?? 'General' }}</flux:subheading>
        </div>

        <!-- Role Quick Switcher for Admin & Action shortcuts -->
        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()->isAdmin())
                <div class="flex items-center bg-zinc-100 dark:bg-zinc-800 p-1 rounded-lg border border-zinc-200 dark:border-zinc-700 text-xs mr-2">
                    <span class="text-zinc-400 font-semibold px-2">Perspective:</span>
                    <button wire:click="switchRoleView('admin')" class="px-2.5 py-1 rounded font-medium {{ $activeRoleView === 'admin' ? 'bg-indigo-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">All</button>
                    <button wire:click="switchRoleView('doctor')" class="px-2.5 py-1 rounded font-medium {{ $activeRoleView === 'doctor' ? 'bg-indigo-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">Doctor</button>
                    <button wire:click="switchRoleView('nurse')" class="px-2.5 py-1 rounded font-medium {{ $activeRoleView === 'nurse' ? 'bg-indigo-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">Nurse</button>
                    <button wire:click="switchRoleView('lab_tech')" class="px-2.5 py-1 rounded font-medium {{ $activeRoleView === 'lab_tech' ? 'bg-indigo-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">Lab</button>
                    <button wire:click="switchRoleView('staff')" class="px-2.5 py-1 rounded font-medium {{ $activeRoleView === 'staff' ? 'bg-indigo-600 text-white shadow-sm' : 'text-zinc-600 dark:text-zinc-300 hover:text-zinc-900' }}">Billing</button>
                </div>
            @endif

            <flux:button href="{{ route('patients.index') }}" icon="user-plus" variant="filled" size="sm" wire:navigate>
                New Patient
            </flux:button>
            <flux:button href="{{ route('payments.create') }}" icon="credit-card" variant="primary" size="sm" wire:navigate>
                Make Payment
            </flux:button>
        </div>
    </div>

    <!-- Active System Alerts Banner if any -->
    @if ($this->activeAlerts->isNotEmpty())
        <div class="flex flex-col gap-2">
            @foreach ($this->activeAlerts as $alert)
                <div class="flex items-center justify-between px-4 py-2.5 rounded-lg border {{ $alert->severity === 'critical' ? 'bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900 text-red-900 dark:text-red-200' : ($alert->severity === 'warning' ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900 text-amber-900 dark:text-amber-200' : 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-900 text-blue-900 dark:text-blue-200') }}">
                    <div class="flex items-center gap-3">
                        <flux:icon name="{{ $alert->severity === 'critical' ? 'exclamation-circle' : 'bell' }}" class="size-5 shrink-0" />
                        <div>
                            <span class="font-bold text-sm">{{ $alert->title }}:</span>
                            <span class="text-sm opacity-90 ml-1">{{ $alert->message }}</span>
                        </div>
                    </div>
                    <flux:button href="{{ route('alerts.index') }}" size="xs" variant="ghost" wire:navigate>
                        View Alerts &rarr;
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif

    <!-- ADMIN / OVERVIEW METRICS GRID -->
    @if (in_array($activeRoleView, ['admin', 'staff']))
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <!-- Total Patients -->
            <flux:card class="p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                    <span class="text-sm font-medium">Total Patients</span>
                    <flux:icon name="users" class="size-5 text-blue-500" />
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ number_format($this->totalPatients) }}
                    </div>
                    <div class="text-xs text-zinc-500 mt-1">Registered hospital files</div>
                </div>
            </flux:card>

            <!-- Total Wallets -->
            <flux:card class="p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                    <span class="text-sm font-medium">Total Wallets</span>
                    <flux:icon name="identification" class="size-5 text-indigo-500" />
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ number_format($this->totalWallets) }}
                    </div>
                    <div class="text-xs text-zinc-500 mt-1">Active patient accounts</div>
                </div>
            </flux:card>

            <!-- Total Patient Wallet Balance -->
            <flux:card class="p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                    <span class="text-sm font-medium">Total Wallet Balance</span>
                    <flux:icon name="banknotes" class="size-5 text-teal-500" />
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ $this->totalWalletBalance }}
                    </div>
                    <div class="text-xs text-zinc-500 mt-1">Patient deposit pool</div>
                </div>
            </flux:card>

            <!-- Hospital Wallet Balance -->
            <flux:card class="p-4 flex flex-col justify-between border-green-200 dark:border-green-900 bg-green-50/40 dark:bg-green-950/20">
                <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                    <span class="text-sm font-semibold text-green-700 dark:text-green-400">Hospital Wallet</span>
                    <flux:icon name="wallet" class="size-5 text-green-600 dark:text-green-400" />
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-extrabold text-green-700 dark:text-green-300">
                        {{ $this->hospitalWallet->formatted_balance }}
                    </div>
                    <div class="text-xs text-green-600 dark:text-green-500 mt-1">Revenue Pool</div>
                </div>
            </flux:card>

            <!-- Today's Payments -->
            <flux:card class="p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                    <span class="text-sm font-medium">Today's Collections</span>
                    <flux:icon name="calendar" class="size-5 text-amber-500" />
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        {{ $this->todayPayments }}
                    </div>
                    <div class="text-xs text-zinc-500 mt-1">Paid services today</div>
                </div>
            </flux:card>
        </div>
    @endif

    <!-- CLINICAL / DOCTOR METRICS GRID -->
    @if (in_array($activeRoleView, ['admin', 'doctor']))
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <flux:card class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase font-semibold text-zinc-500">Today's Appointments</div>
                    <div class="text-2xl font-bold mt-1 text-zinc-900 dark:text-zinc-100">{{ $this->todayAppointmentsCount }}</div>
                    <div class="text-xs text-zinc-400 mt-0.5">Consultations scheduled</div>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 rounded-xl">
                    <flux:icon name="calendar" class="size-6" />
                </div>
            </flux:card>

            <flux:card class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase font-semibold text-zinc-500">Triage Queue</div>
                    <div class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ $this->waitingQueueCount }}</div>
                    <div class="text-xs text-zinc-400 mt-0.5">Patients waiting in clinic</div>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-900/30 text-amber-600 rounded-xl">
                    <flux:icon name="clock" class="size-6" />
                </div>
            </flux:card>

            <flux:card class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase font-semibold text-zinc-500">Pending Lab Tests</div>
                    <div class="text-2xl font-bold mt-1 text-purple-600 dark:text-purple-400">{{ $this->pendingLabsCount }}</div>
                    <div class="text-xs text-zinc-400 mt-0.5">Awaiting pathology report</div>
                </div>
                <div class="p-3 bg-purple-50 dark:bg-purple-900/30 text-purple-600 rounded-xl">
                    <flux:icon name="beaker" class="size-6" />
                </div>
            </flux:card>

            <flux:card class="p-4 flex items-center justify-between {{ $this->criticalLabAlertsCount > 0 ? 'border-red-300 dark:border-red-900 bg-red-50/30' : '' }}">
                <div>
                    <div class="text-xs uppercase font-semibold text-zinc-500">Critical Lab Flags</div>
                    <div class="text-2xl font-bold mt-1 {{ $this->criticalLabAlertsCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ $this->criticalLabAlertsCount }}
                    </div>
                    <div class="text-xs text-zinc-400 mt-0.5">Urgent clinical review</div>
                </div>
                <div class="p-3 {{ $this->criticalLabAlertsCount > 0 ? 'bg-red-100 dark:bg-red-900/40 text-red-600' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600' }} rounded-xl">
                    <flux:icon name="exclamation-circle" class="size-6" />
                </div>
            </flux:card>
        </div>
    @endif

    <!-- INPATIENT / NURSING METRICS GRID -->
    @if (in_array($activeRoleView, ['admin', 'nurse']))
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <flux:card class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase font-semibold text-zinc-500">Bed Occupancy Rate</div>
                    <div class="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-400">{{ $this->bedOccupancyRate }}%</div>
                    <div class="text-xs text-zinc-400 mt-0.5">{{ $this->occupiedBeds }} of {{ $this->totalBeds }} beds occupied</div>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 rounded-xl">
                    <flux:icon name="building-office-2" class="size-6" />
                </div>
            </flux:card>

            <flux:card class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase font-semibold text-zinc-500">Current Inpatients</div>
                    <div class="text-2xl font-bold mt-1 text-zinc-900 dark:text-zinc-100">{{ $this->activeInpatientsCount }}</div>
                    <div class="text-xs text-zinc-400 mt-0.5">Admitted in wards</div>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 rounded-xl">
                    <flux:icon name="user-group" class="size-6" />
                </div>
            </flux:card>

            <flux:card class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase font-semibold text-zinc-500">e-MAR Doses Due Today</div>
                    <div class="text-2xl font-bold mt-1 text-indigo-600 dark:text-indigo-400">{{ $this->dueMarCount }}</div>
                    <div class="text-xs text-zinc-400 mt-0.5">Medications scheduled</div>
                </div>
                <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 rounded-xl">
                    <flux:icon name="shield-check" class="size-6" />
                </div>
            </flux:card>

            <flux:card class="p-4 flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase font-semibold text-zinc-500">Low Drug Stock Alerts</div>
                    <div class="text-2xl font-bold mt-1 {{ $this->lowStockDrugsCount > 0 ? 'text-amber-600' : 'text-zinc-900' }}">{{ $this->lowStockDrugsCount }}</div>
                    <div class="text-xs text-zinc-400 mt-0.5">Below reorder threshold</div>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-900/30 text-amber-600 rounded-xl">
                    <flux:icon name="cube" class="size-6" />
                </div>
            </flux:card>
        </div>
    @endif

    <!-- QUICK ACTIONS LAUNCHPAD -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <flux:button href="{{ route('appointments.queue') }}" variant="filled" class="flex flex-col items-center justify-center py-3 h-auto" wire:navigate>
            <flux:icon name="clock" class="size-5 mb-1 text-amber-500" />
            <span class="text-xs font-medium">Triage Queue</span>
        </flux:button>
        <flux:button href="{{ route('wards.index') }}" variant="filled" class="flex flex-col items-center justify-center py-3 h-auto" wire:navigate>
            <flux:icon name="building-office-2" class="size-5 mb-1 text-emerald-500" />
            <span class="text-xs font-medium">Wards & Beds</span>
        </flux:button>
        <flux:button href="{{ route('ehr.index') }}" variant="filled" class="flex flex-col items-center justify-center py-3 h-auto" wire:navigate>
            <flux:icon name="document-text" class="size-5 mb-1 text-blue-500" />
            <span class="text-xs font-medium">Health Records</span>
        </flux:button>
        <flux:button href="{{ route('lab.orders') }}" variant="filled" class="flex flex-col items-center justify-center py-3 h-auto" wire:navigate>
            <flux:icon name="beaker" class="size-5 mb-1 text-purple-500" />
            <span class="text-xs font-medium">Lab Orders</span>
        </flux:button>
        <flux:button href="{{ route('pharmacy.prescriptions') }}" variant="filled" class="flex flex-col items-center justify-center py-3 h-auto" wire:navigate>
            <flux:icon name="queue-list" class="size-5 mb-1 text-indigo-500" />
            <span class="text-xs font-medium">Prescriptions</span>
        </flux:button>
        <flux:button href="{{ route('reports.index') }}" variant="filled" class="flex flex-col items-center justify-center py-3 h-auto" wire:navigate>
            <flux:icon name="chart-bar" class="size-5 mb-1 text-teal-500" />
            <span class="text-xs font-medium">Analytics & Reports</span>
        </flux:button>
    </div>

    <!-- MAIN TWO COLUMN WORKSPACE -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column: Appointments & Vitals -->
        <div class="space-y-6">
            <!-- Today's Consultations & Clinic Appointments -->
            <flux:card class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Today's Clinic Consultations</flux:heading>
                        <flux:subheading>Scheduled doctor appointments and waiting triage</flux:subheading>
                    </div>
                    <flux:button href="{{ route('appointments.index') }}" variant="ghost" size="sm" icon-trailing="arrow-right" wire:navigate>
                        Schedule
                    </flux:button>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($this->todayAppointments as $apt)
                        <div class="py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900/40 text-blue-600 flex items-center justify-center font-bold text-xs">
                                    {{ $apt->queue_number ?? '#' }}
                                </div>
                                <div>
                                    <a href="{{ route('patients.show', $apt->patient) }}" class="font-semibold text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 hover:underline" wire:navigate>
                                        {{ $apt->patient?->full_name }}
                                    </a>
                                    <div class="text-xs text-zinc-500">
                                        {{ $apt->appointment_date?->format('h:i A') }} &bull; {{ $apt->doctor?->name ?? 'Any Doctor' }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($apt->status === 'checked_in')
                                    <flux:badge color="amber" size="sm">Checked In</flux:badge>
                                @elseif ($apt->status === 'completed')
                                    <flux:badge color="green" size="sm">Completed</flux:badge>
                                @else
                                    <flux:badge color="blue" size="sm">{{ ucfirst($apt->status) }}</flux:badge>
                                @endif
                                <flux:button href="{{ route('patients.treatment-record', $apt->patient) }}" size="xs" variant="outline" wire:navigate>
                                    Chart &rarr;
                                </flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-zinc-500 text-sm">
                            No appointments scheduled for today.
                        </div>
                    @endforelse
                </div>
            </flux:card>

            <!-- Recent Patient Vitals Logged -->
            <flux:card class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Recent Vital Signs</flux:heading>
                        <flux:subheading>Triaged and inpatient vital observations</flux:subheading>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-zinc-600 dark:text-zinc-300">
                        <thead class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 uppercase">
                            <tr>
                                <th class="py-2 px-3">Patient</th>
                                <th class="py-2 px-3">BP</th>
                                <th class="py-2 px-3">Pulse</th>
                                <th class="py-2 px-3">Temp</th>
                                <th class="py-2 px-3">SpO2</th>
                                <th class="py-2 px-3 text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                            @forelse ($this->recentVitals as $vital)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2.5 px-3 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $vital->patient?->full_name }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono font-semibold">{{ $vital->bp_reading ?? '—' }}</td>
                                    <td class="py-2.5 px-3 font-mono">{{ $vital->pulse_rate ? $vital->pulse_rate.' bpm' : '—' }}</td>
                                    <td class="py-2.5 px-3 font-mono">{{ $vital->temperature ? $vital->temperature.' °C' : '—' }}</td>
                                    <td class="py-2.5 px-3 font-mono">{{ $vital->respiratory_rate ? $vital->respiratory_rate.'%' : '—' }}</td>
                                    <td class="py-2.5 px-3 text-right text-zinc-400">{{ $vital->recorded_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-center text-zinc-500">No vitals logged yet today.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:card>
        </div>

        <!-- Right Column: Financials & Recent Transactions -->
        <div class="space-y-6">
            <!-- Recent Transactions Table (Page 5 Requirements) -->
            <flux:card class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Recent Financial Transactions</flux:heading>
                        <flux:subheading>Patient wallet fundings and hospital payments</flux:subheading>
                    </div>
                    <flux:button href="{{ route('transactions.index') }}" variant="ghost" size="sm" icon-trailing="arrow-right" wire:navigate>
                        Ledger
                    </flux:button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                        <thead class="border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                            <tr>
                                <th class="py-3 px-3">Transaction</th>
                                <th class="py-3 px-3">Patient</th>
                                <th class="py-3 px-3">Type</th>
                                <th class="py-3 px-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                            @forelse ($this->recentTransactions as $txn)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 text-xs">
                                    <td class="py-3 px-3">
                                        <div class="font-mono font-semibold text-zinc-700 dark:text-zinc-300">{{ $txn->transaction_id }}</div>
                                        <div class="text-zinc-400">{{ $txn->created_at->format('d M, h:i A') }}</div>
                                    </td>
                                    <td class="py-3 px-3 font-medium">
                                        @if ($txn->patient)
                                            <a href="{{ route('patients.show', $txn->patient) }}" class="text-blue-600 dark:text-blue-400 hover:underline" wire:navigate>
                                                {{ $txn->patient->full_name }}
                                            </a>
                                        @else
                                            <span class="text-zinc-400">Direct Operation</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3">
                                        @if ($txn->type === 'credit')
                                            <flux:badge color="green" size="xs">Credit</flux:badge>
                                        @else
                                            <flux:badge color="amber" size="xs">Debit</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right font-semibold {{ $txn->type === 'credit' ? 'text-green-600 dark:text-green-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                        {{ $txn->type === 'credit' ? '+' : '-' }}{{ $txn->formatted_amount }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-zinc-500">
                                        No transactions recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </flux:card>

            <!-- Quick Flow Banner matching Page 5 Requirements -->
            <flux:card class="p-5 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-zinc-900 dark:to-zinc-800 border-blue-100 dark:border-zinc-700">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <flux:heading size="base" class="text-blue-900 dark:text-blue-300 font-semibold">Standard Patient Payment Journey</flux:heading>
                        <flux:text class="text-xs mt-1 text-blue-800/80 dark:text-zinc-300">
                            Create Patient → Auto Wallet Generated → Fund Patient Wallet → Pay for Hospital Service → Main Wallet Credited
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <flux:button href="{{ route('patients.index') }}" size="xs" variant="outline" wire:navigate>
                            Patients
                        </flux:button>
                        <flux:button href="{{ route('wallet.main') }}" size="xs" variant="outline" wire:navigate>
                            Main Wallet
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>

