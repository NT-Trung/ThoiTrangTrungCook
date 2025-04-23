<?php
session_start();
require_once 'config.php';

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

// Function to fetch categories from KiotViet API
function fetchKiotVietCategories($accessToken) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://public.kiotapi.com/categories',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $accessToken",
            'Retailer: trungapikv',
            'Cookie: ss-id=I90drDGfJ97JmDrTVtkx; ss-pid=AfVhbQQtyM46x5liQIFS'
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("cURL Error in fetchKiotVietCategories: $err");
        return ['error' => 'cURL Error: ' . $err];
    }

    $data = json_decode($response, true);
    if (!isset($data['data']) || !is_array($data['data'])) {
        error_log("Invalid categories API response: " . print_r($response, true));
        return ['error' => 'Invalid categories API response'];
    }

    return $data['data'];
}

// Function to fetch products from KiotViet API (with pagination and category filter)
function fetchKiotVietProducts($accessToken, $categoryId = null) {
    $products = [];
    $pageSize = 100;
    $currentItem = 0;
    $baseUrl = 'https://public.kiotapi.com/products';

    do {
        $url = $baseUrl . '?pageSize=' . $pageSize . '&currentItem=' . $currentItem . '&includeInventory=true&includeSoftDeletedAttribute=true';
        if ($categoryId) {
            $url .= '&categoryId=' . urlencode($categoryId);
        }

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
                "Authorization: Bearer $accessToken",
                'Retailer: trungapikv',
                'Cookie: ss-id=I90drDGfJ97JmDrTVtkx; ss-pid=AfVhbQQtyM46x5liQIFS'
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("cURL Error in fetchKiotVietProducts: $err");
            return ['error' => 'cURL Error: ' . $err];
        }

        $data = json_decode($response, true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            error_log("Invalid API response: " . print_r($response, true));
            return ['error' => 'Invalid API response: ' . print_r($response, true)];
        }

        foreach ($data['data'] as $item) {
            error_log("Product ID: " . $item['id'] . ", Full Data: " . print_r($item, true));

            $onHand = 0;
            if (isset($item['inventories']) && is_array($item['inventories']) && !empty($item['inventories'])) {
                $onHand = (string)($item['inventories'][0]['onHand'] ?? 0);
            }

            $size = '';
            $vi = '';
            if (isset($item['attributes']) && is_array($item['attributes'])) {
                error_log("Attributes for Product ID {$item['id']}: " . print_r($item['attributes'], true));
                foreach ($item['attributes'] as $attr) {
                    if (isset($attr['attributeName']) && isset($attr['attributeValue'])) {
                        if ($attr['attributeName'] == 'SIZE') {
                            $size = $attr['attributeValue'];
                            error_log("Found SIZE for Product ID {$item['id']}: $size");
                        } elseif ($attr['attributeName'] == 'VI') {
                            $vi = $attr['attributeValue'];
                            error_log("Found VI for Product ID {$item['id']}: $vi");
                        }
                    }
                }
            } else {
                error_log("No attributes found for Product ID {$item['id']}");
            }

            $thuocTinh = $size !== '' || $vi !== '' ? trim($size . ($size !== '' && $vi !== '' ? '-' : '') . $vi) : '';

            $products[] = [
                'ID' => $item['id'],
                'TenSP' => $item['name'],
                'HinhAnh' => isset($item['images'][0]) ? $item['images'][0] : 'default_image.jpg',
                'GiaBan' => $item['basePrice'],
                'SL' => $onHand,
                'ThuocTinh' => $thuocTinh,
                'MoTaNgan' => isset($item['description']) ? $item['description'] : 'Sản phẩm thời trang chất lượng cao, phong cách hiện đại.',
                'LuotXem' => rand(50, 2000),
                'NhomHang' => $item['categoryId'] ?? null,
            ];
        }

        $totalItems = $data['total'] ?? count($data['data']);
        $currentItem += $pageSize;

    } while ($currentItem < $totalItems);

    return $products;
}

// Function to sync KiotViet products to MySQL
function syncKiotVietProductsToDB($accessToken, $conn) {
    $products = fetchKiotVietProducts($accessToken);
    if (isset($products['error'])) {
        return ['error' => $products['error']];
    }

    $stmt = $conn->prepare("
        INSERT INTO hanghoa (ID, TenSP, HinhAnh, GiaBan, SL, ThuocTinh, MoTaNgan, LuotXem, NhomHang)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            TenSP = VALUES(TenSP),
            HinhAnh = VALUES(HinhAnh),
            GiaBan = VALUES(GiaBan),
            SL = VALUES(SL),
            ThuocTinh = VALUES(ThuocTinh),
            MoTaNgan = VALUES(MoTaNgan),
            LuotXem = VALUES(LuotXem),
            NhomHang = VALUES(NhomHang),
            UpdatedAt = CURRENT_TIMESTAMP
    ");

    if ($stmt === false) {
        return ['error' => 'Prepare statement failed: ' . $conn->error];
    }

    foreach ($products as $product) {
        $stmt->bind_param(
            "sssdisdis",
            $product['ID'],
            $product['TenSP'],
            $product['HinhAnh'],
            $product['GiaBan'],
            $product['SL'],
            $product['ThuocTinh'],
            $product['MoTaNgan'],
            $product['LuotXem'],
            $product['NhomHang']
        );
        if (!$stmt->execute()) {
            error_log("Failed to sync product ID {$product['ID']}: " . $stmt->error);
        }
    }

    $stmt->close();
    return ['success' => true, 'message' => 'Đồng bộ sản phẩm thành công'];
}

// Fetch and sync products
$accessToken = fetchAccessToken();
if (!$accessToken) {
    $syncResult = ['error' => 'Failed to fetch access token'];
} else {
    $syncResult = syncKiotVietProductsToDB($accessToken, $conn);
    if (isset($syncResult['error'])) {
        error_log("Sync Error: " . $syncResult['error']);
    } else {
        error_log("Sync Success: " . $syncResult['message']);
    }
}

// Fetch categories
$categories = [];
if ($accessToken) {
    $categoriesData = fetchKiotVietCategories($accessToken);
    if (!isset($categoriesData['error'])) {
        $categories = array_map(function($cat) {
            return [
                'id' => $cat['categoryId'],
                'name' => $cat['categoryName']
            ];
        }, $categoriesData);
    } else {
        $syncResult['error'] = $syncResult['error'] ?? $categoriesData['error'];
    }
}

// Pagination settings
$productsPerPage = 25;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $productsPerPage;

// Get category, search query, sort, and page from URL
$selectedCategoryId = isset($_GET['categoryId']) ? trim($_GET['categoryId']) : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

// Count total products for pagination
$countSql = "SELECT COUNT(DISTINCT TenSP) as total 
             FROM hanghoa 
             WHERE 1=1";
$countTypes = '';
$countParams = [];

if ($searchQuery) {
    $countSql .= " AND LOWER(TenSP) LIKE LOWER(?)";
    $countTypes .= 's';
    $countParams[] = "%$searchQuery%";
}
if ($selectedCategoryId) {
    $countSql .= " AND NhomHang = ?";
    $countTypes .= 'i';
    $countParams[] = $selectedCategoryId;
}

if ($countTypes) {
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param($countTypes, ...$countParams);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
} else {
    $countResult = $conn->query($countSql);
}

$totalProducts = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalProducts / $productsPerPage);

// Fetch MySQL products with pagination
$sql = "SELECT ID, TenSP, HinhAnh, GiaBan, MoTaNgan, LuotXem, SL 
        FROM hanghoa 
        WHERE 1=1";
$types = '';
$params = [];

if ($searchQuery) {
    $sql .= " AND LOWER(TenSP) LIKE LOWER(?)";
    $types .= 's';
    $params[] = "%$searchQuery%";
}
if ($selectedCategoryId) {
    $sql .= " AND NhomHang = ?";
    $types .= 'i';
    $params[] = $selectedCategoryId;
}
$sql .= " GROUP BY TenSP LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $productsPerPage;
$params[] = $offset;

if ($types) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

if ($sort === 'low_to_high') {
    usort($products, function ($a, $b) {
        return $a['GiaBan'] <=> $b['GiaBan'];
    });
} elseif ($sort === 'high_to_low') {
    usort($products, function ($a, $b) {
        return $b['GiaBan'] <=> $a['GiaBan'];
    });
}

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

// Filter categories based on search
$filteredCategories = [];
foreach ($categories as $category) {
    $sqlCheck = "SELECT 1 FROM hanghoa WHERE NhomHang = ?";
    $typesCheck = 'i';
    $paramsCheck = [$category['id']];
    if ($searchQuery) {
        $sqlCheck .= " AND LOWER(TenSP) LIKE LOWER(?)";
        $typesCheck .= 's';
        $paramsCheck[] = "%$searchQuery%";
    }
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param($typesCheck, ...$paramsCheck);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        $filteredCategories[] = $category;
    }
}
if (!$searchQuery) {
    $filteredCategories = $categories;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>Thời Trang TrungCook</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"/>
    <style>
        .bg-xcost-dark { background-color: #1A252F; }
        .text-xcost-orange { color: #F68B1F; }
        .bg-xcost-orange { background-color: #F68B1F; }
        .border-xcost-orange { border-color: #F68B1F; }
        .hover\:bg-xcost-dark:hover { background-color: #2A3540; }
        .product-image { aspect-ratio: 1 / 1; object-fit: contain; width: 100%; }
        .product-image:hover { opacity: 0.9; cursor: pointer; }
        .buy-button { background-color: #F68B1F; transition: background-color 0.2s; }
        .buy-button:hover { background-color: #E07B00; }
        .add-to-cart-button { background-color: #4A90E2; transition: background-color 0.2s; }
        .add-to-cart-button:hover { background-color: #357ABD; }
        .bg-sidebar-gradient { background: linear-gradient(to bottom, #E6F0FA, #FFFFFF); }
        .dropdown-content { display: none; position: absolute; background-color: #2A3540; min-width: 160px; z-index: 1; }
        .dropdown:hover .dropdown-content { display: block; }
        .dropdown-content a { color: white; padding: 12px 16px; text-decoration: none; display: block; }
        .dropdown-content a:hover { background-color: #F68B1F; }
        html, body { width: 100%; overflow-x: hidden; margin: 0; padding: 0; }
        .logo-link { text-decoration: none; color: #F68B1F; }
        .logo-link:hover { color: #F68B1F; }
        .pagination a { padding: 8px 16px; margin: 0 4px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #F68B1F; }
        .pagination a:hover { background-color: #F68B1F; color: white; }
        .pagination a.active { background-color: #F68B1F; color: white; border: 1px solid #F68B1F; }
        .pagination a.disabled { color: #ccc; cursor: not-allowed; }
        .product-card { 
            display: flex; 
            flex-direction: column; 
            min-height: 350px;
        }
        .product-content { 
            flex-grow: 1;
            display: flex; 
            flex-direction: column; 
        }
        .product-title { 
            min-height: 2.5rem;
            overflow: hidden; 
            display: -webkit-box; 
            -webkit-box-orient: vertical; 
        }
        .product-description { 
            min-height: 3rem;
            overflow: hidden; 
            display: -webkit-box; 
            -webkit-box-orient: vertical; 
        }
        @media (max-width: 640px) {
            .container { width: 100%; max-width: 100%; margin: 0; padding-left: 0.5rem; padding-right: 0.5rem; }
            #productGrid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            #sidebar { width: 100%; }
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
            .product-card { 
                min-height: 300px;
            }
            .button-group { flex-direction: column; gap: 0.5rem; }
        }
        @media (min-width: 641px) {
            .nav-items { display: flex !important; }
            .mobile-nav-toggle { display: none; }
            .mobile-nav-menu { display: none !important; }
            .mobile-username { display: none !important; }
            .button-group { flex-direction: row; gap: 0.5rem; }
        }
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
                <a href="cart-view.php" class="flex items-center text-white hover:text-xcost-orange mr-2">
                    <svg class="w-6 h-6 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="text-sm">(<span id="cartCount"><?php echo $cartCount; ?></span>)</span>
                </a>
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

    <!-- Main content -->
    <div class="container mx-auto px-4 py-6 flex flex-col md:flex-row">
        <aside id="sidebar" class="hidden md:block w-full md:w-1/3 bg-sidebar-gradient border border-gray-200 rounded shadow-md p-6 mb-4 md:mb-0">
            <h2 class="text-xl font-extrabold mb-4 border-l-4 border-xcost-orange pl-2">DANH MỤC SẢN PHẨM</h2>
            <ul>
                <li class="mb-3">
                    <a href="index.php<?php echo $searchQuery ? '?search=' . urlencode($searchQuery) : ''; ?><?php echo $sort ? ($searchQuery ? '&' : '?') . 'sort=' . urlencode($sort) : ''; ?>" class="text-base font-semibold text-gray-700 hover:bg-xcost-orange hover:text-white block p-2 rounded <?php echo !$selectedCategoryId ? 'bg-xcost-orange text-white font-bold' : ''; ?>">Tất cả</a>
                </li>
                <?php foreach ($filteredCategories as $category): ?>
                    <li class="mb-3">
                        <a href="index.php?categoryId=<?php echo urlencode($category['id']); ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $sort ? '&sort=' . urlencode($sort) : ''; ?>" class="text-base font-semibold text-gray-700 hover:bg-xcost-orange hover:text-white block p-2 rounded <?php echo $selectedCategoryId == $category['id'] ? 'bg-xcost-orange text-white font-bold' : ''; ?>"><?php echo htmlspecialchars($category['name']); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <button id="sidebarToggle" class="md:hidden bg-xcost-orange text-white p-2 rounded mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
            </svg>
        </button>
        <div class="w-full md:w-8/10 md:pl-6">
            <?php if (isset($syncResult['error'])): ?>
                <p class="text-center text-red-500 mb-4">Lỗi đồng bộ API: <?php echo htmlspecialchars($syncResult['error']); ?></p>
            <?php endif; ?>
            <div class="flex justify-between items-center mb-4 flex-wrap">
                <h2 class="text-lg font-bold">THỜI TRANG <span class="text-gray-500 text-sm" id="productCount">(Tổng số sản phẩm: <?php echo $totalProducts; ?>)</span></h2>
                <div class="flex space-x-2 mt-2 md:mt-0">
                    <select id="sortSelect" class="border rounded p-1 text-sm">
                        <option value="" <?php echo !$sort ? 'selected' : ''; ?>>Sắp xếp theo</option>
                        <option value="low_to_high" <?php echo $sort === 'low_to_high' ? 'selected' : ''; ?>>Giá thấp đến cao</option>
                        <option value="high_to_low" <?php echo $sort === 'high_to_low' ? 'selected' : ''; ?>>Giá cao đến thấp</option>
                    </select>
                    <input type="text" placeholder="Mã định danh" class="border rounded p-1 text-sm w-24">
                    <input type="text" placeholder="Hết hạn" class="border rounded p-1 text-sm w-24">
                </div>
            </div>
            <div id="productGrid" class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <?php if (empty($products)): ?>
                    <p class="col-span-full text-center text-gray-500">Không tìm thấy sản phẩm nào.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="bg-white rounded shadow p-4 product-card">
                            <a href="product-detail.php?id=<?php echo htmlspecialchars($product['ID']); ?>">
                                <img src="<?php echo htmlspecialchars($product['HinhAnh']); ?>" alt="<?php echo htmlspecialchars($product['TenSP']); ?>" class="w-full h-40 product-image mb-2">
                            </a>
                            <div class="product-content">
                                <h3 class="text-sm font-semibold text-gray-800 product-title"><?php echo htmlspecialchars($product['TenSP']); ?></h3>
                                <p class="text-sm font-semibold text-gray-800"><?php echo number_format($product['GiaBan'], 2); ?>đ</p>
                                <div class="flex items-center text-xs text-gray-500 mb-2">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <?php echo $product['LuotXem']; ?>
                                </div>
                                <div class="flex items-center text-xs text-gray-500 mb-2">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <?php echo $product['SL']; ?>
                                </div>
                                <div class="button-group flex mt-auto">
                                    <a href="product-detail.php?id=<?php echo htmlspecialchars($product['ID']); ?>" class="buy-button text-white text-center py-2 rounded text-sm flex-1">Mua</a>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <button class="add-to-cart-button text-white text-center py-2 rounded text-sm flex-1" data-product-id="<?php echo htmlspecialchars($product['ID']); ?>" data-price="<?php echo $product['GiaBan']; ?>">Thêm 🛒</button>
                                    <?php else: ?>
                                        <a href="login.php" class="add-to-cart-button text-white text-center py-2 rounded text-sm flex-1">Đăng nhập để thêm</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination flex justify-center mt-6">
                    <?php
                    $params = [];
                    if ($searchQuery) {
                        $params[] = 'search=' . urlencode($searchQuery);
                    }
                    if ($selectedCategoryId) {
                        $params[] = 'categoryId=' . urlencode($selectedCategoryId);
                    }
                    if ($sort) {
                        $params[] = 'sort=' . urlencode($sort);
                    }
                    $baseUrl = 'index.php' . ($params ? '?' . implode('&', $params) : '');
                    ?>
                    <!-- Previous Button -->
                    <a href="<?php echo $page > 1 ? $baseUrl . ($params ? '&' : '?') . 'page=' . ($page - 1) : '#'; ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">Trước</a>
                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="<?php echo $baseUrl . ($params ? '&' : '?') . 'page=' . $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <!-- Next Button -->
                    <a href="<?php echo $page < $totalPages ? $baseUrl . ($params ? '&' : '?') . 'page=' . ($page + 1) : '#'; ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Sau</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Sidebar and search toggle
        document.getElementById('sidebarToggle').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden');
        });

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

        // Handle search button click
        document.querySelectorAll('.search-button').forEach(button => {
            button.addEventListener('click', function () {
                const input = this.previousElementSibling;
                const searchQuery = input.value.trim();
                const sort = document.getElementById('sortSelect').value;
                const categoryId = new URLSearchParams(window.location.search).get('categoryId') || '';
                let url = 'index.php';
                const params = [];
                if (searchQuery) {
                    params.push('search=' + encodeURIComponent(searchQuery));
                }
                if (categoryId) {
                    params.push('categoryId=' + encodeURIComponent(categoryId));
                }
                if (sort) {
                    params.push('sort=' + encodeURIComponent(sort));
                }
                if (params.length > 0) {
                    url += '?' + params.join('&');
                }
                window.location.href = url;
            });
        });

        // Handle Enter key press in search input
        document.querySelectorAll('.search-input').forEach(input => {
            input.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const searchQuery = this.value.trim();
                    const sort = document.getElementById('sortSelect').value;
                    const categoryId = new URLSearchParams(window.location.search).get('categoryId') || '';
                    let url = 'index.php';
                    const params = [];
                    if (searchQuery) {
                        params.push('search=' + encodeURIComponent(searchQuery));
                    }
                    if (categoryId) {
                        params.push('categoryId=' + encodeURIComponent(categoryId));
                    }
                    if (sort) {
                        params.push('sort=' + encodeURIComponent(sort));
                    }
                    if (params.length > 0) {
                        url += '?' + params.join('&');
                    }
                    window.location.href = url;
                }
            });
        });

        // Handle sort selection change
        document.getElementById('sortSelect').addEventListener('change', function () {
            const sort = this.value;
            const searchQuery = new URLSearchParams(window.location.search).get('search') || '';
            const categoryId = new URLSearchParams(window.location.search).get('categoryId') || '';
            let url = 'index.php';
            const params = [];
            if (searchQuery) {
                params.push('search=' + encodeURIComponent(searchQuery));
            }
            if (categoryId) {
                params.push('categoryId=' + encodeURIComponent(categoryId));
            }
            if (sort) {
                params.push('sort=' + encodeURIComponent(sort));
            }
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            window.location.href = url;
        });

        // Handle "Add to Cart" button clicks
        function attachAddToCartListeners() {
            document.querySelectorAll('.add-to-cart-button').forEach(button => {
                button.addEventListener('click', function () {
                    const productId = this.dataset.productId;
                    const price = parseFloat(this.dataset.price);
                    const quantity = 1;

                    const formData = new FormData();
                    formData.append('product_id', productId);
                    formData.append('price', price);
                    formData.append('quantity', quantity);

                    fetch('cart.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const currentCount = parseInt(document.getElementById('cartCount').textContent);
                            document.getElementById('cartCount').textContent = currentCount + quantity;
                        }
                    })
                    .catch(error => {
                        alert('Lỗi kết nối: ' + error.message);
                    });
                });
            });
        }

        // Initial attachment of event listeners
        attachAddToCartListeners();
    </script>
</body>
</html>
<?php if (isset($conn)) $conn->close(); ?>