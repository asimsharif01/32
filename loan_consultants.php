<?php
// loan_consultants.php
$table_name   = 'loan_consultants';
$page_title   = 'Loan Consultants';
$singular     = 'Loan Consultant';
$extra_fields = [
    ['column' => 'employment_start_date', 'label' => 'Employment Start Date', 'type' => 'date'],
];
require '_lending_lookup_render.php';
