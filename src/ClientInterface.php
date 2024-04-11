<?php

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
     * @param string $data
     * @retrun int
     */
    public function write($data);
    /**
     * @param int|null $length
     * @return Generator
     */
    public function read($length = null);
    /**
     * @return void
     */
    public function close();
}
