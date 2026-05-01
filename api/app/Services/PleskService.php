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
        $fqdn = $subdomain.'.'.$this->apex();
        $wwwRoot = '/'.$fqdn;

        // Plesk Web Host Edition only supports subdomain --create when the parent
        // is a primary domain. To host <sub>.<apex> where <apex> is itself a
        // subdomain (e.g. hosting.<primary>), pass the multi-segment prefix as
        // the subdomain name and the primary as -domain.
        // -php false: artifact-host serves static files only. Default Plesk
        // creates with PHP enabled, which would let any user upload a webshell.
        $this->runPlesk([
            'bin', 'subdomain', '--create', $this->subdomainName($subdomain),
            '-domain', $this->pleskSubscriptionDomain(),
            '-www-root', $wwwRoot,
            '-php', 'false',
        ]);

        $info = $this->findSubdomain($fqdn);

        return [
            'id' => $info['id'],
            'doc_root' => '/var/www/vhosts/'.$this->subscriptionDomain().$wwwRoot,
        ];
    }

    public function deleteSubdomain(string $subdomain): void
    {
        $this->runPlesk([
            'bin', 'subdomain', '--remove', $subdomain.'.'.$this->apex(),
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

        // Plesk stores domain names case-sensitively but the REST `?name=` filter
        // is case-insensitive. Match on ascii_name (always lowercase) to avoid
        // brittle case comparisons.
        $domains = $resp->json();
        $match = collect($domains)->firstWhere('ascii_name', $fqdn);

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

    private function pleskSubscriptionDomain(): string
    {
        return config('artifacthost.plesk_subscription_domain') ?: $this->subscriptionDomain();
    }

    /**
     * Build the multi-segment subdomain name to pass to `subdomain --create`.
     * For apex=hosting.<primary> and subdomain=foo, returns "foo.hosting".
     * For apex equal to the primary (no nested subdomain), returns "foo".
     */
    private function subdomainName(string $subdomain): string
    {
        $apex = $this->apex();
        $primary = $this->subscriptionDomain();
        $suffix = '.'.$primary;

        if (str_ends_with($apex, $suffix)) {
            $prefix = substr($apex, 0, -strlen($suffix));

            return $prefix === '' ? $subdomain : $subdomain.'.'.$prefix;
        }

        return $subdomain;
    }
}
