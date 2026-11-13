@props(['scope', 'label'])
{{-- Mints a read-only share link for this page's scope; the link then shows in settings. --}}
<form method="POST" action="{{ route('settings.shares.store') }}">
    @csrf
    <input type="hidden" name="scope" value="{{ $scope }}">
    <button class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800">{{ $label }}</button>
</form>
