<form method="get" action="{{ request()->url() }}" class="ui-inline">
    <input type="hidden" name="locale" value="{{ $locale }}">
    <x-ui.field id="assignee-search" :label="$t('البحث عن مسؤول', 'Find an assignee')">
        <x-ui.search-input name="assignee_q" :value="request('assignee_q')" />
    </x-ui.field>
    <x-ui.button type="submit" variant="secondary">{{ $t('بحث', 'Search') }}</x-ui.button>
</form>
<x-ui.list-pagination :paginator="$assignees" :locale="$locale" />
