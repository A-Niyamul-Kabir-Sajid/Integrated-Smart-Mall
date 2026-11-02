<?php

namespace Tests\Feature\Core;

use App\Core\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BusinessRuleViolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/_test/business-rule', function (): never {
            throw new BusinessRuleViolation('shop_unit.floor_in_other_mall', 'The floor belongs to another mall.');
        });
    }

    public function test_json_requests_receive_422_with_the_rule_code(): void
    {
        $this->postJson('/_test/business-rule')
            ->assertStatus(422)
            ->assertExactJson([
                'message' => 'The floor belongs to another mall.',
                'rule' => 'shop_unit.floor_in_other_mall',
            ]);
    }

    public function test_browser_requests_are_redirected_back_with_the_message_and_safe_input(): void
    {
        $this->from('/form')
            ->post('/_test/business-rule', ['unit_code' => 'G-01', 'password' => 'secret-value'])
            ->assertRedirect('/form')
            ->assertSessionHasErrors(['business' => 'The floor belongs to another mall.'])
            ->assertSessionHasInput('unit_code', 'G-01')
            ->assertSessionMissing('_old_input.password');
    }
}
