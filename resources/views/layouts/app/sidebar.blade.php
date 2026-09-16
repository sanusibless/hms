<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @php
                    $u = auth()->user();
                @endphp

                <!-- General Navigation -->
                <flux:sidebar.group :heading="__('General')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('patients.index')" :current="request()->routeIs('patients.*')" wire:navigate>
                        {{ __('Patients') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bell" :href="route('alerts.index')" :current="request()->routeIs('alerts.*')" wire:navigate>
                        {{ __('Alerts & Notices') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <!-- Clinical & EHR (Doctor, Nurse, Admin) -->
                @if ($u?->hasRole(['doctor', 'nurse']))
                    <flux:sidebar.group :heading="__('Clinical & ADT')" class="grid mt-4">
                        <flux:sidebar.item icon="calendar" :href="route('appointments.index')" :current="request()->routeIs('appointments.index')" wire:navigate>
                            {{ __('Appointments') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('appointments.queue')" :current="request()->routeIs('appointments.queue')" wire:navigate>
                            {{ __('Triage Queue') }}
                        </flux:sidebar.item>
                        @if ($u?->hasRole(['doctor']))
                            <flux:sidebar.item icon="document-text" :href="route('ehr.index')" :current="request()->routeIs('ehr.*')" wire:navigate>
                                {{ __('Health Records') }}
                            </flux:sidebar.item>
                        @endif
                        <flux:sidebar.item icon="building-office-2" :href="route('wards.index')" :current="request()->routeIs('wards.*')" wire:navigate>
                            {{ __('Wards & Beds') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                <!-- Laboratory / LIS (Lab Tech, Doctor, Admin) -->
                @if ($u?->hasRole(['lab_tech', 'doctor']))
                    <flux:sidebar.group :heading="__('Laboratory')" class="grid mt-4">
                        <flux:sidebar.item icon="beaker" :href="route('lab.orders')" :current="request()->routeIs('lab.orders')" wire:navigate>
                            {{ __('Lab Orders') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('lab.results')" :current="request()->routeIs('lab.results')" wire:navigate>
                            {{ __('Test Results') }}
                        </flux:sidebar.item>
                        @if ($u?->hasRole(['lab_tech']))
                            <flux:sidebar.item icon="wrench-screwdriver" :href="route('lab.equipment')" :current="request()->routeIs('lab.equipment')" wire:navigate>
                                {{ __('Equipment & QC') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif

                <!-- Pharmacy & MAR (Doctor, Nurse, Admin) -->
                @if ($u?->hasRole(['doctor', 'nurse']))
                    <flux:sidebar.group :heading="__('Pharmacy & Meds')" class="grid mt-4">
                        <flux:sidebar.item icon="cube" :href="route('pharmacy.inventory')" :current="request()->routeIs('pharmacy.inventory')" wire:navigate>
                            {{ __('Drug Inventory') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="queue-list" :href="route('pharmacy.prescriptions')" :current="request()->routeIs('pharmacy.prescriptions')" wire:navigate>
                            {{ __('Prescriptions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="shield-check" :href="route('pharmacy.mar')" :current="request()->routeIs('pharmacy.mar')" wire:navigate>
                            {{ __('e-MAR Sheet') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                <!-- Billing & Financials (Admin, Staff) -->
                @if ($u?->hasRole(['staff']))
                    <flux:sidebar.group :heading="__('Billing & Wallets')" class="grid mt-4">
                        <flux:sidebar.item icon="credit-card" :href="route('payments.create')" :current="request()->routeIs('payments.*')" wire:navigate>
                            {{ __('Make Payment') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="wallet" :href="route('wallet.main')" :current="request()->routeIs('wallet.main')" wire:navigate>
                            {{ __('Hospital Wallet') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrows-right-left" :href="route('transactions.index')" :current="request()->routeIs('transactions.*')" wire:navigate>
                            {{ __('Transactions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-currency-dollar" :href="route('billing.invoices')" :current="request()->routeIs('billing.invoices')" wire:navigate>
                            {{ __('Invoices & Waivers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="shield-exclamation" :href="route('billing.claims')" :current="request()->routeIs('billing.claims')" wire:navigate>
                            {{ __('HMO Claims') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                <!-- Reports & Analytics (Admin, Doctor, Staff) -->
                @if ($u?->hasRole(['staff', 'doctor']))
                    <flux:sidebar.group :heading="__('Intelligence')" class="grid mt-4">
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                            {{ __('Reports & Analytics') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                <!-- Administration (Admin only) -->
                @if ($u?->isAdmin())
                    <flux:sidebar.group :heading="__('Administration')" class="grid mt-4">
                        <flux:sidebar.item icon="user-group" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                            {{ __('Staff Users') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="rectangle-stack" :href="route('admin.services')" :current="request()->routeIs('admin.services')" wire:navigate>
                            {{ __('Services & Dept') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="finger-print" :href="route('admin.audit-logs')" :current="request()->routeIs('admin.audit-logs')" wire:navigate>
                            {{ __('Audit Logs') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
