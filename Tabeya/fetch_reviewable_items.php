<?php
/**
 * Fetch Reviewable Items for Customer
 * Returns orders and reservations that can be reviewed
 */

header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/api/config/db_config.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        die(json_encode(["success" => false, "message" => "GET required"]));
    }

    $customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

    if ($customerId <= 0) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => "Invalid customer ID"]));
    }

    // Fetch completed orders with items
    $orderSql = "SELECT 
                    o.OrderID,
                    o.OrderDate,
                    o.TotalAmount,
                    o.OrderStatus,
                    GROUP_CONCAT(CONCAT(oi.Quantity, 'x ', oi.ProductName) SEPARATOR ', ') AS Items,
                    CASE WHEN cf.FeedbackID IS NOT NULL THEN 1 ELSE 0 END AS HasReview
                FROM orders o
                LEFT JOIN order_items oi ON o.OrderID = oi.OrderID
                LEFT JOIN customer_feedback cf ON o.OrderID = cf.OrderID AND cf.CustomerID = ?
                WHERE o.CustomerID = ? 
                AND o.OrderStatus = 'Completed'
                AND o.OrderDate >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY o.OrderID
                ORDER BY o.OrderDate DESC
                LIMIT 10";

    $stmt = $conn->prepare($orderSql);
    $stmt->bind_param("ii", $customerId, $customerId);
    $stmt->execute();
    $ordersResult = $stmt->get_result();
    $orders = [];

    while ($row = $ordersResult->fetch_assoc()) {
        $orders[] = [
            'id' => $row['OrderID'],
            'date' => $row['OrderDate'],
            'total' => floatval($row['TotalAmount']),
            'status' => $row['OrderStatus'],
            'items' => $row['Items'],
            'hasReview' => intval($row['HasReview']) === 1
        ];
    }
    $stmt->close();

    // Fetch confirmed reservations with items
    $reservationSql = "SELECT 
                        r.ReservationID,
                        r.EventDate,
                        r.EventType,
                        r.NumberOfGuests,
                        GROUP_CONCAT(CONCAT(ri.Quantity, 'x ', ri.ProductName) SEPARATOR ', ') AS Items,
                        SUM(ri.TotalPrice) AS TotalAmount,
                        CASE WHEN cf.FeedbackID IS NOT NULL THEN 1 ELSE 0 END AS HasReview
                    FROM reservations r
                    LEFT JOIN reservation_items ri ON r.ReservationID = ri.ReservationID
                    LEFT JOIN customer_feedback cf ON r.ReservationID = cf.ReservationID AND cf.CustomerID = ?
                    WHERE r.CustomerID = ? 
                    AND r.ReservationStatus = 'Confirmed'
                    AND r.EventDate >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    GROUP BY r.ReservationID
                    ORDER BY r.EventDate DESC
                    LIMIT 10";

    $stmt = $conn->prepare($reservationSql);
    $stmt->bind_param("ii", $customerId, $customerId);
    $stmt->execute();
    $reservationsResult = $stmt->get_result();
    $reservations = [];

    while ($row = $reservationsResult->fetch_assoc()) {
        $reservations[] = [
            'id' => $row['ReservationID'],
            'date' => $row['EventDate'],
            'eventType' => $row['EventType'],
            'guests' => intval($row['NumberOfGuests']),
            'items' => $row['Items'],
            'total' => floatval($row['TotalAmount']),
            'hasReview' => intval($row['HasReview']) === 1
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'reservations' => $reservations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>