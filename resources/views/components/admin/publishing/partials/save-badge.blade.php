@props(['state' => 'saved'])
<span class="publishing-save-state" role="status" data-save-state="{{ $state }}" :data-save-state="editorState" x-text="editorState.charAt(0).toUpperCase() + editorState.slice(1)">{{ ucfirst($state) }}</span>
