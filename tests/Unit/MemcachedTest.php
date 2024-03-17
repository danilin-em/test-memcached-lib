<?php

declare(strict_types=1);

namespace Memcached\Tests\Unit;

use PHPUnit\Framework\TestCase;

use Memcached\Memcached;

class MemcachedTest extends TestCase
{
    private Memcached $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Memcached();
    }
    protected function tearDown(): void
    {
        unset($this->client);
        parent::tearDown();
    }

    public function testGetReturnsNullWhenKeyDoesNotExist(): void
    {
        $this->assertNull($this->client->get('nonexistent_key'));
    }

    public function testGetReturnsValueWhenKeyExists(): void
    {
        $this->client->set('key', 'value');
        $this->assertEquals('value', $this->client->get('key'));
    }

    public function testSetStoresValue(): void
    {
        $this->client->set('key', 'value');
        $this->assertEquals('value', $this->client->get('key'));
        $this->client->set('key', 'value2');
        $this->assertEquals('value2', $this->client->get('key'));
    }

    public function testDeleteRemovesKey(): void
    {
        $this->client->set('key', 'value');
        $this->assertEquals('value', $this->client->get('key'));
        $this->client->delete('key');
        $this->assertNull($this->client->get('key'));
    }
}