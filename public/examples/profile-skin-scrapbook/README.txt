SCRAPBOOK MEMORY PROFILE SKIN

Contoh project skin bertema scrapbook untuk sistem import folder static.

Cara pakai:
1. Buka Admin > PROFILE SKINS.
2. Klik Create_Import_Skin.
3. Pilih folder public/examples/profile-skin-scrapbook.
4. Setelah import, beli/equip skin dari shop atau inventory sesuai flow aplikasi.

Struktur:

profile-skin-scrapbook/
  skin.json
  index.html
  css/style.css
  js/skin.js
  assets/doodle-star.svg
  assets/doodle-heart.svg
  assets/tiny-flower.svg

Catatan:
- Skin ini memakai renderer project_static dan dibuka sebagai iframe.
- Data profil berasal dari backend lewat event dooptech:profile-skin-data.
- Field yang dipakai: user, stats, classAverages, creations, dan urls.
- Jika ingin preview shop berupa gambar, tambahkan file png/jpg/webp lalu isi object assets.preview di skin.json.
