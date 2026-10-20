(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const base = (document.body?.dataset?.base || '/').replace(/\/?$/, '/');

  const toastContainer = document.getElementById('toastContainer');

  function url(path) {
    return base + path.replace(/^\//, '');
  }

  function showToast(message, type = 'success') {
    if (!toastContainer) {
      alert(message);
      return;
    }
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type === 'error' ? 'danger' : type} border-0 show`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    el.querySelector('.toast-body').textContent = message;
    toastContainer.appendChild(el);
    setTimeout(() => el.remove(), 4200);
  }

  async function api(path, options = {}) {
    const headers = Object.assign({
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
      'X-CSRF-Token': csrf,
    }, options.headers || {});

    const opts = Object.assign({}, options, { headers });
    if (opts.body && !(opts.body instanceof FormData) && typeof opts.body === 'object') {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.body);
    }

    const res = await fetch(url(path), opts);
    let data;
    try {
      data = await res.json();
    } catch {
      data = { success: false, message: 'Unexpected server response.' };
    }
    if (!res.ok && !data.message) {
      data.message = 'Request failed.';
    }
    return data;
  }

  // Sidebar mobile
  const sidebar = document.getElementById('appSidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  document.getElementById('sidebarOpen')?.addEventListener('click', () => {
    sidebar?.classList.add('open');
    if (backdrop) backdrop.hidden = false;
  });
  const closeSidebar = () => {
    sidebar?.classList.remove('open');
    if (backdrop) backdrop.hidden = true;
  };
  document.getElementById('sidebarClose')?.addEventListener('click', closeSidebar);
  backdrop?.addEventListener('click', closeSidebar);

  // Upload
  const dropzone = document.getElementById('uploadDropzone');
  const uploadInput = document.getElementById('uploadInput');
  const uploadResults = document.getElementById('uploadResults');
  const uploadProgress = document.getElementById('uploadProgress');
  const uploadBar = document.getElementById('uploadBar');
  const uploadStatus = document.getElementById('uploadStatus');

  dropzone?.addEventListener('click', () => uploadInput?.click());
  ['dragenter', 'dragover'].forEach((evt) => {
    dropzone?.addEventListener(evt, (e) => {
      e.preventDefault();
      dropzone.classList.add('dragover');
    });
  });
  ['dragleave', 'drop'].forEach((evt) => {
    dropzone?.addEventListener(evt, (e) => {
      e.preventDefault();
      dropzone.classList.remove('dragover');
      if (evt === 'drop' && e.dataTransfer?.files?.length && uploadInput) {
        uploadInput.files = e.dataTransfer.files;
      }
    });
  });

  document.getElementById('startUploadBtn')?.addEventListener('click', async () => {
    if (!uploadInput?.files?.length) {
      showToast('Select at least one file.', 'warning');
      return;
    }
    uploadResults.innerHTML = '';
    uploadProgress?.classList.remove('d-none');
    const files = Array.from(uploadInput.files);
    let done = 0;

    for (const file of files) {
      const fd = new FormData();
      fd.append('_csrf', csrf);
      fd.append('files[]', file);
      const folderId = document.querySelector('#uploadForm [name="folder_id"]')?.value || '';
      if (folderId) fd.append('folder_id', folderId);
      if (document.getElementById('asRevision')?.checked) {
        const replaceId = document.getElementById('replaceFileId')?.value;
        if (replaceId) fd.append('replace_file_id', replaceId);
      }

      uploadStatus.textContent = `Uploading ${file.name}…`;
      const data = await new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url('api/upload.php'));
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-Token', csrf);
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            const pct = Math.round(((done + e.loaded / e.total) / files.length) * 100);
            uploadBar.style.width = `${pct}%`;
          }
        };
        xhr.onload = () => {
          try { resolve(JSON.parse(xhr.responseText)); }
          catch { resolve({ success: false, message: 'Upload failed.', results: [] }); }
        };
        xhr.onerror = () => resolve({ success: false, message: 'Network error.' });
        xhr.send(fd);
      });

      done += 1;
      uploadBar.style.width = `${Math.round((done / files.length) * 100)}%`;
      const results = data.results || [{ name: file.name, success: data.success, message: data.message }];
      results.forEach((r) => {
        const li = document.createElement('li');
        li.className = r.success ? 'text-success' : 'text-danger';
        li.textContent = `${r.name}: ${r.message}`;
        uploadResults.appendChild(li);
      });
    }

    uploadStatus.textContent = 'Finished';
    showToast('Upload complete.');
    setTimeout(() => location.reload(), 900);
  });

  // Folder create via fetch
  document.getElementById('folderForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = await api('api/folders.php', { method: 'POST', body: fd });
    showToast(data.message || 'Done', data.success ? 'success' : 'danger');
    if (data.success) location.reload();
  });

  // Confirm helper
  let confirmCallback = null;
  const confirmModal = document.getElementById('confirmModal');
  const confirmBs = confirmModal ? bootstrap.Modal.getOrCreateInstance(confirmModal) : null;
  document.getElementById('confirmOkBtn')?.addEventListener('click', async () => {
    if (typeof confirmCallback === 'function') {
      await confirmCallback();
    }
    confirmBs?.hide();
  });

  function askConfirm(title, message, cb) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    confirmCallback = cb;
    confirmBs?.show();
  }

  // Item actions
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const action = btn.dataset.action;
    const type = btn.dataset.type;
    const id = btn.dataset.id;
    const name = btn.dataset.name || '';

    if (action === 'star') {
      const data = await api('api/star.php', {
        method: 'POST',
        body: { _csrf: csrf, type, id: Number(id), starred: btn.dataset.starred !== '1' },
      });
      showToast(data.message || '', data.success ? 'success' : 'danger');
      if (data.success) location.reload();
    }

    if (action === 'rename') {
      document.getElementById('renameType').value = type;
      document.getElementById('renameId').value = id;
      document.getElementById('renameName').value = name;
      bootstrap.Modal.getOrCreateInstance('#renameModal').show();
    }

    if (action === 'move') {
      document.getElementById('moveType').value = type;
      document.getElementById('moveId').value = id;
      document.getElementById('moveLabel').textContent = `Move "${name}"`;
      const tree = await api(`api/folders.php?action=tree&exclude=${type === 'folder' ? id : 0}`);
      const select = document.getElementById('moveDestination');
      select.innerHTML = '<option value="">My Storage (root)</option>';
      const walk = (nodes, depth = 0) => {
        nodes.forEach((n) => {
          const opt = document.createElement('option');
          opt.value = n.id;
          opt.textContent = `${'— '.repeat(depth)}${n.name}`;
          select.appendChild(opt);
          if (n.children?.length) walk(n.children, depth + 1);
        });
      };
      walk(tree.tree || []);
      bootstrap.Modal.getOrCreateInstance('#moveModal').show();
    }

    if (action === 'delete') {
      askConfirm('Move to trash', `Move "${name}" to trash?`, async () => {
        const data = await api('api/delete.php', {
          method: 'POST',
          body: { _csrf: csrf, type, id: Number(id), mode: 'soft' },
        });
        showToast(data.message || '', data.success ? 'success' : 'danger');
        if (data.success) location.reload();
      });
    }

    if (action === 'restore') {
      const data = await api('api/delete.php', {
        method: 'POST',
        body: { _csrf: csrf, type, id: Number(id), mode: 'restore' },
      });
      showToast(data.message || '', data.success ? 'success' : 'danger');
      if (data.success) location.reload();
    }

    if (action === 'purge') {
      askConfirm('Delete permanently', `Permanently delete "${name}"? This cannot be undone.`, async () => {
        const data = await api('api/delete.php', {
          method: 'POST',
          body: { _csrf: csrf, type, id: Number(id), mode: 'purge' },
        });
        showToast(data.message || '', data.success ? 'success' : 'danger');
        if (data.success) location.reload();
      });
    }

    if (action === 'share') {
      document.getElementById('shareType').value = type;
      document.getElementById('shareId').value = id;
      document.getElementById('shareResult').classList.add('d-none');
      document.getElementById('disableShareBtn').classList.add('d-none');
      bootstrap.Modal.getOrCreateInstance('#shareModal').show();
    }

    if (action === 'details') {
      const body = document.getElementById('detailsBody');
      body.innerHTML = '<div class="text-center py-4 text-secondary"><div class="spinner-border spinner-border-sm"></div> Loading…</div>';
      bootstrap.Modal.getOrCreateInstance('#detailsModal').show();
      const data = await api(`api/details.php?id=${encodeURIComponent(id)}`);
      if (!data.success) {
        body.textContent = data.message || 'Unable to load details.';
        return;
      }
      body.innerHTML = data.html || '';
    }

    if (action === 'revision') {
      document.getElementById('asRevision').checked = true;
      document.getElementById('replaceFileId').value = id;
      bootstrap.Modal.getOrCreateInstance('#uploadModal').show();
    }
  });

  document.getElementById('renameForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = await api('api/rename.php', { method: 'POST', body: fd });
    showToast(data.message || '', data.success ? 'success' : 'danger');
    if (data.success) location.reload();
  });

  document.getElementById('moveForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = await api('api/move.php', { method: 'POST', body: fd });
    showToast(data.message || '', data.success ? 'success' : 'danger');
    if (data.success) location.reload();
  });

  document.getElementById('shareExpiry')?.addEventListener('change', (e) => {
    document.getElementById('shareCustomWrap')?.classList.toggle('d-none', e.target.value !== 'custom');
  });

  document.getElementById('shareForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = await api('api/share.php', { method: 'POST', body: fd });
    showToast(data.message || '', data.success ? 'success' : 'danger');
    if (data.success && data.url) {
      document.getElementById('shareResult').classList.remove('d-none');
      document.getElementById('shareUrl').value = data.url;
      document.getElementById('disableShareBtn').classList.remove('d-none');
      document.getElementById('disableShareBtn').dataset.shareId = data.id;
    }
  });

  document.getElementById('copyShareBtn')?.addEventListener('click', async () => {
    const val = document.getElementById('shareUrl')?.value;
    if (!val) return;
    await navigator.clipboard.writeText(val);
    showToast('Link copied.');
  });

  document.getElementById('disableShareBtn')?.addEventListener('click', async (e) => {
    const shareId = e.target.dataset.shareId;
    const data = await api('api/share.php', {
      method: 'POST',
      body: { _csrf: csrf, action: 'disable', share_id: Number(shareId) },
    });
    showToast(data.message || '', data.success ? 'success' : 'danger');
  });

  // Tag / version actions inside details modal
  document.addEventListener('click', async (e) => {
    const tagBtn = e.target.closest('[data-tag-action]');
    if (tagBtn) {
      const data = await api('api/tags.php', {
        method: 'POST',
        body: {
          _csrf: csrf,
          action: tagBtn.dataset.tagAction,
          file_id: Number(tagBtn.dataset.fileId),
          tag_id: Number(tagBtn.dataset.tagId),
          name: tagBtn.dataset.name || '',
          color: tagBtn.dataset.color || '#0d9488',
        },
      });
      showToast(data.message || '', data.success ? 'success' : 'danger');
      if (data.success && data.html) {
        document.getElementById('detailsBody').innerHTML = data.html;
      } else if (data.success) {
        location.reload();
      }
    }

    const verBtn = e.target.closest('[data-version-action]');
    if (verBtn) {
      if (verBtn.dataset.versionAction === 'restore') {
        askConfirm('Restore version', 'Restore this version as the current file?', async () => {
          const data = await api('api/versions.php', {
            method: 'POST',
            body: { _csrf: csrf, action: 'restore', version_id: Number(verBtn.dataset.versionId) },
          });
          showToast(data.message || '', data.success ? 'success' : 'danger');
          if (data.success) location.reload();
        });
      }
    }
  });

  document.getElementById('emptyTrashBtn')?.addEventListener('click', () => {
    askConfirm('Empty trash', 'Permanently delete everything in trash?', async () => {
      const data = await api('api/delete.php', {
        method: 'POST',
        body: { _csrf: csrf, mode: 'empty' },
      });
      showToast(data.message || '', data.success ? 'success' : 'danger');
      if (data.success) location.reload();
    });
  });

  // View toggle persistence
  document.querySelectorAll('[data-view-mode]').forEach((btn) => {
    btn.addEventListener('click', () => {
      localStorage.setItem('vaultora_view', btn.dataset.viewMode);
      location.href = btn.dataset.href || location.pathname + '?view=' + btn.dataset.viewMode + (location.search.includes('folder=') ? '&' + location.search.slice(1) : '');
    });
  });
})();
