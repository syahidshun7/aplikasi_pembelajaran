const fallbackAvatar = 'https://api.dicebear.com/7.x/pixel-art/svg?seed=cosmic-orbit';

const text = (id, value) => {
  const node = document.getElementById(id);
  if (node) {
    node.textContent = value || '-';
  }
};

const clear = (node) => {
  while (node.firstChild) {
    node.removeChild(node.firstChild);
  }
};

const asArray = (value) => {
  if (Array.isArray(value)) {
    return value.filter(Boolean);
  }

  return String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
};

const clampPercent = (value) => Math.max(0, Math.min(100, Number(value || 0)));

const assetUrl = (value) => {
  const path = String(value || '').trim();
  if (path === '') return '';
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) return path;
  return `/storage/${path.replace(/^\/+/, '')}`;
};

const creationUrl = (creation) => {
  const rawUrl = String(creation?.url || '').trim();
  if (rawUrl !== '') return rawUrl;
  const key = String(creation?.slug || creation?.id || '').trim();
  return key !== '' ? `/hall/creations/${encodeURIComponent(key)}` : '';
};

const initials = (user) => {
  const source = String(user?.name || user?.username || 'CO').trim();
  const parts = source.split(/\s+/).filter(Boolean);
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  }

  return source.slice(0, 2).toUpperCase();
};

const setLink = (id, value) => {
  const node = document.getElementById(id);
  if (node && value) {
    node.href = value;
  }
};

const renderSkills = (skills) => {
  const node = document.getElementById('skill-list');
  if (!node) return;

  clear(node);
  const items = asArray(skills);

  if (items.length === 0) {
    const empty = document.createElement('span');
    empty.textContent = 'No skills yet';
    node.appendChild(empty);
    return;
  }

  items.slice(0, 14).forEach((skill) => {
    const chip = document.createElement('span');
    chip.textContent = skill;
    node.appendChild(chip);
  });
};

const renderProfileNotes = (user) => {
  const skills = asArray(user.skills);

  text('profile-display-name', user.name || user.username || 'Unknown Hero');
  text('profile-experience', user.experience || 'Not shared');
  text('profile-location-note', user.location || 'Not shared');
  text(
    'profile-skills-note',
    skills.length > 0
      ? `Public skills: ${skills.slice(0, 8).join(', ')}`
      : 'No public skills shared on this profile.'
  );
};

const renderClasses = (classes) => {
  const node = document.getElementById('classes');
  if (!node) return;

  clear(node);
  const items = Array.isArray(classes) ? classes : [];

  if (items.length === 0) {
    const empty = document.createElement('p');
    empty.textContent = 'No class data.';
    node.appendChild(empty);
    return;
  }

  items.slice(0, 6).forEach((item) => {
    const row = document.createElement('div');
    row.className = 'class-row';

    const title = document.createElement('strong');
    title.textContent = item.class_name || 'Class';

    const score = document.createElement('span');
    score.textContent = `${item.average_grade || 0}%`;

    const bar = document.createElement('div');
    bar.className = 'bar';

    const fill = document.createElement('i');
    fill.style.width = `${clampPercent(item.average_grade)}%`;
    bar.appendChild(fill);

    const note = document.createElement('small');
    note.textContent = `${item.completed_quests || 0} / ${item.total_quests || 0} quests clear`;

    row.append(title, score, bar, note);
    node.appendChild(row);
  });
};

const renderCreations = (creations) => {
  const node = document.getElementById('creations');
  if (!node) return;

  clear(node);
  const items = Array.isArray(creations) ? creations : [];

  if (items.length === 0) {
    const empty = document.createElement('p');
    empty.textContent = 'No public creation yet.';
    node.appendChild(empty);
    return;
  }

  items.slice(0, 6).forEach((creation) => {
    const targetUrl = creationUrl(creation);
    const card = document.createElement(targetUrl ? 'a' : 'article');
    card.className = 'creation-card';
    if (targetUrl) {
      card.href = targetUrl;
      card.target = '_parent';
      card.rel = 'noopener';
    }

    const thumbnailUrl = assetUrl(creation.thumbnail_url || creation.featured_image);
    if (thumbnailUrl) {
      const thumb = document.createElement('img');
      thumb.className = 'creation-thumb';
      thumb.src = thumbnailUrl;
      thumb.alt = creation.title || 'Creation thumbnail';
      card.appendChild(thumb);
    }

    const body = document.createElement('div');
    body.className = 'creation-body';

    const title = document.createElement('strong');
    title.textContent = creation.title || 'Untitled Creation';

    const desc = document.createElement('p');
    desc.textContent = creation.description || creation.content || 'No description.';

    const meta = document.createElement('span');
    meta.textContent = `${creation.appreciations_count || 0} respect / ${creation.insights_count || 0} insight`;

    body.append(title, desc, meta);
    card.appendChild(body);
    node.appendChild(card);
  });
};

const renderProfile = (payload) => {
  const user = payload.user || {};
  const stats = payload.stats || {};
  const progress = user.level_progress || {};
  const urls = payload.urls || {};

  text('initials', initials(user));
  text('display-name', user.username || user.name || 'Unknown Hero');
  text('bio', user.bio || user.experience || 'A cosmic public profile powered by backend profile telemetry.');
  text('role', user.role ? `Role: ${String(user.role).replaceAll('_', ' ')}` : 'Role: Member');
  text('job-name', user.job_name ? `Path: ${user.job_name}` : 'Path: Adventurer');
  text('job-current', user.job_name || 'Adventurer');
  text('location', user.location ? `Location: ${user.location}` : 'Location: Unknown');
  text('level-title', progress.title || 'Level');
  text('level', user.lvl || progress.level || 1);
  text('average-grade', `${stats.averageGrade || 0}%`);
  text('quest-clear', stats.totalCompleted || 0);
  text('creation-count', stats.creationCount || 0);
  text('appreciation-count', stats.appreciationCount || 0);

  const avatar = document.getElementById('avatar');
  if (avatar) {
    avatar.src = urls.profilePhoto || fallbackAvatar;
  }

  const jobEmblem = document.getElementById('job-emblem');
  if (jobEmblem) {
    jobEmblem.src = assetUrl(user.job_emblem_path) || '/images/logo.png';
    jobEmblem.alt = user.job_name ? `${user.job_name} emblem` : 'Default job emblem';
  }

  setLink('hall-link', urls.hallOfCreations);
  setLink('hall-link-top', urls.hallOfCreations);
  setLink('hall-link-panel', urls.hallOfCreations);
  setLink('lobby-link', urls.lobby);

  renderSkills(user.skills);
  renderProfileNotes(user);
  renderClasses(payload.classAverages);
  renderCreations(payload.creations);
};

window.addEventListener('message', (event) => {
  const payload = event.data || {};
  if (payload.type !== 'dooptech:profile-skin-data') {
    return;
  }

  renderProfile(payload);
});

window.parent?.postMessage({ type: 'dooptech:profile-skin-ready' }, '*');
