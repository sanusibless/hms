<?php

use App\Models\Drug;
use App\Services\AuditService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pharmacy Drug Inventory - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $stockFilter = ''; // all, low_stock, out_of_stock

    public bool $showCreateModal = false;
    public string $name = '';
    public string $generic_name = '';
    public string $category = 'Antibiotics';
    public string $dosage_form = 'Tablet';
    public string $strength = '';
    public int $stock_quantity = 50;
    public int $reorder_level = 15;
    public string $unit_price = '1500';
    public string $expiry_date = '';

    // Restock Modal
    public bool $showRestockModal = false;
    public ?int $restockDrugId = null;
    public int $restockQuantity = 50;

    public function mount(): void
    {
        $this->expiry_date = now()->addMonths(18)->toDateString();
    }

    public function openCreateModal(): void
    {
        $this->reset(['name', 'generic_name', 'strength']);
        $this->stock_quantity = 50;
        $this->reorder_level = 15;
        $this->unit_price = '1500';
        $this->expiry_date = now()->addMonths(18)->toDateString();
        $this->showCreateModal = true;
    }

    public function createDrug(): void
    {
        $this->validate([
            'name' => 'required|string|max:150',
            'generic_name' => 'nullable|string|max:150',
            'category' => 'required|string|max:100',
            'dosage_form' => 'required|string|max:50',
            'strength' => 'nullable|string|max:50',
            'stock_quantity' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'unit_price' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date',
        ]);

        $drug = Drug::create([
            'name' => $this->name,
            'generic_name' => $this->generic_name,
            'category' => $this->category,
            'dosage_form' => $this->dosage_form,
            'strength' => $this->strength,
            'stock_quantity' => $this->stock_quantity,
            'reorder_level' => $this->reorder_level,
            'unit_price' => (float)$this->unit_price,
            'expiry_date' => $this->expiry_date ?: null,
            'status' => $this->stock_quantity > 0 ? 'active' : 'out_of_stock',
        ]);

        AuditService::log('create', 'pharmacy', (string)$drug->id, "Added new drug to inventory: {$drug->name}");

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: "Medication {$drug->name} added to pharmacy formulary.");
    }

    public function openRestockModal(int $drugId): void
    {
        $this->restockDrugId = $drugId;
        $this->restockQuantity = 50;
        $this->showRestockModal = true;
    }

    public function restockDrug(): void
    {
        $this->validate([
            'restockDrugId' => 'required|exists:drugs,id',
            'restockQuantity' => 'required|integer|min:1',
        ]);

        $drug = Drug::findOrFail($this->restockDrugId);
        $drug->increment('stock_quantity', $this->restockQuantity);
        if ($drug->stock_quantity > 0) {
            $drug->update(['status' => 'active']);
        }

        AuditService::log('update', 'pharmacy', (string)$drug->id, "Restocked {$this->restockQuantity} units of {$drug->name}");

        $this->showRestockModal = false;
        Flux::toast(variant: 'success', text: "Added {$this->restockQuantity} units to {$drug->name}. New Stock: {$drug->stock_quantity}");
    }

    public function render()
    {
        $query = Drug::latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('generic_name', 'like', "%{$this->search}%")
                  ->orWhere('category', 'like', "%{$this->search}%");
            });
        }

        if ($this->stockFilter === 'low_stock') {
            $query->whereColumn('stock_quantity', '<=', 'reorder_level')->where('stock_quantity', '>', 0);
        } elseif ($this->stockFilter === 'out_of_stock') {
            $query->where('stock_quantity', '<=', 0);
        }

        return view('pages.pharmacy.⚡inventory', [
            'drugs' => $query->paginate(12),
            'totalDrugs' => Drug::count(),
            'lowStockCount' => Drug::whereColumn('stock_quantity', '<=', 'reorder_level')->where('stock_quantity', '>', 0)->count(),
            'outOfStockCount' => Drug::where('stock_quantity', '<=', 0)->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Pharmacy Inventory & Formulary</flux:heading>
            <flux:subheading>Manage drug stocks, reorder thresholds, pricing, and expiry monitoring</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('pharmacy.prescriptions') }}" variant="filled" icon="document-text" wire:navigate>
                Prescriptions Queue
            </flux:button>
            <flux:button href="{{ route('pharmacy.mar') }}" variant="filled" icon="check-badge" wire:navigate>
                Nurse MAR
            </flux:button>
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                Add Medication
            </flux:button>
        </div>
    </div>

    <!-- Alert / Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 cursor-pointer hover:border-blue-500" wire:click="$set('stockFilter', '')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Total Formulary Drugs</div>
            <div class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-1">{{ $totalDrugs }}</div>
            <div class="text-xs text-zinc-400 mt-1">Active pharmaceutical products</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-amber-500 cursor-pointer hover:border-amber-600" wire:click="$set('stockFilter', 'low_stock')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Low Stock Warnings</div>
            <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $lowStockCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">At or below reorder threshold</div>
        </flux:card>

        <flux:card class="p-4 border-l-4 border-red-500 cursor-pointer hover:border-red-600" wire:click="$set('stockFilter', 'out_of_stock')">
            <div class="text-xs font-semibold text-zinc-500 uppercase">Out of Stock</div>
            <div class="text-3xl font-extrabold text-red-600 dark:text-red-400 mt-1">{{ $outOfStockCount }}</div>
            <div class="text-xs text-zinc-400 mt-1">Zero stock balance</div>
        </flux:card>
    </div>

    <!-- Inventory Table -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search drug name, generic, or category..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="stockFilter" class="w-48">
                    <flux:select.option value="">All Stock Levels</flux:select.option>
                    <flux:select.option value="low_stock">Low Stock (< Reorder)</flux:select.option>
                    <flux:select.option value="out_of_stock">Out of Stock (0)</flux:select.option>
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Total medications: {{ $drugs->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Brand / Medication</th>
                        <th class="py-3 px-4">Generic & Strength</th>
                        <th class="py-3 px-4">Category / Form</th>
                        <th class="py-3 px-4 text-center">In Stock</th>
                        <th class="py-3 px-4 text-right">Unit Price</th>
                        <th class="py-3 px-4">Expiry Date</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($drugs as $drug)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3 px-4">
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $drug->name }}</span>
                                @if ($drug->is_low_stock && $drug->stock_quantity > 0)
                                    <span class="block text-[10px] text-amber-600 dark:text-amber-400 font-bold uppercase tracking-wider">Low Stock (Min: {{ $drug->reorder_level }})</span>
                                @elseif ($drug->stock_quantity <= 0)
                                    <span class="block text-[10px] text-red-600 font-bold uppercase tracking-wider">Out of Stock</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-zinc-700 dark:text-zinc-300">
                                <div>{{ $drug->generic_name ?? '—' }}</div>
                                <span class="text-xs text-zinc-400 font-mono">{{ $drug->strength ?? '' }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <flux:badge color="zinc" size="sm">{{ $drug->dosage_form }}</flux:badge>
                                <span class="block text-xs text-zinc-500 mt-0.5">{{ $drug->category }}</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="font-mono text-base font-extrabold {{ $drug->stock_quantity <= 0 ? 'text-red-600' : ($drug->is_low_stock ? 'text-amber-600' : 'text-zinc-900 dark:text-zinc-100') }}">
                                    {{ $drug->stock_quantity }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                {{ $drug->formatted_price }}
                            </td>
                            <td class="py-3 px-4 text-xs font-mono">
                                @if ($drug->expiry_date)
                                    <span class="{{ $drug->expiry_date->isPast() ? 'text-red-600 font-bold' : 'text-zinc-600 dark:text-zinc-300' }}">
                                        {{ $drug->expiry_date->format('M Y') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <flux:button wire:click="openRestockModal({{ $drug->id }})" size="xs" variant="filled">
                                    + Restock
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-zinc-500">No drugs found matching your criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $drugs->links() }}</div>
    </flux:card>

    <!-- Create Medication Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[500px]">
        <form wire:submit.prevent="createDrug" class="space-y-4">
            <div>
                <flux:heading size="lg">Add Medication to Formulary</flux:heading>
                <flux:subheading>Register new pharmaceutical item with pricing and reorder thresholds</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Brand Name</flux:label>
                <flux:input wire:model="name" placeholder="e.g. Coartem, Augmentin, Amytrip" required />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Generic Name</flux:label>
                    <flux:input wire:model="generic_name" placeholder="e.g. Artemether + Lumefantrine" />
                </flux:field>

                <flux:field>
                    <flux:label>Strength / Formulation</flux:label>
                    <flux:input wire:model="strength" placeholder="e.g. 500mg, 1g, 20/120mg" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Dosage Form</flux:label>
                    <flux:select wire:model="dosage_form">
                        <flux:select.option value="Tablet">Tablet</flux:select.option>
                        <flux:select.option value="Capsule">Capsule</flux:select.option>
                        <flux:select.option value="Syrup">Syrup / Suspension</flux:select.option>
                        <flux:select.option value="Injection">Injection (IV/IM)</flux:select.option>
                        <flux:select.option value="Ointment">Ointment / Cream</flux:select.option>
                        <flux:select.option value="Drops">Eye/Ear Drops</flux:select.option>
                        <flux:select.option value="Inhaler">Inhaler</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Therapeutic Category</flux:label>
                    <flux:select wire:model="category">
                        <flux:select.option value="Antibiotics">Antibiotics</flux:select.option>
                        <flux:select.option value="Antimalarials">Antimalarials</flux:select.option>
                        <flux:select.option value="Analgesics">Analgesics & NSAIDs</flux:select.option>
                        <flux:select.option value="Antihypertensives">Antihypertensives</flux:select.option>
                        <flux:select.option value="Antidiabetics">Antidiabetics</flux:select.option>
                        <flux:select.option value="Antihistamines">Antihistamines</flux:select.option>
                        <flux:select.option value="Vitamins & Minerals">Vitamins & Minerals</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Initial Stock</flux:label>
                    <flux:input type="number" min="0" wire:model="stock_quantity" required />
                </flux:field>

                <flux:field>
                    <flux:label>Reorder Min</flux:label>
                    <flux:input type="number" min="1" wire:model="reorder_level" required />
                </flux:field>

                <flux:field>
                    <flux:label>Price (₦)</flux:label>
                    <flux:input type="number" min="0" step="0.01" wire:model="unit_price" required />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Expiration Date</flux:label>
                <flux:input type="date" wire:model="expiry_date" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Add Medication</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Restock Modal -->
    <flux:modal wire:model="showRestockModal" class="md:w-[400px]">
        <form wire:submit.prevent="restockDrug" class="space-y-4">
            <div>
                <flux:heading size="lg">Restock Medication</flux:heading>
                <flux:subheading>Add incoming batch supply to current warehouse balance</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Quantity to Add</flux:label>
                <flux:input type="number" min="1" wire:model="restockQuantity" required />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showRestockModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Confirm Restock</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
