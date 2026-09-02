<?php

namespace App\Models;

use Database\Factories\OrganizationMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Support\Config;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['organization_id', 'user_id'])]
class OrganizationMembership extends Model
{
    /** @use HasFactory<OrganizationMembershipFactory> */
    use HasFactory, HasRoles;

    protected string $guard_name = 'web';

    /**
     * Delete Spatie assignments for memberships removed by a parent cascade.
     *
     * @param  Builder<OrganizationMembership>  $memberships
     */
    public static function deleteAuthorizationAssignmentsFor(Builder $memberships): void
    {
        $membership = $memberships->getModel();
        $membershipIds = (clone $memberships)->select(
            $membership->qualifyColumn($membership->getKeyName()),
        );

        foreach ([Config::modelHasRolesTable(), Config::modelHasPermissionsTable()] as $table) {
            DB::table($table)
                ->where('model_type', $membership->getMorphClass())
                ->whereIn(Config::morphKey(), $membershipIds)
                ->delete();
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
