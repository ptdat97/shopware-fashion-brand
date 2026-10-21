<?php declare(strict_types=1);

namespace Acme\Vietnam\Search;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeyword;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeywordCollection;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Framework\Context;

/**
 * Lập chỉ mục từ khoá sản phẩm: thêm bản gấp "đ → d" bên cạnh từ khoá gốc.
 *
 * "đầm" được lưu thêm "dầm" (cùng ranking). Nhờ collation không phân biệt dấu thanh,
 * "dầm" khớp cả khi khách gõ "dam", "dầm" hay — sau khi VietnameseSearchTermInterpreter
 * gấp từ tìm — "đầm". Giữ từ khoá gốc để không làm thay đổi dữ liệu bên khác có thể đọc.
 *
 * Decorate service ProductSearchKeywordAnalyzer (core không có sự kiện/extension point
 * nào trong bước này — EXT-01); cài interface, không kế thừa class của core (EXT-02).
 *
 * @internal
 */
final class VietnameseSearchKeywordAnalyzer implements ProductSearchKeywordAnalyzerInterface
{
    public function __construct(private readonly ProductSearchKeywordAnalyzerInterface $decorated)
    {
    }

    public function analyze(ProductEntity $product, Context $context, array $configFields): AnalyzedKeywordCollection
    {
        $keywords = $this->decorated->analyze($product, $context, $configFields);

        // Chép ra mảng trước: thêm phần tử vào chính collection đang duyệt
        foreach ($keywords->getElements() as $keyword) {
            $folded = VietnameseTextFolding::fold($keyword->getKeyword());

            if ($folded !== $keyword->getKeyword()) {
                // add() của collection giữ ranking cao hơn nếu từ khoá đã có
                $keywords->add(new AnalyzedKeyword($folded, $keyword->getRanking()));
            }
        }

        return $keywords;
    }
}
