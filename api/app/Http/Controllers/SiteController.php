<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\PleskService;
use App\Services\SftpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function __construct(
        private readonly PleskService $plesk,
        private readonly SftpService $sftp,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sites = $request->user()->sites()
            ->orderByDesc('created_at')
            ->get(['subdomain', 'custom_domain', 'ssl_enabled', 'bytes_used', 'created_at'])
            ->map(fn (Site $s) => [
                'subdomain' => $s->subdomain,
                'url' => $s->url(),
                'custom_domain' => $s->custom_domain,
                'ssl_enabled' => $s->ssl_enabled,
                'bytes_used' => $s->bytes_used,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return response()->json(['sites' => $sites]);
    }

    public function destroy(Request $request, string $subdomain): JsonResponse
    {
        $site = $this->findSite($request, $subdomain);

        $this->sftp->teardown($site->doc_root);
        $this->plesk->deleteSubdomain($site->subdomain);

        $site->delete();

        return response()->json(['deleted' => $subdomain]);
    }

    public function logs(Request $request, string $subdomain): JsonResponse
    {
        $site = $this->findSite($request, $subdomain);
        $lines = (int) $request->query('lines', 100);

        $logs = $this->plesk->tailLogs($site->subdomain, $lines);

        return response()->json([
            'subdomain' => $site->subdomain,
            'access' => $logs['access'],
            'error' => $logs['error'],
        ]);
    }

    private function findSite(Request $request, string $subdomain): Site
    {
        return $request->user()->sites()->where('subdomain', $subdomain)->firstOrFail();
    }
}
