PROFILE SKIN BUNDLE FORMAT

Import folder ini dari Admin > PROFILE SKINS > Import_Folder.

Struktur folder:

my-skin-folder/
  skin.json
  index.html
  css/
    style.css
  js/
    skin.js
  assets/
    preview.png
    background.png
    avatar-frame.png
    panel.png
    decoration.png

Catatan:
- skin.json adalah referensi data backend untuk ShopItem dan ProfileSkin.
- Path di object "assets" harus cocok dengan file di dalam folder.
- Untuk layout bebas, gunakan template_key "project_static" dan isi object "project": { "entry": "index.html" }.
- File project yang didukung: html, css, js, json, png, jpg, jpeg, webp, gif, svg, font, mp3, wav.
- Project static akan dirender di profil publik lewat iframe sandbox.
- Data profil dikirim dari aplikasi utama ke project via window.postMessage.
- Dengarkan event message dengan payload type: dooptech:profile-skin-data.
- Asset frontend boleh berbeda untuk setiap skin, tetapi data public profile harus tetap dibaca dari payload backend ini.
- Format asset: jpg, jpeg, png, atau webp.
- avatar_frame dan decoration sebaiknya PNG/WebP transparan.
- template_key yang tersedia:
  default
  void_phantom
  arcade_cabinet
  asset_showcase
  project_static

Contoh struktur payload backend public profile:

{
  "type": "dooptech:profile-skin-data",
  "user": {
    "id": 12,
    "uuid": "user-uuid",
    "name": "Budi Santoso",
    "username": "budi",
    "profile_photo": "profile-photos/budi.png",
    "email": "budi@example.com",
    "job_id": 3,
    "job_name": "Frontend Developer",
    "job_emblem_path": "jobs/frontend.png",
    "gold": 1250,
    "lvl": 7,
    "exp": 3420,
    "level_progress": {
      "level": 7,
      "title": "Adept",
      "progress_percent": 42,
      "exp_in_level": 420,
      "exp_needed": 1000,
      "is_max_level": false
    },
    "role": "student",
    "staff_play_mode": false,
    "bio": "Belajar sambil membangun project.",
    "experience": "HTML, CSS, JavaScript",
    "location": "Jakarta",
    "skills": ["Vue", "Laravel", "UI Design"],
    "active_skin": {}
  },
  "activeSkin": {
    "id": 5,
    "name": "Cyber Terminal",
    "slug": "cyber-terminal",
    "template_key": "project_static",
    "preview_image_path": "profile-skins/cyber/preview.png",
    "project_entry_path": "profile-skins/cyber/project/index.html",
    "project_root_path": "profile-skins/cyber/project",
    "project_manifest": {}
  },
  "stats": {
    "averageGrade": 88.5,
    "totalCompleted": 21,
    "creationCount": 4,
    "appreciationCount": 37
  },
  "classAverages": [
    {
      "study_group_id": 2,
      "class_name": "Web Basic",
      "average_grade": 91.2,
      "total_quests": 10,
      "completed_quests": 9
    }
  ],
  "creations": [
    {
      "id": 101,
      "slug": "portfolio-web",
      "title": "Portfolio Web",
      "description": "Project publik user.",
      "category": "Website",
      "tags": ["vue", "css"],
      "thumbnail_url": "/storage/creations/portfolio.png",
      "appreciations_count": 12,
      "insights_count": 3,
      "team_size": 1,
      "ownership_type": "owner",
      "created_at": "2026-06-03T10:00:00.000000Z"
    }
  ],
  "urls": {
    "profilePhoto": "/storage/profile-photos/budi.png",
    "hallOfCreations": "/hall/creations",
    "lobby": "/lobby"
  }
}
