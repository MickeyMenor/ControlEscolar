<script>
    const academicPrograms = @json($academic_programs);
    const imgHeader = @json($img_header);
</script>
@extends('layouts.app')

@section('main')
    <div class="mt-5 row text-center justify-content-center">
        <academic-program v-for="academic_program in academic_programs" v-bind="academic_program" :id="academic_program.id"
            :photo="academic_program.card_location"
            @@click="selected_academic_program = academic_program">
        </academic-program>
    </div>
    <pre-registro :academic_program.sync="selected_academic_program"></pre-registro>
@endsection

@push('scripts')
    <script src="{{ asset('js/preregistro.js') }}" defer></script>
@endpush
