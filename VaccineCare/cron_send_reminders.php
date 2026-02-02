<?php
// cron_send_reminders.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . "/core/db.php"; // $pdo should be defined here
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ----- Function to send email -----
function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'xxxxxxxxxxxxxxxxxxx';
        $mail->Password = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // replace with real key
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('xxxxxxxxx@gmail.com', 'xxxxx');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email to $to failed: " . $mail->ErrorInfo);
        return false;
    }
}

$todayStr = (new DateTime())->format('Y-m-d');

// ----- 1️⃣ Pending reminders -----
$pendingQuery = "
SELECT r.id, r.scheduled_date, r.first_reminder_sent, r.second_reminder_sent,
       c.name AS child_name, u.email AS parent_email, v.vaccine_name
FROM child_vaccine_reminders r
JOIN children c ON r.child_id = c.id
JOIN users u ON c.parent_id = u.id
JOIN vaccinations v ON r.vaccine_id = v.vaccine_id
WHERE r.status = 'Pending'
";

$pendingReminders = $pdo->query($pendingQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($pendingReminders as $r) {
    $scheduled = new DateTime($r['scheduled_date']);
    $endOfWeek = (clone $scheduled)->modify('+6 days');
    $dayBeforeEnd = (clone $endOfWeek)->modify('-1 day');

    // Start-of-week reminder
    if ($todayStr === $scheduled->format('Y-m-d') && !$r['first_reminder_sent']) {
        if (sendEmail($r['parent_email'],
            "Vaccination Reminder: {$r['vaccine_name']} for {$r['child_name']}",
            "Dear Parent,\nYour child {$r['child_name']} is due for {$r['vaccine_name']} this week. Please visit your health center soon."
        )) {
            $updateStmt = $pdo->prepare("UPDATE child_vaccine_reminders SET first_reminder_sent = 1 WHERE id = ?");
            $updateStmt->execute([$r['id']]);
        }
    }

    // End-of-week reminder
    if ($todayStr === $dayBeforeEnd->format('Y-m-d') && !$r['second_reminder_sent']) {
        if (sendEmail($r['parent_email'],
            "Final Reminder: {$r['vaccine_name']} for {$r['child_name']}",
            "Dear Parent,\nTomorrow is the last day for {$r['vaccine_name']} vaccination week for {$r['child_name']}."
        )) {
            $updateStmt = $pdo->prepare("UPDATE child_vaccine_reminders SET second_reminder_sent = 1 WHERE id = ?");
            $updateStmt->execute([$r['id']]);
        }
    }
}

// ----- 2️⃣ Completed vaccines (last 1 day) -----
$completedQuery = "
SELECT vc.id, c.name AS child_name, u.email AS parent_email, v.vaccine_name
FROM vaccine_children vc
JOIN children c ON vc.child_id = c.id
JOIN users u ON c.parent_id = u.id
JOIN vaccinations v ON vc.vaccine_id = v.vaccine_id
WHERE vc.status='Completed' AND vc.date_completed >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND vc.mail_sent = 0
";

$stmt = $pdo->prepare($completedQuery);
$stmt->execute();
$completedToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($completedToday as $r) {
    if (sendEmail(
        $r['parent_email'],
        "Vaccine Completed: {$r['vaccine_name']} for {$r['child_name']}",
        "Dear Parent,\nVaccination '{$r['vaccine_name']}' for your child '{$r['child_name']}' has been marked as Completed."
    )) {
        $updateStmt = $pdo->prepare("UPDATE vaccine_children SET mail_sent = 1 WHERE id = ?");
        $updateStmt->execute([$r['id']]);
    }
}

echo "✅ Pending reminders and completed vaccine emails processed.\n";
?>
