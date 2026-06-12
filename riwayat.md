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
- Fix `app.css`: ubah `background-color: var(--bg)` → `transparent` pada `user-theme-root`, tambahkan `background-color: #0a0c10` ke `body` sebagai fallback.
- Fix `Landing.vue`: hapus inline `style="background-color: #f2d9d9;"` pada wrapper div yang menutupi background.
- Fix `Error.vue`: hapus class `bg-[#0a0c10]` dari wrapper div yang menutupi background.
- Investigasi bug mobile: background terlihat zoom in/zoom out saat scroll di iOS Safari & Android Chrome.
- Root cause: `height: 100dvh` pada `.app-bg-layer` menyebabkan resize karena nilai `dvh` berubah saat address bar mobile muncul/hilang.
- Fix `AppBackgroundLayer.vue`: hapus `height: 100dvh` dan `contain: strict` — elemen `fixed inset-0` sudah cukup untuk fill viewport tanpa efek resize.


### Fitur: Download Rekap Average Siswa Study Group

- Menambahkan method `exportRecap` di `AdminStudyGroupController`: mengambil semua member non-staff, menghitung jumlah submission dan rata-rata grade dari quest milik group tersebut, lalu menghasilkan file CSV.
- Menambahkan route `GET /admin/study-groups/{uuid}/export-recap` dengan name `groups.export-recap`.
- Menambahkan tombol `[↓ Download Rekap CSV]` di halaman `StudyGroups/Admin/Detail.vue` di sebelah tombol Back.
- Kolom CSV: Nama, Username, Level, EXP, Gold, Jumlah Submission, Rata-rata Grade.


### Fitur: Download Rekap Average Siswa Study Group

- Menambahkan method `exportRecap` di `AdminStudyGroupController`: mengambil semua member non-staff, menghitung jumlah submission dan rata-rata grade dari quest milik group tersebut, lalu menghasilkan file CSV.
- Menambahkan route `GET /admin/study-groups/{uuid}/export-recap` dengan name `groups.export-recap`.
- Menambahkan tombol `[↓ Download Rekap CSV]` di halaman `StudyGroups/Admin/Detail.vue` di sebelah tombol Back.
- Kolom CSV: Nama, Username, Level, EXP, Gold, Jumlah Submission, Rata-rata Grade.



## 2026-06-13

### Fix: Auto-correct Task Bank Platforming & Word Match

- Investigasi bug: submission quest dengan task bank tipe `platforming` dan `word_match` masuk ke status `Pending` alih-alih langsung di-auto correct seperti `multiple_choice`.
- Root cause: `applyTaskBankSubmissionPayload()` di `SubmissionController` selalu set `status = STATUS_PENDING` untuk semua tipe dan tidak pernah memanggil `evaluateTaskBankAnswers()` meski method tersebut sudah ada.
- Fix `SubmissionController::applyTaskBankSubmissionPayload`: tambahkan kondisi pengecekan `isAutoCheckedTaskBankQuest()` — jika tipe adalah `multiple_choice`, `platforming`, atau `word_match`, maka normalize jawaban, validasi, evaluasi, dan set `status = Approved` + `pipeline_status = AI_CHECKED` + grade/feedback/reward langsung.
- Quest dengan essay atau tipe campuran tetap masuk pipeline `Pending` seperti sebelumnya.
