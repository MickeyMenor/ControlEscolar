<?php

namespace App\Http\Controllers;


use App\Http\Requests\ConfirmInterviewRequest;
use App\Http\Requests\DeleteInterviewRequest;
use App\Http\Requests\NewInterviewUserRequest;
use App\Http\Requests\RemoveInterviewUserRequest;
use App\Http\Requests\ReopenInterviewRequest;
use App\Http\Requests\StoreInterviewRequest;
use App\Http\Resources\CalendarResource;
use App\Http\Resources\InterviewProgram\InterviewProgramResource;
use App\Models\AcademicProgram;
use App\Models\Interview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Archive;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
//Presencial
use App\Mail\SendMeeatingInformation;
use App\Mail\SendMeeatingInformationProfesor;
//Zoom
use App\Mail\SendZoomMeeatingInformation;
use App\Mail\SendZoomMeeatingInformationProfesor;

use App\Helpers\MiPortalService;
use App\Mail\UpdateDocumentsInterview;
use App\Models\Announcement;
use App\Models\ArchiveRequiredDocument;
use App\Models\RequiredDocument;
use App\Models\User;

class InterviewController extends Controller
{
    public $alumno;
    /**
     * Devuelve la vista del calendario.
     * 
     * @param Request $request
     */
    public function calendario(Request $request)
    {
        try{
            $calendar_resource = new CalendarResource(AcademicProgram::WithInterviewEagerLoads()->get());
        }catch(\Exception $e){
            return new JsonResponse(['message' => 'Error al cargar el recurso calendario.'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        return view('entrevistas.index')->with($calendar_resource->toArray($request));
    }

    public function calendario1(Request $request)
    {   
        try{
            $calendar_resource = new CalendarResource(AcademicProgram::WithInterviewEagerLoads()->get());
        }catch(\Exception $e){
            return new JsonResponse(['message' => 'Error al cargar el recurso calendario.'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
        return AcademicProgram::WithInterviewEagerLoads()->get();
    }

    public function calendario2(Request $request)
    {
        try {
            $calendar_resource = new CalendarResource(AcademicProgram::WithInterviewEagerLoads()->get());
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al cargar el recurso calendario.'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        // dd($calendar_resource);
        return $calendar_resource;
    }

    // public function calendario3(Request $request)
    // {

    //     $service = new MiPortalService;
    //     $user_data_collect =  $service->miPortalGet('api/usuarios', ['filter[id]' => 291395])->collect();

    //     return $user_data_collect;
    // }


    /**
     * Devuelve la vista del programa de entrevistas.
     * 
     * @param Request $request
     */

    public function programa(Request $request)
    {
        // Administracion
        // Control escolar - Ver todo
        // Comite academico - Ver promedios
        // Cordinador - Ver todo / no modificar / ver promedio
        // Profesor nucleo basico
        // Aspirante 

        $interviews = $request->user()->hasAnyRole(['admin', 'control_escolar', 'comite_academico', 'coordinador']) === true
            ?   Interview::select('*')->where('confirmed', 1)
            :   $request->user()->interviews()->where('confirmed', 1);

        $interview_program_resource = new InterviewProgramResource($interviews);

        // return $interview_program_resource->toArray($request);

        return view('entrevistas.show', $interview_program_resource->toArray($request));
    }

    public function programa2(Request $request)
    {
        // Administracion
        // Control escolar - Ver todo
        // Comite academico - Ver promedios
        // Cordinador - Ver todo / no modificar / ver promedio
        // Profesor nucleo basico
        // Aspirante 

        $interviews = $request->user()->hasAnyRole(['admin', 'control_escolar', 'comite_academico', 'coordinador']) === true
            ?   Interview::select('*')->where('confirmed', 1)
            :   $request->user()->interviews()->where('confirmed', 1);

        $interview_program_resource = new InterviewProgramResource($interviews);

        return $interview_program_resource->toArray($request);

        // return view('entrevistas.show', $interview_program_resource->toArray($request));
    }

    /**
     * Sets the available announcements.
     *
     * @return void
     */
    private function setInterviewAcademicAreas(&$interview)
    {
        # Llena el modelo con 5 ??reas acad??micas vac??as.
        $empty_areas = array_fill(0, 5, [
            'name' => '??rea acad??mica disponible',
            'professor_name' => false
        ]);

        # Asigna de forma inicial, ning??n ??rea acad??mica.
        unset($interview->academicAreas);
        $interview->academic_areas = $empty_areas;
    }

    /**
     * Agenda una nueva entrevista.
     * 
     * @param Request $request
     */
    public function nuevaEntrevista(StoreInterviewRequest $request)
    {
        // try{
        //     $interview_model= Interview::create([
        //         'date' => $request->date,
        //         'room_id' => $request->room_id,
        //         'start_time' => $request->start_time,
        //         'end_time' => $request->end_time,
        //         'confirmed' => 0
        //     ]);

        //     $interview_model->save();
        // }catch(\Exception $e){
        //     return new JsonResponse(['Error' => ' Fall?? la creaci??n del modelo de entrevista','message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        // }

        // try{
        //     DB::table('interview_user')->insert([
        //         'interview_id' => $interview_model->id,
        //         'user_type' => $request->user_type,
        //         'user_id' => $request->user_id
        //     ]);

        // }catch(\Exception $e){
        //     return new JsonResponse(['Error' => 'Fall?? la creacion del pivote' , 'message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        // }

        try {
            $interview_model = Interview::create($request->safe()->except('user_id', 'user_type'));
            $interview_model->users()->attach($request->user_id, ['user_type' => $request->user_type]);  // Agregamos al profesor a la entrevista
            $interview_model->load(['users.roles:name', 'appliant']);
            $interview_model->confirmed = false;
            $interview_model->save();

            # Obtiene los datos del profesor que otorg?? la carta de intenci??n, con base a la informaci??n de mi portal.
            $professor = $interview_model->intentionLetterProfessor->first();
        } catch (\Exception $e) {
            return new JsonResponse(['Error' => 'Fall?? al insertar datos de la entrevista', 'message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            // ! This part here doesn't work in production, just for testing
            // $miPortal_worker = collect($request->session()->get('workers'))->firstWhere('id', $professor->id);
            // $professor_name = implode(' ', [$miPortal_worker['name'], $miPortal_worker['middlename'], $miPortal_worker['surname']]);
            // !

            // * Retrieves the worker's information
            $service = new MiPortalService;
            $miPortal_worker =  $service->miPortalGet('api/usuarios', ['filter[id]' => $professor->id])->collect();
            $miPortal_worker = $miPortal_worker[0];
            $professor_name = implode(' ', [
                $miPortal_worker['name'] ?? '',
                $miPortal_worker['middlename'] ?? '',
                $miPortal_worker['surname']  ?? ''
            ]);

            // ! This part here doesn't work on production, just for testing
            // $miPortal_user = collect($request->session()->get('appliants'))->firstWhere('id', $request->user_id);
            // $name = implode(' ', [$miPortal_user['name'], $miPortal_user['middlename'], $miPortal_user['surname']]);
            // !

            // * Retrieves the appliant's information 
            # Obtiene los datos del postulante, con base a la informaci??n de mi portal.
            $miPortal_user =  $service->miPortalGet('api/usuarios', ['filter[id]' => $request->user_id])->collect();
            $miPortal_user = $miPortal_user[0];

            $name = implode(' ', [
                $miPortal_user['name'] ?? '',
                $miPortal_user['middlename'] ?? '',
                $miPortal_user['surname']  ?? ''
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['Error' => 'Fall?? al traer los datos del portal', 'message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            # Devuelve en la respuesta, los datos de los usuarios.
            $appliant = $interview_model->appliant->first()->toArray();
            $appliant['name'] = $name;

            # Sobreescribe con informaci??n nueva.
            unset($interview_model->appliant);
            unset($interview_model->intentionLetterProfessor);

            $interview_model->appliant = $appliant;
            $interview_model->intention_letter_professor = [
                'id' => $miPortal_worker['id'],
                'type' => $miPortal_worker['user_type'],
                'name' => $professor_name
            ];

            # Establece las ??reas acad??micas de la entrevista.
            $this->setInterviewAcademicAreas($interview_model);
        } catch (\Exception $e) {
            return new JsonResponse(['Error' => 'Fall?? al traer los datos del portal', 'message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($interview_model, JsonResponse::HTTP_CREATED);
    }

    /**
     * A??ade a un profesor a la entrevista.
     * 
     * @param Request $request
     */
    public function newInterviewUser(NewInterviewUserRequest $request)
    {
        # Obtiene el modelo de la entrevista.
        $interview_model = Interview::find($request->safe()->interview_id);
        $interview_model->users()->attach($request->user_id, ['user_type' => $request->user_type]);

        # Obtiene al postulante.
        $appliant = $interview_model->appliant->first();
        $archive = $appliant->latestArchive;

        $interview_model->evaluationRubrics()->create([
            'archive_id' => $archive->id,
            'user_id' => $request->user_id,
            'user_type' => $request->user_type
        ]);

        return new JsonResponse($request->user()->academicAreas->first(), JsonResponse::HTTP_CREATED);
    }

    /**
     * A??ade a un profesor a la entrevista.
     * 
     * @param Request $request
     */
    public function removeInterviewUser(RemoveInterviewUserRequest $request)
    {
        # Registra al profesor a la entrevista.
        $interview_model = Interview::find($request->safe()->interview_id);
        $interview_model->users()->detach($request->user_id);
        $interview_model->evaluationRubrics()
            ->where('user_id', $request->user_id)
            ->where('user_type', $request->user_type)
            ->delete();

        return new JsonResponse(['message' => '??Registro a entrevista cancelado exitosamente!'], JsonResponse::HTTP_CREATED);
    }

    /**
     * Confirma una entrevista.
     * 
     * @param Request $request
     */
    public function confirmInterview(ConfirmInterviewRequest $request)
    {
        $interview2 = Interview::findorFail($request->id);
        $int_room = Room::findorFail($interview2->room_id);

        // foreach ($interview2->evaluationRubrics as $rubric) {
        //     $rubric->getAverageScoreBasicConcepts();
        //     $rubric->getAverageScoreAcademicConcepts();
        //     $rubric->getAverageScoreResearchConcepts();
        //     $rubric->getAverageWorkingExperienceConcepts();
        //     $rubric->getAverageWorkingPersonalAttributesConcepts();
        // }

        try {
            /**Checar si el url es null por si se reabrio el interview */
            if ($interview2->url == null) {

                //Crear url para la sesion de zoom//
                // $ResponseMeating = app(ZoomController::class)->store($interview2);
                $ResponseMeating = null;
                // Entrevista presencial o virtual
                if (str_contains($int_room->site, 'Zoom') ? true : false) {
                    //Crear url para la sesion de zoom//
                    try{
                        $ResponseMeating = app(ZoomController::class)->store($interview2);
                        $join_url = $ResponseMeating['join_url'];
                    }catch(\Exception $e){
                        $join_url = "En linea por Zoom";
                    }
                    // Actualizacion bandera - entrevista cerrada
                    Interview::where('id', $request->id)->update(['confirmed' => true, 'url' => $join_url]);
                } else {
                    Interview::where('id', $request->id)->update(['confirmed' => true, 'url' => 'Presencial']);
                }

                // $Trabajadores = $request->session()->get('workers'); NO FUNCIONA EN PRODUCCI??N :,V            

                // Carga el achivo del postulante para obtener el programa academico
                // $service = new MiPortalService;
                $archive = null;
                $postulante_data = null;
                $url_ContactoAA = asset('/storage/logos/rtic.png');

                foreach ($interview2->users as $key => $User) {
                    if ($User->user_type == "externs" || $User->user_type == "students" || $User->user_type == "Comunidad AA") {
                        $this->alumno = $User;
                        $archive = Archive::where('user_id', $this->alumno->id)->first();
                        //Carga programa academico
                        $archive->loadMissing([
                            'announcement.academicProgram',
                            'appliant',
                        ]);
                        $academic_program =  $archive->announcement->academicProgram;

                        // Extraemos los datos del postulante del portal
                        // $postulante_data =  $service->miPortalGet('api/usuarios', ['filter[id]' => $this->alumno->id])->collect();
                        // $postulante_data = $postulante_data[0];

                        // Extraemos los datos del postulante del portal
                        $postulante_data = DB::connection('portal_real')->select('select * from users where id = :id', ['id' => $this->alumno->id]);         // $miPortal_user[0]->id;
                        $postulante_data = $postulante_data[0];
                    }
                }

                // Envio de correos
                foreach ($interview2->users as $key => $User) {
                    if ($academic_program != null) {
                        // Filtro para envio de correos segun dependencia del programa academico
                        $servicio_correo = 'smtp';
                        // IMAREC
                        if (strcmp($academic_program['alias'], 'imarec') === 0) {
                            $url_LogoAA = asset('/storage/logos/IMAREC.png');
                            $servicio_correo = 'smtp_imarec';
                        } else {
                            // MCA, MCA doble titulacion, Doctorado
                            $url_LogoAA = asset('/storage/logos/PMPCA.png');
                            $servicio_correo = 'smtp_pmpca';
                        }

                        // CC Mail
                        $mail_academic_program = 'rtic.ambiental@uaslp.mx';

                        switch ($servicio_correo) {
                            case 'smtp_imarec':
                                $mail_academic_program = 'imarec.escolar@uaslp.mx';
                                break;
                            case 'smtp_pmpca':
                                $mail_academic_program = 'pmpca@uaslp.mx';
                                break;
                            default:
                                $mail_academic_program = 'rtic.ambiental@uaslp.mx';
                                break;
                        }

                        // Obtenemos el correos del participante
                        $user_data = DB::connection('portal_real')->select('select * from users where id = :id', ['id' => $User->id]);         // $miPortal_user[0]->id;
                        $user_data = $user_data[0];
                        $user_mail = $user_data->email;

                        // $user_data =  $service->miPortalGet('api/usuarios', ['filter[id]' => $User->id])->collect();
                        // $user_data = $user_data[0];
                        // $user_mail = $user_data['email'];

                        if ($User->user_type == "externs" || $User->user_type == "students" || $User->user_type == "Comunidad AA") {
                            $this->alumno = $User;
                            // Envio de correo dependidiendo modalidad de la entrevista
                            // if (str_contains($int_room->site, 'Zoom') ? true : false) {
                                // Mail::mailer($servicio_correo)->to($user_mail)
                                // ->cc($mail_academic_program)  
                                // ->send(new SendZoomMeeatingInformation($ResponseMeating, $user_data, $archive->announcement->academicProgram, $request->room, $archive->id, $url_LogoAA));
                            //     Mail::mailer($servicio_correo)->to('ulises.uudp@gmail.com')->send(new SendZoomMeeatingInformation($ResponseMeating, $user_data, $archive->announcement->academicProgram, $request->room, $archive->id,$url_LogoAA));
                            // } else {
                                // Mail::mailer($servicio_correo)  
                                // ->cc($mail_academic_program)
                                // ->to($user_mail)->send(new SendMeeatingInformation($interview2, $user_data, $archive->announcement->academicProgram, $request->room, $archive->id,$url_LogoAA));
                                // Mail::mailer($servicio_correo)->to('ulises.uudp@gmail.com')->send(new SendMeeatingInformation($interview2, $user_data, $archive->announcement->academicProgram, $request->room, $archive->id,$url_LogoAA));
                            // }
                            // Mail::mailer('smtp')->to($User->email)->send(new SendMeeatingInformation($ResponseMeating, $User, $archive->announcement->academicProgram['name'], $request->room));
                            try {
                                $required_documents = RequiredDocument::where('type', 'interview')->get();
                                $ids_rd = [];
                                foreach ($required_documents as $rd) {
                                    array_push($ids_rd, $rd->id);
                                }
                                $archive->interviewDocuments()->detach($ids_rd);
                                $archive->interviewDocuments()->attach($ids_rd, ['location' => null]);
                            } catch (\Exception $e) {
                                return new JsonResponse($e->getMessage(), JsonResponse::HTTP_BAD_GATEWAY); //Ver info archivos en consola
                            }
                        } else if ($User->user_type == "workers") {
                            $this->alumno = $User;
                            /**Obtener al trabajador inscrito en la entrevista */
                            // Envio de correo dependidiendo modalidad de la entrevista
                            // if (str_contains($int_room->site, 'Zoom') ? true : false) {
                                // Mail::mailer($servicio_correo)->to($user_mail)->send(new SendZoomMeeatingInformationProfesor($ResponseMeating, $user_data, $archive->announcement->academicProgram,  $request->room, $postulante_data, $url_LogoAA));
                                // Mail::mailer($servicio_correo)->to('ulises.uudp@gmail.com')->send(new SendZoomMeeatingInformationProfesor($ResponseMeating, $user_data, $archive->announcement->academicProgram,  $request->room, $postulante_data,$url_LogoAA));
                            // } else {
                                // Mail::mailer($servicio_correo)->to($user_mail)->send(new SendMeeatingInformationProfesor($interview2, $user_data, $archive->announcement->academicProgram,  $request->room, $postulante_data,$url_LogoAA));
                            //     Mail::mailer($servicio_correo)->to('ulises.uudp@gmail.com')->send(new SendMeeatingInformationProfesor($interview2, $user_data, $archive->announcement->academicProgram,  $request->room, $postulante_data,$url_LogoAA));
                            // }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al enviar correos de entrevista', 'error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['message' => 'Se ha confirmado la entrevista'], JsonResponse::HTTP_OK);
    }


    public function SendMailUpdateOnlyDocumentsForInterview(Request $request)
    {

        $request->validate([
            'announcement_id' => ['required', 'numeric', 'exists:announcements,id'],
        ]);

        try {
            $archives = Archive::where('announcement_id', $request->announcement_id)->where(function ($q) {
                $q->where('status', 7)
                    ->orWhere('status', 5);
            })->get();
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al enviar correos de entrevista', 'error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }


        try {
            if (count($archives)) {
                foreach ($archives as $archive1) {

                    $archive = Archive::where('id', $archive1->id)->first();

                    //Carga programa academico
                    $archive->loadMissing([
                        'announcement.academicProgram',
                        'appliant',
                    ]);

                    $academic_program =  $archive->announcement->academicProgram;
                    $User = $archive->appliant;
                    $url_LogoAA = asset('/storage/logos/rtic.png');


                    if ($academic_program != null) {
                        $servicio_correo = 'smtp';
                        // IMAREC
                        if (strcmp($academic_program['alias'], 'imarec') === 0) {
                            $servicio_correo = 'smtp_imarec';
                            $url_LogoAA = asset('/storage/logos/IMAREC.png');

                        } else {
                            // MCA, MCA doble titulacion, Doctorado
                            $servicio_correo = 'smtp_pmpca';
                            $url_LogoAA = asset('/storage/logos/PMPCA.png');
                        }

                        try {

                            $service = new MiPortalService;
                            $user_data_collect =  $service->miPortalGet('api/usuarios', ['filter[id]' => $archive->appliant->id])->collect();
                            if (sizeof($user_data_collect) > 0) {
                                $user_data = $user_data_collect[0];
                            }
                        } catch (\Exception $e) {
                            return new JsonResponse(['message' => 'No se extrajo la informaci??n del postulante', 'errors' => $e->getMessage()],JsonResponse::HTTP_CONFLICT); //Ver info archivos en consola
                        }

                        try {
                            $required_documents = RequiredDocument::where('type', 'interview')->get();
                            $ids_rd = [];
                            foreach ($required_documents as $rd) {
                                array_push($ids_rd, $rd->id);
                            }

                            $archive->interviewDocuments()->detach($ids_rd);
                            $archive->interviewDocuments()->attach($ids_rd, ['location' => null]);
                        } catch (\Exception $e) {
                            return new JsonResponse(['message' => 'No se pudieron crear las relaciones a los archivos', 'errors' => $e->getMessage()], JsonResponse::HTTP_BAD_GATEWAY); //Ver info archivos en consola
                        }

                        try {
                            if ($user_data != null) {
                                // Mail::mailer($servicio_correo)->to('ulises.uudp@gmail.com')->send(new UpdateDocumentsInterview($user_data, $academic_program, $archive->id));
                                Mail::mailer($servicio_correo)->to($user_data['email'])->send(new UpdateDocumentsInterview($user_data, $academic_program, $archive->id,$url_LogoAA ));
                            }
                        } catch (\Exception $e) {
                            return new JsonResponse(['message' => 'No se pudieron crear las relaciones a los archivos', 'errors' => $e->getMessage()], JsonResponse::HTTP_BAD_GATEWAY); //Ver info archivos en consola
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al enviar correos de entrevista', 'error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['message' => 'OK'], JsonResponse::HTTP_OK);
    }

    public function updateArchiveInterviewDocument(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric', 'exists:archives,id'],
            'requiredDocumentId' => ['required', 'numeric', 'exists:required_documents,id'],
            'file' => ['required']
        ]);

        try {
            $archive = Archive::find($request->archive_id);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al buscar archivo'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        // Create the route
        try {
            # Archivo de la solicitud
            $ruta = $request->file('file')->storeAs(
                'archives/' . $request->archive_id . '/interviewDocuments',
                $request->requiredDocumentId . '.pdf'
            );
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'La ruta no se puede generar'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $archive->interviewDocuments()->detach($request->requiredDocumentId);
            $archive->interviewDocuments()->attach($request->requiredDocumentId, ['location' => $ruta]);
            $required_document = ArchiveRequiredDocument::where([
                'required_document_id' => $request->requiredDocumentId,
                'archive_id' => $request->archive_id
            ])->first();
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'No se puede generar la relacion'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        /**Problema al regresar el json, marca un erro en la consulta */
        return new JsonResponse(
            ['location' => $required_document->location],
            JsonResponse::HTTP_CREATED
        );
    }


    /**
     * Confirma una entrevista.
     * 
     * @param Request $request
     */
    public function reopenInterview(ReopenInterviewRequest $request)
    {
        Interview::where('id', $request->id)->update(['confirmed' => false]);

        return new JsonResponse(['message' => 'Se ha vuelto a abrir la entrevista'], JsonResponse::HTTP_OK);
    }

    /**
     * Elimina una entrevista.
     * 
     * @param Request $request
     */
    public function deleteInterview(DeleteInterviewRequest $request)
    {
        Interview::where('id', $request->id)->delete();

        return new JsonResponse(['message' => 'Entrevista eliminada exitosamente'], JsonResponse::HTTP_OK);
    }

    // Documents for interview

    public function documentsForInterviewShow(Request $request, $archive_id)
    {
        // $request->validate([
        //     'archive_id' => ['required', 'numeric', 'exists:archives,id'],
        // ]);


        try {
            $archive = Archive::where('id', intval($archive_id))->first();
            //Carga modelos de archivo
            $archive->loadMissing([
                'appliant',
                'announcement.academicProgram',
                'personalDocuments',
                'recommendationLetter',
                'myRecommendationLetter',
                'entranceDocuments',
                'intentionLetter',
                'academicDegrees.requiredDocuments',
                'appliantLanguages.requiredDocuments',
                'appliantWorkingExperiences',
                'scientificProductions.authors',
                'humanCapitals',
                'interviewDocuments',
            ]);

            $academic_program = $archive->announcement->academicProgram;
            $appliant = $archive->appliant;
            $img = 'PMPCA-SUPERIOR.png';

            if ($academic_program->name === 'Maestr??a en ciencias ambientales') {
                $img = 'PMPCA-SUPERIOR.png';
            } else if ($academic_program->name === 'Maestr??a en ciencias ambientales, doble titulaci??n') {
                $img = 'ENREM-SUPERIOR.png';
            } else if ($academic_program->name === 'Maestr??a Interdisciplinaria en ciudades sostenibles') {
                $img = 'IMAREC-SUPERIOR.png';
            }

            $header_academic_program =          asset('storage/headers/' . $img);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'No se pudo extraer la informacion del expediente'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        // $user = User::where('id',$archive->appliant->id)->with('archives')->get();

        // // $misarchivos= $user->archives()->update();




        // dd($archive);

        return view('entrevistas.showUpdateDocuments')
            ->with('archive', $archive)
            ->with('appliant', $appliant)
            ->with('academic_program', $academic_program)
            ->with('header_academic_program', $header_academic_program);
    }
}
