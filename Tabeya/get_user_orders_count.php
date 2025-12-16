<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Use centralized DB config (same as get_user_orders.php)
require_once(__DIR__ . '/api/config/db_config.php');

// Get customer_id from GET parameter (keep naming consistent with get_user_orders.php)
$customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customerId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID'
    ]);
    exit();
}

// Count orders for this customer where the source is Website
// (Matches the filtering used in get_user_orders.php)
$sql = "SELECT COUNT(*) AS total_orders
        FROM orders
        WHERE CustomerID = ?
          AND OrderSource = 'Website'";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Query preparation failed: ' . $conn->error
    ]);
    exit();
}

$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'total_orders' => intval($row['total_orders']),
        'customer_id' => $customerId
    ]);
} else {
    // No orders found, return 0
    echo json_encode([
        'success' => true,
        'total_orders' => 0,
        'customer_id' => $customerId
    ]);
}

$stmt->close();
$conn->close();
?>