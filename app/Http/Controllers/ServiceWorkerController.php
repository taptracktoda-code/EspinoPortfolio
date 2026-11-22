<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class ServiceWorkerController extends Controller
{
    public function __invoke()
    {
        $manifest = public_path('build/manifest.json');
        $build = is_file($manifest) ? file_get_contents($manifest) : '{}';
        $worker = file_get_contents(resource_path('js/service-worker.js'));
        $entries = json_decode($build, true, flags: JSON_THROW_ON_ERROR);
        $assets = [];
        $seen = [];
        $pending = ['resources/js/app.tsx', 'resources/js/pages/Home.tsx', 'resources/js/pages/Project.tsx'];
        while ($key = array_pop($pending)) {
            if (isset($seen[$key]) || ! isset($entries[$key])) {
                continue;
            }
            $seen[$key] = true;
            $entry = $entries[$key];
            array_push($pending, ...($entry['imports'] ?? []));
            foreach ([$entry['file'], ...($entry['css'] ?? []), ...($entry['assets'] ?? [])] as $file) {
                $assets[] = '/build/'.$file;
            }
        }
        $version = json_encode(substr(hash('sha256', $build.$worker), 0, 16));
        $pageVersion = json_encode(Inertia::getVersion() ?? '');
        $assetList = json_encode(array_values(array_unique($assets)), JSON_UNESCAPED_SLASHES);

        return response("const BUILD_VERSION = {$version};\nconst PAGE_VERSION = {$pageVersion};\nconst STATIC_ASSETS = {$assetList};\n".$worker, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
