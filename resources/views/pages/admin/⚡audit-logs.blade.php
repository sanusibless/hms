<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit Logs & Compliance - HMS')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $moduleFilter = '';

    public function mount(): void
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403, 'Administrator privileges required to view system compliance audit trail.');
        }
    }

    public function render()
    {
        $query = AuditLog::with('user')->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('description', 'like', "%{$this->search}%")
                  ->orWhere('user_name', 'like', "%{$this->search}%")
                  ->orWhere('action', 'like', "%{$this->search}%")
                  ->orWhere('record_id', 'like', "%{$this->search}%");
            });
        }

        if ($this->moduleFilter) {
            $query->where('module', $this->moduleFilter);
        }

        return view('pages.admin.⚡audit-logs', [
            'logs' => $query->paginate(15),
            'totalLogs' => AuditLog::count(),
            'modules' => AuditLog::select('module')->distinct()->pluck('module'),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">System Audit Trail & Compliance</flux:heading>
            <flux:subheading>HIPAA & NDPR immutable transaction and access log monitoring</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('admin.users') }}" variant="filled" icon="user-group" wire:navigate>
                User Accounts
            </flux:button>
            <flux:button href="{{ route('reports.index') }}" variant="filled" icon="chart-bar" wire:navigate>
                System Reports
            </flux:button>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <flux:card class="p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search user, action, record ID, or description..." icon="magnifying-glass" class="w-full sm:w-80" />
                <flux:select wire:model.live="moduleFilter" class="w-48">
                    <flux:select.option value="">All Modules</flux:select.option>
                    @foreach ($modules as $m)
                        <flux:select.option value="{{ $m }}">{{ ucfirst($m) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <span class="text-xs text-zinc-500">Total logged events: {{ $logs->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Timestamp</th>
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4">Module</th>
                        <th class="py-3 px-4">Action</th>
                        <th class="py-3 px-4">Details</th>
                        <th class="py-3 px-4 text-right">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 text-xs">
                            <td class="py-3 px-4 font-mono text-zinc-500">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $log->user_name ?? ($log->user?->name ?? 'System') }}
                            </td>
                            <td class="py-3 px-4">
                                <flux:badge color="zinc" size="sm">{{ ucfirst($log->module) }}</flux:badge>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-mono uppercase font-bold text-zinc-700 dark:text-zinc-300">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-zinc-700 dark:text-zinc-300">
                                {{ $log->description }}
                                @if ($log->record_id)
                                    <span class="font-mono text-zinc-400 block text-[10px]">ID: {{ $log->record_id }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-zinc-400">
                                {{ $log->ip_address ?? '127.0.0.1' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-zinc-500">No audit trail records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </flux:card>
</div>
