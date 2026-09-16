<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('User Management - Hospital Payment App')] class extends Component {
    use WithPagination;

    public bool $showCreateModal = false;

    public string $first_name = '';
    public string $last_name = '';
    public string $username = '';
    public string $email = '';
    public string $phone_number = '';
    public string $role = 'staff';
    public string $department = 'Billing & Accounts';
    public string $password = '';

    public function mount(): void
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403, 'Unauthorized. Hospital Administrator access required.');
        }
    }

    public function openCreateModal(): void
    {
        $this->reset(['first_name', 'last_name', 'username', 'email', 'phone_number', 'password']);
        $this->role = 'staff';
        $this->department = 'Billing & Accounts';
        $this->showCreateModal = true;
    }

    public function createUser(): void
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403);
        }

        $validated = $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'phone_number' => 'required|string|max:25',
            'role' => 'required|in:admin,doctor,nurse,lab_tech,staff',
            'department' => 'required|string|max:100',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'name' => "{$validated['first_name']} {$validated['last_name']}",
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'phone_number' => $validated['phone_number'],
            'role' => $validated['role'],
            'department' => $validated['department'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $this->showCreateModal = false;
        Flux::toast(variant: 'success', text: "Hospital user {$validated['first_name']} {$validated['last_name']} created successfully.");
    }

    public function toggleUserStatus(int $userId): void
    {
        if (!Auth::user()?->isAdmin()) {
            abort(403);
        }

        if (Auth::id() === $userId) {
            Flux::toast(variant: 'danger', text: 'You cannot deactivate your own administrative account.');
            return;
        }

        $user = User::findOrFail($userId);
        $user->is_active = !$user->is_active;
        $user->save();

        $statusStr = $user->is_active ? 'activated' : 'deactivated';
        Flux::toast(variant: 'info', text: "User {$user->name} has been {$statusStr}.");
    }

    public function render()
    {
        return view('pages.admin.⚡users', [
            'users' => User::latest()->paginate(10),
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Hospital Staff & User Management</flux:heading>
            <flux:subheading>Administer hospital staff accounts, credentials, and access permissions</flux:subheading>
        </div>
        <div>
            <flux:button wire:click="openCreateModal" icon="user-plus" variant="primary">
                Create Hospital User
            </flux:button>
        </div>
    </div>

    <!-- Users Table (Page 2 Requirements) -->
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-700 text-xs uppercase text-zinc-500">
                    <tr>
                        <th class="py-3 px-4">Name</th>
                        <th class="py-3 px-4">Username / Login</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4">Phone</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Department</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/60">
                    @forelse ($users as $u)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                            <td class="py-3.5 px-4 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $u->name }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $u->username ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4">
                                {{ $u->email }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs">
                                {{ $u->phone_number ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($u->role === 'admin')
                                    <flux:badge color="indigo" size="sm">Hospital Admin</flux:badge>
                                @elseif ($u->role === 'doctor')
                                    <flux:badge color="sky" size="sm">Doctor</flux:badge>
                                @elseif ($u->role === 'nurse')
                                    <flux:badge color="emerald" size="sm">Nurse</flux:badge>
                                @elseif ($u->role === 'lab_tech')
                                    <flux:badge color="amber" size="sm">Lab Technician</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Hospital Staff</flux:badge>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                {{ $u->department ?? 'General' }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if ($u->is_active)
                                    <flux:badge color="green" size="sm">Active</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Deactivated</flux:badge>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if (auth()->id() !== $u->id)
                                    <flux:button
                                        wire:click="toggleUserStatus({{ $u->id }})"
                                        size="xs"
                                        variant="{{ $u->is_active ? 'danger' : 'filled' }}"
                                    >
                                        {{ $u->is_active ? 'Deactivate' : 'Activate' }}
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-400 italic">Current User</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500">
                                No users registered.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
            {{ $users->links() }}
        </div>
    </flux:card>

    <!-- Create User Modal (Page 2 Requirements) -->
    <flux:modal wire:model="showCreateModal" class="md:w-[32rem]">
        <form wire:submit="createUser" class="space-y-5">
            <div>
                <flux:heading size="lg">Create Hospital User</flux:heading>
                <flux:subheading>Add a new hospital staff or administrator account</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>First Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="first_name" placeholder="e.g. John" required />
                    <flux:error name="first_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Last Name <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="last_name" placeholder="e.g. Doe" required />
                    <flux:error name="last_name" />
                </flux:field>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Username <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="username" placeholder="e.g. jdoe" required />
                    <flux:error name="username" />
                </flux:field>

                <flux:field>
                    <flux:label>Phone Number <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="phone_number" placeholder="e.g. 08012345678" required />
                    <flux:error name="phone_number" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Email Address <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="email" type="email" placeholder="staff@hospital.com" required />
                <flux:error name="email" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Role <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="role" required>
                        <flux:select.option value="staff">Hospital Staff / Cashier</flux:select.option>
                        <flux:select.option value="doctor">Doctor</flux:select.option>
                        <flux:select.option value="nurse">Nurse</flux:select.option>
                        <flux:select.option value="lab_tech">Lab Technician</flux:select.option>
                        <flux:select.option value="admin">Hospital Admin</flux:select.option>
                    </flux:select>
                    <flux:error name="role" />
                </flux:field>

                <flux:field>
                    <flux:label>Department <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="department" placeholder="e.g. Billing, Cashier, OPD" required />
                    <flux:error name="department" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Password <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="password" type="password" placeholder="Min 6 characters" required viewable />
                <flux:error name="password" />
            </flux:field>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('showCreateModal', false)" variant="ghost" type="button">
                    Cancel
                </flux:button>
                <flux:button variant="primary" type="submit">
                    Create User
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
