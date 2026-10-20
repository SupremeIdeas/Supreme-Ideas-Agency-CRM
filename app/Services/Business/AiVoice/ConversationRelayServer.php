<?php

namespace App\Services\Business\AiVoice;

use App\Models\AiVoiceAgentSession;
use Illuminate\Support\Facades\DB;

/**
 * The listening side of the AI voice agent (MASTER-ONLY, Business Suite Prompt 17): a small pre-fork WebSocket server. One short-lived child process per call, so
 * a slow model answer on one call can never stall another, and a crash ends one call, not all of them. The parent only accepts and reaps. Needs the pcntl
 * extension and a process that stays alive (a VPS under systemd or supervisor; a shared cPanel host cannot run it). Put it behind a TLS proxy: Twilio connects to wss://.
 */
class ConversationRelayServer
{
    private bool $stop = false;

    /** @var array<int,true> */
    private array $children = [];

    public function __construct(private ConversationFactory $factory, private AgentSessionFinalizer $finalizer)
    {
    }

    public function run(string $host, int $port, int $maxConcurrent, string $authToken, ?callable $log = null): void
    {
        $log ??= static fn (string $m) => null;
        $server = @stream_socket_server('tcp://'.$host.':'.$port, $errno, $error);
        if (! $server) {
            throw new \RuntimeException("Cannot listen on {$host}:{$port}: {$error}");
        }

        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT] as $sig) {
            pcntl_signal($sig, function () { $this->stop = true; });
        }
        $log("AI voice agent listening on {$host}:{$port}");

        $beat = 0;
        while (! $this->stop) {
            if (time() - $beat >= 20) {   // the admin Setup and health screen reads this to tell whether the agent process is alive
                $beat = time();
                try {
                    \Illuminate\Support\Facades\Cache::put('business.ai_voice.alive', ['at' => $beat, 'host' => $host, 'port' => $port, 'calls' => count($this->children)], 90);
                } catch (\Throwable) {
                    // a cache hiccup must never take the server down
                }
            }
            $this->reap();
            $read = [$server];
            $write = $except = null;
            if (! @stream_select($read, $write, $except, 1)) {
                continue;
            }
            $conn = @stream_socket_accept($server, 0);
            if (! $conn) {
                continue;
            }
            if (count($this->children) >= $maxConcurrent) {
                @fwrite($conn, "HTTP/1.1 503 Busy\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");
                fclose($conn);

                continue;
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                fclose($conn);
            } elseif ($pid === 0) {
                fclose($server);
                $this->serveCall($conn, $authToken);
                exit(0);
            } else {
                fclose($conn);
                $this->children[$pid] = true;
            }
        }

        fclose($server);
        $log('Stopping: waiting for '.count($this->children).' call(s) to finish');
        while ($this->children) {
            $this->reap(true);
        }
    }

    /** Runs inside the child: one call from handshake to hangup. @param resource $conn */
    private function serveCall($conn, string $authToken): void
    {
        DB::purge();   // the parent's connection must not be shared with the child

        $sessionId = null;
        $factory = function (array $setup) use (&$sessionId) {
            $made = ($this->factory)($setup);
            $sessionId = $made ? (int) ($setup['customParameters']['session'] ?? 0) : null;

            return $made;
        };
        $onClose = function () use (&$sessionId) {
            // Bill and file the call even if Twilio never reaches the action URL (a dropped connection, a crash on their side).
            if ($sessionId && ($s = AiVoiceAgentSession::find($sessionId))) {
                $this->finalizer->finalize($s);
            }
        };

        $connection = new ConversationRelayConnection($conn, $factory, $authToken, $onClose);
        while ($connection->isOpen()) {
            $connection->pump(1.0);
        }
    }

    private function reap(bool $block = false): void
    {
        while (($pid = pcntl_waitpid(-1, $status, $block ? 0 : WNOHANG)) > 0) {
            unset($this->children[$pid]);
            $block = false;
        }
        if ($block) {
            usleep(100000);
        }
    }
}
