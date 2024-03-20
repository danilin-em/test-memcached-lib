<?php

namespace Memcached;

use Generator;
use Memcached\Exception\ClientConnectionException;
use Memcached\Exception\ClientWriteException;

interface ClientInterface
{
    /**
     * @throws ClientConnectionException
     */
    public function connect(): void;

    /**
     * @throws ClientWriteException
     */
    public function write(string $data): int;
    public function read(int $length = null):  Generator;
    public function close(): void;
}