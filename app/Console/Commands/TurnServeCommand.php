<?php

namespace App\Console\Commands;

use App\Turn\TurnServer;
use Illuminate\Console\Command;
use RuntimeException;

class TurnServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Note: --verbose is reserved by Symfony Console — use --log-packets instead.
     *
     * @var string
     */
    protected $signature = 'turn:serve
                            {--host= : Bind address (default from config/turn.php)}
                            {--port= : UDP port (default from config/turn.php)}
                            {--log-packets : Log every packet received/sent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'POC: Run a minimal TURN/STUN server for WebRTC relay (PIO-111)';

    /**
     * Execute the console command.
     *
     * Returns int (not void) so the process supervisor can interpret the exit code.
     */
    public function handle(): int
    {
        $host = $this->option('host') ?? config('turn.host');
        $port = (int) ($this->option('port') ?? config('turn.port'));

        $server = new TurnServer(
            host: $host,
            port: $port,
            realm: config('turn.realm'),
            username: config('turn.username'),
            password: config('turn.password'),
            allocationTtl: config('turn.allocation_ttl'),
            relayMinPort: config('turn.relay_min_port'),
            relayMaxPort: config('turn.relay_max_port'),
            logPackets: (bool) $this->option('log-packets'),
        );

        pcntl_signal(SIGTERM, fn () => $server->stop());
        pcntl_signal(SIGINT, fn () => $server->stop());

        try {
            $server->start();
        } catch (RuntimeException $e) {
            $this->error("POC FAILED: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $this->info("TURN server listening on {$host}:{$port} (UDP)");
        $this->info("Relay address: {$server->getPublicIp()}:{$port}");
        $this->line('Press Ctrl+C to stop.');

        $server->run();

        $this->info('TURN server stopped.');
        $this->printChecklist();

        return Command::SUCCESS;
    }

    private function printChecklist(): void
    {
        $this->line('');
        $this->line('--- POC Checklist (fill in .claude.d/PIO-111.findings.md) ---');
        $this->line('[ ] Laravel Cloud accepted the command as a background process');
        $this->line('[ ] UDP 3478 reachable externally (tested with: ___)');
        $this->line('[ ] Two WebRTC peers exchanged media via relay');
        $this->line('[ ] Stable for 10-minute call (peak memory: ___ MB)');
    }
}
