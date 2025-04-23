<?php
require_once 'config.php';

$products = [
    // Quần
    [1, "Quần jean nam", 50, "S", "./image/Q1.jpg", "Chất liệu jean bền đẹp, phong cách trẻ trung.", 350000],
    [2, "Quần jean nam", 50, "M", "./image/Q1.jpg", "Chất liệu jean bền đẹp, phong cách trẻ trung.", 350000],
    [3, "Quần jean nam", 50, "L", "./image/Q1.jpg", "Chất liệu jean bền đẹp, phong cách trẻ trung.", 350000],
    [4, "Quần jean nam", 50, "XL", "./image/Q1.jpg", "Chất liệu jean bền đẹp, phong cách trẻ trung.", 350000],
    [5, "Quần jean nam", 50, "2XL", "./image/Q1.jpg", "Chất liệu jean bền đẹp, phong cách trẻ trung.", 350000],

    [6, "Quần tây công sở", 40, "S", "./image/Q2.jpg", "Thiết kế lịch sự, thích hợp môi trường công sở.", 400000],
    [7, "Quần tây công sở", 40, "M", "./image/Q2.jpg", "Thiết kế lịch sự, thích hợp môi trường công sở.", 400000],
    [8, "Quần tây công sở", 40, "L", "./image/Q2.jpg", "Thiết kế lịch sự, thích hợp môi trường công sở.", 400000],
    [9, "Quần tây công sở", 40, "XL", "./image/Q2.jpg", "Thiết kế lịch sự, thích hợp môi trường công sở.", 400000],
    [10, "Quần tây công sở", 40, "2XL", "./image/Q2.jpg", "Thiết kế lịch sự, thích hợp môi trường công sở.", 400000],

    [11, "Quần short thể thao", 70, "S", "./image/Q3.jpg", "Thoáng mát, thích hợp luyện tập thể thao.", 220000],
    [12, "Quần short thể thao", 70, "M", "./image/Q3.jpg", "Thoáng mát, thích hợp luyện tập thể thao.", 220000],
    [13, "Quần short thể thao", 70, "L", "./image/Q3.jpg", "Thoáng mát, thích hợp luyện tập thể thao.", 220000],
    [14, "Quần short thể thao", 70, "XL", "./image/Q3.jpg", "Thoáng mát, thích hợp luyện tập thể thao.", 220000],
    [15, "Quần short thể thao", 70, "2XL", "./image/Q3.jpg", "Thoáng mát, thích hợp luyện tập thể thao.", 220000],

    [16, "Quần kaki lửng", 60, "S", "./image/Q4.jpg", "Năng động, phù hợp đi chơi hoặc dạo phố.", 300000],
    [17, "Quần kaki lửng", 60, "M", "./image/Q4.jpg", "Năng động, phù hợp đi chơi hoặc dạo phố.", 300000],
    [18, "Quần kaki lửng", 60, "L", "./image/Q4.jpg", "Năng động, phù hợp đi chơi hoặc dạo phố.", 300000],
    [19, "Quần kaki lửng", 60, "XL", "./image/Q4.jpg", "Năng động, phù hợp đi chơi hoặc dạo phố.", 300000],
    [20, "Quần kaki lửng", 60, "2XL", "./image/Q4.jpg", "Năng động, phù hợp đi chơi hoặc dạo phố.", 300000],

    [21, "Quần jogger", 80, "S", "./image/Q5.jpg", "Thời trang thể thao, co giãn tốt.", 280000],
    [22, "Quần jogger", 80, "M", "./image/Q5.jpg", "Thời trang thể thao, co giãn tốt.", 280000],
    [23, "Quần jogger", 80, "L", "./image/Q5.jpg", "Thời trang thể thao, co giãn tốt.", 280000],
    [24, "Quần jogger", 80, "XL", "./image/Q5.jpg", "Thời trang thể thao, co giãn tốt.", 280000],
    [25, "Quần jogger", 80, "2XL", "./image/Q5.jpg", "Thời trang thể thao, co giãn tốt.", 280000],

    [26, "Quần legging nữ", 90, "S", "./image/Q6.jpg", "Ôm sát, co giãn tốt cho vận động.", 250000],
    [27, "Quần legging nữ", 90, "M", "./image/Q6.jpg", "Ôm sát, co giãn tốt cho vận động.", 250000],
    [28, "Quần legging nữ", 90, "L", "./image/Q6.jpg", "Ôm sát, co giãn tốt cho vận động.", 250000],
    [29, "Quần legging nữ", 90, "XL", "./image/Q6.jpg", "Ôm sát, co giãn tốt cho vận động.", 250000],
    [30, "Quần legging nữ", 90, "2XL", "./image/Q6.jpg", "Ôm sát, co giãn tốt cho vận động.", 250000],

    [31, "Quần jean nữ", 55, "S", "./image/Q7.jpg", "Phong cách cá tính, dễ phối đồ.", 370000],
    [32, "Quần jean nữ", 55, "M", "./image/Q7.jpg", "Phong cách cá tính, dễ phối đồ.", 370000],
    [33, "Quần jean nữ", 55, "L", "./image/Q7.jpg", "Phong cách cá tính, dễ phối đồ.", 370000],
    [34, "Quần jean nữ", 55, "XL", "./image/Q7.jpg", "Phong cách cá tính, dễ phối đồ.", 370000],
    [35, "Quần jean nữ", 55, "2XL", "./image/Q7.jpg", "Phong cách cá tính, dễ phối đồ.", 370000],

    [36, "Quần culottes", 65, "S", "./image/Q8.jpg", "Thoải mái, dễ chịu, phong cách Hàn Quốc.", 320000],
    [37, "Quần culottes", 65, "M", "./image/Q8.jpg", "Thoải mái, dễ chịu, phong cách Hàn Quốc.", 320000],
    [38, "Quần culottes", 65, "L", "./image/Q8.jpg", "Thoải mái, dễ chịu, phong cách Hàn Quốc.", 320000],
    [39, "Quần culottes", 65, "XL", "./image/Q8.jpg", "Thoải mái, dễ chịu, phong cách Hàn Quốc.", 320000],
    [40, "Quần culottes", 65, "2XL", "./image/Q8.jpg", "Thoải mái, dễ chịu, phong cách Hàn Quốc.", 320000],

    [41, "Quần short bò", 75, "S", "./image/Q9.jpg", "Mát mẻ, trẻ trung cho ngày hè.", 190000],
    [42, "Quần short bò", 75, "M", "./image/Q9.jpg", "Mát mẻ, trẻ trung cho ngày hè.", 190000],
    [43, "Quần short bò", 75, "L", "./image/Q9.jpg", "Mát mẻ, trẻ trung cho ngày hè.", 190000],
    [44, "Quần short bò", 75, "XL", "./image/Q9.jpg", "Mát mẻ, trẻ trung cho ngày hè.", 190000],
    [45, "Quần short bò", 75, "2XL", "./image/Q9.jpg", "Mát mẻ, trẻ trung cho ngày hè.", 190000],

    [46, "Quần ống rộng", 60, "S", "./image/Q10.jpg", "Phong cách hiện đại, phù hợp nhiều dịp.", 360000],
    [47, "Quần ống rộng", 60, "M", "./image/Q10.jpg", "Phong cách hiện đại, phù hợp nhiều dịp.", 360000],
    [48, "Quần ống rộng", 60, "L", "./image/Q10.jpg", "Phong cách hiện đại, phù hợp nhiều dịp.", 360000],
    [49, "Quần ống rộng", 60, "XL", "./image/Q10.jpg", "Phong cách hiện đại, phù hợp nhiều dịp.", 360000],
    [50, "Quần ống rộng", 60, "2XL", "./image/Q10.jpg", "Phong cách hiện đại, phù hợp nhiều dịp.", 360000],

    
    // Áo
    [101, "Áo sơ mi nam", 40, "S", "./image/Ao1.jpg", "Lịch lãm, phù hợp công sở.", 290000],
    [102, "Áo sơ mi nam", 40, "M", "./image/Ao1.jpg", "Lịch lãm, phù hợp công sở.", 290000],
    [103, "Áo sơ mi nam", 40, "L", "./image/Ao1.jpg", "Lịch lãm, phù hợp công sở.", 290000],
    [104, "Áo sơ mi nam", 40, "XL", "./image/Ao1.jpg", "Lịch lãm, phù hợp công sở.", 290000],
    [105, "Áo sơ mi nam", 40, "2XL", "./image/Ao1.jpg", "Lịch lãm, phù hợp công sở.", 290000],

    [106, "Áo thun cổ tròn", 80, "S", "./image/Ao2.jpg", "Đơn giản, thoải mái cho ngày thường.", 180000],
    [107, "Áo thun cổ tròn", 80, "M", "./image/Ao2.jpg", "Đơn giản, thoải mái cho ngày thường.", 180000],
    [108, "Áo thun cổ tròn", 80, "L", "./image/Ao2.jpg", "Đơn giản, thoải mái cho ngày thường.", 180000],
    [109, "Áo thun cổ tròn", 80, "XL", "./image/Ao2.jpg", "Đơn giản, thoải mái cho ngày thường.", 180000],
    [110, "Áo thun cổ tròn", 80, "2XL", "./image/Ao2.jpg", "Đơn giản, thoải mái cho ngày thường.", 180000],

    [111, "Áo blouse nữ", 50, "S", "./image/Ao3.jpg", "Thanh lịch, dịu dàng.", 250000],
    [112, "Áo blouse nữ", 50, "M", "./image/Ao3.jpg", "Thanh lịch, dịu dàng.", 250000],
    [113, "Áo blouse nữ", 50, "L", "./image/Ao3.jpg", "Thanh lịch, dịu dàng.", 250000],
    [114, "Áo blouse nữ", 50, "XL", "./image/Ao3.jpg", "Thanh lịch, dịu dàng.", 250000],
    [115, "Áo blouse nữ", 50, "2XL", "./image/Ao3.jpg", "Thanh lịch, dịu dàng.", 250000],

    [116, "Áo polo", 70, "S", "./image/Ao4.jpg", "Trẻ trung, dễ phối đồ.", 210000],
    [117, "Áo polo", 70, "M", "./image/Ao4.jpg", "Trẻ trung, dễ phối đồ.", 210000],
    [118, "Áo polo", 70, "L", "./image/Ao4.jpg", "Trẻ trung, dễ phối đồ.", 210000],
    [119, "Áo polo", 70, "XL", "./image/Ao4.jpg", "Trẻ trung, dễ phối đồ.", 210000],
    [120, "Áo polo", 70, "2XL", "./image/Ao4.jpg", "Trẻ trung, dễ phối đồ.", 210000],

    [121, "Áo khoác bomber", 30, "S", "./image/Ao5.jpg", "Thời thượng, giữ ấm tốt.", 420000],
    [122, "Áo khoác bomber", 30, "M", "./image/Ao5.jpg", "Thời thượng, giữ ấm tốt.", 420000],
    [123, "Áo khoác bomber", 30, "L", "./image/Ao5.jpg", "Thời thượng, giữ ấm tốt.", 420000],
    [124, "Áo khoác bomber", 30, "XL", "./image/Ao5.jpg", "Thời thượng, giữ ấm tốt.", 420000],
    [125, "Áo khoác bomber", 30, "2XL", "./image/Ao5.jpg", "Thời thượng, giữ ấm tốt.", 420000],

    [126, "Áo hoodie", 35, "S", "./image/Ao6.jpg", "Phong cách đường phố, ấm áp.", 390000],
    [127, "Áo hoodie", 35, "M", "./image/Ao6.jpg", "Phong cách đường phố, ấm áp.", 390000],
    [128, "Áo hoodie", 35, "L", "./image/Ao6.jpg", "Phong cách đường phố, ấm áp.", 390000],
    [129, "Áo hoodie", 35, "XL", "./image/Ao6.jpg", "Phong cách đường phố, ấm áp.", 390000],
    [130, "Áo hoodie", 35, "2XL", "./image/Ao6.jpg", "Phong cách đường phố, ấm áp.", 390000],

    [131, "Áo len nữ", 40, "S", "./image/Ao7.jpg", "Ấm áp và nữ tính.", 310000],
    [132, "Áo len nữ", 40, "M", "./image/Ao7.jpg", "Ấm áp và nữ tính.", 310000],
    [133, "Áo len nữ", 40, "L", "./image/Ao7.jpg", "Ấm áp và nữ tính.", 310000],
    [134, "Áo len nữ", 40, "XL", "./image/Ao7.jpg", "Ấm áp và nữ tính.", 310000],
    [135, "Áo len nữ", 40, "2XL", "./image/Ao7.jpg", "Ấm áp và nữ tính.", 310000],

    [136, "Áo croptop", 60, "S", "./image/Ao8.jpg", "Thời trang cá tính.", 240000],
    [137, "Áo croptop", 60, "M", "./image/Ao8.jpg", "Thời trang cá tính.", 240000],
    [138, "Áo croptop", 60, "L", "./image/Ao8.jpg", "Thời trang cá tính.", 240000],
    [139, "Áo croptop", 60, "XL", "./image/Ao8.jpg", "Thời trang cá tính.", 240000],
    [140, "Áo croptop", 60, "2XL", "./image/Ao8.jpg", "Thời trang cá tính.", 240000],

    [141, "Áo tanktop", 55, "S", "./image/Ao9.jpg", "Thích hợp mùa hè.", 200000],
    [142, "Áo tanktop", 55, "M", "./image/Ao9.jpg", "Thích hợp mùa hè.", 200000],
    [143, "Áo tanktop", 55, "L", "./image/Ao9.jpg", "Thích hợp mùa hè.", 200000],
    [144, "Áo tanktop", 55, "XL", "./image/Ao9.jpg", "Thích hợp mùa hè.", 200000],
    [145, "Áo tanktop", 55, "2XL", "./image/Ao9.jpg", "Thích hợp mùa hè.", 200000],

    [146, "Áo khoác dạ", 25, "S", "./image/Ao10.jpg", "Sang trọng, giữ nhiệt tốt.", 450000],
    [147, "Áo khoác dạ", 25, "M", "./image/Ao10.jpg", "Sang trọng, giữ nhiệt tốt.", 450000],
    [148, "Áo khoác dạ", 25, "L", "./image/Ao10.jpg", "Sang trọng, giữ nhiệt tốt.", 450000],
    [149, "Áo khoác dạ", 25, "XL", "./image/Ao10.jpg", "Sang trọng, giữ nhiệt tốt.", 450000],
    [150, "Áo khoác dạ", 25, "2XL", "./image/Ao10.jpg", "Sang trọng, giữ nhiệt tốt.", 450000],

    ];

foreach ($products as $p) {
    $sql = "INSERT INTO hanghoa (ID, TenSP, SL, ThuocTinh, HinhAnh, MoTa, GiaBan)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Lỗi prepare: " . $conn->error); // In ra lỗi nếu câu lệnh prepare thất bại
    }
    $ID = $p[0];
    $TenSP = $p[1];
    $SL = $p[2];
    $ThuocTinh = $p[3];
    $HinhAnh = $p[4];
    $MoTa = $p[5];
    $GiaBan = $p[6];

    $stmt->bind_param("isisssi", $ID, $TenSP, $SL, $ThuocTinh, $HinhAnh, $MoTa, $GiaBan);

    if ($stmt->execute()) {
        echo "✅ Thêm sản phẩm {$p[1]} thành công!<br>";
    } else {
        echo "❌ Lỗi khi thêm {$p[1]}: " . $stmt->error . "<br>";
    }
}

$conn->close();
?>
