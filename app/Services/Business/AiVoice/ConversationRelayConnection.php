<?php

namespace App\Services\Business\AiVoice;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Message;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;

/**
 * One Twilio ConversationRelay WebSocket connection (MASTER-ONLY, Business Suite Prompt 17), over any readable/writable stream so it is tested with
 * stream_socket_pair() and no real network. It does three things: authenticate the upgrade request, speak RFC 6455, and pass the JSON messages to an
 * AgentConversation (built lazily when Twilio's `setup` message arrives, because only then do we know which session this is).
 *
 * Authentication: Twilio signs the WebSocket URL with the account auth token (X-Twilio-Signature, HMAC-SHA1, base64). The upgrade is refused unless that
 * matches, so nobody else can open a conversation and run up a workspace's funded block.
 */
class ConversationRelayConnection
{
    private const MAX_REQUEST_BYTES = 16384;

    private const MAX_MESSAGE_BYTES = 1048576;

    /** @var resource */
    private $stream;

    private string $inbox = '';

    private bool $upgraded = false;

    private bool $open = true;

    private ?MessageBuffer $buffer = null;

    private ?AgentConversation $conversation = null;

    private \Closure $factory;

    private ?float $deadline = null;

    private bool $timeUpSent = false;

    private float $openedAt;

    /**
     * @param  resource  $stream
     * @param  callable(array<string,mixed>): ?array{conversation: AgentConversation, deadline_seconds: int}  $factory  builds the conversation from the setup message, null to refuse it
     * @param  \Closure(): void|null  $onClose  called once when the connection ends, however it ends
     */
    public function __construct($stream, callable $factory, private string $authToken, private ?\Closure $onClose = null)
    {
        $this->factory = \Closure::fromCallable($factory);
        $this->stream = $stream;
        stream_set_blocking($this->stream, false);
        $this->openedAt = microtime(true);
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    /** One step: read what has arrived (waiting up to $timeout seconds), act on it, and check the clocks. */
    public function pump(float $timeout = 1.0): void
    {
        if (! $this->open) {
            return;
        }

        $read = [$this->stream];
        $write = $except = null;
        $sec = (int) floor($timeout);
        $ready = @stream_select($read, $write, $except, $sec, (int) (($timeout - $sec) * 1_000_000));
        if ($ready) {
            $data = @fread($this->stream, 65536);
            if ($data === '' || $data === false) {
                if (feof($this->stream)) {
                    $this->close();

                    return;
                }
            } else {
                $this->onBytes($data);
            }
        }

        $this->checkClocks();
    }

    private function onBytes(string $data): void
    {
        if ($this->upgraded) {
            $this->buffer?->onData($data);

            return;
        }

        $this->inbox .= $data;
        if (strlen($this->inbox) > self::MAX_REQUEST_BYTES) {
            $this->refuse(413);

            return;
        }
        if (($end = strpos($this->inbox, "\r\n\r\n")) === false) {
            return;
        }

        $head = substr($this->inbox, 0, $end + 4);
        $rest = substr($this->inbox, $end + 4);
        $this->inbox = '';

        try {
            $request = Message::parseRequest($head);
        } catch (\Throwable) {
            $this->refuse(400);

            return;
        }

        if (! $this->signatureOk($request)) {
            $this->refuse(403);

            return;
        }

        $response = (new ServerNegotiator(new RequestVerifier, new HttpFactory))->handshake($request);
        $this->write(Message::toString($response));
        if ($response->getStatusCode() !== 101) {
            $this->close();

            return;
        }

        $this->upgraded = true;
        $this->buffer = new MessageBuffer(
            new CloseFrameChecker,
            maxMessagePayloadSize: self::MAX_MESSAGE_BYTES,
            onMessage: fn (MessageInterface $m) => $this->onMessage($m->getPayload()),
            onControl: fn (FrameInterface $f) => $this->onControl($f),
            sender: fn (string $bytes) => $this->write($bytes),
        );
        if ($rest !== '') {
            $this->buffer->onData($rest);
        }
    }

    /** @param \Psr\Http\Message\RequestInterface $request */
    private function signatureOk($request): bool
    {
        $given = $request->getHeaderLine('X-Twilio-Signature');
        if ($given === '' || $this->authToken === '') {
            return false;
        }
        $host = $request->getHeaderLine('Host');
        $path = $request->getUri()->getPath().($request->getUri()->getQuery() !== '' ? '?'.$request->getUri()->getQuery() : '');

        // Twilio computes it over the URL we gave it. Accept the https and wss spellings of that same URL.
        foreach (['https', 'wss'] as $scheme) {
            if (hash_equals(base64_encode(hash_hmac('sha1', $scheme.'://'.$host.$path, $this->authToken, true)), $given)) {
                return true;
            }
        }

        return false;
    }

    private function onMessage(string $payload): void
    {
        $message = json_decode($payload, true);
        if (! is_array($message)) {
            return;
        }

        if ($this->conversation === null) {
            if (($message['type'] ?? '') !== 'setup') {
                return; // nothing is accepted before setup
            }
            $made = ($this->factory)($message);
            if ($made === null) {
                $this->send(['type' => 'end']);
                $this->close();

                return;
            }
            $this->conversation = $made['conversation'];
            $this->deadline = microtime(true) + max(5, $made['deadline_seconds']);
        }

        foreach ($this->conversation->handle($message) as $out) {
            $this->send($out);
        }
        // Once we have sent "end", Twilio closes the socket and runs the action URL; the clocks below are the safety net if it does not.
    }

    private function checkClocks(): void
    {
        if (! $this->open) {
            return;
        }
        if (! $this->upgraded && microtime(true) - $this->openedAt > 10) {
            $this->refuse(408); // a connection that never finishes its handshake

            return;
        }
        if ($this->conversation && $this->deadline !== null && ! $this->timeUpSent && microtime(true) >= $this->deadline) {
            $this->timeUpSent = true;
            foreach ($this->conversation->timeUp() as $out) {
                $this->send($out);
            }
        }
        // If Twilio has not closed 15 seconds after we asked it to end, drop the connection ourselves.
        if ($this->timeUpSent && $this->deadline !== null && microtime(true) > $this->deadline + 15) {
            $this->close();
        }
    }

    private function onControl(FrameInterface $frame): void
    {
        match ($frame->getOpcode()) {
            Frame::OP_PING => $this->write((new Frame($frame->getPayload(), true, Frame::OP_PONG))->getContents()),
            Frame::OP_CLOSE => $this->close(),
            default => null,
        };
    }

    /** @param array<string,mixed> $message */
    private function send(array $message): void
    {
        $this->write((new Frame(json_encode($message, JSON_UNESCAPED_UNICODE), true, Frame::OP_TEXT))->getContents());
    }

    private function write(string $bytes): void
    {
        if (! $this->open) {
            return;
        }
        $total = strlen($bytes);
        $sent = 0;
        $tries = 0;
        while ($sent < $total && $tries < 200) {
            $n = @fwrite($this->stream, substr($bytes, $sent));
            if ($n === false) {
                $this->close();

                return;
            }
            if ($n === 0) {
                $tries++;
                usleep(5000);

                continue;
            }
            $sent += $n;
        }
    }

    private function refuse(int $status): void
    {
        $this->write('HTTP/1.1 '.$status." Refused\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");
        $this->close();
    }

    public function close(): void
    {
        if (! $this->open) {
            return;
        }
        $this->open = false;
        @fclose($this->stream);
        if ($this->onClose) {
            ($this->onClose)();
        }
    }
}
