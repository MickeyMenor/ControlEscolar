<script>
    const archiveModel = @json($archive);
    const appliantModel = @json($appliant);
    const academicProgram = @json($academic_program);
    const recommendation_letters = @json($recommendation_letters);
    const archives_recommendation_letters = @json($archives_recommendation_letters);
    const header_academic_program = @json($header_academic_program);
    const letters_Commitment = @json($letters_Commitment);
    const viewer = @json($viewer);
</script>
@extends('layouts.app')

@section('container-class', 'class=container')
@section('main')
    {{-- Img representativa de postulacion --}}
    <img class="img-fluid mt-4 " src="{{ $header_academic_program }}" width="100%" height="300px!important">

    <form v-on:submit.prevent="actualizaSolicitud">
        <solicitud-postulante :archive_id="archive.id" :personal_documents="archive.personal_documents"
            :curricular_documents="archive.curricular_documents" :motivation="archive.motivation"
            :entrance_documents="archive.entrance_documents" :appliant="appliant" :viewer="viewer"
            :academic_program="academic_program" :academic_degrees="archive.academic_degrees"
            :appliant_languages="archive.appliant_languages"
            :appliant_working_experiences="archive.appliant_working_experiences"
            :scientific_productions="archive.scientific_productions" :human_capitals="archive.human_capitals"
            :recommendation_letters="recommendation_letters"
            :archives_recommendation_letters="archives_recommendation_letters" :letters_Commitment="letters_Commitment">
        </solicitud-postulante>
    </form>
@endsection

@push('scripts')

    {{-- Tendremos cuatro vistas
        Administrador               Eliminar, agregar, modificar Cerrar expediente      components (postulaion.js)
        Postulante                  Eliminar, agregar, modificar                        appliant-view (appliant.js)
        Coordinador y profesor      Visualizar solamente                                professor-view (proffesor.js)
        Control Escolar             Visualizar y agregar, no modificar ni eliminar      controlescolar-view (controlescolar.js) 15641 --}}
    {{-- Admin view --}}
    @if (Auth::user()->hasRole('admin'))
        <script src="{{ asset('js/postulacion.js') }}" defer></script>
        {{-- Professor view --}}
    @elseif (Auth::user()->hasRole('control_escolar') || Auth::user()->hasRole('personal_apoyo') || Auth::user()->hasRole('coordinador') || Auth::user()->id === 15641)
        <script src="{{ asset('controlescolar/js/controlescolar.js') }}" defer></script>
    @elseif (Auth::user()->hasRole('profesor_colaborador') || Auth::user()->hasRole('profesor_nb'))
        <script src="{{ asset('professor/js/professor.js') }}" defer></script>
        {{-- Appliant view --}}

        {{-- Control escolar and personal de apoyo view --}}
    @else
        <script src="{{ asset('appliant/js/appliant.js') }}" defer></script>
    @endif


@endpush
