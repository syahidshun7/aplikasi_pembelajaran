<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBackgroundLayer from '@/Components/AppBackgroundLayer.vue';

const props = defineProps({
    status: { type: Number, default: 500 },
    title: { type: String, default: 'SYSTEM_FAILURE' },
    message: { type: String, default: 'Terjadi gangguan di server.' },
});

const meta = computed(() => {
    if (props.status === 400) {
        return {
            status: 400,
            code: '400_BAD_REQUEST',
            headline: 'Signal Rejected',
            badge: 'Packet Corrupted',
            actionText: 'Periksa ulang data yang kamu kirim lalu coba lagi.',
            accent: 'text-amber-400',
        };
    }

    if (props.status === 404) {
        return {
            status: 404,
            code: '404_NOT_FOUND',
            headline: 'Lost in the Dungeon',
            badge: 'Route Missing',
            actionText: 'Link tidak ditemukan. Pastikan URL benar atau kembali ke lobby.',
            accent: 'text-cyan-300',
        };
    }

    return {
        status: 500,
        code: '500_SERVER_ERROR',
        headline: 'System Failure',
        badge: 'Core Overload',
        actionText: 'Tunggu sebentar, lalu muat ulang halaman.',
        accent: 'text-red-400',
    };
});

const reload = () => window.location.reload();
const timestamp = new Date().toLocaleString('id-ID');
</script>

<template>
    <Head :title="`Error ${meta.status}`" />

    <div class="relative isolate min-h-screen bg-[#0a0c10]">
        <AppBackgroundLayer overlay-class="bg-black/70" />

        <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-10">
            <div class="w-full max-w-4xl rpg-panel bg-[#0f172a]/90 border-[#3d415f] text-white space-y-6">
                <div class="flex items-center justify-between text-[10px] uppercase tracking-widest">
                    <span class="text-cyan-300">System_Notice :: {{ title || meta.headline }}</span>
                    <span class="text-slate-500">Code {{ meta.status }}</span>
                </div>

                <div class="bg-[#0a0c10]/60 border border-[#3d415f] p-5 flex flex-col md:flex-row gap-4">
                    <div class="flex-1 space-y-3">
                        <div class="inline-flex items-center gap-2 px-3 py-1 border border-slate-700 bg-slate-900/70 uppercase text-[9px] tracking-widest">
                            <span class="text-slate-400">{{ meta.code }}</span>
                            <span class="px-2 py-0.5 bg-slate-800 text-[8px] border border-slate-700">{{ meta.badge }}</span>
                        </div>

                        <h1 class="text-2xl md:text-3xl font-black uppercase leading-tight" :class="meta.accent">
                            {{ meta.headline }}
                        </h1>

                        <p class="text-[11px] md:text-sm text-slate-300 leading-relaxed">
                            {{ message || meta.actionText }}
                        </p>

                        <p class="text-[10px] text-slate-400 font-mono">
                            {{ meta.actionText }}
                        </p>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <Link
                                :href="route('lobby')"
                                class="text-[10px] bg-[#009999] text-black px-4 py-2 btn-pixel border-[#006666] hover:bg-[#4ed4d4] transition-colors uppercase"
                            >
                                Kembali ke Lobby
                            </Link>
                            <button
                                type="button"
                                @click="reload"
                                class="text-[10px] bg-slate-900 text-cyan-200 px-4 py-2 btn-pixel border-slate-700 hover:bg-slate-700 transition-colors uppercase"
                            >
                                Muat Ulang
                            </button>
                        </div>
                    </div>

                    <div class="w-full md:w-64 bg-[#0d1117] border border-slate-800 p-4 space-y-2 font-mono text-[9px] text-slate-400">
                        <div class="flex items-center justify-between text-[8px] text-slate-500 uppercase">
                            <span>Diagnostics</span>
                            <span class="text-emerald-400">Active</span>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span>channel</span>
                                <span class="text-cyan-300">/core/lobby</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>status</span>
                                <span :class="meta.accent">{{ meta.code }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>trace</span>
                                <span class="text-amber-300">{{ title || meta.headline }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>hint</span>
                                <span class="text-slate-300">{{ meta.actionText }}</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-800">
                            <p class="text-[8px] text-slate-500 uppercase">If the issue persists, hubungi admin dan sertakan timestamp ini.</p>
                            <p class="text-[8px] text-emerald-400 mt-1">{{ timestamp }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-[9px] text-slate-400">
                    <div class="bg-[#0a0c10]/60 border border-slate-800 p-3">
                        <p class="text-emerald-300 mb-1 uppercase">Step 01</p>
                        <p>Pastikan koneksi stabil lalu ulangi aksi terakhir.</p>
                    </div>
                    <div class="bg-[#0a0c10]/60 border border-slate-800 p-3">
                        <p class="text-emerald-300 mb-1 uppercase">Step 02</p>
                        <p>Jika kamu baru saja mengirim form, periksa field wajib dan kirim ulang.</p>
                    </div>
                    <div class="bg-[#0a0c10]/60 border border-slate-800 p-3">
                        <p class="text-emerald-300 mb-1 uppercase">Step 03</p>
                        <p>Coba kembali ke lobby atau hubungi admin jika masalah berulang.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    outline: 2px solid #10121d;
    box-shadow: inset 0 0 0 2px #292c3d;
}
</style>
