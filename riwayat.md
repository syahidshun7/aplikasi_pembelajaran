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
