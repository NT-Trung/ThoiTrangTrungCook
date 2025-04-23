<?php
session_start();
require_once '../config.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: admin_products.php");
    exit;
}

$productId = (int)$_GET['id'];

// Lấy thông tin sản phẩm
$sql = "SELECT ID, TenSP, HinhAnh, GiaBan FROM hanghoa WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    header("Location: admin_products.php");
    exit;
}
$product = $result->fetch_assoc();

// Xử lý cập nhật
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tenSP = trim($_POST['tenSP']);
    $hinhAnh = trim($_POST['hinhAnh']);
    $giaBan = (float)$_POST['giaBan'];

    if (empty($tenSP) || empty($hinhAnh) || $giaBan <= 0) {
        $error = "Vui lòng nhập đầy đủ thông tin hợp lệ.";
    } else {
        $sql = "UPDATE hanghoa SET TenSP = ?, HinhAnh = ?, GiaBan = ? WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdi", $tenSP, $hinhAnh, $giaBan, $productId);
        if ($stmt->execute()) {
            header("Location: admin_products.php");
            exit;
        } else {
            $error = "Có lỗi xảy ra khi cập nhật sản phẩm.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật sản phẩm</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-6">
        <h2 class="text-2xl font-bold mb-4">Cập nhật sản phẩm</h2>
        <?php if (isset($error)): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="admin_edit_product.php?id=<?php echo $productId; ?>">
            <div class="mb-4">
                <label class="block text-gray-700">Tên sản phẩm</label>
                <input type="text" name="tenSP" class="w-full p-2 border rounded" value="<?php echo htmlspecialchars($product['TenSP']); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">URL hình ảnh</label>
                <input type="text" name="hinhAnh" class="w-full p-2 border rounded" value="<?php echo htmlspecialchars($product['HinhAnh']); ?>" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Giá bán (VNĐ)</label>
                <input type="number" name="giaBan" class="w-full p-2 border rounded" value="<?php echo htmlspecialchars($product['GiaBan']); ?>" min="0" step="0.01" required>
            </div>
            <button type="submit" class="bg-blue-500 text-white p-2 rounded">Cập nhật</button>
            <a href="admin_products.php" class="ml-4 text-blue-500">Quay lại</a>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>