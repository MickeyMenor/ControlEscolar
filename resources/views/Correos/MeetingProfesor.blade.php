<!DOCTYPE html>
<style>
    table,
    th,
    td {
        border: 1px solid black;
        border-collapse: collapse;
        width: auto;
    }
</style>

<body>
    <main class="container-fluid">
        <div class="row  justify-content-center">
            <div class="col-12">
                <img src="{{ asset('imagenes/logod.png') }}">
                {{-- <img src="{{ $message->embed('imagenes/logod.png') }}"> --}}
            </div>

            <div class="col-12">
                <p style="text-align:center;">
                    <strong>
                        Información de entrevista para ingreso a
                        <br>
                        {{ $academic_program['name'] }}
                        <br>
                        Convocatoria 2022-02
                    </strong>
                </p>

            </div>

            <div class="row justify-content-end" style="text-align: right;">
                San Luis Potosí, S.L.P,&nbsp;
                {{ Carbon\Carbon::parse(Carbon\Carbon::now())->locale('es')->isoFormat('dddd DD MMMM YYYY') }}
            </div>

            <div class="container my-4">
                <p>Estimado Profesor {{ $Profesor['middlename'] . ' ' . $Profesor['surname'] . ' ' . $Profesor['name'] }}</p>
                <p>Agredecemos su partición en la entrevista de:</p>
                <table class="table" style="max-width: 800px; margin: 0 auto;">

                    <tbody>
                        <tr>
                            <td scope="row">Nombre del aspirante</td>
                            <td>{{ $Student['middlename'] . ' ' . $Student['surname'] . ' ' . $Student['name'] }}</td>

                        </tr>

                        <tr>
                            <td>Día de la entrevista</td>
                            <td>{{ Carbon\Carbon::parse($Interview->date)->locale('es')->isoFormat('DD MMMM YYYY') }}
                            </td>
                        </tr>
                        <tr>
                            <td>Horario de la entrevista</td>
                            <td>{{ $Interview->start_time }}-{{ $Interview->end_time }}</td>
                        </tr>
                        <tr>
                            <td>Lugar</td>
                            <td>{{ $Room }}</td>
                        </tr>
                        <tr>
                            <td>Modalidad de la entrevista</td>
                            <td>Presencial</td>
                        </tr>
                    </tbody>
                </table>

                <div class="row mt-2">
                    <p><strong>Indicaciones:</strong></p>

                    <ul>
                        <li> Acceder a <a href="https://ambiental.uaslp.mx/Bienvenida" target="_blank"
                                rel="noopener noreferrer">https://ambiental.uaslp.mx/Bienvenida</a></li>
                        <li> En el menú superior <strong>Entrevista</strong> , submenú <strong>Programa de
                                entrevista</strong> podrá visualizar el expediente del candidato, ensayo así como la rúbrica
                        </li>
    
                    </ul>
    
                    <p>Saludos cordiales, <br>
                        M.I Maricela Rdz. Díaz de León <br>
                        Coordinación Educativa <br>
                        Agenda Ambiental de la Universidad Autónoma de San Luis Potosí <br>
                        Ave. Manuel Nava 201, 2do piso <br>
                        Zona Universitaria (Entre Facultad de Estomatología y Oficina de Finanzas)78210 San Luis Potosí,
                        México. <br>
                        Tels: (444) 8262439 y 2435
                    </p>
                </div>
            </div>
        </div>
    </main>

    <footer class="container-fluid p-2">
        <div class="row">
            <div class="col-12">
                <img src="{{ $url_ContactoAA }}" alt="Contacto Agenda Ambiental" style="width: 100;">
            </div>
        </div>
    </footer>
</body>
