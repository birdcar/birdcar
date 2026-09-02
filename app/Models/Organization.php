<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\Attributes\Sluggable;

#[RouteKey('slug')]
#[Sluggable(from: 'name', to: 'slug', unique: true)]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Organization $organization): void {
            OrganizationMembership::deleteAuthorizationAssignmentsFor(
                $organization->memberships()->getQuery(),
            );
        });
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }
}
