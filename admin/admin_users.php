<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once '../config.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Đếm số lượng sản phẩm trong giỏ hàng
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sqlCart = "SELECT SUM(SL) as total FROM giohang WHERE ID_Account = ?";
    $stmtCart = $conn->prepare($sqlCart);
    $stmtCart->bind_param("i", $user_id);
    $stmtCart->execute();
    $resultCart = $stmtCart->get_result();
    $cartCount = $resultCart && $resultCart->num_rows > 0 ? $resultCart->fetch_assoc()['total'] : 0;
}

// Kiểm tra kết nối cơ sở dữ liệu
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Xử lý thay đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $id = (int)$_POST['id'];
    $new_password = $_POST['new_password'];

    // Kiểm tra tài khoản có tồn tại và là user
    $sql = "SELECT role FROM taikhoan WHERE ID_Account = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Lỗi prepare (change_password): " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        die("Lỗi execute (change_password): " . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['role'] === 'user' && $id !== (int)$_SESSION['user_id']) {
            // Lưu mật khẩu dưới dạng văn bản thuần (không mã hóa)
            $sql = "UPDATE taikhoan SET MatKhau = ? WHERE ID_Account = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Lỗi prepare (update password): " . $conn->error);
            }
            $stmt->bind_param("si", $new_password, $id);
            if (!$stmt->execute()) {
                die("Lỗi execute (update password): " . $stmt->error);
            }
        }
    }
    header("Location: admin_users.php");
    exit;
}

// Xử lý xóa người dùng
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $sql = "SELECT role FROM taikhoan WHERE ID_Account = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Lỗi prepare (delete): " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        die("Lỗi execute (delete): " . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['role'] !== 'admin' && $id !== (int)$_SESSION['user_id']) {
            $sql = "DELETE FROM taikhoan WHERE ID_Account = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die("Lỗi prepare (delete execute): " . $conn->error);
            }
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                die("Lỗi execute (delete execute): " . $stmt->error);
            }
        }
    }
    header("Location: admin_users.php");
    exit;
}

// Lấy danh sách người dùng
$sql = "SELECT ID_Account, TenTaiKhoan, role, created_at FROM taikhoan ORDER BY ID_Account DESC";
$result = $conn->query($sql);
if ($result === false) {
    die("Lỗi truy vấn danh sách người dùng: " . $conn->error);
}
$users = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>Quản lý tài khoản - Thời Trang TrungCook</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"/>
    <style>
        .bg-xcost-dark { background-color: #1A252F; }
        .text-xcost-orange { color: #F68B1F; }
        .bg-xcost-orange { background-color: #F68B1F; }
        .border-xcost-orange { border-color: #F68B1F; }
        .hover\:bg-xcost-dark:hover { background-color: #2A3540; }
        .dropdown-content { display: none; position: absolute; background-color: #2A3540; min-width: 160px; z-index: 1; }
        .dropdown:hover .dropdown-content { display: block; }
        .dropdown-content a { color: white; padding: 12px 16px; text-decoration: none; display: block; }
        .dropdown-content a:hover { background-color: #F68B1F; }
        html, body { width: 100%; overflow-x: hidden; margin: 0; padding: 0; }
        @media (max-width: 640px) {
            .container { width: 100%; max-width: 100%; margin: 0; padding-left: 1rem; padding-right: 1rem; }
            #cartPopup { width: 100%; right: 0; }
            .users-table { display: none; }
            .users-mobile { display: block; }
            .user-item-mobile { margin-bottom: 1rem; padding: 1rem; background-color: white; border-radius: 0.5rem; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
            .user-item-mobile input[type="text"] { padding: 0.5rem; font-size: 0.875rem; width: 100%; max-width: 150px; }
            .user-item-mobile button, .user-item-mobile a { padding: 0.5rem 1rem; font-size: 0.875rem; display: inline-block; }
        }
        @media (min-width: 641px) {
            .users-table { display: table; }
            .users-mobile { display: none; }
        }
        #cartPopup table { table-layout: fixed; width: 100%; }
        #cartPopup th, #cartPopup td { word-wrap: break-word; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Header -->
    <header class="bg-xcost-dark text-white">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center">
                <h1 class="text-2xl font-bold text-xcost-orange">Thời trang TrungCook</h1>
            </div>
            <div class="hidden md:flex flex-1 mx-20">
                <input type="text" placeholder="Tìm kiếm..." class="w-full p-2 rounded-l-md border-none focus:ring-0 text-gray-800 text-sm search-input">
                <button class="bg-xcost-orange p-2 rounded-r-md search-button">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </div>
            <button id="mobileSearchToggle" class="md:hidden text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
            <div class="flex items-center space-x-4">
                <button id="cartToggle" class="flex items-center text-white hover:text-xcost-orange">
                    <svg class="w-6 h-6 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="text-sm">(<span id="cartCount"><?php echo $cartCount; ?></span>)</span>
                </button>
                <?php if (isset($_SESSION['user_name'])): ?>
                    <span class="text-sm text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <div class="dropdown relative">
                            <span class="text-sm text-white hover:text-xcost-orange cursor-pointer">Quản lý</span>
                            <div class="dropdown-content">
                                <a href="admin_products.php">Danh sách sản phẩm</a>
                                <a href="admin_add_product.php">Thêm sản phẩm mới</a>
                                <a href="admin_users.php">Quản lý tài khoản</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a href="../logout.php" class="text-sm text-white hover:text-xcost-orange">Đăng xuất</a>
                <?php else: ?>
                    <a href="../login.php" class="text-sm text-white hover:text-xcost-orange">Đăng nhập</a>
                    <a href="../register.php" class="text-sm text-white hover:text-xcost-orange">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
        <div id="mobileSearch" class="hidden md:hidden bg-xcost-dark px-4 pb-3">
            <div class="flex">
                <input type="text" placeholder="Tìm kiếm..." class="w-full p-2 rounded-l-md border-none focus:ring-0 text-gray-800 text-sm search-input">
                <button class="bg-xcost-orange p-2 rounded-r-md search-button">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Cart Popup -->
    <div id="cartPopup" class="hidden fixed top-16 right-0 w-full md:w-1/3 bg-white shadow-lg z-50 p-4 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Giỏ Hàng</h2>
            <button id="closeCart" class="text-gray-600 hover:text-gray-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="cartMessage" class="mb-4 hidden text-center text-green-600 font-semibold"></div>
        <div id="cartContent"></div>
        <div id="cartFooter" class="mt-4 hidden">
            <p class="text-lg font-semibold">Tổng cộng: <span id="cartTotal">0</span> đ</p>
            <a href="../cart-view.php" class="block bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 text-center mt-2">Xem giỏ hàng</a>
            <a href="#" class="block bg-xcost-orange text-white py-2 px-4 rounded hover:bg-orange-600 text-center mt-2">Thanh toán</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-6">
        <h2 class="text-2xl font-bold mb-4 text-center md:text-left">Quản lý tài khoản</h2>
        <a href="../index.php" class="bg-gray-500 text-white p-2 rounded mb-4 inline-block hover:bg-gray-600">Quay lại</a>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Danh sách Admin -->
            <div>
                <h3 class="text-xl font-semibold mb-2">Tài khoản Admin</h3>
                <!-- Desktop Table View -->
                <table class="users-table w-full border-collapse border">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border p-2">ID</th>
                            <th class="border p-2">Tên đăng nhập</th>
                            <th class="border p-2">Thời gian đăng ký</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hasAdmin = false;
                        foreach ($users as $user):
                            if ($user['role'] === 'admin'):
                                $hasAdmin = true;
                        ?>
                        <tr>
                            <td class="border p-2"><?= htmlspecialchars($user['ID_Account']); ?></td>
                            <td class="border p-2"><?= htmlspecialchars($user['TenTaiKhoan']); ?></td>
                            <td class="border p-2"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($user['created_at']))); ?></td>
                        </tr>
                        <?php endif; endforeach; ?>
                        <?php if (!$hasAdmin): ?>
                            <tr><td colspan="3" class="border p-2 text-center">Không có admin.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Mobile Card View -->
                <div class="users-mobile">
                    <?php
                    $hasAdmin = false;
                    foreach ($users as $user):
                        if ($user['role'] === 'admin'):
                            $hasAdmin = true;
                    ?>
                    <div class="user-item-mobile flex flex-col">
                        <h4 class="text-sm font-semibold"><?= htmlspecialchars($user['TenTaiKhoan']); ?></h4>
                        <p class="text-sm text-gray-600">ID: <?= htmlspecialchars($user['ID_Account']); ?></p>
                        <p class="text-sm text-gray-600">Thời gian đăng ký: <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($user['created_at']))); ?></p>
                    </div>
                    <?php endif; endforeach; ?>
                    <?php if (!$hasAdmin): ?>
                        <div class="text-center text-gray-600 p-4">Không có admin.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Danh sách User -->
            <div>
                <h3 class="text-xl font-semibold mb-2">Tài khoản User</h3>
                <!-- Desktop Table View -->
                <table class="users-table w-full border-collapse border">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border p-2">ID</th>
                            <th class="border p-2">Tên đăng nhập</th>
                            <th class="border p-2">Thời gian đăng ký</th>
                            <th class="border p-2">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hasUser = false;
                        foreach ($users as $user):
                            if ($user['role'] === 'user'):
                                $hasUser = true;
                        ?>
                        <tr>
                            <td class="border p-2"><?= htmlspecialchars($user['ID_Account']); ?></td>
                            <td class="border p-2"><?= htmlspecialchars($user['TenTaiKhoan']); ?></td>
                            <td class="border p-2"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($user['created_at']))); ?></td>
                            <td class="border p-2">
                                <div class="flex space-x-2">
                                    <?php if ($user['ID_Account'] !== $_SESSION['user_id']): ?>
                                        <form method="POST" action="admin_users.php" class="inline">
                                            <input type="hidden" name="action" value="change_password">
                                            <input type="hidden" name="id" value="<?= $user['ID_Account']; ?>">
                                            <input type="text" name="new_password" class="border rounded p-1 w-32" 
                                                placeholder="Mật khẩu mới" required>
                                            <button type="submit" class="bg-green-500 text-white p-1 rounded"
                                                    onclick="return confirm('Bạn có chắc muốn thay đổi mật khẩu?')">Đổi</button>
                                        </form>
                                        <a href="admin_users.php?action=delete&id=<?= $user['ID_Account']; ?>" 
                                        class="bg-red-500 text-white p-1 rounded"
                                        onclick="return confirm('Bạn có chắc muốn xóa tài khoản này?')">Xóa</a>
                                    <?php else: ?>
                                        <span class="text-gray-500">Không thể chỉnh sửa</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; endforeach; ?>
                        <?php if (!$hasUser): ?>
                            <tr><td colspan="4" class="border p-2 text-center">Không có user.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Mobile Card View -->
                <div class="users-mobile">
                    <?php
                    $hasUser = false;
                    foreach ($users as $user):
                        if ($user['role'] === 'user'):
                            $hasUser = true;
                    ?>
                    <div class="user-item-mobile flex flex-col">
                        <h4 class="text-sm font-semibold"><?= htmlspecialchars($user['TenTaiKhoan']); ?></h4>
                        <p class="text-sm text-gray-600">ID: <?= htmlspecialchars($user['ID_Account']); ?></p>
                        <p class="text-sm text-gray-600">Thời gian đăng ký: <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($user['created_at']))); ?></p>
                        <div class="mt-2">
                            <?php if ($user['ID_Account'] !== $_SESSION['user_id']): ?>
                                <form method="POST" action="admin_users.php" class="flex flex-col space-y-2">
                                    <input type="hidden" name="action" value="change_password">
                                    <input type="hidden" name="id" value="<?= $user['ID_Account']; ?>">
                                    <div>
                                        <label class="text-sm text-gray-700">Mật khẩu mới:</label>
                                        <input type="text" name="new_password" class="border rounded w-full" 
                                               placeholder="Mật khẩu mới" required>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button type="submit" class="bg-green-500 text-white rounded hover:bg-green-600"
                                                onclick="return confirm('Bạn có chắc muốn thay đổi mật khẩu?')">Đổi</button>
                                        <a href="admin_users.php?action=delete&id=<?= $user['ID_Account']; ?>" 
                                           class="bg-red-500 text-white rounded hover:bg-red-600"
                                           onclick="return confirm('Bạn có chắc muốn xóa tài khoản này?')">Xóa</a>
                                    </div>
                                </form>
                            <?php else: ?>
                                <span class="text-gray-500 text-sm">Không thể chỉnh sửa</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; endforeach; ?>
                    <?php if (!$hasUser): ?>
                        <div class="text-center text-gray-600 p-4">Không có user.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Header functionality
        document.getElementById('mobileSearchToggle').addEventListener('click', function () {
            const mobileSearch = document.getElementById('mobileSearch');
            mobileSearch.classList.toggle('hidden');
        });

        document.querySelectorAll('.search-button').forEach(button => {
            button.addEventListener('click', function () {
                const input = this.previousElementSibling;
                const searchQuery = input.value.trim();
                if (searchQuery) {
                    window.location.href = '../index.php?search=' + encodeURIComponent(searchQuery);
                } else {
                    window.location.href = '../index.php';
                }
            });
        });

        document.querySelectorAll('.search-input').forEach(input => {
            input.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const searchQuery = this.value.trim();
                    if (searchQuery) {
                        window.location.href = '../index.php?search=' + encodeURIComponent(searchQuery);
                    } else {
                        window.location.href = '../index.php';
                    }
                }
            });
        });

        // Cart popup handling
        const cartPopup = document.getElementById('cartPopup');
        const cartToggle = document.getElementById('cartToggle');
        const closeCart = document.getElementById('closeCart');

        cartToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            cartPopup.classList.toggle('hidden');
            if (!cartPopup.classList.contains('hidden')) {
                fetchCart();
            }
        });

        closeCart.addEventListener('click', function (e) {
            e.stopPropagation();
            cartPopup.classList.add('hidden');
        });

        document.addEventListener('click', function (e) {
            if (!cartPopup.classList.contains('hidden') && 
                !cartPopup.contains(e.target) && 
                !cartToggle.contains(e.target)) {
                cartPopup.classList.add('hidden');
            }
        });

        cartPopup.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        function fetchCart() {
            fetch('../get_cart.php')
                .then(response => response.json())
                .then(data => {
                    const cartContent = document.getElementById('cartContent');
                    const cartFooter = document.getElementById('cartFooter');
                    const cartTotal = document.getElementById('cartTotal');
                    const cartCount = document.getElementById('cartCount');
                    const cartMessage = document.getElementById('cartMessage');

                    if (!data.success) {
                        cartContent.innerHTML = '<p class="text-gray-600">' + data.message + '</p>';
                        cartFooter.classList.add('hidden');
                        return;
                    }

                    if (data.cartItems.length === 0) {
                        cartContent.innerHTML = '<p class="text-gray-600">Giỏ hàng của bạn đang trống.</p>';
                        cartFooter.classList.add('hidden');
                    } else {
                        cartContent.innerHTML = `
                            <table class="w-full table-auto border-collapse">
                                <thead>
                                    <tr class="bg-gray-200">
                                        <th class="px-2 py-1 text-left text-sm">Hình ảnh</th>
                                        <th class="px-2 py-1 text-left text-sm">Tên sản phẩm</th>
                                        <th class="px-2 py-1 text-left text-sm">Số lượng</th>
                                        <th class="px-2 py-1 text-left text-sm">Giá</th>
                                        <th class="px-2 py-1 text-left text-sm"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.cartItems.map(item => `
                                        <tr class="border-b" data-cart-id="${item.ID}">
                                            <td class="px-2 py-1">
                                                <img src="${item.HinhAnh}" alt="${item.TenSP}" class="w-12 h-12 object-cover rounded">
                                            </td>
                                            <td class="px-2 py-1 text-sm">${item.TenSP} (${item.ThuocTinh})</td>
                                            <td class="px-2 py-1">
                                                <input type="number" class="quantity-input w-12 p-1 border rounded text-sm" value="${item.SL}" min="1" max="${item.Stock}" data-cart-id="${item.ID}">
                                                <button class="update-quantity bg-blue-500 text-white px-2 py-1 rounded ml-1 hover:bg-blue-600 text-xs">Cập nhật</button>
                                            </td>
                                            <td class="px-2 py-1 text-sm cart-item-total">${Number(item.SL * item.GiaBan).toLocaleString('vi-VN')} đ</td>
                                            <td class="px-2 py-1">
                                                <button class="delete-item bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 text-xs" data-cart-id="${item.ID}">Xóa</button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                        cartFooter.classList.remove('hidden');
                        cartTotal.textContent = Number(data.total).toLocaleString('vi-VN');
                        cartCount.textContent = data.cartCount;

                        document.querySelectorAll('.update-quantity').forEach(button => {
                            button.addEventListener('click', function () {
                                const cartId = this.parentElement.parentElement.dataset.cartId;
                                const quantityInput = this.parentElement.querySelector('.quantity-input');
                                const newQuantity = parseInt(quantityInput.value);
                                const maxQuantity = parseInt(quantityInput.max);

                                if (newQuantity < 1) {
                                    showCartMessage('Số lượng phải lớn hơn 0!', 'text-red-600');
                                    return;
                                }
                                if (newQuantity > maxQuantity) {
                                    showCartMessage(`Số lượng không được vượt quá ${maxQuantity}!`, 'text-red-600');
                                    return;
                                }

                                fetch('../update_cart.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'update', cart_id: cartId, quantity: newQuantity })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        fetchCart();
                                        showCartMessage('Cập nhật số lượng thành công!', 'text-green-600');
                                    } else {
                                        showCartMessage(data.message || 'Lỗi khi cập nhật số lượng!', 'text-red-600');
                                    }
                                })
                                .catch(error => {
                                    showCartMessage('Lỗi kết nối: ' + error.message, 'text-red-600');
                                });
                            });
                        });

                        document.querySelectorAll('.delete-item').forEach(button => {
                            button.addEventListener('click', function () {
                                const cartId = this.dataset.cartId;
                                if (confirm('Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?')) {
                                    fetch('../update_cart.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ action: 'delete', cart_id: cartId })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            fetchCart();
                                            showCartMessage('Đã xóa sản phẩm khỏi giỏ hàng!', 'text-green-600');
                                        } else {
                                            showCartMessage(data.message || 'Lỗi khi xóa sản phẩm!', 'text-red-600');
                                        }
                                    })
                                    .catch(error => {
                                        showCartMessage('Lỗi kết nối: ' + error.message, 'text-red-600');
                                    });
                                }
                            });
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching cart:', error);
                    document.getElementById('cartContent').innerHTML = '<p class="text-gray-600">Đã xảy ra lỗi khi tải giỏ hàng.</p>';
                });
        }

        function showCartMessage(message, className) {
            const messageDiv = document.getElementById('cartMessage');
            messageDiv.classList.remove('hidden', 'text-green-600', 'text-red-600');
            messageDiv.classList.add(className);
            messageDiv.textContent = message;
            setTimeout(() => {
                messageDiv.classList.add('hidden');
                messageDiv.textContent = '';
            }, 3000);
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>