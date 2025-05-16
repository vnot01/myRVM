<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted } from 'vue'; // onMounted jika perlu
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SelectInput from '@/Components/SelectInput.vue'; // Atau <select> langsung
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    user: Object, // Data user yang akan diedit
    availableRoles: Array, // Opsi role yang bisa dipilih
    errors: Object, // Otomatis dari Inertia jika ada error validasi dari session sebelumnya
});

// // Log awal untuk melihat apa yang sebenarnya diterima props
// console.log('[Edit.vue SETUP] props.user (raw):', props.user);
// console.log('[Edit.vue SETUP] props.availableRoles (raw):', props.availableRoles);
// // Log yang lebih aman
// // Log dengan JSON.stringify hanya jika prop ada
// if (props.user) {
//     console.log('[Edit.vue SETUP] User to edit (stringified):', JSON.parse(JSON.stringify(props.user)));
// } else {
//     console.warn('[Edit.vue SETUP] props.user is undefined or null AT SETUP');
// }

// if (props.availableRoles) {
//     console.log('[Edit.vue SETUP] Available roles (stringified):', JSON.parse(JSON.stringify(props.availableRoles)));
// } else {
//     console.warn('[Edit.vue SETUP] props.availableRoles is undefined or null AT SETUP');
// }

// Inisialisasi form dengan data dari props.user
const form = useForm({
    _method: 'PUT', // Atau 'PATCH', Laravel akan menanganinya
    name: props.user?.name || '',
    email: props.user?.email || '',
    phone_number: props.user?.phone_number || '',
    // Password tidak diisi secara default untuk edit, hanya diisi jika ingin diubah
    password: '',
    password_confirmation: '',
    role: props.user?.role || 'User',
    citizenship: props.user?.citizenship || null,
    identity_type: props.user?.identity_type || null,
    identity_number: props.user?.identity_number || '',
    points: props.user?.points || 0,
    is_active: props.user?.is_active !== undefined ? Boolean(props.user.is_active) : true, // Pastikan boolean
    email_verified_manually: !!props.user?.email_verified_at, // Jika ada tanggal, anggap terverifikasi
    // Untuk avatar, penanganannya berbeda (upload file)
    avatar: null, // Untuk file input avatar baru
});

// Jika Anda ingin menampilkan avatar saat ini
const currentAvatarUrl = computed(() => {
    // Asumsi props.user.avatar adalah URL ke gambar atau null
    // Jika avatar adalah path relatif dari public/storage, Anda mungkin perlu prefix
    // return props.user?.avatar ? `/storage/${props.user.avatar}` : null;
    return props.user?.avatar; // Sesuaikan ini jika URL avatar perlu dimodifikasi
});
const avatarPreview = ref(null);

function handleAvatarChange(event) {
    const file = event.target.files[0];
    if (file) {
        form.avatar = file; // Simpan objek File ke form
        // console.log('[Edit.vue] Avatar file selected:', form.avatar); // <-- DEBUG FILE
        // console.log('[Edit.vue] Avatar file name:', form.avatar.name);
        // console.log('[Edit.vue] Avatar file size:', form.avatar.size);
        // console.log('[Edit.vue] Avatar file type:', form.avatar.type);

        const reader = new FileReader();
        reader.onload = (e) => {
            avatarPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        form.avatar = null;
        avatarPreview.value = null;
    }
}
// --- LOGIKA UNTUK IDENTITY (Sama seperti Create.vue) ---
const identityTypeOptions = computed(() => {
    if (form.citizenship === 'WNI') return ['KTP', 'Pasport'];
    if (form.citizenship === 'WNA') return ['Pasport'];
    return [];
});

watch(() => form.citizenship, (newCitizenship, oldCitizenship) => {
    // Hanya reset jika citizenship benar-benar berubah dan bukan saat inisialisasi form
    if (newCitizenship !== oldCitizenship) {
        form.identity_type = null;
        form.identity_number = '';
        if (newCitizenship === 'WNA') {
            form.identity_type = 'Pasport';
        }
    }
});
// Pola dan title untuk nomor identitas (sama seperti Create.vue)
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
// --- AKHIR LOGIKA IDENTITY ---


const submitUpdate = () => {
    // Karena avatar adalah file, kita perlu mengirim sebagai FormData (POST request)
    // Inertia secara otomatis akan melakukan ini jika ada objek File di data form.
    console.log('[Edit.vue] Submitting form data:', form); // <-- DEBUG FORM DATA
    console.log('[Edit.vue] form.avatar before POST:', form.avatar);
    form.post(route('admin.users.update', props.user.id), { // form.post akan otomatis handle _method: 'PUT'
        preserveScroll: true,
        onError: (errors) => {
            console.error("Error updating user:", errors);
            // Jika ada error pada avatar, reset preview & file
            if (errors.avatar) {
                form.avatar = null;
                avatarPreview.value = null;
                // Reset input file juga jika perlu
                const avatarInput = document.getElementById('avatar');
                if (avatarInput) avatarInput.value = '';
            }
        },
        onSuccess: () => {
            // Pesan flash akan ditangani AdminLayout
            // Reset field password setelah sukses
            form.password = '';
            form.password_confirmation = '';
            // Reset avatar agar tidak terkirim lagi jika tidak diubah
            form.avatar = null;
            avatarPreview.value = null;
            const avatarInput = document.getElementById('avatar');
            if (avatarInput) avatarInput.value = '';
        }
    });
};

// Inisialisasi form.is_active saat komponen dimuat berdasarkan props.user.is_active
// dan form.email_verified_manually
onMounted(() => {
    form.is_active = props.user?.is_active === undefined ? true : Boolean(props.user.is_active);
    form.email_verified_manually = !!props.user?.email_verified_at;

    // Sinkronkan identity_type jika citizenship sudah ada nilainya saat load
    if (form.citizenship === 'WNA' && !form.identity_type) {
        form.identity_type = 'Pasport';
    }
});

</script>

<template>

    <Head :title="'Edit Pengguna - ' + (props.user?.name || '')" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Pengguna: {{ props.user?.name || 'Memuat...' }}
            </h2>
        </template>

        <div v-if="props.user" class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <form @submit.prevent="submitUpdate" class="p-6 space-y-6">
                        <!-- Avatar Preview dan Input -->
                        <div class="col-span-6 sm:col-span-4">
                            <InputLabel for="avatar" value="Foto Profil (Opsional)" />
                            <!-- Current Avatar -->
                            <div v-if="currentAvatarUrl && !avatarPreview" class="mt-2">
                                <img :src="currentAvatarUrl" :alt="props.user.name"
                                    class="rounded-full h-20 w-20 object-cover">
                            </div>
                            <!-- New Avatar Preview -->
                            <div v-if="avatarPreview" class="mt-2">
                                <img :src="avatarPreview" alt="Avatar Preview"
                                    class="rounded-full h-20 w-20 object-cover">
                            </div>
                            <input class="block w-full text-sm text-slate-500 mt-2
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-violet-50 dark:file:bg-slate-700 file:text-violet-700 dark:file:text-slate-300
                                hover:file:bg-violet-100 dark:hover:file:bg-slate-600
                                dark:text-slate-400" id="avatar" type="file" @input="handleAvatarChange"
                                accept="image/*" />
                            <InputError class="mt-2" :message="form.errors.avatar" />
                        </div>


                        <div>
                            <InputLabel for="name" value="Nama Lengkap" />
                            <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email"
                                required />
                            <InputError class="mt-2" :message="form.errors.email" />
                            <p v-if="!props.user.email_verified_at && !form.email_verified_manually"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">Email belum terverifikasi.</p>
                            <p v-if="form.email_verified_manually || (props.user.email_verified_at && !form.email_verified_manually)"
                                class="mt-1 text-xs text-green-600 dark:text-green-400">Email akan ditandai sebagai
                                terverifikasi.</p>
                        </div>

                        <div>
                            <InputLabel for="phone_number" value="Nomor Telepon (Opsional)" />
                            <TextInput id="phone_number" type="tel" class="mt-1 block w-full"
                                v-model="form.phone_number" />
                            <InputError class="mt-2" :message="form.errors.phone_number" />
                        </div>

                        <div class="border-t border-b border-gray-200 dark:border-gray-700 py-6 my-6 space-y-4">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Ubah Password Pengguna
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 -mt-3 mb-2">
                                Kosongkan field di bawah jika Anda tidak ingin mengubah password pengguna ini.
                            </p>
                            <div>
                                <InputLabel for="edit_password" value="Password Baru" />
                                <TextInput id="edit_password" type="password" class="mt-1 block w-full"
                                    v-model="form.password" autocomplete="new-password" />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>
                            <div>
                                <InputLabel for="edit_password_confirmation" value="Konfirmasi Password Baru" />
                                <TextInput id="edit_password_confirmation" type="password" class="mt-1 block w-full"
                                    v-model="form.password_confirmation" autocomplete="new-password" />
                                <!-- InputError untuk password_confirmation biasanya akan muncul di bawah form.errors.password jika validasi 'confirmed' gagal -->
                            </div>
                        </div>


                        <div>
                            <InputLabel for="role" value="Role Pengguna" />
                            <select id="role" v-model="form.role"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                required>
                                <option v-for="roleOpt in props.availableRoles" :key="roleOpt" :value="roleOpt">{{
                                    roleOpt }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.role" />
                        </div>

                        <div>
                            <InputLabel for="citizenship" value="Kewarganegaraan (Opsional)" />
                            <select id="citizenship" v-model="form.citizenship"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option :value="null">-- Pilih Kewarganegaraan --</option>
                                <option value="WNI">WNI</option>
                                <option value="WNA">WNA</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.citizenship" />
                        </div>

                        <div v-if="form.citizenship">
                            <InputLabel for="identity_type" value="Tipe Identitas (Opsional)" />
                            <select id="identity_type" v-model="form.identity_type"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                :disabled="identityTypeOptions.length === 0">
                                <option :value="null">-- Pilih Tipe Identitas --</option>
                                <option v-for="idType in identityTypeOptions" :key="idType" :value="idType">{{ idType }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.identity_type" />
                        </div>

                        <div v-if="form.identity_type">
                            <InputLabel for="identity_number" value="Nomor Identitas (Opsional)" />
                            <TextInput id="identity_number" type="text" class="mt-1 block w-full"
                                v-model="form.identity_number" @input="handleIdentityNumberInput"
                                :pattern="identityNumberPattern" :title="identityNumberTitle"
                                :maxlength="form.identity_type === 'KTP' ? 16 : (form.identity_type === 'Pasport' ? 12 : undefined)" />
                            <InputError class="mt-2" :message="form.errors.identity_number" />
                            <!-- ... petunjuk KTP/Paspor ... -->
                        </div>

                        <div>
                            <InputLabel for="points" value="Poin" />
                            <TextInput id="points" type="number" min="0" class="mt-1 block w-full"
                                v-model.number="form.points" />
                            <InputError class="mt-2" :message="form.errors.points" />
                            <!-- TODO: Tambahkan UI untuk konfirmasi password Admin jika Operator mengubah poin -->
                        </div>

                        <div class="block mt-4"> <!-- Tambahkan margin jika perlu -->
                            <label class="flex items-center">
                                <Checkbox name="is_active" v-model:checked="form.is_active" />
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Akun Aktif</span>
                            </label>
                            <InputError class="mt-2" :message="form.errors.is_active" />
                            <!-- Debugging Value -->
                            <!-- <p class="text-xs dark:text-gray-400">DB is_active: {{ props.user.is_active ? 'true (1)' : -->
                                <!-- 'false (0)' }}, Form is_active: {{ form.is_active }}</p> -->
                        </div>

                        <div class="block mt-4"> <!-- Tambahkan margin jika perlu -->
                            <label class="flex items-center">
                                <Checkbox name="email_verified_manually"
                                    v-model:checked="form.email_verified_manually" />
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Tandai Email Sebagai
                                    Terverifikasi (Manual)</span>
                            </label>
                            <InputError class="mt-2" :message="form.errors.email_verified_manually" />
                            <!-- Debugging Value -->
                            <!-- <p v-if="props.user.email_verified_at" class="text-xs dark:text-gray-400">DB -->
                                <!-- email_verified_at: {{ props.user.email_verified_at }} (Artinya Terverifikasi)</p> -->
                            <!-- <p v-else class="text-xs dark:text-gray-400">DB email_verified_at: null (Artinya Belum -->
                                <!-- Terverifikasi)</p> -->
                            <!-- <p class="text-xs dark:text-gray-400">Form email_verified_manually: {{ -->
                                <!-- form.email_verified_manually }}</p> -->

                            <!-- Pesan kondisional (ini sudah bagus) -->
                            <p v-if="!props.user.email_verified_at && !form.email_verified_manually && form.email !== props.user.email"
                                class="mt-1 text-xs text-orange-500 dark:text-orange-400">Email diubah dan akan
                                memerlukan verifikasi baru.</p>
                            <p v-else-if="!props.user.email_verified_at && form.email_verified_manually"
                                class="mt-1 text-xs text-green-600 dark:text-green-400">Email akan ditandai sebagai
                                terverifikasi.</p>
                            <p v-else-if="props.user.email_verified_at && !form.email_verified_manually"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">Verifikasi email akan dicabut.
                            </p>
                            <p v-else-if="props.user.email_verified_at && form.email_verified_manually"
                                class="mt-1 text-xs text-green-600 dark:text-green-400">Email sudah terverifikasi.</p>
                            <p v-else-if="!props.user.email_verified_at && !form.email_verified_manually"
                                class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">Email belum terverifikasi.</p>
                        </div>

                        <div class="flex items-center justify-end space-x-4">
                            <Link :href="route('admin.users.index')"
                                class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
                            Batal
                            </Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Simpan Perubahan
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div v-else class="py-12 text-center text-gray-500 dark:text-gray-400">
            Data pengguna tidak ditemukan atau gagal dimuat.
        </div>
    </AdminLayout>
</template>