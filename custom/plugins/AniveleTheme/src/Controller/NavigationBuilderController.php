<?php declare(strict_types=1);

namespace AniveleTheme\Controller;

use AniveleTheme\Service\NavigationBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API cho module dựng menu/footer.
 *
 * @internal Chỉ phục vụ module Administration của theme, không phải API công khai.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['api']])]
class NavigationBuilderController
{
    public function __construct(private readonly NavigationBuilder $navigationBuilder)
    {
    }

    /**
     * Trả về cây menu và footer hiện tại để module hiển thị.
     */
    #[Route(
        path: '/api/_action/anivele/navigation',
        name: 'api.action.anivele.navigation.list',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['category:read']],
        methods: [Request::METHOD_GET]
    )]
    public function list(Context $context): JsonResponse
    {
        return new JsonResponse($this->navigationBuilder->describe($context));
    }

    /**
     * Dựng lại menu và/hoặc footer.
     */
    #[Route(
        path: '/api/_action/anivele/navigation/build',
        name: 'api.action.anivele.navigation.build',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['category:create']],
        methods: [Request::METHOD_POST]
    )]
    public function build(Request $request, Context $context): JsonResponse
    {
        $payload = $request->toArray();

        $result = $this->navigationBuilder->build(
            $context,
            (bool) ($payload['menu'] ?? true),
            (bool) ($payload['footer'] ?? true),
            (bool) ($payload['images'] ?? false),
            (bool) ($payload['assignProducts'] ?? true)
        );

        return new JsonResponse($result);
    }

    /**
     * Bật/tắt hiển thị một danh mục trên storefront.
     */
    #[Route(
        path: '/api/_action/anivele/navigation/visibility',
        name: 'api.action.anivele.navigation.visibility',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['category:update']],
        methods: [Request::METHOD_POST]
    )]
    public function visibility(Request $request, Context $context): JsonResponse
    {
        $payload = $request->toArray();
        $categoryId = $payload['categoryId'] ?? null;

        if (!\is_string($categoryId) || $categoryId === '') {
            throw RoutingException::missingRequestParameter('categoryId');
        }

        $this->navigationBuilder->setVisibility(
            $context,
            $categoryId,
            (bool) ($payload['visible'] ?? true)
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * Thêm một mục vào menu hoặc footer.
     */
    #[Route(
        path: '/api/_action/anivele/navigation/item',
        name: 'api.action.anivele.navigation.item.add',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['category:create']],
        methods: [Request::METHOD_POST]
    )]
    public function addItem(Request $request, Context $context): JsonResponse
    {
        $payload = $request->toArray();
        $parentId = $payload['parentId'] ?? null;
        $name = $payload['name'] ?? null;

        if (!\is_string($parentId) || $parentId === '') {
            throw RoutingException::missingRequestParameter('parentId');
        }

        if (!\is_string($name) || trim($name) === '') {
            throw RoutingException::missingRequestParameter('name');
        }

        $id = $this->navigationBuilder->addItem(
            $context,
            $parentId,
            trim($name),
            (string) ($payload['type'] ?? 'page')
        );

        return new JsonResponse(['id' => $id]);
    }
}
