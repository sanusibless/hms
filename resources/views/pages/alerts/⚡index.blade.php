<?php

use App\Models\HmsAlert;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Notifications & Clinical Alerts - HMS')] class extends Component {
    #[Url]
    public string $filter = ''; // all, critical, unread

    // New Alert / Notice Modal
    public bool $showCreateModal = false;
    public string $target_role = 'all';
    public string $alert_type = 'policy_notice';
    public string $title = '';
    public string $message = '';
    public string $priority = 'normal';

    public function markAsRead(int $alertId): void
    {
        $alert = HmsAlert::findOrFail($alertId);
        $alert->update(['is_read' => true]);
        Flux::toast(variant: 'info', text: 'Alert marked as read.');
    }

    public function markAllRead(): void
    {
        HmsAlert::where('is_read', false)->update(['is_read' => true]);
        Flux::toast(variant: 'success', text: 'All notifications marked as read.');
    }

    public function createNotice(): void
    {
        $this->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:1000',
            'target_role' => 'required|string',
            'alert_type' => 'required|string',
            'priority' => 'required|in:normal,high,critical',
        ]);

        HmsAlert::create([
            'target_role' => $this->target_role,
            'alert_type' => $this->alert_type,
            'title' => $this->title,
            'message' => $this->message,
            'priority' => $this->priority,
            'is_read' => false,
        ]);

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: 'Facility-wide announcement broadcasted.');
    }

    public function render()
    {
        $user = Auth::user();
        $query = HmsAlert::latest();

        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('target_role', 'all')
                  ->orWhere('target_role', $user->role)
                  ->orWhere('user_id', $user->id);
            });
        }

        if ($this->filter === 'critical') {
            $query->where('priority', 'critical');
        } elseif ($this->filter === 'unread') {
            $query->where('is_read', false);
        }

        $alerts = $query->get();

        return view('pages.alerts.⚡index', [
            'alerts' => $alerts,
            'unreadCount' => $alerts->where('is_read', false)->count(),
            'criticalCount' => $alerts->where('priority', 'critical')->count(),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Notifications & Clinical Alerts</flux:heading>
            <flux:subheading>Critical lab findings, medication due alerts, bed occupancy warnings, and policy notices</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="markAllRead" variant="ghost" icon="check">
                Mark All Read
            </flux:button>
            @if (auth()->user()?->isAdmin())
                <flux:button wire:click="$set('showCreateModal', true)" variant="primary" icon="megaphone">
                    Broadcast Notice
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Alert Filters -->
    <div class="flex items-center gap-2">
        <flux:button wire:click="$set('filter', '')" variant="{{ $filter === '' ? 'filled' : 'ghost' }}" size="sm">
            All Alerts ({{ count($alerts) }})
        </flux:button>
        <flux:button wire:click="$set('filter', 'unread')" variant="{{ $filter === 'unread' ? 'filled' : 'ghost' }}" size="sm">
            Unread ({{ $unreadCount }})
        </flux:button>
        <flux:button wire:click="$set('filter', 'critical')" variant="{{ $filter === 'critical' ? 'filled' : 'ghost' }}" size="sm" class="text-red-600">
            Critical Only ({{ $criticalCount }})
        </flux:button>
    </div>

    <!-- Alerts Feed -->
    <div class="space-y-3">
        @forelse ($alerts as $alt)
            <flux:card class="p-4 flex items-start justify-between gap-4 border-l-4 {{ $alt->priority === 'critical' ? 'border-red-500 bg-red-50/20 dark:bg-red-950/10' : ($alt->priority === 'high' ? 'border-amber-500' : 'border-blue-500') }} {{ !$alt->is_read ? 'shadow-sm' : 'opacity-80' }}">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg mt-0.5 {{ $alt->priority === 'critical' ? 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400' : ($alt->priority === 'high' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400' : 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400') }}">
                        @if ($alt->alert_type === 'critical_lab')
                            <flux:icon name="exclamation-circle" class="size-5" />
                        @elseif ($alt->alert_type === 'medication_reminder')
                            <flux:icon name="clock" class="size-5" />
                        @elseif ($alt->alert_type === 'bed_capacity')
                            <flux:icon name="building-office" class="size-5" />
                        @else
                            <flux:icon name="bell" class="size-5" />
                        @endif
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $alt->title }}</h3>
                            @if (!$alt->is_read)
                                <span class="size-2 rounded-full bg-blue-600 inline-block"></span>
                            @endif
                            <flux:badge color="zinc" size="sm">{{ ucfirst(str_replace('_', ' ', $alt->alert_type)) }}</flux:badge>
                            @if ($alt->target_role !== 'all')
                                <flux:badge color="blue" size="sm">For {{ ucfirst($alt->target_role) }}s</flux:badge>
                            @endif
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300 mt-1">
                            {{ $alt->message }}
                        </p>
                        <div class="flex items-center gap-3 mt-2 text-xs text-zinc-400">
                            <span>{{ $alt->created_at->diffForHumans() }}</span>
                            @if ($alt->link)
                                <a href="{{ $alt->link }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">View Attached Record &rarr;</a>
                            @endif
                        </div>
                    </div>
                </div>

                <div>
                    @if (!$alt->is_read)
                        <flux:button wire:click="markAsRead({{ $alt->id }})" size="xs" variant="ghost">
                            Dismiss
                        </flux:button>
                    @endif
                </div>
            </flux:card>
        @empty
            <div class="py-12 text-center text-zinc-500">
                <flux:icon name="check-circle" class="size-10 mx-auto text-green-500/40 mb-2" />
                <p class="font-medium">No alerts or notifications.</p>
            </div>
        @endforelse
    </div>

    <!-- Create Notice Modal -->
    <flux:modal wire:model="showCreateModal" class="md:w-[500px]">
        <form wire:submit.prevent="createNotice" class="space-y-4">
            <div>
                <flux:heading size="lg">Broadcast Hospital Announcement</flux:heading>
                <flux:subheading>Publish policy updates, downtime notices, or clinical bulletins</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Headline / Subject</flux:label>
                <flux:input wire:model="title" placeholder="e.g. Mandatory Electronic Triage Protocol" required />
            </flux:field>

            <div class="grid grid-cols-3 gap-3">
                <flux:field>
                    <flux:label>Target Role</flux:label>
                    <flux:select wire:model="target_role">
                        <flux:select.option value="all">All Personnel</flux:select.option>
                        <flux:select.option value="doctor">Doctors Only</flux:select.option>
                        <flux:select.option value="nurse">Nurses Only</flux:select.option>
                        <flux:select.option value="lab_tech">Lab Technicians</flux:select.option>
                        <flux:select.option value="staff">Billing & Staff</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model="alert_type">
                        <flux:select.option value="policy_notice">Policy Notice</flux:select.option>
                        <flux:select.option value="maintenance">Maintenance</flux:select.option>
                        <flux:select.option value="bed_capacity">Capacity Warning</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Priority</flux:label>
                    <flux:select wire:model="priority">
                        <flux:select.option value="normal">Normal</flux:select.option>
                        <flux:select.option value="high">High</flux:select.option>
                        <flux:select.option value="critical">Critical</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Detailed Message Content</flux:label>
                <flux:textarea wire:model="message" placeholder="Full announcement text..." rows="3" required />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('showCreateModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Broadcast</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
