<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\Attributes\Sluggable;

#[RouteKey('slug')]
#[Sluggable(from: 'name', to: 'slug', unique: true)]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;
}
