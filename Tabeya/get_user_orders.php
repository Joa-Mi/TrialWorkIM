<?php
/**
 * Get User's Orders
 * Fetches orders with WebsiteStatus for the logged-in customer
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
    // Fetch orders from orders table where OrderSource is 'Website'
    $sql = "SELECT 
                OrderID,
                CustomerID,
                OrderDate,
                OrderTime,
                OrderType,
                TotalAmount,
                WebsiteStatus,
                OrderStatus,
                DeliveryAddress,
                SpecialRequests,
                CreatedDate
            FROM orders 
            WHERE CustomerID = ? AND OrderSource = 'Website'
            ORDER BY CreatedDate DESC, OrderDate DESC, OrderTime DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $customerId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $orders = [];
    
    while ($row = $result->fetch_assoc()) {
        // Fetch items for this order
        $itemsSql = "SELECT ProductName, Quantity, UnitPrice 
                     FROM order_items 
                     WHERE OrderID = ?";
        $itemsStmt = $conn->prepare($itemsSql);
        
        if ($itemsStmt) {
            $itemsStmt->bind_param("i", $row['OrderID']);
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
        
        // Format dates for display
        if (!empty($row['OrderDate'])) {
            $date = new DateTime($row['OrderDate']);
            $row['OrderDate'] = $date->format('M d, Y');
        }
        
        // Format time for display
        if (!empty($row['OrderTime'])) {
            try {
                $time = new DateTime($row['OrderTime']);
                $row['OrderTime'] = $time->format('g:i A');
            } catch (Exception $e) {
                // Keep original time if parsing fails
            }
        }
        
        // Use WebsiteStatus if available, otherwise fall back to OrderStatus
        if (empty($row['WebsiteStatus']) && !empty($row['OrderStatus'])) {
            $row['WebsiteStatus'] = $row['OrderStatus'];
        }
        
        $orders[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving orders: ' . $e->getMessage()
    ]);
    
    if (isset($conn)) {
        $conn->close();
    }
}
?>