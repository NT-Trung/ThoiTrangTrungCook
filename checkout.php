<?php
session_start();
require_once 'config.php';

// Kiểm tra người dùng đã đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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
        file_put_contents('checkout_error_log.txt', "Lỗi khi lấy access token: $error\n", FILE_APPEND);
        die("Lỗi khi lấy access token. Vui lòng thử lại sau.");
    }
    
    if ($http_code != 200) {
        file_put_contents('checkout_error_log.txt', "Lỗi khi lấy access token: HTTP Code $http_code, Response: $response\n", FILE_APPEND);
        die("Lỗi khi lấy access token. Vui lòng kiểm tra client_id và client_secret.");
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        file_put_contents('checkout_error_log.txt', "Không thể lấy access token: " . print_r($data, true) . "\n", FILE_APPEND);
        die("Không thể lấy access token. Vui lòng kiểm tra client_id và client_secret.");
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

// Lấy access token
$bearer_token = fetchAccessToken();

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

file_put_contents('checkout_error_log.txt', "Kiểm tra kênh bán hàng - HTTP Code: $salechannel_http_code, Error: $salechannel_error, Response: $salechannel_response\n", FILE_APPEND);

$salechannel_data = json_decode($salechannel_response, true);
if (!$salechannel_data || isset($salechannel_data['error']) || $salechannel_error || $salechannel_http_code != 200) {
    $error_message = "Lỗi khi lấy danh sách kênh bán hàng: HTTP Code $salechannel_http_code, Error: $salechannel_error, Response: " . print_r($salechannel_data, true) . "\n";
    file_put_contents('checkout_error_log.txt', $error_message, FILE_APPEND);
    die("Lỗi khi lấy danh sách kênh bán hàng. Vui lòng kiểm tra log hoặc liên hệ hỗ trợ KiotViet (1900 6522, hotro@kiotviet.com).");
}

// Tìm kênh bán hàng "Khác" và lấy ID của nó
$source_id = null; // Sử dụng ID thay vì tên
$found_channel = false;
foreach ($salechannel_data['data'] as $channel) {
    if (strtolower($channel['name']) === 'khác') {
        $source_id = (int)$channel['id']; // Lấy ID của kênh "Khác"
        $found_channel = true;
        break;
    }
}

if (!$found_channel) {
    file_put_contents('checkout_error_log.txt', "Không tìm thấy kênh bán hàng 'Khác'. Danh sách kênh: " . print_r($salechannel_data['data'], true) . "\n", FILE_APPEND);
    die("Không tìm thấy kênh bán hàng 'Khác'. Vui lòng kiểm tra cấu hình KiotViet hoặc liên hệ hỗ trợ KiotViet (1900 6522, hotro@kiotviet.com).");
}

file_put_contents('checkout_error_log.txt', "ID của kênh bán 'Khác': $source_id\n", FILE_APPEND);

// Lấy branchId trước khi tạo khách hàng
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://public.kiotapi.com/branches',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer $bearer_token",
        'Retailer: trungapikv'
    ),
));
$branch_response = curl_exec($curl);
$branch_error = curl_error($curl);
$branch_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$branch_data = json_decode($branch_response, true);
if (!$branch_data || isset($branch_data['error']) || $branch_error || $branch_http_code != 200) {
    $error_message = "Lỗi khi lấy danh sách chi nhánh: HTTP Code $branch_http_code, Error: $branch_error, Response: " . print_r($branch_data, true) . "\n";
    file_put_contents('checkout_error_log.txt', $error_message, FILE_APPEND);
    die("Lỗi khi lấy danh sách chi nhánh. Vui lòng kiểm tra log hoặc liên hệ hỗ trợ KiotViet (1900 6522, hotro@kiotviet.com).");
}
$branchId = $branch_data['data'][0]['id'] ?? null;
if (!$branchId) {
    file_put_contents('checkout_error_log.txt', "Không tìm thấy chi nhánh: " . print_r($branch_data, true) . "\n", FILE_APPEND);
    die("Không tìm thấy chi nhánh. Vui lòng kiểm tra cấu hình KiotViet.");
}

// Hàm kiểm tra khách hàng có tồn tại trên KiotViet không (dựa trên code = ID_Account)
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
        file_put_contents('checkout_error_log.txt', "Lỗi khi kiểm tra khách hàng với code $customer_code: HTTP Code $http_code, Error: $error, Response: $response\n", FILE_APPEND);
        return false;
    }
    $customer = json_decode($response, true);
    return isset($customer['id']) ? $customer['id'] : false;
}

// Hàm tạo khách hàng mới trên KiotViet với cơ chế thử lại
function createKiotVietCustomer($bearer_token, $customer_name, $customer_code, $branchId, $retry = 1) {
    if (empty($customer_name)) {
        file_put_contents('checkout_error_log.txt', "Tên khách hàng rỗng, không thể tạo khách hàng\n", FILE_APPEND);
        return false;
    }
    if (empty($customer_code)) {
        file_put_contents('checkout_error_log.txt', "Mã khách hàng (ID_Account) rỗng, không thể tạo khách hàng\n", FILE_APPEND);
        return false;
    }
    if (empty($branchId)) {
        file_put_contents('checkout_error_log.txt', "branchId rỗng, không thể tạo khách hàng\n", FILE_APPEND);
        return false;
    }

    $customer_data = [
        "branchId" => (int)$branchId,
        "code" => (string)$customer_code,
        "name" => $customer_name,
        "contactNumber" => generateRandomPhoneNumber()
    ];
    $attempts = 0;

    while ($attempts <= $retry) {
        $attempts++;
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
        
        if ($error) {
            file_put_contents('checkout_error_log.txt', "Lỗi cURL khi tạo khách hàng (lần $attempts): $error\n", FILE_APPEND);
            if ($attempts <= $retry) {
                sleep(1);
                continue;
            }
            return false;
        }
        
        if ($http_code != 200) {
            file_put_contents('checkout_error_log.txt', "Lỗi khi tạo khách hàng (lần $attempts): HTTP Code $http_code, Response: $response\n", FILE_APPEND);
            if ($attempts <= $retry) {
                sleep(1);
                continue;
            }
            return false;
        }
        
        $customer = json_decode($response, true);
        $customer_id = $customer['id'] ?? ($customer['data']['id'] ?? null);
        if (!$customer_id) {
            file_put_contents('checkout_error_log.txt', "Không thể tạo khách hàng (lần $attempts): Response không có id - " . print_r($customer, true) . "\n", FILE_APPEND);
            if ($attempts <= $retry) {
                sleep(1);
                continue;
            }
            return false;
        }
        file_put_contents('checkout_error_log.txt', "Tạo khách hàng thành công: ID $customer_id, Name: $customer_name, Code: $customer_code, Contact Number: {$customer_data['contactNumber']}\n", FILE_APPEND);
        return $customer_id;
    }
    return false;
}

// Lấy thông tin khách hàng từ bảng taikhoan
$sql = "SELECT ID_Account, TenTaiKhoan FROM taikhoan WHERE ID_Account = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    file_put_contents('checkout_error_log.txt', "Prepare failed (fetch TenTaiKhoan): " . $conn->error . "\n", FILE_APPEND);
    die("Lỗi cơ sở dữ liệu khi lấy tên tài khoản. Vui lòng thử lại sau.");
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    file_put_contents('checkout_error_log.txt', "Execute failed (fetch TenTaiKhoan): " . $stmt->error . "\n", FILE_APPEND);
    die("Lỗi cơ sở dữ liệu khi lấy tên tài khoản. Vui lòng thử lại sau.");
}
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    file_put_contents('checkout_error_log.txt', "Không tìm thấy tài khoản với ID: $user_id\n", FILE_APPEND);
    die("Không tìm thấy tài khoản. Vui lòng thử lại sau.");
}
$user_data = $result->fetch_assoc();
$customer_name = $user_data['TenTaiKhoan'];
$customer_code = $user_data['ID_Account'];
$stmt->close();

// Kiểm tra hoặc tạo khách hàng trên KiotViet dựa trên code (ID_Account)
$kiotviet_customer_id = checkCustomerExists($bearer_token, $customer_code, $branchId);
if ($kiotviet_customer_id === false) {
    $kiotviet_customer_id = createKiotVietCustomer($bearer_token, $customer_name, $customer_code, $branchId, 1);
    if ($kiotviet_customer_id === false) {
        file_put_contents('checkout_error_log.txt', "Không thể tạo khách hàng trên KiotViet cho $customer_name (Code: $customer_code) sau 2 lần thử\n", FILE_APPEND);
        die("Không thể tạo khách hàng trên KiotViet. Vui lòng kiểm tra log hoặc liên hệ hỗ trợ KiotViet (1900 6522, hotro@kiotviet.com).");
    }
}

file_put_contents('checkout_error_log.txt', "Customer Name: $customer_name, Customer Code: $customer_code, KiotViet Customer ID: $kiotviet_customer_id\n", FILE_APPEND);

// Sử dụng ID người bán cố định
$soldById = 242577; // Nguyễn Thành Trung

// Lấy sản phẩm từ giỏ hàng
$sql = "SELECT g.ProductID, g.SL, g.ThuocTinh, sp.TenSP, sp.GiaBan, sp.ID
        FROM giohang g 
        JOIN hanghoa sp ON g.ProductID = sp.ID
        WHERE g.ID_Account = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    file_put_contents('checkout_error_log.txt', "Prepare failed: " . $conn->error . "\n", FILE_APPEND);
    die("Lỗi cơ sở dữ liệu. Vui lòng thử lại sau.");
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    file_put_contents('checkout_error_log.txt', "Execute failed: " . $stmt->error . "\n", FILE_APPEND);
    die("Lỗi cơ sở dữ liệu. Vui lòng thử lại sau.");
}
$result = $stmt->get_result();
$cart_items = [];
$total_amount = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total_amount += $row['SL'] * $row['GiaBan'];
}
$stmt->close();

// Kiểm tra nếu giỏ hàng trống
if (empty($cart_items)) {
    die("Giỏ hàng của bạn trống. Vui lòng thêm sản phẩm trước khi thanh toán hoặc đặt hàng.");
}

// Kiểm tra sản phẩm trong giỏ hàng và ánh xạ ID KiotViet
$product_ids = array_column($cart_items, 'ProductID');
$kiotviet_product_ids = array_map(function($id) {
    $kiotviet_id = preg_replace('/^kv_/', '', $id);
    file_put_contents('checkout_error_log.txt', "MySQL Product ID: $id, KiotViet Product ID: $kiotviet_id\n", FILE_APPEND);
    return $kiotviet_id;
}, $product_ids);

file_put_contents('checkout_error_log.txt', "Danh sách KiotViet Product IDs: " . implode(',', $kiotviet_product_ids) . "\n", FILE_APPEND);

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://public.kiotapi.com/products?ids=' . implode(',', $kiotviet_product_ids) . '&branchId=' . $branchId,
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

file_put_contents('checkout_error_log.txt', "Kiểm tra sản phẩm - HTTP Code: $product_http_code, Error: $product_error, Response: $product_response\n", FILE_APPEND);

$product_data = json_decode($product_response, true);
if (!$product_data || isset($product_data['error']) || $product_error || $product_http_code != 200) {
    $error_message = "Lỗi khi kiểm tra sản phẩm: HTTP Code $product_http_code, Error: $product_error, Response: " . print_r($product_data, true) . "\n";
    file_put_contents('checkout_error_log.txt', $error_message, FILE_APPEND);
    die("Lỗi khi kiểm tra sản phẩm trên KiotViet. Vui lòng chạy script đồng bộ sản phẩm (sync_products.php) và thử lại.");
}

// Tạo ánh xạ từ MySQL ID sang KiotViet ID
$kiotviet_products = [];
$product_list = $product_data['data'] ?? [];
foreach ($product_list as $product) {
    $product_id = $product['id'];
    $kiotviet_products[$product_id] = $product;
    file_put_contents('checkout_error_log.txt', "Tìm thấy sản phẩm trên KiotViet: ID $product_id, Name: {$product['name']}\n", FILE_APPEND);
}

// Kiểm tra và ánh xạ ID cho từng sản phẩm trong giỏ hàng
$invoice_details = [];
foreach ($cart_items as $item) {
    $mysql_id = $item['ProductID'];
    $kiotviet_id = preg_replace('/^kv_/', '', $mysql_id);

    if (!isset($kiotviet_products[$kiotviet_id])) {
        file_put_contents('checkout_error_log.txt', "Sản phẩm MySQL ID $mysql_id không tồn tại trên KiotViet (KiotViet ID $kiotviet_id)\n", FILE_APPEND);
        die("Sản phẩm {$item['TenSP']} không tồn tại trên KiotViet. Vui lòng chạy script đồng bộ sản phẩm (sync_products.php) và thử lại.");
    }

    $invoice_details[] = [
        "productId" => (int)$kiotviet_id,
        "quantity" => $item['SL'],
        "price" => $item['GiaBan'],
        "discount" => 0,
        "note" => $item['ThuocTinh'] ?? ''
    ];
}

// Xử lý khi người dùng gửi yêu cầu (Thanh toán hoặc Đặt hàng)
$is_success = false;
$invoice_id = 'N/A';
$error_display = null;
$action_type = isset($_POST['action']) ? $_POST['action'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action_type) {
    // Chuẩn bị dữ liệu chung cho cả Thanh toán và Đặt hàng
    $data = [
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

    // Thêm trường source (dùng ID) khi tạo đơn hàng (POST /orders), bỏ qua khi tạo hóa đơn (POST /invoices)
    if ($action_type === 'order') {
        $data['source'] = $source_id; // Sử dụng ID của kênh "Khác" (dạng số nguyên)
    }
    // Nếu là hóa đơn (POST /invoices), không thêm source để KiotViet tự động gán "Khác"

    // Chọn endpoint dựa trên hành động
    $endpoint = ($action_type === 'pay') ? 'invoices' : 'orders';
    $log_file = ($action_type === 'pay') ? 'invoice_log.txt' : 'order_log.txt';
    $error_log_file = ($action_type === 'pay') ? 'invoice_error_log.txt' : 'order_error_log.txt';
    $data_log_file = ($action_type === 'pay') ? 'invoice_data_log.txt' : 'order_data_log.txt';

    // Ghi log dữ liệu gửi lên
    file_put_contents($data_log_file, print_r($data, true) . "\n\n", FILE_APPEND);

    // Mã hóa JSON và kiểm tra lỗi
    $json_payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents($error_log_file, "JSON Encode Error: " . json_last_error_msg() . "\n", FILE_APPEND);
        die("Lỗi mã hóa JSON: " . json_last_error_msg());
    }
    file_put_contents($data_log_file, "JSON Payload: $json_payload\n", FILE_APPEND);

    // Gửi request đến KiotViet
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

    // Ghi log response
    file_put_contents($log_file, "HTTP Code: $http_code\nResponse: $response\nError: $error\n\n", FILE_APPEND);

    // Xử lý phản hồi
    $result = json_decode($response, true);
    $is_success = ($http_code == 200 || $http_code == 201) && $result && isset($result['id']) && !isset($result['error']) && !$error;

    if ($is_success) {
        $recorded_customer_name = $result['customerName'] ?? 'Khách lẻ';
        file_put_contents($log_file, "Tên khách hàng ghi nhận trên KiotViet: $recorded_customer_name\n", FILE_APPEND);
        if ($recorded_customer_name === 'Khách lẻ' && $customer_name !== 'Khách lẻ') {
            file_put_contents($error_log_file, "KiotViet ghi nhận sai tên khách hàng: $recorded_customer_name (kỳ vọng: $customer_name)\n", FILE_APPEND);
        }
        // Xóa giỏ hàng chỉ khi thành công
        $sql = "DELETE FROM giohang WHERE ID_Account = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $error_message = "Lỗi khi " . ($action_type === 'pay' ? 'tạo hóa đơn' : 'tạo đơn hàng') . ": HTTP Code $http_code\n";
        $error_message .= "Response: " . print_r($result, true) . "\n";
        $error_message .= "cURL Error: $error\n";
        $error_message .= "Data Sent: " . print_r($data, true) . "\n";
        $error_message .= "JSON Payload Sent: $json_payload\n";
        file_put_contents($error_log_file, $error_message . "\n\n", FILE_APPEND);
        $error_display = isset($result['responseStatus']['message']) ? $result['responseStatus']['message'] : (isset($result['error_description']) ? $result['error_description'] : (isset($result['error']) ? $result['error'] : "Lỗi không xác định. Vui lòng kiểm tra log."));
    }

    $invoice_id = $result['id'] ?? 'N/A';
    $total_amount = $result['total'] ?? $total_amount;
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <?php if ($is_success): ?>
        <meta http-equiv="refresh" content="5;url=index.php">
    <?php endif; ?>
    <title>Thanh toán & Đặt hàng</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .success { color: green; font-size: 24px; }
        .error { color: red; font-size: 20px; }
        .total { font-size: 18px; margin-bottom: 20px; }
        .button { padding: 10px 20px; margin: 10px; font-size: 16px; cursor: pointer; }
        .pay-button { background-color: #4CAF50; color: white; border: none; }
        .order-button { background-color: #2196F3; color: white; border: none; }
        .link { margin-top: 20px; }
    </style>
</head>
<body>
    <?php if ($is_success): ?>
        <h2 class="success">✅ <?php echo $action_type === 'pay' ? 'Thanh toán thành công!' : 'Đặt hàng thành công!'; ?></h2>
        <p><?php echo $action_type === 'pay' ? 'Cảm ơn bạn đã mua sắm. Đơn hàng của bạn đang được xử lý.' : 'Đơn hàng của bạn đã được đặt thành công. Chúng tôi sẽ liên hệ để xác nhận.'; ?></p>
        <p><strong>Mã <?php echo $action_type === 'pay' ? 'hóa đơn' : 'đơn hàng'; ?>:</strong> <?php echo htmlspecialchars($invoice_id); ?></p>
        <p><strong>Tổng tiền:</strong> <?php echo number_format($total_amount, 0, ',', '.'); ?> VNĐ</p>
        <p>Bạn sẽ được chuyển về trang chủ trong 5 giây...</p>
    <?php elseif ($action_type && !$is_success): ?>
        <h2 class="error">❌ <?php echo $action_type === 'pay' ? 'Thanh toán thất bại!' : 'Đặt hàng thất bại!'; ?></h2>
        <p><?php echo htmlspecialchars($error_display ?? "Không thể " . ($action_type === 'pay' ? 'tạo hóa đơn' : 'tạo đơn hàng') . " trên KiotViet. Vui lòng kiểm tra lại hoặc liên hệ hỗ trợ."); ?></p>
    <?php else: ?>
        <h2>Thanh toán & Đặt hàng</h2>
        <p class="total"><strong>Tổng tiền:</strong> <?php echo number_format($total_amount, 0, ',', '.'); ?> VNĐ</p>
        <form method="POST">
            <button type="submit" name="action" value="pay" class="button pay-button">Thanh toán ngay</button>
            <button type="submit" name="action" value="order" class="button order-button">Đặt hàng</button>
        </form>
    <?php endif; ?>
    <a href="index.php" class="link">Quay lại trang chủ ngay</a>
</body>
</html>

<?php
$conn->close();
?>