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

    /**
     * @var Memcached
     */
    private $memcached;

    protected function setUp()
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->memcached = new Memcached($this->client);
    }
    private static function makeGeneratorCallback(string ...$lines): callable
    {
        // @phpstan-ignore-next-line
        return static function () use ($lines) {
            return (static function (string ...$lines): Generator {
                foreach ($lines as $line) {
                    yield $line;
                }
            })(...$lines);
        };
    }

    public function testGetReturnsNullWhenKeyDoesNotExist()
    {
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "END\r\n"
        ));

        $this->assertNull($this->memcached->get('nonexistent_key'));
    }

    public function testGetReturnsValueWhenKeyExists()
    {
        $this->client->method('read')->willReturnOnConsecutiveCalls(
            (static function (): Generator {
                yield "VALUE key 0 5\r\n";
            })(),
            (static function (): Generator {
                yield "value";
                yield "END\r\n";
            })()
        );

        $this->assertEquals('value', $this->memcached->get('key'));
    }

    public function testGetReturnsNullWhenDataBytesEmpty()
    {
        $this->client->method('read')->willReturnOnConsecutiveCalls(
            (static function (): Generator {
                yield "VALUE key 0 0\r\n";
            })(),
            (static function (): Generator {
                yield "END\r\n";
            })()
        );

        $this->assertNull($this->memcached->get('key'));
    }

    public function testGetReturnsNullWhenHeaderIsInvalid()
    {
        $this->client->method('read')->willReturnOnConsecutiveCalls(
            (static function (): Generator {
                yield "invalid header\r\n";
            })(),
            (static function (): Generator {
                yield "END\r\n";
            })()
        );

        $this->assertNull($this->memcached->get('key'));
    }

    public function testSetStoresData()
    {
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "STORED\r\n"
        ));

        $this->memcached->set('key', 'value');
        $this->assertTrue(true);
    }

    public function testSetThrowsExceptionWhenDataIsNotStored()
    {
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "NOT_STORED\r\n"
        ));

        $this->expectException(MemcachedException::class);
        $this->memcached->set('key', 'value');
    }

    public function testDeleteRemovesKey()
    {
        $this->memcached->delete('key');
        $this->assertTrue(true);
    }

    public function testValidateKeyThrowsExceptionWhenKeyIsTooLong()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->memcached->get(str_repeat('a', 251));
    }

    public function testValidateKeyThrowsExceptionWhenKeyContainsInvalidCharacters()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->memcached->get("key\n");
    }

    public function testResponseClientErrorException()
    {
        $this->expectException(ResponseClientErrorException::class);
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "CLIENT_ERROR test\r\n"
        ));

        $this->memcached->set('key', 'value');
    }

    public function testResponseServerErrorException()
    {
        $this->expectException(ResponseServerErrorException::class);
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "SERVER_ERROR test\r\n"
        ));

        $this->memcached->set('key', 'value');
    }

    public function testResponseErrorException()
    {
        $this->expectException(ResponseErrorException::class);
        $this->client->method('read')->willReturnCallback(self::makeGeneratorCallback(
            "ERROR\r\n"
        ));

        $this->memcached->set('key', 'value');
    }

    public function testDestructorClosesConnection()
    {
        $this->client->expects(self::once())->method('close');
        unset($this->memcached);
    }
}
