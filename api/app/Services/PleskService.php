<?php

namespace App\Services;

use App\Exceptions\PleskException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PleskService
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * Create a Plesk subdomain. Returns the subdomain id and absolute doc root.
     *
     * @return array{id:int, doc_root:string}
     */
    public function createSubdomain(string $subdomain): array
    {
        $apex = $this->apex();
        $fqdn = $subdomain.'.'.$apex;
        $wwwRoot = '/'.$fqdn;

        $this->runPlesk([
            'bin', 'subdomain', '--create', $subdomain,
            '-domain', $apex,
            '-www-root', $wwwRoot,
        ]);

        $info = $this->findSubdomain($fqdn);

        return [
            'id' => $info['id'],
            'doc_root' => '/var/www/vhosts/'.$this->subscriptionDomain().$wwwRoot,
        ];
    }

    public function deleteSubdomain(string $subdomain): void
    {
        $apex = $this->apex();
        $this->runPlesk([
            'bin', 'subdomain', '--remove', $subdomain.'.'.$apex,
        ]);
    }

    /**
     * Best-effort Let's Encrypt issuance. Returns true on success, false on rate-limit / failure.
     */
    public function issueLetsEncrypt(string $subdomain): bool
    {
        $fqdn = $subdomain.'.'.$this->apex();
        $email = config('artifacthost.le_email');

        try {
            $this->runPlesk([
                'bin', 'extension', '--exec', 'letsencrypt', 'cli.php',
                '--domain', $fqdn,
                '--email', $email,
            ]);

            return true;
        } catch (PleskException $e) {
            Log::warning('artifact-host: LE issuance failed', [
                'fqdn' => $fqdn,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Tail Plesk-managed log files for a subdomain.
     *
     * @return array{access:string, error:string}
     */
    public function tailLogs(string $subdomain, int $lines = 100): array
    {
        $fqdn = $subdomain.'.'.$this->apex();
        $base = '/var/www/vhosts/system/'.$fqdn.'/logs';
        $lines = max(1, min($lines, 1000));

        return [
            'access' => $this->tail($base.'/access_log', $lines),
            'error' => $this->tail($base.'/error_log', $lines),
        ];
    }

    /** @return array{id:int, name:string} */
    private function findSubdomain(string $fqdn): array
    {
        $resp = $this->http->withHeaders([
            'X-API-Key' => config('artifacthost.plesk.api_key'),
            'Accept' => 'application/json',
        ])->withoutVerifying()
            ->get($this->pleskUrl('/api/v2/domains'), ['name' => $fqdn]);

        if (! $resp->successful()) {
            throw new PleskException('Plesk API list failed: HTTP '.$resp->status().' '.$resp->body());
        }

        $domains = $resp->json();
        $match = collect($domains)->firstWhere('name', $fqdn);

        if (! $match || ! isset($match['id'])) {
            throw new PleskException('Subdomain created but not found in Plesk listing: '.$fqdn);
        }

        return ['id' => (int) $match['id'], 'name' => $fqdn];
    }

    /** @param array<int,string> $args */
    private function runPlesk(array $args): string
    {
        $cmd = array_merge(['sudo', '-n', 'plesk'], $args);
        $process = new Process($cmd);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new PleskException(sprintf(
                'plesk %s failed (exit %d): %s',
                implode(' ', $args),
                $process->getExitCode(),
                trim($process->getErrorOutput() ?: $process->getOutput())
            ));
        }

        return $process->getOutput();
    }

    private function tail(string $path, int $lines): string
    {
        $process = new Process(['sudo', '-n', 'tail', '-n', (string) $lines, $path]);
        $process->setTimeout(15);
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        return $process->getOutput();
    }

    private function pleskUrl(string $path): string
    {
        $host = config('artifacthost.plesk.host');
        $port = config('artifacthost.plesk.port');

        return 'https://'.$host.':'.$port.$path;
    }

    private function apex(): string
    {
        return config('artifacthost.apex');
    }

    private function subscriptionDomain(): string
    {
        return config('artifacthost.subscription_domain') ?: $this->apex();
    }
}
