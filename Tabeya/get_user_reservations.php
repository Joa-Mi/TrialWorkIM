<?php
/**
 * Get User's Reservations
 * Fetches reservations with ReservationStatus for the logged-in customer
 */

header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database connection
require_once(__DIR__ . '/api/config/db_config.php');

// Check if customer_id is provided
if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Customer ID is required'
    ]);
    exit;
}

$customerId = intval($_GET['customer_id']);

try {
    // Fetch reservations from reservations table
    // NOTE: Column list matches the current `reservations` table schema you provided.
    $sql = "SELECT 
                ReservationID,
                CustomerID,
                FullName,
                ContactNumber,
                ReservationType,
                EventType,
                EventDate,
                EventTime,
                NumberOfGuests,
                ReservationStatus,
                SpecialRequests,
                DeliveryOption,
                DeliveryAddress,
                ReservationDate,
                UpdatedDate
            FROM reservations 
            WHERE CustomerID = ?
            ORDER BY ReservationDate DESC, EventDate DESC, EventTime DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $customerId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $reservations = [];
    
    while ($row = $result->fetch_assoc()) {
        // Fetch items for this reservation
        $itemsSql = "SELECT ProductName, Quantity, UnitPrice 
                     FROM reservation_items 
                     WHERE ReservationID = ?";
        $itemsStmt = $conn->prepare($itemsSql);
        
        if ($itemsStmt) {
            $itemsStmt->bind_param("i", $row['ReservationID']);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            
            $items = [];
            while ($itemRow = $itemsResult->fetch_assoc()) {
                $items[] = [
                    'name' => $itemRow['ProductName'],
                    'quantity' => intval($itemRow['Quantity']),
                    'price' => floatval($itemRow['UnitPrice'])
                ];
            }
            
            $row['items'] = json_encode($items);
            $itemsStmt->close();
        } else {
            $row['items'] = json_encode([]);
        }
        
        $reservations[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'reservations' => $reservations,
        'count' => count($reservations)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving reservations: ' . $e->getMessage()
    ]);
    
    if (isset($conn)) {
        $conn->close();
    }
}
?>