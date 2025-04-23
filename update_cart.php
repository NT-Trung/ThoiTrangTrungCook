<?php
session_start();
require_once 'config.php';

// Thiết lập header để trả về JSON
header('Content-Type: application/json');

// Tắt hiển thị lỗi PHP (chỉ nên tắt trong production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Khởi tạo phản hồi mặc định
$response = ['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại sau'];

// Kiểm tra nếu người dùng chưa đăng nhập
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Bạn cần đăng nhập để thực hiện thao tác này';
    echo json_encode($response);
    exit;
}

// Lấy dữ liệu từ request
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['action'])) {
    $response['message'] = 'Dữ liệu không hợp lệ';
    echo json_encode($response);
    exit;
}

$action = $input['action'];
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'add') {
        // Thêm sản phẩm vào giỏ hàng
        $product_id = $input['product_id'] ?? null;
        $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;

        if (!$product_id || $quantity < 1) {
            $response['message'] = 'Sản phẩm hoặc số lượng không hợp lệ';
            echo json_encode($response);
            exit;
        }

        // Kiểm tra số lượng tồn kho
        $sqlCheckStock = "SELECT SL FROM hanghoa WHERE ID = ?";
        $stmtCheckStock = $conn->prepare($sqlCheckStock);
        $stmtCheckStock->bind_param("s", $product_id);
        $stmtCheckStock->execute();
        $resultStock = $stmtCheckStock->get_result();
        if ($resultStock->num_rows === 0) {
            $response['message'] = 'Sản phẩm không tồn tại';
            echo json_encode($response);
            exit;
        }

        $stock = $resultStock->fetch_assoc()['SL'];
        if ($quantity > $stock) {
            $response['message'] = "Số lượng vượt quá tồn kho ($stock)";
            echo json_encode($response);
            exit;
        }

        // Kiểm tra xem sản phẩm đã có trong giỏ hàng chưa
        $sqlCheckCart = "SELECT ID, SL FROM giohang WHERE ID_Account = ? AND ProductID = ?";
        $stmtCheckCart = $conn->prepare($sqlCheckCart);
        $stmtCheckCart->bind_param("is", $user_id, $product_id);
        $stmtCheckCart->execute();
        $resultCart = $stmtCheckCart->get_result();

        if ($resultCart->num_rows > 0) {
            // Cập nhật số lượng nếu sản phẩm đã có trong giỏ hàng
            $row = $resultCart->fetch_assoc();
            $newQuantity = $row['SL'] + $quantity;
            if ($newQuantity > $stock) {
                $response['message'] = "Tổng số lượng vượt quá tồn kho ($stock)";
                echo json_encode($response);
                exit;
            }

            $sqlUpdate = "UPDATE giohang SET SL = ? WHERE ID = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("ii", $newQuantity, $row['ID']);
            $stmtUpdate->execute();
        } else {
            // Thêm sản phẩm mới vào giỏ hàng
            $sqlInsert = "INSERT INTO giohang (ID_Account, ProductID, SL) VALUES (?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->bind_param("isi", $user_id, $product_id, $quantity);
            $stmtInsert->execute();
        }

        $response['success'] = true;
        $response['message'] = 'Thêm sản phẩm thành công';

    } elseif ($action === 'update') {
        // Cập nhật số lượng sản phẩm trong giỏ hàng
        $cart_id = $input['cart_id'] ?? null;
        $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;

        if (!$cart_id || $quantity < 1) {
            $response['message'] = 'Dữ liệu không hợp lệ';
            echo json_encode($response);
            exit;
        }

        // Kiểm tra giỏ hàng
        $sqlCheckCart = "SELECT ProductID FROM giohang WHERE ID = ? AND ID_Account = ?";
        $stmtCheckCart = $conn->prepare($sqlCheckCart);
        $stmtCheckCart->bind_param("ii", $cart_id, $user_id);
        $stmtCheckCart->execute();
        $resultCart = $stmtCheckCart->get_result();

        if ($resultCart->num_rows === 0) {
            $response['message'] = 'Sản phẩm không tồn tại trong giỏ hàng';
            echo json_encode($response);
            exit;
        }

        $cartItem = $resultCart->fetch_assoc();
        $product_id = $cartItem['ProductID'];

        // Kiểm tra số lượng tồn kho
        $sqlCheckStock = "SELECT SL FROM hanghoa WHERE ID = ?";
        $stmtCheckStock = $conn->prepare($sqlCheckStock);
        $stmtCheckStock->bind_param("s", $product_id);
        $stmtCheckStock->execute();
        $resultStock = $stmtCheckStock->get_result();
        if ($resultStock->num_rows === 0) {
            $response['message'] = 'Sản phẩm không tồn tại';
            echo json_encode($response);
            exit;
        }

        $stock = $resultStock->fetch_assoc()['SL'];
        if ($quantity > $stock) {
            $response['message'] = "Số lượng vượt quá tồn kho ($stock)";
            echo json_encode($response);
            exit;
        }

        // Cập nhật số lượng
        $sqlUpdate = "UPDATE giohang SET SL = ? WHERE ID = ? AND ID_Account = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("iii", $quantity, $cart_id, $user_id);
        $stmtUpdate->execute();

        $response['success'] = true;
        $response['message'] = 'Cập nhật số lượng thành công';

    } elseif ($action === 'delete') {
        // Xóa sản phẩm khỏi giỏ hàng
        $cart_id = $input['cart_id'] ?? null;

        if (!$cart_id) {
            $response['message'] = 'Dữ liệu không hợp lệ';
            echo json_encode($response);
            exit;
        }

        $sqlDelete = "DELETE FROM giohang WHERE ID = ? AND ID_Account = ?";
        $stmtDelete = $conn->prepare($sqlDelete);
        $stmtDelete->bind_param("ii", $cart_id, $user_id);
        $stmtDelete->execute();

        $response['success'] = true;
        $response['message'] = 'Xóa sản phẩm thành công';
    } else {
        $response['message'] = 'Hành động không hợp lệ';
    }
} catch (Exception $e) {
    $response['message'] = 'Lỗi: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>