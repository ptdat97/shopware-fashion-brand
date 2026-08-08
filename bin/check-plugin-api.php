<?php declare(strict_types=1);
/**
 * Kiểm tra mọi class Shopware mà plugin `use` có thật sự tồn tại trong vendor/ hay không.
 *
 * Bắt lỗi bịa API — dùng tên class từ trí nhớ, từ tutorial cho version cũ, hoặc do AI sinh ra.
 * Ví dụ thật: SynchronousPaymentHandlerInterface đã bị xoá khỏi Shopware 6.7 nhưng phần lớn
 * tutorial trên mạng vẫn dạy. Xem docs/08-quy-tac-phat-trien.md — Quy tắc số 3.
 *
 * Chạy từ thư mục gốc dự án:
 *   php bin/check-plugin-api.php                          # kiểm tra toàn bộ custom/plugins
 *   php bin/check-plugin-api.php custom/plugins/TenPlugin # kiểm tra một plugin
 *
 * Exit code 0 = sạch, 1 = có class không tồn tại.
 */

if (!is_file('vendor/autoload.php')) {
    fwrite(STDERR, "Phải chạy từ thư mục gốc dự án (nơi có vendor/autoload.php).\n");
    exit(2);
}

require 'vendor/autoload.php';

$dir = $argv[1] ?? 'custom/plugins';

if (!is_dir($dir)) {
    fwrite(STDERR, "Không tìm thấy thư mục: $dir\n");
    exit(2);
}

$missing = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    preg_match_all('/^use\s+(Shopware\\\\[^;\s]+)/m', (string) file_get_contents($file->getPathname()), $matches);

    foreach ($matches[1] as $fqcn) {
        if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn)) {
            continue;
        }
        $missing[$fqcn][] = $file->getPathname();
    }
}

if ($missing !== []) {
    echo "❌ Class Shopware KHÔNG TỒN TẠI trong vendor/:\n\n";
    foreach ($missing as $fqcn => $paths) {
        echo "  $fqcn\n";
        foreach (array_unique($paths) as $path) {
            echo "      dùng ở: $path\n";
        }
    }
    echo "\nĐang bịa API. Đọc kiến trúc thật trước khi viết tiếp:\n";
    echo "  docs/08-quy-tac-phat-trien.md — Quy tắc số 3\n";
    exit(1);
}

echo "✅ Mọi class Shopware được dùng đều tồn tại trong vendor/.\n";
exit(0);
