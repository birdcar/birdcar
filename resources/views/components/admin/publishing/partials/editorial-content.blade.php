@props(['value'])
@if (is_array($value))
    <div {{ $attributes->class(['publishing-editorial-content']) }}>
        @foreach ($value as $key => $item)
            @continue(in_array($key, ['source', 'activity_id', 'revision_id', 'input_hash', 'prompt_hash'], true) || $item === null || $item === [] || $item === '')
            <div>
                @if (is_string($key))
                    <p class="mb-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ str($key)->headline() }}</p>
                @endif
                <x-admin.publishing.partials.editorial-content :value="$item" />
            </div>
        @endforeach
    </div>
@elseif (is_scalar($value))
    <p {{ $attributes->class(['whitespace-pre-wrap break-words text-sm leading-relaxed text-zinc-600 dark:text-zinc-300']) }}>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</p>
@endif
