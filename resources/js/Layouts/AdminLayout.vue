// resources/js/Layouts/AdminLayout.vue
<script setup>
// ... (import Anda) ...
import { Link, Head, usePage } from '@inertiajs/vue3'; // Hanya usePage jika Link, dll. tidak dipakai langsung di sini
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'; // Jika Anda pakai untuk mobile sidebar
import { ref, watch, onMounted, onUpdated } from 'vue';
// import { CiColorDbc } from "oh-vue-icons/icons";
// CiColorDbc


const page = usePage();
const showFlashMessage = ref(false);
const flashMessageText = ref('');
const flashMessageType = ref('success');
const showingNavigationDropdown = ref(false);

defineProps({
    title: String,
});

// ... (logika flash message Anda sudah bagus) ...
watch(() => page.props.flash, (newFlashValue) => {
  const MAX_FLASH_LENGTH = 150; // Batas karakter pesan flash
  if (newFlashValue && (newFlashValue.success || newFlashValue.error || newFlashValue.warning || newFlashValue.info)) {
    let message = '';
    if (newFlashValue.success) {
        message = newFlashValue.success;
        flashMessageType.value = 'success';
    } else if (newFlashValue.error) {
        message = newFlashValue.error;
        flashMessageType.value = 'error';
    } else if (newFlashValue.warning) {
        message = newFlashValue.warning;
        flashMessageType.value = 'warning';
    } else if (newFlashValue.info) {
        message = newFlashValue.info;
        flashMessageType.value = 'info';
    }
    flashMessageText.value = message.length > MAX_FLASH_LENGTH ? message.substring(0, MAX_FLASH_LENGTH) + "..." : message;
    showFlashMessage.value = true;
    setTimeout(() => {
      showFlashMessage.value = false;
    }, 5000);
  }
}, { deep: true, immediate: true });
</script>

<template>
    <div>
        <Head :title="title" />

        <!-- Notifikasi Flash (posisi fixed, akan selalu di atas) -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-2"
        >
            <div v-if="showFlashMessage"
                 class="fixed top-5 right-5 max-w-sm w-full z-[9999] rounded-md shadow-lg p-4"
                 :class="{
                    'bg-green-500 dark:bg-green-600 text-white': flashMessageType === 'success',
                    'bg-red-500 dark:bg-red-600 text-white': flashMessageType === 'error',
                    'bg-yellow-400 dark:bg-yellow-500 text-gray-800': flashMessageType === 'warning',
                    'bg-blue-500 dark:bg-blue-600 text-white': flashMessageType === 'info',
                 }">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="font-medium text-sm">
                            {{ flashMessageType === 'success' ? 'Sukses!' : (flashMessageType === 'error' ? 'Error!' : (flashMessageType === 'warning' ? 'Peringatan!' : 'Informasi!')) }}
                        </p>
                        <p class="mt-1 text-xs">
                            {{ flashMessageText }}
                        </p>
                    </div>
                    <button @click="showFlashMessage = false" class="ml-3 flex-shrink-0 -mr-1 -mt-1 flex p-1 rounded-md hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white">
                        <span class="sr-only">Dismiss</span>
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </div>
        </transition>

        <div class="min-h-screen bg-slate-100 dark:bg-slate-900 flex">
            <!-- Sidebar Desktop -->
            <aside class="w-64 bg-white dark:bg-slate-800 shadow-lg flex-col transition-transform duration-300 hidden sm:flex">
                 <div class="flex items-center justify-center h-16 border-b border-gray-200 dark:border-gray-700">
                    <Link :href="route('admin.dashboard')">
                        <ApplicationLogo class="block h-9 w-auto fill-current text-slate-800 dark:text-slate-200" />
                    </Link>
                </div>
                <nav class="flex-grow p-4 space-y-1">
                    <NavLink :href="route('admin.dashboard')" :active="page.url.startsWith('/web/dashboard')">
                        <svg class="mr-2 h-6 w-6 text-gray-400 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 22 21">
                              <path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z"/>
                              <path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z"/>
                          </svg>
                        Dashboard
                    </NavLink>

                    <NavLink :href="route('admin.rvms.index')" :active="page.url.startsWith('/web/rvms')" v-if="page.props.auth.user.role === 'Admin'">
                        <svg class="mr-2 w-6 h-6 text-gray-400 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-300" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M5.535 7.677c.313-.98.687-2.023.926-2.677H17.46c.253.63.646 1.64.977 2.61.166.487.312.953.416 1.347.11.42.148.675.148.779 0 .18-.032.355-.09.515-.06.161-.144.3-.243.412-.1.111-.21.192-.324.245a.809.809 0 0 1-.686 0 1.004 1.004 0 0 1-.324-.245c-.1-.112-.183-.25-.242-.412a1.473 1.473 0 0 1-.091-.515 1 1 0 1 0-2 0 1.4 1.4 0 0 1-.333.927.896.896 0 0 1-.667.323.896.896 0 0 1-.667-.323A1.401 1.401 0 0 1 13 9.736a1 1 0 1 0-2 0 1.4 1.4 0 0 1-.333.927.896.896 0 0 1-.667.323.896.896 0 0 1-.667-.323A1.4 1.4 0 0 1 9 9.74v-.008a1 1 0 0 0-2 .003v.008a1.504 1.504 0 0 1-.18.712 1.22 1.22 0 0 1-.146.209l-.007.007a1.01 1.01 0 0 1-.325.248.82.82 0 0 1-.316.08.973.973 0 0 1-.563-.256 1.224 1.224 0 0 1-.102-.103A1.518 1.518 0 0 1 5 9.724v-.006a2.543 2.543 0 0 1 .029-.207c.024-.132.06-.296.11-.49.098-.385.237-.85.395-1.344ZM4 12.112a3.521 3.521 0 0 1-1-2.376c0-.349.098-.8.202-1.208.112-.441.264-.95.428-1.46.327-1.024.715-2.104.958-2.767A1.985 1.985 0 0 1 6.456 3h11.01c.803 0 1.539.481 1.844 1.243.258.641.67 1.697 1.019 2.72a22.3 22.3 0 0 1 .457 1.487c.114.433.214.903.214 1.286 0 .412-.072.821-.214 1.207A3.288 3.288 0 0 1 20 12.16V19a2 2 0 0 1-2 2h-6a1 1 0 0 1-1-1v-4H8v4a1 1 0 0 1-1 1H6a2 2 0 0 1-2-2v-6.888ZM13 15a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-2Z" clip-rule="evenodd"/>
                          </svg>
                        Manajemen RVM
                    </NavLink>

                    <NavLink :href="route('admin.users.index')" :active="page.url.startsWith('/web/users')" v-if="page.props.auth.user.role === 'Admin'">
                         <svg class="mr-3 w-5 h-5 text-gray-400 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-300"
                            aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 18">
                              <path d="M14 2a3.963 3.963 0 0 0-1.4.267 6.439 6.439 0 0 1-1.331 6.638A4 4 0 1 0 14 2Zm1 9h-1.264A6.957 6.957 0 0 1 15 15v2a2.97 2.97 0 0 1-.184 1H19a1 1 0 0 0 1-1v-1a5.006 5.006 0 0 0-5-5ZM6.5 9a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9ZM8 10H5a5.006 5.006 0 0 0-5 5v2a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-2a5.006 5.006 0 0 0-5-5Z"/>
                          </svg>
                        Manajemen User
                    </NavLink>
                    <NavLink :href="route('admin.configured-prompts.index')" :active="page.url.startsWith('/web/configured-prompts')">
                        <svg class="mr-3 w-5 h-5 border-green-300 dark:border-green-600 animate-pulse text-green-700 dark:text-green-400 transition duration-75 group-hover:text-gray-900 dark:group-hover:text-gray-300 icon icon-tabler icons-tabler-outline icon-tabler-terminal" 
                            aria-hidden="true" xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  
                            stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M5 7l5 5l-5 5" /><path d="M12 19l7 0" />
                        </svg>
                        <span class="border-green-300 dark:border-green-600 animate-pulse text-green-700 dark:text-green-400 transition duration-75">Manajemen Prompt AI</span>
                        
                    </NavLink>
                    <NavLink :href="route('admin.prompt-templates.index')" :active="page.url.startsWith('/web/prompt-templates-manage')">
                        Pustaka Template Prompt
                    </NavLink>
                    <NavLink :href="route('admin.prompt-components.index')" :active="page.url.startsWith('/web/prompt-components-manage')">
                        Pustaka Komponen Prompt
                    </NavLink>
                </nav>
            </aside>

            <!-- Kontainer Kanan (Topbar + Konten Utama) -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Topbar -->
                <nav class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
                    <div class="mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-16">
                            <div class="flex items-center">
                                <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="sm:hidden mr-3 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300">
                                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <!-- Slot untuk header halaman -->
                                <slot name="header" />
                                <h1 v-if="title" class="text-lg font-semibold ml-2 sm:ml-0 text-gray-800 dark:text-gray-200">{{ title }}</h1>
                            </div>
                            <div class="hidden sm:flex sm:items-center sm:ms-6">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-300 focus:outline-none transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </span>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Sidebar Mobile (Overlay) -->
                <!-- {/* Responsive Navigation Menu (Sidebar Mobile) - INI AKAN MENJADI SIDEBAR MOBILE YANG MUNCUL */} -->
                <div v-show="showingNavigationDropdown" class="sm:hidden fixed inset-0 z-30" @click="showingNavigationDropdown = false">
                    <div class="fixed inset-0 bg-black opacity-25" @click="showingNavigationDropdown = false"></div>
                    <aside class="fixed top-0 left-0 h-full w-64 bg-white dark:bg-gray-800 shadow-xl z-40 transform transition-transform ease-in-out duration-300"
                           :class="showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'">
                       <!-- {/* ... Konten Sidebar Mobile (Logo, NavLink, Info User, Logout) ... */}
                       {/* Sama seperti yang ada di kode AdminLayout.vue sebelumnya */} -->
                        <div class="flex items-center justify-between h-16 border-b border-gray-200 dark:border-gray-700 px-4">
                            <Link :href="route('admin.dashboard')" @click="showingNavigationDropdown = false">
                                <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                            </Link>
                            <button @click="showingNavigationDropdown = false" class="p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <nav class="p-4 space-y-1">
                            <ResponsiveNavLink :href="route('admin.dashboard')" :active="route().current('admin.dashboard')" @click="showingNavigationDropdown = false">Dashboard</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.rvms.index')" @click="showingNavigationDropdown = false">Manajemen RVM</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.users.index')" @click="showingNavigationDropdown = false">Manajemen User</ResponsiveNavLink>
                        </nav>
                        <!-- {/* mt-auto untuk mendorong ke bawah */} -->
                        <div class="p-4 border-t border-gray-200 dark:border-gray-700 mt-auto"> 
                            <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ $page.props.auth.user.name }}</div>
                            <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
                            <ResponsiveNavLink :href="route('profile.edit')" class="mt-2" @click="showingNavigationDropdown = false"> Profile </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button" class="w-full text-left" @click="showingNavigationDropdown = false">Log Out</ResponsiveNavLink>
                        </div>
                    </aside>
                </div>


                <!-- {/* Page Content */} -->
                <main class="flex-1 p-6 overflow-y-auto bg-white dark:bg-gray-800 rounded-b-lg">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>