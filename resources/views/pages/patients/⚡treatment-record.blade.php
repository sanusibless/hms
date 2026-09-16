<?php

use App\Models\Drug;
use App\Models\HospitalService;
use App\Models\LabTestOrder;
use App\Models\Patient;
use App\Models\PatientHealthRecord;
use App\Models\PatientVital;
use App\Models\Prescription;
use App\Models\Ward;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Digital Clinical Chart & EHR - HMS')] class extends Component {
    public Patient $patient;

    // New Clinical Visit Record Form
    public bool $showNewConsultModal = false;
    public string $visit_type = 'outpatient';
    public string $icd_code = '';
    public string $chief_complaint = '';
    public string $diagnosis = '';
    public string $treatment_plan = '';
    public string $clinical_notes = '';
    public string $allergies = '';
    public string $chronic_conditions = '';

    // E-Prescription Form
    public bool $showRxModal = false;
    public ?int $selectedDrugId = null;
    public string $rxDrugName = '';
    public string $rxDosage = '500mg';
    public string $rxFrequency = 'TDS';
    public string $rxDuration = '5 days';
    public string $rxInstructions = '';
    public int $rxQuantity = 1;

    // Lab Order Form
    public bool $showLabModal = false;
    public string $labTestName = '';
    public string $labPriority = 'routine';
    public string $labSampleType = 'Blood';
    public string $labNotes = '';

    // Record Vitals Form
    public bool $showVitalsModal = false;
    public string $bp = '';
    public string $temp = '';
    public string $pulse = '';
    public string $spo2 = '';
    public string $resp = '';
    public string $weight = '';
    public string $sugar = '';
    public string $vitalNotes = '';

    public function mount(Patient $patient): void
    {
        $this->patient = $patient->load([
            'healthRecords.doctor',
            'vitals.recordedBy',
            'labOrders.results',
            'prescriptions.items',
            'admissions.ward',
            'currentWard',
            'currentBed',
        ]);
        $this->allergies = $patient->allergies ?? '';
        $this->chronic_conditions = $patient->chronic_conditions ?? '';
    }

    public function openConsultModal(): void
    {
        $this->reset(['icd_code', 'chief_complaint', 'diagnosis', 'treatment_plan', 'clinical_notes']);
        $this->visit_type = 'outpatient';
        $this->allergies = $this->patient->allergies ?? '';
        $this->chronic_conditions = $this->patient->chronic_conditions ?? '';
        $this->showNewConsultModal = true;
    }

    public function saveConsultation(): void
    {
        $this->validate([
            'visit_type' => 'required|string',
            'icd_code' => 'nullable|string|max:50',
            'chief_complaint' => 'required|string|max:500',
            'diagnosis' => 'required|string|max:500',
            'treatment_plan' => 'nullable|string|max:1000',
            'clinical_notes' => 'nullable|string|max:1000',
        ]);

        // Update patient baseline allergies & chronic conditions
        $this->patient->update([
            'allergies' => $this->allergies,
            'chronic_conditions' => $this->chronic_conditions,
        ]);

        $record = PatientHealthRecord::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => Auth::id(),
            'visit_type' => $this->visit_type,
            'icd_code' => $this->icd_code,
            'chief_complaint' => $this->chief_complaint,
            'diagnosis' => $this->diagnosis,
            'treatment' => $this->treatment_plan,
            'treatment_plan' => $this->treatment_plan,
            'clinical_notes' => $this->clinical_notes,
            'allergies' => $this->allergies,
            'chronic_conditions' => $this->chronic_conditions,
            'version' => ($this->patient->healthRecords->max('version') ?? 0) + 1,
        ]);

        AuditService::log('create', 'ehr', (string)$record->id, "Recorded consultation and clinical notes for patient {$this->patient->full_name}");

        $this->patient->load(['healthRecords.doctor']);
        $this->showNewConsultModal = false;
        Flux::toast(variant: 'success', text: 'Clinical consultation recorded.');
    }

    public function openRxModal(): void
    {
        $this->reset(['rxDrugName', 'rxInstructions']);
        $firstDrug = Drug::where('stock_quantity', '>', 0)->first();
        if ($firstDrug) {
            $this->selectedDrugId = $firstDrug->id;
            $this->rxDrugName = $firstDrug->name;
            $this->rxDosage = $firstDrug->strength ?: '500mg';
        }
        $this->rxFrequency = 'TDS';
        $this->rxDuration = '5 days';
        $this->rxQuantity = 1;
        $this->showRxModal = true;
    }

    public function updatedSelectedDrugId($val): void
    {
        $drug = Drug::find($val);
        if ($drug) {
            $this->rxDrugName = $drug->name;
            $this->rxDosage = $drug->strength ?: '500mg';
        }
    }

    public function createPrescription(): void
    {
        $this->validate([
            'rxDrugName' => 'required|string|max:150',
            'rxDosage' => 'required|string|max:100',
            'rxFrequency' => 'required|string|max:50',
            'rxDuration' => 'required|string|max:50',
            'rxQuantity' => 'required|integer|min:1',
            'rxInstructions' => 'nullable|string|max:250',
        ]);

        $rx = Prescription::create([
            'prescription_number' => Prescription::generatePrescriptionNumber(),
            'patient_id' => $this->patient->id,
            'doctor_id' => Auth::id(),
            'diagnosis' => $this->patient->healthRecords->first()?->diagnosis ?? 'Clinical Evaluation',
            'status' => 'pending',
        ]);

        $drug = $this->selectedDrugId ? Drug::find($this->selectedDrugId) : null;

        $rx->items()->create([
            'drug_id' => $drug?->id,
            'drug_name' => $this->rxDrugName,
            'dosage' => $this->rxDosage,
            'frequency' => $this->rxFrequency,
            'duration' => $this->rxDuration,
            'instructions' => $this->rxInstructions,
            'quantity' => $this->rxQuantity,
            'unit_price' => $drug?->unit_price ?? 0.00,
            'is_dispensed' => false,
        ]);

        AuditService::log('create', 'pharmacy', (string)$rx->id, "Prescribed {$this->rxDrugName} for {$this->patient->full_name}");

        $this->patient->load('prescriptions.items');
        $this->showRxModal = false;
        Flux::toast(variant: 'success', text: "E-Prescription {$rx->prescription_number} sent to pharmacy.");
    }

    public function openLabModal(): void
    {
        $this->reset(['labTestName', 'labNotes']);
        $this->labPriority = 'routine';
        $this->labSampleType = 'Blood';
        $this->showLabModal = true;
    }

    public function createLabOrder(): void
    {
        $this->validate([
            'labTestName' => 'required|string|max:150',
            'labPriority' => 'required|in:routine,urgent,stat',
            'labSampleType' => 'required|string',
            'labNotes' => 'nullable|string|max:500',
        ]);

        $order = LabTestOrder::create([
            'order_number' => LabTestOrder::generateOrderNumber(),
            'patient_id' => $this->patient->id,
            'doctor_id' => Auth::id(),
            'test_name' => $this->labTestName,
            'priority' => $this->labPriority,
            'sample_type' => $this->labSampleType,
            'clinical_notes' => $this->labNotes,
            'status' => 'ordered',
        ]);

        AuditService::log('create', 'lab', (string)$order->id, "Ordered lab investigation {$order->test_name} for {$this->patient->full_name}");

        $this->patient->load('labOrders.results');
        $this->showLabModal = false;
        Flux::toast(variant: 'success', text: "Laboratory order {$order->order_number} submitted.");
    }

    public function openVitalsModal(): void
    {
        $this->reset(['bp', 'temp', 'pulse', 'spo2', 'resp', 'weight', 'sugar', 'vitalNotes']);
        $this->showVitalsModal = true;
    }

    public function recordVitals(): void
    {
        $this->validate([
            'bp' => 'nullable|string|max:20',
            'temp' => 'nullable|numeric|min:30|max:45',
            'pulse' => 'nullable|integer|min:30|max:250',
            'spo2' => 'nullable|integer|min:50|max:100',
            'resp' => 'nullable|integer|min:5|max:60',
            'weight' => 'nullable|numeric|min:1|max:300',
        ]);

        $statusFlag = 'stable';
        if (($this->temp && (float)$this->temp >= 39.0) || ($this->spo2 && (int)$this->spo2 < 93)) {
            $statusFlag = 'critical';
        } elseif (($this->temp && (float)$this->temp >= 38.0) || ($this->spo2 && (int)$this->spo2 < 95)) {
            $statusFlag = 'guarded';
        }

        $vital = PatientVital::create([
            'patient_id' => $this->patient->id,
            'recorded_by' => Auth::id(),
            'blood_pressure' => $this->bp ?: null,
            'temperature' => $this->temp ?: null,
            'pulse_rate' => $this->pulse ?: null,
            'respiratory_rate' => $this->resp ?: null,
            'spo2' => $this->spo2 ?: null,
            'weight' => $this->weight ?: null,
            'blood_sugar' => $this->sugar ?: null,
            'status_flag' => $statusFlag,
            'notes' => $this->vitalNotes ?: null,
            'recorded_at' => now(),
        ]);

        AuditService::log('create', 'ehr', (string)$vital->id, "Recorded patient vitals (Status: {$statusFlag}) for {$this->patient->full_name}");

        $this->patient->load('vitals.recordedBy');
        $this->showVitalsModal = false;
        Flux::toast(variant: 'success', text: 'Vital signs successfully saved to chart.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Breadcrumb & Back -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('patients.show', ['patient' => $patient->id]) }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                Patient Profile & Wallet
            </flux:button>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="openVitalsModal" variant="filled" icon="heart">
                Record Vitals
            </flux:button>
            <flux:button wire:click="openLabModal" variant="filled" icon="beaker">
                Order Lab Test
            </flux:button>
            <flux:button wire:click="openRxModal" variant="filled" icon="document-plus">
                E-Prescription
            </flux:button>
            <flux:button wire:click="openConsultModal" variant="primary" icon="pencil-square">
                + Clinical Notes
            </flux:button>
        </div>
    </div>

    <!-- Patient Header Banner -->
    <flux:card class="p-5 bg-gradient-to-r from-blue-900 to-indigo-950 text-white shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="size-14 rounded-full bg-white/20 flex items-center justify-center font-bold text-xl text-white">
                    {{ strtoupper(substr($patient->first_name, 0, 1) . substr($patient->last_name, 0, 1)) }}
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-black text-white">{{ $patient->full_name }}</h1>
                        <span class="font-mono text-xs font-bold bg-white/20 px-2.5 py-0.5 rounded">
                            MRN: {{ $patient->file_number }}
                        </span>
                        @if ($patient->admission_status === 'admitted')
                            <flux:badge color="red" size="sm">Admitted ({{ $patient->currentWard?->name }} - {{ $patient->currentBed?->bed_number }})</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">Outpatient</flux:badge>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-4 mt-2 text-xs text-blue-200 font-medium">
                        <span>DOB: {{ $patient->date_of_birth ? $patient->date_of_birth->format('d M Y') : '—' }} ({{ $patient->gender }})</span>
                        <span>Phone: {{ $patient->phone_number }}</span>
                        <span>Blood Group: <strong class="text-white">{{ $patient->blood_group ?? '—' }}</strong></span>
                        <span>Genotype: <strong class="text-white">{{ $patient->genotype ?? '—' }}</strong></span>
                        <span>HMO: <strong class="text-white">{{ $patient->insurance_provider ?? 'Self-Pay' }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Allergy & Chronic Flags -->
            <div class="flex flex-col gap-1.5 self-start md:self-auto text-xs">
                @if ($patient->allergies)
                    <div class="bg-red-500/20 border border-red-400/40 text-red-200 px-3 py-1 rounded flex items-center gap-2">
                        <flux:icon name="exclamation-triangle" class="size-4 text-red-400" />
                        <span><strong>Allergies:</strong> {{ $patient->allergies }}</span>
                    </div>
                @endif
                @if ($patient->chronic_conditions)
                    <div class="bg-amber-500/20 border border-amber-400/40 text-amber-200 px-3 py-1 rounded flex items-center gap-2">
                        <flux:icon name="information-circle" class="size-4 text-amber-400" />
                        <span><strong>Chronic:</strong> {{ $patient->chronic_conditions }}</span>
                    </div>
                @endif
            </div>
        </div>
    </flux:card>

    <!-- Main Clinical Chart Columns -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Clinical Encounters & Diagnoses Timeline -->
        <div class="lg:col-span-2 space-y-6">
            <flux:card class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Clinical Visit Notes & Diagnoses</flux:heading>
                        <flux:subheading>Versioned doctor encounters, ICD-10 codings, and treatment plans</flux:subheading>
                    </div>
                    <flux:button wire:click="openConsultModal" size="sm" variant="primary" icon="plus">
                        New Consultation
                    </flux:button>
                </div>

                <div class="space-y-6">
                    @forelse ($patient->healthRecords as $record)
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/80 shadow-sm relative">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="blue" size="sm">{{ ucfirst($record->visit_type) }}</flux:badge>
                                        @if ($record->icd_code)
                                            <span class="font-mono text-xs font-bold bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 rounded text-zinc-800 dark:text-zinc-200">
                                                ICD-10: {{ $record->icd_code }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-zinc-400">Ver. {{ $record->version }}</span>
                                    </div>
                                    <h3 class="font-bold text-base text-zinc-900 dark:text-zinc-100 mt-2">
                                        {{ $record->diagnosis }}
                                    </h3>
                                </div>
                                <div class="text-right text-xs text-zinc-500">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $record->doctor?->name ?? 'Attending Doctor' }}</div>
                                    <span>{{ $record->created_at->format('d M Y, h:i A') }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-700 text-xs">
                                <div>
                                    <span class="text-zinc-400 uppercase font-semibold block">Chief Complaint</span>
                                    <p class="text-zinc-700 dark:text-zinc-300 mt-0.5">{{ $record->chief_complaint ?: '—' }}</p>
                                </div>
                                <div>
                                    <span class="text-zinc-400 uppercase font-semibold block">Treatment Plan</span>
                                    <p class="text-zinc-700 dark:text-zinc-300 mt-0.5">{{ $record->treatment_plan ?: '—' }}</p>
                                </div>
                            </div>

                            @if ($record->clinical_notes)
                                <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-700 text-xs">
                                    <span class="text-zinc-400 uppercase font-semibold block">Clinical Findings & Notes</span>
                                    <p class="text-zinc-700 dark:text-zinc-300 mt-0.5 whitespace-pre-line">{{ $record->clinical_notes }}</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="py-12 text-center text-zinc-500">
                            No clinical records recorded yet. Click "New Consultation" to start doctor notes.
                        </div>
                    @endforelse
                </div>
            </flux:card>

            <!-- Lab Orders & Results Chart -->
            <flux:card class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading size="lg">Diagnostic Laboratory Investigations</flux:heading>
                        <flux:subheading>Full pathology history with biological reference flagging</flux:subheading>
                    </div>
                    <flux:button wire:click="openLabModal" size="sm" variant="filled" icon="plus">
                        Order Lab Test
                    </flux:button>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($patient->labOrders as $order)
                        <div class="py-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ $order->order_number }}</span>
                                        @if ($order->status === 'completed')
                                            <flux:badge color="green" size="sm">Completed</flux:badge>
                                        @elseif ($order->status === 'processing')
                                            <flux:badge color="blue" size="sm">Processing</flux:badge>
                                        @else
                                            <flux:badge color="amber" size="sm">Sample Pending</flux:badge>
                                        @endif
                                    </div>
                                    <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-1">{{ $order->test_name }}</div>
                                </div>
                                <div class="text-right text-xs text-zinc-400">
                                    {{ $order->created_at->format('d M Y') }}
                                </div>
                            </div>

                            @if ($order->results->count() > 0)
                                <div class="mt-3 bg-zinc-50 dark:bg-zinc-800/60 rounded-lg p-3 space-y-2">
                                    @foreach ($order->results as $res)
                                        <div class="flex items-center justify-between text-xs">
                                            <div>
                                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $res->parameter_name }}:</span>
                                                <strong class="font-mono ml-1 {{ $res->flag === 'critical' ? 'text-red-600 dark:text-red-400 font-extrabold' : '' }}">
                                                    {{ $res->result_value }} {{ $res->unit }}
                                                </strong>
                                                <span class="text-zinc-400 ml-1">(Ref: {{ $res->reference_range ?? '—' }})</span>
                                            </div>
                                            <div>
                                                @if ($res->flag === 'critical')
                                                    <flux:badge color="red" size="sm">CRITICAL</flux:badge>
                                                @elseif ($res->flag === 'abnormal')
                                                    <flux:badge color="amber" size="sm">Abnormal</flux:badge>
                                                @else
                                                    <flux:badge color="green" size="sm">Normal</flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="py-8 text-center text-zinc-500 text-xs">No laboratory test orders for this patient.</div>
                    @endforelse
                </div>
            </flux:card>
        </div>

        <!-- Right Column: Vitals History & Active Prescriptions -->
        <div class="space-y-6">
            <!-- Vital Signs Flowsheet -->
            <flux:card class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Vital Signs Flowsheet</flux:heading>
                    <flux:button wire:click="openVitalsModal" size="xs" variant="primary" icon="plus">
                        Add Vitals
                    </flux:button>
                </div>

                <div class="space-y-3">
                    @forelse ($patient->vitals as $v)
                        <div class="p-3 rounded-lg border {{ $v->status_flag === 'critical' ? 'border-red-300 dark:border-red-900/60 bg-red-50/40 dark:bg-red-950/20' : 'border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/40' }} text-xs">
                            <div class="flex items-center justify-between text-zinc-500 mb-2">
                                <span>{{ $v->recorded_at->format('d M, h:i A') }}</span>
                                <flux:badge color="{{ $v->status_flag === 'critical' ? 'red' : ($v->status_flag === 'guarded' ? 'amber' : 'green') }}" size="sm">
                                    {{ ucfirst($v->status_flag) }}
                                </flux:badge>
                            </div>

                            <div class="grid grid-cols-2 gap-2 font-mono">
                                <div>BP: <strong class="text-zinc-900 dark:text-zinc-100">{{ $v->blood_pressure ?? '—' }}</strong></div>
                                <div>Temp: <strong class="text-zinc-900 dark:text-zinc-100">{{ $v->temperature ? $v->temperature.'°C' : '—' }}</strong></div>
                                <div>Pulse: <strong class="text-zinc-900 dark:text-zinc-100">{{ $v->pulse_rate ? $v->pulse_rate.' bpm' : '—' }}</strong></div>
                                <div>SpO2: <strong class="text-zinc-900 dark:text-zinc-100">{{ $v->spo2 ? $v->spo2.'%' : '—' }}</strong></div>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-zinc-500 text-xs italic">No vital signs logged yet.</div>
                    @endforelse
                </div>
            </flux:card>

            <!-- Active E-Prescriptions -->
            <flux:card class="p-5">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">E-Prescribing History</flux:heading>
                    <flux:button wire:click="openRxModal" size="xs" variant="primary" icon="plus">
                        Prescribe
                    </flux:button>
                </div>

                <div class="space-y-3">
                    @forelse ($patient->prescriptions as $rx)
                        <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/40 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-bold text-zinc-700 dark:text-zinc-300">{{ $rx->prescription_number }}</span>
                                <flux:badge color="{{ $rx->status === 'dispensed' ? 'green' : 'amber' }}" size="sm">
                                    {{ ucfirst($rx->status) }}
                                </flux:badge>
                            </div>

                            <div class="mt-2 space-y-1.5">
                                @foreach ($rx->items as $i)
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $i->drug_name }} ({{ $i->dosage }})
                                        <span class="block text-[10px] text-zinc-500 font-normal">{{ $i->frequency }} for {{ $i->duration }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-zinc-500 text-xs italic">No prescriptions issued yet.</div>
                    @endforelse
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Modals -->
    <!-- New Consultation Modal -->
    <flux:modal wire:model="showNewConsultModal" class="md:w-[650px]">
        <form wire:submit.prevent="saveConsultation" class="space-y-4">
            <div>
                <flux:heading size="lg">Record Clinical Consultation</flux:heading>
                <flux:subheading>Document physician encounter, ICD-10 diagnosis, and management plan</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Visit Type</flux:label>
                    <flux:select wire:model="visit_type">
                        <flux:select.option value="outpatient">Outpatient Clinic</flux:select.option>
                        <flux:select.option value="inpatient">Inpatient Ward Round</flux:select.option>
                        <flux:select.option value="emergency">Emergency Encounter</flux:select.option>
                        <flux:select.option value="follow_up">Follow-up Review</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>ICD-10 Code</flux:label>
                    <flux:input wire:model="icd_code" placeholder="e.g. J06.9, I10, E11.9, B50.9" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Chief Complaint & History</flux:label>
                <flux:textarea wire:model="chief_complaint" placeholder="Patient's primary symptom, duration, and relevant history" rows="2" required />
            </flux:field>

            <flux:field>
                <flux:label>Clinical Diagnosis</flux:label>
                <flux:input wire:model="diagnosis" placeholder="e.g. Acute Bronchitis, Essential Hypertension" required />
            </flux:field>

            <flux:field>
                <flux:label>Treatment Plan & Directives</flux:label>
                <flux:textarea wire:model="treatment_plan" placeholder="Medications to administer, dietary/fluid recommendations" rows="2" />
            </flux:field>

            <flux:field>
                <flux:label>Physician Examination Notes</flux:label>
                <flux:textarea wire:model="clinical_notes" placeholder="Systemic examination findings (CVS, Resp, Abdomen, CNS)" rows="2" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Known Allergies (Updated)</flux:label>
                    <flux:input wire:model="allergies" placeholder="e.g. Penicillin, NSAIDs" />
                </flux:field>

                <flux:field>
                    <flux:label>Chronic Conditions</flux:label>
                    <flux:input wire:model="chronic_conditions" placeholder="e.g. Hypertension, Asthma" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showNewConsultModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save to Medical Record</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Prescribe Modal -->
    <flux:modal wire:model="showRxModal" class="md:w-[500px]">
        <form wire:submit.prevent="createPrescription" class="space-y-4">
            <div>
                <flux:heading size="lg">E-Prescribe Medication</flux:heading>
                <flux:subheading>Send electronic prescription directly to hospital pharmacy queue</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Medication from Formulary</flux:label>
                <flux:select wire:model.live="selectedDrugId">
                    @foreach (Drug::orderBy('name')->get() as $d)
                        <flux:select.option value="{{ $d->id }}">{{ $d->name }} (In stock: {{ $d->stock_quantity }})</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Custom Medication Name</flux:label>
                <flux:input wire:model="rxDrugName" required />
            </flux:field>

            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Dosage</flux:label>
                    <flux:input wire:model="rxDosage" placeholder="e.g. 500mg" required />
                </flux:field>

                <flux:field>
                    <flux:label>Frequency</flux:label>
                    <flux:select wire:model="rxFrequency">
                        <flux:select.option value="Once Daily">Once Daily</flux:select.option>
                        <flux:select.option value="BD (Twice Daily)">BD (Twice Daily)</flux:select.option>
                        <flux:select.option value="TDS (3x Daily)">TDS (3x Daily)</flux:select.option>
                        <flux:select.option value="QDS (4x Daily)">QDS (4x Daily)</flux:select.option>
                        <flux:select.option value="PRN (As Needed)">PRN (As Needed)</flux:select.option>
                        <flux:select.option value="Stat">Stat (Immediate)</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Duration</flux:label>
                    <flux:input wire:model="rxDuration" placeholder="e.g. 5 days" required />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>Quantity to Dispense</flux:label>
                    <flux:input type="number" min="1" wire:model="rxQuantity" required />
                </flux:field>

                <flux:field>
                    <flux:label>Instructions</flux:label>
                    <flux:input wire:model="rxInstructions" placeholder="e.g. Take after meals" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showRxModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Send to Pharmacy</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Lab Order Modal -->
    <flux:modal wire:model="showLabModal" class="md:w-[480px]">
        <form wire:submit.prevent="createLabOrder" class="space-y-4">
            <div>
                <flux:heading size="lg">Order Diagnostic Test</flux:heading>
                <flux:subheading>Requisition pathology, blood, or imaging investigation</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Investigation / Test</flux:label>
                <flux:input wire:model="labTestName" placeholder="e.g. Full Blood Count, Malaria MP, Urine Microscopy" required />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Specimen Type</flux:label>
                    <flux:select wire:model="labSampleType">
                        <flux:select.option value="Blood">Blood</flux:select.option>
                        <flux:select.option value="Urine">Urine</flux:select.option>
                        <flux:select.option value="Stool">Stool</flux:select.option>
                        <flux:select.option value="Swab">Swab</flux:select.option>
                        <flux:select.option value="Other">Other</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Urgency</flux:label>
                    <flux:select wire:model="labPriority">
                        <flux:select.option value="routine">Routine</flux:select.option>
                        <flux:select.option value="urgent">Urgent</flux:select.option>
                        <flux:select.option value="stat">STAT / Emergency</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Clinical Indications / Remarks</flux:label>
                <flux:textarea wire:model="labNotes" placeholder="Reason for test requisition" rows="2" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showLabModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Submit Order</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Record Vitals Modal -->
    <flux:modal wire:model="showVitalsModal" class="md:w-[500px]">
        <form wire:submit.prevent="recordVitals" class="space-y-4">
            <div>
                <flux:heading size="lg">Record Vital Signs</flux:heading>
                <flux:subheading>Capture physiological baseline measurements</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Blood Pressure (mmHg)</flux:label>
                    <flux:input wire:model="bp" placeholder="e.g. 120/80" />
                </flux:field>

                <flux:field>
                    <flux:label>Temperature (°C)</flux:label>
                    <flux:input type="number" step="0.1" wire:model="temp" placeholder="e.g. 36.8" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Pulse Rate (bpm)</flux:label>
                    <flux:input type="number" wire:model="pulse" placeholder="e.g. 72" />
                </flux:field>

                <flux:field>
                    <flux:label>SpO2 (Oxygen %)</flux:label>
                    <flux:input type="number" wire:model="spo2" placeholder="e.g. 98" />
                </flux:field>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Respiratory (bpm)</flux:label>
                    <flux:input type="number" wire:model="resp" placeholder="e.g. 18" />
                </flux:field>

                <flux:field>
                    <flux:label>Weight (kg)</flux:label>
                    <flux:input type="number" step="0.1" wire:model="weight" placeholder="e.g. 70" />
                </flux:field>

                <flux:field>
                    <flux:label>Blood Sugar</flux:label>
                    <flux:input type="number" step="0.1" wire:model="sugar" placeholder="e.g. 5.5" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Nursing Remarks</flux:label>
                <flux:input wire:model="vitalNotes" placeholder="Patient condition or observations" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showVitalsModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Vitals</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
