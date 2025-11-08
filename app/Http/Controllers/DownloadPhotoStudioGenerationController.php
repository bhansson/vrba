<?php

namespace App\Http\Controllers;

use App\Models\PhotoStudioGeneration;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPhotoStudioGenerationController extends Controller
{
    public function __invoke(PhotoStudioGeneration $generation): Response|StreamedResponse
    {
        $team = Auth::user()?->currentTeam;

        abort_if(! $team || $generation->team_id !== $team->id, 404);

        $disk = $generation->storage_disk;
        $path = $generation->storage_path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $filename = sprintf(
            'photo-studio-%s-%s',
            $generation->id,
            basename($path)
        );

        return Storage::disk($disk)->download($path, $filename);
    }
}
