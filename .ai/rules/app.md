---
paths:
  - 'app/**'
---

# App

## Eloquent is strict outside production
AppServiceProvider::configureModelStrictness() makes Eloquent throw on N+1 lazy loading (any relation touched on a model from a multi-model result), mass-assigning non-fillable attributes, and reading attributes that weren't selected, in local and tests. Eager-load with with()/load() and set non-fillable columns such as timestamps with forceFill(). Production keeps serving and reports each N+1 through report(), which Nightwatch records; don't make production throw.
