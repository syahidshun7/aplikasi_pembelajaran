<script setup>
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
        <div class="w-full max-w-lg bg-[#1a1c2c] border-4 border-[#4ed4d4] p-4 shadow-[8px_8px_0_0_rgba(0,0,0,0.5)] font-['Press_Start_2P'] text-[#4ed4d4]">
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
