<?php
header('Content-Type: application/json');
require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$name = $input['name'] ?? '';
$size = $input['size'] ?? '';
$vi = $input['vi'] ?? '';

if (!$name) {
    echo json_encode(['success' => false, 'message' => 'Tên sản phẩm là bắt buộc']);
    exit;
}

// Combine size and vi into ThuocTinh (e.g., "M-Cam")
$thuocTinh = $size || $vi ? trim($size . ($size && $vi ? '-' : '') . $vi) : '';

$sql = "SELECT ID, TenSP, SL, ThuocTinh, HinhAnh, MoTaNgan, GiaBan 
        FROM hanghoa 
        WHERE TenSP = ? AND ThuocTinh = ? 
        LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu']);
    exit;
}

$stmt->bind_param("ss", $name, $thuocTinh);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if ($product) {
    // Split ThuocTinh back into size and vi for display purposes
    $attributes = explode('-', $product['ThuocTinh']);
    $product['Size'] = $attributes[0] ?? $product['ThuocTinh'];
    $product['Vi'] = $attributes[1] ?? '';
    echo json_encode(['success' => true, 'product' => $product]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm với các thuộc tính này']);
}

$stmt->close();
$conn->close();
?>