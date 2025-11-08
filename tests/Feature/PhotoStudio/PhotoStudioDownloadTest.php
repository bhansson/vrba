<?php

namespace Tests\Feature\PhotoStudio;

use App\Models\PhotoStudioGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoStudioDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_generation_from_own_team(): void
    {
        Storage::fake('public');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $generation = PhotoStudioGeneration::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'product_id' => null,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Prompt text',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 'public',
            'storage_path' => 'photo-studio/test.png',
        ]);

        Storage::disk('public')->put('photo-studio/test.png', 'fake-image');

        $this->actingAs($user)
            ->get(route('photo-studio.gallery.download', $generation))
            ->assertOk()
            ->assertDownload(sprintf('photo-studio-%s-test.png', $generation->id));
    }

    public function test_user_cannot_download_generation_from_another_team(): void
    {
        Storage::fake('public');

        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherTeam = $otherUser->currentTeam;

        $generation = PhotoStudioGeneration::create([
            'team_id' => $otherTeam->id,
            'user_id' => $otherUser->id,
            'product_id' => null,
            'source_type' => 'product_image',
            'source_reference' => 'https://cdn.example.com/reference.png',
            'prompt' => 'Prompt text',
            'model' => 'google/gemini-2.5-flash-image',
            'storage_disk' => 'public',
            'storage_path' => 'photo-studio/other-test.png',
        ]);

        Storage::disk('public')->put('photo-studio/other-test.png', 'fake-image');

        $this->actingAs($user)
            ->get(route('photo-studio.gallery.download', $generation))
            ->assertNotFound();
    }
}
