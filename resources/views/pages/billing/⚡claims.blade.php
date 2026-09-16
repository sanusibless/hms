<?php

use App\Models\InsuranceClaim;
use App\Models\Patient;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Insurance Claims Management - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    // Submit Claim Modal
    public bool $showClaimModal = false;
    public ?int $patient_id = null;
    public string $provider_name = 'Hygeia HMO';
    public string $policy_number = '';
    public string $claim_amount = '25000';
    public string $notes = '';

    // Review Modal
    public bool $showReviewModal = false;
    public ?int $reviewClaimId = null;
    public string $approved_amount = '';
    public string $status = 'approved';
    public string $resolution_notes = '';

    public function openClaimModal(?int $patientId = null): void
    {
        $patient = $patientId ? Patient::find($patientId) : Patient::whereNotNull('insurance_provider')->first() ?? Patient::first();
        $this->patient_id = $patient?->id;
        $this->provider_name = $patient?->insurance_provider ?? 'Reliance HMO';
        $this->policy_number = $patient?->insurance_policy_number ?? '';
        $this->claim_amount = '25000';
        $this->notes = '';
        $this->showClaimModal = true;
    }

    public function submitClaim(): void
    {
        $this->validate([
            'patient_id' => 'required|exists:patients,id',
            'provider_name' => 'required|string|max:150',
            'policy_number' => 'required|string|max:100',
            'claim_amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $claim = InsuranceClaim::create([
            'claim_number' => InsuranceClaim::generateClaimNumber(),
            'patient_id' => $this->patient_id,
            'provider_name' => $this->provider_name,
            'policy_number' => $this->policy_number,
            'claim_amount' => (float)$this->claim_amount,
            'approved_amount' => 0.00,
            'status' => 'submitted',
            'submission_date' => now()->toDateString(),
            'notes' => $this->notes,
            'handled_by' => Auth::id(),
        ]);

        AuditService::log('create', 'billing', (string)$claim->id, "Submitted HMO insurance claim {$claim->claim_number} for ₦" . number_format((float)$this->claim_amount, 2));

        $this->showClaimModal = false;
        Flux::toast(variant: 'success', text: "Claim {$claim->claim_number} submitted to {$this->provider_name}.");
    }

    public function openReviewModal(int $claimId): void
    {
        $this->reviewClaimId = $claimId;
        $claim = InsuranceClaim::findOrFail($claimId);
        $this->approved_amount = (string)$claim->claim_amount;
        $this->status = 'approved';
        $this->resolution_notes = '';
        $this->showReviewModal = true;
    }

    public function resolveClaim(): void
    {
        $this->validate([
            'reviewClaimId' => 'required|exists:insurance_claims,id',
            'status' => 'required|in:approved,rejected,under_review',
            'approved_amount' => 'required|numeric|min:0',
            'resolution_notes' => 'nullable|string|max:500',
        ]);

        $claim = InsuranceClaim::findOrFail($this->reviewClaimId);
        $claim->update([
            'status' => $this->status,
            'approved_amount' => $this->status === 'approved' ? (float)$this->approved_amount : 0.00,
            'resolution_date' => now()->toDateString(),
            'notes' => $this->resolution_notes ?: $claim->notes,
            'handled_by' => Auth::id(),
        ]);

        AuditService::log('update', 'billing', (string)$claim->id, "Resolved insurance claim {$claim->claim_number} ({$this->status})");

        $this->showReviewModal = false;
        Flux::toast(variant: 'info', text: "Claim {$claim->claim_number} marked as {$this->status}.");
    }

    public function render()
    {
        $query = InsuranceClaim::with(['patient', 'handler'])->latest('submission_date');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('claim_number', 'like', "%{$this->search}%")
                  ->orWhere('provider_name', 'like', "%{$this->search}%")
                  ->orWhere('policy_number', 'like', "%{$this->search}%")
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

        return view('pages.billing.⚡claims', [
            'claims' => $query->paginate(10),
            'patients' => Patient::orderBy('first_name')->take(50)->get(),
            'submittedCount' => InsuranceClaim::where('status', 'submitted')->count(),
            'approvedCount' => InsuranceClaim::where('status', 'approved')->count(),
            'totalApprovedAmount' => InsuranceClaim::where('status', 'approved')->sum('approved_amount'),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('billing.invoices') }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                    Invoices & Billing
                </flux:button>
            </div>
            <flux:heading size="xl" level="1" class="mt-1">HMO & Insurance Claims Tracker</flux:heading>
            <flux:subheading>Submit claims, monitor under-review status, and reconcile approved HMO remittances</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="openClaimModal" variant="primary" icon="plus">
                Submit Claim
            </flux:button>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 border-l-4 border-amber-500 cursor-pointer" wire:click="$set('statusFilter', 'submitted')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Pending Review</div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $submittedCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Submitted to health maintenance organizations</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-green-500 cursor-pointer" wire:click="$set('statusFilter', 'approved')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Approved Claims</div>
            <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mt-1">{{ $approvedCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Confirmed and verified for payment</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-blue-500">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Total Approved Remittances</div>
            <div class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">₦{{ number_format((float)$totalApprovedAmount, 2) }}</div>
            <div class="text-xs text-zinc-400 mt-1">Cumulative HMO reimbursed value</div>
        </flux:card>
    </div>

    <!-- Claims Table -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search claim #, provider, policy, patient..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="statusFilter" class="w-44">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    <flux:select.option value="submitted">Submitted</flux:select.option>
                    <flux:select.option value="under_review">Under Review</flux:select.option>
                    <flux:select.option value="approved">Approved</flux:select.option>
                    <flux:select.option value="rejected">Rejected</flux:select.option>
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Total claims: {{ $claims->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Claim #</th>
                        <th class="py-3 px-4">Patient</th>
                        <th class="py-3 px-4">HMO / Provider</th>
                        <th class="py-3 px-4">Policy #</th>
                        <th class="py-3 px-4 text-right">Claimed</th>
                        <th class="py-3 px-4 text-right">Approved</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($claims as $clm)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4 font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $clm->claim_number }}
                                <span class="block text-[10px] text-zinc-400 font-normal">{{ $clm->submission_date->format('d M Y') }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('patients.show', ['patient' => $clm->patient_id]) }}" class="font-medium text-blue-600 hover:underline">
                                    {{ $clm->patient->full_name }}
                                </a>
                                <span class="block text-xs text-zinc-400 font-mono">{{ $clm->patient->file_number }}</span>
                            </td>
                            <td class="py-3 px-4 font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $clm->provider_name }}
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-zinc-500">
                                {{ $clm->policy_number }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                ₦{{ number_format((float)$clm->claim_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-green-600 dark:text-green-400">
                                ₦{{ number_format((float)$clm->approved_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($clm->status === 'approved')
                                    <flux:badge color="green" size="sm">Approved</flux:badge>
                                @elseif ($clm->status === 'under_review')
                                    <flux:badge color="blue" size="sm">Under Review</flux:badge>
                                @elseif ($clm->status === 'rejected')
                                    <flux:badge color="red" size="sm">Rejected</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">Submitted</flux:badge>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <flux:button wire:click="openReviewModal({{ $clm->id }})" size="xs" variant="filled">
                                    Reconcile / Review
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">No insurance claims submitted yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $claims->links() }}</div>
    </flux:card>

    <!-- Submit Claim Modal -->
    <flux:modal wire:model="showClaimModal" class="md:w-[500px]">
        <form wire:submit.prevent="submitClaim" class="space-y-4">
            <div>
                <flux:heading size="lg">Submit Insurance HMO Claim</flux:heading>
                <flux:subheading>Generate formal electronic claim dossier for medical expenses</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Patient</flux:label>
                <flux:select wire:model="patient_id" required>
                    @foreach ($patients as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->full_name }} ({{ $p->file_number }})</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>HMO / Provider Name</flux:label>
                    <flux:input wire:model="provider_name" required />
                </flux:field>

                <flux:field>
                    <flux:label>Enrollee / Policy ID</flux:label>
                    <flux:input wire:model="policy_number" required />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Claimed Amount (₦)</flux:label>
                <flux:input type="number" min="1" step="0.01" wire:model="claim_amount" required />
            </flux:field>

            <flux:field>
                <flux:label>Pre-Auth Code / Clinical Summary</flux:label>
                <flux:textarea wire:model="notes" placeholder="e.g. Pre-authorization approval code HYG-AUTH-1290 or admission breakdown" rows="2" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showClaimModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Submit to HMO</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Review Modal -->
    <flux:modal wire:model="showReviewModal" class="md:w-[450px]">
        <form wire:submit.prevent="resolveClaim" class="space-y-4">
            <div>
                <flux:heading size="lg">HMO Remittance Reconciliation</flux:heading>
                <flux:subheading>Record approval status and remitted fund figure</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Claim Decision Status</flux:label>
                <flux:select wire:model="status" required>
                    <flux:select.option value="approved">Approved & Settled</flux:select.option>
                    <flux:select.option value="under_review">Under Audit Review</flux:select.option>
                    <flux:select.option value="rejected">Rejected / Disallowed</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Approved Amount (₦)</flux:label>
                <flux:input type="number" min="0" step="0.01" wire:model="approved_amount" required />
            </flux:field>

            <flux:field>
                <flux:label>Resolution Notes</flux:label>
                <flux:textarea wire:model="resolution_notes" placeholder="Remarks from HMO clearance letter or check remittance" rows="2" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showReviewModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Reconciliation</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
