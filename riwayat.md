# Riwayan Pengerjaan Fitur Profile Skin

## 2026-06-03

- Mulai melanjutkan fitur profile skin dari perubahan yang sudah ada di repo.
- Memahami kebutuhan baru: skin profil harus bisa diimport sebagai folder project dari luar, bukan hanya konfigurasi warna/aset.
- Menentukan pendekatan implementasi: folder skin disimpan sebagai project statis dan profil publik merender project tersebut lewat iframe sandbox dengan data profil dikirim via `postMessage`.
- Menambahkan migration dan model field untuk menyimpan entry project skin: `project_entry_path`, `project_root_path`, dan `project_manifest`.
- Mengubah import folder agar membaca `project.entry` dari `skin.json`, menyimpan file project statis, dan menolak entry project yang tidak ditemukan.
- Mengubah public profile agar skin `project_static` memakai layout dari file project yang diimport.
- Menambahkan panduan format folder dan contoh `skin.json` langsung di modal create/edit profile skin.
- Memperbarui folder contoh `public/examples/profile-skin-bundle` dengan `index.html`, `css/style.css`, dan `js/skin.js`.
- Menjalankan migration `2026_06_03_140000_add_project_bundle_fields_to_profile_skins_table`.
- Menambahkan test feature untuk import folder project static dan equip skin yang sudah dimiliki user.
- Verifikasi berhasil: `npm.cmd run build` dan `php artisan test tests\Feature\ProfileSkin\ProfileSkinProjectImportTest.php tests\Feature\Shop\ShopTransactionGoldFlowTest.php tests\Feature\ProfileTest.php`.
- Melanjutkan penyesuaian dokumentasi project skin: memastikan asset frontend boleh berbeda, tetapi data public profile tetap berasal dari backend aplikasi melalui payload `dooptech:profile-skin-data`.
- Menambahkan contoh struktur JSON payload backend public profile di halaman Admin Profile Skins dan modal edit skin.
- Menambahkan contoh struktur payload yang sama ke `public/examples/profile-skin-bundle/README.txt` agar pembuat skin tahu field `user`, `activeSkin`, `stats`, `classAverages`, `creations`, dan `urls`.
- Verifikasi berhasil: `npm.cmd run build`.
- Mulai membuat contoh project skin baru yang siap diimport dengan sistem folder project static saat ini.
- Menambahkan contoh bundle `public/examples/profile-skin-codex-terminal` berisi `skin.json`, `index.html`, `css/style.css`, `js/skin.js`, asset SVG internal, dan README cara import.
- Contoh skin `Codex Terminal` membaca payload backend `dooptech:profile-skin-data` untuk user, statistik, class averages, creations, dan urls.
- Verifikasi berhasil: validasi `skin.json` dengan `ConvertFrom-Json` dan `npm.cmd run build`.
- Mulai memperbaiki kasus data profil tidak terload di skin project static dengan menambahkan mekanisme handshake/retry antara parent public profile dan iframe skin.
- Mengubah public profile agar iframe project skin menerima payload backend lewat retry terjadwal saat iframe load dan saat iframe mengirim event `dooptech:profile-skin-ready`.
- Menambahkan event `dooptech:profile-skin-ready` pada contoh `profile-skin-codex-terminal` dan `profile-skin-bundle`.
- Verifikasi berhasil: `npm.cmd run build`.
- Mulai memperbaiki error frontend `DataCloneError` saat `postMessage` karena payload profile masih berisi object reactive/proxy yang tidak bisa dikirim langsung ke iframe.
- Mengubah payload `postMessage` project skin menjadi plain JSON lewat `JSON.parse(JSON.stringify(...))` sebelum dikirim ke iframe.
- Verifikasi berhasil: `npm.cmd run build`.
- Mulai memperbaiki tampilan project skin yang belum full page dan memunculkan dua scrollbar karena layout parent masih memakai padding/footer halaman public profile biasa.
- Menambahkan opsi `fullBleed` dan `hideFooter` pada `PublicProfileLayout` agar project skin bisa memakai area penuh tanpa padding/footer parent.
- Mengaktifkan mode full-bleed hanya untuk profile skin `project_static`, serta mengubah wrapper iframe menjadi flex-fill dengan parent page `h-screen overflow-hidden`.
- Verifikasi berhasil: `npm.cmd run build`.
- Mulai membuat contoh project skin baru bertema putih yang lebih advance, termasuk desain scrollbar custom dari CSS skin.
- Menambahkan bundle `public/examples/profile-skin-white-orbit` berisi skin `White Orbit` dengan tema putih, font system modern, orbit visual, card glass, metric grid, class progress, creations list, dan scrollbar custom dari CSS skin.
- Skin `White Orbit` tetap memakai payload backend `dooptech:profile-skin-data` serta mengirim event `dooptech:profile-skin-ready`.
- Verifikasi berhasil: cek file bundle dengan `rg --files` dan `npm.cmd run build`.
- Mulai upgrade sistem profile skin menuju hybrid renderer agar output tetap fleksibel tetapi beban server lebih ringan: `config`, `vue_template`, dan `project_static`.


## 2026-06-11

- Investigasi bug: semua background image tidak muncul di seluruh halaman.
- Root cause ditemukan: `[data-app-surface='user'].user-theme-root` di `resources/css/app.css` memiliki `background-color: var(--bg)` yang solid (`#0a0c10`), sehingga menutupi `AppBackgroundLayer` yang di-render via `<Teleport to="body">` dengan `z-index: -10`.
- Fix `app.css`: ubah `background-color: var(--bg)` â†’ `transparent` pada `user-theme-root`, tambahkan `background-color: #0a0c10` ke `body` sebagai fallback.
- Fix `Landing.vue`: hapus inline `style="background-color: #f2d9d9;"` pada wrapper div yang menutupi background.
- Fix `Error.vue`: hapus class `bg-[#0a0c10]` dari wrapper div yang menutupi background.
- Investigasi bug mobile: background terlihat zoom in/zoom out saat scroll di iOS Safari & Android Chrome.
- Root cause: `height: 100dvh` pada `.app-bg-layer` menyebabkan resize karena nilai `dvh` berubah saat address bar mobile muncul/hilang.
- Fix `AppBackgroundLayer.vue`: hapus `height: 100dvh` dan `contain: strict` â€” elemen `fixed inset-0` sudah cukup untuk fill viewport tanpa efek resize.


### Fitur: Download Rekap Average Siswa Study Group

- Menambahkan method `exportRecap` di `AdminStudyGroupController`: mengambil semua member non-staff, menghitung jumlah submission dan rata-rata grade dari quest milik group tersebut, lalu menghasilkan file CSV.
- Menambahkan route `GET /admin/study-groups/{uuid}/export-recap` dengan name `groups.export-recap`.
- Menambahkan tombol `[â†“ Download Rekap CSV]` di halaman `StudyGroups/Admin/Detail.vue` di sebelah tombol Back.
- Kolom CSV: Nama, Username, Level, EXP, Gold, Jumlah Submission, Rata-rata Grade.


### Fitur: Download Rekap Average Siswa Study Group

- Menambahkan method `exportRecap` di `AdminStudyGroupController`: mengambil semua member non-staff, menghitung jumlah submission dan rata-rata grade dari quest milik group tersebut, lalu menghasilkan file CSV.
- Menambahkan route `GET /admin/study-groups/{uuid}/export-recap` dengan name `groups.export-recap`.
- Menambahkan tombol `[â†“ Download Rekap CSV]` di halaman `StudyGroups/Admin/Detail.vue` di sebelah tombol Back.
- Kolom CSV: Nama, Username, Level, EXP, Gold, Jumlah Submission, Rata-rata Grade.



## 2026-06-13

### Fix: Auto-correct Task Bank Platforming & Word Match

- Investigasi bug: submission quest dengan task bank tipe `platforming` dan `word_match` masuk ke status `Pending` alih-alih langsung di-auto correct seperti `multiple_choice`.
- Root cause: `applyTaskBankSubmissionPayload()` di `SubmissionController` selalu set `status = STATUS_PENDING` untuk semua tipe dan tidak pernah memanggil `evaluateTaskBankAnswers()` meski method tersebut sudah ada.
- Fix `SubmissionController::applyTaskBankSubmissionPayload`: tambahkan kondisi pengecekan `isAutoCheckedTaskBankQuest()` â€” jika tipe adalah `multiple_choice`, `platforming`, atau `word_match`, maka normalize jawaban, validasi, evaluasi, dan set `status = Approved` + `pipeline_status = AI_CHECKED` + grade/feedback/reward langsung.
- Quest dengan essay atau tipe campuran tetap masuk pipeline `Pending` seperti sebelumnya.



## 2026-06-15

### Fix: Auto Zoom In/Out saat Input Focus di Mobile Chrome

- Bug: background halaman zoom in saat input field di-focus dan zoom out saat focus hilang di Chrome mobile.
- Root cause: browser mobile otomatis zoom in jika `font-size` input kurang dari 16px.
- Fix `resources/views/app.blade.php`: tambahkan `maximum-scale=1` pada meta viewport.
  ```
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  ```
- Catatan: `maximum-scale=1` mencegah auto-zoom saat focus input, namun juga menonaktifkan pinch zoom manual user. Alternatif yang lebih ramah: set `font-size: 16px` pada semua `input`, `textarea`, `select` via CSS global.



## 2026-06-15

### Fix: Mobile Chrome â€” Background Zoom In/Out & Space Hitam di Landing Page

**Bug 1: Auto zoom saat input field di-focus (Chrome mobile)**
- Root cause: browser mobile auto-zoom jika `font-size` input < 16px.
- Fix `resources/views/app.blade.php`: tambah `maximum-scale=1` di meta viewport.

**Bug 2: Background zoom in/out saat address bar Chrome muncul/hilang**
- Root cause: `AppBackgroundLayer` menggunakan `position: fixed` yang dipengaruhi visual viewport resize saat address bar muncul/hilang.
- Fix `resources/css/app.css`: tambah override `.app-bg-layer { height: 100svh }` pada mobile (`max-width: 768px`). `100svh` = small viewport height, nilai konstan tidak berubah saat address bar toggle.

**Bug 3: Space hitam di bawah Landing Page**
- Root cause: `AppBackgroundLayer` scoped CSS memiliki `background: var(--bg, #0a0c10)` yang membuat layer itu sendiri berwarna hitam. Di halaman gelap tidak masalah, tapi di Landing yang overlay-nya terang, warna hitam ini terlihat di area bawah.
- Fix `AppBackgroundLayer.vue`: hapus `background: var(--bg, #0a0c10)` dari scoped CSS agar layer transparan.
- Fix `app.css`: tambah `body:has(.landing-page-root) { background-color: #eff7ff }` dan `.landing-page-root { background-color: #eff7ff }` agar fallback body match dengan overlay terang Landing.
- Fix `Landing.vue`: tambah class `landing-page-root` di wrapper utama.



## 2026-06-20

- Mulai melakukan analisis fitur DoopLab bagian Roadmap (canvas peta belajar visual bermentor) berdasarkan kode yang ada di repo.
- Mengidentifikasi subsistem DoopLab: **Roadmap** (canvas visual), **Todo** (task management), **Dashboard** (hub eksperimen). Entry ini khusus untuk Roadmap.

### Skema Data (8 Tabel)
- `dooplab_roadmaps` - master roadmap, flag `is_published`, `created_by_user_id`.
- `dooplab_roadmap_sections` - wadah/grouping node di canvas (opsional).
- `dooplab_roadmap_nodes` - unit aktivitas individual, bisa terkait Guide/Quest via `resource_type`+`resource_id`.
- `dooplab_roadmap_text_blocks` - catatan/deskripsi free-form di canvas.
- `dooplab_roadmap_edges` - garis berarah `from_node_id` -> `to_node_id` + `curvature` & `stroke_color`.
- `dooplab_roadmap_node_resources` - pivot multi-resource per node (1 node bisa punya banyak Guide/Quest), skema lebih bersih dari kolom `resource_type`/`resource_id` langsung di node.
- `dooplab_roadmap_enrollments` - relasi student -> roadmap -> mentor + `status` (unique `roadmap_id`+`user_id`).
- `dooplab_roadmap_node_progress` - progress per node per student: status + `student_note`/`mentor_note` + timestamp submit/review.

### Akses & Gate Premium
- `User::canAccessDoopLab()` - staff (admin/mentor) selalu boleh; student **wajib punya item `dooplab_key`** di inventory.
- Item `dooplab_key` di-seed via `2026_05_15_160000_seed_dooplab_key_shop_item.php` seharga **500 gold**, non-stackable, `is_active=true`.
- Implikasi: DoopLab = fitur premium, gold/inventory adalah sumber aksesnya.

### State Machine Node Progress
- Status: `locked` -> `unlocked` -> `submitted` -> `approved` (auto-unlock children) atau `revision` -> `unlocked` (re-submit).
- `recomputeUnlocks()`: node root (0 parent) otomatis `unlocked` saat enrollment dibuat; node dengan parent hanya `unlocked` jika **semua parent berstatus `approved`**.
- Override manual tersedia via endpoint `unlock`/`lock` untuk edge case mentor.

### Alur End-to-End
1. Mentor/admin membuat roadmap (`POST /dooplab/roadmaps`).
2. Edit canvas di `Index.vue` dengan `?workspace=1` - drag-drop section/node/edge, simpan parsial PATCH/POST.
3. Mentor/admin assign siswa (`POST /dooplab/enrollments`).
4. Siswa buka enrollment -> controller panggil `ensureProgressRows()` + `recomputeUnlocks()`.
5. Siswa submit node -> `unlocked` -> `submitted` + set `submitted_at`.
6. Mentor review -> `approved`/`revision` + `mentor_note`.
7. Auto-unlock: `approved` membuka semua child node via incoming edge.
8. Override manual: `unlock`/`lock` paksa oleh mentor.

### Catatan Teknis
- **Canvas-style API**: lebar/tinggi canvas dihitung otomatis di frontend (`boardWidth`/`boardHeight` computed) dari max posisi elemen - mendukung infinite canvas tanpa virtualisasi.
- **SVG-based edges**: pakai path SVG `C` (Bezier curve) di `edgePaths` computed berdasarkan `curvature`, bukan library graf (vue-flow). Solusi ringan & custom.
- **Draft-saving pattern**: `dirtySectionUuids`/`dirtyNodeUuids`/`dirtyTextBlockUuids` Set untuk auto-save parsial.
- **UUID route binding**: semua model pakai `getRouteKeyName() = 'uuid'` - URL aman, tidak bocorkan ID internal.
- **Cascade delete**: hapus roadmap otomatis menghapus section/node/edge/enrollment/progress - integritas data aman.
- **Resource resolution**: `resolveResourceMetaList()` di enrollment controller mendeteksi submission terakhir siswa untuk quest terkait, sehingga mentor bisa loncat ke `admin.submissions.inspect` saat review (alur mentoring -> grading tersambung).
- **Estetika UI**: latar pixel-art `Gerbang_lab_pixel_art_website (3).jpeg` + font `Press Start 2P` (tema game/retro, "lab").

### Posisi Roadmap di Arsitektur
- DoopLab adalah modul premium (gate via 500 gold), dengan struktur: **Dashboard** (ringkasan eksperimen, kolab, todos, learning paths) -> **Roadmap Lab** (editor + viewer canvas) -> **Todos** (task management).
- Roadmap menghubungkan **Quest** (sumber tugas) -> **Submission** (tugas dikumpulkan) -> **Guide** (materi) lewat satu peta, dengan **workflow approval mentor** yang terstruktur dan **alur belajar jelas** untuk siswa.

### Potensi Peningkatan (jika ingin dipromosikan jadi fitur produk)
- **Belum ada notifikasi** ke siswa/mentor saat transisi status (`unlocked`/`approved`/`revision`) - saat ini silent.
- **Belum ada progress percentage** di level roadmap (mis. "3/12 node selesai") - `MyPaths.vue` hanya menampilkan status enrollment.
- **Editor canvas belum kolaboratif** - setiap save replace, tidak ada versioning/undo.
- **Belum ada komentar/diskusi per node** (cuma `student_note` & `mentor_note` sebagai teks, bukan thread).
- **Edge tidak bisa diberi label/kondisi** (mis. "jika skor > 80") - selalu linear approval.
- **Belum ada template roadmap** - tiap mentor bikin dari nol.

### Bug Fix — 2026-06-25 (Canvas Roadmap `Index.vue`)

**1. Posisi node/section reset saat tambah item baru**
- Root cause: `watch(activeRoadmap)` hanya melindungi item yang ada di `dirtySectionUuids`/`dirtyNodeUuids`. Saat `submitNode` sukses, Inertia refresh props → watcher jalan → dirty set sudah dikosongkan oleh `clearLayoutDirty()` → posisi draft di-overwrite data lama server.
- Fix: snapshot seluruh posisi draft (`snapshotDraftPositions()`) sebelum merge incoming. Posisi dipertahankan jika item: (a) masih dirty, (b) sedang di-drag (`dragState`), atau (c) `layoutSaving` aktif.

**2. Scroll halaman terblokir saat kursor di area canvas**
- Root cause 1: `touch-action: none` di CSS statis `.section-box`, `.node-box`, `.text-block-box` memblokir semua gesture touch termasuk scroll.
- Root cause 2: `overflow: auto` di `.canvas-wrapper` meng-intercept mouse wheel vertikal.
- Fix: hapus `touch-action: none` dari CSS, ganti ke inline style dinamis — `none` hanya saat elemen sedang di-drag, `pan-x pan-y` saat idle. Ubah `.canvas-wrapper` ke `overflow-x: auto; overflow-y: visible` agar scroll vertikal naik ke halaman secara natural.

**3. `preventDefault` di `pointerdown` memblokir scroll sebelum drag dimulai**
- Fix: pindahkan `preventDefault()` + `setPointerCapture()` ke `onDragMove`, aktif hanya setelah pointer bergerak > 4px (threshold drag). Flag `started: false` di `dragState` melacak status ini.

**4. Node dengan `section_id` bisa di-drag keluar dari section**
- Root cause: `maxX`/`maxY` di `startDrag` dihitung dari ukuran board, bukan bounds section parent.
- Fix: jika `type === 'node'` dan `section_id` ada, cari section parent di `draftSections`, hitung `minX/minY` dari posisi section dan `maxX/maxY` dari posisi + ukuran section dikurangi ukuran node. `clampValue` di `onDragMove` pakai `minX/minY` sehingga node terkunci dalam section-nya.

## 2026-06-20 (Todo List)

- Mulai melakukan analisis fitur Todo List DoopLab berdasarkan kode yang ada di repo.
- Entry ini khusus untuk subsistem Todo: task management hybrid (self + mentor-assigned) dengan workflow review dan chat notes.

### Posisi di Arsitektur DoopLab
- Todo **tidak punya halaman dedicated** (`DoopLab/Todos/Index.vue` belum ada) - semua interaksi berlangsung inline di `resources/js/Pages/DoopLab/Dashboard.vue` lewat multi-panel: `summary`, `todo_list`, `todo_form`, `todo_detail` (panel mode di-switch via `panelMode` ref).
- Backend: `DoopLabDashboardController::index` me-render `DoopLab/Dashboard` sekaligus menyertakan props `todos`, `todo_permissions`, `todo_assignable_users`, `research_workspaces`, `hireable_creations`, `mentor_invites`, `learning_paths` - **satu endpoint untuk banyak domain**.
- 4 form utama di frontend: `todoForm` (create/edit), `todoNoteForm` (chat), `hireMentorForm`, `respondMentorInvite`. State `selectedTodoUuid` & `showTodoModal` jadi pivot navigasi.

### Skema Data (2 Tabel)

**`dooplab_todos`**
- `uuid` (UUID route binding), `title` (max 160), `description` (max 1000).
- `start_at`, `deadline` (datetime), `notify_deadline_email` (bool), `deadline_reminded_at` (datetime).
- `assignment_mode`: `self` | `mentor`.
- `milestone_type`: `task` | `milestone` | `checkpoint` | `logbook`.
- `workflow_status`: `todo` | `ongoing` | `blocked` | `pending_review` | `approved` | `rejected` | `done`.
- `owner_user_id` (FK users), `mentor_user_id` (FK users, nullable), `creation_id` (FK creations, nullable).
- `is_completed` (bool, **auto-computed** di observer `saving`), `completed_at`, `completed_by_user_id`.
- `review_requested_at`, `reviewed_at`, `reviewed_by_user_id`, `review_note` (max 1200).
- Index komposit: `(owner_user_id, is_completed)`, `(mentor_user_id, is_completed)`, `(assignment_mode, created_at)`, `(creation_id, workflow_status)`, `(milestone_type, workflow_status)`.

**`dooplab_todo_notes`**
- `todo_id` (FK), `author_user_id` (FK), `note` (text, max 2000), `image_path` (nullable).
- Index: `(todo_id, created_at)`, `(author_user_id, created_at)`.

### Model Logic & Invariants
- **Auto-UUID** saat `creating`, default `workflow_status = 'todo'` & `milestone_type = 'task'`.
- **Single source of truth** untuk completion: observer `saving` sync `is_completed = in_array(workflow, [DONE, APPROVED])`. Auto-set `completed_at` saat completed, auto-clear `completed_at` + `completed_by_user_id` saat `isDirty('workflow_status')` ke non-completed.
- **Casts**: `start_at`, `deadline`, `deadline_reminded_at`, `completed_at`, `review_requested_at`, `reviewed_at` -> `datetime`. `notify_deadline_email`, `is_completed` -> `boolean`.

### Permission Methods
- `canToggleBy` - owner untuk self-mode; mentor yang ditugaskan untuk mentor-mode.
- `canEditBy` / `canDeleteBy` - owner selalu; atau mentor yang ditugaskan.
- `canCommentBy` - owner selalu; atau mentor yang ditugaskan.
- `canSubmitCheckpointBy` - **hanya** owner + mentor-mode (siswa submit ke mentor).
- `canReviewCheckpointBy` - mentor + `mentor_user_id == user.id` + mentor-mode.

### State Machine Workflow
- `todo` -> `ongoing` -> `pending_review` (oleh owner via `submitForReview`) -> `approved`/`rejected` (oleh mentor via `reviewCheckpoint`).
- `toggle` = binary switch: `done/approved` -> `todo`, selain itu -> `done`. Hanya update completion fields, **preserve review fields** (review_note, reviewed_at, dll tidak di-reset).

### Alur End-to-End
1. Member/student buka `DoopLab Dashboard` -> controller filter todo by relevance (owner atau mentor yang ditugaskan).
2. Member bikin todo self (`assignment_mode=self`) lewat modal inline `showTodoModal` -> `POST /dooplab/todos`.
3. Mentor bikin todo untuk member (`assignment_mode=mentor`) -> `POST /dooplab/todos` dengan `owner_user_id` target (validasi: target punya akses DoopLab, creation target punya `user_id == owner` & mentor di collaborator).
4. Member centang `done` (kalau self) atau klik `Submit checkpoint` (kalau mentor-mode) -> `PATCH /dooplab/todos/{uuid}/submit-review` -> status `pending_review`.
5. Mentor review: `PATCH /dooplab/todos/{uuid}/review` dengan `decision: approve|reject` + `review_note` -> status `approved` atau `rejected`.
6. Notes/chat: `POST /dooplab/todos/{uuid}/notes` (multipart, bisa teks + image) - role-gated via `canCommentBy`.

### Endpoint Inventory
| Method | URL | Name |
|---|---|---|
| POST | `/dooplab/todos` | `dooplab.todos.store` |
| PATCH | `/dooplab/todos/{todo}` | `dooplab.todos.update` |
| PATCH | `/dooplab/todos/{todo}/toggle` | `dooplab.todos.toggle` |
| PATCH | `/dooplab/todos/{todo}/submit-review` | `dooplab.todos.submit-review` |
| PATCH | `/dooplab/todos/{todo}/review` | `dooplab.todos.review` |
| DELETE | `/dooplab/todos/{todo}` | `dooplab.todos.destroy` |
| POST | `/dooplab/todos/{todo}/notes` | `dooplab.todos.notes.store` |

### Catatan Teknis
- **Single-page multi-panel**: `panelMode` switch antara `summary` (counter cards), `todo_form` (create/edit inline), `todo_detail` (selected todo + notes). State `selectedTodoUuid` jadi pivot detail. Tidak ada URL state - share link tidak bisa point ke todo tertentu.
- **Form state management**: `useForm` (Inertia) untuk `todoForm` & `todoNoteForm` dengan `transform()` untuk normalize payload (date ISO, null-safe). `forceFormData: true` untuk note karena image upload.
- **Persistence pattern**: `preserveScroll: true` + `preserveState: true` di semua mutation - UX tetap di tempat, tapi Inertia full reload data, tidak optimistic update.
- **localStorage state**: `persistDashboardState()` simpan `panelMode`, `todoFilter`, `todoSearch`, `selectedTodoUuid`, `scrollY` ke localStorage - restore onMounted dengan scroll restoration. Bagus untuk UX tapi **tidak sync antar device**.
- **Counter badges**: `todoCounters` computed (total/pending/completed/self/mentor) di-render sebagai card di summary view.
- **Filter & search**: `filteredTodoItems` filter by `todoFilter` (all/self/mentor) + `todoSearch` substring search di title/description/owner.name/owner.username/mentor.name/mentor.username (case-insensitive). Real-time.
- **Image preview**: `URL.createObjectURL` di `onTodoNoteImageChange`, di-revoke di `removeTodoNoteImage` & `clearTodoNoteForm` (handle blob: prefix check) - good memory hygiene.
- **scrollIntoView pattern**: saat `selectedTodoUuid` berubah, `todoChatPanelRef.value?.scrollIntoView({ behavior: 'smooth' })` - smooth focus ke detail panel.
- **Watch cleanup**: `watch(allTodos, ...)` reset `selectedTodoUuid` kalau todo yang dipilih sudah hilang (mis. dihapus orang lain). Konsisten.

### Aksesibilitas & Mobile
- Icon button `todo-icon-btn` punya `title` + `aria-label` (cukup accessible).
- Native `<input type="datetime-local">` untuk date picker - native browser UI, kerja di mobile.
- CSS theme retro/lab sama dengan Roadmap Index.vue (Press Start 2P + dark panel).

### Potensi Peningkatan (jika ingin dipromosikan jadi fitur produk)
- **`window.prompt()` untuk review note** - UX buruk & tidak accessible. Idealnya modal/form proper yang reuse pattern `showTodoModal`.
- **Tanpa dedicated page** - semua inline di Dashboard, sulit di-deep-link atau di-share URL. `DoopLab/Todos/Index.vue` akan memisahkan concern.
- **Tanpa pagination** - server limit 80, client tidak ada indikasi "X of Y loaded" atau load-more. Untuk power user dengan ratusan todo, ini bottleneck.
- **Tanpa sorting** - hard-coded `latest('created_at')` di controller. User tidak bisa sort by deadline asc/desc, milestone, atau workflow status.
- **Recurring/deadline auto-roll** tidak ada - deadline reminder cuma notif 1x (via cron `SendDoopLabTodoDeadlineReminderNotifications`), tidak auto-create next todo.
- **Tanpa bulk actions** - harus toggle satu-satu. Untuk mentor yang handle 30+ student todos, akan lambat.
- **Notes tanpa edit/hapus** - author tidak bisa koreksi typo atau hapus lampiran salah. Thread jadi satu arah.
- **Tidak ada notifikasi real-time** ke author note / owner todo saat ada update - silent kecuali di-refresh.
- **`assignment_mode` & `owner_user_id` lock di edit** - tidak bisa reassign todo mentor ke orang lain, harus delete + create ulang.
- **Submission-to-todo link tidak ada** - `EnrollmentShow` punya resolve submission untuk quest, tapi todo tidak auto-create dari submission sebagai "task lanjutan".
- **Deadline cron** - `SendDoopLabTodoDeadlineReminderNotifications` ada & ada test, tapi scheduler registration belum terlihat (perlu cek `routes/console.php` atau `app/Console/Kernel.php`).


## 2026-06-27

### Fix: Review Checkpoint Todo — Ganti window.prompt() dengan Inline Form

- Root cause: `reviewTodoCheckpoint()` di `Dashboard.vue` menggunakan `window.prompt()` — UX buruk, tidak accessible, dan klik Cancel tetap submit request (bug).
- Fix: tambah state `reviewForm { show, decision, note }`, fungsi `openReviewForm(decision)` dan `submitReviewCheckpoint()`.
- Tombol approve/reject sekarang trigger `openReviewForm` → muncul inline form textarea + tombol Batal/Approve/Reject di atas area notes.
- Tambah CSS: `.review-inline-form`, `nb-btn--success`, `nb-btn--danger`.
- Verifikasi berhasil: `npm.cmd run build`.

### Fix: Card Todo List Sensitif saat Copy Teks

- Root cause: `@click="openTodoDetail(item)"` di `todo-nav-item` terpicu saat user drag-select teks judul.
- Pendekatan pertama (`getSelection().toString()`) gagal karena selection masih tersisa dari klik sebelumnya.
- Fix final: track `mousedown` posisi awal di `_dragStartX/_dragStartY` pada elemen, cek di `@click` apakah perpindahan pointer < 5px. Jika ≥ 5px (drag/select), `openTodoDetail` tidak dipanggil.
- Verifikasi berhasil: `npm.cmd run build`.

### Fitur: Logbook DoopLab

- Menambahkan subsistem Logbook sebagai catatan riwayat kegiatan student dan mentor, terintegrasi dengan Todo List.
- Entry logbook bisa dibuat mandiri oleh student atau dikaitkan dengan todo mentor.

#### Skema Data (1 Tabel)

**`dooplab_logbooks`**
- `uuid` (UUID route binding), `owner_user_id` (FK users), `mentor_user_id` (FK users, nullable), `todo_id` (FK dooplab_todos, nullable).
- `activity_date` (date), `activity_time` (time, nullable).
- `activity` (string max 500) — deskripsi kegiatan.
- `purpose` (text, nullable) — tujuan kegiatan.
- `result` (text, nullable) — hasil kegiatan.
- `mentor_signature` (string max 255, nullable) — nama/paraf teks mentor.
- `documentation_path` (string, nullable) — path file upload (jpg/png/webp/pdf, maks 5MB).
- Index: `(owner_user_id, activity_date)`, `(mentor_user_id, activity_date)`.

#### Backend
- Model `DoopLabLogbook` — auto-UUID, `canEditBy`/`canDeleteBy` (owner atau mentor yang ditugaskan).
- `DoopLabLogbookController` — `store`, `update`, `destroy`. Saat store, jika `todo_id` dikaitkan dan todo punya `mentor_user_id`, field `mentor_user_id` logbook otomatis terisi dari todo.
- 3 route baru di `web.php` (auth middleware): `POST /dooplab/logbooks`, `PATCH /dooplab/logbooks/{logbook}`, `DELETE /dooplab/logbooks/{logbook}`.
- `DoopLabDashboardController::index` — tambah prop `logbooks` (max 100, latest by activity_date, eager load owner/mentor/todo, computed `can_edit`/`can_delete`/`documentation_url`).

#### Frontend (Dashboard.vue)
- Tombol **Logbook (N)** di sidebar kiri, di bawah To-Do List.
- Panel list logbook: tampil tanggal, waktu, kegiatan, tujuan, hasil, paraf mentor, todo terkait, link dokumentasi, tombol edit/hapus.
- Modal form create/edit — semua field + dropdown todo terkait (create only) + upload dokumentasi dengan preview.
- State: `allLogbooks`, `showLogbookModal`, `logbookModalMode`, `editingLogbookUuid`, `logbookForm` (useForm), `logbookDocPreview`.
- `panelMode` enum diperluas dengan `'logbook'`.
- CSS baru: `.logbook-card`, `.logbook-card-head`, `.logbook-date`, `.logbook-activity`, `.logbook-card-actions`, `.logbook-card-body`, `.logbook-field`, `.logbook-doc-link`.
- Verifikasi berhasil: `php artisan migrate` (tabel baru) + `npm.cmd run build`.

#### Catatan Teknis
- `mentor_user_id` di logbook otomatis diisi dari todo terkait saat create — tidak perlu mentor pilih sendiri.
- File dokumentasi disimpan di `storage/app/public/dooplab/logbooks/`.
- `forceFormData: true` dipakai di Inertia form karena ada file upload.
- Tidak ada notifikasi real-time saat logbook dibuat/diedit — silent seperti todo notes.


## 2026-07-07

### Profile Skin: Job Status Card

- Menambahkan blok **Job Status / Current Path** ke skin static `White Orbit` dan `Scrapbook Memory`.
- Blok ini memakai data backend yang sudah tersedia di payload public profile: `user.job_name` dan `user.job_emblem_path`.
- Perubahan diterapkan ke folder contoh import dan juga copy aktif di `public/storage/profile-skins/...`, sehingga skin yang sudah terpasang ikut menampilkan emblem/path tanpa perlu import ulang.
- Styling dibuat responsif: kartu job berubah satu kolom di mobile agar emblem dan nama path tidak saling menekan.

### DoopLab: Navigation, Mentor Invites, Hire Mentor, dan Logbook

#### Navigation DoopLab
- Mobile bottom navigation dibuat tetap terlihat saat halaman di-scroll (`position: fixed`) dan layout mobile diberi padding bawah agar konten tidak ketutup nav.
- Halaman admin **Roadmap Lab** disamakan dengan gaya navigasi DoopLab yang lain, tanpa mengubah fitur roadmap.
- Active state navigation diperbaiki untuk mobile dan desktop:
  - **Mentor Invites** aktif saat `panelMode === 'mentor_invites'`.
  - **My Learning Path** aktif saat `panelMode === 'learning_paths'`.
  - **To-Do List** aktif saat `panelMode` berada di `summary`, `todo`, atau `todo_form`.
  - **Hire Mentor** aktif saat `panelMode === 'hire_mentor'`.
  - **Logbook** aktif saat `panelMode === 'logbook'`.
- Bug active style desktop diperbaiki karena sebelumnya tertimpa style global pixel button.

#### Mentor Invites
- Menu **Mentor Invites** diubah dari tampilan riwayat menjadi list user yang hire mentor.
- Card dibuat lebih sederhana: avatar, nama user, username, tipe invite, status, dan tombol aksi.
- Pending invite hanya menampilkan tombol **Accept** dan **Reject**.
- Approved invite menampilkan tombol **Cancel**.
- Tombol **Reject** diberi warna merah agar makna aksinya jelas.
- Pending invite tidak bisa di-cancel, sesuai flow yang diinginkan.
- Backend menambahkan route cancel invite:
  - `POST /creation-mentor-invites/{collaborationRequest}/cancel`
  - route name: `creations.mentor-invites.cancel`
- Cancel hanya boleh dilakukan mentor dan hanya untuk invite berstatus approved.

#### Hire Mentor User
- Menu user **Hire Mentor** dibuat menampilkan mentor aktif/pending saja, bukan riwayat invite.
- Pilihan **Pilih Mentor** disembunyikan jika user sudah punya mentor direct pending atau approved.
- Backend `hireDirectMentor()` membatasi user hanya bisa punya satu direct mentor yang pending/approved pada fitur saat ini.
- Data rejected/riwayat lama tidak lagi ditampilkan pada panel user.

#### Logbook DoopLab
- Edit entry logbook diperbaiki karena payload `PATCH` + multipart/form-data tidak terbaca konsisten oleh Laravel/PHP.
- Ditambahkan route POST khusus update entry:
  - `POST /dooplab/logbooks/{logbook}/entries/{entry}`
  - route name: `dooplab.logbooks.entries.update-post`
- Frontend edit entry sekarang memakai route update POST tersebut.
- Pesan error toast dibuat menampilkan error validasi pertama, bukan hanya alert umum "input gagal".
- Validasi waktu diperbaiki dengan normalisasi `activity_time`:
  - menerima format seperti `07:18 PM`, `19:18:00`, dan `19:18`;
  - disimpan/validasi sebagai format `H:i`.
- Foto dokumentasi yang sudah di-upload sekarang muncul saat entry dibuka di mode edit.
- Foto dokumentasi existing bisa dihapus dari edit form:
  - frontend menyimpan `keep_documentation_paths`;
  - backend hanya mempertahankan path yang masih dipilih;
  - upload foto baru tetap mengganti dokumentasi lama.
- Section logbook aktif dipertahankan setelah refresh, edit berhasil, create berhasil, delete, dan navigasi balik:
  - selected logbook disimpan di localStorage key `dooplab.logbook.selected-uuid`;
  - tombol Back membersihkan selected logbook dan kembali ke list utama logbook.

#### Fix: Roadmap Canvas Berantakan Setelah Refresh
- Investigasi bug: setelah edit/save node atau section di canvas Roadmap, posisi terlihat aman di UI, tetapi setelah refresh node/section bisa berantakan.
- Root cause utama: `saveLayoutChanges()` mengirim banyak `router.patch()` paralel lewat `Promise.allSettled()`. Inertia request paralel rawan saling cancel/overwrite response, sementara dirty state sudah dibersihkan sebelum server benar-benar sukses menyimpan semua item.
- Root cause tambahan: form edit node/section ikut membawa `x`, `y`, `width`, dan `height`; saat form memakai data stale, edit judul/section bisa menimpa posisi layout terbaru.
- Fix `resources/js/Pages/DoopLab/Roadmaps/Index.vue`:
  - save layout dibuat sequential (`await` satu item selesai sebelum lanjut item berikutnya);
  - dirty state item hanya dihapus setelah request item tersebut sukses;
  - jika sebagian save gagal/cancel, dirty state tetap ada dan toolbar menampilkan pesan error agar user tidak refresh sebelum klik Save lagi;
  - jika user mengubah item saat save masih berjalan, dirty state tidak dihapus bila signature layout saat ini sudah berbeda dari payload yang baru tersimpan;
  - sebelum submit edit node/section, form disinkronkan ulang dari draft canvas terbaru agar tidak mengirim koordinat stale.

#### Rencana/Fix: Zoom In-Out Roadmap Canvas
- Kebutuhan baru: canvas Roadmap perlu zoom in/out agar editor nyaman untuk roadmap besar dan tetap responsif di desktop/mobile.
- Keputusan desain:
  - zoom hanya memengaruhi tampilan visual, bukan data `x`, `y`, `width`, dan `height` yang disimpan ke database;
  - board memakai CSS `transform: scale(...)`, sedangkan wrapper stage menghitung ukuran visual `boardWidth * zoomScale` dan `boardHeight * zoomScale`;
  - drag dan resize wajib membagi delta pointer dengan `zoomScale` agar posisi tersimpan tetap akurat;
  - ukuran minimum canvas diperbesar supaya area kerja tidak terasa sempit saat mulai membuat roadmap;
  - kontrol zoom dibuat responsif: tombol zoom out, reset/persentase, zoom in, dan fit width.
- Implementasi selesai di `resources/js/Pages/DoopLab/Roadmaps/Index.vue`:
  - menambahkan `zoomScale`, `zoomPercent`, `visualBoardWidth`, `visualBoardHeight`, `zoomInCanvas`, `zoomOutCanvas`, `resetCanvasZoom`, dan `fitCanvasToWidth`;
  - canvas minimum diperlebar menjadi basis 1600x1000 plus padding otomatis;
  - `.canvas-stage` menjadi wrapper ukuran visual hasil zoom dan tetap center di halaman;
  - `.roadmap-board` memakai `transform-origin: top left` dan `transform: scale(...)`;
  - `onDragMove()` memakai screen delta untuk threshold, lalu membagi delta canvas dengan zoom aktif supaya drag/resize tetap presisi;
  - kontrol zoom dibuat wrap-friendly di mobile agar toolbar tidak overflow.
- Follow-up fix karena zoom belum terasa bekerja konsisten:
  - binding style stage/board dipindah ke computed `canvasStageStyle` dan `roadmapBoardStyle` agar `scale(zoomScale.value)` eksplisit reaktif;
  - event wheel canvas diubah dari passive menjadi non-passive agar `Ctrl + wheel` bisa zoom tanpa browser/page ikut menangani scroll;
  - menambahkan `zoomCanvasFromWheel()` untuk shortcut zoom via `Ctrl + scroll`;
  - menambahkan `will-change: transform` pada board.
- Follow-up zoom Learning Path user:
  - zoom canvas ditambahkan juga ke `resources/js/Pages/DoopLab/Roadmaps/EnrollmentShow.vue`, bukan hanya editor admin/mentor;
  - viewer user memakai pola yang sama: `zoomScale`, `canvasStageStyle`, `roadmapBoardStyle`, tombol `-`, persentase/reset, `+`, dan `Fit`;
  - `Ctrl + wheel` dibuat lebih halus dengan akumulasi delta (`WHEEL_ZOOM_THRESHOLD`) dan step kecil 5%, sehingga touchpad tidak terlalu agresif;
  - kontrol zoom dibuat nyaman di mobile dengan tombol full-width/wrap, sehingga tetap bisa dipakai tanpa mouse/keyboard.
- Follow-up fix touchpad/pinch:
  - mekanisme `Ctrl + wheel` tidak lagi memakai threshold besar karena beberapa touchpad presisi mengirim delta kecil sehingga terasa tidak bekerja;
  - zoom wheel sekarang memakai delta langsung yang dinormalisasi dan dibatasi, sehingga pinch/trackpad lebih responsif tetapi tetap halus;
  - fallback `Meta/Alt + scroll` ikut didukung untuk browser/perangkat yang tidak mengirim `ctrlKey` saat gesture;
  - event `gesturestart/gesturechange` dan two-touch pinch ditambahkan di editor Roadmap dan viewer Learning Path user;
  - wrapper canvas memakai `touch-action: pan-x pan-y` agar scroll/pan tetap bisa berjalan, sementara pinch zoom ditangani oleh canvas.
- Follow-up responsive mobile editor Roadmap:
  - drag node dan section diperbaiki untuk layar sentuh; pointer touch/pen sekarang langsung di-capture saat drag dimulai agar browser mobile tidak membatalkan gerakan;
  - area judul node dan section sekarang ikut bisa menjadi area drag, karena sebelumnya `pointerdown.stop` pada title membuat sentuhan mobile di area utama item tidak memulai drag;
  - node dan section memakai `touch-action: none` saat disentuh agar drag/drop lebih stabil di mobile;
  - kontrol item dan resize handle muncul saat item selected, bukan hanya hover, sehingga tetap bisa dipakai di perangkat tanpa hover;
  - ukuran target tombol item dan resize handle diperbesar pada viewport mobile.
- Follow-up focal zoom:
  - zoom canvas sekarang mengikuti titik fokus pointer/cubit, bukan selalu membesar dari origin kiri-atas;
  - saat `Ctrl/Meta/Alt + scroll` dengan touchpad/mouse, posisi canvas di bawah cursor dipertahankan lewat penyesuaian `scrollLeft` dan `scrollTop`;
  - saat pinch mobile atau gesture browser, titik tengah cubitan menjadi anchor zoom;
  - tombol zoom/reset/fit memakai titik tengah viewport canvas sebagai anchor agar perubahan zoom tetap terasa stabil;
  - diterapkan konsisten di editor Roadmap dan viewer Learning Path user.
- Follow-up aksi User View di editor Roadmap:
  - toolbar Visual Preview di editor Roadmap sekarang memiliki tombol `User View` untuk membuka tampilan roadmap versi user dari enrollment aktif pertama;
  - jika roadmap belum di-assign ke student, tombol `User View` tampil disabled dengan tooltip agar mentor tahu perlu assign terlebih dahulu;
  - modal Manage Roadmaps per student ditambahkan tombol `View` pada setiap enrollment agar mentor bisa memilih tampilan user tertentu;
  - aksi memakai route existing `dooplab.roadmaps.enrollments.show`, sehingga membuka viewer user/progress yang sama dengan Learning Path user.
- Fix mentor lock/unlock node Roadmap:
  - ditemukan bug: action mentor `Unlock Node` dan `Relock Node` sempat menyimpan status, tetapi saat halaman reload `recomputeUnlocks()` menghitung ulang dependency dan menimpa status manual;
  - ditambahkan kolom `mentor_override_status` pada tabel `dooplab_roadmap_node_progress` untuk menyimpan keputusan manual mentor;
  - action unlock sekarang menyimpan status `unlocked` sekaligus override `unlocked`;
  - action lock/reblock sekarang menyimpan status `locked` sekaligus override `locked`;
  - `recomputeUnlocks()` tetap mengurus auto-unlock normal, tetapi tidak lagi menimpa node yang punya override manual mentor;
  - override manual dibersihkan saat student submit node atau mentor review node, supaya workflow submitted/revision/approved tetap berjalan normal.
- Fix tombol Back Enrollment Roadmap:
  - tombol Back di `resources/js/Pages/DoopLab/Roadmaps/EnrollmentShow.vue` tidak lagi hardcoded ke Dashboard;
  - mentor/admin diarahkan kembali ke dashboard/list Roadmap DoopLab (`dooplab.roadmaps.index`) dengan roadmap aktif, tanpa membuka workspace editor langsung;
  - user/student diarahkan kembali ke daftar Learning Path/My Roadmaps (`dooplab.roadmaps.enrollments.index`);
  - label tombol dibedakan menjadi `Back Roadmap` untuk mentor/admin dan `Back Paths` untuk user.

## 2026-07-14

### Rencana: Roadmap Review Mode Auto / Manual

#### Latar Belakang
- Kebutuhan awal yang dibahas: mentor ingin bisa assign roadmap ke study group/class, bukan hanya ke user satu per satu.
- Setelah dianalisis, assign roadmap ke class ditunda dulu karena konsekuensinya cukup besar:
  - perlu membedakan roadmap personal vs roadmap kelas;
  - perlu aturan progress untuk user baru yang join class belakangan;
  - perlu alur unassign dari class;
  - perlu report/progress per class;
  - perlu keputusan apakah mentor harus approve node untuk semua siswa satu per satu.
- Keputusan sementara: **tidak mengerjakan assign class ke roadmap dulu**.
- Ide yang tetap menarik dan lebih aman diterapkan lebih dulu: tambah `review_mode` pada sistem roadmap yang sudah ada.

#### Tujuan Fitur
- Roadmap personal tetap bisa memakai review manual oleh mentor.
- Roadmap yang sifatnya latihan mandiri bisa memakai review otomatis agar mentor tidak perlu approve semua node satu per satu.
- Sistem tetap memakai struktur enrollment/progress yang sama agar tidak memecah flow DoopLab menjadi dua sistem berbeda.

#### Keputusan Desain Awal
- Tambahkan mode review di level enrollment:
  - `manual`: workflow lama, siswa submit node lalu mentor approve/revision.
  - `auto`: node bisa selesai otomatis berdasarkan aturan tertentu.
- Default untuk assignment personal yang sudah ada: `manual`.
- `review_mode` **melekat pada enrollment/assignment**, bukan pada roadmap blueprint.
- Alasan: roadmap yang sama bisa dipakai untuk kebutuhan berbeda, misalnya user A memakai review manual untuk mentoring personal, sedangkan user B memakai review auto untuk latihan mandiri.
- Node belum diberi setting sendiri pada tahap awal; semua node mengikuti mode enrollment.
- Assignment yang nanti bersifat massal/class bisa memakai default `auto`, tetapi fitur class assignment belum dieksekusi.
- Tetap pertahankan manual override mentor:
  - `unlock`;
  - `lock/reblock`;
  - `mentor_override_status`.
- Auto mode tidak boleh menghilangkan kontrol mentor pada node penting.

#### Rencana Data
- Tambah kolom di `dooplab_roadmap_enrollments`:
  - `review_mode` string, default `manual`.
- Kandidat nilai:
  - `manual`;
  - `auto`.
- Opsional tahap lanjutan:
  - tambah `review_policy` di `dooplab_roadmap_nodes` dengan nilai `inherit`, `manual`, `auto`;
  - tambah `completion_rule` di node untuk menentukan trigger auto-complete.
- Tahap pertama cukup mulai dari `review_mode` enrollment agar blast radius kecil.

#### Rencana Backend
- Update model `DoopLabRoadmapEnrollment`:
  - tambah konstanta `REVIEW_MODE_MANUAL`;
  - tambah konstanta `REVIEW_MODE_AUTO`;
  - masukkan `review_mode` ke `$fillable`.
- Update store enrollment:
  - validasi optional `review_mode`;
  - default `manual` jika tidak dikirim;
  - simpan `review_mode` saat enrollment dibuat.
- Update serializer enrollment:
  - kirim `review_mode` ke halaman viewer.
- Tambah helper rule:
  - `isAutoReview()` atau method sejenis di model/controller.
- Ubah flow submit node:
  - jika `manual`, tetap seperti sekarang: `unlocked` -> `submitted`;
  - jika `auto`, node bisa langsung `approved` saat syarat completion terpenuhi.
- Recompute unlock tetap memakai status `approved` sebagai kunci untuk membuka child node.

#### Rencana Auto Completion Tahap 1
- Untuk node tanpa resource:
  - user klik tombol semacam `Mark as Done`;
  - jika enrollment `review_mode=auto`, status langsung `approved`;
  - jika `manual`, status masuk `submitted` seperti sekarang.
- Untuk node guide:
  - tahap awal bisa tetap pakai tombol `Mark as Done`;
  - tahap lanjutan baru pertimbangkan tracking "guide opened/read".
- Untuk node quest:
  - tahap awal bisa tetap pakai tombol manual `Mark as Done` atau submit note;
  - tahap lanjutan: auto approve saat submission quest mendapat status approved/grade minimal.

#### Rencana Frontend
- Di Roadmap Lab assign modal:
  - tambah pilihan mode review saat assign roadmap ke student:
    - `Manual Review`;
    - `Auto Review`.
- Di viewer roadmap user (`EnrollmentShow.vue`):
  - tampilkan badge mode review;
  - untuk auto mode, CTA node perlu berubah dari `Submit for Review` menjadi `Mark as Done` atau label serupa;
  - untuk manual mode, UI lama tetap.
- Di view mentor:
  - untuk auto mode, mentor tetap bisa lock/unlock node jika perlu;
  - mentor tidak wajib approve setiap node.

#### Risiko / Pertanyaan yang Perlu Diputuskan
- Apakah auto mode boleh langsung approve semua node tanpa resource?
- Apakah node quest harus menunggu submission quest approved, atau cukup user mark done?
- Apakah mentor bisa mengubah `review_mode` setelah enrollment dibuat?
- Apakah mode review berlaku per enrollment saja, atau bisa override per node?
- Apakah auto-approved node perlu catatan audit seperti `reviewed_at` dan `mentor_note` kosong?

#### Checklist Progress
- [x] Analisis struktur roadmap saat ini: enrollment dan progress masih per user.
- [x] Keputusan: fitur assign roadmap ke study group/class ditunda dulu.
- [x] Keputusan: fitur `review_mode` auto/manual diprioritaskan sebagai langkah kecil.
- [x] Rencana data awal ditulis: `review_mode` di `dooplab_roadmap_enrollments`.
- [x] Rencana backend ditulis: validasi, model constant, serializer, submit flow.
- [x] Rencana frontend ditulis: pilihan mode assign dan perubahan CTA di viewer.
- [x] Buat migration `review_mode` pada `dooplab_roadmap_enrollments`.
- [x] Update model `DoopLabRoadmapEnrollment`.
- [x] Update `DoopLabRoadmapEnrollmentController::store`.
- [x] Update `DoopLabRoadmapEnrollmentController::show` serializer.
- [x] Implementasi flow submit auto-review.
- [x] Update UI assign roadmap di `resources/js/Pages/DoopLab/Roadmaps/Index.vue`.
- [x] Update UI viewer roadmap di `resources/js/Pages/DoopLab/Roadmaps/EnrollmentShow.vue`.
- [x] Tambah feature test untuk manual mode tetap sama seperti sekarang.
- [x] Tambah feature test untuk auto mode langsung approve node dan membuka child node.
- [x] Jalankan verifikasi PHP lint, feature test DoopLab roadmap, dan `npm.cmd run build`.

#### Implementasi Selesai — 2026-07-14
- Migration baru: `2026_07_14_000000_add_review_mode_to_dooplab_roadmap_enrollments_table`.
- `review_mode` default `manual`, sehingga enrollment lama tetap mengikuti workflow lama.
- Assignment roadmap ke student sekarang bisa memilih `Manual Review` atau `Auto Review`.
- Enrollment auto review saat student menekan submit/mark done:
  - node langsung menjadi `approved`;
  - `reviewed_at` otomatis terisi;
  - child node yang dependency-nya terpenuhi langsung terbuka lewat `recomputeUnlocks()`.
- Viewer roadmap user menampilkan badge `Manual Review` atau `Auto Review`.
- CTA user pada auto mode berubah menjadi `Mark as Done`; manual mode tetap `Submit Node`.
- Bug kecil diperbaiki: pencarian submission quest di roadmap resource memakai `enrollment->user_id`, bukan `student_id`.
- Verifikasi berhasil:
  - `php -l` untuk model, controller, migration, dan test yang berubah;
  - `php artisan test tests\Feature\DoopLab\RoadmapLabTest.php`;
  - `php artisan migrate`;
  - `npm.cmd run build`.

### Rencana: Class Roadmap View-Only di Detail Study Group

#### Latar Belakang
- Ide assign roadmap ke class dengan progress penuh ditunda karena terlalu kompleks.
- Alternatif yang lebih sederhana: class bisa punya roadmap, tetapi hanya sebagai **view-only curriculum**.
- Roadmap kelas tidak diperlakukan sebagai enrollment/progress DoopLab.
- Tujuannya: user di kelas bisa melihat peta materi, klik node, lalu membuka Guide atau Quest yang terhubung.

#### Keputusan Konsep
- Nama konsep: **Class Roadmap View-Only** atau **Attach Roadmap to Study Group as View-Only Curriculum**.
- Ini bukan assignment enrollment.
- Tidak membuat data di `dooplab_roadmap_enrollments`.
- Tidak membuat data di `dooplab_roadmap_node_progress`.
- Tidak ada aksi user seperti:
  - submit node;
  - mark done;
  - approve/revision;
  - lock/unlock;
  - auto review.
- User hanya bisa:
  - melihat canvas roadmap;
  - klik node;
  - melihat popup node;
  - membuka resource Guide/Quest dari popup.

#### Perbedaan dengan Roadmap Personal
- Roadmap personal:
  - memakai enrollment per user;
  - punya progress node;
  - punya `review_mode` manual/auto;
  - bisa dikelola mentor untuk progress siswa.
- Roadmap kelas view-only:
  - melekat ke study group;
  - tidak punya progress;
  - tidak punya review;
  - berfungsi sebagai peta kurikulum/materi kelas.

#### Rencana Data
- Tambah pivot table baru `study_group_roadmaps`.
- Kolom awal:
  - `id`;
  - `study_group_id`;
  - `roadmap_id`;
  - `assigned_by_user_id`;
  - `sort_order`;
  - `is_active`;
  - `created_at`;
  - `updated_at`.
- Alasan memakai pivot:
  - satu study group bisa punya banyak roadmap;
  - satu roadmap bisa dipakai di banyak study group;
  - tidak mengikat roadmap blueprint ke satu kelas saja.
- Unique constraint yang disarankan:
  - unique `study_group_id + roadmap_id`.

#### Rencana Relasi Model
- `StudyGroup::roadmaps()`:
  - belongsToMany `DoopLabRoadmap`;
  - via table `study_group_roadmaps`;
  - dengan pivot `assigned_by_user_id`, `sort_order`, `is_active`.
- `DoopLabRoadmap::studyGroups()`:
  - belongsToMany `StudyGroup`;
  - via table `study_group_roadmaps`.

#### Rencana Backend
- Admin/mentor bisa attach roadmap ke study group.
- Admin/mentor bisa detach roadmap dari study group.
- Detail kelas user (`groups.show`) memuat roadmap aktif yang terhubung ke kelas.
- Access detail tetap memakai rule yang sudah ada:
  - hanya user approved/member di study group yang bisa melihat detail kelas dan roadmap view-only.
- Serializer roadmap view-only cukup memuat:
  - roadmap `uuid`, `title`, `description`;
  - sections;
  - nodes;
  - text blocks;
  - edges;
  - resource meta list per node.
- Serializer tidak memuat:
  - progress;
  - status node;
  - mentor note;
  - student note.

#### Rencana Frontend Admin
- Tambahkan UI di detail/admin study group untuk attach/detach roadmap.
- Lokasi kandidat:
  - `resources/js/Pages/StudyGroups/Admin/Detail.vue`.
- UI minimal:
  - dropdown/select roadmap;
  - tombol attach;
  - list roadmap yang sudah attached;
  - tombol detach;
  - optional sort order.

#### Rencana Frontend User
- Tambahkan section di halaman detail kelas user:
  - `resources/js/Pages/StudyGroups/Show.vue`.
- Section nama:
  - `Class Roadmap`;
  - atau `Roadmap Kelas`.
- Jika ada banyak roadmap:
  - tampilkan tab/list roadmap;
  - user pilih salah satu untuk dilihat.
- Canvas roadmap:
  - view-only;
  - reuse pola visual dari `EnrollmentShow.vue`;
  - tidak ada status/progress badge;
  - tidak ada tombol submit/review/lock/unlock.
- Klik node:
  - buka popup;
  - tampilkan judul node;
  - tampilkan daftar resource Guide/Quest;
  - Guide link ke `guides.user.show`;
  - Quest link ke `quests.show`.

#### Rencana Implementasi Bertahap
- [x] Buat migration pivot `study_group_roadmaps`.
- [x] Tambah relasi model `StudyGroup::roadmaps()`.
- [x] Tambah relasi model `DoopLabRoadmap::studyGroups()`.
- [x] Tambah backend attach/detach roadmap ke study group.
- [x] Tambah route attach/detach untuk admin study group.
- [x] Update admin study group detail untuk attach/detach roadmap.
- [x] Update `StudyGroupController::show` agar mengirim roadmap view-only.
- [x] Buat serializer roadmap view-only yang aman untuk user kelas.
- [x] Update `StudyGroups/Show.vue` untuk menampilkan canvas roadmap view-only.
- [x] Tambah popup node resource di detail kelas.
- [x] Tambah feature test:
  - member kelas bisa melihat roadmap attached;
  - non-member tidak bisa mengakses;
  - roadmap view-only tidak membuat enrollment/progress.
- [x] Jalankan verifikasi PHP lint, feature test study group/roadmap, dan `npm.cmd run build`.

#### Implementasi Berjalan — 2026-07-14
- Migration `2026_07_14_010000_create_study_group_roadmaps_table` dibuat untuk pivot roadmap kelas.
- Model `StudyGroup` dan `DoopLabRoadmap` sekarang punya relasi many-to-many lewat `study_group_roadmaps`.
- Admin detail group mendapat panel `Class_Roadmap_View_Only` untuk attach/detach roadmap published.
- Detail kelas user (`groups.show`) mengirim `classRoadmaps` yang hanya memuat struktur roadmap dan resource link, tanpa progress/enrollment.
- `StudyGroups/Show.vue` menampilkan canvas roadmap view-only, tab roadmap jika lebih dari satu, dan popup node berisi link Guide/Quest.
- Feature test `tests/Feature/StudyGroup/UserStudyGroupDetailTest.php` ditambah untuk memastikan roadmap kelas view-only terlihat oleh member dan tidak membuat enrollment/progress.
- Verifikasi sementara berhasil:
  - `php -l` untuk controller/model/migration/test roadmap;
  - `php artisan test tests\Feature\StudyGroup\UserStudyGroupDetailTest.php`.
- Verifikasi final berhasil setelah attendance juga selesai:
  - `php artisan migrate`;
  - `npm.cmd run build`.

### Rencana: Event Attendance Check-In Code / QR

#### Latar Belakang
- Self attendance saat ini masih berbasis tombol biasa.
- Untuk event kelas/fisik, mentor butuh validasi agar siswa benar-benar hadir.
- Solusi awal: mentor membuat kode check-in angka yang berlaku terbatas waktu; user bisa scan QR atau memasukkan kode manual.

#### Keputusan Konsep
- Kode check-in bersifat sesi sementara, bukan password permanen event.
- Mentor/admin generate kode dari halaman attendance event.
- Kode lama untuk event yang sama akan dinonaktifkan saat kode baru dibuat.
- User check-in dengan kode angka dari detail event.
- QR tahap awal bisa memakai URL check-in yang membawa token sesi; input manual tetap memakai kode angka.

#### Rencana Data
- Tambah tabel `event_check_in_codes`.
- Kolom:
  - `id`;
  - `event_id`;
  - `code_hash`;
  - `plain_code_last_four`;
  - `qr_token`;
  - `expires_at`;
  - `created_by_user_id`;
  - `is_active`;
  - timestamps.
- Kode angka mentah tidak disimpan; backend validasi dengan hash.
- `qr_token` random disimpan untuk flow QR/link.

#### Rencana Backend
- Tambah model `EventCheckInCode`.
- Admin/mentor endpoint:
  - generate code untuk event;
  - default berlaku 10 menit;
  - menonaktifkan code aktif sebelumnya untuk event tersebut;
  - mengirim kode angka dan URL QR ke UI admin.
- User endpoint:
  - menerima `code` atau `token`;
  - validasi event accessible, code aktif, belum expired, dan cocok;
  - jika valid, catat `event_attendances.status = present`;
  - trigger daily quest seperti self attendance.
- Self attendance lama tetap ada, tetapi jika event punya check-in code aktif, flow baru menjadi jalur validasi utama.

#### Rencana Frontend
- Admin attendance page:
  - tombol `Generate_Check_In_Code`;
  - input durasi menit;
  - tampilkan kode angka, masa expired, dan link QR/check-in.
- User event detail:
  - tambah input kode angka;
  - tombol `Check_In_With_Code`;
  - jika user membuka URL QR dengan token, bisa submit token.

#### Rencana Implementasi Bertahap
- [x] Buat migration `event_check_in_codes`.
- [x] Tambah model `EventCheckInCode`.
- [x] Tambah route admin generate code.
- [x] Tambah route user check-in code/token.
- [x] Update `AdminEventController` generate code.
- [x] Update `UserEventController` validasi code/token.
- [x] Update `Events/Admin/Attendance.vue`.
- [x] Update `Events/UserShow.vue`.
- [x] Tambah feature test attendance code.
- [x] Jalankan PHP lint, feature test attendance, migration, dan `npm.cmd run build`.

#### Implementasi Selesai — 2026-07-14
- Migration `2026_07_14_020000_create_event_check_in_codes_table` dibuat.
- Model `EventCheckInCode` dibuat dengan cast `expires_at` dan `is_active`.
- Admin attendance page bisa generate kode angka 6 digit dengan durasi 1-120 menit.
- Kode angka mentah hanya dikirim sekali lewat flash session setelah generate.
- Database menyimpan `code_hash`, `plain_code_last_four`, `qr_token`, `expires_at`, dan status aktif.
- Saat generate kode baru, kode aktif event yang lama otomatis dinonaktifkan.
- User event detail bisa check-in dengan kode angka manual atau token QR dari URL.
- Update lanjutan:
  - QR/link check-in sekarang memakai route instant `events.attendance.qr`;
  - user yang scan QR langsung tercatat hadir tanpa input PIN;
  - user yang memakai komputer tetap bisa absen lewat input PIN 6 digit di detail event;
  - flow query token lama masih bisa dikonfirmasi dari halaman event sebagai fallback.
- Check-in code memvalidasi:
  - user punya akses ke event;
  - kode/token cocok;
  - kode masih aktif;
  - kode belum expired;
  - attendance user belum final `present`, `absent`, atau `excused`.
- Jika valid, attendance menjadi `present`, `checked_at` terisi, dan daily quest `event_attendance` tetap ter-trigger.
- Admin attendance page menampilkan QR image dari `qr_url` sebagai tahap awal, dengan kode angka manual sebagai fallback.
- Verifikasi berhasil:
  - `php -l` untuk controller/model/migration/test attendance;
  - `php artisan test tests\Feature\Event\EventCheckInCodeTest.php`;
  - `php artisan migrate`;
  - `npm.cmd run build`.
- Verifikasi update QR instant berhasil:
  - `php -l` untuk `UserEventController`, `AdminEventController`, route, dan test;
  - `php artisan test tests\Feature\Event\EventCheckInCodeTest.php`.

### Implementasi: Study Group Attendance Dashboard

#### Tujuan
- Mentor/admin butuh dashboard attendance di detail study group untuk melihat siswa mana yang hadir/tidak hadir pada event kelas.
- Dashboard dipakai sebagai bahan pertimbangan penilaian.

#### Implementasi
- Ditambahkan payload `attendanceDashboard` pada `AdminStudyGroupController::detail`.
- Data dashboard mengambil:
  - event dengan `study_group_id` sesuai kelas;
  - member aktif non-staff dari study group;
  - status dari `event_attendances`.
- Status kosong dianggap `pending`.
- Persentase attendance dihitung dari jumlah `present / total event`.
- Summary yang dikirim:
  - total event kelas;
  - total siswa aktif;
  - class attendance rate;
  - jumlah siswa attendance di bawah 75%;
  - event dengan attendance rate terendah.
- Matrix yang dikirim:
  - row = siswa;
  - column = event;
  - cell = status `present`, `absent`, `excused`, atau `pending`.

#### UI
- Ditambahkan panel `Attendance_Dashboard` di `resources/js/Pages/StudyGroups/Admin/Detail.vue`.
- UI berisi:
  - summary cards;
  - tabel matrix horizontal;
  - warna status:
    - `P` hijau untuk present;
    - `A` merah untuk absent;
    - `I` cyan untuk excused/izin;
    - `-` abu-abu untuk pending;
  - link header event ke halaman attendance event.

#### Verifikasi
- Feature test baru: `tests/Feature/StudyGroup/AdminStudyGroupAttendanceDashboardTest.php`.
- Verifikasi berhasil:
  - `php -l app\Http\Controllers\AdminStudyGroupController.php`;
  - `php -l tests\Feature\StudyGroup\AdminStudyGroupAttendanceDashboardTest.php`;
  - `php artisan test tests\Feature\StudyGroup\AdminStudyGroupAttendanceDashboardTest.php`.

#### Risiko / Pertanyaan Lanjutan
- Apakah semua roadmap boleh di-attach oleh admin, atau mentor hanya boleh attach roadmap miliknya sendiri?
- Apakah roadmap harus `is_published=true` agar bisa ditampilkan ke kelas?
- Apakah node resource yang private untuk group lain boleh tampil di roadmap kelas?
- Apakah urutan roadmap kelas perlu drag/drop atau cukup `sort_order` sederhana?
- Apakah user tanpa akses DoopLab key boleh melihat roadmap kelas view-only dari detail study group?
  - Rekomendasi awal: boleh, karena ini bagian dari study group curriculum, bukan progress DoopLab premium.

#### Rencana: Wajib Verifikasi Email Sebelum Masuk Lobby
- Kebutuhan baru: user yang sudah register/login tetapi belum verifikasi email tidak boleh masuk lobby utama.
- Kondisi saat ini:
  - `User` sudah memakai `MustVerifyEmail` dan event `Registered` sudah mengirim email verifikasi;
  - setelah register, user langsung `Auth::login()` lalu diarahkan ke `lobby`;
  - setelah login, user yang belum verified tetap diarahkan ke `lobby`;
  - route lobby `/` masih public dan tidak memakai middleware `verified`;
  - `EmailVerificationPromptController` saat user belum verified justru redirect balik ke `lobby`, sehingga halaman `Auth/VerifyEmail` tidak menjadi halaman tahan/holding page.
- Rencana perubahan:
  - guest tetap boleh membuka lobby/landing seperti biasa;
  - user login yang belum verified akan dicegah masuk lobby dan diarahkan ke `verification.notice`;
  - setelah register, user tetap boleh auto-login, tetapi diarahkan ke halaman `verification.notice`, bukan lobby;
  - setelah login, jika email belum verified diarahkan ke `verification.notice`, jika sudah verified lanjut ke intended route/lobby/dashboard;
  - `EmailVerificationPromptController` akan render `Auth/VerifyEmail` untuk user belum verified, bukan redirect ke lobby;
  - setelah link verifikasi email diklik, redirect ke lobby dengan `verified=1` tetap dipertahankan.
- File yang kemungkinan diubah saat eksekusi:
  - `app/Http/Controllers/Auth/RegisteredUserController.php`
  - `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
  - `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
  - `app/Http/Controllers/HomeController.php`
- Catatan keputusan:
  - rule ini sebaiknya berlaku juga untuk staff/admin/mentor yang belum verified, kecuali nanti diputuskan ada pengecualian khusus.
- Verifikasi berhasil: `npm.cmd run build`.

#### File Utama yang Terdampak
- `resources/js/Pages/DoopLab/Dashboard.vue`
- `resources/js/Pages/DoopLab/Roadmaps/Index.vue`
- `resources/js/Components/Dashboard/LogbookPanel.vue`
- `app/Http/Controllers/DoopLabDashboardController.php`
- `app/Http/Controllers/DoopLabLogbookController.php`
- `app/Http/Controllers/CreationApiController.php`
- `routes/web.php`

#### Verifikasi
- `npm.cmd run build` berhasil setelah batch perubahan frontend.
- `php -l` berhasil untuk route dan controller yang diubah.

### Profile Skin: Toy Flight

- Menambahkan contoh project skin baru `public/examples/profile-skin-toy-flight`.
- Tema visual mengikuti referensi portfolio toys: langit cerah, navbar kapsul, huruf balok warna-warni, pesawat mainan, awan, dan panel dashboard putih.
- Bundle berisi `skin.json`, `index.html`, `css/style.css`, `js/skin.js`, asset SVG `toy-plane`, `cloud-strip`, dan `star-badge`.
- Skin memakai renderer `project_static`, mengirim event `dooptech:profile-skin-ready`, dan membaca payload backend `dooptech:profile-skin-data`.
- Konten yang dirender: identitas user, avatar, level, job emblem, profile notes, skill blocks, class progress, creation cards, statistik profil, link Hall of Creations, dan lobby.
- Verifikasi berhasil: validasi `skin.json` dengan `ConvertFrom-Json` dan `node --check` untuk `js/skin.js`.

### Profile Skin: Cosmic Orbit

- Menambahkan contoh project skin baru `public/examples/profile-skin-cosmic-orbit`.
- Tema visual antariksa: ruang gelap, rocket hero, ringed planet, comet, satellite, star asset, orbit stage, dan dashboard telemetry.
- Semua asset visual utama diambil dari OpenMoji lewat CDN lalu disimpan lokal di folder `assets`.
- Bundle berisi `skin.json`, `index.html`, `css/style.css`, `js/skin.js`, README, dan asset SVG `rocket`, `ringed-planet`, `comet`, `star`, serta `satellite`.
- Skin memakai renderer `project_static`, mengirim event `dooptech:profile-skin-ready`, dan membaca payload backend `dooptech:profile-skin-data`.
- Konten yang dirender: identitas user, avatar, level, job emblem, profile notes, skill satellites, class telemetry, creation cards, statistik profil, link Hall of Creations, dan lobby.
- Verifikasi berhasil: validasi `skin.json` dengan `ConvertFrom-Json`, `node --check` untuk `js/skin.js`, dan cek semua asset berisi tag `<svg>`.
- Follow-up desain: CSS Cosmic Orbit dirombak agar tidak terasa seperti Toy Flight; navigasi menjadi command rail kiri, hero menjadi mission console, visual utama menjadi orbital view, telemetry tampil sebagai strip data, dan panel bawah menjadi command deck.

### Rencana: DoopNews

#### Tujuan
- Menambahkan halaman berita/beranda info bernama `DoopNews`.
- DoopNews menjadi pusat broadcast untuk:
  - event baru;
  - item shop baru;
  - kelas/roadmap baru;
  - quest baru;
  - pengumuman umum;
  - update aplikasi, patch notes, dan changelog.

#### Konsep UI
- DoopNews tampil sebagai feed berita untuk user yang sudah login.
- Di lobby/dashboard user dapat ditambahkan section ringkas `Latest DoopNews` berisi beberapa posting terbaru.
- Halaman detail berita menampilkan judul, kategori, tanggal publish, penulis, isi lengkap, dan tombol aksi jika berita terkait resource tertentu.
- Untuk kategori update aplikasi, tampilannya bisa memuat versi aplikasi, ringkasan perubahan, dan daftar perubahan penting.

#### Kategori Awal
- `announcement`
- `event`
- `shop_item`
- `class`
- `quest`
- `app_update`
- `community`

#### Hak Akses
- Super admin/admin:
  - dapat membuat, mengedit, publish, unpublish, dan menghapus semua posting DoopNews;
  - dapat membuat posting kategori `app_update`.
- Mentor/staff:
  - dapat membuat dan publish posting sesuai scope kelas/event/quest yang mereka kelola;
  - aturan scope detail perlu dicek saat implementasi.
- User biasa:
  - dapat mengirim posting sebagai draft/submission;
  - posting user tidak langsung tampil publik;
  - harus melalui moderation terlebih dahulu.

#### Moderation User Post
- Status posting:
  - `draft`;
  - `pending`;
  - `approved`;
  - `rejected`;
  - `published`;
  - `archived`.
- User biasa menggunakan tombol `Kirim Kabar`.
- Admin/mentor melihat daftar posting pending di halaman moderation.
- Setelah approved/published, posting user tampil dengan label `Community Post`.

#### Route yang Diusulkan
- User feed: `/doopnews`
- Detail berita: `/doopnews/{slug}`
- Submit user: `/doopnews/submit`
- Admin/moderation: `/admin/doopnews`

#### Integrasi
- Footer/version aplikasi bisa diarahkan ke DoopNews kategori `app_update`.
- Notifikasi dapat dikirim saat posting penting dipublish.
- Posting dapat memiliki optional action link ke event, item shop, kelas, quest, atau halaman eksternal.

#### Catatan Keputusan
- Nama final fitur: `DoopNews`.
- DoopNews sebaiknya tetap terasa sebagai kanal resmi aplikasi, bukan sosial media bebas.
- User biasa boleh berkontribusi, tetapi melalui approval agar feed tetap rapi dan tidak membingungkan.


## 2026-08-22

### Aturan Profile Skin Project Static

#### Tujuan Sistem Skin
- Skin profil adalah kosmetik untuk mengubah tampilan public profile user.
- Skin tidak boleh mengubah struktur data utama user, inventory, shop, progress, quest, class, atau creation.
- Skin hanya membaca data dari backend lewat payload profile skin, lalu merender tampilan sendiri.
- Skin tipe `project_static` dirender dari folder project statis di iframe.
- Skin tipe `project_static` harus ringan, mandiri, dan tidak bergantung pada build Vite aplikasi utama.

#### Struktur Bundle Skin Baru
- Bundle skin baru wajib punya `skin.json`.
- `skin.json` boleh berada di root folder yang dipilih atau di subfolder utama yang ikut dipilih browser.
- Struktur yang disarankan:

```text
profile-skin-name/
  skin.json
  index.html
  css/
    style.css
  js/
    skin.js
  assets/
    preview.png
    icon.svg
```

- Untuk skin `project_static`, `skin.json` wajib punya `project.entry`.
- Nilai `project.entry` biasanya:

```json
{
  "project": {
    "entry": "index.html"
  }
}
```

- File project yang boleh diimport: `html`, `css`, `js`, `json`, `png`, `jpg`, `jpeg`, `webp`, `gif`, `svg`, `woff`, `woff2`, `ttf`, `otf`, `mp3`, dan `wav`.
- File selain ekstensi yang diizinkan tidak akan disimpan ke project skin.

#### Format Minimal `skin.json`

```json
{
  "shop": {
    "code": "SKIN_WHITE_ORBIT",
    "name": "Skin: White Orbit",
    "description": "Skin profil publik.",
    "price_gold": 1200,
    "is_active": true
  },
  "skin": {
    "name": "White Orbit",
    "slug": "white-orbit",
    "description": "Skin profil publik bertema orbit.",
    "renderer_type": "project_static",
    "template_key": "project_static",
    "is_active": true,
    "hero_gradient": "linear-gradient(135deg, #f8fafc 0%, #e0f2fe 45%, #fff7ed 100%)",
    "accent_color": "#2563eb",
    "border_color": "#cbd5e1",
    "glow_color": "rgba(37,99,235,0.18)",
    "stat_panel_bg": "#ffffff",
    "text_primary": "#0f172a"
  },
  "project": {
    "entry": "index.html"
  }
}
```

#### Aturan Slug dan Update
- `slug` adalah identitas stabil skin.
- Jangan mengganti `slug` skin lama kalau user sudah membeli atau memakai skin itu.
- Jika `slug` diganti, sistem akan menganggapnya sebagai skin berbeda.
- Saat import bundle baru lewat `[Create_Import_Skin]`, `skin.json` wajib ada.
- Saat update skin lama lewat `[Update_Bundle]`, folder boleh tanpa `skin.json` selama folder tersebut berisi `index.html`.
- `[Update_Bundle]` memperbarui file tampilan/project skin lama tanpa membuat record skin baru.
- `[Update_Bundle]` dipakai agar user yang sudah memakai skin tetap tersambung ke skin yang sama.
- Jika `skin.json` pada update bundle memakai slug yang sudah dipakai skin lain, update harus ditolak.

#### Folder Aktif dan Folder Contoh
- Folder contoh bundle ada di:

```text
public/examples/profile-skin-white-orbit
public/examples/profile-skin-cosmic-orbit
public/examples/profile-skin-toy-world
```

- Folder skin aktif yang dipakai aplikasi biasanya ada di:

```text
public/storage/profile-skins/{slug}/project
```

- Folder aktif di `public/storage/profile-skins/{slug}` bisa saja tidak punya `skin.json`, karena yang dipakai runtime hanya folder `project`.
- Jika ingin update dari folder aktif tersebut, gunakan tombol `[Update_Bundle]`, bukan `[Create_Import_Skin]`.

#### Data Backend ke Skin
- Skin project static menerima data dari parent app lewat `postMessage`.
- Event data utama:

```js
window.addEventListener('message', (event) => {
  if (event.data?.type !== 'dooptech:profile-skin-data') return;
  // render event.data
});
```

- Skin harus mengirim ready event agar parent app bisa mengirim ulang payload:

```js
window.parent?.postMessage({ type: 'dooptech:profile-skin-ready' }, '*');
```

- Payload utama berisi:
  - `user`
  - `activeSkin`
  - `stats`
  - `classAverages`
  - `creations`
  - `urls`

- Data yang umum dipakai:
  - `user.name`
  - `user.username`
  - `user.email`
  - `user.bio`
  - `user.experience`
  - `user.location`
  - `user.skills`
  - `user.job_name`
  - `user.job_emblem_path`
  - `user.level_progress`
  - `stats.averageGrade`
  - `stats.totalCompleted`
  - `stats.creationCount`
  - `stats.appreciationCount`
  - `classAverages`
  - `creations`
  - `urls.profilePhoto`
  - `urls.hallOfCreations`
  - `urls.lobby`

#### Aturan Data Kosong
- Jangan hardcode data dummy seperti nama, email, nomor telepon, tanggal lahir, job, skill, pengalaman, kelas, atau karya.
- Jika data tidak ada dari backend, section terkait tidak perlu ditampilkan.
- Jika avatar/foto profil tidak ada, jangan pakai foto dummy.
- Jika creation tidak ada, section creation boleh disembunyikan.
- Jika class average tidak ada, section kelas/progress kelas boleh disembunyikan.
- Jika skill kosong, jangan tampilkan chip skill dummy.
- Jika experience kosong, jangan tampilkan riwayat experience dummy.
- Jika level progress kosong, panel level/progress boleh disembunyikan.
- Fallback yang boleh dipakai hanya label teknis netral untuk mencegah layout rusak, bukan data palsu.

#### Aturan Desain Skin
- Skin harus responsif untuk desktop dan mobile.
- Desktop harus tampil sebagai desktop, tidak boleh terkunci dalam ukuran mobile.
- Hindari banyak scrollbar bertumpuk. Untuk preview, gunakan halaman preview penuh, bukan modal kecil.
- Thumbnail creation harus memakai `object-fit: cover` dengan posisi center agar tidak terpotong aneh.
- Gunakan bahasa Indonesia untuk teks UI skin yang tampil ke user.
- Foto profil harus mengikuti tema skin. Untuk White Orbit, frame foto profil dibuat lingkaran.
- Card creation/recent work harus tampil menarik jika data creation tersedia, memakai thumbnail, judul, deskripsi bersih tanpa HTML mentah, dan metrik seperti apresiasi/insight jika ada.
- Deskripsi creation yang berasal dari HTML harus dibersihkan menjadi text sebelum ditampilkan.

#### Preview Skin
- Item shop bertipe skin harus punya tombol `Preview`.
- Inventory item bertipe skin juga harus punya tombol `Preview`.
- Preview tidak boleh memakai modal kecil yang menabrak navbar.
- Preview memakai halaman khusus:

```text
/profile/skins/{skin}/preview
```

- Halaman preview menampilkan iframe full page dengan top bar sendiri.
- Preview mengirim payload dummy-minimal dari backend yang mengikuti data user login, bukan hardcode di skin.
- Tombol preview pada light theme harus tetap terlihat jelas.

#### Import dan Update di Admin
- `[Create_Import_Skin]` dipakai untuk membuat/import skin baru.
- `[Create_Import_Skin]` wajib memilih folder yang punya `skin.json`.
- `[Update_Bundle]` dipakai untuk memperbarui tampilan skin lama.
- `[Update_Bundle]` bisa memilih folder bundle lengkap yang punya `skin.json`.
- `[Update_Bundle]` juga bisa memilih folder project-only yang berisi `index.html`, `css`, `js`, dan `assets`.
- Form `[Edit]` dipakai untuk metadata seperti name, description, price, active, warna, renderer, dan asset ringan.
- Jangan create ulang skin hanya untuk update desain, karena record baru bisa memutus ekspektasi user yang sudah membeli/memakai skin lama.

#### Deploy ke Server
- Jika update menyentuh Vue/admin page/routes/controller, jalankan build:

```bash
npm run build
php artisan optimize:clear
```

- Jika update hanya menimpa file di `public/storage/profile-skins/{slug}/project`, build tidak wajib karena file skin dibaca langsung oleh browser.
- Jika tampilan masih lama setelah update:
  - hard refresh browser;
  - jalankan `php artisan optimize:clear`;
  - purge cache CDN jika memakai Cloudflare;
  - pastikan folder yang dipilih saat update adalah folder project/bundle yang benar.

#### Build di Server 1 Core
- Untuk server Debian 1 core, `taskset` tidak membantu banyak.
- Gunakan `nice` agar build tidak terlalu mengunci server:

```bash
nice -n 15 npm run build
```

- Jika ada `cpulimit`, bisa pakai:

```bash
nice -n 15 cpulimit -l 50 -- npm run build
```

- Build dianggap berhasil jika output menampilkan:

```text
✓ built in ...
```

#### Skin yang Sudah Dikerjakan
- `White Orbit`: skin putih modern, dashboard profile, card glass, orbit visual, creation cards, dan frame foto profil lingkaran.
- `Cosmic Orbit`: skin antariksa gelap, orbital dashboard, telemetry cards, creation section, dan visual kosmik.
- `Toy World`: sebelumnya Toy Flight, diganti nama menjadi Toy World, tema mainan cerah, card creation, level progress, dan layout mobile.

#### Catatan Keputusan
- Jangan menambah mata uang baru untuk skin saat ini; cukup pakai `gold`.
- Skin adalah item shop cosmetic, bukan sistem progres baru.
- Sistem top up QRIS bisa dipikirkan terpisah dari skin; untuk skin cukup pastikan harga memakai `price_gold`.
- Prioritas skin profile: aman untuk data lama, mudah diupdate, ringan di server, dan tidak menampilkan data palsu.
