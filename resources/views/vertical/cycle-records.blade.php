@extends('layouts.shell')

@section('content')
    <section class="card" aria-labelledby="cycle-heading">
        <h2 id="cycle-heading">دورة تشغيلية</h2>
        <p>أنشئ سجلاً تجريبياً محفوظاً ضمن نطاق مؤسستك.</p>
        <form id="cycle-record-form" method="post" action="/vertical/cycle-records">
            @csrf
            <label for="record_type">نوع السجل</label>
            <input id="record_type" name="record_type" required maxlength="100" autocomplete="off">
            <label for="payload_state">الحالة</label>
            <input id="payload_state" name="payload[state]" required autocomplete="off">
            <button type="submit">حفظ</button>
        </form>
    </section>
@endsection
