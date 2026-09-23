<?php

use App\Authorization\Publishing\Permission as PublishingPermission;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public function mount(): void
    {
        if (auth()->user()?->can(PublishingPermission::View->value)) {
            $this->redirectRoute('admin.publishing.dashboard', navigate: false);
        }
    }
};
?>

<section class="space-y-6">
    <div>
        <h1 class="text-3xl font-semibold">Admin workspace</h1>
        <p class="mt-2 text-zinc-400">You have Admin access. Publishing data is hidden until publishing permissions are granted.</p>
    </div>
    <div class="rounded-xl border border-white/10 bg-white/5 p-6">
        <h2 class="font-medium">No editorial access</h2>
        <p class="mt-2 text-sm text-zinc-400">This shell intentionally shows no article names, counts, statuses, or writing queues.</p>
    </div>
</section>
