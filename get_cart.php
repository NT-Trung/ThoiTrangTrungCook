<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Bạn cần đăng nhập để xem giỏ hàng', 'cartItems' => [], 'total' => 0, 'cartCount' => 0];

if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT gh.ID, gh.ProductID, gh.SL, hh.TenSP, hh.HinhAnh, hh.GiaBan, hh.ThuocTinh, hh.SL as Stock
        FROM giohang gh
        JOIN hanghoa hh ON gh.ProductID = hh.ID
        WHERE gh.ID_Account = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cartItems = [];
$total = 0;
while ($row = $result->fetch_assoc()) {
    $cartItems[] = $row;
    $total += $row['SL'] * $row['GiaBan'];
}

$response['success'] = true;
$response['message'] = 'Lấy giỏ hàng thành công';
$response['cartItems'] = $cartItems;
$response['total'] = $total;
$response['cartCount'] = array_sum(array_column($cartItems, 'SL'));

echo json_encode($response);
$stmt->close();
$conn->close();
?>