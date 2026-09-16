<?php

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LabTestOrder;
use App\Models\Patient;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Ward;
use Carbon\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reports & Analytics - HMS')] class extends Component {
    public string $timeRange = '30_days'; // 7_days, 30_days, 90_days, year

    public function downloadCsvReport(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="hms_executive_summary_report.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Hospital Management System - Executive Performance Summary']);
            fputcsv($file, ['Generated At', now()->toDateTimeString()]);
            fputcsv($file, []);

            fputcsv($file, ['Metric', 'Total Value']);
            fputcsv($file, ['Total Registered Patients', Patient::count()]);
            fputcsv($file, ['Total Consultations/Appointments', Appointment::count()]);
            fputcsv($file, ['Total Inpatient Admissions', Admission::count()]);
            fputcsv($file, ['Total Laboratory Investigations', LabTestOrder::count()]);
            fputcsv($file, ['Total Invoiced Revenue (NGN)', number_format(Invoice::sum('total_amount'), 2)]);
            fputcsv($file, ['Total Collections (NGN)', number_format(Invoice::sum('paid_amount'), 2)]);
            fputcsv($file, ['Hospital Wallet Balance (NGN)', number_format(\App\Models\HospitalWallet::getSingleton()->balance, 2)]);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        $days = match($this->timeRange) {
            '7_days' => 7,
            '90_days' => 90,
            'year' => 365,
            default => 30,
        };
        $startDate = now()->subDays($days);

        $newPatientsCount = Patient::where('created_at', '>=', $startDate)->count();
        $admissionsCount = Admission::where('created_at', '>=', $startDate)->count();
        $dischargesCount = Admission::where('status', 'discharged')->where('updated_at', '>=', $startDate)->count();
        $labTestsCount = LabTestOrder::where('created_at', '>=', $startDate)->count();
        $consultationsCount = Appointment::where('created_at', '>=', $startDate)->count();
        $periodRevenue = Invoice::where('created_at', '>=', $startDate)->sum('paid_amount');

        // Department breakdown
        $deptWorkload = [
            'General Medicine' => Appointment::where('department', 'General Medicine')->count(),
            'Internal Medicine' => Appointment::where('department', 'Internal Medicine')->count(),
            'Diagnostic Pathology' => LabTestOrder::count(),
            'Inpatient Wards' => Admission::where('status', 'admitted')->count(),
        ];

        // Staff workload breakdown
        $doctorsList = User::where('role', 'doctor')->withCount(['appointments'])->get();
        $nursesList = User::where('role', 'nurse')->get();

        return view('pages.reports.⚡index', [
            'newPatientsCount' => $newPatientsCount,
            'admissionsCount' => $admissionsCount,
            'dischargesCount' => $dischargesCount,
            'labTestsCount' => $labTestsCount,
            'consultationsCount' => $consultationsCount,
            'periodRevenue' => $periodRevenue,
            'deptWorkload' => $deptWorkload,
            'doctorsList' => $doctorsList,
            'nursesList' => $nursesList,
            'wards' => Ward::with('beds')->get(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">System Reports & Healthcare Analytics</flux:heading>
            <flux:subheading>Clinical volume, patient inflow/outflow, department workload, and financial returns</flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            <flux:select wire:model.live="timeRange" class="w-44">
                <flux:select.option value="7_days">Last 7 Days</flux:select.option>
                <flux:select.option value="30_days">Last 30 Days</flux:select.option>
                <flux:select.option value="90_days">Last 90 Days</flux:select.option>
                <flux:select.option value="year">Past Year</flux:select.option>
            </flux:select>
            <flux:button wire:click="downloadCsvReport" variant="primary" icon="arrow-down-tray">
                Export CSV
            </flux:button>
        </div>
    </div>

    <!-- Overview Performance Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="p-4 border-l-4 border-blue-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Patient Inflow (Registrations)</div>
            <div class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">{{ number_format($newPatientsCount) }}</div>
            <div class="text-xs text-zinc-400 mt-1">New medical records created</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-indigo-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Admissions vs Discharges</div>
            <div class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-1">{{ $admissionsCount }} / {{ $dischargesCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Inpatient bed turnover</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-purple-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Diagnostic & Lab Load</div>
            <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">{{ number_format($labTestsCount) }}</div>
            <div class="text-xs text-zinc-400 mt-1">Completed lab investigations</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-green-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Settled Revenue</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">₦{{ number_format((float)$periodRevenue, 2) }}</div>
            <div class="text-xs text-zinc-400 mt-1">Direct receipts & remittances</div>
        </flux:card>
    </div>

    <!-- Department Workload & Ward Occupancy -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Department Workload -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Department Workload Distribution</flux:heading>
                <span class="text-xs text-zinc-400">Clinical activity index</span>
            </div>

            <div class="space-y-4">
                @foreach ($deptWorkload as $dept => $volume)
                    @php
                        $max = max(array_values($deptWorkload)) ?: 1;
                        $percent = min(100, round(($volume / $max) * 100));
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $dept }}</span>
                            <span class="font-mono text-zinc-600 dark:text-zinc-400">{{ $volume }} encounters</span>
                        </div>
                        <div class="w-full h-2.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-600 rounded-full" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <!-- Ward Inpatient Occupancy -->
        <flux:card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Ward Capacity & Utilization</flux:heading>
                <span class="text-xs text-zinc-400">Live bed metrics</span>
            </div>

            <div class="space-y-4">
                @foreach ($wards as $ward)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <div>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $ward->name }}</span>
                                <span class="text-xs text-zinc-400 block font-normal">{{ $ward->type }} (₦{{ number_format((float)$ward->daily_rate, 0) }}/day)</span>
                            </div>
                            <span class="font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $ward->occupied_beds_count }} / {{ $ward->beds->count() }} Beds ({{ $ward->occupancy_rate }}%)
                            </span>
                        </div>
                        <div class="w-full h-2.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full {{ $ward->occupancy_rate > 80 ? 'bg-red-500' : 'bg-green-500' }} rounded-full" style="width: {{ $ward->occupancy_rate }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>

    <!-- Staff Workload Table -->
    <flux:card class="p-5">
        <flux:heading size="lg" class="mb-4">Medical Staff Duty & Patient Encounter Logs</flux:heading>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Staff Member</th>
                        <th class="py-3 px-4">Designated Role</th>
                        <th class="py-3 px-4">Department</th>
                        <th class="py-3 px-4">Phone / Contact</th>
                        <th class="py-3 px-4 text-center">Encounter Volume</th>
                        <th class="py-3 px-4 text-right">Account Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @foreach ($doctorsList as $doc)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4 font-bold text-zinc-900 dark:text-zinc-100">{{ $doc->name }}</td>
                            <td class="py-3 px-4"><flux:badge color="blue" size="sm">Doctor</flux:badge></td>
                            <td class="py-3 px-4">{{ $doc->department ?? 'General' }}</td>
                            <td class="py-3 px-4 font-mono text-xs">{{ $doc->phone_number }}</td>
                            <td class="py-3 px-4 text-center font-mono font-bold">{{ $doc->appointments_count }} consultations</td>
                            <td class="py-3 px-4 text-right"><flux:badge color="green" size="sm">Active</flux:badge></td>
                        </tr>
                    @endforeach
                    @foreach ($nursesList as $nur)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4 font-bold text-zinc-900 dark:text-zinc-100">{{ $nur->name }}</td>
                            <td class="py-3 px-4"><flux:badge color="purple" size="sm">Nurse</flux:badge></td>
                            <td class="py-3 px-4">{{ $nur->department ?? 'Inpatient' }}</td>
                            <td class="py-3 px-4 font-mono text-xs">{{ $nur->phone_number }}</td>
                            <td class="py-3 px-4 text-center font-mono font-bold">—</td>
                            <td class="py-3 px-4 text-right"><flux:badge color="green" size="sm">Active</flux:badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
