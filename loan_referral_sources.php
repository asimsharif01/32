<?php
// loan_referral_sources.php
$table_name   = 'loan_referral_sources';
$page_title   = 'Referral Sources';
$singular     = 'Referral Source';
$extra_fields = [
    ['column' => 'company', 'label' => 'Company',     'type' => 'text'],
    ['column' => 'phone',   'label' => 'Phone',        'type' => 'text'],
    ['column' => 'email',  'label' => 'Email',         'type' => 'email'],
];
require '_lending_lookup_render.php';
