@php
    use App\Authorization\Publishing\Permission as PublishingPermission;
@endphp
<nav class="space-y-2" aria-label="Admin">
    <a class="block rounded px-3 py-2 text-sm hover:bg-white/10" href="{{ route('admin.index') }}">Home</a>
    @can(PublishingPermission::View->value)
        <a class="block rounded px-3 py-2 text-sm hover:bg-white/10" href="{{ route('admin.publishing.dashboard') }}">Publishing workspace</a>
        <a class="block rounded px-3 py-2 text-sm hover:bg-white/10" href="{{ route('admin.publishing.published') }}">Published</a>
    @endcan
</nav>
