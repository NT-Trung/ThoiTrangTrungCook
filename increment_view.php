<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_POST['product_id'])) {
    $response['message'] = 'Thiếu ID sản phẩm.';
    echo json_encode($response);
    exit;
}

$product_id = (int)$_POST['product_id'];

// Increment the view count
$sql = "UPDATE hanghoa SET LuotXem = LuotXem + 1 WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);

if ($stmt->execute()) {
    // Fetch the updated view count
    $sql_select = "SELECT LuotXem FROM hanghoa WHERE ID = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $product_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $row = $result->fetch_assoc();
    
    $response['success'] = true;
    $response['new_view_count'] = $row['LuotXem'];
} else {
    $response['message'] = 'Lỗi khi cập nhật lượt xem: ' . $conn->error;
}

$stmt->close();
if (isset($stmt_select)) {
    $stmt_select->close();
}
$conn->close();

echo json_encode($response);
?>