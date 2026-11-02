<?php

namespace Tests\Feature\Modules\SharedPlatform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SecureDefaultsTest extends TestCase
{
    #[TestWith(['short1'])]
    #[TestWith(['onlyletters'])]
    #[TestWith(['1234567890'])]
    public function test_default_password_rule_rejects_weak_passwords(string $password): void
    {
        $this->assertTrue(Validator::make(['password' => $password], ['password' => Password::defaults()])->fails());
    }

    public function test_default_password_rule_accepts_letters_and_numbers_of_at_least_eight_characters(): void
    {
        $this->assertTrue(Validator::make(['password' => 'mall2026x'], ['password' => Password::defaults()])->passes());
    }

    public function test_eloquent_is_strict_outside_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertTrue(Model::preventsSilentlyDiscardingAttributes());
    }
}
