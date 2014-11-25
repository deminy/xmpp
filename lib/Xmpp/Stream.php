<?php

namespace Xmpp;

use Psr\Log\LoggerInterface;
use Xmpp\Exception\StreamException;

/**
 * The Stream class wraps up the stream functions, so you can pass around the stream as an object and perform operations
 * on it.
 */
class Stream
{
    /**
     * @var resource
     */
    protected $conn;

    /**
     * @var bool
     */
    protected $connected = false;

    /**
     * @var int
     */
    protected $errno;

    /**
     * @var string
     */
    protected $errstr;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Creates an instance of the Stream class
     *
     * @param string $remoteSocket The remote socket to connect to.
     * @param int $timeout Give up trying to connect after these number of seconds.
     * @param int $flags Connection flags.
     * @param resource $context Context of the stream.
     * @param LoggerInterface $logger
     * @throws StreamException
     */
    public function __construct(
        $remoteSocket,
        $timeout = null,
        $flags = null,
        $context = null,
        LoggerInterface $logger = null
    ) {
        $this->logger = $logger;

        // stream_socket_client needs to be called in the correct way based on what we have been passed.
        if (is_null($timeout) && is_null($flags) && is_null($context)) {
            $this->conn = stream_socket_client($remoteSocket, $this->errno, $this->errstr);
        } else if (is_null($flags) && is_null($context)) {
            $this->conn = stream_socket_client($remoteSocket, $this->errno, $this->errstr, $timeout);
        } else if (is_null($context)) {
            $this->conn = stream_socket_client(
                $remoteSocket,
                $this->errno,
                $this->errstr,
                $timeout,
                $flags
            );
        } else {
            $this->conn = stream_socket_client(
                $remoteSocket,
                $this->errno,
                $this->errstr,
                $timeout,
                $flags,
                $context
            );
        }

        /**
         * If the connection comes back as false, it could not be established. Note that a connection may appear to be
         * successful at this stage and yet be invalid. e.g. UDP connections are "connectionless" and not actually made
         * until they are required.
         */
        if (false === $this->conn) {
            throw new StreamException($this->errstr, $this->errno);
        }

        // Set the time out of the stream.
        stream_set_timeout($this->conn, 1); //TODO: update required?

        $this->connected = true;
    }

    /**
     * Class destructor
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Closes the connection to whatever this class is connected to.
     *
     * @return void
     */
    public function disconnect()
    {
        if (!is_null($this->conn) && (false !== $this->conn)) {
            fclose($this->conn);

            $this->conn      = null;
            $this->connected = false;
        }
    }

    /**
     * Returns whether or not this object is currently connected to anything.
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->connected;
    }

    /**
     * Attempts to read some data from the stream.
     *
     * @param int $length The amount of data to be returned
     * @return string
     */
    public function read($length)
    {
        return fread($this->conn, $length);
    }

    /**
     * Waits for some data to be sent or received on the stream
     *
     * @return int|boolean
     */
    public function select()
    {
        $read = array($this->conn);
        $write = $except = array();

        return stream_select($read, $write, $except, 0, 200000);
    }

    /**
     * Will sent the message passed in down the stream.
     *
     * @param string $message Content to be sent down the stream.
     * @return int The number of bytes sent.
     */
    public function send($message)
    {
        // Perhaps need to check the stream is still open here?
        $this->logger->debug('Sent: ' . $message);

        return fwrite($this->conn, $message);
    }

    /**
     * Turns blocking on or off on the stream.
     *
     * @param boolean $enable Set what to do with blocking, turn it on or off.
     * @return boolean
     */
    public function setBlocking($enable)
    {
        return stream_set_blocking($this->conn, ($enable ? 1 : 0));
    }

    /**
     * Toggle whether or not TLS is use on the connection.
     *
     * @param boolean $enable Whether or not to turn on TLS.
     * @return mixed
     */
    public function setTLS($enable)
    {
        return stream_socket_enable_crypto($this->conn, (boolean) $enable, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    }
}
