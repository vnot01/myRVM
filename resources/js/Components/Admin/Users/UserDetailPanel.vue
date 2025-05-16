// resources/js/Components/Admin/Users/UserDetailPanel.vue
<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
// Import komponen form Anda: PrimaryButton, TextInput, InputLabel, InputError, SelectInput, TextareaInput (jika perlu)
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SelectInput from '@/Components/SelectInput.vue'; // Jika Anda punya komponen ini
import Modal from '@/Components/Modal.vue'; // Untuk konfirmasi hapus & edit poin/password


const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    availableRoles: {
        type: Array,
        default: () => ['Admin', 'Operator', 'User'],
    },
});

const emit = defineEmits(['user-updated', 'user-deleted', 'close-panel']);

// Kita akan mengisi ini nanti dengan form dan logika
</script>

<template>
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Detail Pengguna: {{ user.name }}
                </h3>
                <button @click="$emit('close-panel')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        <div class="p-6">
            <p class="text-gray-700 dark:text-gray-300">
                Form edit dan detail lengkap untuk pengguna <strong>{{ user.name }}</strong> akan ada di sini.
            </p>
            <div class="mt-4">
                <p>ID: {{ user.id }}</p>
                <p>Email: {{ user.email }}</p>
                <p>Role: {{ user.role }}</p>
                <p>Poin: {{ user.points }}</p>
                <p>Status Aktif: {{ user.is_active ? 'Aktif' : 'Tidak Aktif' }}</p>
                <p>Email Terverifikasi: {{ user.email_verified_at ? 'Ya' : 'Belum' }}</p>
            </div>
            <!-- Tombol placeholder -->
            <div class="mt-6 flex justify-end space-x-3">
                <DangerButton @click.stop="">Hapus User (Placeholder)</DangerButton>
                <PrimaryButton @click.stop="">Simpan Perubahan (Placeholder)</PrimaryButton>
            </div>
        </div>
    </div>
</template>