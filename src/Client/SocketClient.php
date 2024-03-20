<?php

declare(strict_types=1);

namespace Memcached\Client;

use Generator;
use Memcached\ClientInterface;
use Memcached\Exception\ClientConnectionException;
use Memcached\Exception\ClientWriteException;

final class SocketClient implements ClientInterface
{
    /**
     * @var resource/null
     */
    private $socket = null;
    private string $address;
    private int $timeout;

    public function __construct(string $address, int $timeout = 30)
    {
        $this->address = $address;
        $this->timeout = $timeout;
    }

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        if (!is_resource($this->socket)) {
            $context = stream_context_create();
            $socket = @stream_socket_client(
                $this->address,
                $errorCode,
                $errorMessage,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
            if ($socket === false) {
                throw new ClientConnectionException(sprintf('Cannot establish connection: [%d] %s', $errorCode, $errorMessage));
            }
            $this->socket = $socket;
        }
    }

    /**
     * @inheritDoc
     */
    public function write(string $data): int
    {
        $bytes = fwrite($this->socket, $data);
        if ($bytes === false) {
            throw new ClientWriteException('Cannot write data.');
        }
        return $bytes;
    }

    public function read(int $length = null): Generator
    {
        if ($length !== null) {
            return yield fread($this->socket, $length);
        }
        while (!feof($this->socket)) {
            yield fgets($this->socket);
        }
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }
}