@if (session('status'))
    <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
@endif
