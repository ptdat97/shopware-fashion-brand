<?php declare(strict_types=1);

namespace Acme\DemoData\Service;

/**
 * Danh mục sản phẩm demo — chỉ là dữ liệu, không có logic.
 *
 * Thứ tự mảng quyết định khoá sản phẩm (`key`: ANV-1001, ANV-1002…) nên **không đổi
 * thứ tự** và không chèn vào giữa: id sản phẩm được suy ra từ khoá, đổi thứ tự sẽ tạo
 * trùng lặp thay vì cập nhật sản phẩm cũ.
 *
 * `key` và `number` tách nhau từ Phase MB2: mã SKU mang tiền tố của brand
 * (`MAY-1005`), còn id vẫn suy ra từ khoá cũ `ANV-1005` — nhờ vậy chạy lại seed trên
 * dữ liệu cũ sẽ *đổi mã* sản phẩm có sẵn chứ không tạo sản phẩm thứ hai.
 */
final class DemoProductCatalog
{
    public const TAG_AUTUMN_WINTER = 'Thu Đông 2026';

    /**
     * @return list<array{
     *     key: string,
     *     number: string,
     *     brand: string,
     *     tags?: list<string>,
     *     listPrice?: int,
     *     name: string,
     *     category: string,
     *     price: int,
     *     stock: int,
     *     colors: list<string>,
     *     material: string,
     *     modelSize: string,
     *     images: list<string>,
     *     description: string
     * }>
     */
    public static function definitions(): array
    {
        $definitions = [
            [
                'brand' => 'ANV',
                'name' => 'Đầm maxi hoa nhí dáng xoè',
                'category' => 'Đầm',
                'price' => 1_250_000,
                'stock' => 42,
                'colors' => ['Hoa nhiều màu'],
                'material' => 'Voan lụa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['DTT_8954.jpg', 'DTT_9009.jpg', 'DTT_9123.jpg', 'DTT_9150.jpg'],
                'description' => '<p>Đầm maxi hoạ tiết hoa rực rỡ, thân trên chun co giãn ôm nhẹ, chân váy xoè nhiều tầng tạo độ bay bổng. Chất voan lụa mỏng nhẹ, thoáng mát — phù hợp khí hậu nắng nóng và các chuyến đi biển, dạo phố cuối tuần.</p><ul><li>Dáng maxi dài qua mắt cá</li><li>Thân trên chun co giãn, dây hai quai điều chỉnh được</li><li>Chất liệu voan lụa mềm, không bí</li></ul>',
            ],
            [
                'brand' => 'MAY',
                'name' => 'Áo polo dệt kim tay ngắn',
                'tags' => [self::TAG_AUTUMN_WINTER],
                'category' => 'Áo',
                'price' => 690_000,
                'stock' => 65,
                'colors' => ['Nâu'],
                'material' => 'Len dệt kim',
                'modelSize' => 'Người mẫu cao 1m70, mặc size M',
                'images' => ['VNQ00498 copy.jpg', 'VNQ00869 copy.jpg', 'VNQ00887 copy.jpg', 'VNQ00905 copy.jpg'],
                'description' => '<p>Áo polo dệt kim tay ngắn với cổ bẻ và hàng cúc kim loại nhỏ, phom vừa vặn tôn dáng. Sắc nâu trầm dễ phối cùng quần âu hoặc chân váy dài, mang hơi hướng cổ điển nhưng vẫn hiện đại.</p><ul><li>Chất len dệt kim mịn, co giãn nhẹ</li><li>Cổ bẻ, cúc trang trí</li><li>Phom regular fit</li></ul>',
            ],
            [
                'brand' => 'LLN',
                'name' => 'Đầm midi cổ thuyền xếp ly',
                'tags' => [self::TAG_AUTUMN_WINTER],
                'category' => 'Đầm',
                'price' => 1_450_000,
                'stock' => 28,
                'colors' => ['Kem'],
                'material' => 'Lụa tơ',
                'modelSize' => 'Người mẫu cao 1m70, mặc size S',
                'images' => ['_DSC6767.jpg', '_DSC6793.jpg', '_DSC6826.jpg', '_DSC6834.jpg'],
                'description' => '<p>Đầm midi tay bồng, cổ cao thanh lịch, eo chiết tạo khối kết hợp chân váy xoè mềm mại. Tông kem sáng nhẹ nhàng, phù hợp dự tiệc nhẹ, đi làm hoặc chụp ảnh.</p><ul><li>Tay bồng dài, bo cổ tay</li><li>Eo chiết ly nổi khối</li><li>Chất lụa tơ rũ, giữ phom tốt</li></ul>',
            ],
            [
                'brand' => 'ANV',
                'name' => 'Đầm đen cổ chữ V dáng xoè',
                'tags' => [self::TAG_AUTUMN_WINTER],
                'category' => 'Đầm',
                'price' => 1_390_000,
                'stock' => 37,
                'colors' => ['Đen'],
                'material' => 'Tuyết mưa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['IMG_5804.jpeg', 'IMG_5805.jpeg', 'IMG_5806.jpeg', 'IMG_5807.jpeg'],
                'description' => '<p>Đầm đen cổ chữ V với chi tiết gấp nếp ở vai tạo điểm nhấn, chân váy xoè nhẹ đến bắp chân. Món đồ cơ bản không bao giờ lỗi mốt, dễ mặc từ đi làm đến dự sự kiện buổi tối.</p><ul><li>Cổ chữ V, vai gấp nếp</li><li>Chất tuyết mưa dày dặn, ít nhăn</li><li>Có lót trong</li></ul>',
            ],
            [
                'brand' => 'MAY',
                'name' => 'Set áo sơ mi & chân váy tông kem',
                'category' => 'Bộ sưu tập',
                'price' => 1_690_000,
                'stock' => 24,
                'colors' => ['Kem', 'Be'],
                'material' => 'Đũi lụa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['VNQ05143 copy.jpg', 'VNQ05152 copy.jpg', 'VNQ05166 copy.jpg', 'VNQ05184 copy.jpg'],
                'description' => '<p>Set hai món gồm áo sơ mi cộc tay phom rộng và chân váy dài cùng tông. Chất đũi lụa thoáng mát, thấm hút tốt — lựa chọn lý tưởng cho ngày hè. Có thể mặc rời từng món để phối đa dạng.</p><ul><li>Áo sơ mi cổ đức, phom rộng thoải mái</li><li>Chân váy dài có dây rút eo</li><li>Chất đũi lụa mát, ít nhăn</li></ul>',
            ],
            [
                'brand' => 'LLN',
                'name' => 'Đầm trắng sát nách cúc bọc',
                'category' => 'Đầm',
                'price' => 1_290_000,
                'listPrice' => 1_490_000,
                'stock' => 31,
                'colors' => ['Trắng'],
                'material' => 'Tweed',
                'modelSize' => 'Người mẫu cao 1m70, mặc size S',
                'images' => ['VNQ06518 copy.jpg', 'VNQ06526 copy.jpg', 'VNQ06565 copy.jpg'],
                'description' => '<p>Đầm trắng sát nách phom ôm, hàng cúc bọc chạy dọc thân trước và xẻ tà nhẹ. Chất tweed dệt nổi tinh tế mang nét thanh lịch cổ điển, tôn đường cong tự nhiên.</p><ul><li>Phom ôm body, xẻ tà trước</li><li>Cúc bọc cùng màu</li><li>Chất tweed dệt nổi, có lót</li></ul>',
            ],
            [
                'brand' => 'ANV',
                'name' => 'Đầm đen tay loe dáng ôm',
                'tags' => [self::TAG_AUTUMN_WINTER],
                'category' => 'Đầm',
                'price' => 1_490_000,
                'listPrice' => 1_690_000,
                'stock' => 19,
                'colors' => ['Đen'],
                'material' => 'Tuyết mưa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size M',
                'images' => ['VNQ06903 copy.jpg', 'VNQ06910 copy.jpg', 'VNQ06955 copy.jpg', 'VNQ06971 copy.jpg'],
                'description' => '<p>Đầm đen dáng ôm với tay loe nhẹ và cổ chữ V sâu vừa phải, đi kèm đai eo bản nhỏ. Thiết kế tôn dáng, thích hợp cho những buổi tiệc tối hoặc sự kiện trang trọng.</p><ul><li>Tay loe, cổ chữ V</li><li>Đai eo rời tạo điểm nhấn</li><li>Chất tuyết mưa co giãn nhẹ</li></ul>',
            ],
            [
                'brand' => 'LLN',
                'name' => 'Áo thun kẻ sọc phối chân váy',
                'category' => 'Áo',
                'price' => 590_000,
                'stock' => 73,
                'colors' => ['Xanh pastel', 'Trắng'],
                'material' => 'Cotton pha',
                'modelSize' => 'Người mẫu cao 1m70, mặc size M',
                'images' => ['VNQ09889 copy.jpg', 'VNQ09903 copy.jpg', 'VNQ09918 copy.jpg', 'VNQ09943 copy.jpg'],
                'description' => '<p>Áo thun kẻ sọc ngang phối cổ polo, phom rộng trẻ trung năng động. Chất cotton pha thoáng khí, thấm hút mồ hôi tốt — thoải mái cho cả ngày dài.</p><ul><li>Hoạ tiết kẻ sọc ngang cổ điển</li><li>Cổ polo, phom oversize nhẹ</li><li>Cotton pha thoáng mát</li></ul>',
            ],
            [
                'brand' => 'MAY',
                'name' => 'Đầm lụa xanh pastel dáng ôm',
                'category' => 'Đầm',
                'price' => 1_590_000,
                'stock' => 22,
                'colors' => ['Xanh pastel'],
                'material' => 'Lụa tơ',
                'modelSize' => 'Người mẫu cao 1m70, mặc size S',
                'images' => ['_1772166247_DSC7352_1.jpg', '_1772166249_DSC7277_1.jpg', '_1772166251_DSC7304_1.jpg', '_1772166255_DSC7333_1.jpg'],
                'description' => '<p>Đầm lụa tông xanh pastel dịu mắt, cổ thuyền cách điệu với phần vai đắp chéo mềm mại. Đai eo bản nhỏ tôn vòng hai, chân váy ôm nhẹ tạo dáng thanh thoát.</p><ul><li>Cổ thuyền, vai đắp chéo</li><li>Đai eo bản nhỏ có khoá</li><li>Chất lụa tơ rũ mềm</li></ul>',
            ],
            [
                'brand' => 'ANV',
                'name' => 'Set áo lụa & chân váy xếp ly',
                'category' => 'Bộ sưu tập',
                'price' => 1_790_000,
                'stock' => 26,
                'colors' => ['Kem', 'Be'],
                'material' => 'Lụa tơ',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['_1773800496VNQ06585 copy_1.jpg', '_1773800496VNQ06609 copy_1.jpg', '_1773800497VNQ06582 copy_1.jpg', '_1773800497VNQ06590 copy_1.jpg'],
                'description' => '<p>Set hai món thanh lịch: áo lụa sát nách cổ thuyền phối chân váy xếp ly dài. Bảng màu trung tính dễ phối, phù hợp đi tiệc trà, sự kiện ban ngày hoặc chụp ảnh.</p><ul><li>Áo lụa cổ thuyền, tay cánh tiên</li><li>Chân váy xếp ly dài qua gối</li><li>Có thể mặc rời từng món</li></ul>',
            ],
            [
                'brand' => 'MAY',
                'name' => 'Chân váy dài xếp ly tông be',
                'category' => 'Chân váy',
                'price' => 790_000,
                'listPrice' => 990_000,
                'stock' => 58,
                'colors' => ['Be', 'Kem'],
                'material' => 'Đũi lụa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['_1776485633VNQ00471 copy_1.jpg', '_1776485634VNQ00473 copy_1.jpg', '_1776485636VNQ00480 copy_1.jpg', '_1776485638VNQ00536 copy_1.jpg'],
                'description' => '<p>Chân váy dài xếp ly tông be trung tính, cạp chun co giãn thoải mái. Dễ phối cùng áo thun, sơ mi hay áo len — món đồ nền tảng cho tủ quần áo hằng ngày.</p><ul><li>Xếp ly đều, tạo độ rơi đẹp</li><li>Cạp chun co giãn</li><li>Chất đũi lụa thoáng mát</li></ul>',
            ],
            [
                'brand' => 'LLN',
                'name' => 'Đầm dạ hội dáng dài tay bồng',
                'tags' => [self::TAG_AUTUMN_WINTER],
                'category' => 'Đầm',
                'price' => 1_890_000,
                'stock' => 16,
                'colors' => ['Kem'],
                'material' => 'Lụa tơ',
                'modelSize' => 'Người mẫu cao 1m70, mặc size S',
                'images' => ['_DSC6841.jpg', '_DSC6863.jpg', '_DSC7359.jpg', '_dsc7406-1772166267.jpg'],
                'description' => '<p>Đầm dài tay bồng với phần eo chiết tinh tế, chân váy xoè rộng tạo hiệu ứng thướt tha khi di chuyển. Lựa chọn nổi bật cho tiệc cưới, sự kiện trang trọng.</p><ul><li>Tay bồng dài</li><li>Eo chiết, chân váy xoè rộng</li><li>Chất lụa tơ cao cấp, có lót</li></ul>',
            ],
        ];

        // Khoá gắn theo vị trí trong mảng — nguồn duy nhất sinh ra id sản phẩm.
        // Số thứ tự đánh chung toàn catalog để SKU không trùng giữa các brand.
        foreach ($definitions as $i => $definition) {
            $definitions[$i]['key'] = 'ANV-' . (1001 + $i);
            $definitions[$i]['number'] = $definition['brand'] . '-' . (1001 + $i);
        }

        return $definitions;
    }

    /**
     * Thương hiệu demo. Khoá là mã brand 3 ký tự — tiền tố SKU (ADR-003).
     *
     * Anivele là brand thật của dự án; hai brand còn lại là **giả định** để kiểm thử
     * bộ lọc, trang brand và đơn nhiều brand. Thay bằng brand thật trước go-live.
     *
     * @return array<string, array{name: string, description: string, position: int, featured: bool}>
     */
    public static function brands(): array
    {
        return [
            'ANV' => [
                'name' => 'Anivele',
                'description' => '<p>Thời trang nữ tông trung tính ấm — đầm, áo và chân váy cho nhịp sống hằng ngày.</p>',
                'position' => 1,
                'featured' => true,
            ],
            'MAY' => [
                'name' => 'Mây Atelier',
                'description' => '<p>Thương hiệu demo — dệt kim và đồ đi làm thanh lịch.</p>',
                'position' => 2,
                'featured' => false,
            ],
            'LLN' => [
                'name' => 'Linh Lan',
                'description' => '<p>Thương hiệu demo — đầm dự tiệc và lụa mềm.</p>',
                'position' => 3,
                'featured' => false,
            ],
        ];
    }

    /**
     * Bộ sưu tập — danh mục con của "Bộ sưu tập", sản phẩm lấy bằng product stream của
     * core, URL /collections/{tên} nhờ ô "Là bộ sưu tập" (AcmeCatalog).
     *
     * Bộ lọc viết đúng dạng Administration lưu: multi OR → multi AND → điều kiện.
     *
     * @return array<string, list<array<string, mixed>>> tên => điều kiện (nối AND)
     */
    public static function collections(): array
    {
        return [
            // Tương đối theo thời điểm request — tự cập nhật, không cần chạy lại gì
            'Hàng mới về' => [
                ['type' => 'since', 'field' => 'createdAt', 'value' => 'P30D', 'parameters' => ['operator' => 'lte']],
            ],
            self::TAG_AUTUMN_WINTER => [
                ['type' => 'equals', 'field' => 'tags.name', 'value' => self::TAG_AUTUMN_WINTER],
            ],
            // Giảm giá so với giá niêm yết (listPrice) — điều kiện có sẵn trong Admin:
            // "Giá rẻ nhất → Phần trăm" lớn hơn 0
            'Sale' => [
                ['type' => 'range', 'field' => 'cheapestPrice.percentage', 'parameters' => ['gt' => 0]],
            ],
        ];
    }

    /**
     * Meta description demo cho danh mục và bộ sưu tập (trường native `metaDescription`).
     * Nội dung thật do người vận hành viết lại — đây chỉ để trang không trống meta.
     *
     * @return array<string, string> tên danh mục => meta description
     */
    public static function metaDescriptions(): array
    {
        return [
            'Đầm' => 'Đầm nữ từ nhiều thương hiệu: đầm maxi, midi, dạ hội, đi làm. Giao toàn quốc, thanh toán khi nhận hàng.',
            'Áo' => 'Áo nữ: polo dệt kim, sơ mi, áo thun phối đồ. Nhiều thương hiệu, đủ size S–XL, thanh toán khi nhận hàng.',
            'Chân váy' => 'Chân váy dài, xếp ly, chữ A cho đi làm và dạo phố. Đổi size dễ dàng, thanh toán khi nhận hàng.',
            'Bộ sưu tập' => 'Bộ sưu tập theo mùa và chủ đề từ các thương hiệu thời trang nữ.',
            'Hàng mới về' => 'Mẫu thời trang nữ mới về trong 30 ngày qua từ mọi thương hiệu.',
            self::TAG_AUTUMN_WINTER => 'Bộ sưu tập Thu Đông 2026: dệt kim, đầm dài tay, gam trầm ấm.',
            'Sale' => 'Thời trang nữ đang giảm giá so với giá niêm yết, cập nhật liên tục.',
        ];
    }

    /**
     * Meta description sản phẩm (trường native `metaDescription`): câu đầu của mô tả,
     * cắt ở ranh giới từ cho vừa ~155 ký tự. Bỏ trống thì core lấy nguyên mô tả, cắt
     * cứng ở 255 ký tự — Google chỉ hiện khoảng 160.
     */
    public static function productMetaDescription(string $descriptionHtml, int $maxLength = 155): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags(str_replace('</p>', ' ', $descriptionHtml))));

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $lastSpace === false ? $maxLength : $lastSpace), ' ,.;:—-') . '…';
    }

    /** Danh mục cần có, theo đúng thứ tự hiển thị */
    public static function categories(): array
    {
        return ['Đầm', 'Áo', 'Chân váy', 'Bộ sưu tập'];
    }

    /** @return array<string,string> tên màu => mã hex cho swatch */
    public static function colors(): array
    {
        return [
            'Đen' => '#1a1a1a',
            'Trắng' => '#ffffff',
            'Kem' => '#f0e6d2',
            'Be' => '#d9c9b0',
            'Nâu' => '#6b4a34',
            'Xanh pastel' => '#b9d4d8',
            'Hoa nhiều màu' => '#c8a2c8',
        ];
    }

    /** @return list<string> */
    public static function sizes(): array
    {
        return ['S', 'M', 'L', 'XL'];
    }
}
