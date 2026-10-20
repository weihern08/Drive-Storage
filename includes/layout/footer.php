<?php
declare(strict_types=1);
$folderIdForModals = $folderIdForModals ?? null;
?>
</main>
<footer class="app-footer">
    <span><?= e(vaultora_app_name()) ?> v<?= e(VAULTORA_VERSION) ?></span>
</footer>
</div>
</div>

<?php if (!($isAdminArea ?? false)): ?>
<!-- Upload modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="uploadModalLabel">Upload files</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="uploadForm" enctype="multipart/form-data">
            <?= vaultora_csrf_field() ?>
            <input type="hidden" name="folder_id" value="<?= e((string) ($folderIdForModals ?? '')) ?>">
            <div class="upload-dropzone" id="uploadDropzone">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p>Drag &amp; drop files here, or click to browse</p>
                <input type="file" name="files[]" id="uploadInput" multiple hidden>
            </div>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" value="1" id="asRevision" name="as_revision">
                <label class="form-check-label" for="asRevision">Upload as new version of selected file</label>
            </div>
            <input type="hidden" name="replace_file_id" id="replaceFileId" value="">
            <div id="uploadProgress" class="mt-3 d-none">
                <div class="progress"><div class="progress-bar" id="uploadBar" style="width:0%"></div></div>
                <div class="small mt-1" id="uploadStatus">Uploading…</div>
            </div>
            <ul class="upload-results list-unstyled mt-3" id="uploadResults"></ul>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-vault" id="startUploadBtn">Upload</button>
      </div>
    </div>
  </div>
</div>

<!-- New folder modal -->
<div class="modal fade" id="folderModal" tabindex="-1" aria-labelledby="folderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= e(vaultora_url('api/folders.php')) ?>" id="folderForm">
      <div class="modal-header">
        <h2 class="modal-title h5" id="folderModalLabel">New folder</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body vstack gap-3">
        <?= vaultora_csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="parent_id" value="<?= e((string) ($folderIdForModals ?? '')) ?>">
        <div>
            <label class="form-label" for="folderName">Folder name</label>
            <input class="form-control" id="folderName" name="name" required maxlength="180">
        </div>
        <div>
            <label class="form-label" for="folderColor">Color</label>
            <input class="form-control form-control-color" id="folderColor" name="color" type="color" value="#0d9488">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-vault">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Rename modal -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="renameForm">
      <div class="modal-header">
        <h2 class="modal-title h5">Rename</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= vaultora_csrf_field() ?>
        <input type="hidden" name="type" id="renameType">
        <input type="hidden" name="id" id="renameId">
        <label class="form-label" for="renameName">Name</label>
        <input class="form-control" id="renameName" name="name" required maxlength="180">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-vault">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Move modal -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="moveForm">
      <div class="modal-header">
        <h2 class="modal-title h5">Move item</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= vaultora_csrf_field() ?>
        <input type="hidden" name="type" id="moveType">
        <input type="hidden" name="id" id="moveId">
        <p class="mb-2" id="moveLabel"></p>
        <label class="form-label" for="moveDestination">Destination</label>
        <select class="form-select" id="moveDestination" name="destination_id"></select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-vault">Move</button>
      </div>
    </form>
  </div>
</div>

<!-- Share modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="shareForm">
      <div class="modal-header">
        <h2 class="modal-title h5">Share</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body vstack gap-3">
        <?= vaultora_csrf_field() ?>
        <input type="hidden" name="type" id="shareType">
        <input type="hidden" name="id" id="shareId">
        <div>
            <label class="form-label" for="shareExpiry">Expiration</label>
            <select class="form-select" id="shareExpiry" name="expiry">
                <option value="">Never</option>
                <option value="1">1 day</option>
                <option value="7">7 days</option>
                <option value="30">30 days</option>
                <option value="custom">Custom date</option>
            </select>
        </div>
        <div id="shareCustomWrap" class="d-none">
            <label class="form-label" for="shareCustomDate">Custom date</label>
            <input class="form-control" type="datetime-local" id="shareCustomDate" name="expires_at">
        </div>
        <div>
            <label class="form-label" for="sharePassword">Password (optional)</label>
            <input class="form-control" type="password" id="sharePassword" name="password" autocomplete="new-password">
        </div>
        <div id="shareResult" class="d-none">
            <label class="form-label">Share link</label>
            <div class="input-group">
                <input class="form-control" id="shareUrl" readonly>
                <button class="btn btn-outline-vault" type="button" id="copyShareBtn">Copy</button>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger d-none" id="disableShareBtn">Disable link</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-vault">Create link</button>
      </div>
    </form>
  </div>
</div>

<!-- Details / tags / versions modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5">File details</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="detailsBody">
        <div class="text-center py-4 text-secondary"><div class="spinner-border spinner-border-sm"></div> Loading…</div>
      </div>
    </div>
  </div>
</div>

<!-- Confirm modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="confirmTitle">Confirm</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmMessage"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmOkBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(vaultora_url('assets/js/app.js')) ?>"></script>
</body>
</html>
