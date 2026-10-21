<?php declare(strict_types=1);

namespace Acme\Vietnam\Search;

/**
 * Gấp chữ "đ" thành "d" cho tìm kiếm sản phẩm.
 *
 * Bảng product_search_keyword dùng collation utf8mb4_unicode_ci: MySQL đã coi mọi
 * nguyên âm có dấu bằng chữ không dấu (á = a, ặ = a, ơ = o, ữ = u…), nhưng "đ" là một
 * chữ cái riêng trong Unicode, KHÔNG bằng "d". Khách gõ không dấu "dam" vì thế không ra
 * "đầm". Đã kiểm từng chữ cái tiếng Việt bằng MySQL 8.0 ngày 21/10/2026 — chỉ đ/Đ lệch,
 * nên không cần bảng chuyển đổi đầy đủ.
 *
 * @internal
 */
final class VietnameseTextFolding
{
    private const MAP = ['đ' => 'd', 'Đ' => 'D'];

    public static function fold(string $text): string
    {
        return strtr($text, self::MAP);
    }
}
