<?php

namespace App\Services\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileImageService
{
    /** @var list<string> */
    private const EXTENSIONS = ['png', 'jpg', 'jpeg'];

    public function path(User $user): ?string
    {
        foreach (self::EXTENSIONS as $extension) {
            $path = 'profile-images/'.$user->id.'.'.$extension;
            if (Storage::disk('local')->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function store(User $user, UploadedFile $image): string
    {
        $extension = match ($image->getMimeType()) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            default => throw new \InvalidArgumentException('Unsupported profile image type.'),
        };
        $path = 'profile-images/'.$user->id.'.'.$extension;
        $stored = Storage::disk('local')->put($path, $image->getContent());
        if (! $stored) {
            throw new \RuntimeException('The profile image could not be stored.');
        }

        Storage::disk('local')->delete(collect(self::EXTENSIONS)
            ->reject(fn (string $candidate): bool => $candidate === $extension)
            ->map(fn (string $candidate): string => 'profile-images/'.$user->id.'.'.$candidate)
            ->all());

        return $path;
    }

    public function delete(User $user): void
    {
        Storage::disk('local')->delete(array_map(
            fn (string $extension): string => 'profile-images/'.$user->id.'.'.$extension,
            self::EXTENSIONS,
        ));
    }
}
