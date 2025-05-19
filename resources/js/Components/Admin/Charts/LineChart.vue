<script setup>
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Filler // Jika ingin area fill di bawah garis
} from 'chart.js';
import { ref, watch, onMounted } from 'vue';

// Daftarkan komponen dan skala yang akan digunakan oleh Chart.js
ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Filler
);

const props = defineProps({
  chartId: {
    type: String,
    default: 'line-chart',
  },
  datasetIdKey: {
    type: String,
    default: 'label',
  },
  width: {
    type: Number,
    default: 400,
  },
  height: {
    type: Number,
    default: 200, // Sesuaikan tinggi default
  },
  cssClasses: {
    default: '',
    type: String,
  },
  styles: {
    type: Object,
    default: () => {},
  },
  plugins: { // Opsi untuk plugin Chart.js
    type: Array,
    default: () => [],
  },
  data: { // Ganti nama dari chartData menjadi data
    type: Object,
    required: true,
  },
  // chartData: { // Data utama untuk chart (labels, datasets)
  //   type: Object,
  //   required: true,
  //   default: () => ({
  //       labels: [],
  //       datasets: []
  //   })
  // },
  options: { // Opsi kustom untuk Chart.js
    type: Object,
    default: () => ({
        responsive: true,
        maintainAspectRatio: false, // Penting agar chart bisa mengisi kontainer
        scales: {
            y: {
                beginAtZero: true, // Mulai sumbu Y dari 0
                ticks: {
                    // Pastikan hanya integer yang ditampilkan di sumbu Y jika data adalah integer
                    callback: function(value) { if (Number.isInteger(value)) { return value; } },
                    stepSize: 1 // Atau sesuaikan stepSize jika perlu
                }
            },
            x: {
                grid: {
                    display: false // Sembunyikan grid sumbu X jika diinginkan
                }
            }
        },
        plugins: {
            legend: {
                position: 'top', // Posisi legenda
            },
            title: {
                display: true,
                text: 'Chart Title Placeholder' // Judul default, bisa di-override
            }
        }
    }),
  }
});
const reactiveData = ref({
  labels: props.data?.labels || [],
  datasets: props.data?.datasets || []
});
const reactiveOptions = ref(props.options);
// Reactive reference untuk data chart agar bisa diupdate
const reactiveChartData = ref({
  labels: props.chartData?.labels || [], // Gunakan optional chaining dan fallback
  datasets: props.chartData?.datasets || [] // Gunakan optional chaining dan fallback
});

// Watcher untuk memperbarui chart jika data dari props berubah
watch(() => props.chartData, (newData) => {
  if (newData && newData.labels && newData.datasets) { // Pastikan newData valid
    reactiveChartData.value = newData;
  } else {
    // Jika data baru tidak valid, mungkin reset ke state kosong/aman
    reactiveChartData.value = { labels: [], datasets: [] };
  }
}, { deep: true, immediate: true }); // Tambahkan immediate: true

// Jika Anda ingin chartOptions juga bisa reaktif dari props
const reactiveChartOptions = ref(props.chartOptions);
watch(() => props.chartOptions, (newOptions) => {
    reactiveChartOptions.value = newOptions;
}, { deep: true, immediate: true });

// onMounted(() => {
//     // Jika ada data awal, pastikan sudah ter-set
//     reactiveChartData.value = props.chartData;
//     reactiveChartOptions.value = props.chartOptions;
// });

</script>
<!-- Langsung teruskan -->
 <!-- Langsung teruskan -->
<template>
  <Line
    :options="reactiveOptions" 
    :data="reactiveData"       
    :chart-id="chartId"
    :dataset-id-key="datasetIdKey"
    :plugins="plugins"
    :css-classes="cssClasses"
    :styles="styles"
    :width="width"
    :height="height"
  />
</template>