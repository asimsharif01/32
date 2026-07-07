<?php
// backfill_listing_milestones.php
//
// One-time (safe to re-run) backfill that populates `listing_milestones`
// from the deadline columns already sitting on `listings`.
//
// Why this is needed:
//   transaction_detail.php reads the Deadlines panel ONLY from
//   `listing_milestones` (keyed by listing_id + milestone_type).
//   The historical import wrote deadline dates onto `listings`
//   (seller_disclosure_date, due_diligence_date, financing_appraisal_date,
//   settlement_date, contract_date) but never created the matching rows
//   in `listing_milestones`, so the panel shows blank for every
//   imported/historical listing even though the data exists.
//
// This script is idempotent: for every listing it wipes out any existing
// listing_milestones rows for that listing_id and re-inserts fresh ones,
// so you can run it again later (e.g. after another data fix) without
// creating duplicates.

set_time_limit(0);

$host     = 'localhost';
$dbname   = '032';
$username = 'root';
$password = '';

$mysqli = new mysqli($host, $username, $password, $dbname);
if ($mysqli->connect_error) {
    die("❌ Database connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset("utf8mb4");
echo "✅ Database connected successfully\n";

// milestone_type => [date column on listings, completed column on listings (or null)]
$milestoneMap = [
    'Date of Contract'      => ['contract_date',            null],
    'Seller Disclosure'     => ['seller_disclosure_date',   'seller_disclosure_completed'],
    'Due Diligence'         => ['due_diligence_date',       'due_diligence_completed'],
    'Financing & Appraisal' => ['financing_appraisal_date', 'financing_appraisal_completed'],
    'Settlement'            => ['settlement_date',          'settlement_completed'],
];

$mysqli->begin_transaction();

$result = $mysqli->query("SELECT * FROM listings");
if (!$result) {
    die("❌ Could not read listings: " . $mysqli->error . "\n");
}

$listingsProcessed  = 0;
$milestonesInserted = 0;
$errors             = [];

$delStmt = $mysqli->prepare("DELETE FROM listing_milestones WHERE listing_id = ?");
$insStmt = $mysqli->prepare(
    "INSERT INTO listing_milestones (listing_id, milestone_type, due_date, completed, na_flag)
     VALUES (?, ?, ?, ?, ?)"
);

while ($listing = $result->fetch_assoc()) {
    $listingId = (int)$listing['id'];

    // Wipe any existing rows for this listing so re-running is always safe
    $delStmt->bind_param('i', $listingId);
    $delStmt->execute();

    foreach ($milestoneMap as $milestoneType => $cols) {
        [$dateCol, $completedCol] = $cols;

        $dueDate      = $listing[$dateCol] ?? null;
        $hasDate      = !empty($dueDate) && $dueDate !== '0000-00-00';
        $dueDateParam = $hasDate ? $dueDate : null;
        $completed    = $completedCol ? (int)($listing[$completedCol] ?? 0) : 0;
        // Treat a missing date as N/A, matching how the old Access sheet used the checkbox
        $naFlag       = $hasDate ? 0 : 1;

        $insStmt->bind_param('issii', $listingId, $milestoneType, $dueDateParam, $completed, $naFlag);
        if ($insStmt->execute()) {
            $milestonesInserted++;
        } else {
            $errors[] = "Listing $listingId / $milestoneType: " . $insStmt->error;
        }
    }

    $listingsProcessed++;
    if ($listingsProcessed % 100 === 0) {
        echo "  ✅ Processed $listingsProcessed listings...\n";
    }
}

$mysqli->commit();

echo "\n✅ Backfill completed!\n";
echo "📊 Summary:\n";
echo "   - Listings processed: $listingsProcessed\n";
echo "   - Milestone rows inserted: $milestonesInserted\n";

if (!empty($errors)) {
    echo "   - Errors: " . count($errors) . " (first 10 shown below)\n";
    foreach (array_slice($errors, 0, 10) as $error) {
        echo "     ⚠️  $error\n";
    }
}

$mysqli->close();