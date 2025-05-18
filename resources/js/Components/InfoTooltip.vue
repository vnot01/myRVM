<script setup>
import { ref, computed } from 'vue';
import { IconInfoCircle } from '@tabler/icons-vue'; // Menggunakan ikon dari Tabler

const props = defineProps({
    textToShow: {
        type: String,
        required: true,
    },
    // Menentukan posisi tooltip relatif terhadap ikon
    // 'top', 'bottom', 'left', 'right'
    // Defaultnya akan kita buat 'top' seperti sebelumnya
    position: {
        type: String,
        default: 'top', // Default posisi di atas ikon
    },
    // Untuk alignment horizontal jika posisi top/bottom
    // 'center', 'start', 'end'
    horizontalAlign: {
        type: String,
        default: 'center', // Default di tengah ikon
    }
    // Anda bisa menambahkan props lain untuk kustomisasi posisi, warna, dll.
    // Misalnya: position: { type: String, default: 'bottom' } // 'top', 'bottom', 'left', 'right'
});

const showTooltip = ref(false);
const tooltipRef = ref(null); // Untuk mendapatkan referensi ke elemen tooltip jika perlu kalkulasi posisi lanjutan

// Posisi tooltip bisa dikelola dengan lebih canggih jika diperlukan,
// untuk sekarang kita akan gunakan styling CSS dasar.
// Kelas dinamis untuk posisi tooltip
const tooltipPositionClasses = computed(() => {
    let classes = 'absolute z-50 '; // z-index lebih tinggi

    // Posisi Vertikal & Horizontal Alignment
    if (props.position === 'top') {
        classes += 'bottom-full mb-2 '; // Di atas ikon dengan margin bawah
        if (props.horizontalAlign === 'center') {
            classes += 'left-1/2 -translate-x-1/2 ';
        } else if (props.horizontalAlign === 'start') {
            classes += 'left-0 ';
        } else if (props.horizontalAlign === 'end') {
            classes += 'right-0 ';
        }
    } else if (props.position === 'bottom') {
        classes += 'top-full mt-2 '; // Di bawah ikon dengan margin atas
        if (props.horizontalAlign === 'center') {
            classes += 'left-1/2 -translate-x-1/2 ';
        } else if (props.horizontalAlign === 'start') {
            classes += 'left-0 ';
        } else if (props.horizontalAlign === 'end') {
            classes += 'right-0 ';
        }
    } else if (props.position === 'left') {
        classes += 'right-full mr-2 top-1/2 -translate-y-1/2 '; // Di kiri ikon
    } else if (props.position === 'right') {
        classes += 'left-full ml-2 top-1/2 -translate-y-1/2 '; // Di kanan ikon
    }
    return classes;
});

// Kelas dinamis untuk panah tooltip
const arrowPositionClasses = computed(() => {
    let classes = 'absolute w-3 h-3 bg-inherit transform rotate-45 ';
    if (props.position === 'top') {
        classes += '-bottom-1.5 '; // Panah di bawah tooltip, menunjuk ke atas
        if (props.horizontalAlign === 'center') classes += 'left-1/2 -translate-x-1/2';
        else if (props.horizontalAlign === 'start') classes += 'left-2'; // Sesuaikan jika perlu
        else if (props.horizontalAlign === 'end') classes += 'right-2'; // Sesuaikan jika perlu

    } else if (props.position === 'bottom') {
        classes += '-top-1.5 '; // Panah di atas tooltip, menunjuk ke bawah
         if (props.horizontalAlign === 'center') classes += 'left-1/2 -translate-x-1/2';
        else if (props.horizontalAlign === 'start') classes += 'left-2';
        else if (props.horizontalAlign === 'end') classes += 'right-2';

    } else if (props.position === 'left') {
        classes += '-right-1.5 top-1/2 -translate-y-1/2 '; // Panah di kanan tooltip, menunjuk ke kiri
    } else if (props.position === 'right') {
        classes += '-left-1.5 top-1/2 -translate-y-1/2 '; // Panah di kiri tooltip, menunjuk ke kanan
    }
    return classes;
});
</script>

<template>
    <div class="relative inline-flex items-center group"> <!-- Tambahkan 'group' untuk group-hover -->
        <button
            type="button"
            @mouseenter="showTooltip = true"
            @mouseleave="showTooltip = false"
            @focus="showTooltip = true"
            @blur="showTooltip = false"
            class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 focus:outline-none"
            aria-label="Informasi tambahan"
        >
            <IconInfoCircle class="w-4 h-4" />
        </button>

        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
        >
            <div
                v-if="showTooltip"
                role="tooltip"
                :class="tooltipPositionClasses"
                class="w-64 p-3 text-xs font-normal text-left text-white bg-gray-800 rounded-lg shadow-xl dark:bg-slate-700 whitespace-normal break-words"
            >
                <!-- Panah Tooltip (opsional) -->
                <div :class="arrowPositionClasses"></div>
                {{ textToShow }}
            </div>
        </transition>
    </div>
</template>

<style scoped>
/* Styling tambahan jika diperlukan, misalnya untuk panah tooltip yang lebih presisi */
/* .tooltip-arrow { ... } */
</style>