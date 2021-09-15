@props([
    'profile_picture' => null,
    'identidad_usuario' => null,
    'datos_personales' => null,
    'experiencia_laboral' => null,
    'informacion_personal' => null,
    'informacion_academica' => null,
    'formatos_ingreso' => null,
    'documentos_curriculares' => null,
    'grados_academicos' => null,
])

<form @if($attributes->has('v-on:submit.prevent'))
        v-on:submit.prevent="{{ $attributes->get('v-on:submit.prevent') }}"
        @endif>
    
    
    @csrf
    <div class="mt-5 form-row justify-content-left">
        <h4 class="col-md-9 my-4"><strong> Datos Personales </strong></h4>
        <div class="col-12">
            <div class="row">
                <div class="col-md-5 col-lg-6 col-xl-3 my-2"> {{ $profile_picture }} </div>
                <div class="form-group col-md-7 col-lg-6 col-xl-9">
                    <div class="row"> {{ $identidad_usuario }} </div>
                </div>
                
                {{ $datos_personales }}
            </div>
        </div>

        @isset($datos_universitarios)
        <hr class="col-md-12" @if($attributes->has('style')) style="{{ $attributes->get('style') }}" @endif>
        <h4 class="col-md-9 my-4"><strong> Datos Solicitud </strong></h4>
        

        <div class="col-12">
            <div class="row">
                {{ $datos_universitarios }}
                <div class="form-group col-12 my-0"></div>
            </div>
            <div class="row">
                {{ $datos_titulacion }}
                <div class="form-group col-12 my-0"></div>
            </div>
            <div class="row">
                {{ $datos_ingles }}
            </div>
        </div>
        @endisset

        @isset($informacion_personal)
        <hr class="col-md-12" @if($attributes->has('style')) style="{{ $attributes->get('style') }}" @endif>
        
        <h4 class="col-md-9 my-4"><strong> Información personal </strong></h4>
        <div class="col-12"> {{ $informacion_personal }} </div>
        @endisset

        @isset($informacion_academica)
        <hr class="col-md-12" @if($attributes->has('style')) style="{{ $attributes->get('style') }}" @endif>

        <h4 class="col-md-9 my-4"><strong> Información académica  </strong></h4>
        <div class="col-12"> {{ $informacion_academica }}</div>
        @endisset

        @isset($formatos_ingreso)
        <hr class="col-md-12" @if($attributes->has('style')) style="{{ $attributes->get('style') }}" @endif>

        <h4 class="col-md-9 my-4"><strong> Formatos de ingreso  </strong></h4>
        <div class="col-12"> {{ $formatos_ingreso }} </div>
        @endisset

        @isset($grados_academicos)
        <hr class="col-md-12" @if($attributes->has('style')) style="{{ $attributes->get('style') }}" @endif>
        <h4 class="col-md-9 my-4"><strong> Grados académicos </strong></h4>
        
        <div class="col-12"> {{ $grados_academicos }} </div>
        @endisset


        {{--


        @isset($documentos_curriculares)
        <h4 class="col-md-9 my-4"><strong> Documentos curriculares </strong></h4>
        
        <div class="col-12"> 
            {{ $documentos_curriculares }} 
        
            <button type="submit" class="d-block my-3 btn btn-primary"> Guardar </button>
        </div>
        @endisset
--}}
    </div>
</form>