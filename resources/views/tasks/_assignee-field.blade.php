<x-ui.field id="task-assignee" :label="$t('المسؤول عن المهمة', 'Task assignee')" :error="$errors->first('assignee_id')">
    <x-ui.select name="assignee_id" required>
        @if ($selectedAssigneeId !== $actor->actorId && $selectedAssignee === null)
            <option value="" disabled selected>{{ $t('المسؤول غير متاح؛ اختر مسؤولاً مؤهلاً', 'Assignee unavailable; select an eligible user') }}</option>
        @endif
        <option value="{{ $actor->actorId }}" @selected($selectedAssigneeId === $actor->actorId)>{{ $t('أنا', 'Me') }}</option>
        @if ($selectedAssignee && ! $assignees->getCollection()->contains('id', $selectedAssignee->id))
            <option value="{{ $selectedAssignee->id }}" selected>{{ $selectedAssignee->name }}</option>
        @endif
        @foreach ($assignees as $assignee)
            @if ($assignee->id !== $actor->actorId)
                <option value="{{ $assignee->id }}" @selected($selectedAssigneeId === $assignee->id)>{{ $assignee->name }}</option>
            @endif
        @endforeach
    </x-ui.select>
</x-ui.field>
