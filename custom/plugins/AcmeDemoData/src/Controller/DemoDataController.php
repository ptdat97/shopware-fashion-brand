<?php declare(strict_types=1);

namespace Acme\DemoData\Controller;

use Acme\DemoData\Service\DemoDataImporter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API cho nút "Nhập dữ liệu demo" trong Administration.
 *
 * Administration gọi `import` nhiều lần, mỗi lần một đợt nhỏ, cho tới khi `done = true`.
 * Môi trường chưa sẵn sàng → `DemoDataException` (400, mã `ACME_DEMO_DATA__*`) do core
 * chuyển thành JSON lỗi chuẩn.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class DemoDataController
{
    public function __construct(private readonly DemoDataImporter $importer)
    {
    }

    #[Route(
        path: '/api/_action/acme-demo-data/status',
        name: 'api.action.acme_demo_data.status',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['product:read']],
        methods: ['GET']
    )]
    public function status(Context $context): JsonResponse
    {
        return new JsonResponse($this->importer->getStatus($context));
    }

    #[Route(
        path: '/api/_action/acme-demo-data/import',
        name: 'api.action.acme_demo_data.import',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['product:create']],
        methods: ['POST']
    )]
    public function import(Request $request, Context $context): JsonResponse
    {
        // Body rỗng cũng hợp lệ: coi như bắt đầu từ đầu
        $payload = $request->getContent() === '' ? [] : $request->toArray();
        $offset = (int) ($payload['offset'] ?? 0);

        return new JsonResponse($this->importer->import($context, $offset));
    }
}
