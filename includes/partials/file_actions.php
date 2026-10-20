<?php
declare(strict_types=1);
/**
 * Expects: $itemType ('file'|'folder'), $item (row), optional flags
 */
$itemType = $itemType ?? 'file';
$item = $item ?? [];
$id = (int) ($item['id'] ?? 0);
$name = $itemType === 'file' ? ($item['original_name'] ?? '') : ($item['name'] ?? '');
$starred = !empty($item['is_starred']);
$showTrashActions = $showTrashActions ?? false;
?>
<div class="dropdown">
  <button class="btn btn-sm btn-outline-secondary item-actions-btn" type="button"
    data-bs-toggle="dropdown"
    data-bs-auto-close="true"
    data-bs-offset="0,6"
    data-bs-popper-config='{"placement":"bottom-start","strategy":"fixed","modifiers":[{"name":"preventOverflow","options":{"boundary":"viewport","padding":8,"altAxis":true}},{"name":"flip","options":{"fallbackPlacements":["bottom-end","top-start","right-start"]}}]}'
    aria-expanded="false"
    aria-label="Actions for <?= e($name) ?>">
    <i class="fa-solid fa-ellipsis"></i>
  </button>
  <ul class="dropdown-menu shadow">
    <?php if ($showTrashActions): ?>
      <li><button class="dropdown-item" type="button" data-action="restore" data-type="<?= e($itemType) ?>" data-id="<?= $id ?>" data-name="<?= e($name) ?>"><i class="fa-solid fa-rotate-left me-2"></i>Restore</button></li>
      <li><button class="dropdown-item text-danger" type="button" data-action="purge" data-type="<?= e($itemType) ?>" data-id="<?= $id ?>" data-name="<?= e($name) ?>"><i class="fa-solid fa-trash me-2"></i>Delete forever</button></li>
    <?php else: ?>
      <?php if ($itemType === 'file'): ?>
        <li><a class="dropdown-item" href="<?= e(vaultora_url('preview.php?id=' . $id)) ?>"><i class="fa-solid fa-eye me-2"></i>Preview</a></li>
        <li><a class="dropdown-item" href="<?= e(vaultora_url('download.php?id=' . $id)) ?>"><i class="fa-solid fa-download me-2"></i>Download</a></li>
        <li><button class="dropdown-item" type="button" data-action="details" data-id="<?= $id ?>"><i class="fa-solid fa-circle-info me-2"></i>Details / tags / versions</button></li>
        <li><button class="dropdown-item" type="button" data-action="revision" data-id="<?= $id ?>"><i class="fa-solid fa-code-branch me-2"></i>Upload revision</button></li>
      <?php else: ?>
        <li><a class="dropdown-item" href="<?= e(vaultora_url('files.php?folder=' . $id)) ?>"><i class="fa-solid fa-folder-open me-2"></i>Open</a></li>
      <?php endif; ?>
      <li><button class="dropdown-item" type="button" data-action="rename" data-type="<?= e($itemType) ?>" data-id="<?= $id ?>" data-name="<?= e($name) ?>"><i class="fa-solid fa-i-cursor me-2"></i>Rename</button></li>
      <li><button class="dropdown-item" type="button" data-action="move" data-type="<?= e($itemType) ?>" data-id="<?= $id ?>" data-name="<?= e($name) ?>"><i class="fa-solid fa-arrow-right-arrow-left me-2"></i>Move</button></li>
      <li><button class="dropdown-item" type="button" data-action="star" data-type="<?= e($itemType) ?>" data-id="<?= $id ?>" data-starred="<?= $starred ? '1' : '0' ?>"><i class="fa-solid fa-star me-2"></i><?= $starred ? 'Unstar' : 'Star' ?></button></li>
      <li><button class="dropdown-item" type="button" data-action="share" data-type="<?= e($itemType) ?>" data-id="<?= $id ?>" data-name="<?= e($name) ?>"><i class="fa-solid fa-share-nodes me-2"></i>Share</button></li>
      <li><hr class="dropdown-divider"></li>
      <li><button class="dropdown-item text-danger" type="button" data-action="delete" data-type="<?= e($itemType) ?>" data-id="<?= $id ?>" data-name="<?= e($name) ?>"><i class="fa-solid fa-trash me-2"></i>Delete</button></li>
    <?php endif; ?>
  </ul>
</div>
