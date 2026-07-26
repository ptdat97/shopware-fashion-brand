<?php declare(strict_types=1);

namespace AniveleTheme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Storefront\Framework\ThemeInterface;

class AniveleTheme extends Plugin implements ThemeInterface
{
    private const CUSTOM_FIELD_SET = 'anivele_menu';

    public function install(InstallContext $installContext): void
    {
        $this->createBadgeCustomField($installContext->getContext());
    }

    /**
     * Bản cài sẵn có từ trước không chạy install() nên tạo field ở đây luôn.
     */
    public function update(UpdateContext $updateContext): void
    {
        $this->createBadgeCustomField($updateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        $repository = $this->container->get('custom_field_set.repository');
        \assert($repository instanceof EntityRepository);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET));
        $id = $repository->searchIds($criteria, $uninstallContext->getContext())->firstId();

        if ($id !== null) {
            $repository->delete([['id' => $id]], $uninstallContext->getContext());
        }
    }

    /**
     * Badge cho mục menu (ví dụ "Mới", "Sale") — core không có field này.
     */
    private function createBadgeCustomField(Context $context): void
    {
        $repository = $this->container->get('custom_field_set.repository');
        \assert($repository instanceof EntityRepository);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELD_SET));

        if ($repository->searchIds($criteria, $context)->firstId() !== null) {
            return;
        }

        $repository->create([[
            'name' => self::CUSTOM_FIELD_SET,
            'config' => [
                'label' => [
                    'vi-VN' => 'Menu Anivele',
                    'en-GB' => 'Anivele menu',
                ],
            ],
            'relations' => [
                ['entityName' => 'category'],
            ],
            'customFields' => [
                [
                    'name' => 'anivele_menu_badge',
                    'type' => 'text',
                    'config' => [
                        'label' => [
                            'vi-VN' => 'Nhãn menu',
                            'en-GB' => 'Menu badge',
                        ],
                        'customFieldType' => 'text',
                        'componentName' => 'sw-field',
                        'customFieldPosition' => 1,
                    ],
                ],
            ],
        ]], $context);
    }
}
