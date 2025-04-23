<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $cart_id = intval($_GET['id']);

    // Trả lại hàng về kho
    $sql = "SELECT product_id, size, quantity FROM cart WHERE id = $cart_id";
    $result = $conn->query($sql);
    if ($row = $result->fetch_assoc()) {
        $conn->query("UPDATE inventory SET stock_quantity = stock_quantity + {$row['quantity']} WHERE product_id = {$row['product_id']} AND size = '{$row['size']}'");
    }

    // Xoá khỏi giỏ
    $conn->query("DELETE FROM cart WHERE id = $cart_id");
}

$conn->close();
header("Location: cart-view.php");
exit;
?>
