<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Integration\Commerce;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\TestShortHands;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Nghiệm thu MB5: commerce không biết brand — một giỏ/đơn chứa nhiều brand, khuyến
 * mãi theo brand bằng Rule Builder của core. Không có dòng code nào của plugin chạy
 * trong luồng này; test giữ cho giả định kiến trúc (ADR-003) không bị phá về sau.
 *
 * @internal
 */
class MultiBrandCheckoutTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TestShortHands;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $context = Context::createDefaultContext();

        static::getContainer()->get('product_manufacturer.repository')->create([
            ['id' => $this->ids->get('brand-a'), 'name' => 'Brand A', 'acmeBrand' => ['active' => true]],
            ['id' => $this->ids->get('brand-b'), 'name' => 'Brand B', 'acmeBrand' => ['active' => true]],
        ], $context);

        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($this->ids, 'brand-a-dress', 10))
                ->price(100)
                ->visibility()
                ->add('manufacturerId', $this->ids->get('brand-a'))
                ->build(),
            (new ProductBuilder($this->ids, 'brand-b-shirt', 10))
                ->price(200)
                ->visibility()
                ->add('manufacturerId', $this->ids->get('brand-b'))
                ->build(),
        ], $context);
    }

    public function testOneOrderHoldsProductsOfSeveralBrandsAndKeepsTheBrandPerLine(): void
    {
        $context = $this->login($this->getContext());
        $this->addProductToCart($this->ids->get('brand-a-dress'), $context);
        $cart = $this->addProductToCart($this->ids->get('brand-b-shirt'), $context);

        static::assertSame($this->ids->get('brand-a'), $cart->get($this->ids->get('brand-a-dress'))?->getPayloadValue('manufacturerId'));
        static::assertSame($this->ids->get('brand-b'), $cart->get($this->ids->get('brand-b-shirt'))?->getPayloadValue('manufacturerId'));

        $orderId = $this->order($cart, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('type', LineItem::PRODUCT_LINE_ITEM_TYPE));

        /** @var OrderLineItemCollection $lines */
        $lines = static::getContainer()->get('order_line_item.repository')->search($criteria, Context::createDefaultContext())->getEntities();

        $brandPerProduct = [];
        foreach ($lines as $line) {
            $brandPerProduct[$line->getReferencedId()] = $line->getPayload()['manufacturerId'] ?? null;
        }

        static::assertSame([
            $this->ids->get('brand-a-dress') => $this->ids->get('brand-a'),
            $this->ids->get('brand-b-shirt') => $this->ids->get('brand-b'),
        ], $this->sortByKey($brandPerProduct), 'Brand phải đi vào payload dòng đơn để báo cáo theo brand');
    }

    public function testOrderReducesStockOfEveryBrand(): void
    {
        $context = $this->login($this->getContext());
        $this->addProductToCart($this->ids->get('brand-a-dress'), $context);
        $cart = $this->addProductToCart($this->ids->get('brand-b-shirt'), $context);

        $this->order($cart, $context);

        $stocks = static::getContainer()->get('product.repository')
            ->search(new Criteria([$this->ids->get('brand-a-dress'), $this->ids->get('brand-b-shirt')]), Context::createDefaultContext())
            ->getEntities();

        foreach ($stocks as $product) {
            static::assertSame(9, $product->getStock(), (string) $product->getProductNumber());
        }
    }

    public function testBrandPromotionDiscountsOnlyThatBrand(): void
    {
        $this->createBrandPromotion($this->ids->get('brand-a'), 10.0);

        $context = $this->getContext();
        $this->addProductToCart($this->ids->get('brand-a-dress'), $context);
        $cart = $this->addProductToCart($this->ids->get('brand-b-shirt'), $context);

        $discounts = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        static::assertCount(1, $discounts);

        // 10% của Brand A (100) = -10. Nếu rule không giới hạn theo brand sẽ là -30.
        static::assertSame(-10.0, $discounts->first()?->getPrice()?->getTotalPrice());
    }

    private function createBrandPromotion(string $manufacturerId, float $percentage): void
    {
        $ruleId = Uuid::randomHex();
        $orId = Uuid::randomHex();
        $andId = Uuid::randomHex();

        // Cây điều kiện phải đủ orContainer → andContainer → điều kiện, giống Rule Builder
        // lưu — thiếu container thì rule không bao giờ khớp (xem 03-plugin-app.md).
        static::getContainer()->get('rule.repository')->create([[
            'id' => $ruleId,
            'name' => 'Sản phẩm của Brand A',
            'priority' => 1,
            'conditions' => [[
                'id' => $orId,
                'type' => 'orContainer',
                'children' => [[
                    'id' => $andId,
                    'type' => 'andContainer',
                    'children' => [[
                        'type' => LineItemOfManufacturerRule::RULE_NAME,
                        'value' => ['operator' => Rule::OPERATOR_EQ, 'manufacturerIds' => [$manufacturerId]],
                    ]],
                ]],
            ]],
        ]], Context::createDefaultContext());

        static::getContainer()->get('promotion.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => 'Giảm 10% Brand A',
            'active' => true,
            'useCodes' => false,
            'useSetGroups' => false,
            'salesChannels' => [['salesChannelId' => $this->getContext()->getSalesChannelId(), 'priority' => 1]],
            'discounts' => [[
                'scope' => PromotionDiscountEntity::SCOPE_CART,
                'type' => PromotionDiscountEntity::TYPE_PERCENTAGE,
                'value' => $percentage,
                'considerAdvancedRules' => true,
                'discountRules' => [['id' => $ruleId]],
            ]],
        ]], Context::createDefaultContext());
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function sortByKey(array $values): array
    {
        $expectedOrder = [$this->ids->get('brand-a-dress'), $this->ids->get('brand-b-shirt')];

        return array_replace(array_flip($expectedOrder), $values);
    }
}
