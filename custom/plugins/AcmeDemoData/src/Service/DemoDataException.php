<?php declare(strict_types=1);

namespace Acme\DemoData\Service;

/**
 * Lỗi do môi trường chưa sẵn sàng (thiếu thuế, thiếu ảnh…), không phải bug.
 * Controller trả về HTTP 400 kèm thông điệp này cho Administration hiển thị.
 */
class DemoDataException extends \RuntimeException
{
}
