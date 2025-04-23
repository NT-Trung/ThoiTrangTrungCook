<?php
session_start();
require_once 'config.php';
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenTaiKhoan = trim($_POST['tenTaiKhoan']);
    $matKhau = trim($_POST['matKhau']);
    $confirm_matKhau = trim($_POST['confirm_matKhau']);

    // Kiểm tra đầu vào
    if (empty($tenTaiKhoan) || empty($matKhau) || empty($confirm_matKhau)) {
        $message = "Nhập đầy đủ thông tin vào.";
    } elseif ($matKhau !== $confirm_matKhau) {
        $message = "Mật khẩu xác nhận sai kìa bây.";
    } elseif (strlen($matKhau) < 1) {
        $message = "Mật khẩu phải có ít nhất 1 ký tự bạn êy";
    } else {
        // Kiểm tra tên tài khoản đã tồn tại
        $stmt = $conn->prepare("SELECT ID_Account FROM taikhoan WHERE TenTaiKhoan = ?");
        if ($stmt === false) {
            $message = "Lỗi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param("s", $tenTaiKhoan);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $message = "Tên tài khoản đã tồn tại.";
            } else {
                // Lưu tài khoản với mật khẩu dạng plain text
                $stmt = $conn->prepare("INSERT INTO taikhoan (TenTaiKhoan, MatKhau, Role) VALUES (?, ?, 'user')");
                if ($stmt === false) {
                    $message = "Lỗi chuẩn bị truy vấn: " . $conn->error;
                } else {
                    $stmt->bind_param("ss", $tenTaiKhoan, $matKhau);
                    if ($stmt->execute()) {
                        // Lấy ID_Account và Role của tài khoản vừa tạo
                        $stmt->close();
                        $stmt = $conn->prepare("SELECT ID_Account, TenTaiKhoan, Role FROM taikhoan WHERE TenTaiKhoan = ?");
                        $stmt->bind_param("s", $tenTaiKhoan);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();

                        // Đăng nhập tự động
                        $_SESSION['user_id'] = $user['ID_Account'];
                        $_SESSION['user_name'] = $user['TenTaiKhoan'];
                        $_SESSION['role'] = $user['Role'];
                        header("Location: index.php");
                        exit;
                    } else {
                        $message = "Lỗi khi đăng ký: " . $stmt->error;
                    }
                }
                $stmt->close();
            }
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
  <title>Đăng Ký</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 font-sans">
  <div class="container mx-auto p-6 max-w-md">
    <h1 class="text-2xl font-bold mb-6 text-center">Đăng Ký Tài Khoản</h1>
    <div class="bg-white p-6 rounded shadow">
      <?php if ($message): ?>
        <p class="mb-4 text-center <?php echo strpos($message, 'thành công') !== false ? 'text-green-600' : 'text-red-600'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </p>
      <?php endif; ?>
      <form method="POST" action="register.php">
        <div class="mb-4">
          <label for="tenTaiKhoan" class="block font-semibold mb-1">Nhập tên vào đây</label>
          <input type="text" id="tenTaiKhoan" name="tenTaiKhoan" required class="w-full p-2 border rounded" value="<?php echo isset($_POST['tenTaiKhoan']) ? htmlspecialchars($_POST['tenTaiKhoan']) : ''; ?>">
        </div>
        <div class="mb-4">
          <label for="matKhau" class="block font-semibold mb-1">Còn này là mật khẩu</label>
          <input type="password" id="matKhau" name="matKhau" required class="w-full p-2 border rounded">
        </div>
        <div class="mb-4">
          <label for="confirm_matKhau" class="block font-semibold mb-1">Thằng này cũng là mật khẩu</label>
          <input type="password" id="confirm_matKhau" name="confirm_matKhau" required class="w-full p-2 border rounded">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
          Đăng Ký nào
        </button>
      </form>
      <p class="mt-4 text-center">
        Đã có tài khoản? <a href="login.php" class="text-blue-600 hover:underline">Đăng nhập đê</a>
      </p>
    </div>
  </div>
</body>
</html>