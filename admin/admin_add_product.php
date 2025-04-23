<?php
session_start();
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tenSP = trim($_POST['tenSP']);
    $giaBan = (float)$_POST['giaBan'];

    // Validate inputs
    if (empty($tenSP) || $giaBan <= 0) {
        $error = "Vui lòng nhập đầy đủ thông tin hợp lệ.";
    } elseif (empty($_FILES['hinhAnh']['name'])) {
        $error = "Vui lòng chọn một hình ảnh.";
    } else {
        // Handle image upload
        $image = $_FILES['hinhAnh'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Validate image
        if (!in_array($image['type'], $allowedTypes)) {
            $error = "Chỉ hỗ trợ định dạng JPEG, PNG, hoặc GIF.";
        } elseif ($image['size'] > $maxSize) {
            $error = "Hình ảnh không được lớn hơn 5MB.";
        } elseif ($image['error'] !== UPLOAD_ERR_OK) {
            $error = "Có lỗi xảy ra khi tải lên hình ảnh.";
        } else {
            // Read image data
            $imageData = file_get_contents($image['tmp_name']);

            // Insert into database
            $sql = "INSERT INTO hanghoa (TenSP, HinhAnh, GiaBan) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sbd", $tenSP, $imageData, $giaBan);
            if ($stmt->execute()) {
                header("Location: admin_products.php");
                exit;
            } else {
                $error = "Có lỗi xảy ra khi thêm sản phẩm.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>Thêm sản phẩm mới - Thời Trang TrungCook</title>
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
        }
        #cartPopup table { table-layout: fixed; width: 100%; }
        #cartPopup th, #cartPopup td { word-wrap: break-word; overflow: hidden; text-overflow: ellipsis; }
        /* Center the form */
        .form-container { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: calc(100vh - 80px); /* Adjust for header height */
        }
        .form-box {
            width: 100%;
            max-width: 500px; /* Limit width on larger screens */
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
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
    <div class="form-container">
        <div class="form-box">
            <h2 class="text-2xl font-bold mb-4 text-center">Thêm sản phẩm mới</h2>
            <?php if (isset($error)): ?>
                <p class="text-red-500 mb-4 text-center"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST" action="admin_add_product.php" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-gray-700">Tên sản phẩm</label>
                    <input type="text" name="tenSP" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Hình ảnh sản phẩm</label>
                    <input type="file" name="hinhAnh" accept="image/*" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Giá bán (VNĐ)</label>
                    <input type="number" name="giaBan" class="w-full p-2 border rounded" min="0" step="0.01" required>
                </div>
                <div class="flex justify-center space-x-4">
                    <button type="submit" class="bg-green-500 text-white p-2 rounded hover:bg-green-600">Thêm sản phẩm</button>
                    <a href="admin_products.php" class="bg-gray-500 text-white p-2 rounded hover:bg-gray-600">Quay lại</a>
                </div>
            </form>
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