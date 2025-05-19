<script setup>
import { Pie } from 'vue-chartjs'; // Atau Doughnut jika Anda mau
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  ArcElement, // Penting untuk Pie/Doughnut
  CategoryScale, // Meskipun pie tidak selalu butuh category scale, kadang diperlukan
  // LinearScale // Tidak selalu dibutuhkan untuk pie, kecuali ada kustomisasi sumbu
} from 'chart.js';
import { ref, watch } from 'vue';

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale
  // LinearScale
);

const props = defineProps({
  chartId: {
    type: String,
    default: 'pie-chart',
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
    default: 400, // Pie chart biasanya lebih baik jika rasio 1:1
  },
  cssClasses: {
    default: '',
    type: String,
  },
  styles: {
    type: Object,
    default: () => {},
  },
  plugins: {
    type: Array,
    default: () => [],
  },
  chartData: { // Ini adalah prop yang kita terima dari parent (Admin/Dashboard.vue)
    type: Object,
    required: true,
    // default: () => ({ labels: [], datasets: [{ data: [], backgroundColor: [] }] }) // Hapus default jika required true
  },
  chartOptions: {
    type: Object,
    default: () => ({
        responsive: true,
        maintainAspectRatio: false, // Atau true jika ingin rasio tetap
        plugins: {
            legend: {
                position: 'top', // Atau 'right', 'bottom'
            },
            title: {
                display: true,
                text: 'Chart Title Placeholder'
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
                            // Tambahkan persentase jika mau
                            // let total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            // let percentage = (context.parsed / total * 100).toFixed(2) + '%';
                            // label += ' (' + percentage + ')';
                        }
                        return label;
                    }
                }
            }
        }
    }),
  }
});

const reactiveChartData = ref({
  labels: props.chartData?.labels || [],
  datasets: props.chartData?.datasets || [{ data: [], backgroundColor: [] }]
});

watch(() => props.chartData, (newData) => {
  if (newData && newData.labels && newData.datasets) {
    reactiveChartData.value = newData;
  } else {
    reactiveChartData.value = { labels: [], datasets: [{ data: [], backgroundColor: [] }] };
  }
}, { deep: true, immediate: true });

const reactiveChartOptions = ref(props.chartOptions);
watch(() => props.chartOptions, (newOptions) => {
    reactiveChartOptions.value = newOptions;
}, { deep: true, immediate: true });

</script>

<template>
  <Pie
    :options="reactiveChartOptions"
    :data="reactiveChartData"
    :chart-id="chartId"
    :dataset-id-key="datasetIdKey"
    :plugins="plugins"
    :css-classes="cssClasses"
    :styles="styles"
    :width="width"
    :height="height"
  />
</template>