# Dokumentasi Usulan Skripsi

## Identitas Project

**Nama project sementara:** DoopTech Learning Platform  
**Jenis sistem:** Learning Management System (LMS) berbasis web dengan pendekatan gamifikasi  
**Status penggunaan:** Sudah digunakan di lingkungan sekolah selama 1 semester  
**Teknologi utama:** Laravel, Vue.js, Inertia.js, Vite, Tailwind CSS, MySQL, Redis, Node.js untuk chat server, dan integrasi AI sebagai fitur pendukung evaluasi submission

## Ringkasan Project

Project ini adalah platform pembelajaran berbasis web yang dirancang untuk mendukung aktivitas belajar siswa dan pengelolaan pembelajaran oleh guru/admin. Sistem tidak hanya menyediakan fitur LMS umum seperti materi, tugas, kelas, dan pengumpulan jawaban, tetapi juga menambahkan unsur gamifikasi seperti level, experience point, gold, leaderboard, daily quest, shop item, inventory, reward, dan progress tracking.

Selain itu, sistem juga memiliki fitur **AI-assisted evaluation** sebagai alat bantu dalam proses evaluasi submission siswa. Fitur AI digunakan untuk membantu ekstraksi, pembersihan, analisis, dan penyajian hasil evaluasi jawaban. Dalam konteks penelitian, fitur ini dapat diposisikan sebagai fitur pendukung, bukan pengganti keputusan guru.

Platform ini telah digunakan di sekolah selama satu semester, sehingga project tidak hanya berada pada tahap prototipe, tetapi sudah memiliki konteks implementasi nyata. Hal ini membuat project layak dijadikan bahan skripsi karena dapat dikaji dari sisi rancang bangun, implementasi, usability, penerimaan pengguna, dan dampaknya terhadap keterlibatan belajar siswa.

## Latar Belakang Singkat

Pemanfaatan LMS di sekolah dapat membantu proses pembelajaran menjadi lebih terstruktur, terdokumentasi, dan mudah diakses. Namun, LMS konvensional sering kali hanya berfungsi sebagai tempat distribusi materi dan pengumpulan tugas, sehingga kurang memberi dorongan motivasional bagi siswa untuk aktif belajar.

Gamifikasi dapat menjadi pendekatan untuk meningkatkan keterlibatan belajar dengan menerapkan elemen permainan ke dalam sistem non-game. Dalam project ini, gamifikasi diterapkan melalui model quest, reward, level, gold, leaderboard, progress, daily quest, dan sistem item. Dengan pendekatan tersebut, aktivitas belajar dibuat lebih terlihat, bertahap, dan memiliki umpan balik yang lebih menarik bagi siswa.

## Alasan Project Layak Dijadikan Skripsi

1. **Sudah digunakan secara nyata**

   Sistem telah digunakan selama satu semester di sekolah, sehingga penelitian dapat menggunakan pengalaman implementasi dan data penggunaan nyata.

2. **Memiliki kompleksitas fitur yang cukup**

   Project tidak terbatas pada CRUD sederhana. Sistem memiliki modul pembelajaran, quest, submission, task bank, rubric, event, progress, reward, shop, inventory, leaderboard, profile, chat, notification, DoopLab, dan fitur AI-assisted evaluation.

3. **Memiliki nilai akademik**

   Project dapat dikaji sebagai penerapan gamifikasi dalam LMS untuk meningkatkan keterlibatan siswa pada lingkungan sekolah.

4. **Dapat diuji secara ilmiah**

   Sistem dapat dievaluasi menggunakan black box testing, System Usability Scale (SUS), Technology Acceptance Model (TAM), User Acceptance Test (UAT), atau analisis data penggunaan selama satu semester.

5. **Relevan dengan kebutuhan sekolah**

   Sistem mendukung digitalisasi pembelajaran, pengelolaan tugas, monitoring progres siswa, dan peningkatan motivasi belajar melalui mekanisme reward.

## Fitur Utama Sistem

### 1. Manajemen Pengguna

- Login dan register.
- Role pengguna seperti siswa, mentor/guru, admin, dan super admin.
- Profil pengguna.
- Level, experience point, gold, dan statistik belajar.

### 2. Study Group atau Kelas

- Pengelolaan kelas atau kelompok belajar.
- Permintaan bergabung ke kelas.
- Pengelolaan anggota kelas.
- Rekap aktivitas dan nilai siswa.
- Dashboard kehadiran.

### 3. Quest dan Tugas

- Guru/admin dapat membuat quest atau tugas.
- Quest dapat dikaitkan dengan kelas, event, guide, atau task bank.
- Quest memiliki status, deadline, reward, dan pengaturan attempt.
- Siswa dapat mengerjakan dan mengirimkan submission.

### 4. Task Bank

- Mendukung bank soal.
- Tipe soal seperti multiple choice, essay, platforming, dan word match.
- Beberapa tipe soal dapat dinilai otomatis.
- Mendukung import JSON untuk task bank.

### 5. Submission dan Penilaian

- Siswa dapat mengumpulkan jawaban/tugas.
- Guru/admin dapat menilai submission.
- Sistem mendukung rubric.
- Terdapat pipeline evaluasi berbasis AI untuk membantu ekstraksi, pembersihan, analisis, dan presentasi hasil submission.

### 5.1 AI-Assisted Evaluation

- Membantu ekstraksi isi submission dari teks atau dokumen.
- Membantu membersihkan teks submission agar lebih siap dianalisis.
- Membantu mendeteksi struktur jawaban siswa.
- Membantu analisis semantik terhadap isi submission.
- Membantu menyiapkan konteks penilaian berdasarkan rubric.
- Membantu memberikan saran evaluasi awal terhadap jawaban siswa.
- Membantu validasi dan penyajian hasil evaluasi agar lebih mudah dibaca guru/admin.
- Keputusan akhir penilaian tetap berada pada guru/admin, sehingga AI berperan sebagai alat bantu, bukan penentu nilai final secara mutlak.

### 6. Gamifikasi

- Level dan experience point.
- Gold sebagai reward.
- Daily quest.
- Leaderboard.
- Shop item.
- Inventory.
- Unlock akses atau reward tertentu.
- Profile skin sebagai kosmetik profil.

### 7. Event dan Guide

- Event pembelajaran atau kegiatan sekolah.
- Guide sebagai materi atau panduan belajar.
- Progress event berdasarkan guide dan quest yang dibuka.
- Absensi event.

### 8. DoopLab

- Roadmap pembelajaran visual.
- Todo dan logbook.
- Mentor review.
- Progress node pembelajaran.
- Akses premium berbasis item/gold.

### 9. Hall of Creations

- Siswa dapat membuat dan menampilkan karya.
- Karya dapat diapresiasi dan diberi insight.
- Mendukung proses review dan publikasi karya.

### 10. Notifikasi dan Chat

- Notifikasi aktivitas belajar.
- Chat global atau room chat.
- Upload gambar pada chat.

## Fokus Penelitian yang Disarankan

Project ini memiliki banyak fitur, tetapi untuk skripsi sebaiknya fokus penelitian dibuat lebih sempit agar mudah dibahas dan diuji.

Fokus yang paling disarankan:

**Rancang bangun dan evaluasi LMS berbasis gamifikasi dengan model quest pada lingkungan sekolah.**

Fokus ini cocok karena:

- sesuai dengan fitur utama project;
- tidak terlalu luas;
- tidak memaksa narasi pembelajaran berbasis proyek;
- dapat diuji dengan data penggunaan selama satu semester;
- tetap mencakup elemen penting seperti quest, reward, progress, leaderboard, dan submission.

## Rekomendasi Judul Skripsi

### Judul Utama yang Disarankan

**Rancang Bangun dan Evaluasi Learning Management System Berbasis Gamifikasi dengan Model Quest pada Lingkungan Sekolah**

### Alternatif Judul Jika Fitur AI Ingin Ditekankan

1. **Rancang Bangun dan Evaluasi Learning Management System Berbasis Gamifikasi dengan Dukungan AI-Assisted Evaluation pada Lingkungan Sekolah**

2. **Pengembangan Learning Management System Berbasis Gamifikasi dengan Fitur AI-Assisted Evaluation untuk Mendukung Proses Pembelajaran di Sekolah**

3. **Rancang Bangun Platform Pembelajaran Berbasis Web dengan Gamifikasi dan AI-Assisted Evaluation pada Lingkungan Sekolah**

Catatan: apabila fitur AI dimasukkan ke dalam judul utama, penelitian perlu menjelaskan lebih rinci cara kerja AI, batasan penggunaan AI, peran guru dalam validasi hasil, serta risiko kesalahan atau bias dari hasil evaluasi AI. Jika ingin scope lebih aman, fitur AI sebaiknya dijelaskan sebagai fitur pendukung di dalam sistem.

### Alternatif Judul

1. **Evaluasi Implementasi Learning Management System Berbasis Gamifikasi dalam Meningkatkan Keterlibatan Belajar Siswa**

2. **Rancang Bangun Platform Pembelajaran Berbasis Web dengan Pendekatan Gamifikasi pada Lingkungan Sekolah**

3. **Pengembangan Learning Management System Berbasis Gamifikasi Menggunakan Laravel dan Vue.js**

4. **Analisis Penerimaan Pengguna terhadap Learning Management System Berbasis Gamifikasi Menggunakan Technology Acceptance Model**

5. **Rancang Bangun Sistem Pembelajaran Digital dengan Model Quest, Reward, dan Progress Tracking**

## Rumusan Masalah yang Dapat Digunakan

1. Bagaimana merancang dan membangun Learning Management System berbasis gamifikasi dengan model quest pada lingkungan sekolah?

2. Bagaimana penerapan elemen gamifikasi seperti quest, level, reward, leaderboard, dan progress tracking dalam sistem pembelajaran?

3. Bagaimana hasil pengujian fungsionalitas sistem menggunakan metode black box testing?

4. Bagaimana tingkat usability atau penerimaan pengguna terhadap sistem setelah digunakan di sekolah?

5. Bagaimana data penggunaan sistem selama satu semester menggambarkan keterlibatan siswa dalam pembelajaran?

6. Bagaimana fitur AI-assisted evaluation dapat membantu guru/admin dalam memproses dan menganalisis submission siswa?

## Tujuan Penelitian

1. Merancang dan membangun LMS berbasis gamifikasi yang dapat digunakan dalam proses pembelajaran di sekolah.

2. Menerapkan model quest, reward, level, leaderboard, dan progress tracking untuk mendukung keterlibatan siswa.

3. Menguji fungsionalitas sistem menggunakan black box testing.

4. Mengevaluasi usability atau penerimaan pengguna terhadap sistem.

5. Menganalisis penggunaan sistem berdasarkan data implementasi selama satu semester.

6. Mendeskripsikan penerapan fitur AI-assisted evaluation sebagai alat bantu dalam proses evaluasi submission siswa.

## Batasan Masalah

1. Penelitian dilakukan pada sistem LMS yang telah digunakan di lingkungan sekolah.

2. Fokus utama penelitian adalah fitur pembelajaran dan gamifikasi, seperti quest, submission, reward, level, leaderboard, dan progress tracking.

3. Evaluasi sistem dapat dilakukan menggunakan data penggunaan, kuesioner pengguna, dan pengujian fungsional.

4. Penelitian tidak membahas keamanan sistem secara mendalam kecuali pada aspek autentikasi dan hak akses pengguna.

5. Fitur AI dijelaskan sebagai fitur pendukung evaluasi submission, bukan sebagai pengganti penilaian guru secara penuh.

6. Penelitian tidak berfokus pada pengembangan model AI dari nol, tetapi pada integrasi dan pemanfaatan AI dalam alur evaluasi pembelajaran.

## Metode Penelitian yang Disarankan

### 1. Metode Pengembangan Sistem

Metode yang dapat digunakan:

- **Prototype**, jika ingin menekankan proses iterasi berdasarkan kebutuhan sekolah.
- **Waterfall**, jika ingin menyusun laporan dengan alur analisis, desain, implementasi, pengujian, dan evaluasi.
- **Research and Development (R&D)**, jika ingin menekankan bahwa sistem dikembangkan sebagai produk pembelajaran dan diuji kelayakannya.

Rekomendasi paling cocok:

**Research and Development (R&D) dengan pendekatan prototype.**

Alasannya, sistem sudah dikembangkan, digunakan, dan dapat dievaluasi berdasarkan feedback pengguna nyata.

### 2. Metode Pengujian

- **Black Box Testing** untuk menguji fungsi sistem.
- **System Usability Scale (SUS)** untuk mengukur usability.
- **Technology Acceptance Model (TAM)** untuk mengukur penerimaan pengguna.
- **User Acceptance Test (UAT)** untuk validasi dari guru/admin.
- **Analisis deskriptif** untuk melihat aktivitas penggunaan selama satu semester.
- **Evaluasi terbatas fitur AI** melalui perbandingan hasil saran AI dengan validasi guru/admin atau melalui wawancara/kuesioner persepsi guru terhadap manfaat fitur AI.

## Data yang Dapat Digunakan

Karena sistem sudah digunakan selama satu semester, data berikut dapat dipertimbangkan:

- jumlah user aktif;
- jumlah kelas atau study group;
- jumlah quest yang dibuat;
- jumlah submission siswa;
- jumlah daily quest yang diselesaikan;
- jumlah event atau guide yang dibuka;
- rata-rata nilai submission;
- jumlah submission yang diproses menggunakan fitur AI;
- persepsi guru/admin terhadap manfaat fitur AI dalam membantu evaluasi submission;
- jumlah reward/gold yang diperoleh siswa;
- aktivitas leaderboard;
- data penggunaan shop/inventory;
- hasil kuesioner siswa;
- hasil wawancara guru atau admin.

## Contoh Variabel Evaluasi

### Jika Menggunakan SUS

Variabel utama:

- usability sistem;
- kemudahan penggunaan;
- kenyamanan pengguna;
- konsistensi antarmuka;
- kepercayaan diri pengguna saat memakai sistem.

### Jika Menggunakan TAM

Variabel utama:

- perceived usefulness;
- perceived ease of use;
- attitude toward using;
- behavioral intention to use;
- actual system use.

### Jika Menggunakan Engagement

Indikator yang dapat digunakan:

- frekuensi login;
- jumlah quest yang diselesaikan;
- jumlah submission;
- penyelesaian daily quest;
- partisipasi pada event;
- interaksi dengan reward, leaderboard, atau inventory.

### Jika Mengevaluasi Fitur AI

Indikator yang dapat digunakan:

- kemudahan guru/admin dalam memahami hasil analisis AI;
- kesesuaian saran AI dengan penilaian guru/admin;
- kecepatan proses pemeriksaan submission setelah dibantu AI;
- tingkat kepercayaan guru/admin terhadap hasil AI;
- kebutuhan validasi manual oleh guru/admin;
- kendala atau risiko penggunaan AI dalam evaluasi jawaban siswa.

## Kelebihan Project untuk Skripsi

- Sudah digunakan dalam kondisi nyata.
- Memiliki fitur gamifikasi yang jelas.
- Memiliki data aktivitas pengguna.
- Memiliki arsitektur sistem modern.
- Memiliki modul pembelajaran yang lengkap.
- Dapat diuji dari sisi teknis dan pengguna.
- Cocok untuk bidang Rekayasa Perangkat Lunak, Sistem Informasi, dan Teknologi Pendidikan.

## Risiko atau Hal yang Perlu Diperhatikan

1. **Scope terlalu luas**

   Solusi: fokuskan penelitian pada LMS berbasis gamifikasi, bukan seluruh fitur project.

2. **Data pengguna perlu dijaga**

   Solusi: gunakan data agregat dan anonymized. Jangan tampilkan nama siswa secara langsung dalam laporan.

3. **Fitur terlalu banyak untuk dibahas**

   Solusi: fitur tambahan seperti profile skin, chat, dan AI cukup dijelaskan sebagai fitur pendukung.

4. **Harus jelas metrik evaluasinya**

   Solusi: gunakan SUS, TAM, UAT, black box testing, dan analisis penggunaan.

5. **Fitur AI berpotensi memperluas scope penelitian**

   Solusi: posisikan AI sebagai fitur pendukung evaluasi submission. Jelaskan bahwa AI membantu guru/admin, tetapi keputusan akhir tetap divalidasi manusia.

## Referensi Awal

1. Nugroho, R. P., Soepriyanto, Y., & Wedi, A. (2024). **Development of Learning Management System with Gamification Approach for Project-Based Learning**. Jurnal Teknologi Pendidikan, 26(3), 794-806.  
   https://journal.unj.ac.id/unj/index.php/jtp/article/view/40873

2. Dichev, C., & Dicheva, D. (2017). **Gamifying education: what is known, what is believed and what remains uncertain: a critical review**. International Journal of Educational Technology in Higher Education.  
   https://link.springer.com/article/10.1186/s41239-017-0042-5

3. Zainuddin, Z., Chu, S. K. W., Shujahat, M., & Perera, C. J. (2020). **The impact of gamification on learning and instruction: A systematic review of empirical evidence**. Educational Research Review.  
   https://doi.org/10.1016/j.edurev.2020.100326

4. **Gamification in Learning Management Systems: A Systematic Literature Review**. Information, MDPI.  
   https://www.mdpi.com/2078-2489/16/12/1094

5. Sari, et al. **Exploring the Impact of Gamification Elements in Learning Management Systems on Intrinsic and Extrinsic Student Motivation: A Systematic Literature Review**. JOIV: International Journal on Informatics Visualization.  
   https://joiv.org/index.php/joiv/article/view/5141

6. Li, et al. (2024). **The use of leaderboards in education: A systematic review of empirical evidence in higher education**. Journal of Computer Assisted Learning.  
   https://onlinelibrary.wiley.com/doi/10.1111/jcal.13077

## Kesimpulan Awal

Berdasarkan fitur, tingkat implementasi, dan fakta bahwa sistem telah digunakan selama satu semester di sekolah, project ini layak dijadikan objek skripsi. Arah penelitian yang paling disarankan adalah rancang bangun dan evaluasi LMS berbasis gamifikasi dengan model quest. Pendekatan ini cukup kuat secara teknis, relevan secara pendidikan, dan memungkinkan evaluasi menggunakan data nyata dari pengguna.

Judul yang paling direkomendasikan:

**Rancang Bangun dan Evaluasi Learning Management System Berbasis Gamifikasi dengan Model Quest pada Lingkungan Sekolah**
