<?php declare(strict_types=1);

namespace Acme\Brand\Validation;

use Acme\Brand\Core\Content\Brand\BrandDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Mã brand là tiền tố SKU (`NKE-1001-01`), nên phải đúng 3 ký tự in hoa A–Z hoặc 0–9.
 *
 * Kiểm ở tầng ghi DAL để áp dụng cho mọi đường ghi: Administration, Admin API,
 * Sync API, Import/Export.
 *
 * @internal
 */
class BrandCodeValidator implements EventSubscriberInterface
{
    public const CODE_PATTERN = '/^[A-Z0-9]{3}$/';

    public const ERROR_CODE = 'ACME_BRAND__INVALID_CODE';

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommandsForEntity(BrandDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (!\array_key_exists('code', $payload) || $payload['code'] === null) {
                continue;
            }

            $code = $payload['code'];
            if (\is_string($code) && preg_match(self::CODE_PATTERN, $code) === 1) {
                continue;
            }

            $violations = new ConstraintViolationList();
            $violations->add(new ConstraintViolation(
                'Brand code must be exactly 3 uppercase letters or digits, e.g. "NKE".',
                null,
                [],
                null,
                '/code',
                $code,
                null,
                self::ERROR_CODE,
            ));

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }
}
