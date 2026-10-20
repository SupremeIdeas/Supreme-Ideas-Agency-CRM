<?php

namespace App\Console\Commands;

use App\Services\Business\AiVoice\ConversationRelayServer;
use App\Support\Business\BusinessSettings;
use Illuminate\Console\Command;

/**
 * Runs the AI voice agent's WebSocket server (MASTER-ONLY, Business Suite Prompt 17). A long-lived process: run it under systemd or supervisor on a VPS, behind a
 * TLS proxy that exposes it as wss://. See docs/business-suite/README.md for a unit file. Not available on shared cPanel hosting.
 */
class BusinessAiVoiceServer extends Command
{
    protected $signature = 'business:ai-voice-server {--host=127.0.0.1 : Interface to listen on (keep it private; the TLS proxy faces the internet)} {--port=8089}';

    protected $description = 'Run the AI voice agent WebSocket server for Twilio ConversationRelay';

    public function handle(ConversationRelayServer $server): int
    {
        if (! function_exists('pcntl_fork')) {
            $this->error('The pcntl extension is required (Linux, CLI).');

            return self::FAILURE;
        }
        $token = (string) config('services.twilio.auth_token');
        if ($token === '') {
            $this->error('Twilio is not configured: the auth token is used to verify every connection.');

            return self::FAILURE;
        }

        $server->run((string) $this->option('host'), (int) $this->option('port'), max(1, BusinessSettings::aiInt('max_concurrent')), $token, fn (string $m) => $this->info($m));

        return self::SUCCESS;
    }
}
