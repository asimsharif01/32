<?php
// users.php — System Users Management
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Auth guard
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// Only super_admin and admin can manage users
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Location: index.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Users — Larson &amp; Company</title>
     <!-- FAVICONS ICON -->
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
  
    <style>
        body { background: #f0f2f5; }

        /* ── Avatar ────────────────────────────────────────── */
        .avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }
        .avatar-placeholder {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: #cbd5e1;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; color: #fff;
        }

        /* ── Avatar preview in modal ───────────────────────── */
        .avatar-preview {
            width: 90px; height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e2e8f0;
            display: block; margin: 0 auto 10px;
        }
        .avatar-wrap { text-align: center; margin-bottom: 8px; }

        /* ── Role / module badges ──────────────────────────── */
        .badge-role-super_admin { background:#7c3aed; }
        .badge-role-admin       { background:#2563eb; }
        .badge-role-basic_user  { background:#0891b2; }

        .badge-mod-real_estate  { background:#059669; }
        .badge-mod-mortgage     { background:#d97706; }
        .badge-mod-both         { background:#6d28d9; }

        /* ── Password strength ─────────────────────────────── */
        .strength-bar { height: 4px; border-radius: 2px; transition: all .3s; margin-top: 4px; }
        .strength-weak   { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #22c55e; width: 100%; }
        .strength-label  { font-size: .73rem; color: #6c757d; margin-top: 2px; }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center">
        <h5 class="bc-title">System Users</h5>
        <button class="btn btn-primary btn-sm"
            data-bs-toggle="modal" data-bs-target="#userModal"
            onclick="openAddModal()">
            <i class="fas fa-plus me-1"></i> Add User
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
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Module</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $res = mysqli_query($conn, "SELECT * FROM users ORDER BY name");
                    while ($u = mysqli_fetch_assoc($res)):
                        $initials = strtoupper(substr($u['name'], 0, 1));
                        $role_labels = [
                            'super_admin' => 'Super Admin',
                            'admin'       => 'Admin',
                            'basic_user'  => 'Basic User',
                        ];
                        $mod_labels = [
                            'real_estate' => 'Real Estate',
                            'mortgage'    => 'Mortgage',
                            'both'        => 'Both',
                        ];
                    ?>
                    <tr>
                        <td>
                            <button class="btn btn-primary btn-sm"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($u['id'] != $_SESSION['id']): // prevent self-delete ?>
                            <button class="btn btn-danger btn-sm ms-1"
                                onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($u['profile_image']) && file_exists('uploads/users/' . $u['profile_image'])): ?>
                                    <img src="uploads/users/<?= htmlspecialchars($u['profile_image']) ?>"
                                         class="avatar" alt="">
                                <?php else: ?>
                                    <div class="avatar-placeholder"><?= $initials ?></div>
                                <?php endif; ?>
                                <span class="fw-semibold"><?= htmlspecialchars($u['name']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge badge-role-<?= $u['role'] ?>">
                                <?= $role_labels[$u['role']] ?? $u['role'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-mod-<?= $u['module'] ?>">
                                <?= $mod_labels[$u['module']] ?? $u['module'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- ══ ADD / EDIT USER MODAL ════════════════════════════════════════════ -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="userForm" action="users/save_user.php" method="POST"
                  enctype="multipart/form-data">
                <input type="hidden" name="user_id" id="user_id" value="0">

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Avatar preview + upload -->
                        <div class="col-12">
                            <div class="avatar-wrap">
                                <img id="avatarPreview"
                                     src="images/default_avatar.png"
                                     class="avatar-preview" alt="Profile">
                                <label class="btn btn-outline-secondary btn-sm mt-1">
                                    <i class="fas fa-camera me-1"></i> Change Photo
                                    <input type="file" name="profile_image" id="profileImageInput"
                                           accept="image/*" class="d-none">
                                </label>
                                <!-- Hidden field carries existing filename on edit -->
                                <input type="hidden" name="existing_image" id="existing_image">
                            </div>
                        </div>

                        <!-- Name -->
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f_name"
                                   class="form-control" required>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="f_email"
                                   class="form-control" required>
                        </div>

                        <!-- Password -->
                        <div class="col-md-6">
                            <label class="form-label" id="passLabel">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" name="password" id="f_password"
                                       class="form-control" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="togglePass('f_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="strength-bar" id="strengthBar"></div>
                            <div class="strength-label" id="strengthLabel"></div>
                            <small class="text-muted" id="passHint"></small>
                        </div>

                        <!-- Confirm password -->
                        <div class="col-md-6">
                            <label class="form-label" id="confirmLabel">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="f_confirm"
                                       class="form-control" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="togglePass('f_confirm', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="text-danger" style="font-size:.78rem" id="matchMsg"></div>
                        </div>

                        <!-- Role -->
                        <div class="col-md-4">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="f_role" class="form-select" required>
                                <option value="">Select role</option>
                                <option value="super_admin">Super Admin</option>
                                <option value="admin">Admin</option>
                                <option value="basic_user">Basic User</option>
                            </select>
                        </div>

                        <!-- Module -->
                        <div class="col-md-4">
                            <label class="form-label">Module Access <span class="text-danger">*</span></label>
                            <select name="module" id="f_module" class="form-select" required>
                                <option value="">Select module</option>
                                <option value="real_estate">Real Estate</option>
                                <option value="mortgage">Mortgage</option>
                                <option value="both">Both</option>
                            </select>
                        </div>

                        <!-- Active -->
                        <div class="col-md-4 d-flex align-items-end pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       name="active" id="f_active" value="1" checked>
                                <label class="form-check-label" for="f_active">Active</label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══ DELETE CONFIRMATION MODAL ════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Delete <strong id="deleteNameLabel"></strong>? This cannot be undone.
                <input type="hidden" id="deleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm"
                        id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
$(document).ready(function () {
    $('#usersTable').DataTable({ pageLength: 25, order: [[0, 'asc']] });
});

// ── Open Add modal (blank form) ──────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent  = 'Add User';
    document.getElementById('submitBtn').textContent   = 'Save User';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value           = '0';
    document.getElementById('existing_image').value    = '';
    document.getElementById('avatarPreview').src       = 'images/default_avatar.png';
    document.getElementById('strengthBar').className   = 'strength-bar';
    document.getElementById('strengthLabel').textContent = '';
    document.getElementById('matchMsg').textContent    = '';
    document.getElementById('passHint').textContent    = '';

    // Password required for new users
    document.getElementById('f_password').required = true;
    document.getElementById('f_confirm').required  = true;
    document.getElementById('passLabel').innerHTML =
        'Password <span class="text-danger">*</span>';
    document.getElementById('confirmLabel').innerHTML =
        'Confirm Password <span class="text-danger">*</span>';
}

// ── Open Edit modal (pre-fill from row data) ─────────────────────────────
function openEditModal(u) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('submitBtn').textContent  = 'Update User';
    document.getElementById('user_id').value   = u.id;
    document.getElementById('f_name').value    = u.name;
    document.getElementById('f_email').value   = u.email;
    document.getElementById('f_role').value    = u.role;
    document.getElementById('f_module').value  = u.module;
    document.getElementById('f_active').checked = (u.active == 1);
    document.getElementById('existing_image').value = u.profile_image ?? '';
    document.getElementById('f_password').value = '';
    document.getElementById('f_confirm').value  = '';
    document.getElementById('matchMsg').textContent = '';
    document.getElementById('strengthBar').className = 'strength-bar';
    document.getElementById('strengthLabel').textContent = '';

    // Password optional on edit
    document.getElementById('f_password').required = false;
    document.getElementById('f_confirm').required  = false;
    document.getElementById('passLabel').innerHTML =
        'New Password <small class="text-muted fw-normal">(leave blank to keep)</small>';
    document.getElementById('confirmLabel').innerHTML =
        'Confirm New Password';

    // Show existing avatar or default
    if (u.profile_image) {
        document.getElementById('avatarPreview').src =
            'uploads/users/' + u.profile_image + '?v=' + Date.now();
    } else {
        document.getElementById('avatarPreview').src = 'images/default_avatar.png';
    }

    new bootstrap.Modal(document.getElementById('userModal')).show();
}

// ── Profile image preview ────────────────────────────────────────────────
document.getElementById('profileImageInput').addEventListener('change', function () {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// ── Password strength meter ──────────────────────────────────────────────
document.getElementById('f_password').addEventListener('input', function () {
    const val = this.value;
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');

    if (!val) {
        bar.className = 'strength-bar';
        lbl.textContent = '';
        return;
    }
    let score = 0;
    if (val.length >= 8)                   score++;
    if (/[A-Z]/.test(val))                 score++;
    if (/[0-9]/.test(val))                 score++;
    if (/[^A-Za-z0-9]/.test(val))          score++;

    if (score <= 1) {
        bar.className = 'strength-bar strength-weak';
        lbl.textContent = 'Weak';
    } else if (score <= 3) {
        bar.className = 'strength-bar strength-medium';
        lbl.textContent = 'Medium';
    } else {
        bar.className = 'strength-bar strength-strong';
        lbl.textContent = 'Strong';
    }

    checkMatch();
});

// ── Password match check ─────────────────────────────────────────────────
document.getElementById('f_confirm').addEventListener('input', checkMatch);
function checkMatch() {
    const p = document.getElementById('f_password').value;
    const c = document.getElementById('f_confirm').value;
    const msg = document.getElementById('matchMsg');
    if (!c) { msg.textContent = ''; return; }
    msg.textContent = (p === c) ? '' : '⚠ Passwords do not match';
}

// ── Form submit guard ────────────────────────────────────────────────────
document.getElementById('userForm').addEventListener('submit', function (e) {
    const p = document.getElementById('f_password').value;
    const c = document.getElementById('f_confirm').value;

    // If a password is entered, confirm must match
    if (p && p !== c) {
        e.preventDefault();
        document.getElementById('matchMsg').textContent = '⚠ Passwords do not match';
        document.getElementById('f_confirm').focus();
        return;
    }
});

// ── Show/hide password ───────────────────────────────────────────────────
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// ── Delete ───────────────────────────────────────────────────────────────
function confirmDelete(id, name) {
    document.getElementById('deleteId').value         = id;
    document.getElementById('deleteNameLabel').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    const id = document.getElementById('deleteId').value;
    window.location.href = 'users/delete_user.php?id=' + id;
});
</script>
</body>
</html>