TOY FLIGHT PROFILE SKIN

Contoh project skin bertema toys untuk sistem import folder static.

Cara pakai:
1. Buka Admin > PROFILE SKINS.
2. Klik Create_Import_Skin.
3. Pilih folder public/examples/profile-skin-toy-flight.
4. Setelah import, beli/equip skin dari shop atau inventory sesuai flow aplikasi.

Struktur:

profile-skin-toy-flight/
  skin.json
  index.html
  css/style.css
  js/skin.js
  assets/toy-plane.svg
  assets/cloud-strip.svg
  assets/star-badge.svg

Catatan:
- Skin ini memakai renderer project_static dan dibuka sebagai iframe.
- Data profil berasal dari backend lewat event dooptech:profile-skin-data.
- Field yang dipakai: user, stats, classAverages, creations, dan urls.
- Skin mengirim event dooptech:profile-skin-ready agar parent mengirim payload ulang.
- Pesawat memakai asset eksternal OpenMoji small airplane:
  https://openmoji.org/library/emoji-1F6E9/
  Source image diunduh dari jsDelivr CDN ke assets/toy-plane.svg:
  https://cdn.jsdelivr.net/gh/hfg-gmuend/openmoji@master/color/svg/1F6E9.svg
  Lisensi OpenMoji: CC BY-SA 4.0.
