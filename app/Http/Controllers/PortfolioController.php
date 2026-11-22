<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\PortfolioRecord;
use App\Support\Portfolio;
use App\Support\PortfolioData;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $sections = [];
        foreach (Portfolio::SECTIONS as $section => $definition) {
            $query = PortfolioRecord::forSection($section)->newQuery()->where('published', true);
            if ($section === 'projects') {
                $query->orderByDesc('featured');
            }

            $sections[$section] = [
                'label' => $definition['label'],
                'empty' => $definition['empty'],
                'items' => PortfolioData::records($section, $query->latest('id')->get(), true),
            ];
        }

        return $this->page($request, 'Home', ['sections' => $sections, 'inquiryTypes' => Portfolio::INQUIRY_TYPES]);
    }

    public function project(Request $request, string $slug)
    {
        $record = PortfolioRecord::forSection('projects')->newQuery()->where('published', true)->where('slug', $slug)->firstOrFail();
        [$project] = PortfolioData::records('projects', $record->newCollection([$record]), true);

        return $this->page($request, 'Project', ['project' => $project], $record->title, $project['details']['overview'] ?? null, $project['media'][0]['url'] ?? null);
    }

    public function image(string $uuid)
    {
        return $this->file($uuid, true, true);
    }

    public function document(string $uuid)
    {
        return $this->file($uuid, true, false);
    }

    public function privateMedia(string $uuid)
    {
        return $this->file($uuid, false);
    }

    public function sitemap()
    {
        $urls = [route('home')];
        $projects = PortfolioRecord::forSection('projects')->newQuery()->where('published', true)->pluck('slug');
        foreach ($projects as $slug) {
            $urls[] = route('projects.show', $slug);
        }

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        return response("User-agent: *\nDisallow: /admin/\nSitemap: ".route('sitemap')."\n")->header('Content-Type', 'text/plain');
    }

    private function page(Request $request, string $component, array $props, ?string $title = null, ?string $description = null, ?string $image = null)
    {
        $profile = PortfolioData::singleton('profiles');
        $profile = Arr::except($profile, ['hidden_fields', ...($profile['hidden_fields'] ?? [])]);
        $site = PortfolioData::singleton('site_settings');
        $photo = PortfolioData::media('profile', [1])->first()?->viewData(true)['url'] ?? null;
        $siteName = ($site['name'] ?? null) ?: (($profile['name'] ?? null) ?: 'Personal Portfolio');
        $meta = [
            'title' => $title ? $title.' | '.$siteName : $siteName,
            'description' => $description ?: (($site['description'] ?? null) ?: 'Professional portfolio, projects, credentials, and contact information.'),
            'image' => url($image ?: ($photo ?: '/icons/icon-512.png')),
            'url' => $request->url(),
        ];

        return Inertia::render($component, [...$props, 'site' => $site, 'profile' => $profile, 'photo' => $photo])
            ->withViewData(['meta' => $meta])
            ->toResponse($request)
            ->withHeaders([
                'X-Portfolio-Public' => 'page',
                'X-Portfolio-Build' => Inertia::getVersion() ?? '',
                'Cache-Control' => 'no-cache',
            ]);
    }

    private function file(string $uuid, bool $public, ?bool $imageOnly = null)
    {
        $file = Media::where('public_id', $uuid)->firstOrFail();
        $image = str_starts_with($file->mime, 'image/');
        abort_unless($imageOnly === null || $imageOnly === $image, 404);

        if ($public && $file->owner_type !== 'profile') {
            $section = $file->owner_type;
            abort_unless(isset(Portfolio::SECTIONS[$section]) &&
                PortfolioRecord::forSection($section)->newQuery()->whereKey($file->owner_id)->where('published', true)->exists(), 404);
        }

        $disk = Storage::disk($file->disk);
        $stream = $disk->readStream($file->path);
        abort_unless(is_resource($stream), 404);
        $resume = $public && $file->owner_type === 'resumes';
        $name = $resume ? 'resume.pdf' : basename($file->path);

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $file->mime,
            'Content-Length' => $file->bytes,
            'Content-Disposition' => ($resume ? 'attachment' : 'inline').'; filename="'.$name.'"',
            'Cache-Control' => $public && $image ? 'no-cache' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            ...($public && $image ? ['X-Portfolio-Public' => 'image'] : []),
        ]);
    }
}
