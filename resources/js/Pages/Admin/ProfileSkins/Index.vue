<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminNavbar from '@/Components/AdminNavbar.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    skins: {
        type: Object,
        required: true,
    },
});

const showFormModal = ref(false);
const isEditing = ref(false);
const editId = ref(null);
const deleteId = ref(null);
const showDeleteModal = ref(false);
const bundleInput = ref(null);
const updateBundleInput = ref(null);
const updateBundleSkinId = ref(null);
const importManifest = ref(null);

const form = useForm({
    name: '',
    description: '',
    price_gold: 500,
    renderer_type: 'vue_template',
    template_key: 'asset_showcase',
    hero_gradient: 'linear-gradient(135deg, #0d0015 0%, #1a0a2e 50%, #0d1117 100%)',
    accent_color: '#a855f7',
    border_color: '#7c3aed',
    glow_color: 'rgba(168,85,247,0.35)',
    stat_panel_bg: '#130d1f',
    text_primary: '#c4b5fd',
    is_active: true,
    preview_image: null,
    background_image: null,
    avatar_frame_image: null,
    panel_image: null,
    decoration_image: null,
    _method: 'POST',
});
const importForm = useForm({
    bundle_files: [],
    relative_paths: [],
});

const bundleStructureExample = `my-profile-skin/
  skin.json
  index.html
  css/style.css
  js/skin.js
  assets/preview.png
  assets/background.png`;

const manifestExample = `{
  "skin": {
    "name": "Cyber Terminal",
    "slug": "cyber-terminal",
    "description": "Profile skin project statis.",
    "renderer_type": "project_static",
    "template_key": "project_static",
    "is_active": true
  },
  "shop": {
    "price_gold": 800
  },
  "assets": {
    "preview": "assets/preview.png",
    "background": "assets/background.png"
  },
  "project": {
    "entry": "index.html"
  }
}`;

const profilePayloadExample = `{
  "type": "dooptech:profile-skin-data",
  "user": {
    "id": 12,
    "uuid": "user-uuid",
    "name": "Budi Santoso",
    "username": "budi",
    "profile_photo": "profile-photos/budi.png",
    "email": "budi@example.com",
    "job_id": 3,
    "job_name": "Frontend Developer",
    "job_emblem_path": "jobs/frontend.png",
    "gold": 1250,
    "lvl": 7,
    "exp": 3420,
    "level_progress": {
      "level": 7,
      "title": "Adept",
      "progress_percent": 42,
      "exp_in_level": 420,
      "exp_needed": 1000,
      "is_max_level": false
    },
    "role": "student",
    "staff_play_mode": false,
    "bio": "Belajar sambil membangun project.",
    "experience": "HTML, CSS, JavaScript",
    "location": "Jakarta",
    "skills": ["Vue", "Laravel", "UI Design"],
    "active_skin": {}
  },
  "activeSkin": {
    "id": 5,
    "name": "Cyber Terminal",
    "slug": "cyber-terminal",
    "template_key": "project_static",
    "preview_image_path": "profile-skins/cyber/preview.png",
    "project_entry_path": "profile-skins/cyber/project/index.html",
    "project_root_path": "profile-skins/cyber/project",
    "project_manifest": {}
  },
  "stats": {
    "averageGrade": 88.5,
    "totalCompleted": 21,
    "creationCount": 4,
    "appreciationCount": 37
  },
  "classAverages": [
    {
      "study_group_id": 2,
      "class_name": "Web Basic",
      "average_grade": 91.2,
      "total_quests": 10,
      "completed_quests": 9
    }
  ],
  "creations": [
    {
      "id": 101,
      "slug": "portfolio-web",
      "title": "Portfolio Web",
      "description": "Project publik user.",
      "category": "Website",
      "tags": ["vue", "css"],
      "thumbnail_url": "/storage/creations/portfolio.png",
      "appreciations_count": 12,
      "insights_count": 3,
      "team_size": 1,
      "ownership_type": "owner",
      "created_at": "2026-06-03T10:00:00.000000Z"
    }
  ],
  "urls": {
    "profilePhoto": "/storage/profile-photos/budi.png",
    "hallOfCreations": "/hall/creations",
    "lobby": "/lobby"
  }
}`;

const skinItems = computed(() => props.skins?.data || []);
const paginationLinks = computed(() => props.skins?.links || []);

const resetForm = () => {
    form.reset();
    form.price_gold = 500;
    form.renderer_type = 'vue_template';
    form.template_key = 'asset_showcase';
    form.hero_gradient = 'linear-gradient(135deg, #0d0015 0%, #1a0a2e 50%, #0d1117 100%)';
    form.accent_color = '#a855f7';
    form.border_color = '#7c3aed';
    form.glow_color = 'rgba(168,85,247,0.35)';
    form.stat_panel_bg = '#130d1f';
    form.text_primary = '#c4b5fd';
    form.is_active = true;
    form.preview_image = null;
    form.background_image = null;
    form.avatar_frame_image = null;
    form.panel_image = null;
    form.decoration_image = null;
    form._method = 'POST';
    form.clearErrors();
};

const openCreateModal = () => {
    isEditing.value = false;
    editId.value = null;
    resetForm();
    showFormModal.value = true;
};

const startEdit = (skin) => {
    isEditing.value = true;
    editId.value = skin.id;
    form.name = skin.name || '';
    form.description = skin.description || skin.shop_item?.description || '';
    form.price_gold = Number(skin.shop_item?.price_gold || 0);
    form.renderer_type = skin.renderer_type || (skin.project_entry_path ? 'project_static' : 'vue_template');
    form.template_key = skin.template_key || 'default';
    form.hero_gradient = skin.hero_gradient || '';
    form.accent_color = skin.accent_color || '#a855f7';
    form.border_color = skin.border_color || '#7c3aed';
    form.glow_color = skin.glow_color || 'rgba(168,85,247,0.35)';
    form.stat_panel_bg = skin.stat_panel_bg || '#130d1f';
    form.text_primary = skin.text_primary || '#c4b5fd';
    form.is_active = Boolean(skin.is_active);
    form.preview_image = null;
    form.background_image = null;
    form.avatar_frame_image = null;
    form.panel_image = null;
    form.decoration_image = null;
    form._method = 'PUT';
    form.clearErrors();
    showFormModal.value = true;
};

const closeForm = () => {
    showFormModal.value = false;
    isEditing.value = false;
    editId.value = null;
    resetForm();
};

const onPreviewChange = (event) => {
    form.preview_image = event.target.files?.[0] || null;
};

const onAssetChange = (field, event) => {
    form[field] = event.target.files?.[0] || null;
};

const submit = () => {
    const endpoint = isEditing.value
        ? route('admin.profile-skins.update', editId.value)
        : route('admin.profile-skins.store');

    form.post(endpoint, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeForm,
        onError: (errors) => {
            Swal.fire({
                icon: 'error',
                title: isEditing.value ? 'UPDATE_FAILED' : 'CREATE_FAILED',
                text: Object.values(errors || {})[0] || 'Profile skin gagal disimpan.',
                background: '#1a1c2c',
                color: '#ff4d4d',
            });
        },
    });
};

const confirmDelete = (skin) => {
    deleteId.value = skin?.id || null;
    showDeleteModal.value = true;
};

const executeDelete = () => {
    if (!deleteId.value) return;

    router.delete(route('admin.profile-skins.destroy', deleteId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteId.value = null;
        },
    });
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const openBundlePicker = () => {
    updateBundleSkinId.value = null;
    bundleInput.value?.click();
};

const openUpdateBundlePicker = (skin) => {
    updateBundleSkinId.value = skin?.id || null;
    updateBundleInput.value?.click();
};

const readFileAsText = (file) => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ''));
        reader.onerror = reject;
        reader.readAsText(file);
    });
};

const submitBundleFolder = async (event, endpoint, successTitle, successText, requireManifest = true) => {
    const files = Array.from(event.target.files || []);
    if (files.length === 0) return;

    const manifestFile = files.find((file) => String(file.webkitRelativePath || file.name).split(/[\\/]/).pop() === 'skin.json');
    if (!manifestFile) {
        if (requireManifest) {
            importManifest.value = null;
            Swal.fire({
                icon: 'error',
                title: 'SKIN_JSON_NOT_FOUND',
                text: 'Folder bundle wajib berisi skin.json.',
                background: '#1a1c2c',
                color: '#ff4d4d',
            });
            event.target.value = '';
            return;
        }
        importManifest.value = { project: { entry: 'index.html' } };
    } else {
        try {
            importManifest.value = JSON.parse(await readFileAsText(manifestFile));
        } catch (error) {
            importManifest.value = null;
            Swal.fire({
                icon: 'error',
                title: 'INVALID_SKIN_JSON',
                text: 'skin.json tidak bisa dibaca sebagai JSON.',
                background: '#1a1c2c',
                color: '#ff4d4d',
            });
            event.target.value = '';
            return;
        }
    }

    importForm.bundle_files = files;
    importForm.relative_paths = files.map((file) => file.webkitRelativePath || file.name);

    importForm.post(endpoint, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                icon: 'success',
                title: successTitle,
                text: successText,
                background: '#1a1c2c',
                color: '#4ed4d4',
            });
            importForm.reset();
            updateBundleSkinId.value = null;
            event.target.value = '';
        },
        onError: (errors) => {
            Swal.fire({
                icon: 'error',
                title: 'IMPORT_FAILED',
                text: Object.values(errors || {})[0] || 'Import bundle gagal.',
                background: '#1a1c2c',
                color: '#ff4d4d',
            });
        },
    });
};

const onBundleFolderChange = (event) => {
    submitBundleFolder(
        event,
        route('admin.profile-skins.import-bundle'),
        'BUNDLE_IMPORTED',
        'Skin bundle berhasil diimport ke shop.'
    );
};

const onUpdateBundleFolderChange = (event) => {
    if (!updateBundleSkinId.value) {
        event.target.value = '';
        return;
    }

    submitBundleFolder(
        event,
        route('admin.profile-skins.update-bundle', updateBundleSkinId.value),
        'BUNDLE_UPDATED',
        'File tampilan skin lama berhasil diperbarui.',
        false
    );
};
</script>

<template>
    <Head title="PROFILE_SKIN_MANAGEMENT" />

    <div class="min-h-screen bg-[#0d1117] p-4 font-['Press_Start_2P'] text-[10px] text-[#4ed4d4] md:p-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <AdminNavbar />

            <div class="flex flex-col gap-3 border-b-4 border-purple-900 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-base uppercase tracking-widest text-white sm:text-xl">Profile_Skin_Armory</h1>
                    <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-500">Cosmetic shop bundle untuk tampilan profil publik user.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <input
                        ref="bundleInput"
                        type="file"
                        class="hidden"
                        webkitdirectory
                        directory
                        multiple
                        @change="onBundleFolderChange"
                    >
                    <input
                        ref="updateBundleInput"
                        type="file"
                        class="hidden"
                        webkitdirectory
                        directory
                        multiple
                        @change="onUpdateBundleFolderChange"
                    >
                    <button
                        type="button"
                        class="border border-cyan-500 bg-cyan-500/10 px-3 py-2 text-[9px] uppercase text-cyan-300 hover:bg-cyan-400 hover:text-black disabled:opacity-50"
                        :disabled="importForm.processing"
                        @click="openBundlePicker"
                    >
                        {{ importForm.processing ? '[Importing...]' : '[Create_Import_Skin]' }}
                    </button>
                    <Link :href="route('admin.shop-items.index')" class="border border-slate-600 bg-slate-900/40 px-3 py-2 text-[9px] uppercase text-slate-300 hover:text-white">
                        [Shop_Items]
                    </Link>
                </div>
            </div>

            <section class="rpg-panel border-cyan-500/50 bg-cyan-950/10">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div>
                        <p class="text-[7px] uppercase tracking-[0.25em] text-cyan-300">Create_Skin_By_Folder</p>
                        <h2 class="mt-2 text-[11px] uppercase text-white">1 Folder = 1 Profile Skin</h2>
                        <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">
                            Buat skin baru dengan import folder project. Asset frontend boleh berbeda per skin, tetapi data profil publik tetap memakai payload backend aplikasi.
                        </p>
                        <pre class="mt-3 overflow-auto border border-cyan-900 bg-black/50 p-3 font-mono text-[10px] leading-relaxed text-slate-200">{{ bundleStructureExample }}</pre>
                    </div>
                    <div>
                        <p class="text-[7px] uppercase tracking-[0.25em] text-cyan-300">skin.json_Acuan</p>
                        <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">
                            Gunakan `renderer_type`: `config` untuk skin ringan Vue internal, `vue_template` untuk template bawaan, atau `project_static` untuk folder iframe. Project static menerima data backend lewat event `dooptech:profile-skin-data`.
                        </p>
                        <pre class="mt-3 max-h-72 overflow-auto border border-cyan-900 bg-black/50 p-3 font-mono text-[10px] leading-relaxed text-slate-200">{{ manifestExample }}</pre>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-[7px] uppercase tracking-[0.25em] text-cyan-300">Backend_Profile_Data</p>
                    <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">
                        Gunakan struktur JSON ini di `window.addEventListener('message', ...)`. Jangan hardcode nama, level, statistik, karya, atau avatar di asset skin.
                    </p>
                    <pre class="mt-3 max-h-96 overflow-auto border border-cyan-900 bg-black/50 p-3 font-mono text-[10px] leading-relaxed text-slate-200">{{ profilePayloadExample }}</pre>
                </div>
            </section>

            <section v-if="importManifest" class="rpg-panel border-cyan-500/50 bg-cyan-950/10">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-[7px] uppercase tracking-[0.25em] text-cyan-300">Last_Bundle_Manifest</p>
                        <h2 class="mt-2 text-[11px] uppercase text-white">
                            {{ importManifest.skin?.name || importManifest.name || 'Imported Skin' }}
                        </h2>
                        <p class="mt-2 max-w-3xl text-[8px] uppercase leading-relaxed text-slate-400">
                            Template: {{ importManifest.skin?.template_key || importManifest.template_key || 'asset_showcase' }}
                            | Price: {{ importManifest.shop?.price_gold || importManifest.skin?.price_gold || importManifest.price_gold || 500 }} G
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-[7px] uppercase text-slate-400 md:min-w-[320px]">
                        <p class="border border-cyan-900 bg-black/30 p-2">Preview: {{ importManifest.assets?.preview || '-' }}</p>
                        <p class="border border-cyan-900 bg-black/30 p-2">Background: {{ importManifest.assets?.background || '-' }}</p>
                        <p class="border border-cyan-900 bg-black/30 p-2">Avatar: {{ importManifest.assets?.avatar_frame || '-' }}</p>
                        <p class="border border-cyan-900 bg-black/30 p-2">Panel: {{ importManifest.assets?.panel || '-' }}</p>
                        <p class="border border-cyan-900 bg-black/30 p-2">Project: {{ importManifest.project?.entry || '-' }}</p>
                    </div>
                </div>
            </section>

            <section class="rpg-panel border-slate-700">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="skin in skinItems"
                        :key="skin.id"
                        class="overflow-hidden border-2 bg-[#0d1117]"
                        :class="skin.is_active ? 'border-purple-500/60' : 'border-slate-700 opacity-70'"
                    >
                        <div
                            class="relative flex min-h-[150px] items-end border-b border-slate-800 p-4"
                            :style="{ background: skin.hero_gradient }"
                        >
                            <div
                                class="absolute inset-0"
                                :style="{ background: `radial-gradient(circle at top right, ${skin.glow_color}, transparent 35%)` }"
                            />
                            <img
                                v-if="skin.preview_image_path"
                                :src="`/storage/${skin.preview_image_path}`"
                                :alt="skin.name"
                                class="absolute right-3 top-3 h-16 w-16 border-2 border-black/40 object-cover"
                            >
                            <div class="relative z-10">
                                <p class="text-[7px] uppercase tracking-[0.24em]" :style="{ color: skin.text_primary }">Skin_ID_{{ skin.id }}</p>
                                <h2 class="mt-2 break-words text-[12px] uppercase text-white">{{ skin.name }}</h2>
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-[8px] uppercase text-yellow-300">{{ skin.shop_item?.price_gold || 0 }} G</span>
                                <span class="text-[8px] uppercase" :class="skin.is_active ? 'text-emerald-300' : 'text-slate-500'">
                                    {{ skin.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="text-[8px] uppercase text-cyan-300">Users: {{ skin.users_count || 0 }}</span>
                            </div>
                            <p class="text-[8px] uppercase text-purple-300">Renderer: {{ skin.renderer_type || (skin.project_entry_path ? 'project_static' : 'vue_template') }}</p>
                            <p class="text-[8px] uppercase text-purple-300">Template: {{ skin.template_key || 'default' }}</p>
                            <p v-if="skin.project_entry_path" class="break-all text-[7px] uppercase text-cyan-300">
                                Project: /storage/{{ skin.project_entry_path }}
                            </p>
                            <p v-if="skin.bundle_root_path" class="break-all text-[7px] uppercase text-slate-500">
                                Folder: /storage/{{ skin.bundle_root_path }}
                            </p>
                            <p class="line-clamp-3 min-h-[54px] text-[9px] leading-relaxed text-slate-400">{{ skin.description || 'No description.' }}</p>
                            <div class="flex flex-wrap gap-2">
                                <span class="h-5 w-5 border border-slate-600" :style="{ backgroundColor: skin.accent_color }" />
                                <span class="h-5 w-5 border border-slate-600" :style="{ backgroundColor: skin.border_color }" />
                                <span class="h-5 w-5 border border-slate-600" :style="{ backgroundColor: skin.stat_panel_bg }" />
                            </div>
                            <div class="flex justify-end gap-4 border-t border-slate-800 pt-3">
                                <button
                                    type="button"
                                    class="text-[8px] uppercase text-cyan-400 hover:text-white disabled:opacity-40"
                                    :disabled="importForm.processing"
                                    @click="openUpdateBundlePicker(skin)"
                                >
                                    [Update_Bundle]
                                </button>
                                <button type="button" class="text-[8px] uppercase text-green-400 hover:text-white" @click="startEdit(skin)">[Edit]</button>
                                <button type="button" class="text-[8px] uppercase text-red-400 hover:text-white" @click="confirmDelete(skin)">[Delete]</button>
                            </div>
                        </div>
                    </article>
                </div>

                <p v-if="skinItems.length === 0" class="border border-dashed border-slate-700 p-8 text-center text-[8px] uppercase text-slate-500">
                    No_Profile_Skins
                </p>

                <div class="mt-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <p class="text-[8px] uppercase text-slate-500">
                        PAGE {{ skins.current_page || 1 }} / {{ skins.last_page || 1 }} | TOTAL {{ skins.total || 0 }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(link, idx) in paginationLinks"
                            :key="`${idx}-${link.label}`"
                            type="button"
                            class="border px-3 py-1 text-[8px] uppercase"
                            :class="link.active ? 'border-purple-400 bg-purple-500/10 text-purple-300' : 'border-slate-700 text-slate-300 hover:bg-slate-700 hover:text-white'"
                            :disabled="!link.url"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showFormModal" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm">
            <div class="max-h-[92vh] w-full max-w-4xl overflow-y-auto">
                <form class="rpg-panel space-y-5 border-purple-500/60" @submit.prevent="submit">
                    <div class="flex items-start justify-between gap-4 border-b border-purple-900/70 pb-4">
                        <div>
                            <p class="text-[8px] uppercase tracking-[0.25em] text-purple-300">Skin_Bundle_Form</p>
                            <h2 class="mt-2 text-[12px] uppercase text-white">Update_Profile_Skin_Metadata</h2>
                            <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">
                                Skin baru dibuat dari tombol Create_Import_Skin. Form ini hanya untuk edit metadata skin yang sudah terhubung ke folder.
                            </p>
                        </div>
                        <button type="button" class="border border-slate-600 px-3 py-2 text-slate-300 hover:bg-slate-700 hover:text-white" @click="closeForm">X</button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Skin_Name</span>
                            <input v-model="form.name" required type="text" class="field" placeholder="Void Phantom">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Price_Gold</span>
                            <input v-model.number="form.price_gold" required min="1" type="number" class="field text-yellow-300">
                        </label>
                        <label class="space-y-2 md:col-span-2">
                            <span class="block text-[8px] uppercase text-slate-400">Renderer_Type</span>
                            <select v-model="form.renderer_type" required class="field">
                                <option value="vue_template">Vue Template - ringan, internal app</option>
                                <option value="config">Config - paling ringan, JSON driven</option>
                                <option value="project_static">Project Static - iframe folder custom</option>
                            </select>
                        </label>
                        <label class="space-y-2 md:col-span-2">
                            <span class="block text-[8px] uppercase text-slate-400">Profile_UI_Template</span>
                            <select v-model="form.template_key" required class="field">
                                <option value="project_static">Project Static - imported folder layout</option>
                                <option value="asset_showcase">Asset Showcase - full art profile</option>
                                <option value="arcade_cabinet">Arcade Cabinet - game machine UI</option>
                                <option value="void_phantom">Void Phantom - cinematic void UI</option>
                                <option value="default">Default - classic layout</option>
                            </select>
                        </label>
                        <label class="space-y-2 md:col-span-2">
                            <span class="block text-[8px] uppercase text-slate-400">Description</span>
                            <textarea v-model="form.description" class="field min-h-[90px] font-sans text-[12px]" />
                        </label>
                        <label class="space-y-2 md:col-span-2">
                            <span class="block text-[8px] uppercase text-slate-400">Hero_Gradient</span>
                            <input v-model="form.hero_gradient" required type="text" class="field font-sans text-[12px]">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Accent_Color</span>
                            <input v-model="form.accent_color" required type="text" class="field">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Border_Color</span>
                            <input v-model="form.border_color" required type="text" class="field">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Glow_Color</span>
                            <input v-model="form.glow_color" required type="text" class="field">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Stat_Panel_BG</span>
                            <input v-model="form.stat_panel_bg" required type="text" class="field">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Text_Primary</span>
                            <input v-model="form.text_primary" required type="text" class="field">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Preview_Image</span>
                            <input type="file" accept="image/*" class="field text-[8px]" @change="onPreviewChange">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Background_Asset</span>
                            <input type="file" accept="image/*" class="field text-[8px]" @change="onAssetChange('background_image', $event)">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Avatar_Frame_PNG</span>
                            <input type="file" accept="image/png,image/webp" class="field text-[8px]" @change="onAssetChange('avatar_frame_image', $event)">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Panel_Asset</span>
                            <input type="file" accept="image/*" class="field text-[8px]" @change="onAssetChange('panel_image', $event)">
                        </label>
                        <label class="space-y-2">
                            <span class="block text-[8px] uppercase text-slate-400">Decoration_PNG</span>
                            <input type="file" accept="image/png,image/webp" class="field text-[8px]" @change="onAssetChange('decoration_image', $event)">
                        </label>
                    </div>

                    <section class="grid grid-cols-1 gap-4 border-2 border-cyan-900/70 bg-cyan-950/10 p-4 md:grid-cols-2">
                        <div>
                            <p class="text-[8px] uppercase tracking-[0.22em] text-cyan-300">Folder_Project_Format</p>
                            <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">
                                Untuk skin ringan gunakan `renderer_type: config`. Untuk layout bebas, import folder project statis dengan `renderer_type: project_static`. Data profil tetap dari backend.
                            </p>
                            <pre class="mt-3 overflow-auto border border-cyan-900 bg-black/50 p-3 font-mono text-[10px] leading-relaxed text-slate-200">{{ bundleStructureExample }}</pre>
                        </div>
                        <div>
                            <p class="text-[8px] uppercase tracking-[0.22em] text-cyan-300">skin.json_Acuan</p>
                            <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">
                                Project dapat membaca data profil dari `window.message` dengan event type `dooptech:profile-skin-data`.
                            </p>
                            <pre class="mt-3 max-h-72 overflow-auto border border-cyan-900 bg-black/50 p-3 font-mono text-[10px] leading-relaxed text-slate-200">{{ manifestExample }}</pre>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-[8px] uppercase tracking-[0.22em] text-cyan-300">Backend_Profile_Data</p>
                            <p class="mt-2 text-[8px] uppercase leading-relaxed text-slate-400">
                                Payload ini dikirim dari public profile ke iframe. Gunakan field `user`, `stats`, `classAverages`, `creations`, dan `urls` untuk merender UI skin.
                            </p>
                            <pre class="mt-3 max-h-96 overflow-auto border border-cyan-900 bg-black/50 p-3 font-mono text-[10px] leading-relaxed text-slate-200">{{ profilePayloadExample }}</pre>
                        </div>
                    </section>

                    <div
                        class="relative min-h-[130px] overflow-hidden border-2 p-4"
                        :style="{ borderColor: form.border_color, background: form.hero_gradient }"
                    >
                        <div class="absolute inset-0" :style="{ background: `radial-gradient(circle at top right, ${form.glow_color}, transparent 34%)` }" />
                        <div class="relative z-10">
                            <p class="text-[8px] uppercase tracking-[0.24em]" :style="{ color: form.text_primary }">Live_Preview</p>
                            <h3 class="mt-3 text-[12px] uppercase text-white">{{ form.name || 'Profile Skin' }}</h3>
                            <p class="mt-2 text-[7px] uppercase text-slate-300">Renderer: {{ form.template_key }}</p>
                            <div class="mt-4 inline-flex border px-3 py-2 text-[8px] uppercase" :style="{ borderColor: form.accent_color, backgroundColor: form.stat_panel_bg, color: form.text_primary }">
                                Public_Profile_Stat
                            </div>
                        </div>
                    </div>

                    <label class="flex items-center gap-2">
                        <input v-model="form.is_active" type="checkbox" class="accent-purple-400">
                        <span class="text-[8px] uppercase text-purple-300">Skin_Active_In_Shop</span>
                    </label>

                    <button
                        type="submit"
                        class="w-full border-2 border-purple-500 bg-purple-500/10 px-4 py-3 text-[9px] uppercase text-purple-300 hover:bg-purple-400 hover:text-black disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : (isEditing ? 'Update_Skin' : 'Create_Skin') }}
                    </button>
                </form>
            </div>
        </div>

        <div v-if="showDeleteModal" class="fixed inset-0 z-[130] flex items-center justify-center bg-black/90 p-4">
            <div class="rpg-panel w-full max-w-lg border-red-600/70">
                <h2 class="text-[11px] uppercase text-red-400">Delete_Profile_Skin?</h2>
                <p class="mt-4 text-[8px] uppercase leading-relaxed text-slate-400">
                    Skin akan dilepas dari semua user yang sedang memakainya, lalu shop item terkait dinonaktifkan.
                </p>
                <div class="mt-6 flex gap-3">
                    <button type="button" class="flex-1 border border-red-500 px-3 py-2 text-red-300 hover:bg-red-600 hover:text-white" @click="executeDelete">Delete</button>
                    <button type="button" class="flex-1 border border-slate-600 px-3 py-2 text-slate-300 hover:bg-slate-700 hover:text-white" @click="showDeleteModal = false">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rpg-panel {
    background-color: #1a1c2c;
    border-width: 4px;
    padding: 1rem;
    position: relative;
    box-shadow: 8px 8px 0 rgba(0, 0, 0, 0.5);
}

.field {
    width: 100%;
    border-width: 2px;
    border-color: #334155;
    background-color: #05070b;
    padding: 0.65rem;
    color: #c4b5fd;
    outline: none;
}

.field:focus {
    border-color: #a855f7;
}
</style>
