<?php
declare(strict_types=1);
/** @var array $details */
$file = $details['file'];
$tags = $details['tags'];
$allTags = vaultora_list_tags((int) $file['user_id']);
$versions = $details['versions'];
$share = $details['share'];
$fileId = (int) $file['id'];
?>
<div class="row g-4">
  <div class="col-md-6">
    <h3 class="h6 text-secondary">Overview</h3>
    <dl class="row mb-0 small">
      <dt class="col-5">Name</dt><dd class="col-7"><?= e($file['original_name']) ?></dd>
      <dt class="col-5">Type</dt><dd class="col-7"><?= e(strtoupper((string) $file['extension']) ?: 'File') ?></dd>
      <dt class="col-5">MIME</dt><dd class="col-7"><?= e($file['mime_type']) ?></dd>
      <dt class="col-5">Size</dt><dd class="col-7"><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?></dd>
      <dt class="col-5">Folder</dt><dd class="col-7"><?= e($details['folder_name'] ?? 'My Storage') ?></dd>
      <dt class="col-5">Owner</dt><dd class="col-7"><?= e($details['owner']['display_name'] ?? '') ?></dd>
      <dt class="col-5">Created</dt><dd class="col-7"><?= e($file['created_at']) ?></dd>
      <dt class="col-5">Modified</dt><dd class="col-7"><?= e($file['updated_at'] ?? '') ?></dd>
      <dt class="col-5">Starred</dt><dd class="col-7"><?= !empty($file['is_starred']) ? 'Yes' : 'No' ?></dd>
      <dt class="col-5">Versions</dt><dd class="col-7"><?= e((string) count($versions)) ?></dd>
      <dt class="col-5">Share</dt><dd class="col-7"><?= $share ? 'Active link' : 'Not shared' ?></dd>
    </dl>
    <div class="mt-3 d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-vault" href="<?= e(vaultora_url('download.php?id=' . $fileId)) ?>">Download</a>
      <a class="btn btn-sm btn-outline-vault" href="<?= e(vaultora_url('preview.php?id=' . $fileId)) ?>">Preview</a>
      <button type="button" class="btn btn-sm btn-outline-vault" data-action="revision" data-id="<?= $fileId ?>">Upload revision</button>
    </div>
  </div>
  <div class="col-md-6">
    <h3 class="h6 text-secondary">Tags</h3>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php if (!$tags): ?>
        <span class="text-secondary small">No tags yet.</span>
      <?php endif; ?>
      <?php foreach ($tags as $tag): ?>
        <span class="tag-chip" style="background:<?= e($tag['color']) ?>22;color:<?= e($tag['color']) ?>">
          <?= e($tag['name']) ?>
          <button type="button" class="btn btn-sm p-0 border-0" data-tag-action="detach" data-file-id="<?= $fileId ?>" data-tag-id="<?= (int) $tag['id'] ?>" aria-label="Remove tag">&times;</button>
        </span>
      <?php endforeach; ?>
    </div>
    <div class="input-group input-group-sm mb-2">
      <select class="form-select" id="attachTagSelect">
        <option value="">Attach existing tag…</option>
        <?php foreach ($allTags as $tag): ?>
          <option value="<?= (int) $tag['id'] ?>"><?= e($tag['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline-vault" type="button" id="attachTagBtn" data-file-id="<?= $fileId ?>">Attach</button>
    </div>
    <div class="input-group input-group-sm">
      <input type="text" class="form-control" id="newTagName" placeholder="New tag name" maxlength="60">
      <input type="color" class="form-control form-control-color" id="newTagColor" value="#0d9488" title="Color">
      <button class="btn btn-vault" type="button" id="createTagBtn" data-file-id="<?= $fileId ?>">Add</button>
    </div>

    <h3 class="h6 text-secondary mt-4">Version history</h3>
    <div class="list-group list-group-flush">
      <?php foreach ($versions as $ver): ?>
        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
          <div>
            <strong>Version <?= (int) $ver['version_number'] ?></strong>
            <?= !empty($ver['is_current']) ? '<span class="badge text-bg-success ms-1">Current</span>' : '' ?>
            <div class="small text-secondary"><?= e(vaultora_format_bytes((int) $ver['size_bytes'])) ?> · <?= e($ver['created_at']) ?></div>
          </div>
          <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-vault" href="<?= e(vaultora_url('download.php?version_id=' . (int) $ver['id'])) ?>">Download</a>
            <?php if (empty($ver['is_current'])): ?>
              <button type="button" class="btn btn-outline-secondary" data-version-action="restore" data-version-id="<?= (int) $ver['id'] ?>">Restore</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const base = (document.body?.dataset?.base || '/').replace(/\/?$/, '/');
  const post = async (body) => {
    const res = await fetch(base + 'api/tags.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-Token':csrf},
      body: JSON.stringify(Object.assign({_csrf: csrf}, body))
    });
    return res.json();
  };
  document.getElementById('attachTagBtn')?.addEventListener('click', async () => {
    const tagId = Number(document.getElementById('attachTagSelect').value || 0);
    if (!tagId) return;
    const data = await post({action:'attach', file_id: <?= $fileId ?>, tag_id: tagId});
    if (data.html) document.getElementById('detailsBody').innerHTML = data.html;
  });
  document.getElementById('createTagBtn')?.addEventListener('click', async () => {
    const name = document.getElementById('newTagName').value.trim();
    const color = document.getElementById('newTagColor').value;
    if (!name) return;
    const data = await post({action:'create', file_id: <?= $fileId ?>, name, color});
    if (data.html) document.getElementById('detailsBody').innerHTML = data.html;
  });
})();
</script>
