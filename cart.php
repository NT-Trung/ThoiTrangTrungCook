<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? $_POST['product_id'] : null;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$size = isset($_POST['size']) ? $_POST['size'] : '';
$vi = isset($_POST['vi']) ? $_POST['vi'] : '';
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0;

// Combine size and vi into ThuocTinh (e.g., "M-Cam")
$thuocTinh = $size || $vi ? trim($size . ($size && $vi ? '-' : '') . $vi) : '';

if (!$product_id || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

// Check if product exists and has sufficient stock
$sql = "SELECT SL FROM hanghoa WHERE ID = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    exit;
}

$stmt->bind_param("s", $product_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    exit;
}

$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']);
    exit;
}

if ($product['SL'] < $quantity) {
    echo json_encode(['success' => false, 'message' => 'Số lượng tồn kho không đủ']);
    exit;
}

// Check if the item is already in the cart (using combined ThuocTinh)
$sql = "SELECT ID, SL FROM giohang WHERE ID_Account = ? AND ProductID = ? AND ThuocTinh = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    exit;
}

$stmt->bind_param("iss", $user_id, $product_id, $thuocTinh);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    exit;
}

$result = $stmt->get_result();
$cart_item = $result->fetch_assoc();
$stmt->close();

if ($cart_item) {
    // Update quantity
    $new_quantity = $cart_item['SL'] + $quantity;
    if ($new_quantity > $product['SL']) {
        echo json_encode(['success' => false, 'message' => 'Tổng số lượng vượt quá tồn kho']);
        exit;
    }

    $sql = "UPDATE giohang SET SL = ?, GiaBan = ? WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
        exit;
    }

    $stmt->bind_param("idi", $new_quantity, $price, $cart_item['ID']);
} else {
    // Insert new cart item with combined ThuocTinh
    $sql = "INSERT INTO giohang (ID_Account, ProductID, SL, ThuocTinh, GiaBan) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
        exit;
    }

    $stmt->bind_param("isisi", $user_id, $product_id, $quantity, $thuocTinh, $price);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm vào giỏ hàng']);
}

$stmt->close();
$conn->close();
?>