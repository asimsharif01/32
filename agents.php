<?php
// agents.php — List, add, edit, delete agents
require_once 'db.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents — Larson &amp; Company</title>
     <!-- FAVICONS ICON -->
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center">
        <h5 class="bc-title">Agents</h5>
        <button type="button" class="btn btn-primary btn-sm"
            data-bs-toggle="modal" data-bs-target="#addAgentModal">
            + Add Agent
        </button>
    </div>

    <div class="container-fluid">
        <div class="card p-3">
            <div class="table-responsive">
                <table id="example" class="display table table-hover table-striped" style="min-width:845px">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Office Phone</th>
                            <th>Cell Phone</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = mysqli_query($conn, "SELECT * FROM agents ORDER BY name");
                        while ($agent = mysqli_fetch_assoc($result)):
                            $roles = [];
                            if ($agent['is_loan_officer'])  $roles[] = 'LO';
                            if ($agent['is_buyer_escrow'])  $roles[] = 'BEO';
                            if ($agent['is_seller_escrow']) $roles[] = 'SEO';
                            if ($agent['is_listing_agent']) $roles[] = 'LA';
                            if ($agent['is_selling_agent']) $roles[] = 'SA';
                        ?>
                        <tr>
                            <td>
                                <button class="btn btn-primary btn-sm btn-edit"
                                    data-id="<?= $agent['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-delete"
                                    data-id="<?= $agent['id'] ?>"
                                    data-name="<?= htmlspecialchars($agent['name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <td><?= htmlspecialchars($agent['name']) ?></td>
                            <td><?= htmlspecialchars($agent['company'] ?? '') ?></td>
                            <td><?= htmlspecialchars($agent['office_phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($agent['cell_phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($agent['email'] ?? '') ?></td>
                            <td><?= implode(', ', $roles) ?: '—' ?></td>
                            <td>
                                <?= $agent['active']
                                    ? '<span class="badge bg-success">Active</span>'
                                    : '<span class="badge bg-secondary">Inactive</span>' ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- ══ Shared agent form fields macro ════════════════════════════════════ -->
<!-- Used inside both Add and Edit modals via include trick — we just      -->
<!-- build each modal separately to keep id prefixes clean.                -->


<!-- ══ ADD AGENT MODAL ══════════════════════════════════════════════════ -->
<div class="modal fade" id="addAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="agents/add.php" method="POST">
                <div class="modal-body">
                    <?php include('agents/_form_fields.php'); /* prefix="" */ ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══ EDIT AGENT MODAL ═════════════════════════════════════════════════ -->
<div class="modal fade" id="editAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="agents/edit.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body" id="editModalBody">
                    <!-- Populated by AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══ DELETE CONFIRMATION MODAL ════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete
                    <strong id="delete_name_label"></strong>?
                </p>
                <div id="delete_warning" class="alert alert-warning d-none">
                    ⚠️ This agent is referenced in existing listings. Deleting will not remove
                    those listing records but the agent name will remain stored there.
                </div>
                <input type="hidden" id="delete_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
$(document).ready(function () {

  

    // ── Edit button — load form HTML via AJAX ────────────────────────
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        $('#edit_id').val(id);

        $.ajax({
            url: 'agents/fetch_for_edit.php',
            type: 'GET',
            data: { id: id },
            dataType: 'html',
            success: function (html) {
                $('#editModalBody').html(html);
                $('#editAgentModal').modal('show');
            },
            error: function () {
                alert('Failed to load agent data.');
            }
        });
    });

    // ── Delete button ────────────────────────────────────────────────
    $(document).on('click', '.btn-delete', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#delete_id').val(id);
        $('#delete_name_label').text(name);
        $('#delete_warning').addClass('d-none');

        // Check if agent is used in any listing
        $.ajax({
            url: 'agents/check_in_use.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function (res) {
                if (res.in_use) {
                    $('#delete_warning').removeClass('d-none');
                }
            }
        });

        $('#deleteModal').modal('show');
    });

    $('#confirmDelete').click(function () {
        window.location.href = 'agents/delete.php?id=' + $('#delete_id').val();
    });

});
</script>
</body>
</html>