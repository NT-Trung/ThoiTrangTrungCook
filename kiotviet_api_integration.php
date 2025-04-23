<?php
// Step 1: Define KiotViet API credentials
$clientId = "b4550d97-4803-4b86-baca-ca00d465ed3b"; // Updated ClientId
$clientSecret = "C717BFDF28CB74FBC2AD1F99C29E17AB2FD6C24E"; // Updated ClientSecret
$retailer = "trungapikv"; // Updated Retailer name (confirm this in your KiotViet account)

// Step 2: Get Access Token using OAuth 2.0
$tokenUrl = "https://id.kiotviet.vn/connect/token";
$tokenData = [
    "client_id" => $clientId,
    "client_secret" => $clientSecret,
    "grant_type" => "client_credentials",
    "scope" => "PublicApi" // Reintroduce scope parameter
];

// Log the request data for debugging
echo "Request Data: " . http_build_query($tokenData) . "<br>";

// Initialize cURL for token request
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/x-www-form-urlencoded"
]);
curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Execute token request
$tokenResponse = curl_exec($ch);
$tokenError = curl_error($ch);
$tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Log verbose output
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "Verbose Log: <pre>$verboseLog</pre>";

curl_close($ch);

// Debug: Check token response
if ($tokenHttpCode !== 200) {
    die("Error getting access token. HTTP Code: $tokenHttpCode. Error: $tokenError. Response: $tokenResponse");
}

$tokenData = json_decode($tokenResponse, true);
if (!$tokenData || isset($tokenData["error"])) {
    die("Error parsing access token: " . ($tokenData["error_description"] ?? $tokenError));
}

$accessToken = $tokenData["access_token"];

// Step 3: Fetch Data (e.g., Customers)
$apiUrl = "https://public.kiotapi.com/customers";
$headers = [
    "Retailer: $retailer",
    "Authorization: Bearer $accessToken"
];

// Initialize cURL for API request
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute API request
$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug: Check API response
if ($httpCode !== 200) {
    die("Error fetching data. HTTP Code: $httpCode. Error: $error. Response: $response");
}

$data = json_decode($response, true);
if (!$data || isset($data["error"])) {
    die("Error parsing data: " . ($data["error_description"] ?? $error));
}

// Step 4: Display Customers
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách khách hàng từ KiotViet</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .customer { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Danh sách khách hàng từ KiotViet</h1>
    <?php if (isset($data["data"]) && !empty($data["data"])): ?>
        <?php foreach ($data["data"] as $customer): ?>
            <div class="customer">
                <h3><?php echo htmlspecialchars($customer["name"]); ?></h3>
                <p>Số điện thoại: <?php echo htmlspecialchars($customer["contactNumber"] ?? "Không có"); ?></p>
                <p>Email: <?php echo htmlspecialchars($customer["email"] ?? "Không có"); ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Không có khách hàng nào để hiển thị.</p>
    <?php endif; ?>
</body>
</html>