<?php declare(strict_types=1);

namespace Acme\DemoData;

use Shopware\Core\Framework\Plugin;

/**
 * Dữ liệu demo cho môi trường dev/staging: 3 thương hiệu (1 thật, 2 giả định), 12 mẫu,
 * ảnh, thẻ, bộ sưu tập. Không tự nhập khi cài — bấm nút trong cấu hình plugin hoặc chạy
 * `acme:demo:seed`. Chạy lại không nhân bản (id suy ra từ khoá cố định).
 *
 * Bề mặt công khai: lệnh `acme:demo:seed`; route Admin API
 * `GET /api/_action/acme-demo-data/status`, `POST /api/_action/acme-demo-data/import`.
 *
 * Tắt / gỡ: dữ liệu đã nhập là dữ liệu core (sản phẩm, nhà sản xuất…) nên giữ nguyên;
 * chỉ mất nút và lệnh nhập. KHÔNG cài plugin này trên production.
 */
class AcmeDemoData extends Plugin
{
}
