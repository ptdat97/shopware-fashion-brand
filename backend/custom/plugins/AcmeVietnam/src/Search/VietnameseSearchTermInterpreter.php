<?php declare(strict_types=1);

namespace Acme\Vietnam\Search;

use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;

/**
 * Diễn giải từ khách tìm: gấp "đ → d" trước khi core tra bảng từ khoá.
 *
 * Đi cặp với VietnameseSearchKeywordAnalyzer (bảng từ khoá đã có bản gấp). Chỉ áp cho
 * tìm kiếm sản phẩm (ProductSearchBuilder: trang tìm kiếm + gợi ý) — không đụng
 * Tokenizer chung, nên tìm kiếm trong Administration (khách hàng "Đức"…) không đổi.
 *
 * ⚠️ Sau khi bật cần lập chỉ mục lại sản phẩm, nếu không từ có "đ" sẽ không khớp:
 *    bin/console dal:refresh:index --only=product.indexer
 *
 * @internal
 */
final class VietnameseSearchTermInterpreter implements ProductSearchTermInterpreterInterface
{
    public function __construct(private readonly ProductSearchTermInterpreterInterface $decorated)
    {
    }

    public function interpret(string $word, Context $context): SearchPattern
    {
        return $this->decorated->interpret(VietnameseTextFolding::fold($word), $context);
    }
}
