<?php

namespace App\Policies;

use App\Authorization\Publishing\Permission as PublishingPermission;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PublishingPermission::View->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Article $article): bool
    {
        return $user->can(PublishingPermission::View->value)
            && (int) $article->author_id === (int) $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PublishingPermission::Write->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Article $article): bool
    {
        return $user->can(PublishingPermission::Write->value)
            && (int) $article->author_id === (int) $user->id;
    }

    public function develop(User $user, Article $article): bool
    {
        return $user->can(PublishingPermission::Develop->value)
            && (int) $article->author_id === (int) $user->id;
    }

    public function approve(User $user, Article $article): bool
    {
        return $user->can(PublishingPermission::Approve->value)
            && (int) $article->author_id === (int) $user->id;
    }

    public function publish(User $user, Article $article): bool
    {
        return $user->can(PublishingPermission::Publish->value)
            && (int) $article->author_id === (int) $user->id;
    }

    public function budget(User $user, Article $article): bool
    {
        return $user->can(PublishingPermission::Budget->value)
            && (int) $article->author_id === (int) $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Article $article): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Article $article): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Article $article): bool
    {
        return false;
    }
}
