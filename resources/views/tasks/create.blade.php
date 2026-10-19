@php
    $locale = ($locale ?? request()->query('locale', 'ar')) === 'en' ? 'en' : 'ar';
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
@endphp
@extends('layouts.shell', ['locale' => $locale])

@section('content')
    <div class="ui-stack">
        <x-ui.detail-panel :title="$t('بيانات المهمة', 'Task details')">
            @if ($canAssign)
                @include('tasks._assignee-browser')
            @endif
            <form class="ui-stack" method="post" action="{{ route('tasks.store') }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                @if (! $canAssign)
                    <input type="hidden" name="assignee_id" value="{{ $actor->actorId }}">
                @endif

                <div class="ui-grid">
                    <x-ui.field id="task-title" :label="$t('عنوان المهمة', 'Task title')" :error="$errors->first('title')">
                        <x-ui.input name="title" :value="old('title')" required autocomplete="off" />
                    </x-ui.field>

                    <x-ui.field id="task-priority" :label="$t('الأولوية', 'Priority')" :error="$errors->first('priority')">
                        <x-ui.select name="priority">
                            @foreach (['low' => $t('منخفضة', 'Low'), 'normal' => $t('عادية', 'Normal'), 'high' => $t('عالية', 'High')] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field id="task-due-at" :label="$t('تاريخ الاستحقاق', 'Due date')" :error="$errors->first('due_at')">
                        <x-ui.input name="due_at" type="datetime-local" :value="old('due_at')" />
                    </x-ui.field>
                </div>

                @if ($canAssign)
                    @include('tasks._assignee-field')
                @else
                    <x-ui.alert variant="info">{{ $t('سيتم إسناد المهمة إلى حسابك.', 'This task will be assigned to you.') }}</x-ui.alert>
                @endif

                <x-ui.form-actions>
                    <x-ui.button type="submit">{{ $t('إنشاء المهمة', 'Create task') }}</x-ui.button>
                    <a href="{{ route('tasks.index') }}">{{ $t('العودة إلى المهام', 'Back to tasks') }}</a>
                </x-ui.form-actions>
            </form>
        </x-ui.detail-panel>
    </div>
@endsection
