COSMIC ORBIT PROFILE SKIN

Contoh project skin bertema antariksa untuk sistem import folder static.

Cara pakai:
1. Buka Admin > PROFILE SKINS.
2. Klik Create_Import_Skin.
3. Pilih folder public/examples/profile-skin-cosmic-orbit.
4. Setelah import, beli/equip skin dari shop atau inventory sesuai flow aplikasi.

Struktur:

profile-skin-cosmic-orbit/
  skin.json
  index.html
  css/style.css
  js/skin.js
  assets/rocket.svg
  assets/ringed-planet.svg
  assets/comet.svg
  assets/star.svg
  assets/satellite.svg

Catatan:
- Skin ini memakai renderer project_static dan dibuka sebagai iframe.
- Data profil berasal dari backend lewat event dooptech:profile-skin-data.
- Field yang dipakai: user, stats, classAverages, creations, dan urls.
- Skin mengirim event dooptech:profile-skin-ready agar parent mengirim payload ulang.
- Semua asset visual utama diambil dari OpenMoji dan disimpan lokal dalam folder assets.
- Source OpenMoji CDN:
  https://cdn.jsdelivr.net/gh/hfg-gmuend/openmoji@master/color/svg/
- Referensi OpenMoji:
  https://openmoji.org/
- Lisensi OpenMoji: CC BY-SA 4.0.
