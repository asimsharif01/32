<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

set_time_limit(0);

// Database connection using MySQLi
$host = 'localhost';
$dbname = '032';
$username = 'root';
$password = '';

$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die("❌ Database connection failed: " . $mysqli->connect_error . "\n");
}

$mysqli->set_charset("utf8mb4");
echo "✅ Database connected successfully\n";

// Helper function to clean string values
function cleanValue($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $value = str_replace(["\r\n", "\r", "\n", '_x000d_'], ' ', (string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

// Helper function to escape strings for MySQL
function escapeString($mysqli, $value) {
    if ($value === null || $value === '') {
        return 'NULL';
    }
    if (is_numeric($value)) {
        return (string)$value;
    }
    return "'" . $mysqli->real_escape_string((string)$value) . "'";
}

// Helper function to parse date
function parseDate($value) {
    if ($value === null || $value === '' || $value === 'False' || $value === 'false') {
        return null;
    }
    
    // If it's a numeric Excel date
    if (is_numeric($value)) {
        try {
            $timestamp = ExcelDate::excelToTimestamp($value);
            return date('Y-m-d', $timestamp);
        } catch (Exception $e) {
            return null;
        }
    }
    
    // If it's a string date
    $date = trim((string)$value);
    if (strlen($date) >= 10) {
        $formats = ['d/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y', 'm-d-Y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $date);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }
    return null;
}

// Helper function to parse financial values
function parseFinancial($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $clean = str_replace(['$', ','], '', (string)$value);
    if (is_numeric($clean)) {
        return (float)$clean;
    }
    return null;
}

// Helper function to parse percentage values
function parsePercentage($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $clean = str_replace(['%', ','], '', (string)$value);
    if (is_numeric($clean)) {
        return (float)$clean;
    }
    return null;
}

// Helper function to get lookup ID from description
function getLookupId($mysqli, $table, $description, $createIfMissing = false) {
    if (empty($description) || $description === '' || $description === 'N/A' || $description === 'TBD') {
        return null;
    }
    
    $desc = cleanValue($description);
    if (empty($desc)) return null;
    
    $stmt = $mysqli->prepare("SELECT id FROM `$table` WHERE description = ?");
    $stmt->bind_param('s', $desc);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    if ($createIfMissing) {
        $stmt = $mysqli->prepare("INSERT INTO `$table` (description) VALUES (?)");
        $stmt->bind_param('s', $desc);
        if ($stmt->execute()) {
            return $mysqli->insert_id;
        }
    }
    
    return null;
}

// Map property types
function getPropertyTypeId($mysqli, $typeDesc) {
    $typeMap = [
        'Single Family' => 'Single Family',
        'Condominium' => 'Condominium',
        'Townhouse' => 'Townhouse',
        'duplex' => 'duplex',
        '4-plex' => '4-plex',
        'New Construction' => 'New Construction',
        'Vacant Lot' => 'Vacant Lot'
    ];
    
    $desc = cleanValue($typeDesc);
    if (isset($typeMap[$desc])) {
        return getLookupId($mysqli, 'property_types', $typeMap[$desc]);
    }
    return null;
}

// Map financing types
function getFinancingTypeId($mysqli, $typeDesc) {
    $typeMap = [
        'Conventional' => 'Conventional',
        'FHA' => 'FHA',
        'VA' => 'VA',
        'Cash' => 'Cash',
        'USDA' => 'USDA',
        'Rural Housing' => 'Rural Housing',
        'Seller Financing' => 'Seller Financing',
        'TBD' => 'TBD',
        'Conv/FHA' => 'Conv/FHA',
        'Rural Loan' => 'Rural Loan',
        'Private' => 'Private'
    ];
    
    $desc = cleanValue($typeDesc);
    if (isset($typeMap[$desc])) {
        return getLookupId($mysqli, 'financing_types', $typeMap[$desc]);
    }
    return null;
}

// Map sales status
function getStatusId($mysqli, $statusDesc) {
    $statusMap = [
        'Listed' => 'Listed',
        'Under Contract' => 'Under Contract',
        'Closed' => 'Closed',
        'Rescinded' => 'Rescinded',
        'Expired' => 'Expired'
    ];
    
    $desc = cleanValue($statusDesc);
    if (isset($statusMap[$desc])) {
        return getLookupId($mysqli, 'sales_statuses', $statusMap[$desc]);
    }
    return null;
}

// Map lead source
function getLeadSourceId($mysqli, $leadDesc) {
    $leadMap = [
        'ELP' => 'ELP',
        'Referral' => 'Referral',
        'Laser Lending' => 'Laser Lending',
        'Sign Call' => 'Sign Call',
        'Abel' => 'Abel',
        'Google' => 'Google',
        'Other' => 'Other',
        'Past Client' => 'Other',
        '5476' => 'Other'
    ];
    
    $desc = cleanValue($leadDesc);
    if (empty($desc)) return null;
    
    $stmt = $mysqli->prepare("SELECT id FROM lead_sources WHERE description = ?");
    $stmt->bind_param('s', $desc);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    foreach ($leadMap as $key => $value) {
        if (stripos($desc, $key) !== false) {
            return getLookupId($mysqli, 'lead_sources', $value);
        }
    }
    
    return getLookupId($mysqli, 'lead_sources', $desc, true);
}

try {
    $excelFile = 'PropertyTb.xlsx';
    if (!file_exists($excelFile)) {
        throw new Exception("Excel file '$excelFile' not found.");
    }

    echo "📂 Loading Excel file: $excelFile\n";
    
    $spreadsheet = IOFactory::load($excelFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    if (empty($rows)) {
        throw new Exception("Excel file is empty.");
    }

    $headerRow = $rows[0];
    echo "📋 Found " . count($headerRow) . " columns in header\n";
    
    // Build column index mapping
    $colIndexes = [];
    foreach ($headerRow as $index => $colName) {
        $colIndexes[trim($colName)] = $index;
    }
    
    // Start transaction
    $mysqli->begin_transaction();
    
    $inserted = 0;
    $skipped = 0;
    $errors = [];
    
    // Define the column list - match exactly with your database table
    $columns = [
        'mls_number', 'transaction_number', 'address1', 'address2', 'city', 'state', 'zip',
        'property_type_id', 'purchase_price', 'uc_price', 'final_price', 'financing_type_id',
        'status_id', 'earnest_money_amount', 'earnest_money_deposit_with',
        'contract_date', 'date_of_listing', 'date_of_expiration', 'closing_date',
        'buyer_name', 'buyer_home_phone', 'buyer_cell_phone1', 'buyer_cell_phone2',
        'buyer_fax', 'buyer_email1', 'buyer_email2',
        'seller_name', 'seller_home_phone', 'seller_cell_phone1', 'seller_cell_phone2',
        'seller_fax', 'seller_email1', 'seller_email2',
        'commission_price', 'commission_pct', 'commission_other',
        'transaction_fee', 'errors_omissions', 'agent_split', 'processing_fee', 'other2',
        'split_with', 'multiplier', 'private', 'comments', 'lead_source_id',
        'LO_Name', 'LO_Company', 'LO_Email', 'LO_OfficePhone', 'LO_CellPhone', 'LO_Fax',
        'LO_Address1', 'LO_Address2', 'LO_City', 'LO_State', 'LO_Zip',
        'LO_AsstName', 'LO_AsstOfficePhone', 'LO_AsstFax', 'LO_AsstEmail', 'LO_AddAsstFlag',
        'BEO_Name', 'BEO_Company', 'BEO_Email', 'BEO_OfficePhone', 'BEO_CellPhone', 'BEO_Fax',
        'BEO_Address1', 'BEO_Address2', 'BEO_City', 'BEO_State', 'BEO_Zip',
        'BEO_AsstName', 'BEO_AsstOfficePhone', 'BEO_AsstFax', 'BEO_AsstEmail', 'BEO_AddAsstFlag',
        'SEO_Name', 'SEO_Company', 'SEO_Email', 'SEO_OfficePhone', 'SEO_CellPhone', 'SEO_Fax',
        'SEO_Address1', 'SEO_Address2', 'SEO_City', 'SEO_State', 'SEO_Zip',
        'SEO_AsstName', 'SEO_AsstOfficePhone', 'SEO_AsstFax', 'SEO_AsstEmail', 'SEO_AddAsstFlag',
        'LA_Name', 'LA_Company', 'LA_Email', 'LA_OfficePhone', 'LA_CellPhone', 'LA_Fax',
        'LA_Address1', 'LA_Address2', 'LA_City', 'LA_State', 'LA_Zip',
        'LA_AsstName', 'LA_AsstOfficePhone', 'LA_AsstFax', 'LA_AsstEmail',
        'LA_ForReport', 'LA_AddAsstFlag',
        'SA_Name', 'SA_Company', 'SA_Email', 'SA_OfficePhone', 'SA_CellPhone', 'SA_Fax',
        'SA_Address1', 'SA_Address2', 'SA_City', 'SA_State', 'SA_Zip',
        'SA_AsstName', 'SA_AsstOfficePhone', 'SA_AsstFax', 'SA_AsstEmail',
        'SA_ForReport', 'SA_AddAsstFlag'
    ];
    
    echo "⏳ Processing rows...\n";
    
    // Loop through data rows
    for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
        $rowData = $rows[$rowIndex];
        
        // Skip empty rows
        if (empty(array_filter($rowData, function($val) { return $val !== null && $val !== ''; }))) {
            $skipped++;
            continue;
        }
        
        // Helper to get column value
        $getCol = function($colName) use ($rowData, $colIndexes) {
            if (isset($colIndexes[$colName]) && isset($rowData[$colIndexes[$colName]])) {
                return $rowData[$colIndexes[$colName]];
            }
            return null;
        };
        
        // Get MLS number
        $mlsNumber = cleanValue($getCol('MLS_Number'));
        if (empty($mlsNumber) || $mlsNumber === 'N/A' || $mlsNumber === 'n/a') {
            $skipped++;
            continue;
        }
        
        // Parse dates
        $contractDate = parseDate($getCol('Contract_Date'));
        $closingDate = parseDate($getCol('Closing_Date'));
        $dateOfListing = parseDate($getCol('Date_of_Listing'));
        $dateOfExpiration = parseDate($getCol('Date_of_Expiration'));
        
        // Parse financial fields
        $finalPrice = parseFinancial($getCol('Final_Price'));
        $purchasePrice = parseFinancial($getCol('Purchase_Price'));
        $ucPrice = parseFinancial($getCol('UC_Price'));
        $commissionPrice = parseFinancial($getCol('Commission_Price'));
        $commissionPct = parsePercentage($getCol('Commission_Pct'));
        $commissionOther = parseFinancial($getCol('Commission_Other'));
        $transactionFee = parseFinancial($getCol('Transaction_Fee'));
        $errorsOmissions = parseFinancial($getCol('Errors_and_Omissions'));
        $agentSplit = parseFinancial($getCol('Agent_Split'));
        $processingFee = parseFinancial($getCol('Processing_Fee'));
        $other2 = parseFinancial($getCol('Other2'));
        $earnestMoney = parseFinancial($getCol('Earnest_Money_Amount'));
        $multiplier = parseFinancial($getCol('TA_Balance'));
        
        if ($multiplier === null || $multiplier == 0) {
            $multiplier = 1.0;
        }
        
        // Get lookup IDs
        $statusId = getStatusId($mysqli, cleanValue($getCol('Status')));
        $propertyTypeId = getPropertyTypeId($mysqli, cleanValue($getCol('Type')));
        $financingTypeId = getFinancingTypeId($mysqli, cleanValue($getCol('Financing_Type')));
        $leadSourceId = getLeadSourceId($mysqli, cleanValue($getCol('Lead')));
        
        // Handle Earnest Money On Deposit With
        $emDepositWith = cleanValue($getCol('Earnest_Money_On_Deposit_With'));
        if ($emDepositWith === 'Larson and Company' || $emDepositWith === 'n/a') {
            $emDepositWith = null;
        }
        
        // Handle private flag
        $private = cleanValue($getCol('Private'));
        $private = ($private === 'True' || $private === 'true' || $private === '1') ? 1 : 0;
        
        // Handle split with
        $splitWith = cleanValue($getCol('Split_With'));
        if ($splitWith === '') $splitWith = null;
        
        // Build values array - each value must be properly escaped
        $values = [
            escapeString($mysqli, $mlsNumber),
            escapeString($mysqli, cleanValue($getCol('Transaction_Number'))),
            escapeString($mysqli, cleanValue($getCol('Address1'))),
            escapeString($mysqli, cleanValue($getCol('Address2'))),
            escapeString($mysqli, cleanValue($getCol('City'))),
            escapeString($mysqli, cleanValue($getCol('State')) ?: 'UT'),
            escapeString($mysqli, cleanValue($getCol('ZipCode'))),
            escapeString($mysqli, $propertyTypeId),
            escapeString($mysqli, $purchasePrice),
            escapeString($mysqli, $ucPrice),
            escapeString($mysqli, $finalPrice),
            escapeString($mysqli, $financingTypeId),
            escapeString($mysqli, $statusId),
            escapeString($mysqli, $earnestMoney),
            escapeString($mysqli, $emDepositWith),
            escapeString($mysqli, $contractDate),
            escapeString($mysqli, $dateOfListing),
            escapeString($mysqli, $dateOfExpiration),
            escapeString($mysqli, $closingDate),
            escapeString($mysqli, cleanValue($getCol('Buyer_Name'))),
            escapeString($mysqli, cleanValue($getCol('Buyer_Home_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('Buyer_Cell_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('Buyer_Cell_Telephone2'))),
            escapeString($mysqli, cleanValue($getCol('Buyer_Fax_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('Buyer_Email_Address1'))),
            escapeString($mysqli, cleanValue($getCol('Buyer_Email_Address2'))),
            escapeString($mysqli, cleanValue($getCol('Seller_Name'))),
            escapeString($mysqli, cleanValue($getCol('Seller_Home_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('Seller_Cell_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('Seller_Cell_Telephone2'))),
            escapeString($mysqli, cleanValue($getCol('Seller_Fax_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('Seller_Email_Address1'))),
            escapeString($mysqli, cleanValue($getCol('Seller_Email_Address2'))),
            escapeString($mysqli, $commissionPrice),
            escapeString($mysqli, $commissionPct),
            escapeString($mysqli, $commissionOther),
            escapeString($mysqli, $transactionFee),
            escapeString($mysqli, $errorsOmissions),
            escapeString($mysqli, $agentSplit),
            escapeString($mysqli, $processingFee),
            escapeString($mysqli, $other2),
            escapeString($mysqli, $splitWith),
            escapeString($mysqli, $multiplier),
            escapeString($mysqli, $private),
            escapeString($mysqli, cleanValue($getCol('Comments'))),
            escapeString($mysqli, $leadSourceId),
            // LO fields
            escapeString($mysqli, cleanValue($getCol('LO_Name'))),
            escapeString($mysqli, cleanValue($getCol('LO_Company'))),
            escapeString($mysqli, cleanValue($getCol('LO_Email_Address'))),
            escapeString($mysqli, cleanValue($getCol('LO_Office_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('LO_Cell_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('LO_Fax_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('LO_Address1'))),
            escapeString($mysqli, cleanValue($getCol('LO_Address2'))),
            escapeString($mysqli, cleanValue($getCol('LO_City'))),
            escapeString($mysqli, cleanValue($getCol('LO_State'))),
            escapeString($mysqli, cleanValue($getCol('LO_ZipCode'))),
            escapeString($mysqli, cleanValue($getCol('LO_Asst_Name1'))),
            escapeString($mysqli, cleanValue($getCol('LO_Asst_Office_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('LO_Asst_Fax_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('LO_Asst_Email_Address1'))),
            escapeString($mysqli, (int)cleanValue($getCol('LO_AddAsst_Flag')) ?: 0),
            // BEO fields
            escapeString($mysqli, cleanValue($getCol('BEO_Name'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Company'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Email_Address'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Office_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Cell_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Fax_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Address1'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Address2'))),
            escapeString($mysqli, cleanValue($getCol('BEO_City'))),
            escapeString($mysqli, cleanValue($getCol('BEO_State'))),
            escapeString($mysqli, cleanValue($getCol('BEO_ZipCode'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Asst_Name1'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Asst_Office_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Asst_Fax_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('BEO_Asst_Email_Address1'))),
            escapeString($mysqli, (int)cleanValue($getCol('BEO_AddAsst_Flag')) ?: 0),
            // SEO fields
            escapeString($mysqli, cleanValue($getCol('SEO_Name'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Company'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Email_Address'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Office_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Cell_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Fax_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Address1'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Address2'))),
            escapeString($mysqli, cleanValue($getCol('SEO_City'))),
            escapeString($mysqli, cleanValue($getCol('SEO_State'))),
            escapeString($mysqli, cleanValue($getCol('SEO_ZipCode'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Asst_Name1'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Asst_Office_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Asst_Fax_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('SEO_Asst_Email_Address1'))),
            escapeString($mysqli, (int)cleanValue($getCol('SEO_AddAsst_Flag')) ?: 0),
            // LA fields
            escapeString($mysqli, cleanValue($getCol('LA_Name'))),
            escapeString($mysqli, cleanValue($getCol('LA_Company'))),
            escapeString($mysqli, cleanValue($getCol('LA_Email_Address'))),
            escapeString($mysqli, cleanValue($getCol('LA_Office_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('LA_Cell_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('LA_Fax_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('LA_Address1'))),
            escapeString($mysqli, cleanValue($getCol('LA_Address2'))),
            escapeString($mysqli, cleanValue($getCol('LA_City'))),
            escapeString($mysqli, cleanValue($getCol('LA_State'))),
            escapeString($mysqli, cleanValue($getCol('LA_ZipCode'))),
            escapeString($mysqli, cleanValue($getCol('LA_Asst_Name1'))),
            escapeString($mysqli, cleanValue($getCol('LA_Asst_Office_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('LA_Asst_Fax_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('LA_Asst_Email_Address1'))),
            escapeString($mysqli, (int)cleanValue($getCol('LA_For_Report')) ?: 1),
            escapeString($mysqli, (int)cleanValue($getCol('LA_AddAsst_Flag')) ?: 0),
            // SA fields
            escapeString($mysqli, cleanValue($getCol('SA_Name'))),
            escapeString($mysqli, cleanValue($getCol('SA_Company'))),
            escapeString($mysqli, cleanValue($getCol('SA_Email_Address'))),
            escapeString($mysqli, cleanValue($getCol('SA_Office_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('SA_Cell_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('SA_Fax_Telephone'))),
            escapeString($mysqli, cleanValue($getCol('SA_Address1'))),
            escapeString($mysqli, cleanValue($getCol('SA_Address2'))),
            escapeString($mysqli, cleanValue($getCol('SA_City'))),
            escapeString($mysqli, cleanValue($getCol('SA_State'))),
            escapeString($mysqli, cleanValue($getCol('SA_ZipCode'))),
            escapeString($mysqli, cleanValue($getCol('SA_Asst_Name1'))),
            escapeString($mysqli, cleanValue($getCol('SA_Asst_Office_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('SA_Asst_Fax_Telephone1'))),
            escapeString($mysqli, cleanValue($getCol('SA_Asst_Email_Address1'))),
            escapeString($mysqli, (int)cleanValue($getCol('SA_For_Report')) ?: 1),
            escapeString($mysqli, (int)cleanValue($getCol('SA_AddAsst_Flag')) ?: 0)
        ];
        
        // Build the INSERT query
        $query = "INSERT INTO `listings` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ")";
        
        try {
            if ($mysqli->query($query)) {
                $inserted++;
                if ($inserted % 50 === 0) {
                    echo "  ✅ Inserted $inserted records...\n";
                }
            } else {
                $errors[] = "Row " . ($rowIndex + 1) . ": " . $mysqli->error;
                $skipped++;
            }
        } catch (Exception $e) {
            $errors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
            $skipped++;
        }
    }
    
    $mysqli->commit();
    
    echo "\n✅ Import completed successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Inserted: $inserted records\n";
    echo "   - Skipped: $skipped records\n";
    echo "   - Total rows processed: " . ($inserted + $skipped) . "\n";
    
    if (!empty($errors)) {
        echo "   - Errors: " . count($errors) . " (first 10 shown below)\n";
        foreach (array_slice($errors, 0, 10) as $error) {
            echo "     ⚠️  $error\n";
        }
    }
    
} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->errno) {
        $mysqli->rollback();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}

$mysqli->close();