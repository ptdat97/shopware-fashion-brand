<?php declare(strict_types=1);

namespace Acme\Vietnam\Tests\Integration\Search;

use Acme\Vietnam\Search\VietnameseSearchKeywordAnalyzer;
use Acme\Vietnam\Search\VietnameseSearchTermInterpreter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Bọc service thật của core bằng decorator (plugin không cài vào DB test dùng chung)
 * rồi tra bảng product_search_keyword thật — kiểm cả hành vi collation của MySQL.
 *
 * @internal
 */
class VietnameseSearchTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testCoreAloneMissesUnaccentedD(): void
    {
        $product = $this->createProduct('Đầm xoè acmetest');
        $this->index($product, static::getContainer()->get(ProductSearchKeywordAnalyzer::class));
        $core = static::getContainer()->get(ProductSearchTermInterpreter::class);

        static::assertContains('đầm', $core->interpret('đầm', $this->context)->getAllTerms(), 'Đối chứng: đã lập chỉ mục');
        static::assertNotContains('đầm', $core->interpret('dam', $this->context)->getAllTerms(), 'Lỗi gốc: collation coi đ ≠ d');
    }

    public function testAnalyzerIndexesFoldedKeywordFromRealProduct(): void
    {
        $product = $this->createProduct('Đầm xoè acmetest');

        $keywords = (new VietnameseSearchKeywordAnalyzer(static::getContainer()->get(ProductSearchKeywordAnalyzer::class)))
            ->analyze($product, $this->context, [['field' => 'name', 'tokenize' => true, 'ranking' => 700]]);

        static::assertTrue($keywords->has('đầm'));
        static::assertTrue($keywords->has('dầm'));
        static::assertSame($keywords->get('đầm')?->getRanking(), $keywords->get('dầm')?->getRanking());
    }

    public function testUnaccentedAndAccentedQueriesFindFoldedKeyword(): void
    {
        $product = $this->createProduct('Đầm xoè acmetest');
        $this->index($product, new VietnameseSearchKeywordAnalyzer(static::getContainer()->get(ProductSearchKeywordAnalyzer::class)));

        $interpreter = new VietnameseSearchTermInterpreter(static::getContainer()->get(ProductSearchTermInterpreter::class));

        foreach (['dam', 'đầm', 'Đầm', 'dầm'] as $query) {
            static::assertContains(
                'dầm',
                $interpreter->interpret($query, $this->context)->getAllTerms(),
                \sprintf('"%s" phải khớp từ khoá đã gấp', $query)
            );
        }
    }

    private function createProduct(string $name): ProductEntity
    {
        $ids = new IdsCollection();
        static::getContainer()->get('product.repository')->create([[
            'id' => $ids->get('p'),
            'productNumber' => 'ACME-' . $ids->get('p'),
            'name' => $name,
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
            'tax' => ['name' => 'acme', 'taxRate' => 10],
        ]], $this->context);

        $product = static::getContainer()->get('product.repository')
            ->search(new Criteria([$ids->get('p')]), $this->context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);

        return $product;
    }

    /**
     * Ghi từ khoá analyzer sinh ra — như SearchKeywordUpdater:
     * product_search_keyword (lọc sản phẩm) và product_keyword_dictionary (KeywordLoader tra
     * từ khách gõ ở bảng này).
     */
    private function index(ProductEntity $product, ProductSearchKeywordAnalyzerInterface $analyzer): void
    {
        $keywords = $analyzer->analyze($product, $this->context, [['field' => 'name', 'tokenize' => true, 'ranking' => 700]]);

        foreach ($keywords as $keyword) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO product_search_keyword
                    (id, version_id, product_id, product_version_id, language_id, keyword, ranking, created_at)
                 VALUES (:id, :version, :product, :version, :lang, :keyword, :ranking, :now)',
                [
                    'id' => Uuid::randomBytes(),
                    'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'product' => Uuid::fromHexToBytes($product->getId()),
                    'lang' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    'keyword' => $keyword->getKeyword(),
                    'ranking' => $keyword->getRanking(),
                    'now' => '2026-10-21 00:00:00.000',
                ]
            );

            $this->connection->executeStatement(
                'INSERT IGNORE INTO product_keyword_dictionary (id, language_id, keyword) VALUES (:id, :lang, :keyword)',
                [
                    'id' => Uuid::randomBytes(),
                    'lang' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    'keyword' => $keyword->getKeyword(),
                ]
            );
        }
    }
}
