<?php declare(strict_types=1);

namespace Anivele\DemoData\Controller;

use Anivele\DemoData\Service\DemoDataException;
use Anivele\DemoData\Service\DemoDataImporter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API cho nút "Nhập dữ liệu demo" trong Administration.
 *
 * Administration gọi `import` nhiều lần, mỗi lần một đợt nhỏ, cho tới khi `done = true`.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class DemoDataController
{
    public function __construct(private readonly DemoDataImporter $importer)
    {
    }

    #[Route(
        path: '/api/_action/anivele-demo-data/status',
        name: 'api.action.anivele_demo_data.status',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['product:read']],
        methods: ['GET']
    )]
    public function status(Context $context): JsonResponse
    {
        return new JsonResponse($this->importer->getStatus($context));
    }

    #[Route(
        path: '/api/_action/anivele-demo-data/import',
        name: 'api.action.anivele_demo_data.import',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['product:create']],
        methods: ['POST']
    )]
    public function import(Request $request, Context $context): JsonResponse
    {
        // Body rỗng cũng hợp lệ: coi như bắt đầu từ đầu
        $payload = $request->getContent() === '' ? [] : $request->toArray();
        $offset = (int) ($payload['offset'] ?? 0);

        try {
            return new JsonResponse($this->importer->import($context, $offset));
        } catch (DemoDataException $e) {
            // Môi trường chưa sẵn sàng — lỗi của cấu hình, không phải của server
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
