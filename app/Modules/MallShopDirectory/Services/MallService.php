<?php

namespace App\Modules\MallShopDirectory\Services;

use App\Core\Exceptions\BusinessRuleViolation;
use App\Modules\MallShopDirectory\Enums\MallStatus;
use App\Modules\MallShopDirectory\Models\Mall;
use App\Modules\SharedPlatform\Services\AuditLogger;
use DateTimeZone;

/**
 * Creates malls and moves them through their lifecycle (draft → active → archived).
 */
class MallService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * New malls start as drafts until an administrator activates them.
     *
     * @param  array{slug: string, name: string, address: string, city: string, timezone: string}  $data
     */
    public function create(array $data): Mall
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug']) !== 1) {
            throw new BusinessRuleViolation('mall.invalid_slug', __('A mall slug may only contain lower-case letters, digits and single hyphens.'));
        }

        if (! in_array($data['timezone'], DateTimeZone::listIdentifiers(), true)) {
            throw new BusinessRuleViolation('mall.invalid_timezone', __(':timezone is not a known time zone.', ['timezone' => $data['timezone']]));
        }

        if (Mall::query()->where('slug', $data['slug'])->exists()) {
            throw new BusinessRuleViolation('mall.slug_taken', __('Another mall already uses this slug.'));
        }

        $mall = Mall::query()->create([...$data, 'status' => MallStatus::Draft]);

        $this->audit->record('mall.created', $mall, ['slug' => $mall->slug], mallId: $mall->getKey());

        return $mall;
    }

    /**
     * Changes what the mall's administrators maintain about it. The slug is the public address
     * and the status follows its own lifecycle, so neither is part of the profile.
     *
     * @param  array{name: string, address: string, city: string, timezone: string}  $data
     */
    public function updateProfile(Mall $mall, array $data): Mall
    {
        $name = trim($data['name']);
        $address = trim($data['address']);
        $city = trim($data['city']);

        if ($name === '' || mb_strlen($name) > 150 || $address === '' || $city === '') {
            throw new BusinessRuleViolation('mall.invalid_profile', __('Enter the mall name, address and city.'));
        }

        if (! in_array($data['timezone'], DateTimeZone::listIdentifiers(), true)) {
            throw new BusinessRuleViolation('mall.invalid_timezone', __(':timezone is not a known time zone.', ['timezone' => $data['timezone']]));
        }

        if ($mall->status === MallStatus::Archived) {
            throw new BusinessRuleViolation('mall.archived', __('An archived mall cannot be edited.'));
        }

        $mall->update(['name' => $name, 'address' => $address, 'city' => $city, 'timezone' => $data['timezone']]);

        $this->audit->record('mall.profile_changed', $mall, ['fields' => array_keys($mall->getChanges())], mallId: $mall->getKey());

        return $mall;
    }

    public function changeStatus(Mall $mall, MallStatus $to): Mall
    {
        $from = $mall->status;

        if (! in_array($to, $from->allowedTransitions(), true)) {
            throw new BusinessRuleViolation('mall.invalid_status_transition', __('A :from mall cannot become :to.', ['from' => $from->value, 'to' => $to->value]));
        }

        $mall->update(['status' => $to]);

        $this->audit->record('mall.status_changed', $mall, ['from' => $from->value, 'to' => $to->value], mallId: $mall->getKey());

        return $mall;
    }
}
