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

const statText = (id, value, formatter = (item) => item) => {
  const node = document.getElementById(id);
  if (!node) return;
  const visible = hasValue(value);
  node.textContent = visible ? formatter(value) : '';
  const item = node.closest('article');
  if (item) item.hidden = !visible;
};

const asArray = (value) => Array.isArray(value)
  ? value.filter(Boolean)
  : String(value || '').split(',').map((item) => item.trim()).filter(Boolean);

const clampPercent = (value) => Math.max(0, Math.min(100, Number(value || 0)));

const numberText = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));

const formatDate = (value, fallback = '-') => {
  if (!value) return fallback;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
};

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

const setLink = (id, value) => {
  const node = document.getElementById(id);
  if (node && value) node.href = value;
};

const appendDetail = (node, icon, label, value) => {
  if (!hasValue(value)) return;
  const row = document.createElement('div');
  row.className = 'detail-row';
  row.dataset.icon = icon;
  const box = document.createElement('div');
  const term = document.createElement('dt');
  term.textContent = label;
  const detail = document.createElement('dd');
  detail.textContent = value || '-';
  box.append(term, detail);
  row.appendChild(box);
  node.appendChild(row);
};

const renderDetails = (user) => {
  const node = document.getElementById('profile-details');
  if (!node) return;
  clear(node);
  appendDetail(node, '[]', 'Tanggal Lahir', formatDate(user.birth_date || user.birthday, ''));
  appendDetail(node, 'p', 'Telepon', user.phone || user.phone_number);
  appendDetail(node, 'o', 'Lokasi', user.location);
  appendDetail(node, 'u', 'Username', user.username || user.name);
  appendDetail(node, '@', 'Email', user.email);
  appendDetail(node, '+', 'Bergabung', formatDate(user.created_at || user.joined_at, ''));
  toggleSection(node, node.children.length > 0);
};

const renderSkills = (skills) => {
  const node = document.getElementById('skill-list');
  if (!node) return;
  clear(node);
  const items = asArray(skills);
  toggleSection(node, items.length > 0);
  items.slice(0, 8).forEach((skill) => {
    const chip = document.createElement('span');
    chip.className = 'skill-chip';
    chip.dataset.initial = String(skill).trim().slice(0, 2).toUpperCase();
    chip.textContent = skill;
    node.appendChild(chip);
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
      title: item.class_name || 'Jalur Belajar',
      place: `Nilai rata-rata ${item.average_grade || 0}%`,
      period: 'Aktif',
      desc: 'Membangun konsistensi melalui progres kelas, latihan, dan aktivitas pembelajaran.',
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

const renderClasses = (classes) => {
  const node = document.getElementById('classes');
  if (!node) return;
  clear(node);
  const items = Array.isArray(classes) ? classes : [];
  if (items.length === 0) {
    toggleSection(node, false);
    return;
  }
  toggleSection(node, true);

  items.slice(0, 4).forEach((item) => {
    const row = document.createElement('article');
    row.className = 'class-row';
    const line = document.createElement('div');
    line.className = 'class-line';
    const title = document.createElement('h3');
    title.textContent = item.class_name || 'Kelas';
    const score = document.createElement('span');
    score.className = 'class-score';
    score.textContent = `${item.average_grade || 0}%`;
    const bar = document.createElement('div');
    bar.className = 'bar';
    const fill = document.createElement('i');
    fill.style.width = `${clampPercent(item.average_grade)}%`;
    bar.appendChild(fill);
    line.append(title, score);
    row.append(line, bar);
    node.appendChild(row);
  });
};

const renderCreations = (creations) => {
  const node = document.getElementById('creations');
  if (!node) return;
  clear(node);
  const items = Array.isArray(creations) ? creations : [];
  const visible = items.slice(0, 3);

  if (visible.length === 0) {
    toggleSection(node, false);
    return;
  }
  toggleSection(node, true);

  visible.forEach((creation) => {
    const targetUrl = creationUrl(creation);
    const imageUrl = creationImage(creation);
    const card = document.createElement(targetUrl ? 'a' : 'article');
    card.className = 'creation-card';
    if (targetUrl) {
      card.href = targetUrl;
      card.target = '_parent';
      card.rel = 'noopener';
    }

    const thumb = document.createElement('div');
    thumb.className = 'creation-thumb';
    if (imageUrl) {
      const img = document.createElement('img');
      img.src = imageUrl;
      img.alt = creation.title || 'Pratinjau karya';
      img.loading = 'lazy';
      thumb.appendChild(img);
    } else {
      const fallback = document.createElement('span');
      fallback.textContent = String(creation.title || 'Karya').slice(0, 2).toUpperCase();
      thumb.appendChild(fallback);
    }

    const body = document.createElement('div');
    body.className = 'creation-body';
    const title = document.createElement('h3');
    title.textContent = creation.title || 'Karya Tanpa Judul';
    const desc = document.createElement('p');
    desc.textContent = plainText(creation.description || creation.content, 'Karya publik dari profil ini.');
    const meta = document.createElement('div');
    meta.className = 'creation-meta';
    const likes = document.createElement('span');
    likes.textContent = `${numberText(creation.appreciations_count || 0)} suka`;
    const insights = document.createElement('span');
    insights.textContent = `${numberText(creation.insights_count || 0)} insight`;
    meta.append(likes, insights);
    body.append(title, desc, meta);
    card.append(thumb, body);
    node.appendChild(card);
  });

  if (items.length > visible.length) {
    const more = document.createElement('article');
    more.className = 'creation-card';
    const thumb = document.createElement('div');
    thumb.className = 'creation-thumb';
    const plus = document.createElement('span');
    plus.textContent = '+';
    thumb.appendChild(plus);
    const body = document.createElement('div');
    body.className = 'creation-body';
    const title = document.createElement('h3');
    title.textContent = `${items.length - visible.length} karya lainnya`;
    body.append(title);
    more.append(thumb, body);
    node.appendChild(more);
  }
};

const renderContact = (user) => {
  const node = document.getElementById('contact-chips');
  if (!node) return;
  clear(node);
  [
    user.email,
    user.phone || user.phone_number,
    user.location,
  ].filter(hasValue).forEach((value) => {
    const chip = document.createElement('span');
    chip.className = 'contact-chip';
    chip.textContent = value;
    node.appendChild(chip);
  });
  toggleSection(node, node.children.length > 0);
};

const renderProfile = (payload) => {
  const user = payload.user || {};
  const stats = payload.stats || {};
  const progress = user.level_progress || {};
  const urls = payload.urls || {};
  const classes = Array.isArray(payload.classAverages) ? payload.classAverages : [];
  const creations = Array.isArray(payload.creations) ? payload.creations : [];
  const displayName = user.name || user.username || '';
  const bio = user.bio || user.experience || '';
  const progressValue = clampPercent(progress.progress || progress.percent || 0);

  text('display-name', displayName);
  text('signature-name', displayName);
  text('job-title', user.job_name);
  text('bio', bio);
  text('about-text', user.experience || bio);
  statText('average-grade', stats.averageGrade, (value) => `${value}%`);
  statText('quest-clear', stats.totalCompleted);
  statText('creation-count', stats.creationCount || (creations.length || null));
  statText('appreciation-count', stats.appreciationCount);
  const statsCard = document.querySelector('.stats-card');
  if (statsCard) statsCard.hidden = !Array.from(statsCard.querySelectorAll('article')).some((item) => !item.hidden);

  const hasLevel = hasValue(user.lvl || progress.level);
  text('level', hasLevel ? `Level ${user.lvl || progress.level}` : '');
  text('level-title', progress.title);
  text('level-progress-text', `${progressValue}%`);
  const levelPanel = document.querySelector('.level-panel');
  if (levelPanel) levelPanel.hidden = !hasLevel && !hasValue(progress.title) && progressValue <= 0;
  text('footer-year', new Date().getFullYear());

  const progressBar = document.getElementById('level-progress-bar');
  if (progressBar) progressBar.style.width = `${progressValue}%`;

  const avatar = document.getElementById('avatar');
  if (avatar && urls.profilePhoto) avatar.src = urls.profilePhoto;

  setLink('hall-link', urls.hallOfCreations);
  setLink('hall-link-panel', urls.hallOfCreations);

  const contact = document.getElementById('contact-link');
  if (contact && user.email) contact.href = `mailto:${user.email}`;
  const contactTop = document.getElementById('contact-top');
  if (contactTop && user.email) contactTop.href = `mailto:${user.email}`;

  renderDetails(user);
  renderSkills(user.skills);
  renderExperience(user, classes);
  renderEducation(classes, stats);
  renderClasses(classes);
  renderCreations(creations);
  renderContact(user);
};

window.addEventListener('message', (event) => {
  const payload = event.data || {};
  if (payload.type === 'dooptech:profile-skin-data') renderProfile(payload);
});

window.parent?.postMessage({ type: 'dooptech:profile-skin-ready' }, '*');
