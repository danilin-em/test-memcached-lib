<?php

declare(strict_types=1);

namespace Memcached;

use Generator;
use Memcached\Exception\ClientConnectionException;
use Memcached\Exception\ClientWriteException;

interface ClientInterface
{
    /**
     * @throws ClientConnectionException
     * @return void
     */
    public function connect();

    /**
     * @throws ClientWriteException
     */
    public function write(string $data): int;
    public function read(int $length = null): Generator;
    /**
     * @return void
     */
    public function close();
}
