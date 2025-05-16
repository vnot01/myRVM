<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Checkbox from '@/Components/Checkbox.vue'; // Untuk is_active
import { ref, computed, watch } from 'vue';

const props = defineProps({
    user: Object, // Data user yang akan diedit
    availableRoles: Array,
    errors: Object,
});

const form = useForm({
    _method: 'PATCH', // atau 'PUT'
    name: props.user.name,
    email: props.user.email,
    role: props.user.role,
    phone_number: props.user.phone_number || '',
    citizenship: props.user.citizenship || 'WNI',
    identity_type: props.user.identity_type || '',
    identity_number: props.user.identity_number || '',
    is_active: props.user.is_active ?? true, // Default true jika null
    avatar: null, // Untuk file upload baru
    password: '', // Kosongkan, hanya diisi jika ingin diubah
    password_confirmation: '',
});

// Untuk preview avatar
const avatarPreview = ref(props.user.avatar || null);

function selectNewAvatar(event) {
    const file = event.target.files[0];
    if (file) {
        form.avatar = file; // Simpan file object di form
        avatarPreview.value = URL.createObjectURL(file); // Buat URL preview lokal
    }
}

const submit = () => {
    // Karena ada file upload (avatar), kita harus submit sebagai FormData
    // Inertia akan otomatis melakukannya jika ada File object di dalam form.
    form.post(route('admin.users.update', props.user.id), { // .post akan menghandle _method: 'PATCH'
        onError: (formErrors) => {
            if (formErrors.password) {
                form.reset('password', 'password_confirmation');
            }
            // Jika ada error pada avatar, mungkin reset preview
            if (formErrors.avatar) {
                // avatarPreview.value = props.user.avatar; // Kembali ke avatar lama
                // form.avatar = null;
            }
        },
        onSuccess: () => {
            // Form akan otomatis di-reset oleh Inertia jika ada redirect dari backend
            // atau Anda bisa reset manual jika perlu
        }
    });
};

// Logika kondisional untuk Tipe Identitas (sama seperti Create.vue)
const citizenshipOptions = [
    { value: 'WNI', label: 'WNI (Warga Negara Indonesia)' },
    { value: 'WNA', label: 'WNA (Warga Negara Asing)' },
];

const identityTypeOptions = computed(() => {
    if (form.citizenship === 'WNI') {
        return [ { value: '', label: '-- Pilih Tipe --'}, { value: 'KTP', label: 'KTP' }, { value: 'Pasport', label: 'Pasport' }];
    } else if (form.citizenship === 'WNA') {
        return [ { value: '', label: '-- Pilih Tipe --'}, { value: 'Pasport', label: 'Pasport' }];
    }
    return [{ value: '', label: '-- Pilih Kewarganegaraan Dahulu --'}];
});

watch(() => form.citizenship, (newCitizenship, oldCitizenship) => {
    if (newCitizenship !== oldCitizenship) {
        const currentOptions = identityTypeOptions.value.map(opt => opt.value);
        if (!currentOptions.includes(form.identity_type)) {
            form.identity_type = currentOptions.length > 0 ? currentOptions[0] : '';
        }
        form.identity_number = '';
    }
});
watch(() => form.identity_type, (newType, oldType) => {
    if (newType !== oldType) {
        form.identity_number = '';
    }
});

</script>

<template>
    <Head :title="'Edit Pengguna - ' + user.name" />

    <AdminLayout :title="'Edit Pengguna: ' + user.name">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Pengguna: {{ user.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8">
                        <form @submit.prevent="submit">
                            <!-- Avatar -->
                            <div class="mb-4">
                                <InputLabel for="avatar" value="Foto Profil (Opsional)" />
                                <div class="mt-2 flex items-center gap-x-3">
                                    <img v-if="avatarPreview" :src="avatarPreview" alt="Avatar Preview" class="h-20 w-20 rounded-full object-cover">
                                    <div v-else class="h-20 w-20 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-400">
                                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                        </svg>
                                    </div>
                                    <input type="file" @input="selectNewAvatar" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-olive/10 file:text-brand-olive hover:file:bg-brand-olive/20 dark:file:bg-brand-teal/20 dark:file:text-brand-teal dark:hover:file:bg-brand-teal/30"/>
                                </div>
                                <InputError class="mt-2" :message="form.errors.avatar" />
                            </div>

                            <!-- Nama -->
                            <div>
                                <InputLabel for="name" value="Nama Lengkap" />
                                <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- Email -->
                            <div class="mt-4">
                                <InputLabel for="email" value="Email" />
                                <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <!-- Role -->
                             <!-- Admin tidak bisa mengubah role diri sendiri dari sini -->
                            <div class="mt-4">
                                <InputLabel for="role" value="Role Pengguna" />
                                <SelectInput
                                    id="role"
                                    class="mt-1 block w-full"
                                    v-model="form.role"
                                    :options="availableRoles.map(role => ({ value: role, label: role }))"
                                    required
                                    :disabled="user.id === $page.props.auth.user.id && user.role === 'Admin'"
                                />
                                <InputError class="mt-2" :message="form.errors.role" />
                            </div>

                            <!-- Status Aktif/Nonaktif -->
                            <div class="mt-4">
                                <label class="flex items-center">
                                    <Checkbox name="is_active" v-model:checked="form.is_active" />
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Akun Aktif</span>
                                </label>
                                <InputError class="mt-2" :message="form.errors.is_active" />
                            </div>


                            <!-- Password Baru (Opsional) -->
                            <div class="mt-6 border-t dark:border-slate-700 pt-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Ubah Password (kosongkan jika tidak ingin diubah)</p>
                                <div class="mt-4">
                                    <InputLabel for="password" value="Password Baru" />
                                    <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" autocomplete="new-password" />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>
                                <div class="mt-4">
                                    <InputLabel for="password_confirmation" value="Konfirmasi Password Baru" />
                                    <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" autocomplete="new-password" />
                                </div>
                            </div>

                            <!-- Kewarganegaraan, Tipe & No Identitas (Sama seperti Create) -->
                            <div class="mt-6 border-t dark:border-slate-700 pt-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Detail Identitas (Opsional)</p>
                                <div class="mt-4">
                                    <InputLabel for="citizenship_edit" value="Kewarganegaraan" />
                                    <SelectInput id="citizenship_edit" class="mt-1 block w-full" v-model="form.citizenship" :options="citizenshipOptions" @change="form.identity_type = ''; form.identity_number = '';" />
                                    <InputError class="mt-2" :message="form.errors.citizenship" />
                                </div>
                                <div class="mt-4" v-if="form.citizenship">
                                    <InputLabel for="identity_type_edit" value="Tipe Identitas" />
                                    <SelectInput id="identity_type_edit" class="mt-1 block w-full" v-model="form.identity_type" :options="identityTypeOptions" @change="form.identity_number = '';" />
                                    <InputError class="mt-2" :message="form.errors.identity_type" />
                                </div>
                                <div class="mt-4" v-if="form.identity_type">
                                    <InputLabel for="identity_number_edit" value="Nomor Identitas" />
                                    <TextInput id="identity_number_edit" type="text" class="mt-1 block w-full" v-model="form.identity_number"
                                        :maxlength="form.identity_type === 'KTP' ? 16 : (form.identity_type === 'Pasport' ? 10 : undefined)"
                                        :pattern="form.identity_type === 'KTP' ? '\\d{16}' : (form.identity_type === 'Pasport' ? '[A-Z0-9]{1,10}' : undefined)"
                                    />
                                    <InputError class="mt-2" :message="form.errors.identity_number" />
                                     <!-- Pesan error frontend bisa ditambahkan seperti di Create -->
                                </div>
                            </div>
                            <!-- Nomor Telepon -->
                            <div class="mt-4">
                                <InputLabel for="phone_number_edit" value="Nomor Telepon (Opsional)" />
                                <TextInput id="phone_number_edit" type="text" class="mt-1 block w-full" v-model="form.phone_number" />
                                <InputError class="mt-2" :message="form.errors.phone_number" />
                            </div>


                            <div class="flex items-center justify-end mt-6 space-x-4">
                                <Link :href="route('admin.users.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
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
        </div>
    </AdminLayout>
</template>