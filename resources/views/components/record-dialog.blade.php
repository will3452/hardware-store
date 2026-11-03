@props(['id', 'title', 'reopen' => false])
<dialog id="{{ $id }}" class="hms-record-dialog" aria-labelledby="{{ $id }}-title" x-data @if($reopen) x-init="$el.showModal()" @endif>
    <header class="hms-top"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="hms-secondary" autofocus @click="$el.closest('dialog').close()">Close ✕</button></header>
    <div class="hms-dialog-body">
        @if($reopen && $errors->any())<div class="hms-error" role="alert"><strong>Please review these details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        {{ $slot }}
    </div>
</dialog>
