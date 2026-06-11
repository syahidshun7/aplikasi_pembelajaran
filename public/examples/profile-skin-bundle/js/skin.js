const setText = (id, value) => {
  const node = document.getElementById(id);
  if (node) {
    node.textContent = value || '-';
  }
};

window.addEventListener('message', (event) => {
  const payload = event.data || {};
  if (payload.type !== 'dooptech:profile-skin-data') {
    return;
  }

  const user = payload.user || {};
  const stats = payload.stats || {};

  setText('display-name', user.username || user.name || 'Unknown Hero');
  setText('bio', user.bio || 'This profile is using a custom static skin project.');
  setText('level', `LVL ${user.lvl || 1}`);
  setText('average-grade', `${stats.averageGrade || 0}%`);
  setText('creations', stats.creationCount || 0);

  const avatar = document.getElementById('avatar');
  if (avatar && payload.urls?.profilePhoto) {
    avatar.src = payload.urls.profilePhoto;
  }
});

window.parent?.postMessage({ type: 'dooptech:profile-skin-ready' }, '*');
