<?php
// Thiết lập header trả về JSON
header('Content-Type: application/json');

// Khởi tạo cURL
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://public.kiotapi.com/categories?hierarchicalData=true&pageSize=100&orderBy=name&orderDirection=Asc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'Authorization: bearer eyJhbGciOiJSUzI1NiIsInR5cCI6ImF0K2p3dCJ9.eyJuYmYiOjE3NDUwODIzMzYsImV4cCI6MTc0NTE2ODczNiwiaXNzIjoiaHR0cDovL2lkLmtpb3R2aWV0LnZuIiwiY2xpZW50X2lkIjoiMmE0ZjFkYjEtMmIyNS00ZTA4LTg5NWUtNTYzZGI2ZjllZDFlIiwiY2xpZW50X1JldGFpbGVyQ29kZSI6InRydW5nYXBpa3YiLCJjbGllbnRfUmV0YWlsZXJJZCI6IjUwMDgxMzg4MCIsImNsaWVudF9Vc2VySWQiOiIyNDE5OTYiLCJjbGllbnRfU2Vuc2l0aXZlQXBpIjoiVHJ1ZSIsImNsaWVudF9Hcm91cElkIjoiMjgiLCJpYXQiOjE3NDUwODIzMzYsInNjb3BlIjpbIlB1YmxpY0FwaS5BY2Nlc3MiXX0.JC2FLQCt2Crcd4yy3YoOUuyi4H_v-mOGtxDzRnagZMIEfXNJmwqd6n6ae8zv-zV7UwkEh7JQM-WS5VkMDn1RJFePPV0TiyIiTeGfkVF84p7CL3LIsIpGnwvMvk8_znv8tGya5RYSJKpdBtIxZRoEmXAHi6wJ9wS521MqkMrHX1jukeV9oX13_h5YDKDr9fBLa8vWTLl_OXo6YmoB4A4mMtX8nQtlRt_gl07p7l5UQlDkJrDN9l1ZVpC9RI_snNFXaD-5vNryW66e5vjcA28yBkxbHkt3STNyKoU_UkaEy6XIZ3K2MNX51OEtJFATOWlXkuqTP2gGuRjqkKR2ucsnMQ',
        'Retailer: trungapikv',
        'Cookie: ss-id=V9ScozEn07lC4rTQ72z5; ss-pid=AfVhbQQtyM46x5liQIFS'
    ),
));

// Thực thi yêu cầu
$response = curl_exec($curl);

// Đóng cURL
curl_close($curl);

// Kiểm tra lỗi cURL
if ($response === false) {
    echo json_encode(['error' => 'Lỗi khi gọi API: ' . curl_error($curl)]);
    exit;
}

// Giải mã dữ liệu JSON
$data = json_decode($response, true);
if ($data === null) {
    echo json_encode(['error' => 'Lỗi giải mã JSON: ' . json_last_error_msg()]);
    exit;
}

// Lấy danh sách nhóm hàng
$categories = $data['data'] ?? [];

// Hàm hiển thị danh sách nhóm hàng phân cấp
function displayCategories($categories, $parentId = null, $level = 0) {
    $result = [];
    foreach ($categories as $category) {
        if ($category['parentId'] == $parentId) {
            $result[] = [
                'id' => $category['categoryId'],
                'name' => str_repeat('— ', $level) . $category['categoryName'],
                'level' => $level
            ];
            // Lấy các nhóm con
            $children = displayCategories($categories, $category['categoryId'], $level + 1);
            $result = array_merge($result, $children);
        }
    }
    return $result;
}

// Hiển thị danh sách nhóm hàng phân cấp (bắt đầu từ nhóm cha)
$categoryList = displayCategories($categories);

// Trả về JSON
echo json_encode($categoryList);
?>