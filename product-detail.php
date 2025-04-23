<?php
session_start();
require_once 'config.php';

// Function to fetch KiotViet product by ID
function fetchKiotVietProduct($accessToken, $productId) {
  $url = "https://public.kiotapi.com/products/$productId";

  $curl = curl_init();
  curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
          "Authorization: bearer $accessToken",
          'Retailer: trungapikv',
          'Cookie: ss-id=i1j0NscVu9ySkj6PHN0s; ss-pid=AfVhbQQtyM46x5liQIFS'
      ),
  ));

  $response = curl_exec($curl);
  $err = curl_error($curl);
  curl_close($curl);

  if ($err) {
      error_log("cURL Error in fetchKiotVietProduct: $err");
      return ['error' => 'cURL Error: ' . $err];
  }

  $data = json_decode($response, true);
  if (!isset($data['id'])) {
      error_log("Product not found in API: " . print_r($response, true));
      return ['error' => 'Product not found'];
  }

  // Log toàn bộ dữ liệu sản phẩm để kiểm tra
  error_log("Product ID: " . $data['id'] . ", Full Data: " . print_r($data, true));

  $onHand = 0;
  if (isset($data['inventories']) && is_array($data['inventories']) && !empty($data['inventories'])) {
      $onHand = (string)($data['inventories'][0]['onHand'] ?? 0);
  }

  // Lấy thuộc tính của sản phẩm (size áo)
  $thuocTinh = 'N/A'; // Giá trị mặc định nếu không tìm thấy thuộc tính SIZE
  if (isset($data['attributes']) && is_array($data['attributes'])) {
      error_log("Attributes for Product ID {$data['id']}: " . print_r($data['attributes'], true));
      foreach ($data['attributes'] as $attr) {
          if (isset($attr['attributeName']) && isset($attr['attributeValue'])) {
              if ($attr['attributeName'] == 'SIZE') {
                  $thuocTinh = $attr['attributeValue'];
                  error_log("Found SIZE for Product ID {$data['id']}: $thuocTinh");
                  break;
              }
          }
      }
  } else {
      error_log("No attributes found for Product ID {$data['id']}");
  }

  return [
      'ID' => $data['id'],
      'TenSP' => $data['name'],
      'HinhAnh' => isset($data['images'][0]) ? $data['images'][0] : 'default_image.jpg',
      'GiaBan' => $data['basePrice'],
      'SL' => $onHand,
      'ThuocTinh' => $thuocTinh,
      'MoTaNgan' => isset($data['description']) ? $data['description'] : 'Sản phẩm thời trang chất lượng cao, phong cách hiện đại.'
  ];
}

// Function to fetch access token
function fetchAccessToken() {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://id.kiotviet.vn/connect/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'scopes=PublicApi.Access&grant_type=client_credentials&client_id=2a4f1db1-2b25-4e08-895e-563db6f9ed1e&client_secret=90B1ECB6A671D0854D9C34D34998743ECAAEF73B',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("cURL Error in fetchAccessToken: $err");
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        error_log("Failed to fetch access token: " . print_r($response, true));
        return null;
    }

    return $data['access_token'];
}

$id = isset($_GET['id']) ? $_GET['id'] : 0;
$product = null;
$sizes = [];

// Check if the product exists in MySQL
$sql = "SELECT ID, TenSP, SL, ThuocTinh, HinhAnh, MoTaNgan, GiaBan 
        FROM hanghoa 
        WHERE ID = ? 
        ORDER BY ThuocTinh ASC 
        LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
    $product = ['error' => 'Database error: Unable to prepare query'];
} else {
    $stmt->bind_param("s", $id);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        $product = ['error' => 'Database error: Unable to execute query'];
    } else {
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
    }
    $stmt->close();
}

if ($product && !isset($product['error'])) {
    // Fetch sizes for MySQL product
    $sqlSizes = "SELECT DISTINCT ThuocTinh 
                 FROM hanghoa 
                 WHERE TenSP = (SELECT TenSP FROM hanghoa WHERE ID = ?)";
    $stmtSizes = $conn->prepare($sqlSizes);
    if ($stmtSizes === false) {
        error_log("Prepare sizes query failed: " . $conn->error);
        $product = ['error' => 'Database error: Unable to prepare sizes query'];
    } else {
        $stmtSizes->bind_param("s", $id);
        if (!$stmtSizes->execute()) {
            error_log("Execute sizes query failed: " . $stmtSizes->error);
            $product = ['error' => 'Database error: Unable to execute sizes query'];
        } else {
            $sizeResult = $stmtSizes->get_result();
            while ($row = $sizeResult->fetch_assoc()) {
                if (!empty($row['ThuocTinh']) && $row['ThuocTinh'] !== 'N/A') {
                    $sizes[] = $row['ThuocTinh'];
                }
            }
            $sizes = array_unique($sizes);
            sort($sizes);
        }
        $stmtSizes->close();
    }
} else {
    // Fetch from KiotViet
    $accessToken = fetchAccessToken();
    if (!$accessToken) {
        $product = ['error' => 'Failed to fetch access token'];
    } else {
        $product = fetchKiotVietProduct($accessToken, $id);
        if (!isset($product['error'])) {
            $sizes = !empty($product['ThuocTinh']) && $product['ThuocTinh'] !== 'N/A' ? [$product['ThuocTinh']] : [];
        }
    }
}

// Count cart items
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sqlCart = "`SELECT SUM(SL) as total FROM giohang WHERE ID_Account = ?`";
    $stmtCart = $conn->prepare($sqlCart);
    if ($stmtCart === false) {
        error_log("Prepare cart query failed: " . $conn->error);
    } else {
        $stmtCart->bind_param("i", $user_id);
        if ($stmtCart->execute()) {
            $resultCart = $stmtCart->get_result();
            $cartCount = $resultCart && $resultCart->num_rows > 0 ? $resultCart->fetch_assoc()['total'] : 0;
        }
        $stmtCart->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Chi Tiết Sản Phẩm - Thời Trang TrungCook</title>
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
    .logo-link { text-decoration: none; color: #F68B1F; }
    .logo-link:hover { color: #F68B1F; }
    html, body { width: 100%; overflow-x: hidden; margin: 0; padding: 0; }
    @media (max-width: 640px) {
      .container { width: 100%; max-width: 100%; margin: 0; padding-left: 0.5rem; padding-right: 0.5rem; }
      #cartPopup { width: 100%; right: 0; }
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
      .mobile-nav-menu { 
        display: none; 
        position: absolute; 
        top: 60px; 
        right: 0; 
        background-color: #2A3540; 
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
      .mobile-nav-menu a:hover { color: #F68B1F; }
      .mobile-nav-menu .dropdown-content { 
        position: static; 
        background-color: #3A454F; 
        min-width: 100%; 
        padding-left: 1rem; 
      }
    }
    @media (min-width: 641px) {
      .nav-items { display: flex !important; }
      .mobile-nav-toggle { display: none; }
      .mobile-nav-menu { display: none !important; }
      .mobile-username { display: none !important; }
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
        <a href="index.php" class="logo-link">
          <h1 class="text-2xl font-bold text-xcost-orange">Thời trang TrungCook</h1>
        </a>
      </div>
      <div class="hidden md:flex flex-1 mx-20">
        <input type="text" placeholder="Tìm kiếm..." class="w-full p-2 rounded-l-md border-none focus:ring-0 text-gray-800 text-sm search-input">
        <button class="bg-xcost-orange p-2 rounded-r-md search-button">
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
      <div class="flex items-center space-x-2">
        <button id="cartToggle" class="flex items-center text-white hover:text-xcost-orange mr-2">
          <svg class="w-6 h-6 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <span class="text-sm">(<span id="cartCount"><?php echo $cartCount; ?></span>)</span>
        </button>
        <?php if (isset($_SESSION['user_name'])): ?>
          <span class="mobile-username text-white mr-2"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <?php endif; ?>
        <div class="nav-items items-center space-x-4">
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
        <input type="text" placeholder="Tìm kiếm..." class="w-full p-2 rounded-l-md border-none focus:ring-0 text-gray-800 text-sm search-input">
        <button class="bg-xcost-orange p-2 rounded-r-md search-button">
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
      <a href="cart-view.php" class="block bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 text-center mt-2">Xem giỏ hàng</a>
    </div>
  </div>

  <!-- Product Details Content -->
  <div class="container mx-auto p-6">
    <div id="product" class="bg-white p-6 mt-4 rounded shadow">
      <?php if ($product && !isset($product['error'])): ?>
        <h1 class="text-2xl font-bold mb-6"><?php echo htmlspecialchars($product['TenSP']); ?></h1>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
          <div>
            <img id="productImage" class="w-full max-w-sm rounded mb-4" src="<?php echo htmlspecialchars($product['HinhAnh']); ?>" alt="<?php echo htmlspecialchars($product['TenSP']); ?>">
            <p id="productPrice" class="text-xl text-red-500 font-semibold"><?php echo number_format($product['GiaBan']); ?> đ</p>
          </div>
          <div>
            <h2 class="text-lg font-semibold mb-2">Mô tả sản phẩm:</h2>
            <p id="productDescription" class="text-gray-700 leading-relaxed"><?php echo htmlspecialchars($product['MoTaNgan']); ?></p>
            <p id="productStock" class="text-gray-600 mt-2">Số lượng tồn kho: <span id="stockValue"><?php echo htmlspecialchars($product['SL']); ?></span></p>
            <p id="productSize" class="text-gray-600">Size: <span id="sizeValue"><?php echo htmlspecialchars($product['ThuocTinh'] ?: 'Không có'); ?></span></p>
          </div>
          <div>
            <?php if (isset($_SESSION['user_id'])): ?>
              <form id="addToCartForm">
                <input type="hidden" name="product_id" id="productId" value="<?php echo htmlspecialchars($product['ID']); ?>">
                <input type="hidden" name="price" id="priceInput" value="<?php echo $product['GiaBan']; ?>">
                <div class="mb-4">
                  <label for="size" class="block font-semibold mb-1">Chọn đi nha:</label>
                  <select id="size" name="size" class="w-full p-2 border rounded">
                    <?php if (empty($sizes)): ?>
                      <option value="">Không có gì để chọn</option>
                    <?php else: ?>
                      <option value="">-- Chọn --</option>
                      <?php foreach ($sizes as $size): ?>
                        <option value="<?php echo htmlspecialchars($size); ?>" <?php echo $size === $product['ThuocTinh'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($size); ?></option>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                </div>
                <div class="mb-4">
                  <label for="quantity" class="block font-semibold mb-1">Số lượng:</label>
                  <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['SL']; ?>" required class="w-24 p-2 border rounded">
                </div>
                <button type="submit" id="addToCartButton" class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
                  Thêm vào giỏ hàng
                </button>
              </form>
              <div id="cartMessage" class="mt-4 hidden text-green-600 font-semibold"></div>
            <?php else: ?>
              <p class="text-red-600 font-semibold">Vui lòng <a href="login.php" class="text-blue-600 hover:underline">đăng nhập</a> để thêm sản phẩm vào giỏ hàng.</p>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <p class="text-red-500"><?php echo isset($product['error']) ? htmlspecialchars($product['error']) : 'Không tìm thấy sản phẩm.'; ?></p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Header functionality
    document.getElementById('mobileSearchToggle').addEventListener('click', function () {
      const mobileSearch = document.getElementById('mobileSearch');
      mobileSearch.classList.toggle('hidden');
    });

    // Mobile Navigation Toggle
    document.getElementById('mobileNavToggle').addEventListener('click', function () {
      const mobileNavMenu = document.getElementById('mobileNavMenu');
      mobileNavMenu.classList.toggle('active');
    });

    document.addEventListener('click', function (e) {
      const mobileNavMenu = document.getElementById('mobileNavMenu');
      const mobileNavToggle = document.getElementById('mobileNavToggle');
      if (!mobileNavMenu.contains(e.target) && !mobileNavToggle.contains(e.target)) {
        mobileNavMenu.classList.remove('active');
      }
    });

    document.querySelectorAll('.search-button').forEach(button => {
      button.addEventListener('click', function () {
        const input = this.previousElementSibling;
        const searchQuery = input.value.trim();
        if (searchQuery) {
          window.location.href = 'index.php?search=' + encodeURIComponent(searchQuery);
        } else {
          window.location.href = 'index.php';
        }
      });
    });

    document.querySelectorAll('.search-input').forEach(input => {
      input.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          const searchQuery = this.value.trim();
          if (searchQuery) {
            window.location.href = 'index.php?search=' + encodeURIComponent(searchQuery);
          } else {
            window.location.href = 'index.php';
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
      fetch('get_cart.php')
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
                      <td class="px-2 py-1 text-sm">${item.TenSP}${item.ThuocTinh ? ' (' + item.ThuocTinh + ')' : ''}</td>
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

                fetch('update_cart.php', {
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
                  fetch('update_cart.php', {
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

    // Product details scripts
    <?php if (isset($_SESSION['user_id']) && $product && !isset($product['error'])): ?>
      // Handle size change
      function updateProductDetails() {
        const size = document.getElementById('size').value;
        const productName = <?php echo json_encode($product['TenSP']); ?>;
        
        // Only fetch if size is selected
        if (size) {
          fetch('get_product_by_size.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: productName, size: size })
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              document.getElementById('productImage').src = data.product.HinhAnh;
              document.getElementById('productPrice').textContent = new Intl.NumberFormat('vi-VN').format(data.product.GiaBan) + ' đ';
              document.getElementById('productDescription').textContent = data.product.MoTaNgan;
              document.getElementById('stockValue').textContent = data.product.SL;
              document.getElementById('sizeValue').textContent = data.product.ThuocTinh || 'Không có';
              document.getElementById('quantity').max = data.product.SL;
              document.getElementById('quantity').value = 1; // Reset quantity when changing attributes
              document.getElementById('productId').value = data.product.ID;
              document.getElementById('priceInput').value = data.product.GiaBan;
            } else {
              showCartMessage(data.message || 'Không tìm thấy sản phẩm với kích thước này.', 'text-red-600');
            }
          })
          .catch(error => {
            showCartMessage('Lỗi kết nối: ' + error.message, 'text-red-600');
          });
        }
      }

      document.getElementById('size').addEventListener('change', updateProductDetails);

      // Handle add to cart
      document.getElementById('addToCartForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const size = document.getElementById('size').value;
        const quantity = parseInt(document.getElementById('quantity').value);
        const maxQuantity = parseInt(document.getElementById('quantity').max);
        const messageDiv = document.getElementById('cartMessage');
        const hasSizes = <?php echo json_encode(!empty($sizes)); ?>;

        if (hasSizes && !size) {
          messageDiv.classList.remove('hidden', 'text-green-600');
          messageDiv.classList.add('text-red-600');
          messageDiv.textContent = 'Vui lòng chọn size!';
          setTimeout(() => {
            messageDiv.classList.add('hidden');
            messageDiv.textContent = '';
          }, 3000);
          return;
        }

        if (quantity < 1) {
          messageDiv.classList.remove('hidden', 'text-green-600');
          messageDiv.classList.add('text-red-600');
          messageDiv.textContent = 'Số lượng phải lớn hơn 0!';
          setTimeout(() => {
            messageDiv.classList.add('hidden');
            messageDiv.textContent = '';
          }, 3000);
          return;
        }

        if (quantity > maxQuantity) {
          messageDiv.classList.remove('hidden', 'text-green-600');
          messageDiv.classList.add('text-red-600');
          messageDiv.textContent = `Số lượng không được vượt quá ${maxQuantity}!`;
          setTimeout(() => {
            messageDiv.classList.add('hidden');
            messageDiv.textContent = '';
          }, 3000);
          return;
        }

        const formData = new FormData(this);
        fetch('cart.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          messageDiv.classList.remove('hidden', 'text-red-600');
          if (data.success) {
            messageDiv.classList.add('text-green-600');
            messageDiv.textContent = 'Đã thêm vào giỏ hàng thành công!';
            const currentCount = parseInt(document.getElementById('cartCount').textContent);
            document.getElementById('cartCount').textContent = currentCount + quantity;
            // Redirect immediately to index.php
            window.location.href = 'index.php';
          } else {
            messageDiv.classList.add('text-red-600');
            messageDiv.textContent = data.message || 'Lỗi khi thêm vào giỏ hàng!';
            setTimeout(() => {
              messageDiv.classList.add('hidden');
              messageDiv.textContent = '';
            }, 3000);
          }
        })
        .catch(error => {
          messageDiv.classList.remove('hidden', 'text-green-600');
          messageDiv.classList.add('text-red-600');
          messageDiv.textContent = 'Lỗi kết nối: ' + error.message;
          setTimeout(() => {
            messageDiv.classList.add('hidden');
            messageDiv.textContent = '';
          }, 3000);
        });
      });
    <?php endif; ?>
  </script>
</body>
</html>
<?php if (isset($conn)) $conn->close(); ?>