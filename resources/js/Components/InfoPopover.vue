// resources/js/Components/InfoTooltip.vue (atau InfoPopover.vue)
<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { IconInfoCircle, IconX } from '@tabler/icons-vue';

const props = defineProps({
    textToShow: {
        type: String,
        required: true,
    },
    position: {
        type: String,
        default: 'top', // top, bottom, left, right
    },
    horizontalAlign: { // center, start, end (untuk top/bottom)
        type: String,
        default: 'center',
    }
});

const showPopover = ref(false);
const popoverRef = ref(null);
const buttonRef = ref(null);

const togglePopover = () => {
    showPopover.value = !showPopover.value;
};

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && showPopover.value) {
        showPopover.value = false;
    }
};

// Fungsi untuk menutup popover jika diklik di luar
const handleClickOutside = (event) => {
    if (popoverRef.value && !popoverRef.value.contains(event.target) &&
        buttonRef.value && !buttonRef.value.contains(event.target)) {
        showPopover.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleEscapeKey);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
    document.removeEventListener('keydown', handleEscapeKey);
});


// Kelas dinamis (sama seperti sebelumnya, tapi z-index mungkin perlu lebih tinggi jika modal)
const popoverPositionClasses = computed(() => { 
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
    <div class="relative inline-flex items-center">
        <button
            ref="buttonRef"
            type="button"
            @click="togglePopover"
            class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 focus:outline-none rounded-full p-0.5 hover:bg-gray-100 dark:hover:bg-gray-700"
            aria-label="Informasi tambahan"
            @mouseenter="showPopover = true"
            :aria-expanded="showPopover.toString()"
        >
            <IconInfoCircle class="w-4 h-4" />
        </button>

        <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
        <!-- Lebih cocok role dialog karena bisa ada interaksi -->
          <!-- Agar bisa di-fokus dan ditutup dengan Esc (perlu logika Esc tambahan) -->
           <!-- <div -->
                <!-- v-if="showTooltip" -->
                <!-- role="tooltip" -->
                <!-- :class="tooltipPositionClasses" -->
                <!-- class="w-64 p-3 text-xs font-normal text-left text-white bg-gray-800 rounded-lg shadow-xl dark:bg-slate-700 whitespace-normal break-words" -->
            <!-- > -->
                <!-- <!~~ Panah Tooltip (opsional) ~~> -->
                <!-- <div :class="arrowPositionClasses"></div> -->
                <!-- {{ textToShow }} -->
            <!-- </div> -->
            <div
                v-if="showPopover"
                ref="popoverRef"
                role="dialog"
                aria-modal="true"
                :class="popoverPositionClasses"
                class="w-64 p-3 text-xs font-normal text-left text-white bg-gray-800 rounded-lg shadow-xl dark:bg-slate-700 whitespace-normal break-words"
                tabindex="-1">
                <!-- Konten Popover -->
                <!-- <div class="flex justify-between items-center mb-2"> -->
                    <!-- <p class="font-semibold"></p> -->
                    <!-- <button @click="showPopover = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"> -->
                        <!-- <IconX class="w-4 h-4" /> -->
                    <!-- </button> -->
                <!-- </div> -->

                <div class="text-xs dark:text-slate-300 leading-relaxed select-text"> <!-- select-text agar bisa diseleksi -->
                    <pre class="whitespace-pre-wrap font-sans">{{ textToShow }}</pre>
                </div>

                <!-- Panah (opsional, bisa di-style lebih baik) -->
                <div :class="arrowPositionClasses" class="border-transparent dark:border-transparent"
                     :style="props.position === 'top' ? {'border-top-color': 'inherit', 'border-bottom-width': '0'} :
                              props.position === 'bottom' ? {'border-bottom-color': 'inherit', 'border-top-width': '0'} :
                              props.position === 'left' ? {'border-left-color': 'inherit', 'border-right-width': '0'} :
                              props.position === 'right' ? {'border-right-color': 'inherit', 'border-left-width': '0'} : {} ">
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
/* Untuk panah yang lebih baik dengan warna background yang benar */
/* Ini adalah contoh dasar, mungkin perlu disesuaikan */
[role="tooltip"] > div[class*="-bottom-1.5"] { /* Panah di bawah (tooltip di atas) */
    border-top-color: inherit; /* Mewarisi warna dari bg-gray-800 atau bg-slate-700 */
}
[role="tooltip"] > div[class*="-top-1.5"] { /* Panah di atas (tooltip di bawah) */
    border-bottom-color: inherit;
}
[role="tooltip"] > div[class*="-right-1.5"] { /* Panah di kanan (tooltip di kiri) */
    border-left-color: inherit;
}
[role="tooltip"] > div[class*="-left-1.5"] { /* Panah di kiri (tooltip di kanan) */
    border-right-color: inherit;
}

/* Pastikan teks di dalam pre bisa di-select */
.select-text pre {
  user-select: text;
  -webkit-user-select: text; /* Safari */
  -ms-user-select: text; /* IE 10+ */
}
</style>