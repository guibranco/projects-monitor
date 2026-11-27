<?php
$pageTitle = "Applications";
$pageIcon  = "bi-app-indicator";
require_once "partials/standalone-head.php";
?>

   <div class="dashboard-layout">
      <div class="dashboard-container">

         <div class="full-width-section">
            <div class="section-header d-flex align-items-center">
               <span><i class="bi bi-app-indicator me-2"></i>Applications <span id="counter_applications"
                  class="badge rounded-pill"></span></span>
               <button type="button" id="app_new" class="btn btn-sm btn-nav-action ms-auto">
                  <i class="bi bi-plus-lg"></i><span class="ms-1">New application</span>
               </button>
            </div>
            <div class="section-content">
               <p class="apps-hint">
                  Applications send messages to <code>api/v1/log-message</code> with the
                  <code>X-API-KEY</code> and <code>X-API-TOKEN</code> headers. Keys follow
                  <code>pmk-{acronym}-key</code>; a token is only shown when it is created or rotated.
               </p>
               <div class="table-responsive">
                  <table class="table table-dark table-hover align-middle apps-table mb-0">
                     <thead>
                        <tr>
                           <th scope="col">Name</th>
                           <th scope="col">Key</th>
                           <th scope="col">Token</th>
                           <th scope="col">Last seen</th>
                           <th scope="col">Updated</th>
                           <th scope="col">Created</th>
                           <th scope="col" class="text-end">Actions</th>
                        </tr>
                     </thead>
                     <tbody id="applications_table">
                        <tr><td colspan="7" class="text-center apps-empty">Loading…</td></tr>
                     </tbody>
                  </table>
               </div>
            </div>
         </div>

      </div>
   </div>

   <div class="modal fade" id="app_form_modal" tabindex="-1" aria-labelledby="app_form_title" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
         <form class="modal-content apps-modal" id="app_form" novalidate>
            <div class="modal-header">
               <h5 class="modal-title" id="app_form_title">New application</h5>
               <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
               <label for="app_form_name" class="form-label">Name</label>
               <input type="text" class="form-control" id="app_form_name" maxlength="255" required autocomplete="off">
               <div class="form-text apps-form-text" id="app_form_preview"></div>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancel</button>
               <button type="submit" class="btn btn-primary btn-sm" id="app_form_submit">Create</button>
            </div>
         </form>
      </div>
   </div>

   <div class="modal fade" id="app_confirm_modal" tabindex="-1" aria-labelledby="app_confirm_title" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
         <div class="modal-content apps-modal">
            <div class="modal-header">
               <h5 class="modal-title" id="app_confirm_title">Confirm</h5>
               <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="app_confirm_body"></div>
            <div class="modal-footer">
               <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancel</button>
               <button type="button" class="btn btn-danger btn-sm" id="app_confirm_ok">Rotate</button>
            </div>
         </div>
      </div>
   </div>

   <div class="modal fade" id="app_secret_modal" tabindex="-1" aria-labelledby="app_secret_title" aria-hidden="true"
      data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered modal-lg">
         <div class="modal-content apps-modal">
            <div class="modal-header">
               <h5 class="modal-title" id="app_secret_title">Credentials</h5>
            </div>
            <div class="modal-body">
               <p class="apps-warning"><i class="bi bi-exclamation-triangle me-1"></i>
                  Copy the token now: it will not be shown again. Rotate it if it is lost.</p>
               <label class="form-label" for="app_secret_key">X-API-KEY</label>
               <div class="input-group mb-3">
                  <input type="text" class="form-control font-monospace" id="app_secret_key" readonly>
                  <button class="btn btn-outline-light" type="button" data-copy="app_secret_key" title="Copy"><i class="bi bi-clipboard"></i></button>
               </div>
               <label class="form-label" for="app_secret_token">X-API-TOKEN</label>
               <div class="input-group">
                  <input type="text" class="form-control font-monospace" id="app_secret_token" readonly>
                  <button class="btn btn-outline-light" type="button" data-copy="app_secret_token" title="Copy"><i class="bi bi-clipboard"></i></button>
               </div>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">I have copied it</button>
            </div>
         </div>
      </div>
   </div>

   <?php require_once __DIR__.'/footer.php'; ?>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha256-CDOy6cOibCWEdsRiZuaHf8dSGGJRYuBGC+mjoJimHGw=" crossorigin="anonymous"></script>
   <script type="module" src="static/applications.js?<?php echo filemtime("static/applications.js"); ?>"></script>
</body>

</html>
