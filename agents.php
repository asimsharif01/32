<?php
require_once 'db.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents - Larson & Company</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles">
        <h5 class="bc-title">Agents</h5>
        <button type="button" class="btn btn-primary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#addAgentModal">+ Add Agent</button>
    </div>
    <div class="container-fluid">
        <div class="card p-3">
            <div class="table-responsive">
                <table id="agentsTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Office Phone</th>
                            <th>Cell Phone</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM agents ORDER BY name";
                        $result = mysqli_query($conn, $sql);
                        while($agent = mysqli_fetch_assoc($result)):
                            $roles = [];
                            if ($agent['is_loan_officer']) $roles[] = 'LO';
                            if ($agent['is_buyer_escrow']) $roles[] = 'BEO';
                            if ($agent['is_seller_escrow']) $roles[] = 'SEO';
                            if ($agent['is_listing_agent']) $roles[] = 'LA';
                            if ($agent['is_selling_agent']) $roles[] = 'SA';
                            $role_str = implode(', ', $roles);
                        ?>
                        <tr>
                            <td>
                                <button class="btn btn-primary btn-sm btn-edit" data-id="<?= $agent['id'] ?>"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $agent['id'] ?>" data-name="<?= htmlspecialchars($agent['name']) ?>"><i class="fas fa-trash"></i></button>
                            </td>
                            <td><?= htmlspecialchars($agent['name']) ?></td>
                            <td><?= htmlspecialchars($agent['company']) ?></td>
                            <td><?= htmlspecialchars($agent['office_phone']) ?></td>
                            <td><?= htmlspecialchars($agent['cell_phone']) ?></td>
                            <td><?= htmlspecialchars($agent['email']) ?></td>
                            <td><?= $role_str ?></td>
                            <td><?= $agent['active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Agent Modal -->
<div class="modal fade" id="addAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="agents/add.php" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company</label>
                            <input type="text" name="company" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address 1</label>
                            <input type="text" name="address1" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address 2</label>
                            <input type="text" name="address2" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="UT">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Zip</label>
                            <input type="text" name="zip" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Office Phone</label>
                            <input type="text" name="office_phone" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cell Phone</label>
                            <input type="text" name="cell_phone" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Fax</label>
                            <input type="text" name="fax" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assistant Name</label>
                            <input type="text" name="asst_name" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Asst Office Phone</label>
                            <input type="text" name="asst_office_phone" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Asst Fax</label>
                            <input type="text" name="asst_fax" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Asst Email</label>
                            <input type="email" name="asst_email" class="form-control">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Roles</label>
                            <div class="row">
                                <div class="col-md-3"><input type="checkbox" name="is_loan_officer" value="1"> Loan Officer</div>
                                <div class="col-md-3"><input type="checkbox" name="is_buyer_escrow" value="1"> Buyer Escrow</div>
                                <div class="col-md-3"><input type="checkbox" name="is_seller_escrow" value="1"> Seller Escrow</div>
                                <div class="col-md-3"><input type="checkbox" name="is_listing_agent" value="1"> Listing Agent</div>
                                <div class="col-md-3"><input type="checkbox" name="is_selling_agent" value="1"> Selling Agent</div>
                                <div class="col-md-3"><input type="checkbox" name="include_in_reports" value="1" checked> Include in Reports</div>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="active" class="form-check-input" value="1" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Agent Modal (populated via AJAX) -->
<div class="modal fade" id="editAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="agents/edit.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Name *</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Company</label><input type="text" name="company" id="edit_company" class="form-control"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Address 1</label><input type="text" name="address1" id="edit_address1" class="form-control"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Address 2</label><input type="text" name="address2" id="edit_address2" class="form-control"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">City</label><input type="text" name="city" id="edit_city" class="form-control"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">State</label><input type="text" name="state" id="edit_state" class="form-control"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Zip</label><input type="text" name="zip" id="edit_zip" class="form-control"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Office Phone</label><input type="text" name="office_phone" id="edit_office_phone" class="form-control"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Cell Phone</label><input type="text" name="cell_phone" id="edit_cell_phone" class="form-control"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Fax</label><input type="text" name="fax" id="edit_fax" class="form-control"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Assistant Name</label><input type="text" name="asst_name" id="edit_asst_name" class="form-control"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Asst Office Phone</label><input type="text" name="asst_office_phone" id="edit_asst_office_phone" class="form-control"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Asst Fax</label><input type="text" name="asst_fax" id="edit_asst_fax" class="form-control"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Asst Email</label><input type="email" name="asst_email" id="edit_asst_email" class="form-control"></div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Roles</label>
                            <div class="row">
                                <div class="col-md-3"><input type="checkbox" name="is_loan_officer" id="edit_is_loan_officer" value="1"> Loan Officer</div>
                                <div class="col-md-3"><input type="checkbox" name="is_buyer_escrow" id="edit_is_buyer_escrow" value="1"> Buyer Escrow</div>
                                <div class="col-md-3"><input type="checkbox" name="is_seller_escrow" id="edit_is_seller_escrow" value="1"> Seller Escrow</div>
                                <div class="col-md-3"><input type="checkbox" name="is_listing_agent" id="edit_is_listing_agent" value="1"> Listing Agent</div>
                                <div class="col-md-3"><input type="checkbox" name="is_selling_agent" id="edit_is_selling_agent" value="1"> Selling Agent</div>
                                <div class="col-md-3"><input type="checkbox" name="include_in_reports" id="edit_include_in_reports" value="1"> Include in Reports</div>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="active" id="edit_active" class="form-check-input" value="1">
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this agent?</p>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#agentsTable').DataTable();

    // Edit button
    $('.btn-edit').click(function() {
        let id = $(this).data('id');
        $.ajax({
            url: 'agents/fetch_for_edit.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(data) {
                $('#edit_id').val(data.id);
                $('#edit_name').val(data.name);
                $('#edit_company').val(data.company);
                $('#edit_address1').val(data.address1);
                $('#edit_address2').val(data.address2);
                $('#edit_city').val(data.city);
                $('#edit_state').val(data.state);
                $('#edit_zip').val(data.zip);
                $('#edit_office_phone').val(data.office_phone);
                $('#edit_cell_phone').val(data.cell_phone);
                $('#edit_fax').val(data.fax);
                $('#edit_email').val(data.email);
                $('#edit_asst_name').val(data.asst_name);
                $('#edit_asst_office_phone').val(data.asst_office_phone);
                $('#edit_asst_fax').val(data.asst_fax);
                $('#edit_asst_email').val(data.asst_email);
                $('#edit_is_loan_officer').prop('checked', data.is_loan_officer == 1);
                $('#edit_is_buyer_escrow').prop('checked', data.is_buyer_escrow == 1);
                $('#edit_is_seller_escrow').prop('checked', data.is_seller_escrow == 1);
                $('#edit_is_listing_agent').prop('checked', data.is_listing_agent == 1);
                $('#edit_is_selling_agent').prop('checked', data.is_selling_agent == 1);
                $('#edit_include_in_reports').prop('checked', data.include_in_reports == 1);
                $('#edit_active').prop('checked', data.active == 1);
                $('#editAgentModal').modal('show');
            }
        });
    });

    // Delete button
    $('.btn-delete').click(function() {
        let id = $(this).data('id');
        $('#delete_id').val(id);
        $('#deleteModal').modal('show');
    });

    $('#confirmDelete').click(function() {
        let id = $('#delete_id').val();
        window.location.href = 'agents/delete.php?id=' + id;
    });
});
</script>
</body>
</html>