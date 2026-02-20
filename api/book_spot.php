<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to book a spot.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$spot_id = isset($data['spot_id']) ? intval($data['spot_id']) : 0;
$user_id = $_SESSION['user_id'];
$booking_date = isset($data['booking_date']) ? $data['booking_date'] : '';
$num_adults = isset($data['num_adults']) ? intval($data['num_adults']) : 1;
$num_children = isset($data['num_children']) ? intval($data['num_children']) : 0;
$special_request = isset($data['special_request']) ? $data['special_request'] : '';

if ($spot_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid spot.']);
    exit;
}

if (empty($booking_date)) {
    echo json_encode(['success' => false, 'message' => 'Please select a date.']);
    exit;
}

try {
    // 1. Get spot price
    $stmt = $conn->prepare("SELECT price FROM spots WHERE id = ?");
    $stmt->bind_param("i", $spot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $spot = $result->fetch_assoc();
    $stmt->close();

    if (!$spot) {
        echo json_encode(['success' => false, 'message' => 'Spot not found.']);
        exit;
    }

    $price = floatval($spot['price']);
    $total_price = $price * ($num_adults + $num_children);
    
    // Payment Logic
    $payment = isset($data['payment']) ? $data['payment'] : null;
    $status = 'pending'; // Default status

    if ($total_price > 0) {
        if (!$payment) {
             echo json_encode(['success' => false, 'message' => 'Payment is required for this booking.']);
             exit;
        }

        $card_number = str_replace(' ', '', $payment['card_number']);
        $expiry = $payment['expiry'];
        $cvv = $payment['cvv'];

        // Server-side Validation
        if (strlen($card_number) < 13 || strlen($card_number) > 19 || !is_numeric($card_number)) {
            echo json_encode(['success' => false, 'message' => 'Invalid card number.']);
            exit;
        }
        
        // Simulate Payment Gateway Delay
        sleep(1); // 1 second delay to simulate real-time processing

        // Assume Success
        $status = 'confirmed';
        $last4 = substr($card_number, -4);
        $special_request = trim($special_request . " [Paid: Card **** $last4]");
    } else {
        // Free spots are auto-confirmed? Or pending? Let's say pending approval unless specified.
        // But usually free booking is just a reservation.
        // Let's keep it pending for free spots, or confirmed if no payment needed.
        // For consistency, let's keep pending for free spots as they might need manual approval.
        // Or if the user wants "realtime", maybe confirmed?
        // Let's stick to 'pending' for free spots to be safe, unless user overrides.
        // But for paid spots, successful payment = confirmed.
    }

    // 2. Insert into bookings table
    $stmt = $conn->prepare("INSERT INTO bookings (user_id, spot_id, booking_date, num_adults, num_children, total_price, special_request, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiiiss", $user_id, $spot_id, $booking_date, $num_adults, $num_children, $total_price, $special_request, $status);
    
    if ($stmt->execute()) {
        // 3. Also log to user_activity for consistency
        $activity_stmt = $conn->prepare("INSERT INTO user_activity (user_id, spot_id, activity_type, status) VALUES (?, ?, 'booking', ?)");
        $activity_stmt->bind_param("iis", $user_id, $spot_id, $status);
        $activity_stmt->execute();
        $activity_stmt->close();

        $msg = ($status === 'confirmed') ? 'Booking confirmed! Payment successful.' : 'Booking request sent successfully!';
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>