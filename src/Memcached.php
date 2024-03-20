<?php

declare(strict_types=1);

namespace Memcached;

use Generator;

use Memcached\Exception\ClientWriteException;
use Memcached\Exception\MemcachedException;
use Memcached\Exception\ClientConnectionException;
use Memcached\Exception\ResponseClientErrorException;
use Memcached\Exception\InvalidArgumentException;
use Memcached\Exception\ResponseErrorException;
use Memcached\Exception\ResponseServerErrorException;

final class Memcached
{
    public const DEFAULT_TTL = 3600;
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }
    public function __destruct()
    {
        $this->client->close();
    }

    /**
     * @throws ClientConnectionException
     * @throws ClientWriteException
     * @throws InvalidArgumentException
     * @throws ResponseClientErrorException
     * @throws ResponseErrorException
     * @throws ResponseServerErrorException
     */
    public function get(string $key): ?string
    {
        self::validateKey($key);
        $header = $this->command(sprintf('get %s', $key))->current();
        if (!$header) {
            return null;
        }
        if (strpos($header, 'VALUE ') === 0) {
            [, , , $bytes] = explode(' ', trim($header));
            if (!$bytes) {
                return null;
            }
            $data = trim($this->client->read((int)$bytes)->current());
            return $data ?: null;
        }
        return null;
    }

    /**
     * @throws ClientConnectionException
     * @throws ClientWriteException
     * @throws InvalidArgumentException
     * @throws MemcachedException
     * @throws ResponseClientErrorException
     * @throws ResponseErrorException
     * @throws ResponseServerErrorException
     */
    public function set(string $key, string $data, int $ttl = null): void
    {
        self::validateKey($key);
        if ($ttl === null) {
            $ttl = self::DEFAULT_TTL;
        }
        $flags = 0;
        $bytes = mb_strlen($data);
        $line = $this->command(sprintf(
            "set %s %d %d %d\r\n%s",
            $key,
            $flags,
            $ttl,
            $bytes,
            $data
        ))->current();
        if ($line !== 'STORED') {
            throw new MemcachedException('Data is not Stored, reason: ' . ($line ?: 'line is empty'));
        }
    }

    /**
     * @throws ClientConnectionException
     * @throws ClientWriteException
     * @throws InvalidArgumentException
     */
    public function delete(string $key): void
    {
        self::validateKey($key);
        $this->command(sprintf('delete %s noreply', $key), true)->current();
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function validateKey(string $key): void
    {
        if (mb_strlen($key) > 250) {
            throw new InvalidArgumentException('Key is too long');
        }
        if (preg_match('/[\x00-\x1F\x7F\s]/', $key)) {
            throw new InvalidArgumentException('Key contains invalid characters');
        }
    }

    /**
     * @throws ClientConnectionException
     * @throws ClientWriteException
     * @throws ResponseClientErrorException
     * @throws ResponseErrorException
     * @throws ResponseServerErrorException
     */
    private function command(string $command, bool $noWait = false): Generator
    {
        $this->client->connect();

        $writtenBytes = $this->client->write("$command\r\n");

        if ($noWait) {
            return yield $writtenBytes;
        }

        foreach ($this->client->read() as $line) {
            $line = trim($line ?: '');
            if ($line === 'END') {
                return yield null;
            }
            if (strpos($line, 'CLIENT_ERROR ') === 0) {
                $line .= $this->client->read(1024)->current() ?: '';
                throw new ResponseClientErrorException('Client Error: ' . $line);
            }
            if (strpos($line, 'SERVER_ERROR ') === 0) {
                $line .= $this->client->read(1024)->current() ?: '';
                throw new ResponseServerErrorException('Server Error: ' . $line);
            }
            if ($line === 'ERROR') {
                throw new ResponseErrorException('Command failed');
            }
            yield $line;
        }
    }
}