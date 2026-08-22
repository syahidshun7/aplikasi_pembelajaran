<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    panelClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const closeOnEscape = (event) => {
    if (event.key === 'Escape' && props.show) {
        emit('close');
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));
</script>

<template>
    <div v-if="show" class="pixel-modal-backdrop fixed inset-0 z-[260] flex items-start justify-center overflow-y-auto bg-black/70 p-3 py-5 backdrop-blur-sm sm:p-4 md:items-center" @click.self="emit('close')">
        <div class="pixel-modal-panel w-full max-w-lg bg-[#1a1c2c] border-4 border-[#4ed4d4] p-4 shadow-[8px_8px_0_0_rgba(0,0,0,0.5)] font-['Press_Start_2P'] text-[#4ed4d4]" :class="panelClass" role="dialog" aria-modal="true" :aria-label="title || 'Dialog'">
            <!-- Header -->
            <div v-if="title" class="text-center mb-6 uppercase text-[10px] tracking-wider">
                {{ title }}
            </div>

            <!-- Body -->
            <div class="mb-6 text-center text-[9px] uppercase tracking-wider text-[#4ed4d4]">
                <slot name="content" />
            </div>

            <!-- Footer -->
            <div class="flex justify-center gap-6 mt-4">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
