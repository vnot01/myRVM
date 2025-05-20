<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue'; // Tambahkan computed
import debounce from 'lodash/debounce';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SelectInput from '@/Components/SelectInput.vue'; // Komponen select kustom Anda
import Checkbox from '@/Components/Checkbox.vue'; // Komponen checkbox Breeze

const props = defineProps({
    availableRoles: Array,
    availableRolesProp: { // Terima 'availableRoles'
        type: Array,
        required: true // Tandai sebagai required jika memang selalu dikirim
    },
    defaultPassword: {
        type: String,
        // required: false, // Tidak wajib jika ada default di Vue
        default: ''
    },
    errors: Object, // Ini otomatis dari Inertia jika ada error validasi
});
// const props = defineProps({
//     // users: Object,
//     availableRolesProp: Array,
//     // availableRoles:Array,
//     errors: Object,
//     defaultName: { // Opsional dari controller
//         type: String,
//         default: '',
//     },
//     defaultPassword: { // Opsional dari controller
//         type: String,
//         default: '',
//     }
// });

// Log props saat komponen di-setup (dijalankan sekali)
// Computed property untuk mengubah array string menjadi array objek
// // console.log('Prop users di Create.vue:', JSON.parse(JSON.stringify(props.users)));
// console.log('[Create.vue] Props diterima:', JSON.parse(JSON.stringify(props)));
// console.log('[Create.vue] availableRoles dari props:', JSON.parse(JSON.stringify(props.availableRoles)));
// console.log('[Create.vue] availableRolesProp di Create.vue:', JSON.parse(JSON.stringify(props.availableRolesProp)));
const roleOptionsForSelectInput = computed(() => {
    if (!props.availableRolesProp) return [];
    return props.availableRolesProp.map(role => ({ value: role, label: role }));
});

const form = useForm({
    name: props.defaultName,
    email: '',
    phone_number: '',
    password: props.defaultPassword, // Isi dengan default jika ada
    password_confirmation: props.defaultPassword,
    role: null,
    citizenship: null,
    identity_type: null,
    identity_number: '',
    points: 0,
    is_active: true,
    email_verified_manually: false,
});
// --- LOGIKA BARU UNTUK IDENTITY ---
// // console.log('[Create.vue] availableRoles dari props:', JSON.parse(JSON.stringify(props.availableRoles)));
// Logika kondisional untuk Tipe Identitas (sama seperti Create.vue)
const citizenshipOptions = ref([
    { value: 'WNI', label: 'WNI (Warga Negara Indonesia)' },
    { value: 'WNA', label: 'WNA (Warga Negara Asing)' },
]);

// console.log('[Create.vue] citizenshipOptions dari props:', JSON.parse(JSON.stringify(citizenshipOptions)));
const identityTypeOptions = computed(() => {
    if (form.citizenship === 'WNI') {
        return ['KTP', 'Pasport'];
    } else if (form.citizenship === 'WNA') {
        return ['Pasport'];
    }
    return []; // Kosong jika citizenship belum dipilih
});

// Reset identity_type dan identity_number jika citizenship berubah
watch(() => form.citizenship, (newCitizenship) => {
    form.identity_type = null; // Reset tipe identitas
    form.identity_number = ''; // Reset nomor identitas
    if (newCitizenship === 'WNA') {
        form.identity_type = 'Pasport'; // Otomatis pilih Pasport jika WNA
    }
});

// Logika untuk validasi input identity_number (bisa disempurnakan)
const identityNumberPattern = computed(() => {
    if (form.identity_type === 'KTP') {
        return "^[0-9]{16}$"; // Hanya 16 digit angka
    } else if (form.identity_type === 'Pasport') {
        return "^[A-Z0-9]{1,12}$"; // Hingga 12 digit, angka dan huruf besar
    }
    return ".*"; // Pola default jika tidak ada yang dipilih (seharusnya tidak terjadi)
});

const identityNumberTitle = computed(() => {
    if (form.identity_type === 'KTP') {
        return "Masukkan 16 digit nomor KTP (hanya angka).";
    } else if (form.identity_type === 'Pasport') {
        return "Masukkan hingga 12 karakter nomor Paspor (huruf besar dan angka).";
    }
    return "Pilih tipe identitas terlebih dahulu.";
});

const handleIdentityNumberInput = (event) => {
    if (form.identity_type === 'Pasport') {
        // Otomatis ubah ke huruf besar untuk Paspor
        form.identity_number = event.target.value.toUpperCase();
    } else {
        form.identity_number = event.target.value;
    }
};
// --- AKHIR LOGIKA BARU ---

const submit = () => {
    form.post(route('admin.users.store'), {
        onError: (errors) => {
            // form.errors akan otomatis terisi
            console.error("Error submitting form:", errors);
        },
        onSuccess: () => {
            // Inertia akan otomatis redirect berdasarkan respons controller
            // Pesan flash akan ditangani oleh AdminLayout
        }
    });
};
</script>

<template>
    <Head title="Tambah Pengguna Baru" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Tambah Pengguna Baru
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Nama, Email, Phone, Password, Role, Poin, Status (tetap sama) -->
                        <div>
                            <InputLabel for="name" value="Nama Lengkap" />
                            <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="phone_number" value="Nomor Telepon (Opsional)" />
                            <TextInput id="phone_number" type="tel" class="mt-1 block w-full" v-model="form.phone_number" />
                            <InputError class="mt-2" :message="form.errors.phone_number" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" />
                            <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" value="Konfirmasi Password" />
                            <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" required />
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <div class="mt-4">
                            <InputLabel for="role" value="Role Pengguna" />
                            <!-- <pre class="text-xs dark:text-gray-300 bg-gray-100 dark:bg-gray-900 p-2 rounded">Debug availableRoles: {{ availableRoles }}</pre> DEBUG DI TEMPLATE -->
                            <!-- <select id="role" v-model="form.role" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"> -->
                                <!-- <option v-for="roleOpt in availableRoles" :key="roleOpt" :value="roleOpt"> -->
                                <!-- {{ roleOpt.charAt(0).toUpperCase() + roleOpt.slice(1) }} -->
                                <!-- </option> -->
                            <!-- </select> -->
                             <SelectInput id="role" class="mt-1 block w-full" v-model="form.role" :options="roleOptionsForSelectInput" valueKey="value" 
                                labelKey="label" :placeholder="'-- Pilih Role --'" required/>
                                <option v-if="placeholder" value="" disabled selected>{{ placeholder }}</option>
                            <!-- </SelectInput> -->
                            <InputError class="mt-2" :message="form.errors.role" />
                        </div>
                        <!-- <div> -->
                            <!-- <InputLabel for="role" value="Role Pengguna" /> -->
                            <!-- <pre class="text-xs bg-gray-100 dark:bg-gray-900 p-2 rounded">Debug availableRoles: {{ props.availableRoles }}</pre> <!~~ DEBUG DI TEMPLATE ~~> -->
                            <!-- <SelectInput id="role" class="mt-1 block w-full" v-model="form.role" required> -->
                                <!-- <option v-if="!props.availableRoles || props.availableRoles.length === 0" disabled value=""> -->
                                    <!-- (Tidak ada role tersedia) -->
                                <!-- </option> -->
                                <!-- <!~~ Pastikan v-for menggunakan props.availableRoles ~~> -->
                                <!-- <option v-for="roleOpt in props.availableRoles" :key="roleOpt" :value="roleOpt"> -->
                                    <!-- {{ roleOpt }} -->
                                <!-- </option> -->
                            <!-- </SelectInput> -->
                            <!-- <InputError class="mt-2" :message="form.errors.role" /> -->
                        <!-- </div> -->
                        
                        <!-- --- FIELD BARU --- -->
                        <div>
                            <InputLabel for="citizenship" value="Kewarganegaraan (Opsional)" />
                            <SelectInput id="citizenship" class="mt-1 block w-full" v-model="form.citizenship" :options="citizenshipOptions" 
                                :placeholder="'-- Pilih Kewarganegaraan --'"/>
                                 <!-- <select id="citizenship" v-model="form.citizenship" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"> -->
                                    <!-- <option :value="null">-- Pilih Kewarganegaraan --</option> -->
                                    <!-- <option value="WNI">WNI (Warga Negara Indonesia)</option> -->
                                    <!-- <option value="WNA">WNA (Warga Negara Asing)</option> -->
                                <!-- </select> -->
                            <InputError class="mt-2" :message="form.errors.citizenship" />
                        </div>

                        <div v-if="form.citizenship"> <!-- Tampilkan jika citizenship sudah dipilih -->
                            <InputLabel for="identity_type" value="Tipe Identitas (Opsional)" />
                            <select id="identity_type" v-model="form.identity_type" :disabled="identityTypeOptions.length === 0" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option :value="null">-- Pilih Tipe Identitas --</option>
                                <option v-for="idType in identityTypeOptions" :key="idType" :value="idType">{{ idType }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.identity_type" />
                        </div>

                        <div v-if="form.identity_type"> <!-- Tampilkan jika tipe identitas sudah dipilih -->
                            <InputLabel for="identity_number" value="Nomor Identitas (Opsional)" />
                            <TextInput
                                id="identity_number"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="form.identity_number"
                                @input="handleIdentityNumberInput"
                                :pattern="identityNumberPattern"
                                :title="identityNumberTitle"
                                :maxlength="form.identity_type === 'KTP' ? 16 : (form.identity_type === 'Pasport' ? 12 : undefined)"
                            />
                            <InputError class="mt-2" :message="form.errors.identity_number" />
                            <p v-if="form.identity_type === 'KTP'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">KTP: 16 digit angka.</p>
                            <p v-if="form.identity_type === 'Pasport'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paspor: Maks 12 karakter (huruf besar & angka).</p>
                        </div>
                        <!-- --- AKHIR FIELD BARU --- -->

                        <div>
                            <InputLabel for="points" value="Poin Awal (Opsional)" />
                            <TextInput id="points" type="number" min="0" class="mt-1 block w-full" v-model.number="form.points" />
                            <InputError class="mt-2" :message="form.errors.points" />
                        </div>
                        
                        <div class="block">
                            <label class="flex items-center">
                                <Checkbox name="is_active" v-model:checked="form.is_active" />
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Akun Aktif</span>
                            </label>
                            <InputError class="mt-2" :message="form.errors.is_active" />
                        </div>

                        <div class="block">
                            <label class="flex items-center">
                                <Checkbox name="email_verified_manually" v-model:checked="form.email_verified_manually" />
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Verifikasi Email Secara Manual</span>
                            </label>
                            <InputError class="mt-2" :message="form.errors.email_verified_manually" />
                        </div>

                        <div class="flex items-center justify-end">
                            <Link :href="route('admin.users.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 mr-4">
                                Batal
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Simpan Pengguna
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>