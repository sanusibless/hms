<?php

use App\Models\HospitalService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Services & Departments - Hospital Payment App')] class extends Component {
    public bool $showCreateModal = false;

    public string $name = '';
    public string $department = '';
    public string $default_amount = '';
    public string $description = '';

    public function mount(): void
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403, 'Unauthorized. Hospital Administrator access required.');
        }
    }

    public function openCreateModal(): void
    {
        $this->reset(['name', 'department', 'default_amount', 'description']);
        $this->showCreateModal = true;
    }

    public function createService(): void
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403);
        }

        $validated = $this->validate([
            'name' => 'required|string|max:100|unique:services,name',
            'department' => 'nullable|string|max:100',
            'default_amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        HospitalService::create([
            'name' => $validated['name'],
            'department' => $validated['department'] ?: null,
            'default_amount' => $validated['default_amount'] ? (float)$validated['default_amount'] : null,
            'description' => $validated['description'] ?: null,
            'is_active' => true,
        ]);

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: "Service '{$validated['name']}' created successfully.");
    }

    public function toggleService(int $id): void
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403);
        }

        $service = HospitalService::findOrFail($id);
        $service->is_active = !$service->is_active;
        $service->save();

        $statusStr = $service->is_active ? 'activated' : 'deactivated';
        Flux::toast(variant: 'info', text: "Service '{$service->name}' has been {$statusStr}.");
    }

    public function render()
    {
        return view('pages.admin.⚡services', [
            'services' => HospitalService::latest()->get(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Hospital Services & Departments</flux:heading>
            <flux:subheading>Manage chargeable hospital services, diagnostic investigations, and department tariffs</flux:subheading>
        </div>
        <div>
            <flux:button wire:click="openCreateModal" icon="plus" variant="primary">
                Add Service / Department
            </flux:button>
        </div>
    </div>

    <!-- Services Table (Page 2 & 3 Requirements) -->
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Service Name</th>
                        <th class="py-3 px-4">Department</th>
                        <th class="py-3 px-4 text-right">Default Fee (₦)</th>
                        <th class="py-3 px-4">Description</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($services as $svc)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3.5 px-4 font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $svc->name }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs px-2 py-0.5 rounded">
                                    {{ $svc->department ?? 'General' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $svc->formatted_default_amount ?? 'Variable' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-500 max-w-xs truncate">
                                {{ $svc->description ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if ($svc->is_active)
                                    <flux:badge color="green" size="sm">Active</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <flux:button
                                    wire:click="toggleService({{ $svc->id }})"
                                    size="xs"
                                    variant="{{ $svc->is_active ? 'ghost' : 'filled' }}"
                                >
                                    {{ $svc->is_active ? 'Disable' : 'Enable' }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-zinc-500">
                                No services configured yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <!-- Create Service Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[28rem]">
        <form wire:submit="createService" class="space-y-4">
            <div>
                <flux:heading size="lg">Add Hospital Service</flux:heading>
                <flux:subheading>Define a chargeable hospital procedure, test, or department fee</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Service Name <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="name" placeholder="e.g. Ophthalmology Examination" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Department / Unit</flux:label>
                <flux:input wire:model="department" placeholder="e.g. Eye Clinic" />
                <flux:error name="department" />
            </flux:field>

            <flux:field>
                <flux:label>Default Fee (₦) (Optional)</flux:label>
                <flux:input wire:model="default_amount" type="number" step="0.01" min="0" placeholder="e.g. 15000" />
                <flux:error name="default_amount" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" placeholder="Brief details regarding this service..." />
                <flux:error name="description" />
            </flux:field>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showCreateModal', false)" variant="ghost" type="button">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit">
                    Save Service
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
