<?php declare(strict_types=1);

namespace Acme\Payment\Tests\Unit\Service;

use Acme\Payment\Service\BankDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BankDirectory::class)]
class BankDirectoryTest extends TestCase
{
    public function testMatchesBankOptionsOfPluginConfig(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/../../../src/Resources/config/config.xml');
        static::assertNotFalse($xml);

        $options = [];
        foreach ($xml->xpath('//input-field[name="bankBin"]/options/option') ?: [] as $option) {
            $options[(string) $option->id] = (string) $option->name[0];
        }

        static::assertSame($options, BankDirectory::NAMES, 'Thêm ngân hàng vào config.xml thì thêm cả vào BankDirectory');
    }

    public function testUnknownBinFallsBackToBin(): void
    {
        static::assertSame('970999', BankDirectory::name('970999'));
    }
}
