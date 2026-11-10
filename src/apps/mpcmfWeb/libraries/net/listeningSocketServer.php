<?php

namespace mpcmf\apps\mpcmfWeb\libraries\net;

use Evenement\EventEmitter;
use mpcmf\apps\mpcmfWeb\libraries\net\exception\netException;
use React\EventLoop\LoopInterface;
use React\Socket\Connection;
use React\Socket\ServerInterface;

/**
 * react/socket 0.7 compatible server over a listening socket resource
 *
 * The socket may be created by this process ({@see listen()}) or inherited from the parent process:
 * the web server master opens the sockets before forking, so a child can be replaced while
 * new connections wait in the kernel queue of the shared socket.
 *
 * Unlike React\Socket\Server it gives access to the resource and can accept the already queued
 * connections before it stops listening ({@see acceptPending()}).
 */
class listeningSocketServer
    extends EventEmitter
    implements ServerInterface
{
    const DEFAULT_BACKLOG = 511;

    /** @var resource|null */
    private $socket;

    /** @var LoopInterface */
    private $loop;

    private $listening = false;

    /**
     * Open a listening TCP socket (not attached to any loop)
     *
     * @param string $host IP address, IPv6 with or without brackets
     * @param int    $port
     * @param int    $backlog
     *
     * @return resource
     *
     * @throws netException
     */
    public static function listen($host, $port, $backlog = self::DEFAULT_BACKLOG)
    {
        $host = trim($host, '[]');
        if (strpos($host, ':') !== false) {
            $host = "[{$host}]";
        }
        $context = stream_context_create(['socket' => ['backlog' => (int)$backlog]]);
        $socket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($socket === false) {
            throw new netException("Failed to listen on \"{$host}:{$port}\": {$errstr}", (int)$errno);
        }
        stream_set_blocking($socket, false);

        return $socket;
    }

    /**
     * @param resource      $socket listening socket
     * @param LoopInterface $loop
     * @param bool          $listen start accepting right away
     */
    public function __construct($socket, LoopInterface $loop, $listen = true)
    {
        if (!is_resource($socket)) {
            throw new \InvalidArgumentException('Listening socket resource expected');
        }
        $this->socket = $socket;
        $this->loop = $loop;
        stream_set_blocking($this->socket, false);
        if ($listen) {
            $this->resume();
        }
    }

    /**
     * @return resource|null
     */
    public function getSocket()
    {
        return $this->socket;
    }

    public function getAddress()
    {
        if (!is_resource($this->socket)) {
            return null;
        }
        $address = stream_socket_get_name($this->socket, false);
        // IPv6 address without brackets, same as React\Socket\Server
        $pos = strrpos($address, ':');
        if ($pos !== false && strpos($address, ':') < $pos && substr($address, 0, 1) !== '[') {
            $address = '[' . substr($address, 0, $pos) . ']:' . substr($address, $pos + 1);
        }

        return $address;
    }

    public function pause()
    {
        if (!$this->listening) {
            return;
        }
        $this->loop->removeReadStream($this->socket);
        $this->listening = false;
    }

    public function resume()
    {
        if ($this->listening || !is_resource($this->socket)) {
            return;
        }
        $this->loop->addReadStream($this->socket, function () {
            $this->acceptOne();
        });
        $this->listening = true;
    }

    /**
     * Accept the connections already waiting in the kernel queue
     *
     * Called before close() on shutdown: queued connections would get RST otherwise.
     *
     * @param int $limit
     *
     * @return int number of accepted connections
     */
    public function acceptPending($limit = 10000)
    {
        $accepted = 0;
        while ($accepted < $limit && is_resource($this->socket) && $this->acceptOne()) {
            $accepted++;
        }

        return $accepted;
    }

    /**
     * Stop listening and close the socket in this process
     *
     * An inherited socket stays open in the processes that still hold it.
     */
    public function close()
    {
        $this->pause();
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
        $this->removeAllListeners();
    }

    /**
     * @return bool
     */
    private function acceptOne()
    {
        // another process sharing the socket may have taken the connection already: EAGAIN is not an error
        $client = @stream_socket_accept($this->socket, 0);
        if ($client === false) {
            return false;
        }
        $this->emit('connection', [new Connection($client, $this->loop)]);

        return true;
    }
}
