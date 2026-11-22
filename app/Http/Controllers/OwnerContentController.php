<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\PortfolioRecord;
use App\Support\MediaStore;
use App\Support\Portfolio;
use App\Support\PortfolioData;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class OwnerContentController extends Controller
{
    public function dashboard()
    {
        $stats = [];
        foreach (Portfolio::SECTIONS as $section => $spec) {
            $stats[] = ['label' => $spec['label'], 'count' => PortfolioRecord::forSection($section)->newQuery()->count(), 'href' => '/admin/'.$section];
        }
        $stats[] = ['label' => 'New inquiries', 'count' => Inquiry::where('status', 'New')->count(), 'href' => '/admin/inquiries'];

        return Inertia::render('Dashboard', ['stats' => $stats, 'activity' => AuditLog::latest('id')->limit(8)->get()]);
    }

    public function audits()
    {
        return Inertia::render('Audit', ['activity' => AuditLog::latest('id')->paginate(25)->onEachSide(1)]);
    }

    public function profile()
    {
        $data = PortfolioData::singleton('profiles');

        return Inertia::render('Settings', [
            'kind' => 'profile', 'fields' => Portfolio::PROFILE, 'values' => Arr::only($data, array_keys(Portfolio::PROFILE)),
            'hiddenFields' => $data['hidden_fields'] ?? [],
            'photo' => PortfolioData::media('profile', [1])->first()?->viewData(false)['url'] ?? null,
        ]);
    }

    public function settings()
    {
        return Inertia::render('Settings', ['kind' => 'settings', 'fields' => Portfolio::SETTINGS, 'values' => PortfolioData::singleton('site_settings')]);
    }

    public function saveProfile(Request $request)
    {
        $data = $request->validate([
            ...PortfolioData::rules(Portfolio::PROFILE),
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_photo' => 'nullable|boolean',
            'hidden_fields' => 'nullable|array',
            'hidden_fields.*' => ['string', Rule::in(array_keys(Portfolio::PROFILE))],
        ]);
        $added = [];
        $removed = [];
        try {
            DB::transaction(function () use ($data, &$added, &$removed) {
                PortfolioData::saveSingleton('profiles', [
                    ...Arr::only($data, array_keys(Portfolio::PROFILE)),
                    'hidden_fields' => array_values(array_unique($data['hidden_fields'] ?? [])),
                ]);
                if (! empty($data['photo']) || ! empty($data['remove_photo'])) {
                    $removed = PortfolioData::media('profile', [1]);
                    foreach ($removed as $file) {
                        $file->delete();
                    }
                }
                if (! empty($data['photo'])) {
                    $added[] = MediaStore::add('profile', 1, $data['photo']);
                }
                AuditLog::record('Updated', 'Profile', 1);
            });
        } catch (Throwable $exception) {
            MediaStore::purge($added);
            throw $exception;
        }
        MediaStore::purge($removed);

        return back()->with('notice', 'Profile saved.');
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate(PortfolioData::rules(Portfolio::SETTINGS));
        DB::transaction(function () use ($data) {
            PortfolioData::saveSingleton('site_settings', $data);
            AuditLog::record('Updated', 'Settings', 1);
        });

        return back()->with('notice', 'Settings saved.');
    }

    public function index(string $section)
    {
        $spec = Portfolio::SECTIONS[$section];
        $page = PortfolioRecord::forSection($section)->newQuery()->latest('id')->paginate(20)->onEachSide(1);
        $items = PortfolioData::records($section, $page->getCollection(), false);

        return Inertia::render('Records', [
            'section' => $section, 'spec' => $spec, 'items' => $items,
            'categories' => $section === 'skills' ? $this->categories() : [],
            'savedCategories' => $section === 'skills' ? DB::table('skill_categories')->select('id', 'name')->orderBy('name')->get() : [],
            'pagination' => ['links' => $page->linkCollection(), 'total' => $page->total()],
        ]);
    }

    public function save(Request $request, string $section, ?int $id = null)
    {
        $spec = Portfolio::SECTIONS[$section];
        $upload = $spec['upload'] ?? null;
        $rules = [
            'title' => 'required|string|max:160',
            'published' => 'required|boolean',
            ...PortfolioData::rules($spec['fields']),
        ];
        if ($upload) {
            $rules += [
                'files' => 'nullable|array|max:'.$upload[3],
                'files.*' => 'required|file|mimes:'.$upload[1].'|max:'.$upload[2],
                'remove_media' => 'nullable|array|max:'.$upload[3],
                'remove_media.*' => 'integer',
            ];
        }
        foreach (['end' => 'start', 'expires' => 'issued'] as $end => $start) {
            if ($request->filled($start) && isset($rules[$end])) {
                $rules[$end][] = 'after_or_equal:'.$start;
            }
        }
        $data = $request->validate($rules, [
            'files.*.uploaded' => 'An attachment could not be uploaded. Check that each file is within the upload limit shown below the file picker.',
        ], ['files.*' => 'attachment']);
        $details = Arr::only($data, array_keys($spec['fields']));
        $removed = collect();
        $added = [];

        try {
            DB::transaction(function () use ($data, $details, $section, $id, $spec, $upload, &$removed, &$added) {
                if ($section === 'resumes') {
                    // A singleton row serializes changes to the active resume, including the first upload.
                    DB::table('site_settings')->insertOrIgnore(['id' => 1]);
                    DB::table('site_settings')->where('id', 1)->lockForUpdate()->first();
                }

                $model = PortfolioRecord::forSection($section);
                $record = $id ? $model->newQuery()->lockForUpdate()->findOrFail($id) : $model;
                $record->fill(['title' => $data['title'], 'published' => (bool) $data['published']]);
                if ($section === 'projects') {
                    $record->slug ??= (Str::slug(Str::limit($record->title, 100, '')) ?: 'project').'-'.strtolower((string) Str::ulid());
                    $record->featured = $details['featured'] ?? false;
                    unset($details['featured']);
                }
                if ($section === 'skills') {
                    $record->skill_category_id = $this->catalogId('skill_categories', $details['category']);
                    unset($details['category']);
                }
                $record->details = $details;
                $record->save();

                if ($upload) {
                    $existing = PortfolioData::media($section, [$record->id]);
                    $files = $data['files'] ?? [];
                    $removeIds = array_map('intval', $data['remove_media'] ?? []);
                    $removed = $existing->filter(fn ($file) => in_array($file->id, $removeIds, true) || ($files && $upload[3] === 1));
                    $remaining = $existing->count() - $removed->count() + count($files);
                    if ($remaining > $upload[3] || ($record->published && $remaining === 0 && in_array($section, ['resumes', 'certificates'], true))) {
                        throw ValidationException::withMessages(['files' => 'Check the file limit and attach a file before publishing.']);
                    }
                    foreach ($removed as $file) {
                        $file->delete();
                    }
                    foreach ($files as $file) {
                        $media = MediaStore::add($section, $record->id, $file);
                        $added[] = $media;
                        if ($section === 'projects') {
                            DB::table('project_images')->insert(['project_id' => $record->id, 'media_id' => $media->id, 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                }

                if ($section === 'projects') {
                    $this->syncTechnologies($record->id, $details['technologies'] ?? '');
                }
                if ($section === 'resumes' && $record->published) {
                    $others = $model->newQuery()->where('id', '!=', $record->id)->where('published', true);
                    foreach ($others->pluck('id') as $previous) {
                        AuditLog::record('Unpublished', $spec['item'], $previous);
                    }
                    $others->update(['published' => false]);
                }

                AuditLog::record($id ? 'Updated' : 'Created', $spec['item'], $record->id);
                if ($record->published && (! $id || $record->wasChanged('published'))) {
                    AuditLog::record('Published', $spec['item'], $record->id);
                } elseif ($id && $record->wasChanged('published')) {
                    AuditLog::record('Unpublished', $spec['item'], $record->id);
                }
            });
        } catch (Throwable $exception) {
            MediaStore::purge($added);
            throw $exception;
        }

        MediaStore::purge($removed);

        return to_route('admin.records', $section)->with('notice', $spec['item'].' saved.');
    }

    public function destroy(string $section, int $id)
    {
        $files = DB::transaction(function () use ($section, $id) {
            $record = PortfolioRecord::forSection($section)->newQuery()->lockForUpdate()->findOrFail($id);
            $files = PortfolioData::media($section, [$id]);
            foreach ($files as $file) {
                $file->delete();
            }
            $record->delete();
            AuditLog::record('Deleted', Portfolio::SECTIONS[$section]['item'], $id);

            return $files;
        });
        MediaStore::purge($files);

        return back()->with('notice', Portfolio::SECTIONS[$section]['item'].' deleted.');
    }

    public function saveCategory(Request $request, ?int $id = null)
    {
        $unique = Rule::unique('skill_categories');
        $data = $request->validate(['name' => ['required', 'string', 'max:100', $id ? $unique->ignore($id) : $unique]]);

        DB::transaction(function () use ($data, $id) {
            $table = DB::table('skill_categories');
            if ($id) {
                abort_unless($table->where('id', $id)->lockForUpdate()->first(), 404);
                $table->update([...$data, 'updated_at' => now()]);
            } else {
                $id = $table->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
            }
            AuditLog::record('Saved', 'Skill category', $id);
        });

        return back()->with('notice', 'Skill category saved.');
    }

    public function destroyCategory(int $id)
    {
        DB::transaction(function () use ($id) {
            $category = DB::table('skill_categories')->where('id', $id);
            abort_unless($category->lockForUpdate()->first(), 404);
            if (DB::table('skills')->where('skill_category_id', $id)->exists()) {
                throw ValidationException::withMessages(['category' => 'Move skills to another category before deleting this one.']);
            }
            $category->delete();
            AuditLog::record('Deleted', 'Skill category', $id);
        });

        return back()->with('notice', 'Skill category deleted.');
    }

    private function catalogId(string $table, string $name): int
    {
        DB::table($table)->insertOrIgnore(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table($table)->where('name', $name)->value('id');
    }

    private function categories(): array
    {
        return array_values(array_unique([
            'Programming languages', 'Frontend', 'Backend', 'Mobile development', 'Databases',
            'Development tools', 'Cloud platforms', 'Other IT competencies',
            ...DB::table('skill_categories')->orderBy('name')->pluck('name')->all(),
        ]));
    }

    private function syncTechnologies(int $project, string $stack): void
    {
        $names = array_unique(array_filter(array_map('trim', explode(',', $stack))));
        DB::table('project_technology')->where('project_id', $project)->delete();
        foreach ($names as $name) {
            $technology = $this->catalogId('technologies', Str::limit($name, 100, ''));
            DB::table('project_technology')->insertOrIgnore(['project_id' => $project, 'technology_id' => $technology]);
        }
    }
}
