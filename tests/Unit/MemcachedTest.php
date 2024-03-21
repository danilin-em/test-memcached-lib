<?php

declare(strict_types=1);

namespace Memcached\Tests\Unit;

use Generator;

use Memcached\Exception\ResponseClientErrorException;
use Memcached\Exception\ResponseErrorException;
use Memcached\Exception\ResponseServerErrorException;
use PHPUnit\Framework\TestCase;

use Memcached\Memcached;
use Memcached\ClientInterface;
use Memcached\Exception\InvalidArgumentException;
use Memcached\Exception\MemcachedException;

class MemcachedTest extends TestCase
{
    /**
     * @var ClientInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;
    private Memcached $memcached;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->memcached = new Memcached($this->client);
    }
    private static function makeGeneratorCallback(string ...$lines): callable
    {
        // @phpstan-ignore-next-line
        return static fn() => (static function (string ...$lines): Generator {
            foreach ($lines as $line) {
                yield $line;
            }
        })(...$lines);
    }

    public function testGetReturnsNullWhenKeyDoesNotExist(): void
    {
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "END\r\n"
        ));

        $this->assertNull($this->memcached->get('nonexistent_key'));
    }

    public function testGetReturnsValueWhenKeyExists(): void
    {
        $this->client->method('read')->willReturnOnConsecutiveCalls(
            (static function (): Generator {
                yield "VALUE key 0 5\r\n";
            })(),
            (static function (): Generator {
                yield "value\r\n";
                yield "END\r\n";
            })(),
        );

        $this->assertEquals('value', $this->memcached->get('key'));
    }

    public function testGetReturnsNullWhenDataBytesEmpty(): void
    {
        $this->client->method('read')->willReturnOnConsecutiveCalls(
            (static function (): Generator {
                yield "VALUE key 0 0\r\n";
            })(),
            (static function (): Generator {
                yield "END\r\n";
            })(),
        );

        $this->assertNull($this->memcached->get('key'));
    }

    public function testGetReturnsNullWhenHeaderIsInvalid(): void
    {
        $this->client->method('read')->willReturnOnConsecutiveCalls(
            (static function (): Generator {
                yield "invalid header\r\n";
            })(),
            (static function (): Generator {
                yield "END\r\n";
            })(),
        );

        $this->assertNull($this->memcached->get('key'));
    }

    public function testSetStoresData(): void
    {
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "STORED\r\n",
        ));

        $this->memcached->set('key', 'value');
        $this->expectNotToPerformAssertions();
    }

    public function testSetThrowsExceptionWhenDataIsNotStored(): void
    {
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "NOT_STORED\r\n",
        ));

        $this->expectException(MemcachedException::class);
        $this->memcached->set('key', 'value');
    }

    public function testDeleteRemovesKey(): void
    {
        $this->memcached->delete('key');
        $this->expectNotToPerformAssertions();
    }

    public function testValidateKeyThrowsExceptionWhenKeyIsTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->memcached->get(str_repeat('a', 251));
    }

    public function testValidateKeyThrowsExceptionWhenKeyContainsInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->memcached->get("key\n");
    }

    public function testResponseClientErrorException(): void
    {
        $this->expectException(ResponseClientErrorException::class);
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "CLIENT_ERROR test\r\n",
        ));

        $this->memcached->set('key', 'value');
    }

    public function testResponseServerErrorException(): void
    {
        $this->expectException(ResponseServerErrorException::class);
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "SERVER_ERROR test\r\n",
        ));

        $this->memcached->set('key', 'value');
    }

    public function testResponseErrorException(): void
    {
        $this->expectException(ResponseErrorException::class);
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "ERROR\r\n",
        ));

        $this->memcached->set('key', 'value');
    }

    public function testDestructorClosesConnection(): void
    {
        $this->client->expects(self::once())->method('close');
        unset($this->memcached);
    }
}