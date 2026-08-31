@if(session('success'))
    <div class="alert success">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert warning">{{ session('warning') }}</div>
@endif
@if($errors->any())
    <div class="alert danger validation-summary" role="alert" data-validation-summary>
        <strong>Periksa kembali isian Anda.</strong>
        <ul class="validation-summary-list">
            @foreach(array_slice($errors->all(), 0, 5) as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
        @if($errors->count() > 5)
            <div class="text-sm">Dan {{ $errors->count() - 5 }} masalah lainnya.</div>
        @endif
    </div>
    <script type="application/json" id="sirkel-validation-errors">@json($errors->messages())</script>
@endif