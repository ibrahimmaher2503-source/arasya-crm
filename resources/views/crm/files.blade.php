@extends('layouts.shell')
@section('content')
<div class="ui-stack crm-page">
    @include('crm._entity-header')
    @include('crm._workspace-nav', ['section' => 'files'])
    <div class="crm-workspace-layout">
        <section class="ui-detail-panel crm-workspace-main">
            <x-ui.filter-bar :action="request()->url()" class="crm-workspace-filters">
                <input type="hidden" name="locale" value="{{ $locale }}">
                <x-ui.search-input name="q" :value="request('q')" :aria-label="$t('البحث في الملفات', 'Search files')" :placeholder="$t('البحث في الملفات...', 'Search files...')" />
                <x-ui.input name="category" :value="request('category')" :aria-label="$t('التصنيف', 'Category')" :placeholder="$t('جميع التصنيفات', 'All categories')" />
                <x-ui.button type="submit" variant="secondary">{{ $t('تطبيق', 'Apply') }}</x-ui.button>
                <a class="ui-button ui-button--ghost" href="{{ request()->url() }}?locale={{ $locale }}">{{ $t('مسح', 'Clear') }}</a>
            </x-ui.filter-bar>
            <x-ui.table :caption="$title" class="crm-workspace-table">
                <thead><tr><th>{{ $t('اسم الملف', 'File name') }}</th><th>{{ $t('الوصف', 'Description') }}</th><th>{{ $t('التصنيف', 'Category') }}</th><th>{{ $t('الحجم', 'Size') }}</th><th>{{ $t('تاريخ الرفع', 'Uploaded') }}</th><th>{{ $t('الإجراءات', 'Actions') }}</th></tr></thead>
                <tbody>
                @forelse ($files as $file)
                    <tr><td><span class="ui-inline"><x-ui.icon name="audit" /><bdi>{{ $file['name'] }}</bdi></span></td><td>{{ $file['description'] }}</td><td><x-ui.status-badge status="info">{{ $file['category'] }}</x-ui.status-badge></td><td><bdi>{{ number_format($file['size'] / 1024, 1) }} KB</bdi></td><td><bdi>{{ $file['created_at'] }}</bdi></td><td><a class="ui-button ui-button--ghost" href="/crm/leads/{{ $lead['id'] }}/files/{{ $file['id'] }}/download">{{ $t('تنزيل', 'Download') }}</a></td></tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state :title="$t('لا توجد ملفات', 'No files found')" /></td></tr>
                @endforelse
                </tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$files" :locale="$locale" />
        </section>
        <aside class="ui-stack crm-workspace-aside">
            @if (! $lead['archived'] && in_array('crm.write', $permissions, true))
            <x-ui.detail-panel :title="$t('رفع ملف جديد', 'Upload new file')">
                <form class="ui-stack" method="post" action="/crm/leads/{{ $lead['id'] }}/files?locale={{ $locale }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="version" value="{{ $lead['version'] }}">
                    <x-ui.field id="crm-upload" :label="$t('اختر ملفاً', 'Choose a file')" :hint="$t('PDF، صور، نصوص، Word أو Excel حتى 50 MB', 'PDF, images, text, Word or Excel up to 50 MB')"><x-ui.input id="crm-upload" type="file" name="file" accept=".pdf,.png,.jpg,.jpeg,.txt,.docx,.xlsx" required /></x-ui.field>
                    <x-ui.field id="crm-file-description" :label="$t('الوصف', 'Description')"><x-ui.textarea id="crm-file-description" name="description" maxlength="500" /></x-ui.field>
                    <x-ui.field id="crm-file-category" :label="$t('التصنيف', 'Category')"><x-ui.input id="crm-file-category" name="category" maxlength="80" /></x-ui.field>
                    <x-ui.button type="submit"><x-ui.icon name="plus" />{{ $t('رفع الملف', 'Upload file') }}</x-ui.button>
                </form>
            </x-ui.detail-panel>
            @endif
            <x-ui.detail-panel :title="$t('تصنيفات الملفات', 'File categories')">
                <ul class="crm-breakdown-list">@foreach ($categories as $category)<li class="crm-breakdown-list__heading"><a href="{{ request()->url() }}?{{ http_build_query(['locale' => $locale, 'category' => $category['category']]) }}">{{ $category['category'] }}</a><strong>{{ $category['total'] }}</strong></li>@endforeach</ul>
            </x-ui.detail-panel>
            <x-ui.stat-card :label="$t('حجم ملفات هذا العميل', 'Storage used by this lead')" :value="number_format($bytes / 1048576, 2).' MB'" />
        </aside>
    </div>
</div>
@endsection
