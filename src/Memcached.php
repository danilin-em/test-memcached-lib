<?php

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
    const DEFAULT_TTL = 3600;

    /**
     * @var ClientInterface
     */
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }
    public function __destruct()
    {
        $this->client->close();
    }

    /**
     * @param string $key
     * @throws ClientConnectionException
     * @throws ClientWriteException
     * @throws InvalidArgumentException
     * @throws ResponseClientErrorException
     * @throws ResponseErrorException
     * @throws ResponseServerErrorException
     */
    public function get($key)
    {
        self::validateKey($key);
        $line = $this->command(sprintf('get %s', $key));
        $header = $line->current();
        if ($header === null) {
            return null;
        }
        if (!is_string($header)) {
            throw new ResponseErrorException('Cannot read header');
        }
        if (strpos($header, 'VALUE ') === 0) {
            list(, , , $bytes) = explode(' ', trim($header));
            if (!is_numeric($bytes)) {
                throw new ResponseErrorException('Cannot parse header');
            }
            $data = null;
            if ($bytes) {
                $data = $this->client->read((int)$bytes)->current();
            }
            $line->next();
            $end = $line->current();
            if ($end !== null) {
                throw new ResponseErrorException('Cannot reach the end of the data');
            }
            return $data ?: null;
        }
        return null;
    }

    /**
     * @param string $key
     * @param string $data
     * @param int|null $ttl
     * @throws ClientConnectionException
     * @throws ClientWriteException
     * @throws InvalidArgumentException
     * @throws MemcachedException
     * @throws ResponseClientErrorException
     * @throws ResponseErrorException
     * @throws ResponseServerErrorException
     * @retrun void
     */
    public function set($key, $data, $ttl = null)
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
     * @param string $key
     * @throws ClientConnectionException
     * @throws ClientWriteException
     * @throws InvalidArgumentException
     * @retrun void
     */
    public function delete($key)
    {
        self::validateKey($key);
        $this->command(sprintf('delete %s noreply', $key), true)->current();
    }

    /**
     * @param string $key
     * @throws InvalidArgumentException
     * @retrun void
     */
    private static function validateKey($key)
    {
        if (mb_strlen($key) > 250) {
            throw new InvalidArgumentException('Key is too long');
        }
        if (preg_match('/[\x00-\x1F\x7F\s]/', $key)) {
            throw new InvalidArgumentException('Key contains invalid characters');
        }
    }

    /**
     * @param string $command
     * @param bool $noreply
     * @return Generator
     * @throws ClientConnectionException
     * @throws ClientWriteException
     * @throws ResponseClientErrorException
     * @throws ResponseErrorException
     * @throws ResponseServerErrorException
     */
    private function command($command, $noreply = false)
    {
        $this->client->connect();

        $writtenBytes = $this->client->write("$command\r\n");

        if ($noreply) {
            yield $writtenBytes;
            return;
        }

        $tries = 10;
        foreach ($this->client->read() as $line) {
            if ($tries === 0) {
                throw new ResponseErrorException('Too many empty lines');
            }
            if ($line === false) {
                throw new ResponseErrorException('Failed to read data');
            }
            $line = trim($line);
            if (!$line) {
                $tries--;
                continue;
            }
            if ($line === 'END') {
                yield null;
                return;
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
