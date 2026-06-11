const fallbackAvatar = 'https://api.dicebear.com/7.x/pixel-art/svg?seed=dooptech';

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

const renderSkills = (skills) => {
  const node = document.getElementById('skills');
  if (!node) return;

  const items = asArray(skills);
  clear(node);

  if (items.length === 0) {
    const empty = document.createElement('span');
    empty.textContent = 'No skills yet';
    node.appendChild(empty);
    return;
  }

  items.slice(0, 10).forEach((skill) => {
    const item = document.createElement('span');
    item.textContent = skill;
    node.appendChild(item);
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

  items.slice(0, 5).forEach((item) => {
    const row = document.createElement('div');
    row.className = 'class-row';

    const label = document.createElement('strong');
    label.textContent = item.class_name || 'Class';

    const score = document.createElement('span');
    score.textContent = `${item.average_grade || 0}% | ${item.completed_quests || 0}/${item.total_quests || 0}`;

    row.append(label, score);
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
    meta.textContent = `${creation.appreciations_count || 0} respect | ${creation.insights_count || 0} insight`;

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
  text('bio', user.bio || user.experience || 'This skin reads public profile data from the backend payload.');
  text('job-name', user.job_name ? `Class: ${user.job_name}` : 'Class: Adventurer');
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
