const fallbackAvatar = 'https://api.dicebear.com/7.x/pixel-art/svg?seed=white-orbit';

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

const renderSkills = (skills) => {
  const node = document.getElementById('skills');
  if (!node) return;

  clear(node);
  const items = asArray(skills);

  if (items.length === 0) {
    const empty = document.createElement('span');
    empty.textContent = 'No skills yet';
    node.appendChild(empty);
    return;
  }

  items.slice(0, 12).forEach((skill) => {
    const chip = document.createElement('span');
    chip.textContent = skill;
    node.appendChild(chip);
  });
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

    row.append(title, score, bar);
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
    const card = document.createElement('article');
    card.className = 'creation-card';

    const title = document.createElement('strong');
    title.textContent = creation.title || 'Untitled Creation';

    const desc = document.createElement('p');
    desc.textContent = creation.description || creation.content || 'No description.';

    const meta = document.createElement('span');
    meta.textContent = `${creation.appreciations_count || 0} respect / ${creation.insights_count || 0} insight`;

    card.append(title, desc, meta);
    node.appendChild(card);
  });
};

const renderProfile = (payload) => {
  const user = payload.user || {};
  const stats = payload.stats || {};
  const progress = user.level_progress || {};
  const urls = payload.urls || {};

  text('display-name', user.username || user.name || 'Unknown Hero');
  text('bio', user.bio || user.experience || 'This white skin reads public profile data from the backend payload.');
  text('job-name', user.job_name ? `Path: ${user.job_name}` : 'Path: Adventurer');
  text('location', user.location ? `Location: ${user.location}` : 'Location: Unknown');
  text('role', user.role ? `Role: ${String(user.role).replaceAll('_', ' ')}` : 'Role: Member');
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

  const hall = document.getElementById('hall-link');
  if (hall && urls.hallOfCreations) {
    hall.href = urls.hallOfCreations;
  }

  renderSkills(user.skills);
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
