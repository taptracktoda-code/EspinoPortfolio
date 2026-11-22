<?php

namespace App\Support;

use App\Models\Media;
use App\Models\PortfolioRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PortfolioData
{
    public static function singleton(string $table): array
    {
        return json_decode(DB::table($table)->where('id', 1)->value('details') ?? '{}', true);
    }

    public static function saveSingleton(string $table, array $details): void
    {
        $now = now();
        DB::table($table)->upsert(
            [['id' => 1, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]],
            ['id'],
            ['details', 'updated_at'],
        );
    }

    public static function media(string $section, array $ids): Collection
    {
        return Media::where('owner_type', $section)->whereIn('owner_id', $ids)->orderBy('id')->get();
    }

    public static function records(string $section, Collection $records, bool $public): array
    {
        $media = static::media($section, $records->modelKeys())->groupBy('owner_id');
        $categories = $section === 'skills' ? DB::table('skill_categories')->pluck('name', 'id') : collect();

        return $records->map(function (PortfolioRecord $record) use ($section, $media, $categories, $public) {
            $details = $record->details ?? [];
            if ($section === 'skills') {
                $details['category'] = $categories->get($record->skill_category_id);
            }
            if ($section === 'projects') {
                $details['featured'] = $record->featured;
            }

            return [
                'id' => $record->id, 'title' => $record->title, 'slug' => $record->slug,
                'published' => $record->published, 'details' => $details,
                'media' => $media->get($record->id, collect())->map(fn (Media $file) => $file->viewData($public))->values()->all(),
            ];
        })->all();
    }

    public static function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $key => $spec) {
            $rules[$key] = [
                ($spec[2] ?? null) === 'required' ? 'required' : 'nullable',
                ...match ($spec[1]) {
                    'checkbox' => ['boolean'],
                    'date' => ['date_format:Y-m-d'],
                    'url' => ['url:http,https', 'max:500'],
                    'email' => ['email:rfc', 'max:254'],
                    'color' => ['regex:/^#[0-9a-fA-F]{6}$/'],
                    'select' => [\Illuminate\Validation\Rule::in($spec[3])],
                    'category' => ['string', 'max:100'],
                    'textarea' => ['string', 'max:6000'],
                    default => ['string', 'max:500'],
                },
            ];
        }

        return $rules;
    }
}
