<?php
session_start();
require_once 'config.php';

// Count cart items
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>Giỏ Hàng - Thời Trang TrungCook</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"/>
    <style>
        :root {
            --xcost-dark: #1A252F;
            --xcost-orange: #F68B1F;
            --xcost-blue: #4A90E2;
            --text-gray: #4B5563;
            --border-gray: #E5E7EB;
        }

        .bg-xcost-dark { background-color: var(--xcost-dark); }
        .text-xcost-orange { color: var(--xcost-orange); }
        .bg-xcost-orange { background-color: var(--xcost-orange); }
        .border-xcost-orange { border-color: var(--xcost-orange); }
        .bg-xcost-blue { background-color: var(--xcost-blue); }
        .hover\:bg-xcost-orange:hover { background-color: #E07B00; }
        .hover\:bg-xcost-blue:hover { background-color: #357ABD; }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--text-gray);
        }

        /* Header and Navigation */
        .logo-link { text-decoration: none; color: var(--xcost-orange); }
        .logo-link:hover { color: var(--xcost-orange); }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--xcost-dark);
            min-width: 160px;
            z-index: 10;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .dropdown:hover .dropdown-content { display: block; }
        .dropdown-content a {
            color: white;
            padding: 10px 14px;
            text-decoration: none;
            display: block;
            font-size: 0.875rem;
        }
        .dropdown-content a:hover { background-color: var(--xcost-orange); }

        /* Cart Table */
        .cart-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .cart-table th {
            background-color: #F9FAFB;
            color: var(--text-gray);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
        }
        .cart-table td {
            padding: 1rem;
            border-top: 1px solid var(--border-gray);
            vertical-align: middle;
        }
        .cart-table tr:hover {
            background-color: #F9FAFB;
        }
        .cart-table img {
            border-radius: 4px;
            object-fit: cover;
        }
        .quantity-col {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .quantity-input {
            width: 60px;
            padding: 0.5rem;
            border: 1px solid var(--border-gray);
            border-radius: 4px;
            text-align: center;
        }
        .update-quantity, .delete-item {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }
        .update-quantity { background-color: var(--xcost-blue); }
        .delete-item { background-color: #EF4444; }
        .update-quantity:hover { background-color: #357ABD; }
        .delete-item:hover { background-color: #DC2626; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
        }
        .modal-content {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            color: #6B7280;
            cursor: pointer;
            transition: color 0.3s;
        }
        .modal-close:hover { color: var(--text-gray); }
        .modal-content h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 1rem;
            text-align: center;
        }
        .modal-content .total {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--xcost-orange);
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .modal-buttons button {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }
        .modal-buttons button:hover {
            transform: translateY(-2px);
        }
        .pay-button { background-color: #10B981; }
        .order-button { background-color: var(--xcost-blue); }
        .pay-button:hover { background-color:rgb(26, 217, 255); }
        .order-button:hover { background-color:rgb(207, 255, 16); }

        /* Success Message */
        .success {
            color: #1F2937;
            font-size: 0.875rem;
        }
        .success .logo img {
            width: 100px;
            margin: 0 auto 1rem;
        }
        .success .store-info {
            text-align: center;
            margin-bottom: 1rem;
        }
        .success .store-info .store-name {
            font-size: 1.125rem;
            font-weight: 700;
        }
        .success .store-info p {
            font-size: 0.875rem;
            color: #4B5563;
        }
        .success .invoice-title {
            font-size: 1.25rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1rem;
        }
        .success .invoice-details p {
            font-size: 0.875rem;
            margin: 0.25rem 0;
        }
        .success .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .success .items-table th,
        .success .items-table td {
            border-bottom: 1px solid var(--border-gray);
            padding: 0.75rem;
            font-size: 0.875rem;
            text-align: left;
        }
        .success .items-table th {
            font-weight: 600;
            background-color: #F9FAFB;
        }
        .success .totals p {
            font-size: 0.875rem;
            margin: 0.25rem 0;
        }
        .success .totals p span {
            font-weight: 600;
        }
        .error {
            color: #EF4444;
            font-size: 1rem;
            text-align: center;
        }
        .link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--xcost-orange);
            text-decoration: none;
            font-size: 0.875rem;
        }
        .link:hover { color: #E07B00; }

        /* Mobile Styles */
        @media (max-width: 640px) {
            .container { padding: 0.75rem; }
            .cart-table th, .cart-table td {
                font-size: 0.75rem;
                padding: 0.5rem;
            }
            .cart-table img {
                width: 48px;
                height: 48px;
            }
            .quantity-input { width: 48px; }
            .update-quantity, .delete-item {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            .modal-content {
                width: 95%;
                padding: 1rem;
                max-height: 90vh;
            }
            .modal-content h2 { font-size: 1.25rem; }
            .modal-content .total { font-size: 1.125rem; }
            .modal-buttons button {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
            .success .logo img { width: 80px; }
            .success .store-info .store-name { font-size: 1rem; }
            .success .invoice-title { font-size: 1.125rem; }
            .mobile-nav-menu {
                display: none;
                position: absolute;
                top: 60px;
                right: 0;
                background-color: var(--xcost-dark);
                width: 200px;
                z-index: 10;
                padding: 1rem;
                border-radius: 0 0 0 8px;
            }
            .mobile-nav-menu.active { display: block; }
            .mobile-nav-menu a, .mobile-nav-menu span {
                display: block;
                color: white;
                padding: 0.5rem 0;
                text-decoration: none;
                font-size: 0.875rem;
            }
            .mobile-nav-menu a:hover { color: var(--xcost-orange); }
            .mobile-nav-menu .dropdown-content {
                position: static;
                background-color: #2A3540;
                min-width: 100%;
                padding-left: 1rem;
            }
            .nav-items { display: none; }
            .mobile-nav-toggle { display: block; }
            .mobile-username {
                display: block;
                max-width: 80px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-size: 0.875rem;
            }
        }
        @media (min-width: 641px) {
            .nav-items { display: flex !important; }
            .mobile-nav-toggle, .mobile-nav-menu, .mobile-username { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-xcost-dark text-white">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <a href="index.php" class="logo-link">
                <h1 class="text-2xl font-bold text-xcost-orange">Thời Trang TrungCook</h1>
            </a>
            <div class="hidden md:flex flex-1 mx-6">
                <input type="text" placeholder="Tìm kiếm sản phẩm..." class="w-full p-2 rounded-l-md border-none focus:ring-2 focus:ring-xcost-orange text-gray-800" />
                <button class="bg-xcost-orange p-2 rounded-r-md hover:bg-orange-600">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </div>
            <button id="mobileSearchToggle" class="md:hidden text-white mr-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
            <div class="flex items-center space-x-3">
                <a href="cart-view.php" class="flex items-center text-white hover:text-xcost-orange">
                    <svg class="w-6 h-6 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="text-sm">(<span id="cartCount"><?php echo $cartCount; ?></span>)</span>
                </a>
                <?php if (isset($_SESSION['user_name'])): ?>
                    <span class="mobile-username text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <?php endif; ?>
                <div class="nav-items flex items-center space-x-4">
                    <?php if (isset($_SESSION['user_name'])): ?>
                        <span class="text-sm text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <div class="dropdown relative">
                                <span class="text-sm text-white hover:text-xcost-orange cursor-pointer">Quản lý</span>
                                <div class="dropdown-content">
                                    <a href="./admin/admin_products.php">Danh sách sản phẩm</a>
                                    <a href="./admin/admin_add_product.php">Thêm sản phẩm mới</a>
                                    <a href="./admin/admin_users.php">Quản lý tài khoản</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        <a href="logout.php" class="text-sm text-white hover:text-xcost-orange">Đăng xuất</a>
                    <?php else: ?>
                        <a href="login.php" class="text-sm text-white hover:text-xcost-orange">Đăng nhập</a>
                        <a href="register.php" class="text-sm text-white hover:text-xcost-orange">Đăng ký</a>
                    <?php endif; ?>
                </div>
                <button id="mobileNavToggle" class="mobile-nav-toggle md:hidden text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
        <div id="mobileSearch" class="hidden md:hidden bg-xcost-dark px-4 pb-3">
            <div class="flex">
                <input type="text" placeholder="Tìm kiếm sản phẩm..." class="w-full p-2 rounded-l-md border-none focus:ring-2 focus:ring-xcost-orange text-gray-800" />
                <button class="bg-xcost-orange p-2 rounded-r-md hover:bg-orange-600">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </div>
        </div>
        <div id="mobileNavMenu" class="mobile-nav-menu md:hidden">
            <?php if (isset($_SESSION['user_name'])): ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <div class="dropdown">
                        <span class="text-sm text-white hover:text-xcost-orange cursor-pointer">Quản lý</span>
                        <div class="dropdown-content">
                            <a href="./admin/admin_products.php">Danh sách sản phẩm</a>
                            <a href="./admin/admin_add_product.php">Thêm sản phẩm mới</a>
                            <a href="./admin/admin_users.php">Quản lý tài khoản</a>
                        </div>
                    </div>
                <?php endif; ?>
                <a href="logout.php" class="text-sm text-white hover:text-xcost-orange">Đăng xuất</a>
            <?php else: ?>
                <a href="login.php" class="text-sm text-white hover:text-xcost-orange">Đăng nhập</a>
                <a href="register.php" class="text-sm text-white hover:text-xcost-orange">Đăng ký</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Giỏ Hàng Của Bạn</h1>
        <div id="cartMessage" class="mb-4 hidden text-center font-semibold rounded-md py-2"></div>
        <div id="cartContent" class="mb-8"></div>
        <div id="cartFooter" class="hidden text-right">
            <p class="text-xl font-semibold text-gray-800 mb-4">Tổng cộng: <span id="cartTotal">0</span> đ</p>
            <button id="checkoutButton" class="bg-xcost-orange text-white py-2 px-6 rounded-md hover:bg-orange-600 font-medium">Thanh toán</button>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">×</span>
            <h2>Thanh toán & Đặt hàng</h2>
            <p class="total">Tổng tiền: <span id="modalTotal">0</span> VNĐ</p>
            <div id="modalMessage" class="hidden"></div>
            <div class="modal-buttons">
                <button class="pay-button" data-action="pay">Thanh toán ngay</button>
                <button class="order-button" data-action="order">Đặt hàng</button>
            </div>
            <a href="index.php" class="link">Quay lại trang chủ</a>
        </div>
    </div>

    <script>
        // Mobile Navigation Toggle
        document.getElementById('mobileNavToggle').addEventListener('click', function () {
            document.getElementById('mobileNavMenu').classList.toggle('active');
        });

        document.addEventListener('click', function (e) {
            const mobileNavMenu = document.getElementById('mobileNavMenu');
            const mobileNavToggle = document.getElementById('mobileNavToggle');
            if (!mobileNavMenu.contains(e.target) && !mobileNavToggle.contains(e.target)) {
                mobileNavMenu.classList.remove('active');
            }
        });

        // Mobile Search Toggle
        document.getElementById('mobileSearchToggle').addEventListener('click', function () {
            document.getElementById('mobileSearch').classList.toggle('hidden');
        });

        // Search Functionality
        document.querySelectorAll('.search-button').forEach(button => {
            button.addEventListener('click', function () {
                const input = this.previousElementSibling;
                const searchQuery = input.value.trim();
                if (searchQuery) {
                    window.location.href = `index.php?search=${encodeURIComponent(searchQuery)}`;
                }
            });
        });

        document.querySelectorAll('.search-input').forEach(input => {
            input.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const searchQuery = this.value.trim();
                    if (searchQuery) {
                        window.location.href = `index.php?search=${encodeURIComponent(searchQuery)}`;
                    }
                }
            });
        });

        // Modal Functionality
        const checkoutModal = document.getElementById('checkoutModal');
        const checkoutButton = document.getElementById('checkoutButton');
        const closeModal = document.querySelector('.modal-close');

        checkoutButton.addEventListener('click', function () {
            if (!<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
                showCartMessage('Vui lòng đăng nhập để thanh toán!', 'text-red-600 bg-red-100');
                setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                return;
            }
            fetchCartForModal();
            checkoutModal.style.display = 'flex';
        });

        closeModal.addEventListener('click', function () {
            checkoutModal.style.display = 'none';
            resetModal();
        });

        window.addEventListener('click', function (e) {
            if (e.target === checkoutModal) {
                checkoutModal.style.display = 'none';
                resetModal();
            }
        });

        function resetModal() {
            document.getElementById('modalMessage').classList.add('hidden');
            document.getElementById('modalMessage').textContent = '';
            document.querySelector('.modal-buttons').style.display = 'flex';
            document.querySelector('.link').style.display = 'block';
        }

        function fetchCartForModal() {
            fetch('get_cart.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Phản hồi mạng không thành công: ' + response.statusText);
                    }
                    return response.text().then(text => {
                        if (!text.trim()) {
                            throw new Error('Phản hồi từ server trống');
                        }
                        return JSON.parse(text);
                    });
                })
                .then(data => {
                    if (data.success && data.cartItems.length > 0) {
                        document.getElementById('modalTotal').textContent = Number(data.total).toLocaleString('vi-VN');
                    } else {
                        showCartMessage('Giỏ hàng trống!', 'text-red-600 bg-red-100');
                        checkoutModal.style.display = 'none';
                    }
                })
                .catch(error => {
                    showCartMessage('Lỗi khi tải giỏ hàng: ' + error.message, 'text-red-600 bg-red-100');
                    checkoutModal.style.display = 'none';
                });
        }

        // Handle Pay/Order Buttons
        document.querySelectorAll('.modal-buttons button').forEach(button => {
            button.addEventListener('click', function () {
                const action = this.dataset.action;
                fetch('process_checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: action })
                })
                .then(response => response.json())
                .then(data => {
                    const modalMessage = document.getElementById('modalMessage');
                    modalMessage.classList.remove('hidden', 'success', 'error');
                    document.querySelector('.modal-buttons').style.display = 'none';
                    if (data.success) {
                        const currentDate = new Date().toLocaleDateString('vi-VN', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric'
                        });
                        modalMessage.classList.add('success');
                        modalMessage.innerHTML = `
                            <div class="logo">
                                <img src="image/Q10.jpg" alt="TrungCook Logo" />
                            </div>
                            <div class="store-info">
                                <p class="store-name">Cửa Hàng TrungCook</p>
                                <p>Địa chỉ: 10 Phố Quang - Quận Tân Bình, Hồ Chí Minh</p>
                                <p>Điện thoại: 0363270823</p>
                            </div>
                            <div class="invoice-title">
                                ${action === 'pay' ? 'HÓA ĐƠN BÁN HÀNG' : 'ĐƠN ĐẶT HÀNG'}
                            </div>
                            <div class="invoice-details">
                                <p><span class="font-semibold">Số HD:</span> ${data.invoice_id}</p>
                                <p><span class="font-semibold">Ngày:</span> ${currentDate}</p>
                                <p><span class="font-semibold">Khách hàng:</span> ${data.customer_name || '<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Khách'; ?>'}</p>
                                <p><span class="font-semibold">SDT:</span> ${data.customer_phone || '0927077778'}</p>
                                <p><span class="font-semibold">Địa chỉ:</span> --</p>
                            </div>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Tên sản phẩm</th>
                                        <th>Số lượng</th>
                                        <th>Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.cartItems.map(item => `
                                        <tr>
                                            <td>${item.TenSP} ${item.ThuocTinh ? `(${item.ThuocTinh})` : ''}</td>
                                            <td>${item.SL}</td>
                                            <td>${Number(item.SL * item.GiaBan).toLocaleString('vi-VN')}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            <div class="totals">
                                <p><span class="font-semibold">Tổng tiền hàng:</span> ${Number(data.total).toLocaleString('vi-VN')}</p>
                                <p><span class="font-semibold">Chiết khấu:</span> 0</p>
                                <p><span class="font-semibold">Tổng thanh toán:</span> ${Number(data.total).toLocaleString('vi-VN')} <span class="text-xcost-orange">(Hai mươi nghìn đồng chẵn)</span></p>
                            </div>
                        `;
                        fetchCart();
                    } else {
                        modalMessage.classList.add('error');
                        modalMessage.textContent = data.error || (action === 'pay' ? '❌ Thanh toán thất bại!' : '❌ Đặt hàng thất bại!');
                    }
                })
                .catch(error => {
                    const modalMessage = document.getElementById('modalMessage');
                    modalMessage.classList.remove('hidden', 'success', 'error');
                    modalMessage.classList.add('error');
                    modalMessage.textContent = 'Lỗi kết nối: ' + error.message;
                });
            });
        });

        // Fetch Cart on Page Load
        document.addEventListener('DOMContentLoaded', function () {
            fetchCart();
        });

        function fetchCart() {
            fetch('get_cart.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Phản hồi mạng không thành công: ' + response.statusText);
                    }
                    return response.text().then(text => {
                        if (!text.trim()) {
                            throw new Error('Phản hồi từ server trống');
                        }
                        return JSON.parse(text);
                    });
                })
                .then(data => {
                    const cartContent = document.getElementById('cartContent');
                    const cartFooter = document.getElementById('cartFooter');
                    const cartTotal = document.getElementById('cartTotal');
                    const cartCount = document.getElementById('cartCount');
                    const cartMessage = document.getElementById('cartMessage');

                    if (!data.success) {
                        cartContent.innerHTML = '<p class="text-gray-600 text-center">Không thể tải giỏ hàng: ' + data.message + '</p>';
                        cartFooter.classList.add('hidden');
                        return;
                    }

                    if (data.cartItems.length === 0) {
                        cartContent.innerHTML = '<p class="text-gray-600 text-center">Giỏ hàng của bạn đang trống.</p>';
                        cartFooter.classList.add('hidden');
                    } else {
                        const isMobile = window.innerWidth <= 640;
                        cartContent.innerHTML = `
                            <table class="cart-table w-full">
                                <thead>
                                    <tr>
                                        ${isMobile ? `
                                            <th>Sản phẩm</th>
                                            <th>Số lượng</th>
                                            <th>Tổng</th>
                                            <th></th>
                                        ` : `
                                            <th class="w-24">Hình ảnh</th>
                                            <th>Tên sản phẩm</th>
                                            <th class="w-32">Số lượng</th>
                                            <th class="w-24">Giá</th>
                                            <th class="w-24">Tổng</th>
                                            <th class="w-24"></th>
                                        `}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.cartItems.map(item => `
                                        <tr class="border-b" data-cart-id="${item.ID}">
                                            ${isMobile ? `
                                                <td>
                                                    <div class="flex items-center space-x-2">
                                                        <img src="${item.HinhAnh}" alt="${item.TenSP}" class="w-12 h-12">
                                                        <span class="text-sm">${item.TenSP} ${item.ThuocTinh ? `(${item.ThuocTinh})` : ''}</span>
                                                    </div>
                                                </td>
                                                <td class="quantity-col">
                                                    <input type="number" class="quantity-input" value="${item.SL}" min="1" max="${item.Stock}" data-cart-id="${item.ID}">
                                                    <button class="update-quantity text-white">Cập nhật</button>
                                                </td>
                                                <td class="cart-item-total text-sm">${Number(item.SL * item.GiaBan).toLocaleString('vi-VN')} đ</td>
                                                <td>
                                                    <button class="delete-item text-white">Xóa</button>
                                                </td>
                                            ` : `
                                                <td>
                                                    <img src="${item.HinhAnh}" alt="${item.TenSP}" class="w-16 h-16">
                                                </td>
                                                <td class="text-sm">${item.TenSP} ${item.ThuocTinh ? `(${item.ThuocTinh})` : ''}</td>
                                                <td class="quantity-col">
                                                    <input type="number" class="quantity-input" value="${item.SL}" min="1" max="${item.Stock}" data-cart-id="${item.ID}">
                                                    <button class="update-quantity text-white">Cập nhật</button>
                                                </td>
                                                <td class="text-sm">${Number(item.GiaBan).toLocaleString('vi-VN')} đ</td>
                                                <td class="cart-item-total text-sm">${Number(item.SL * item.GiaBan).toLocaleString('vi-VN')} đ</td>
                                                <td>
                                                    <button class="delete-item text-white">Xóa</button>
                                                </td>
                                            `}
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
                                const cartId = this.closest('tr').dataset.cartId;
                                const quantityInput = this.previousElementSibling;
                                const newQuantity = parseInt(quantityInput.value);
                                const maxQuantity = parseInt(quantityInput.max);

                                if (newQuantity < 1) {
                                    showCartMessage('Số lượng phải lớn hơn 0!', 'text-red-600 bg-red-100');
                                    return;
                                }
                                if (newQuantity > maxQuantity) {
                                    showCartMessage(`Số lượng không được vượt quá ${maxQuantity}!`, 'text-red-600 bg-red-100');
                                    return;
                                }

                                fetch('update_cart.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'update', cart_id: cartId, quantity: newQuantity })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        fetchCart();
                                        showCartMessage('Cập nhật số lượng thành công!', 'text-green-600 bg-green-100');
                                    } else {
                                        showCartMessage(data.message || 'Lỗi khi cập nhật số lượng!', 'text-red-600 bg-red-100');
                                    }
                                })
                                .catch(error => {
                                    showCartMessage('Lỗi kết nối: ' + error.message, 'text-red-600 bg-red-100');
                                });
                            });
                        });

                        document.querySelectorAll('.delete-item').forEach(button => {
                            button.addEventListener('click', function () {
                                const cartId = this.closest('tr').dataset.cartId;
                                fetch('update_cart.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'delete', cart_id: cartId })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        fetchCart();
                                        showCartMessage('Đã xóa sản phẩm khỏi giỏ hàng!', 'text-green-600 bg-green-100');
                                    } else {
                                        showCartMessage(data.message || 'Lỗi khi xóa sản phẩm!', 'text-red-600 bg-red-100');
                                    }
                                })
                                .catch(error => {
                                    showCartMessage('Lỗi kết nối: ' + error.message, 'text-red-600 bg-red-100');
                                });
                            });
                        });
                    }
                })
                .catch(error => {
                    document.getElementById('cartContent').innerHTML = '<p class="text-gray-600 text-center">Đã xảy ra lỗi khi tải giỏ hàng: ' + error.message + '</p>';
                });
        }

        function showCartMessage(message, className) {
            const messageDiv = document.getElementById('cartMessage');
            messageDiv.classList.remove('hidden', 'text-green-600', 'text-red-600', 'bg-green-100', 'bg-red-100');
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
<?php if (isset($conn)) $conn->close(); ?>