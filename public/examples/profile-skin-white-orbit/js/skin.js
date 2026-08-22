const text = (id, value) => {
  const node = document.getElementById(id);
  if (!node) return;
  const visible = hasValue(value);
  node.textContent = visible ? value : '';
  node.hidden = !visible;
};

const clear = (node) => {
  while (node.firstChild) node.removeChild(node.firstChild);
};

const hasValue = (value) => {
  if (Array.isArray(value)) return value.length > 0;
  return value !== null && value !== undefined && String(value).trim() !== '';
};

const toggleSection = (node, visible) => {
  const section = node?.closest('article, section');
  if (section) section.hidden = !visible;
};

const asArray = (value) => Array.isArray(value)
  ? value.filter(Boolean)
  : String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

const formatRole = (value) => {
  const role = String(value || '').replaceAll('_', ' ');
  if (!role.trim()) return '';
  return role.charAt(0).toUpperCase() + role.slice(1);
};

const formatDate = (value, fallback = '-') => {
  if (!value) return fallback;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

const numberText = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));

const plainText = (value, fallback = '') => {
  const raw = String(value || '').trim();
  if (raw === '') return fallback;
  const doc = new DOMParser().parseFromString(raw, 'text/html');
  return (doc.body.textContent || fallback).replace(/\s+/g, ' ').trim();
};

const assetUrl = (value) => {
  const path = String(value || '').trim();
  if (path === '') return '';
  return path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')
    ? path
    : `/storage/${path.replace(/^\/+/, '')}`;
};

const creationUrl = (creation) => {
  const rawUrl = String(creation?.url || '').trim();
  if (rawUrl !== '') return rawUrl;
  const key = String(creation?.slug || creation?.id || '').trim();
  return key !== '' ? `/hall/creations/${encodeURIComponent(key)}` : '';
};

const creationImage = (creation) => assetUrl(
  creation?.thumbnail_url
  || creation?.featured_image
  || creation?.featured_image_url
  || creation?.image_url
  || creation?.cover_url
  || creation?.thumbnail
  || creation?.image
  || creation?.cover
  || creation?.media_path
);

const appendDetail = (node, label, value) => {
  if (!hasValue(value)) return;
  const row = document.createElement('div');
  row.className = 'detail-row';
  const term = document.createElement('dt');
  term.textContent = label;
  const detail = document.createElement('dd');
  detail.textContent = value || '-';
  row.append(term, detail);
  node.appendChild(row);
};

const renderDetails = (user) => {
  const node = document.getElementById('profile-details');
  if (!node) return;
  clear(node);
  appendDetail(node, 'Nama', user.name || user.username);
  appendDetail(node, 'Tanggal Lahir', formatDate(user.birth_date || user.birthday, ''));
  appendDetail(node, 'Lokasi', user.location);
  appendDetail(node, 'Email', user.email);
  appendDetail(node, 'Telepon', user.phone || user.phone_number);
  toggleSection(node, node.children.length > 0);
};

const skillGroups = (skills) => {
  const items = asArray(skills);
  if (items.length === 0) return [];
  return [
    { title: 'Keahlian Utama', items: items.slice(0, 8) },
    { title: 'Sedang Dipelajari', items: items.slice(8, 14) },
  ];
};

const renderSkills = (skills) => {
  const node = document.getElementById('skills-list');
  if (!node) return;
  clear(node);
  const groups = skillGroups(skills).filter((group) => group.items.length > 0);
  toggleSection(node, groups.length > 0);
  groups.forEach((group) => {
    const block = document.createElement('section');
    block.className = 'skill-group';
    const title = document.createElement('h3');
    title.textContent = group.title;
    const chips = document.createElement('div');
    chips.className = 'chips';
    group.items.forEach((skill) => {
      const chip = document.createElement('span');
      chip.textContent = skill;
      chips.appendChild(chip);
    });
    block.append(title, chips);
    node.appendChild(block);
  });
};

const renderExperience = (user, classes) => {
  const node = document.getElementById('experience-list');
  if (!node) return;
  clear(node);
  const items = [
    (hasValue(user.job_name) || hasValue(user.experience)) ? {
      title: user.job_name || 'Pengalaman',
      place: '',
      period: '',
      desc: user.experience || '',
    } : null,
    ...classes.slice(0, 2).map((item) => ({
      title: item.class_name || 'Proyek Pembelajaran',
      place: `Nilai rata-rata ${item.average_grade || 0}%`,
      period: 'Jalur Belajar',
      desc: 'Membangun konsistensi melalui progres kelas, latihan, dan aktivitas yang diselesaikan.',
    })),
  ].filter(Boolean);

  toggleSection(node, items.length > 0);
  items.slice(0, 3).forEach((item) => {
    const row = document.createElement('article');
    row.className = 'timeline-item';
    const content = document.createElement('div');
    const title = document.createElement('h3');
    title.textContent = item.title;
    const place = document.createElement('strong');
    place.textContent = item.place;
    const desc = document.createElement('p');
    desc.textContent = item.desc;
    const time = document.createElement('time');
    time.textContent = item.period;
    content.append(title, place, desc);
    row.append(content, time);
    node.appendChild(row);
  });
};

const renderEducation = (classes, stats) => {
  const node = document.getElementById('education-list');
  if (!node) return;
  clear(node);
  const main = classes[0] || {};
  if (!hasValue(main.class_name)) {
    toggleSection(node, false);
    return;
  }
  toggleSection(node, true);
  const item = document.createElement('article');
  item.className = 'education-item';
  const title = document.createElement('h3');
  title.textContent = main.class_name;
  const place = document.createElement('strong');
  place.textContent = 'Kelas Belajar Aktif';
  const desc = document.createElement('p');
  desc.textContent = [
    hasValue(stats.averageGrade || main.average_grade) ? `Nilai rata-rata ${stats.averageGrade || main.average_grade}%.` : '',
    hasValue(stats.totalCompleted) ? `Menyelesaikan ${stats.totalCompleted} aktivitas belajar.` : '',
  ].filter(Boolean).join(' ');
  item.append(title, place, desc);
  node.appendChild(item);
};

const renderWorks = (creations) => {
  const node = document.getElementById('works-list');
  if (!node) return;
  clear(node);
  const items = Array.isArray(creations) ? creations : [];
  if (items.length === 0) {
    toggleSection(node, false);
    return;
  }
  toggleSection(node, true);

  items.slice(0, 3).forEach((creation) => {
    const url = creationUrl(creation);
    const imageUrl = creationImage(creation);
    const item = document.createElement(url ? 'a' : 'article');
    item.className = 'work-item';
    if (url) {
      item.href = url;
      item.target = '_parent';
      item.rel = 'noopener';
    }
    const media = document.createElement('div');
    media.className = imageUrl ? 'work-media has-image' : 'work-media';
    if (imageUrl) {
      const img = document.createElement('img');
      img.src = imageUrl;
      img.alt = creation.title || 'Pratinjau karya';
      img.loading = 'lazy';
      media.appendChild(img);
    } else {
      const initials = document.createElement('span');
      initials.textContent = String(creation.title || 'Karya').trim().slice(0, 2).toUpperCase();
      media.appendChild(initials);
    }
    const content = document.createElement('div');
    content.className = 'work-content';
    const tag = document.createElement('span');
    tag.className = 'work-tag';
    tag.textContent = `${numberText(creation.appreciations_count || 0)} suka`;
    const title = document.createElement('h3');
    title.textContent = creation.title || 'Karya Tanpa Judul';
    const desc = document.createElement('p');
    desc.textContent = plainText(
      creation.description || creation.content,
      'Karya publik dari profil ini.'
    );
    content.append(tag, title, desc);
    item.append(media, content);
    node.appendChild(item);
  });
};

const renderProfile = (payload) => {
  const user = payload.user || {};
  const stats = payload.stats || {};
  const urls = payload.urls || {};
  const classes = Array.isArray(payload.classAverages) ? payload.classAverages : [];
  const creations = Array.isArray(payload.creations) ? payload.creations : [];
  const displayName = user.name || user.username || '';
  const joined = user.created_at || user.joined_at;
  const bio = user.bio || user.experience || '';

  text('display-name', displayName);
  text('job-title', user.job_name);
  text('role', formatRole(user.role));
  text('joined-at', joined ? `Bergabung sejak ${formatDate(joined)}` : '');
  text('bio', bio);
  text('about-text', bio);
  text('email', user.email);
  text('phone', user.phone || user.phone_number);
  text('location', user.location);
  text('birthday', formatDate(user.birth_date || user.birthday, ''));

  const avatar = document.getElementById('avatar');
  if (avatar && urls.profilePhoto) avatar.src = urls.profilePhoto;

  const hall = document.getElementById('hall-link');
  if (hall && urls.hallOfCreations) hall.href = urls.hallOfCreations;

  const works = document.getElementById('creation-link');
  if (works && urls.hallOfCreations) works.href = urls.hallOfCreations;

  const contact = document.getElementById('contact-link');
  if (contact && user.email) contact.href = `mailto:${user.email}`;

  renderDetails(user);
  renderSkills(user.skills);
  renderExperience(user, classes);
  renderEducation(classes, stats);
  renderWorks(creations);
};

window.addEventListener('message', (event) => {
  const payload = event.data || {};
  if (payload.type === 'dooptech:profile-skin-data') renderProfile(payload);
});

window.parent?.postMessage({ type: 'dooptech:profile-skin-ready' }, '*');
