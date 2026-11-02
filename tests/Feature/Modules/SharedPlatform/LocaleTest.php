<?php

namespace Tests\Feature\Modules\SharedPlatform;

use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_pages_render_in_english_by_default(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('<html lang="en">', false)->assertSee('Sign in');
    }

    public function test_choosing_bangla_translates_later_pages(): void
    {
        $this->from(route('login'))
            ->post(route('locale.update'), ['locale' => 'bn'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'bn');

        $this->get(route('login'))->assertSee('<html lang="bn">', false)->assertSee('সাইন ইন');
    }

    public function test_unsupported_locales_are_rejected(): void
    {
        $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');

        $this->assertNull(session('locale'));
    }
}
