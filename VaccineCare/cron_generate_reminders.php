<?php
// cron_generate_reminders.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . "/core/db.php"; // Make sure this returns $pdo (PDO instance)

// Get all children
$childrenStmt = $pdo->query("SELECT id, name, dob FROM children");
$children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($children as $child) {
    $childId = $child['id'];
    $dob = new DateTime($child['dob']);

    // Get all vaccines
    $vaccinesStmt = $pdo->query("SELECT vaccine_id, vaccine_name, recommended_age_months FROM vaccinations");
    $vaccines = $vaccinesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vaccines as $vaccine) {
        $vaccineId = $vaccine['vaccine_id'];
        $months = $vaccine['recommended_age_months'] ?? 0;

        // Calculate scheduled date
        $scheduledDate = (clone $dob)->modify("+{$months} months")->format('Y-m-d');
        $startDate = $scheduledDate;
        $endDate = (clone $dob)->modify("+{$months} months")->modify("+6 days")->format('Y-m-d');

        // Check if reminder already exists
        $existsStmt = $pdo->prepare("SELECT id FROM child_vaccine_reminders WHERE child_id = ? AND vaccine_id = ?");
        $existsStmt->execute([$childId, $vaccineId]);
        if ($existsStmt->rowCount() === 0) {
            // Insert new reminder
            $insertStmt = $pdo->prepare(
                "INSERT INTO child_vaccine_reminders (child_id, vaccine_id, scheduled_date, start_date, end_date, status) 
                 VALUES (?, ?, ?, ?, ?, 'Pending')"
            );
            $insertStmt->execute([$childId, $vaccineId, $scheduledDate, $startDate, $endDate]);
        }
    }
}

echo "✅ Reminder generation complete.\n";
?>
