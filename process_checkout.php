<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Kiểm tra người dùng đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Vui lòng đăng nhập để tiếp tục']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Đọc dữ liệu từ request
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = isset($data['action']) ? $data['action'] : null;

if (!$action || !in_array($action, ['pay', 'order'])) {
    echo json_encode(['success' => false, 'error' => 'Hành động không hợp lệ']);
    exit;
}

// Hàm lấy access token mới
function fetchAccessToken() {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://id.kiotviet.vn/connect/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'scopes=PublicApi.Access&grant_type=client_credentials&client_id=2a4f1db1-2b25-4e08-895e-563db6f9ed1e&client_secret=90B1ECB6A671D0854D9C34D34998743ECAAEF73B',
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
    ));
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($error) {
        file_put_contents('process_checkout_error_log.txt', "Lỗi khi lấy access token: $error\n", FILE_APPEND);
        return false;
    }

    if ($http_code != 200) {
        file_put_contents('process_checkout_error_log.txt', "Lỗi khi lấy access token: HTTP Code $http_code, Response: $response\n", FILE_APPEND);
        return false;
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        file_put_contents('process_checkout_error_log.txt', "Không thể lấy access token: " . print_r($data, true) . "\n", FILE_APPEND);
        return false;
    }
    return $data['access_token'];
}

// Hàm tạo số điện thoại ngẫu nhiên
function generateRandomPhoneNumber() {
    $prefixes = ['090', '091', '092', '093', '094', '095', '096', '097', '098', '099'];
    $prefix = $prefixes[array_rand($prefixes)];
    $suffix = str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
    return $prefix . $suffix;
}

// Hàm kiểm tra sản phẩm riêng lẻ trên KiotViet
function checkSingleProduct($bearer_token, $product_id, $branchId) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://public.kiotapi.com/products/$product_id?includeInventory=true",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $bearer_token",
            'Retailer: trungapikv'
        ),
    ));
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($error) {
        file_put_contents('process_checkout_error_log.txt', "Lỗi khi kiểm tra sản phẩm $product_id: cURL Error: $error\n", FILE_APPEND);
        return false;
    }

    if ($http_code == 401) {
        file_put_contents('process_checkout_error_log.txt', "Lỗi khi kiểm tra sản phẩm $product_id: HTTP Code $http_code (Token có thể đã hết hạn), Response: $response\n", FILE_APPEND);
        return false;
    }

    if ($http_code != 200) {
        file_put_contents('process_checkout_error_log.txt', "Lỗi khi kiểm tra sản phẩm $product_id: HTTP Code $http_code, Response: $response\n", FILE_APPEND);
        return false;
    }

    $product = json_decode($response, true);
    if (isset($product['id'])) {
        // Kiểm tra xem sản phẩm có thuộc chi nhánh không
        $inventories = $product['inventories'] ?? [];
        $branch_ids = array_column($inventories, 'branchId');
        if (!in_array($branchId, $branch_ids)) {
            file_put_contents('process_checkout_error_log.txt', "Sản phẩm $product_id không thuộc chi nhánh $branchId. Danh sách chi nhánh của sản phẩm: " . implode(',', $branch_ids) . "\n", FILE_APPEND);
            return false;
        }
        return $product;
    }
    file_put_contents('process_checkout_error_log.txt', "Sản phẩm $product_id không tồn tại trên KiotViet. Response: " . print_r($response, true) . "\n", FILE_APPEND);
    return false;
}

// Lấy access token
$bearer_token = fetchAccessToken();
if (!$bearer_token) {
    echo json_encode(['success' => false, 'error' => 'Không thể lấy access token']);
    exit;
}

// Lấy danh sách kênh bán hàng từ API
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://public.kiotapi.com/salechannel',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer $bearer_token",
        'Retailer: trungapikv'
    ),
));
$salechannel_response = curl_exec($curl);
$salechannel_error = curl_error($curl);
$salechannel_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

file_put_contents('process_checkout_error_log.txt', "Kiểm tra kênh bán hàng - HTTP Code: $salechannel_http_code, Error: $salechannel_error, Response: $salechannel_response\n", FILE_APPEND);

$salechannel_data = json_decode($salechannel_response, true);
if (!$salechannel_data || isset($salechannel_data['error']) || $salechannel_error || $salechannel_http_code != 200) {
    echo json_encode(['success' => false, 'error' => 'Lỗi khi lấy danh sách kênh bán hàng']);
    exit;
}

// Tìm kênh bán hàng "Khác" và lấy ID của nó
$source_id = null;
foreach ($salechannel_data['data'] as $channel) {
    if (strtolower($channel['name']) === 'khác') {
        $source_id = (int)$channel['id'];
        break;
    }
}

if ($source_id === null) {
    file_put_contents('process_checkout_error_log.txt', "Không tìm thấy kênh bán hàng 'Khác'. Danh sách kênh: " . print_r($salechannel_data['data'], true) . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy kênh bán hàng "Khác"']);
    exit;
}

// Hardcode branchId to ensure consistency
$branchId = 106314;

// Hàm kiểm tra khách hàng có tồn tại trên KiotViet không
function checkCustomerExists($bearer_token, $customer_code, $branchId) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://public.kiotapi.com/customers/code/$customer_code",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $bearer_token",
            'Retailer: trungapikv'
        ),
    ));
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($error || $http_code != 200) {
        file_put_contents('process_checkout_error_log.txt', "Lỗi khi kiểm tra khách hàng với code $customer_code: HTTP Code $http_code, Error: $error, Response: $response\n", FILE_APPEND);
        return false;
    }
    $customer = json_decode($response, true);
    return isset($customer['id']) ? $customer['id'] : false;
}

// Hàm tạo khách hàng mới trên KiotViet
function createKiotVietCustomer($bearer_token, $customer_name, $customer_code, $branchId) {
    $customer_data = [
        "branchId" => (int)$branchId,
        "code" => (string)$customer_code,
        "name" => $customer_name,
        "contactNumber" => generateRandomPhoneNumber()
    ];

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://public.kiotapi.com/customers',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($customer_data),
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $bearer_token",
            'Retailer: trungapikv',
            'Content-Type: application/json'
        ),
    ));
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($error || $http_code != 200) {
        file_put_contents('process_checkout_error_log.txt', "Lỗi khi tạo khách hàng: HTTP Code $http_code, Error: $error, Response: $response\n", FILE_APPEND);
        return false;
    }

    $customer = json_decode($response, true);
    return isset($customer['id']) ? $customer['id'] : false;
}

// Lấy thông tin khách hàng từ bảng taikhoan
$sql = "SELECT ID_Account, TenTaiKhoan FROM taikhoan WHERE ID_Account = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

$customer_name = $user_data['TenTaiKhoan'];
$customer_code = $user_data['ID_Account'];

// Kiểm tra hoặc tạo khách hàng trên KiotViet
$kiotviet_customer_id = checkCustomerExists($bearer_token, $customer_code, $branchId);
if ($kiotviet_customer_id === false) {
    $kiotviet_customer_id = createKiotVietCustomer($bearer_token, $customer_name, $customer_code, $branchId);
    if ($kiotviet_customer_id === false) {
        echo json_encode(['success' => false, 'error' => 'Không thể tạo khách hàng trên KiotViet']);
        exit;
    }
}

// Sử dụng ID người bán cố định
$soldById = 242577;

// Lấy sản phẩm từ giỏ hàng
$sql = "SELECT g.ProductID, g.SL, g.ThuocTinh, sp.TenSP, sp.GiaBan
        FROM giohang g 
        JOIN hanghoa sp ON g.ProductID = sp.ID
        WHERE g.ID_Account = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = [];
$total_amount = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total_amount += $row['SL'] * $row['GiaBan'];
}
$stmt->close();

if (empty($cart_items)) {
    echo json_encode(['success' => false, 'error' => 'Giỏ hàng trống']);
    exit;
}

// Kiểm tra sản phẩm trong giỏ hàng và ánh xạ ID KiotViet
$product_ids = array_column($cart_items, 'ProductID');
$kiotviet_product_ids = $product_ids; // Use directly, no kv_ prefix handling

// Kiểm tra sản phẩm theo lô
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://public.kiotapi.com/products?ids=' . implode(',', $kiotviet_product_ids) . '&branchId=' . $branchId . '&includeInventory=true',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer $bearer_token",
        'Retailer: trungapikv'
    ),
));
$product_response = curl_exec($curl);
$product_error = curl_error($curl);
$product_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

file_put_contents('process_checkout_error_log.txt', "Kiểm tra sản phẩm - HTTP Code: $product_http_code, Error: $product_error, Response: $product_response\n", FILE_APPEND);

$product_data = json_decode($product_response, true);
if (!$product_data || isset($product_data['error']) || $product_error || $product_http_code != 200) {
    file_put_contents('process_checkout_error_log.txt', "Lỗi khi kiểm tra sản phẩm: HTTP Code $product_http_code, Error: $product_error, Response: " . print_r($product_data, true) . "\n", FILE_APPEND);
}

// Tạo ánh xạ từ MySQL ID sang KiotViet ID
$kiotviet_products = [];
$product_list = $product_data['data'] ?? [];
foreach ($product_list as $product) {
    $product_id = $product['id'];
    $kiotviet_products[$product_id] = $product;
}

// Kiểm tra và ánh xạ ID cho từng sản phẩm trong giỏ hàng
$invoice_details = [];
$invalid_products = [];
foreach ($cart_items as $item) {
    $mysql_id = $item['ProductID'];
    $kiotviet_id = $mysql_id; // Use directly, no prefix handling

    if (!isset($kiotviet_products[$kiotviet_id])) {
        $product = checkSingleProduct($bearer_token, $kiotviet_id, $branchId);
        if ($product === false) {
            file_put_contents('process_checkout_error_log.txt', "Sản phẩm MySQL ID $mysql_id không tồn tại trên KiotViet hoặc không thuộc chi nhánh $branchId (KiotViet ID $kiotviet_id)\n", FILE_APPEND);
            $invalid_products[] = $item['TenSP'];
            // Remove from cart to prevent future errors
            $sql = "DELETE FROM giohang WHERE ProductID = ? AND ID_Account = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $mysql_id, $user_id);
            $stmt->execute();
            $stmt->close();
            continue;
        }
        $kiotviet_products[$kiotviet_id] = $product;
    }

    $invoice_details[] = [
        "productId" => (string)$kiotviet_id, // KiotViet expects string
        "quantity" => $item['SL'],
        "price" => $item['GiaBan'],
        "discount" => 0,
        "note" => $item['ThuocTinh'] ?? ''
    ];
}

if (!empty($invalid_products)) {
    echo json_encode([
        'success' => false,
        'error' => "Sản phẩm " . implode(', ', $invalid_products) . " không tồn tại trên KiotViet hoặc không thuộc chi nhánh hiện tại. Đã xóa khỏi giỏ hàng."
    ]);
    exit;
}

// Chuẩn bị dữ liệu cho KiotViet
$api_data = [
    "branchId" => $branchId,
    "soldById" => $soldById,
    "customerId" => $kiotviet_customer_id,
    "customerName" => $customer_name,
    "total" => $total_amount,
    "status" => 1,
    "invoiceDetails" => $invoice_details,
    "payments" => [
        [
            "method" => "Cash",
            "amount" => $total_amount
        ]
    ]
];

if ($action === 'order') {
    $api_data['source'] = $source_id;
}

$endpoint = ($action === 'pay') ? 'invoices' : 'orders';
$json_payload = json_encode($api_data, JSON_UNESCAPED_UNICODE);

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://public.kiotapi.com/$endpoint",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $json_payload,
    CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer $bearer_token",
        "Retailer: trungapikv",
        "Content-Type: application/json"
    ),
));
$response = curl_exec($curl);
$error = curl_error($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

file_put_contents('process_checkout_log.txt', "HTTP Code: $http_code\nResponse: $response\nError: $error\n\n", FILE_APPEND);

$result = json_decode($response, true);
if (($http_code == 200 || $http_code == 201) && $result && isset($result['id']) && !isset($result['error']) && !$error) {
    // Xóa giỏ hàng sau khi thành công
    $sql = "DELETE FROM giohang WHERE ID_Account = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'invoice_id' => $result['id'],
        'customer_name' => $customer_name,
        'customer_phone' => generateRandomPhoneNumber(),
        'cartItems' => $cart_items,
        'total' => $total_amount
    ]);
} else {
    $error_message = "Lỗi khi " . ($action === 'pay' ? 'thanh toán' : 'đặt hàng') . ": HTTP Code $http_code, Error: $error, Response: " . print_r($result, true);
    file_put_contents('process_checkout_error_log.txt', $error_message . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => 'Không thể xử lý yêu cầu']);
}

$conn->close();
?>