<?php declare(strict_types=1);

namespace Acme\Vietnam\Tests\Unit\Search;

use Acme\Vietnam\Search\VietnameseSearchKeywordAnalyzer;
use Acme\Vietnam\Search\VietnameseSearchTermInterpreter;
use Acme\Vietnam\Search\VietnameseTextFolding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeyword;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeywordCollection;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;

/**
 * @internal
 */
class VietnameseSearchFoldingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function foldingCases(): iterable
    {
        yield 'đ thường' => ['đầm', 'dầm'];
        yield 'Đ hoa' => ['Đầm Đen', 'Dầm Den'];
        yield 'dấu thanh giữ nguyên — collation MySQL đã tự bỏ qua' => ['áo sơ mi', 'áo sơ mi'];
        yield 'không có đ' => ['polo', 'polo'];
    }

    #[DataProvider('foldingCases')]
    public function testFoldsOnlyD(string $input, string $expected): void
    {
        static::assertSame($expected, VietnameseTextFolding::fold($input));
    }

    public function testAnalyzerAddsFoldedKeywordWithSameRanking(): void
    {
        $inner = $this->createStub(ProductSearchKeywordAnalyzerInterface::class);
        $inner->method('analyze')->willReturn(new AnalyzedKeywordCollection([
            new AnalyzedKeyword('đầm', 700),
            new AnalyzedKeyword('polo', 700),
            new AnalyzedKeyword('dầm', 900),
        ]));

        $keywords = (new VietnameseSearchKeywordAnalyzer($inner))
            ->analyze(new ProductEntity(), Context::createDefaultContext(), []);

        $rankings = [];
        foreach ($keywords as $keyword) {
            $rankings[$keyword->getKeyword()] = $keyword->getRanking();
        }
        ksort($rankings);

        static::assertSame(
            ['dầm' => 900.0, 'polo' => 700.0, 'đầm' => 700.0],
            $rankings,
            'Giữ từ gốc, thêm bản gấp; trùng thì giữ ranking cao hơn; không nhân đôi từ không có đ'
        );
    }

    public function testInterpreterPassesFoldedWordToCore(): void
    {
        $inner = $this->createMock(ProductSearchTermInterpreterInterface::class);
        $inner->expects(static::once())
            ->method('interpret')
            ->with('dầm den')
            ->willReturn(new SearchPattern(new SearchTerm('dầm den')));

        (new VietnameseSearchTermInterpreter($inner))->interpret('đầm đen', Context::createDefaultContext());
    }
}
