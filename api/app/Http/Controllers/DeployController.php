<?php

namespace App\Http\Controllers;

use App\Exceptions\PleskException;
use App\Models\Deploy;
use App\Models\Site;
use App\Rules\Subdomain;
use App\Services\PleskService;
use App\Services\SftpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DeployController extends Controller
{
    public function __construct(
        private readonly PleskService $plesk,
        private readonly SftpService $sftp,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subdomain' => ['required', 'string', new Subdomain, Rule::unique('sites', 'subdomain')],
            'files' => ['required', 'array', 'min:1'],
            'files.*.path' => ['required', 'string'],
            'files.*.content' => ['required', 'string'],
            'files.*.encoding' => ['nullable', 'in:utf8,base64'],
        ]);

        $this->assertIndexHtml($data['files']);

        $user = $request->user();
        $subdomain = $data['subdomain'];

        $plesk = $this->plesk->createSubdomain($subdomain);

        $site = DB::transaction(fn () => Site::create([
            'user_id' => $user->id,
            'subdomain' => $subdomain,
            'plesk_domain_id' => $plesk['id'],
            'doc_root' => $plesk['doc_root'],
            'ssl_enabled' => false,
            'bytes_used' => 0,
        ]));

        try {
            $result = $this->sftp->deploy($site->doc_root, $data['files']);
        } catch (\Throwable $e) {
            $this->rollbackSubdomain($subdomain, $site);
            throw $e;
        }

        $site->update(['bytes_used' => $result['bytes']]);

        Deploy::create([
            'site_id' => $site->id,
            'file_count' => $result['file_count'],
            'bytes' => $result['bytes'],
            'status' => 'success',
        ]);

        $sslOk = $this->plesk->issueLetsEncrypt($subdomain);
        if ($sslOk) {
            $site->update(['ssl_enabled' => true]);
        }

        return response()->json([
            'subdomain' => $site->subdomain,
            'url' => $site->fresh()->url(),
            'ssl_enabled' => $site->ssl_enabled,
            'file_count' => $result['file_count'],
            'bytes' => $result['bytes'],
        ], 201);
    }

    public function update(Request $request, string $subdomain): JsonResponse
    {
        $site = $this->findSite($request, $subdomain);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*.path' => ['required', 'string'],
            'files.*.content' => ['required', 'string'],
            'files.*.encoding' => ['nullable', 'in:utf8,base64'],
        ]);

        $this->assertIndexHtml($data['files']);

        $deploy = Deploy::create([
            'site_id' => $site->id,
            'file_count' => count($data['files']),
            'bytes' => 0,
            'status' => 'uploading',
        ]);

        try {
            $result = $this->sftp->deploy($site->doc_root, $data['files']);
        } catch (\Throwable $e) {
            $deploy->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }

        $deploy->update([
            'file_count' => $result['file_count'],
            'bytes' => $result['bytes'],
            'status' => 'success',
        ]);

        $site->update(['bytes_used' => $result['bytes']]);

        return response()->json([
            'subdomain' => $site->subdomain,
            'url' => $site->fresh()->url(),
            'file_count' => $result['file_count'],
            'bytes' => $result['bytes'],
        ]);
    }

    private function findSite(Request $request, string $subdomain): Site
    {
        return $request->user()->sites()->where('subdomain', $subdomain)->firstOrFail();
    }

    /** @param array<int, array<string, mixed>> $files */
    private function assertIndexHtml(array $files): void
    {
        foreach ($files as $f) {
            $path = ltrim(str_replace('\\', '/', (string) ($f['path'] ?? '')), '/');
            if ($path === 'index.html') {
                return;
            }
        }
        abort(422, 'index.html is required at the root of files[]');
    }

    private function rollbackSubdomain(string $subdomain, Site $site): void
    {
        try {
            $this->plesk->deleteSubdomain($subdomain);
        } catch (PleskException $e) {
            Log::error('artifact-host: rollback subdomain delete failed', [
                'subdomain' => $subdomain,
                'error' => $e->getMessage(),
            ]);
        }
        $site->delete();
    }
}
