CODEX TERMINAL PROFILE SKIN

Folder ini adalah contoh project skin untuk sistem import folder saat ini.

Cara pakai:
1. Buka Admin > PROFILE SKINS.
2. Klik Create_Import_Skin.
3. Pilih folder public/examples/profile-skin-codex-terminal.
4. Setelah import, beli/equip skin dari shop atau inventory sesuai flow aplikasi.

Struktur:

profile-skin-codex-terminal/
  skin.json
  index.html
  css/style.css
  js/skin.js
  assets/circuit-frame.svg
  assets/status-badge.svg

Catatan penting:
- Backend tetap menjadi sumber data profil.
- Skin ini hanya mengatur tampilan.
- Data masuk ke iframe lewat window.postMessage.
- Event yang dibaca: dooptech:profile-skin-data.
- Field yang dipakai: user, stats, classAverages, creations, dan urls.
- Asset SVG di folder assets dipakai sebagai dekorasi internal project.
- Jika ingin preview shop berupa gambar, tambahkan file png/jpg/webp lalu isi object assets.preview di skin.json.
