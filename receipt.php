<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['booking_id'])) {
    die("Booking ID not specified.");
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];

// Fetch booking details securely
$sql = "SELECT b.*, s.name as spot_name, s.location, u.username, u.email 
        FROM bookings b 
        JOIN spots s ON b.spot_id = s.id 
        JOIN users u ON b.user_id = u.id 
        WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Booking not found or access denied.");
}

$booking = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt #<?php echo $booking_id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Courier New', Courier, monospace; /* Monospace for receipt feel */
            background-color: #f0f0f0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
        }
        .receipt-container {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        /* Paper cut effect */
        .receipt-container::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 20px;
            background: radial-gradient(circle, transparent 70%, white 70%) 0 -10px;
            background-size: 20px 20px;
            transform: rotate(180deg);
        }
        
        .header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #666;
        }
        .details {
            margin-bottom: 20px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .label {
            color: #666;
        }
        .value {
            font-weight: bold;
            text-align: right;
        }
        .divider {
            border-top: 1px dashed #ccc;
            margin: 15px 0;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: bold;
            text-align: center;
            width: 100%;
            margin-top: 10px;
            color: white;
        }
        .status-pending { background-color: #f39c12; }
        .status-confirmed { background-color: #2ecc71; }
        .status-cancelled { background-color: #e74c3c; }
        .status-completed { background-color: #3498db; }
        .status-rejected { background-color: #95a5a6; }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #888;
            margin-top: 30px;
            border-top: 2px dashed #333;
            padding-top: 20px;
        }
        
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: sans-serif;
            font-weight: bold;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-print {
            background-color: #333;
            color: white;
        }
        .btn-print:hover {
            background-color: #000;
        }
        .btn-back {
            background-color: #ddd;
            color: #333;
        }
        .btn-back:hover {
            background-color: #ccc;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
                max-width: 100%;
                width: 100%;
            }
            .actions {
                display: none;
            }
            .receipt-container::after {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="receipt-container">
        <div class="header">
            <h1>Laguna Guide</h1>
            <p>Official Receipt</p>
            <p><?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <div class="details">
            <div class="row">
                <span class="label">Receipt No:</span>
                <span class="value">#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="row">
                <span class="label">Customer:</span>
                <span class="value"><?php echo htmlspecialchars($booking['username']); ?></span>
            </div>
            <div class="row">
                <span class="label">Email:</span>
                <span class="value"><?php echo htmlspecialchars($booking['email']); ?></span>
            </div>
            
            <div class="divider"></div>

            <div class="row">
                <span class="label">Spot:</span>
                <span class="value"><?php echo htmlspecialchars($booking['spot_name']); ?></span>
            </div>
            <div class="row">
                <span class="label">Location:</span>
                <span class="value"><?php echo htmlspecialchars($booking['location']); ?></span>
            </div>
            <div class="row">
                <span class="label">Date of Visit:</span>
                <span class="value"><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
            </div>
            
            <div class="divider"></div>

            <div class="row">
                <span class="label">Adults:</span>
                <span class="value"><?php echo $booking['num_adults']; ?></span>
            </div>
            <div class="row">
                <span class="label">Children:</span>
                <span class="value"><?php echo $booking['num_children']; ?></span>
            </div>
            
            <div class="divider"></div>
            
            <div class="total-row">
                <span>Total Amount:</span>
                <span>₱<?php echo number_format($booking['total_price'], 2); ?></span>
            </div>

            <div class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                <?php echo htmlspecialchars($booking['status']); ?>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for booking with Laguna Guide!</p>
            <p>Please present this receipt upon arrival.</p>
            <p>www.lagunaguide.com</p>
        </div>

        <div class="actions">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print / Save PDF
            </button>
            <a href="user_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

</body>
</html>