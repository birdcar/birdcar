<?php

use App\Models\Article;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Exceptions;

test('lazy loading a relation across a multi-model result throws outside production', function (): void {
    Article::factory()->count(2)->create();
    $article = Article::query()->oldest('id')->get()->firstOrFail();

    expect(fn () => $article->author)->toThrow(LazyLoadingViolationException::class);
});

test('production reports lazy loading instead of throwing and still loads the relation', function (): void {
    Exceptions::fake();
    Article::factory()->count(2)->create();
    app()->detectEnvironment(fn (): string => 'production');
    $article = Article::query()->oldest('id')->get()->firstOrFail();

    $author = $article->author;

    expect($author?->id)->toBe($article->author_id);
    Exceptions::assertReported(fn (LazyLoadingViolationException $violation): bool => $violation->relation === 'author');
});

test('mass assigning an attribute that is not fillable throws outside production', function (): void {
    expect(fn () => new Article(['updated_at' => now()]))->toThrow(MassAssignmentException::class);
});
