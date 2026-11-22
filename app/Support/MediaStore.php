<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaStore
{
    public static function add(string $section, int $id, UploadedFile $file): Media
    {
        $disk = config('filesystems.default');
        $uuid = (string) Str::uuid();
        $path = 'portfolio/'.$uuid.'.'.$file->guessExtension();
        $mime = $file->getMimeType();
        $bytes = $file->getSize();

        if ($section === 'profile') {
            $contents = self::profileImage($file);
            $path = 'portfolio/'.$uuid.'.webp';
            $mime = 'image/webp';
            $bytes = strlen($contents);
            $saved = Storage::disk($disk)->put($path, $contents);
        } else {
            $stream = fopen($file->getRealPath(), 'rb');
            try {
                $saved = Storage::disk($disk)->put($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        if (! $saved) {
            throw new RuntimeException('The upload could not be stored.');
        }

        try {
            return Media::create([
                'public_id' => $uuid, 'owner_type' => $section, 'owner_id' => $id,
                'disk' => $disk, 'path' => $path, 'mime' => $mime, 'bytes' => $bytes,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public static function purge(iterable $files): void
    {
        foreach ($files as $file) {
            try {
                if (! Storage::disk($file->disk)->delete($file->path)) {
                    report(new RuntimeException('Could not remove unreferenced portfolio media '.$file->id));
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private static function profileImage(UploadedFile $file): string
    {
        $size = getimagesize($file->getRealPath());
        if (! $size || $size[0] * $size[1] > 20000000) {
            throw ValidationException::withMessages(['photo' => 'Choose an image with fewer than 20 megapixels.']);
        }

        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be decoded.']);
        }

        $edge = min(imagesx($image), imagesy($image));
        $target = min(640, $edge);
        $tile = imagecreatetruecolor($target, $target);
        imagealphablending($tile, false);
        imagesavealpha($tile, true);
        imagecopyresampled($tile, $image, 0, 0, (int) ((imagesx($image) - $edge) / 2), (int) ((imagesy($image) - $edge) / 2), $target, $target, $edge, $edge);
        ob_start();
        imagewebp($tile, null, 88);
        $contents = ob_get_clean();
        if (! $contents) {
            throw new RuntimeException('The profile photo could not be encoded.');
        }

        return $contents;
    }
}
