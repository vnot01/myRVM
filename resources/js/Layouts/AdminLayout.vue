// resources/js/Layouts/AdminLayout.vue
<script setup>
import { ref } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue'; // Dari Breeze
import Dropdown from '@/Components/Dropdown.vue';             // Dari Breeze
import DropdownLink from '@/Components/DropdownLink.vue';     // Dari Breeze
import NavLink from '@/Components/NavLink.vue';               // Dari Breeze (untuk sidebar)
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'; // Dari Breeze

const showingNavigationDropdown = ref(false);

defineProps({
    title: String,
});
</script>

<template>
    <div>
        <Head :title="title" />

        <div class="min-h-screen bg-gray-100 flex">
            <!-- Sidebar -->
            <aside class="w-64 bg-white shadow-md hidden sm:flex sm:flex-col">
                <div class="flex items-center justify-center h-16 border-b">
                    <Link :href="route('admin.dashboard')">
                        <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                    </Link>
                </div>
                <nav class="flex-grow p-4 space-y-1">
                    <NavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')">
                        Dashboard
                    </NavLink>
                    <NavLink :href="route('admin.rvms.index')" :active="route().current('admin.rvms.index')" v-if="$page.props.auth.user.role === 'Admin'">
                        Manajemen RVM
                    </NavLink>
                    <NavLink :href="route('admin.users.index')" :active="route().current('admin.users.index')">
                        Manajemen User
                    </NavLink>
                    <!-- Tambahkan link admin lain di sini -->
                </nav>
            </aside>

            <div class="flex-1 flex flex-col">
                <!-- Navbar Atas -->
                <nav class="bg-white border-b border-gray-100">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-16">
                            <div class="flex items-center">
                                <!-- Hamburger untuk mobile (jika ada sidebar mobile) -->
                                <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="sm:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <h1 class="text-lg font-semibold ml-2 sm:ml-0">{{ title }}</h1>
                            </div>

                            <div class="hidden sm:flex sm:items-center sm:ms-6">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </span>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>
                    <!-- Responsive Navigation Menu (untuk mobile) -->
                     <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                        <div class="pt-2 pb-3 space-y-1">
                            <ResponsiveNavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')">Dashboard</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.rvms.index')" :active="route().current('admin.rvms.index')" v-if="$page.props.auth.user.role === 'Admin'">Manajemen RVM</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.users.index')" :active="route().current('admin.users.index')">Manajemen User</ResponsiveNavLink>
                        </div>
                         <!-- Responsive Settings Options -->
                        <div class="pt-4 pb-1 border-t border-gray-200">
                            <div class="px-4">
                                <div class="font-medium text-base text-gray-800">{{ $page.props.auth.user.name }}</div>
                                <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
                            </div>
                            <div class="mt-3 space-y-1">
                                <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
                                <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Page Content -->
                <main class="flex-1 p-6 overflow-y-auto">
                    <slot /> <!-- Konten halaman spesifik akan masuk di sini -->
                </main>
            </div>
        </div>
    </div>
</template>