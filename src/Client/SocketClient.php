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
    /**
     * @var string
     */
    private $address;
    /**
     * @var int
     */
    private $timeout;

    public function __construct(string $address, int $timeout = 5)
    {
        $this->address = $address;
        $this->timeout = $timeout;
    }

    /**
     * @inheritDoc
     */
    public function connect()
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
                throw new ClientConnectionException(
                    sprintf('Cannot establish connection: [%d] %s', $errorCode, $errorMessage)
                );
            }
            $this->socket = $socket;
            stream_set_timeout($this->socket, $this->timeout);
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

    public function close()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }
}
