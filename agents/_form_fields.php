<?php
// agents/_form_fields.php
// Reusable form fields for adding/editing an agent
// For edit mode, $agent_data is an associative array with existing values.
// For add mode, $agent_data is empty.
if (!isset($agent_data)) $agent_data = [];
function val($field, $default = '') {
    global $agent_data;
    return htmlspecialchars($agent_data[$field] ?? $default);
}
?>
<div class="row">
    <div class="col-md-6 mb-1">
        <label class="form-label">Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= val('name') ?>">
    </div>
    <div class="col-md-6 mb-1">
        <label class="form-label">Company</label>
        <input type="text" name="company" class="form-control" value="<?= val('company') ?>">
    </div>
    <div class="col-md-6 mb-1">
        <label class="form-label">Address 1</label>
        <input type="text" name="address1" class="form-control" value="<?= val('address1') ?>">
    </div>
    <div class="col-md-6 mb-1">
        <label class="form-label">Address 2</label>
        <input type="text" name="address2" class="form-control" value="<?= val('address2') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">City</label>
        <input type="text" name="city" class="form-control" value="<?= val('city') ?>">
    </div>
    <div class="col-md-2 mb-1">
        <label class="form-label">State</label>
        <input type="text" name="state" class="form-control" value="<?= val('state', 'UT') ?>">
    </div>
    <div class="col-md-2 mb-1">
        <label class="form-label">Zip</label>
        <input type="text" name="zip" class="form-control" value="<?= val('zip') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Office Phone</label>
        <input type="text" name="office_phone" class="form-control" value="<?= val('office_phone') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Cell Phone</label>
        <input type="text" name="cell_phone" class="form-control" value="<?= val('cell_phone') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Cell Phone 2</label>
        <input type="text" name="cell_phone2" class="form-control" value="<?= val('cell_phone2') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Fax</label>
        <input type="text" name="fax" class="form-control" value="<?= val('fax') ?>">
    </div>
    <div class="col-md-6 mb-1">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= val('email') ?>">
    </div>

    <!-- Assistant 1 -->
    <div class="col-md-6 mb-1">
        <label class="form-label">Assistant Name 1</label>
        <input type="text" name="asst_name" class="form-control" value="<?= val('asst_name') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Asst Office Phone 1</label>
        <input type="text" name="asst_office_phone" class="form-control" value="<?= val('asst_office_phone') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Asst Cell Phone 1</label>
        <input type="text" name="asst_cell_phone1" class="form-control" value="<?= val('asst_cell_phone1') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Asst Fax 1</label>
        <input type="text" name="asst_fax" class="form-control" value="<?= val('asst_fax') ?>">
    </div>
    <div class="col-md-6 mb-1">
        <label class="form-label">Asst Email 1</label>
        <input type="email" name="asst_email" class="form-control" value="<?= val('asst_email') ?>">
    </div>

    <!-- Assistant 2 -->
    <div class="col-md-6 mb-1">
        <label class="form-label">Assistant Name 2</label>
        <input type="text" name="asst_name2" class="form-control" value="<?= val('asst_name2') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Asst Office Phone 2</label>
        <input type="text" name="asst_office_phone2" class="form-control" value="<?= val('asst_office_phone2') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Asst Cell Phone 2</label>
        <input type="text" name="asst_cell_phone2" class="form-control" value="<?= val('asst_cell_phone2') ?>">
    </div>
    <div class="col-md-4 mb-1">
        <label class="form-label">Asst Fax 2</label>
        <input type="text" name="asst_fax2" class="form-control" value="<?= val('asst_fax2') ?>">
    </div>
    <div class="col-md-6 mb-1">
        <label class="form-label">Asst Email 2</label>
        <input type="email" name="asst_email2" class="form-control" value="<?= val('asst_email2') ?>">
    </div>

    <!-- Roles -->
    <div class="col-12 mb-1">
        <label class="form-label">Roles</label>
        <div class="row">
            <div class="col-md-3"><input type="checkbox" name="is_loan_officer" value="1" <?= val('is_loan_officer') ? 'checked' : '' ?>> Loan Officer</div>
            <div class="col-md-3"><input type="checkbox" name="is_buyer_escrow" value="1" <?= val('is_buyer_escrow') ? 'checked' : '' ?>> Buyer Escrow</div>
            <div class="col-md-3"><input type="checkbox" name="is_seller_escrow" value="1" <?= val('is_seller_escrow') ? 'checked' : '' ?>> Seller Escrow</div>
            <div class="col-md-3"><input type="checkbox" name="is_listing_agent" value="1" <?= val('is_listing_agent') ? 'checked' : '' ?>> Listing Agent</div>
            <div class="col-md-3"><input type="checkbox" name="is_selling_agent" value="1" <?= val('is_selling_agent') ? 'checked' : '' ?>> Selling Agent</div>
            <div class="col-md-3"><input type="checkbox" name="include_in_reports" value="1" <?= val('include_in_reports', 1) ? 'checked' : '' ?>> Include in Reports</div>
            <div class="col-md-3"><input type="checkbox" name="add_asst_flag" value="1" <?= val('add_asst_flag') ? 'checked' : '' ?>> Show Assistant Section</div>
        </div>
    </div>

    <div class="col-12 mb-1">
        <div class="form-check">
            <input type="checkbox" name="active" class="form-check-input" value="1" <?= val('active', 1) ? 'checked' : '' ?>>
            <label class="form-check-label">Active</label>
        </div>
    </div>
</div>