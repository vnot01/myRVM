<!-- <template>
  <AdminLayout title="Admin Dashboard"> 
    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow dark:text-gray-800">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Admin Dashboard RVM</h1>
      <p class="mt-2 text-gray-900 dark:text-gray-100">Selamat datang, {{ $page.props.auth.user.name }}!</p>
      <p class="mt-1 text-gray-900 dark:text-gray-100">Anda login sebagai: <span class="font-medium">{{ $page.props.auth.user.role }}</span></p>
      <p class="mt-1 text-gray-900 dark:text-gray-100">Email: <span class="font-medium">{{ $page.props.auth.user.email }}</span></p>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
// Tidak perlu Head karena sudah di AdminLayout
</script> -->
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import StatCard from '@/Components/Admin/StatCard.vue'; // Import StatCard

// Impor ikon Tabler yang akan digunakan
import { IconBuildingArch, IconUsers, IconChecklist, IconCoin, IconMessageCog } from '@tabler/icons-vue';

const props = defineProps({
    stats: Object, // { totalRvms, activeRvms, totalUsers, todayDepositsCount, todayPointsAwarded }
    activePromptInfo: Object,
    depositsChartData: Object, // Akan dipakai nanti
});

// Helper untuk format angka jika StatCard tidak melakukannya
// const formatNumber = (num) => num != null ? num.toLocaleString('id-ID') : '0';

</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Admin Dashboard
            </h2>
        </template>

        <div class="space-y-6">
            <!-- Baris Kartu Statistik -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <StatCard
                    title="Total RVM"
                    :value="props.stats?.totalRvms"
                    :icon="IconBuildingArch"
                    colorClass="bg-blue-500 dark:bg-blue-700"
                />
                <StatCard
                    title="RVM Aktif"
                    :value="props.stats?.activeRvms"
                    :icon="IconBuildingArch"
                    colorClass="bg-green-500 dark:bg-green-700"
                    :change="`${((props.stats?.activeRvms / (props.stats?.totalRvms || 1)) * 100).toFixed(0)}% dari total`"
                    changeType="neutral"
                />
                <StatCard
                    title="Total Pengguna"
                    :value="props.stats?.totalUsers"
                    :icon="IconUsers"
                    colorClass="bg-indigo-500 dark:bg-indigo-700"
                />
                 <StatCard
                    title="Prompt Aktif"
                    :value="props.activePromptInfo?.name || 'Belum Diatur'"
                    :icon="IconMessageCog"
                    colorClass="bg-purple-500 dark:bg-purple-700"
                >
                    <template #value> <!-- Menggunakan slot untuk kustomisasi tampilan value -->
                        <Link v-if="props.activePromptInfo" :href="route('admin.configured-prompts.edit', props.activePromptInfo.id)" class="text-2xl font-semibold text-gray-900 dark:text-white hover:underline">
                            {{ props.activePromptInfo.name }}
                        </Link>
                        <span v-else class="text-2xl font-semibold text-gray-900 dark:text-white">Belum Diatur</span>
                    </template>
                    <template #change v-if="props.activePromptInfo">
                        <span class="text-gray-500 dark:text-gray-400">Versi: {{ props.activePromptInfo.version }}</span>
                    </template>
                </StatCard>
            </div>

            <!-- Baris Kartu Statistik Deposit Hari Ini -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                 <StatCard
                    title="Deposit Hari Ini"
                    :value="props.stats?.todayDepositsCount"
                    :icon="IconChecklist"
                    colorClass="bg-orange-500 dark:bg-orange-600"
                />
                <StatCard
                    title="Poin Diberikan Hari Ini"
                    :value="props.stats?.todayPointsAwarded"
                    :icon="IconCoin"
                    colorClass="bg-pink-500 dark:bg-pink-600"
                />
            </div>

            <!-- Tempat untuk Grafik (Akan Diisi Nanti) -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-xl shadow-lg">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Statistik Deposit (7 Hari Terakhir) - Segera Hadir
                </h3>
                <div class="h-64 flex items-center justify-center text-gray-400 dark:text-gray-500">
                    [Grafik akan ditampilkan di sini]
                </div>
            </div>

        </div>
    </AdminLayout>
</template>