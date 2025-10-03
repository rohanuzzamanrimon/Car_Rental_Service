<?php
require_once("../includes/config.php");
require_once("fpdf.php");

$booking_id = $_GET['booking_id'] ?? null;
$subscription_id = $_GET['subscription_id'] ?? null;

// --- Booking Invoice (update field names if needed) ---
if ($booking_id) {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($booking) {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Car Booking Invoice #' . $booking['id'], 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Ln(5);
        // SAFELY handle missing columns below as needed
        $pdf->Cell(0, 10, 'User ID: ' . ($booking['user_id'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Car ID: ' . ($booking['car_id'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Amount Paid: ৳' . ($booking['amount'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Status: ' . ($booking['payment_status'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Start: ' . ($booking['start_date'] ?? '') . ' ' . ($booking['start_time'] ?? ''), 0, 1);
        $pdf->Cell(0, 10, 'End: ' . ($booking['end_date'] ?? '') . ' ' . ($booking['end_time'] ?? ''), 0, 1);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Booking-Invoice-' . $booking['id'] . '.pdf"');
        $pdf->Output('D');
        exit;
    } else {
        http_response_code(404);
        echo "Booking not found!";
        exit;
    }
}

// --- Owner Subscription Invoice (matches your DB exactly) ---
if ($subscription_id) {
    $stmt = $conn->prepare("SELECT * FROM owner_subscriptions WHERE id = ?");
    $stmt->execute([$subscription_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($subscription) {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Owner Subscription Invoice #' . $subscription['id'], 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'User ID: ' . ($subscription['user_id'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Plan Name: ' . ($subscription['plan_name'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Duration: ' . ($subscription['duration'] ?? 'N/A') . ' days', 0, 1);
        $pdf->Cell(0, 10, 'Price: ৳' . ($subscription['price'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Status: ' . ($subscription['status'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Started At: ' . ($subscription['started_at'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Expires At: ' . ($subscription['expires_at'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Payment Status: ' . ($subscription['payment_status'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 10, 'Paid Amount: ৳' . ($subscription['paid_amount'] ?? 'N/A'), 0, 1);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Subscription-Invoice-' . $subscription['id'] . '.pdf"');
        $pdf->Output('D');
        exit;
    } else {
        http_response_code(404);
        echo "Subscription not found!";
        exit;
    }
}

http_response_code(400);
echo "Invalid request: missing invoice id.";
exit;
