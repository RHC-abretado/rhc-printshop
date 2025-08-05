<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only Managers and Super Admins may access
if (
    !isset($_SESSION['logged_in'], $_SESSION['role'])
    || $_SESSION['logged_in'] !== true
    || !in_array($_SESSION['role'], ['Manager', 'Super Admin'], true)
) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once 'assets/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $category = trim($input['category'] ?? '');
            $itemName = trim($input['item_name'] ?? '');
            $price = trim($input['price'] ?? '');
            
            if (empty($category) || empty($itemName) || empty($price)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }
            
            // Get the highest display_order for this category
            $orderStmt = $pdo->prepare("SELECT MAX(display_order) FROM pricing_items WHERE category = :category");
            $orderStmt->execute([':category' => $category]);
            $maxOrder = $orderStmt->fetchColumn() ?: 0;
            
            // Insert the new item
            $insertStmt = $pdo->prepare("
                INSERT INTO pricing_items (category, item_name, price, display_order) 
                VALUES (:category, :item_name, :price, :display_order)
            ");
            $insertStmt->execute([
                ':category' => $category,
                ':item_name' => $itemName,
                ':price' => $price,
                ':display_order' => $maxOrder + 1
            ]);
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log (username, event, details) 
                VALUES (:user, 'add_pricing_item', :details)
            ");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Added pricing item: {$itemName} in {$category} - {$price}"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Pricing item added successfully.']);
            break;
            
        case 'edit':
            $id = (int)($input['id'] ?? 0);
            $category = trim($input['category'] ?? '');
            $itemName = trim($input['item_name'] ?? '');
            $price = trim($input['price'] ?? '');
            
            if ($id <= 0 || empty($category) || empty($itemName) || empty($price)) {
                echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
                exit;
            }
            
            // Update the item
            $updateStmt = $pdo->prepare("
                UPDATE pricing_items 
                SET category = :category, item_name = :item_name, price = :price 
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':category' => $category,
                ':item_name' => $itemName,
                ':price' => $price,
                ':id' => $id
            ]);
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log (username, event, details) 
                VALUES (:user, 'update_pricing_item', :details)
            ");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Updated pricing item ID {$id}: {$itemName} in {$category} - {$price}"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Pricing item updated successfully.']);
            break;
            
        case 'delete':
            $id = (int)($input['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid item ID.']);
                exit;
            }
            
            // Get the item details for logging
            $getStmt = $pdo->prepare("SELECT category, item_name, price FROM pricing_items WHERE id = :id");
            $getStmt->execute([':id' => $id]);
            $itemDetails = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$itemDetails) {
                echo json_encode(['success' => false, 'message' => 'Pricing item not found.']);
                exit;
            }
            
            // Delete the item
            $deleteStmt = $pdo->prepare("DELETE FROM pricing_items WHERE id = :id");
            $deleteStmt->execute([':id' => $id]);
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log (username, event, details) 
                VALUES (:user, 'delete_pricing_item', :details)
            ");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Deleted pricing item: {$itemDetails['item_name']} from {$itemDetails['category']}"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Pricing item deleted successfully.']);
            break;
            
            case 'edit_category':
            $oldName = trim($input['old_name'] ?? '');
            $newName = trim($input['new_name'] ?? '');
            
            if (empty($oldName) || empty($newName)) {
                echo json_encode(['success' => false, 'message' => 'Both old and new category names are required.']);
                exit;
            }
            
            if ($oldName === $newName) {
                echo json_encode(['success' => false, 'message' => 'No changes detected.']);
                exit;
            }
            
            // Check if new name already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM pricing_items WHERE category = :new_name");
            $checkStmt->execute([':new_name' => $newName]);
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'A category with that name already exists.']);
                exit;
            }
            
            // Update all items in the category
            $updateStmt = $pdo->prepare("UPDATE pricing_items SET category = :new_name WHERE category = :old_name");
            $updateStmt->execute([
                ':new_name' => $newName,
                ':old_name' => $oldName
            ]);
            
            $updatedCount = $updateStmt->rowCount();
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log (username, event, details) 
                VALUES (:user, 'edit_pricing_category', :details)
            ");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Renamed category '{$oldName}' to '{$newName}' ({$updatedCount} items affected)"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Category renamed successfully.']);
            break;
            
        case 'add_category':
            $categoryName = trim($input['category_name'] ?? '');
            $firstItemName = trim($input['first_item_name'] ?? '');
            $firstItemPrice = trim($input['first_item_price'] ?? '');
            
            if (empty($categoryName)) {
                echo json_encode(['success' => false, 'message' => 'Category name is required.']);
                exit;
            }
            
            // Check if category already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM pricing_items WHERE category = :category");
            $checkStmt->execute([':category' => $categoryName]);
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'A category with that name already exists.']);
                exit;
            }
            
            // Add default headers for new category
            $headerStmt = $pdo->prepare("
                INSERT INTO pricing_headers (category, header1, header2) 
                VALUES (:category, 'Item', 'Price')
            ");
            $headerStmt->execute([':category' => $categoryName]);
            
            // If both first item name and price are provided, add the first item
            if (!empty($firstItemName) && !empty($firstItemPrice)) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO pricing_items (category, item_name, price, display_order) 
                    VALUES (:category, :item_name, :price, 1)
                ");
                $insertStmt->execute([
                    ':category' => $categoryName,
                    ':item_name' => $firstItemName,
                    ':price' => $firstItemPrice
                ]);
                
                $logDetails = "Created new category '{$categoryName}' with first item: {$firstItemName} - {$firstItemPrice}";
            } else {
                $logDetails = "Created new empty category '{$categoryName}'";
            }
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log (username, event, details) 
                VALUES (:user, 'add_pricing_category', :details)
            ");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => $logDetails
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Category created successfully.']);
            break;
            
        case 'delete_category':
            $category = trim($input['category'] ?? '');
            
            if (empty($category)) {
                echo json_encode(['success' => false, 'message' => 'Category name is required.']);
                exit;
            }
            
            // Get count of items that will be deleted
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pricing_items WHERE category = :category");
            $countStmt->execute([':category' => $category]);
            $itemCount = $countStmt->fetchColumn();
            
            // Delete all items in the category
            $deleteStmt = $pdo->prepare("DELETE FROM pricing_items WHERE category = :category");
            $deleteStmt->execute([':category' => $category]);
            
            // Delete headers for the category
            $deleteHeaderStmt = $pdo->prepare("DELETE FROM pricing_headers WHERE category = :category");
            $deleteHeaderStmt->execute([':category' => $category]);
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log (username, event, details) 
                VALUES (:user, 'delete_pricing_category', :details)
            ");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Deleted category '{$category}' and {$itemCount} items"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Category and all its items deleted successfully.']);
            break;
            
            case 'edit_headers':
            $category = trim($input['category'] ?? '');
            $header1 = trim($input['header1'] ?? '');
            $header2 = trim($input['header2'] ?? '');
            
            if (empty($category) || empty($header1) || empty($header2)) {
                echo json_encode(['success' => false, 'message' => 'Category and both headers are required.']);
                exit;
            }
            
            // Update or insert headers
            $updateStmt = $pdo->prepare("
                INSERT INTO pricing_headers (category, header1, header2) 
                VALUES (:category, :header1, :header2)
                ON DUPLICATE KEY UPDATE 
                header1 = VALUES(header1), 
                header2 = VALUES(header2),
                updated_at = CURRENT_TIMESTAMP
            ");
            $updateStmt->execute([
                ':category' => $category,
                ':header1' => $header1,
                ':header2' => $header2
            ]);
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO activity_log (username, event, details) 
                VALUES (:user, 'edit_pricing_headers', :details)
            ");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Updated headers for '{$category}' to: '{$header1}' | '{$header2}'"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Headers updated successfully.']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>