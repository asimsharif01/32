<?php
// financing_types.php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }
// Optional: restrict to admins only
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) { header('Location: index.php'); exit; }

$page_title = 'Property Types';
$table_name = 'property_types';
$id_column = 'id';
$desc_column = 'description';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Larson &amp; Company</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">

</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center">
        <h5 class="bc-title"><?= $page_title ?></h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add <?= rtrim($page_title, 's') ?>
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
                            <th><?= ucfirst(rtrim($page_title, 's')) ?></th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = mysqli_query($conn, "SELECT * FROM $table_name ORDER BY $desc_column");
                        while ($row = mysqli_fetch_assoc($result)):
                        ?>
                        <tr>
                            <td>
                                <button class="btn btn-primary btn-sm btn-edit" data-id="<?= $row[$id_column] ?>"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $row[$id_column] ?>" data-name="<?= htmlspecialchars($row[$desc_column]) ?>"><i class="fas fa-trash"></i></button>
                            </td>
                            <td><?= $row[$id_column] ?></td>
                            <td><?= htmlspecialchars($row[$desc_column]) ?></td>
                            <td><?= date('M j, Y', strtotime($row['created_at'] ?? $row['created_at'] ?? 'now')) ?></td>
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
                <h5 class="modal-title">Add <?= rtrim($page_title, 's') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="lookups/<?= strtolower(str_replace(' ', '_', $page_title)) ?>/add.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= rtrim($page_title, 's') ?> Name <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    <?php if ($table_name === 'lead_sources'): ?>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="active" class="form-check-input" value="1" checked>
                        <label class="form-check-label">Active</label>
                    </div>
                    <?php endif; ?>
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
                <h5 class="modal-title">Edit <?= rtrim($page_title, 's') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="lookups/<?= strtolower(str_replace(' ', '_', $page_title)) ?>/edit.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= rtrim($page_title, 's') ?> Name <span class="text-danger">*</span></label>
                        <input type="text" name="description" id="edit_description" class="form-control" required>
                    </div>
                    <?php if ($table_name === 'lead_sources'): ?>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="active" id="edit_active" class="form-check-input" value="1">
                        <label class="form-check-label">Active</label>
                    </div>
                    <?php endif; ?>
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
                <h5 class="modal-title">Delete <?= rtrim($page_title, 's') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong id="delete_name"></strong>? This cannot be undone.</p>
                <input type="hidden" id="delete_id">
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
$(document).ready(function() {


    // EDIT button – use event delegation (works for all pages)
    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.ajax({
            url: 'lookups/<?= strtolower(str_replace(' ', '_', $page_title)) ?>/fetch_for_edit.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(data) {
                $('#edit_id').val(data.id);
                $('#edit_description').val(data.description);
                <?php if ($table_name === 'lead_sources'): ?>
                $('#edit_active').prop('checked', data.active == 1);
                <?php endif; ?>
                $('#editModal').modal('show');
            },
            error: function() {
                alert('Failed to load data for editing.');
            }
        });
    });

    // DELETE button – use event delegation
    $(document).on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#delete_id').val(id);
        $('#delete_name').text(name);
        $('#deleteModal').modal('show');
    });

    // Confirm delete
    $('#confirmDeleteBtn').click(function() {
        window.location.href = 'lookups/<?= strtolower(str_replace(' ', '_', $page_title)) ?>/delete.php?id=' + $('#delete_id').val();
    });
});
</script>
</body>
</html>