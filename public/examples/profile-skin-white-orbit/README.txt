WHITE ORBIT PROFILE SKIN

Contoh project skin advance bertema putih untuk sistem import folder static.

Cara pakai:
1. Buka Admin > PROFILE SKINS.
2. Klik Create_Import_Skin.
3. Pilih folder public/examples/profile-skin-white-orbit.
4. Setelah import, beli/equip skin dari shop atau inventory sesuai flow aplikasi.

Struktur:

profile-skin-white-orbit/
  skin.json
  index.html
  css/style.css
  js/skin.js
  assets/orbit-rings.svg
  assets/spark.svg

Catatan:
- Skin ini memakai tema putih dengan font system modern.
- Scrollbar berasal dari CSS skin: lihat selector body::-webkit-scrollbar dan scrollbar-color.
- Data profil tetap berasal dari backend lewat event dooptech:profile-skin-data.
- Field yang dipakai: user, stats, classAverages, creations, dan urls.
- Jika ingin preview shop berupa gambar, tambahkan file png/jpg/webp dan isi object assets.preview di skin.json.
