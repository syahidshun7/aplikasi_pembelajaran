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
