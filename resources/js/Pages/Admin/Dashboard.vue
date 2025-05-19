<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatCard from '@/Components/Admin/StatCard.vue'; // Impor StatCard
import LineChart from '@/Components/Admin/Charts/LineChart.vue'; // Impor LineChart
import PieChart from '@/Components/Admin/Charts/PieChart.vue';

const props = defineProps({
    stats: Object,
    activePromptInfo: Object,
    depositsChartData: Object,
    itemDistributionChartData: Object,
});
// console.log('Data Chart dari Controller:', JSON.parse(JSON.stringify(props.depositsChartData)));
// Placeholder ikon SVG (Anda bisa ganti dengan yang lebih baik atau library ikon)
const rvmStatusIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
const rvmIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12M3.75 3v9h9V3H3.75Zm0 0h9m-9 0V3m9 0V3m0 0h3.75m-3.75 0h3.75M3.75 12v9h9v-9H3.75Zm0 0H3.75m9 0H3.75m0 0v9m9-9v9m0-9h.008v.008H12.75V12Zm0 0h3.75m-3.75 0h3.75M3.75 3h.008v.008H3.75V3Zm0 0h3.75m-3.75 0h3.75M3.75 12h.008v.008H3.75V12Zm0 0h3.75m-3.75 0h3.75" /></svg>`;
const userIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>`;
const depositIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10.5 11.25h3M12 17.25v-6.75" /></svg>`;
const pointsIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.82.61l-4.725-2.885a.562.562 0 0 0-.652 0l-4.725 2.885a.562.562 0 0 1-.82-.61l1.285-5.385a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>`;
const aiIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18.75 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L22.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.5 13.5h3.75v3.75h-3.75V13.5Z" /></svg>`;
const promptIcon = `<svg class="mr-3 w-7 h-7 border-green-300 dark:border-green-600 animate-pulse text-green-700 dark:text-green-400 transition duration-75 group-hover:text-gray-900 dark:group-hover:text-gray-300 icon icon-tabler icons-tabler-outline icon-tabler-terminal" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<path stroke="none" d="M0 0h24v24H0z" fill="none"/>
<path d="M5 7l5 5l-5 5" /><path d="M12 19l7 0" /></svg>`;

function formatChartLabel(label) {
    if (!label || typeof label !== 'string') {
        return 'Tidak Diketahui';
    }
    return label.toLowerCase().split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

const formattedItemDistributionChartData = computed(() => {
    if (!props.itemDistributionChartData || !props.itemDistributionChartData.labels) {
        return { labels: [], datasets: [] };
    }
    return {
        ...props.itemDistributionChartData,
        labels: props.itemDistributionChartData.labels.map(label => formatChartLabel(label))
    };
});

const rvmStatusDescriptionParts = computed(() => {
    if (!props.stats) return [];
    const parts = [];
    if (props.stats.inactiveRvms > 0) {
        parts.push({ text: `${props.stats.inactiveRvms} Inactive`, class: 'text-gray-300 dark:text-gray-300' });
    }
    if (props.stats.maintenanceRvms > 0) {
        parts.push({ text: `${props.stats.maintenanceRvms} Maintenance`, class: 'text-gray-300 dark:text-gray-300' });
    }
    if (props.stats.fullRvms > 0) {
        parts.push({
            text: `${props.stats.fullRvms} Full`,
            class: 'text-amber-700 dark:text-amber-700 font-bold animate-blink' // <-- KELAS BARU
        });
    }
    return parts;
});

// Opsi spesifik untuk chart deposit kita
const depositChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function(value) { if (Number.isInteger(value)) { return value; } },
                stepSize: 1 // Atau sesuaikan jika jumlah deposit bisa sangat besar
            },
            title: {
                display: true,
                text: 'Jumlah'
            }
        },
        x: {
            grid: {
                display: false
            },
            title: {
                display: true,
                text: 'Tanggal'
            }
        }
    },
    plugins: {
        legend: {
            position: 'top',
        },
        title: {
            display: true,
            text: 'Aktivitas Deposit 7 Hari Terakhir' // Judul chart
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) {
                        label += ': ';
                    }
                    if (context.parsed.y !== null) {
                        label += context.parsed.y;
                        if (context.dataset.label === 'Total Deposit per Hari') {
                            label += ' deposit';
                        } else if (context.dataset.label === 'Total Poin per Hari') {
                            label += ' poin';
                        }
                    }
                    return label;
                }
            }
        }
    }
}));

// Opsi spesifik untuk pie chart distribusi item
const itemDistributionOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right', // Legenda di kanan untuk pie chart seringkali lebih baik
        },
        title: {
            display: true,
            text: 'Distribusi Jenis Item Diterima'
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    let label = context.label || '';
                    if (label) {
                        label += ': ';
                    }
                    if (context.parsed !== null) {
                        label += context.parsed;
                        let total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        if (total > 0) {
                            let percentage = (context.parsed / total * 100).toFixed(1) + '%';
                            label += ' (' + percentage + ')';
                        }
                    }
                    return label;
                }
            }
        }
    }
}));

// Jika Anda ingin menambahkan dataset poin ke chart yang sama
const combinedDepositsAndPointsChartData = computed(() => {
    // Pengecekan ini penting untuk menghindari error jika data tidak ada
    // Pastikan props.depositsChartData memiliki struktur yang benar
    if (!props.depositsChartData || !props.depositsChartData.labels) { 
        return { labels: [], datasets: [] }; // Kembalikan struktur aman
    }

    // Asumsikan props.depositsChartData dari controller sudah memiliki struktur:
    // labels: ['2024-05-10', '2024-05-11', ...],
    // datasets: [
    //   { label: 'Total Deposit per Hari', data: [5, 10, ...], backgroundColor: '...', borderColor: '...' },
    //   // { label: 'Total Poin per Hari', data: [50, 100, ...], backgroundColor: '...', borderColor: '...' } // Jika ada dari controller
    // ]

    // Jika data poin belum ada di props.depositsChartData.datasets,
    // Anda perlu memodifikasi controller untuk menyiapkannya, atau memprosesnya di sini jika data mentahnya ada.
    // Untuk contoh ini, kita asumsikan controller sudah mengirim data poin juga jika diinginkan.
    // Jika tidak, kita hanya akan menampilkan data deposit.

    // Contoh jika Anda ingin menambahkan dataset poin secara manual di frontend (kurang ideal, lebih baik dari backend)
    // const pointsData = props.depositsChartData.labels.map(date => {
    //     // Anda perlu cara untuk mendapatkan data poin per tanggal di sini
    //     // Misalnya, jika props.depositsChartData.pointsByDate adalah objek { '2024-05-10': 50, ... }
    //     return props.depositsChartData.pointsByDate[date] || 0;
    // });

    return {
        labels: props.depositsChartData.labels,
        datasets: [
            // Dataset untuk Total Deposit (dari props)
            ...(props.depositsChartData.datasets || []),

            // Contoh jika ingin menambahkan dataset Poin secara dinamis di frontend
            // Ini lebih baik jika disiapkan di backend dan dikirim dalam props.depositsChartData.datasets
            // {
            //     label: 'Total Poin per Hari',
            //     backgroundColor: '#f97316', // Warna oranye
            //     borderColor: '#ea580c',
            //     data: pointsData, // Data poin yang sudah diproses
            //     tension: 0.1,
            //     yAxisID: 'yPoin' // Jika ingin sumbu Y terpisah untuk poin
            // }
        ]
    };
});

// Jika Anda menggunakan sumbu Y terpisah untuk poin:
// const depositChartOptionsWithDualAxis = computed(() => ({
//     ...depositChartOptions.value, // Ambil opsi dasar
//     scales: {
//         ...depositChartOptions.value.scales,
//         yPoin: { // Definisi sumbu Y kedua
//             type: 'linear',
//             display: true,
//             position: 'right',
//             beginAtZero: true,
//             grid: {
//                 drawOnChartArea: false, // only want the grid lines for one axis to show up
//             },
//             title: {
//                 display: true,
//                 text: 'Poin'
//             }
//         }
//     }
// }));

</script>

<template>

    <Head title="Admin Dashboard" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Dashboard Utama
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="mb-6 text-gray-900 dark:text-gray-100 text-lg">
                        Selamat datang di Dashboard Admin!
                    </div>
                </div>

                <!-- Kartu Statistik -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                    <div>
                    <div v-if="activePromptInfo"
                        class="mt-8 bg-green-50 dark:bg-green-800/30 border-green-500 dark:border-green-400 animate-pulse shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">
                            <span v-html="promptIcon" class=" inline-block w-6 h-6 mr-6 align-middle"></span>
                            <span class="inline-block align-middle">Prompt AI Aktif Saat Ini</span>
                        </h3>
                        <p class="text-gray-700 dark:text-gray-300">
                            Nama: <span class="font-semibold">{{ activePromptInfo.name }}</span> (Versi: {{
                                activePromptInfo.version }})
                        </p>
                        <p class="text-gray-700 dark:text-gray-300">
                            Skor Kepercayaan Estimasi: <span class="font-semibold">{{ activePromptInfo.score ?
                                (activePromptInfo.score * 100).toFixed(0) + '%' : 'N/A' }}</span>
                        </p>
                        <Link :href="route('admin.configured-prompts.edit', activePromptInfo.id)"
                            class="mt-2 inline-block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        Lihat/Edit Prompt Aktif →
                        </Link>
                    </div>
                    <div v-else
                        class="mt-8 bg-yellow-100 dark:bg-yellow-700 border-l-4 border-yellow-500 dark:border-yellow-300 text-yellow-700 dark:text-yellow-200 p-4 shadow-sm sm:rounded-lg"
                        role="alert">
                        <div class="flex">
                            <div class="py-1"><svg
                                    class="fill-current h-6 w-6 text-yellow-500 dark:text-yellow-300 mr-4"
                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path
                                        d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zM9 5v6h2V5H9zm0 8v2h2v-2H9z" />
                                </svg></div>
                            <div>
                                <p class="font-bold">Tidak Ada Prompt AI Aktif</p>
                                <p class="text-sm">Sistem mungkin tidak dapat memproses deposit dengan benar. Silakan
                                    aktifkan salah satu konfigurasi prompt di Manajemen Prompt AI.</p>
                                <Link :href="route('admin.configured-prompts.index')"
                                    class="mt-2 inline-block font-semibold text-yellow-800 dark:text-yellow-100 hover:underline">
                                Ke Manajemen Prompt AI →
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                    <StatCard title="Total RVM Terdaftar" :value="stats.totalRvms" :icon="rvmIcon"
                        colorClass="bg-sky-500" :link="route('admin.rvms.index')" linkText="Lihat Semua RVM" />
                    <StatCard title="RVM Aktif" :value="`${stats.activeRvms}`" :icon="rvmStatusIcon"
                        colorClass="bg-green-600 dark:bg-green-700" :link="route('admin.rvms.index')"
                        linkText="Lihat Semua RVM">
                        <!-- Menggunakan slot untuk deskripsi kustom -->
                        <template #description>
                            <span v-for="(part, index) in rvmStatusDescriptionParts" :key="index" :class="part.class">
                                {{ part.text }}
                                <span v-if="index < rvmStatusDescriptionParts.length - 1">, </span>
                            </span>
                            <span
                                v-if="rvmStatusDescriptionParts.length === 0 && stats.activeRvms === stats.totalRvms && stats.totalRvms > 0">
                                Semua RVM beroperasi normal.
                            </span>
                            <span v-if="rvmStatusDescriptionParts.length === 0 && stats.totalRvms === 0">
                                Belum ada RVM.
                            </span>
                        </template>
                    </StatCard>

                    <StatCard title="Total User Terdaftar" :value="stats.totalUsers" :icon="userIcon"
                        colorClass="bg-indigo-500" :link="route('admin.users.index')" linkText="Lihat Semua User" />
                    <StatCard title="Deposit Hari Ini" :value="stats.todayDepositsCount" :icon="depositIcon"
                        colorClass="bg-amber-500" :description="`Poin: ${stats.todayPointsAwarded}`" />
                    <StatCard title="Deposit Minggu Ini" :value="stats.weekDepositsCount" :icon="depositIcon"
                        colorClass="bg-orange-500" :description="`Poin: ${stats.weekPointsAwarded}`" />
                    <StatCard title="Deposit Bulan Ini" :value="stats.monthDepositsCount" :icon="depositIcon"
                        colorClass="bg-rose-500" :description="`Poin: ${stats.monthPointsAwarded}`" />
                    
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- ... LineChart ... -->
                    <div class="mt-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Statistik Deposit 7 Hari Terakhir
                        </h3>
                        <div class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-lg">
                            <!-- <LineChart/> -->
                            <div
                                v-if="depositsChartData && depositsChartData.labels && depositsChartData.labels.length > 0">
                                <LineChart :data="combinedDepositsAndPointsChartData" :options="depositChartOptions"
                                    :height="300" />
                            </div>
                            <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <p>Data grafik tidak tersedia atau belum ada aktivitas deposit dalam 7 hari terakhir.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Distribusi Item -->
                    <!-- ... PieChart ... -->
                    <div class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Distribusi Jenis Item
                        </h3>
                        <div v-if="formattedItemDistributionChartData && formattedItemDistributionChartData.labels && formattedItemDistributionChartData.labels.length > 0"
                            style="height: 350px;">
                            <PieChart :chart-data="formattedItemDistributionChartData"
                                :chart-options="itemDistributionOptions" />
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <p>Data distribusi item tidak tersedia.</p>
                        </div>
                    </div>
                </div>
                <!-- Informasi Prompt Aktif -->
                


            </div>
        </div>
    </AdminLayout>
</template>