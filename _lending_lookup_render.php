<?php
// _lending_lookup_render.php
// Shared CRUD UI for all Lending System Listings pages.
// Each thin page (loan_consultants.php, loan_processors.php, etc.) sets
// the config variables below, then includes this file.
//
// Required variables (set by the including page BEFORE require):
//   $table_name   string   real table name, e.g. 'loan_consultants'
//   $page_title   string   plural display name, e.g. 'Loan Consultants'
//   $singular     string   singular display name, e.g. 'Loan Consultant'
//   $extra_fields array    optional extra columns beyond name/active, e.g.:
//                          [['column'=>'employment_start_date','label'=>'Start Date','type'=>'date']]
//
// All lookups use the SAME backend handlers:
//   lending_lookups_save.php   (add / edit / delete / toggle-active)
//   lending_lookups_fetch.php  (fetch one row for editing, JSON)
// Both validate $table_name against a strict allowlist — see those files.

if (!isset($table_name) || !isset($page_title)) {
    die('Lookup page misconfigured: $table_name and $page_title are required.');
}
$singular     = $singular ?? rtrim($page_title, 's');
$extra_fields = $extra_fields ?? [];

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) { header('Location: index.php'); exit; }

require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Laser Lending</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center">
        <h5 class="bc-title"><?= htmlspecialchars($page_title) ?></h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add <?= htmlspecialchars($singular) ?>
        </button>
    </div>

    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="container-fluid">
        <div class="card p-3">
            <div class="table-responsive">
                <table id="example" class="display table table-hover table-striped" style="min-width:845px">
                    <thead>
                        <tr>
                            <th>Actions</th>
                            <th>ID</th>
                            <th><?= htmlspecialchars($singular) ?></th>
                            <?php foreach ($extra_fields as $ef): ?>
                                <th><?= htmlspecialchars($ef['label']) ?></th>
                            <?php endforeach; ?>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $cols = 'id, name, active, created_at' . ($extra_fields ? ', ' . implode(',', array_column($extra_fields,'column')) : '');
                    $result = mysqli_query($conn, "SELECT $cols FROM `$table_name` ORDER BY sort_order, name");
                    while ($row = mysqli_fetch_assoc($result)):
                    ?>
                        <tr>
                            <td>
                                <button class="btn btn-primary btn-sm btn-edit" data-id="<?= $row['id'] ?>"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $row['id'] ?>" data-name="<?= htmlspecialchars($row['name']) ?>"><i class="fas fa-trash"></i></button>
                            </td>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <?php foreach ($extra_fields as $ef):
                                $val = $row[$ef['column']] ?? '';
                                if ($ef['type'] === 'date' && $val) $val = date('M j, Y', strtotime($val));
                            ?>
                                <td><?= htmlspecialchars($val) ?></td>
                            <?php endforeach; ?>
                            <td>
                                <button class="btn btn-sm btn-toggle-active <?= $row['active'] ? 'btn-success' : 'btn-secondary' ?>"
                                        data-id="<?= $row['id'] ?>" data-active="<?= $row['active'] ?>">
                                    <?= $row['active'] ? 'Active' : 'Inactive' ?>
                                </button>
                            </td>
                            <td><?= date('M j, Y', strtotime($row['created_at'] ?? 'now')) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add <?= htmlspecialchars($singular) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="lending_lookups_save.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="table" value="<?= htmlspecialchars($table_name) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($singular) ?> Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <?php foreach ($extra_fields as $ef): ?>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($ef['label']) ?></label>
                        <input type="<?= $ef['type'] ?>" name="<?= $ef['column'] ?>" class="form-control">
                    </div>
                    <?php endforeach; ?>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="active" class="form-check-input" value="1" checked>
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit <?= htmlspecialchars($singular) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="lending_lookups_save.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="table" value="<?= htmlspecialchars($table_name) ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($singular) ?> Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <?php foreach ($extra_fields as $ef): ?>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($ef['label']) ?></label>
                        <input type="<?= $ef['type'] ?>" name="<?= $ef['column'] ?>" id="edit_<?= $ef['column'] ?>" class="form-control">
                    </div>
                    <?php endforeach; ?>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="active" id="edit_active" class="form-check-input" value="1">
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete <?= htmlspecialchars($singular) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong id="delete_name"></strong>? This cannot be undone.</p>
                <p class="text-muted small">If this item is used by any existing loans, deletion will be blocked — deactivate it instead.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
const LOOKUP_TABLE = <?= json_encode($table_name) ?>;
const EXTRA_FIELDS = <?= json_encode(array_column($extra_fields, 'column')) ?>;

$(document).ready(function() {

    // EDIT button
    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.ajax({
            url: 'lending_lookups_fetch.php',
            type: 'GET',
            data: { table: LOOKUP_TABLE, id: id },
            dataType: 'json',
            success: function(data) {
                $('#edit_id').val(data.id);
                $('#edit_name').val(data.name);
                $('#edit_active').prop('checked', data.active == 1);
                EXTRA_FIELDS.forEach(function(col) {
                    $('#edit_' + col).val(data[col] ?? '');
                });
                $('#editModal').modal('show');
            },
            error: function() {
                alert('Failed to load data for editing.');
            }
        });
    });

    // TOGGLE ACTIVE — single click, no modal needed
    $(document).on('click', '.btn-toggle-active', function() {
        var btn = $(this);
        var id = btn.data('id');
        var newActive = btn.data('active') == 1 ? 0 : 1;
        $.ajax({
            url: 'lending_lookups_save.php',
            type: 'POST',
            data: { action: 'toggle', table: LOOKUP_TABLE, id: id, active: newActive },
            success: function() { location.reload(); },
            error: function() { alert('Failed to update status.'); }
        });
    });

    // DELETE button
    var deleteId = null;
    $(document).on('click', '.btn-delete', function() {
        deleteId = $(this).data('id');
        $('#delete_name').text($(this).data('name'));
        $('#deleteModal').modal('show');
    });

    $('#confirmDeleteBtn').on('click', function() {
        $.ajax({
            url: 'lending_lookups_save.php',
            type: 'POST',
            data: { action: 'delete', table: LOOKUP_TABLE, id: deleteId },
            success: function(resp) {
                if (resp.indexOf('ERROR') === 0) {
                    alert(resp.replace('ERROR: ', ''));
                } else {
                    location.reload();
                }
            },
            error: function() { alert('Failed to delete.'); }
        });
    });

});
</script>
</body>
</html>
