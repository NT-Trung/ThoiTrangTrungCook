<?php
session_start();
require_once 'config.php';

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenTaiKhoan = trim($_POST['tenTaiKhoan']);
    $matKhau = trim($_POST['matKhau']);

    // Kiểm tra đầu vào
    if (empty($tenTaiKhoan) || empty($matKhau)) {
        $message = "Vui lòng điền đầy đủ tên tài khoản và mật khẩu.";
    } else {
        // Chuẩn bị truy vấn
        $stmt = $conn->prepare("SELECT ID_Account, TenTaiKhoan, MatKhau, Role FROM taikhoan WHERE TenTaiKhoan = ?");
        if ($stmt === false) {
            $message = "Lỗi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param("s", $tenTaiKhoan);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                // So sánh mật khẩu dạng plain text
                if ($matKhau === $user['MatKhau']) {
                    // Đăng nhập thành công
                    $_SESSION['user_id'] = $user['ID_Account'];
                    $_SESSION['user_name'] = $user['TenTaiKhoan'];
                    $_SESSION['role'] = $user['Role'];
                    header("Location: index.php");
                    exit;
                } else {
                    $message = "Mật khẩu không đúng.";
                }
            } else {
                $message = "Tên tài khoản không tồn tại.";
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Đăng Nhập</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 font-sans">
  <div class="container mx-auto p-6 max-w-md">
    <h1 class="text-2xl font-bold mb-6 text-center">Đăng Nhập</h1>
    <div class="bg-white p-6 rounded shadow">
      <?php if ($message): ?>
        <p class="mb-4 text-center text-red-600"><?php echo htmlspecialchars($message); ?></p>
      <?php endif; ?>
      <form method="POST" action="login.php">
        <div class="mb-4">
          <label for="tenTaiKhoan" class="block font-semibold mb-1">Tên tài khoản</label>
          <input type="text" id="tenTaiKhoan" name="tenTaiKhoan" required class="w-full p-2 border rounded" value="<?php echo isset($_POST['tenTaiKhoan']) ? htmlspecialchars($_POST['tenTaiKhoan']) : ''; ?>">
        </div>
        <div class="mb-4">
          <label for="matKhau" class="block font-semibold mb-1">Mật khẩu</label>
          <input type="password" id="matKhau" name="matKhau" required class="w-full p-2 border rounded">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
          Đăng Nhập
        </button>
      </form>
      <p class="mt-4 text-center">
        Chưa có tài khoản? <a href="register.php" class="text-blue-600 hover:underline">Đăng ký</a>
      </p>
    </div>
  </div>
</body>
</html>