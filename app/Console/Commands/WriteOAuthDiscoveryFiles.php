<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * The staging host's nginx answers /.well-known/* itself and never passes it to Laravel, so the MCP
 * OAuth discovery routes are unreachable there. This writes their responses as static files that
 * nginx can serve. Run it on the server after config:cache, because the URLs come from APP_URL.
 */
#[Signature('mcp:well-known {--path= : Target directory (default: public/.well-known)}')]
#[Description('Write the MCP OAuth discovery documents as static files under public/.well-known')]
class WriteOAuthDiscoveryFiles extends Command
{
    /**
     * Discovery paths a client fetches for the /mcp endpoint. The root oauth-protected-resource document
     * is left out: a file and a directory cannot share that name, and the 401 header points at /mcp.
     *
     * @var list<string>
     */
    private const array PATHS = [
        'oauth-protected-resource/mcp',
        'oauth-authorization-server',
    ];

    public function handle(Kernel $kernel): int
    {
        $directory = $this->option('path') ?: public_path('.well-known');

        foreach (self::PATHS as $path) {
            $response = $kernel->handle(Request::create(rtrim(config('app.url'), '/').'/.well-known/'.$path));

            if (! $response->isOk()) {
                $this->components->error("/.well-known/{$path} answered {$response->getStatusCode()}.");

                return self::FAILURE;
            }

            File::ensureDirectoryExists(dirname("{$directory}/{$path}"));
            File::put("{$directory}/{$path}", $response->getContent());

            $this->components->info("Wrote {$directory}/{$path}");
        }

        return self::SUCCESS;
    }
}
