<?php
// lending_lookups_save.php
// Generic add / edit / delete / toggle-active handler for ALL 7 lending
// lookup tables. Table name comes from POST but is validated against a
// strict allowlist below — never interpolate $_POST['table'] directly.

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { http_response_code(401); exit('Not authenticated'); }
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) { http_response_code(403); exit('Not authorized'); }

require_once 'db.php';

// ── Allowlist: table => extra column definitions ──────────────────────────
// 'type' controls how the value is escaped/validated before INSERT/UPDATE.
$ALLOWED_TABLES = [
    'loan_consultants' => [
        'employment_start_date' => 'date',
    ],
    'loan_processors' => [],
    'loan_statuses' => [],
    'loan_types' => [],
    'purchase_types' => [],
    'loan_role_types' => [],
    'loan_referral_sources' => [
        'company' => 'text',
        'phone'   => 'text',
        'email'   => 'text',
    ],
];

// ── FK reference map — used to block deletes if the lookup is in use ──────
$FK_MAP = [
    'loan_consultants'      => 'loan_consultant_id',
    'loan_processors'       => 'loan_processor_id',
    'loan_statuses'         => 'status_id',
    'loan_types'            => 'loan_type_id',
    'purchase_types'        => 'purchase_type_id',
    'loan_role_types'       => 'loan_role_type_id',
    'loan_referral_sources' => 'referral_source_id',
];

$action = $_POST['action'] ?? '';
$table  = $_POST['table']  ?? '';

if (!array_key_exists($table, $ALLOWED_TABLES)) {
    http_response_code(400);
    exit('ERROR: Invalid table.');
}

$extraCols = $ALLOWED_TABLES[$table];

function esc($conn, $v) { return mysqli_real_escape_string($conn, $v); }

switch ($action) {

    case 'add': {
        $name   = trim($_POST['name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            header('Location: ' . $table . '.php?error=' . urlencode('Name is required.'));
            exit;
        }

        $cols = ['name', 'active'];
        $vals = ["'" . esc($conn, $name) . "'", $active];

        foreach ($extraCols as $col => $type) {
            $raw = trim($_POST[$col] ?? '');
            $cols[] = $col;
            $vals[] = $raw === '' ? 'NULL' : "'" . esc($conn, $raw) . "'";
        }

        $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
        if (!mysqli_query($conn, $sql)) {
            header('Location: ' . $table . '.php?error=' . urlencode('Database error: ' . mysqli_error($conn)));
            exit;
        }

        header('Location: ' . $table . '.php?success=' . urlencode(ucfirst(str_replace('_',' ',$table)) . ' added.'));
        exit;
    }

    case 'edit': {
        $id     = intval($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($id <= 0 || $name === '') {
            header('Location: ' . $table . '.php?error=' . urlencode('Invalid data.'));
            exit;
        }

        $sets = ["name = '" . esc($conn, $name) . "'", "active = $active"];

        foreach ($extraCols as $col => $type) {
            $raw = trim($_POST[$col] ?? '');
            $sets[] = "`$col` = " . ($raw === '' ? 'NULL' : "'" . esc($conn, $raw) . "'");
        }

        $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = $id";
        if (!mysqli_query($conn, $sql)) {
            header('Location: ' . $table . '.php?error=' . urlencode('Database error: ' . mysqli_error($conn)));
            exit;
        }

        header('Location: ' . $table . '.php?success=' . urlencode('Updated successfully.'));
        exit;
    }

    case 'toggle': {
        $id     = intval($_POST['id'] ?? 0);
        $active = intval($_POST['active'] ?? 0) ? 1 : 0;

        if ($id <= 0) { http_response_code(400); exit('ERROR: Invalid id.'); }

        mysqli_query($conn, "UPDATE `$table` SET active = $active WHERE id = $id");
        echo 'OK';
        exit;
    }

    case 'delete': {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { exit('ERROR: Invalid id.'); }

        // Safety check — block delete if any loan references this lookup
        $fkCol = $FK_MAP[$table] ?? null;
        if ($fkCol) {
            $check = mysqli_query($conn, "SELECT COUNT(*) c FROM loans WHERE `$fkCol` = $id");
            $row = mysqli_fetch_assoc($check);
            if (intval($row['c']) > 0) {
                echo 'ERROR: This item is used by ' . $row['c'] . ' loan(s) and cannot be deleted. Deactivate it instead.';
                exit;
            }
        }

        mysqli_query($conn, "DELETE FROM `$table` WHERE id = $id");
        echo 'OK';
        exit;
    }

    default:
        http_response_code(400);
        exit('ERROR: Invalid action.');
}
