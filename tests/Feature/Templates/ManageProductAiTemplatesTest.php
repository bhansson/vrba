<?php

namespace Tests\Feature\Templates;

use App\Livewire\ManageProductAiTemplates;
use App\Models\ProductAiTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageProductAiTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\ProductAiTemplate::syncDefaultTemplates();
    }

    public function test_templates_page_is_accessible(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('ai-templates.index'))
            ->assertOk()
            ->assertSeeText('Template Library');
    }

    public function test_user_can_create_custom_template(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user);

        Livewire::test(ManageProductAiTemplates::class)
            ->call('startCreate')
            ->set('form.name', 'Creative Summary')
            ->set('form.description', 'Crafts a short marketing summary for the product.')
            ->set('form.prompt', 'Write a short summary for {{ title }} using {{ description }}')
            ->set('form.content_type', 'text')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('product_ai_templates', [
            'team_id' => $team->id,
            'name' => 'Creative Summary',
            'is_default' => false,
            'is_active' => true,
        ]);

        $template = ProductAiTemplate::query()
            ->where('team_id', $team->id)
            ->where('name', 'Creative Summary')
            ->first();

        $this->assertNotNull($template);
        $this->assertSame('Crafts a short marketing summary for the product.', $template->description);
        $this->assertSame('text', $template->settings['content_type']);
        $this->assertEqualsCanonicalizing(
            ['title', 'description'],
            collect($template->context ?? [])->pluck('key')->all()
        );
    }
}
