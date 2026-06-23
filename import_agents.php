<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

set_time_limit(0); // No PHP execution timeout

// Database connection using MySQLi
$host = 'localhost';
$dbname = '032';
$username = 'root';  // Change this to your MySQL username
$password = '';      // Change this to your MySQL password

$mysqli = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($mysqli->connect_error) {
    die("❌ Database connection failed: " . $mysqli->connect_error . "\n");
}

// Set charset
$mysqli->set_charset("utf8mb4");
echo "✅ Database connected successfully\n";

// Map Excel columns to database fields
$columnMap = [
    'Agent_ID' => 'id',
    'Agent_Name' => 'name',
    'Agent_Office_Company' => 'company',
    'Agent_Address1' => 'address1',
    'Agent_Address2' => 'address2',
    'Agent_City' => 'city',
    'Agent_State' => 'state',
    'Agent_ZipCode' => 'zip',
    'Agent_Office_Telephone' => 'office_phone',
    'Agent_Cell_Telephone' => 'cell_phone',
    'Agent_Fax_Telephone' => 'fax',
    'Agent_Email_Address' => 'email',
    'Agent_Asst_Name1' => 'asst_name',
    'Agent_Asst_Office_Telephone1' => 'asst_office_phone',
    'Agent_Asst_Cell_Telephone1' => 'asst_cell_phone1',
    'Agent_Asst_Fax_Telephone1' => 'asst_fax',
    'Agent_Asst_Email_Address1' => 'asst_email',
    'LO' => 'is_loan_officer',
    'BEO' => 'is_buyer_escrow',
    'SEO' => 'is_seller_escrow',
    'LA' => 'is_listing_agent',
    'SA' => 'is_selling_agent',
    'ForReports' => 'include_in_reports'
];

/**
 * Clean string values for database insertion
 */
function cleanValue($value) {
    if ($value === null || $value === '') {
        return null;
    }
    // Remove Excel line break characters
    $value = str_replace(["\r\n", "\r", "\n", '_x000d_'], ' ', (string)$value);
    // Clean multiple spaces
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

/**
 * Convert Excel boolean to database tinyint
 */
function convertBoolean($value) {
    if ($value === null || $value === '') {
        return 0;
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_string($value)) {
        $value = strtolower(trim($value));
        return ($value === 'true' || $value === 'yes' || $value === '1') ? 1 : 0;
    }
    return $value ? 1 : 0;
}

/**
 * Get column index from header row
 */
function getColumnIndexes($headerRow, $columnMap) {
    $indexes = [];
    foreach ($headerRow as $colIndex => $headerValue) {
        $headerValue = trim((string)$headerValue);
        if (isset($columnMap[$headerValue])) {
            $indexes[$columnMap[$headerValue]] = $colIndex;
        }
    }
    return $indexes;
}

/**
 * Escape string for MySQL
 */
function escapeString($mysqli, $value) {
    if ($value === null) {
        return 'NULL';
    }
    return "'" . $mysqli->real_escape_string((string)$value) . "'";
}

try {
    // Check if Excel file exists
    $excelFile = 'AgentTb.xlsx';
    if (!file_exists($excelFile)) {
        throw new Exception("Excel file '$excelFile' not found. Please place it in the same directory.");
    }

    echo "📂 Loading Excel file: $excelFile\n";
    
    // Load the spreadsheet
    $spreadsheet = IOFactory::load($excelFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    if (empty($rows)) {
        throw new Exception("Excel file is empty.");
    }

    // Get header row (first row)
    $headerRow = $rows[0];
    echo "📋 Found " . count($headerRow) . " columns in header\n";
    
    // Map columns to database fields
    $columnIndexes = getColumnIndexes($headerRow, $columnMap);
    echo "✅ Mapped " . count($columnIndexes) . " columns to database fields\n";
    
    // Start transaction
    $mysqli->begin_transaction();
    
    // Optionally truncate table first (uncomment if you want to replace all data)
    // $mysqli->query("TRUNCATE TABLE agents");
    // echo "🗑️  Table truncated\n";
    
    $inserted = 0;
    $skipped = 0;
    $updated = 0;
    $errors = [];
    $totalRows = count($rows) - 1; // Exclude header
    
    // Get the field list
    $fields = array_keys($columnIndexes);
    $fieldList = implode('`, `', $fields);
    
    echo "⏳ Processing $totalRows rows...\n";
    
    // Loop through data rows (skip header)
    for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
        $rowData = $rows[$rowIndex];
        
        // Skip empty rows
        if (empty(array_filter($rowData, function($val) { return $val !== null && $val !== ''; }))) {
            $skipped++;
            continue;
        }
        
        // Get Agent_ID
        $agentId = isset($rowData[0]) ? trim((string)$rowData[0]) : '';
        
        // Skip invalid Agent_ID
        if ($agentId === '' || $agentId === 'N/A' || $agentId === 'TBD' || $agentId === 'See Notes Below') {
            $skipped++;
            continue;
        }
        
        // Build data array for insertion
        $insertData = [];
        $isValid = true;
        
        foreach ($columnIndexes as $dbField => $colIndex) {
            $value = isset($rowData[$colIndex]) ? $rowData[$colIndex] : null;
            
            // Handle different field types
            if (in_array($dbField, ['is_loan_officer', 'is_buyer_escrow', 'is_seller_escrow', 
                                    'is_listing_agent', 'is_selling_agent', 'include_in_reports'])) {
                $value = convertBoolean($value);
            } elseif ($dbField === 'id' && $value !== null) {
                // Agent_ID is numeric, skip if not valid
                if (!is_numeric($value) || $value === '' || $value === 'N/A') {
                    $isValid = false;
                    break;
                }
                $value = (int)$value;
            } else {
                $value = cleanValue($value);
            }
            
            $insertData[$dbField] = $value;
        }
        
        if (!$isValid) {
            $skipped++;
            continue;
        }
        
        // Validate that we have a name
        if (empty($insertData['name'])) {
            $skipped++;
            continue;
        }
        
        // Build the INSERT query
        $insertFields = [];
        $insertValues = [];
        
        foreach ($insertData as $field => $value) {
            $insertFields[] = "`$field`";
            $insertValues[] = escapeString($mysqli, $value);
        }
        
        // Add active and created_at
        $insertFields[] = "`active`";
        $insertValues[] = "1";
        $insertFields[] = "`created_at`";
        $insertValues[] = "NOW()";
        
        // Build the UPDATE part for ON DUPLICATE KEY
        $updateParts = [];
        foreach ($insertData as $field => $value) {
            if ($field !== 'id') {
                $updateParts[] = "`$field` = VALUES(`$field`)";
            }
        }
        $updateParts[] = "`active` = 1";
        
        $sql = "INSERT INTO `agents` (" . implode(', ', $insertFields) . ") 
                VALUES (" . implode(', ', $insertValues) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
        
        if ($mysqli->query($sql)) {
            if ($mysqli->affected_rows == 1) {
                $inserted++;
            } else {
                $updated++;
            }
        } else {
            $errors[] = "Row " . ($rowIndex + 1) . ": " . $mysqli->error;
            $skipped++;
        }
        
        // Progress indicator
        if (($inserted + $updated) % 50 === 0) {
            echo "  ✅ Processed " . ($inserted + $updated) . " records...\n";
        }
    }
    
    // Commit the transaction
    $mysqli->commit();
    
    echo "\n✅ Import completed successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Inserted: $inserted records\n";
    echo "   - Updated: $updated records\n";
    echo "   - Skipped: $skipped records\n";
    echo "   - Total rows processed: " . ($inserted + $updated + $skipped) . "\n";
    
    if (!empty($errors)) {
        echo "   - Errors: " . count($errors) . " (first 5 shown below)\n";
        foreach (array_slice($errors, 0, 5) as $error) {
            echo "     ⚠️  $error\n";
        }
    }
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($mysqli) && $mysqli->errno) {
        $mysqli->rollback();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}

// Close connection
$mysqli->close();