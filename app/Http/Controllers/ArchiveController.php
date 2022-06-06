<?php

namespace App\Http\Controllers;

# Peticiones
use App\Http\Requests\{
    AddScientificProductionAuthorRequest,
    UpdateAcademicDegreeRequest,
    UpdateAppliantLanguageRequest,
    UpdateHumanCapitalRequest,
    UpdateScientificProductionAuthorRequest,
    UpdateScientificProductionRequest,
    UpdateWorkingExperienceRequest,
    StoreRecommendationLetter,
    UpdateMailRecommendationLetter,
};

use Illuminate\Validation\Rule;
# Resources (respuestas en formato JSON)
use Illuminate\Support\Str;
use App\Services\PayUService\Exception;
use App\Http\Resources\AppliantArchive\ArchiveResource;
use App\Mail\SendRecommendationLetter;
use Illuminate\Support\Facades\Auth;
//convertir blade a pdf
use Barryvdh\DomPDF\Facade\Pdf;

# Modelos
use App\Models\{
    AcademicArea,
    AcademicDegree,
    AcademicProgram,
    AppliantLanguage,
    Archive,
    ArchiveRequiredDocument,
    Author,
    CustomParameter,
    HumanCapital,
    MyRecommendationLetter,
    Parameter,
    ScientificProduction,
    User,
    WorkingExperience,
    RecommendationLetter,
    RequiredDocument,
    ScoreParameter,
};
use FontLib\Table\Type\os2;
use Illuminate\Auth\Events\Validated;
# Clases auxiliares de Laravel.
use Illuminate\Http\{
    JsonResponse,
    Request,
    File
};
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{
    DB,
    Schema,
    Cache,
    Mail,
    Storage
};
use Nette\Utils\Json;
use App\Helpers\MiPortalService;
use App\Mail\SendUpdateDocuments;
use Database\Factories\ArchiveFactory;
# Clases de otros paquetes.
use Spatie\QueryBuilder\{
    AllowedFilter,
    QueryBuilder
};

class ArchiveController extends Controller
{
    /**
     * Web service de Mi portal.
     * 
     * @var \App\Helpers\MiPortalService
     */
    private $service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->service = new MiPortalService;
        parent::__construct();
    }

    public function getRol(Request $request)
    {
        $request->validate([
            'viewer_id' => ['required', 'numeric'],
        ]);
        //             $res = DB::table('articles')->where('scientific_production_id', 'John')->first('name');

        $isProffesor = $request->session()->get('user')->hasRole('profesor_nb');

        $roles_id = DB::table('model_has_roles')->where('model_id', $request->viewer_id);
        $viewer_roles = [];

        //Get the names
        foreach ($roles_id as $rol_id) {
            $rol =  DB::table('roles')->where('id', $rol_id->role_id);
            if ($rol != null) {
                array_push($viewer_roles, $rol->name);
            }
        }

        return new JsonResponse([
            'roles' => $viewer_roles, 'isProfessor' => $isProffesor
        ], 200);
    }

    /* -------------------------- DASHBOARD DEL APLICANTE --------------------------*/

    public function index(Request $request)
    {
        return view('postulacion.index')
            ->with('user', $request->user())
            ->with('academic_programs', AcademicProgram::with('latestAnnouncement')->get());
    }

    /* -------------------------- ADMIN VIEW FUNCTIONS --------------------------*/

    public function archivesProfessor(Request $request)
    {

        try {

            $archives = QueryBuilder::for(Archive::class)
                ->with('appliant')
                ->allowedIncludes(['announcement'])
                ->allowedFilters([
                    AllowedFilter::exact('announcement.id'),
                ])
                ->get();
        } catch (\Exception $e) {
            return new JsonResponse(['message' => "No existen archivos para las fechas y modalidad indicada"], 502);
        }

        $archives_searched = [];


        foreach ($archives as $k => $archive) {

            try {
                $user_data_collect =  $this->service->miPortalGet('api/usuarios', ['filter[id]' => $archive->appliant->id])->collect();
            } catch (\Exception $e) {
                return new JsonResponse($e->getMessage(), 200); //Ver info archivos en consola
            }

            if (sizeof($user_data_collect) > 0) {
                //ArchiveResource create $user_data_collect[0];
                $user_data = $user_data_collect[0];
                //Se guarda el nombre del usuario en el modelo
                $name =  strtoupper($user_data['name'] . ' ' . $user_data['middlename'] . ' ' . $user_data['surname']);
                $nameUpper = strtoupper($request->student_name);

                if (strcmp($name, $nameUpper) == 0) {
                    array_push($archives_searched, $archive);
                }
            }
        }
        return ArchiveResource::collection($archives_searched);
    }

    public function archives(Request $request)
    {
        try {
            $startDate = Carbon::createFromFormat('Y-m-d', $request->date_from)->startOfDay();
            $endDate = Carbon::createFromFormat('Y-m-d', $request->date_to)->endOfDay();
        } catch (\Exception $e) {
            return new JsonResponse('Error al crear la fecha', 200);
        }

        // return new JsonResponse("No existen archivos para las fechas y modalidad indicada Desde: " .$request->date_from .' -- Hasta: '.$request->date_from, 502);

        try {

            $archives = QueryBuilder::for(Archive::class)
                ->with('appliant')
                ->allowedIncludes(['announcement'])
                ->allowedFilters([
                    AllowedFilter::exact('announcement.id'),
                ])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();
        } catch (\Exception $e) {
            return new JsonResponse(['message' => "No existen archivos para las fechas y modalidad indicada"], 502);
        }

        //Comprobar que todos los modelos de appliant tengan la información necesaria
        //Caso contrario insertar información en modelos

        foreach ($archives as $k => $archive) {
            //El postulante no tiene toda la información
            if (!$archive->appliant->name) {

                try {
                    $user_data_collect =  $this->service->miPortalGet('api/usuarios', ['filter[id]' => $archive->appliant->id])->collect();
                } catch (\Exception $e) {
                    return new JsonResponse($e->getMessage(), 200); //Ver info archivos en consola
                }

                if (sizeof($user_data_collect) > 0) {

                    //ArchiveResource create $user_data_collect[0];
                    $user_data = $user_data_collect[0];
                    //Se guarda el nombre del usuario en el modelo
                    $archive->appliant->setAttribute('name', $user_data['name'] . ' ' . $user_data['middlename'] . ' ' . $user_data['surname']);

                    //Eliminar mi archivo para produccion
                    //Descomentar en local
                    //Quitas a Ulises y a Rodrigo
                    if ($archive->appliant->id == 298428 || $archive->appliant->id == 245241) {
                        unset($archives[$k]);
                    }
                } else {
                    //No existe aplicante por lo que no se podra ver expediente
                    $archive->appliant->setAttribute('name', 'Usuario invalido');
                    $archive->id = -1;

                    //Elimina al usuario invalido de la lista
                    unset($archives[$k]);
                }
            }
        }
        // return new JsonResponse($archives, 200); //Ver info archivos en consola
        return ArchiveResource::collection($archives);
    }

    public function updateStatusArchive(Request $request)
    {

        try {
            $request->validate([
                'archive_id' => ['required', 'numeric'],
                'status' => ['required', 'numeric']
            ]);

            $update = Archive::where('id', $request->archive_id)->update(['status' => $request->status]);

            if ($update <= 0) {
                return new JsonResponse(['message' => 'No se pudo actualizar el estado del expediente'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al extraer y modificar el estado del expediente'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }


        return new JsonResponse(['message' => 'El expediente se ha cerrado'], JsonResponse::HTTP_ACCEPTED);
    }

    public function sentEmailToUpdateDocuments(Request $request)
    {
        try {
            $request->validate([
                'selected_personalDocuments.*' =>   ['required', 'numeric', 'exists:required_documents,id'],
                'selected_entranceDocuments.*' =>   ['required', 'numeric', 'exists:required_documents,id'],
                'selected_academicDocuments.*' =>   ['required'],
                'selected_languageDocuments.*' =>   ['required'],
                'selected_workingDocuments.*'  =>   ['required'],
                'instructions' => ['required', 'nullable', 'string', 'max:225'],
                'academic_program' => ['required'],
                'archive_id' => ['required', 'numeric', 'exists:archives,id'],
                'user_id' => ['required', 'numeric', 'exists:users,id']
            ]);

            $appliant = null;
            $url_LogoAA = asset('/storage/headers/logod.png');
            $url_ContactoAA = asset('/storage/logos/rtic.png');

            try {
                $user_data_collect =  $this->service->miPortalGet('api/usuarios', ['filter[id]' => $request->user_id])->collect();

                if (sizeof($user_data_collect) > 0) {
                    $appliant = $user_data_collect[0];
                }
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'El usuario no existe'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
            }
            // return new JsonResponse($appliant['email'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);

            // Encuentra el nombre de cada documento
            $name_documents = [];
            foreach ($request->selected_personalDocuments as $doc_id) {
                $doc = RequiredDocument::where('id', $doc_id)->first();
                array_push($name_documents, $doc->name);
            }
            foreach ($request->selected_entranceDocuments as $doc_id) {
                $doc = RequiredDocument::where('id', $doc_id)->first();
                array_push($name_documents, $doc->name);
            }
            foreach ($request->selected_academicDocuments as $doc_id) {
                $doc = RequiredDocument::where('id', $doc_id)->first();
                array_push($name_documents, $doc->name);
            }
            foreach ($request->selected_languageDocuments as $doc_id) {
                $doc = RequiredDocument::where('id', $doc_id)->first();
                array_push($name_documents, $doc->name);
            }
            foreach ($request->selected_workingDocuments as $doc_id) {
                $doc = RequiredDocument::where('id', $doc_id)->first();
                array_push($name_documents, $doc->name);
            }


            try {
                Mail::to("ulises.uudp@gmail.com")->send(new SendUpdateDocuments(
                    $request->selected_personalDocuments,
                    $request->selected_entranceDocuments,
                    $request->selected_academicDocuments,
                    $request->selected_languageDocuments,
                    $request->selected_workingDocuments,
                    $name_documents,
                    $request->instructions,
                    $appliant,
                    $request->academic_program,
                    $request->archive_id,
                    $url_LogoAA,
                    $url_ContactoAA
                ));
                // Mail::to("ulises.uudp@gmail.com")->send(new SendUpdateDocuments($request->selected_etiquetas,$request->instructions,$appliant, $request->academic_program, $request->archive_id,$url_LogoAA,$url_ContactoAA ));
                // Mail::to($appliant['email'])->send(new SendUpdateDocuments($request->selected_etiquetas,$request->instructions,$appliant, $request->academic_program, $request->archive_id,$url_LogoAA,$url_ContactoAA ));
            } catch (\Exception $e) {
                return new JsonResponse(['message' => 'No se pudo enviar el correo'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al extraer y modificar el estado del expediente'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['message' => 'Exito'], JsonResponse::HTTP_ACCEPTED);
    }

    public function showDocumentsFromEmail(Request $request, $archive_id, $personal_documents, $entrance_documents, $academic_documents, $language_documents, $working_documents)
    {

        try{
            $personal_documents_ids = json_decode($personal_documents);
            $entrance_documents_ids = json_decode($entrance_documents);
            $academic_documents_ids = json_decode($academic_documents);
            $language_documents_ids = json_decode($language_documents);
            $working_documents_ids  = json_decode($working_documents);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'No se pudo decodificar todo'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
       

        // dd($personal_documents_ids, $entrance_documents_ids, $academic_documents_ids,$language_documents_ids,$working_documents_ids);
        try {
            if ($archive_id != null) {
                $archive = Archive::where('id',intval($archive_id) )->first();
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
                    'humanCapitals'
                ]);
                $academic_program = $archive->announcement->academicProgram;
                $appliant = $archive->appliant;
                $img = 'PMPCA-SUPERIOR.png';
        
                if ($academic_program->name === 'Maestría en ciencias ambientales') {
                    $img = 'PMPCA-SUPERIOR.png';
                } else if ($academic_program->name === 'Maestría en ciencias ambientales, doble titulación') {
                    $img = 'ENREM-SUPERIOR.png';
                } else if ($academic_program->name === 'Maestría Interdisciplinaria en ciudades sostenibles') {
                    $img = 'IMAREC-SUPERIOR.png';
                }
        
                $header_academic_program =          asset('storage/headers/' . $img);
        
               
            }
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'No se pudo extraer informacion del archivo'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
       

        return view('postulacion.showUpdateDocuments')
            ->with('archive', $archive)
            ->with('appliant', $appliant)
            ->with('academic_program', $academic_program)
            ->with('header_academic_program', $header_academic_program)
            ->with('personal_documents_ids', $personal_documents_ids)
            ->with('entrance_documents_ids', $entrance_documents_ids)
            ->with('academic_documents_ids', $academic_documents_ids)
            ->with('language_documents_ids', $language_documents_ids)
            ->with('working_documents_ids', $working_documents_ids);
    }

    public function appliantFile_AdminView(Request $request, $archiveusr)
    {
        $archiveModel = Archive::where('id', $archiveusr)->first();
        if ($archiveModel == null) {
            return '<h1>No existe expediente para el postulante</h1>';
        }

        $archiveModel->loadMissing([
            // Cosas del aplicante
            'appliant',
            'announcement.academicProgram',
            // Documentos y secciones para expedinte
            'requiredDocuments',
            'personalDocuments',
            'curricularDocuments',
            'entranceDocuments',
            'intentionLetter',
            'recommendationLetter',
            'myRecommendationLetter',
            'academicDegrees.requiredDocuments',
            'appliantLanguages.requiredDocuments',
            'appliantWorkingExperiences',
            'scientificProductions.authors',
            'humanCapitals'
        ]);

        $academic_program = $archiveModel->announcement->academicProgram;

        //function that returns the info complete in the appliant model
        $appliant = $this->getDataFromPortalUser($archiveModel->appliant);

        #Set the image according to academic program
        //Doctorado en ciencias ambientales
        $img = 'DOCTORADO-SUPERIOR.png';

        if ($academic_program->name == 'Maestría en ciencias ambientales') {
            $img = 'PMPCA-SUPERIOR.png';
        } else if ($academic_program->name == 'Maestría en ciencias ambientales, doble titulación') {
            $img = 'ENREM-SUPERIOR.png';
        } else if ($academic_program->name == 'Maestría Interdisciplinaria en ciudades sostenibles') {
            $img = 'IMAREC-SUPERIOR.png';
        }

        $header_academic_program =          asset('storage/headers/' . $img);
        $location_letterCommitment_DCA =    asset('storage/DocumentoExtra/LetterCommitment/DCA.docx');
        $location_letterCommitment_MCA =    asset('storage/DocumentoExtra/LetterCommitment/MCA.docx');
        $location_letterCommitment_IMaREC = asset('storage/DocumentoExtra/LetterCommitment/IMaREC.docx');

        $letters_Commitment = [];
        array_push($letters_Commitment, $location_letterCommitment_MCA);    // [0] Maestria en ciencias ambientales, normal y doble titulacion
        array_push($letters_Commitment, $location_letterCommitment_DCA);    // [1] Doctorado en ciencias
        array_push($letters_Commitment, $location_letterCommitment_IMaREC); // [2]  ciudades sosteniles

        // dd($archiveModel);

        //Change to the view for admin
        return view('postulacion.show')
            ->with('archive', $archiveModel)
            ->with('appliant', $appliant)
            ->with('academic_program', $academic_program)
            ->with('recommendation_letters', $archiveModel->myRecommendationLetter)
            ->with('archives_recommendation_letters', $archiveModel->recommendationLetter)
            ->with('header_academic_program', $header_academic_program)
            ->with('letters_Commitment', $letters_Commitment)
            ->with('viewer', $request->session()->get('user'));
    }

    public function getDataFromPortalUser($appliant)
    {
        #Search user in portal
        $user_data_collect =  $this->service->miPortalGet('api/usuarios', ['filter[id]' => $appliant->id])->collect();

        #Save only the first user that the portal get reach

        $user_data = $user_data_collect[0];

        #Add data of user from portal to the collection in controlescolar
        $appliant->setAttribute('birth_date', $user_data['birth_date']);
        $appliant->setAttribute('curp', $user_data['curp']);
        $appliant->setAttribute('name', $user_data['name']);
        $appliant->setAttribute('middlename', $user_data['middlename']);
        $appliant->setAttribute('surname', $user_data['surname']);
        $appliant->setAttribute('age', $user_data['age']);
        $appliant->setAttribute('gender', $user_data['gender']);
        $appliant->setAttribute('birth_country', $user_data['nationality']);
        $appliant->setAttribute('residence_country', $user_data['residence']);
        $appliant->setAttribute('phone_number', $user_data['phone_number']);
        $appliant->setAttribute('email', $user_data['email']);
        $appliant->setAttribute('altern_email', $user_data['altern_email']);

        return $appliant;
    }

    /* -------------------------- APPLIANT VIEW --------------------------*/
    public function getDataFromSessionUser(Request $request)
    {
        //User data from ControlEscolar
        $appliant = $request->session()->get('user');
        //Usser data from portal
        $user_data = $request->session()->get('user_data');

        //Add data portal to local collection USER
        $appliant->setAttribute('birth_date', $user_data['birth_date']);
        $appliant->setAttribute('curp', $user_data['curp']);
        $appliant->setAttribute('name', $user_data['name']);
        $appliant->setAttribute('middlename', $user_data['middlename']);
        $appliant->setAttribute('surname', $user_data['surname']);
        $appliant->setAttribute('age', $user_data['age']);
        $appliant->setAttribute('gender', $user_data['gender']);
        $appliant->setAttribute('birth_country', $user_data['nationality']);
        $appliant->setAttribute('residence_country', $user_data['residence']);
        $appliant->setAttribute('phone_number', $user_data['phone_number']);
        $appliant->setAttribute('email', $user_data['email']);
        $appliant->setAttribute('altern_email', $user_data['altern_email']);
        return $appliant;
    }

    public function appliantFile_AppliantView(Request $request, $user_id)
    {
        // return $user_id;
        //User is not a student
        if ($request->user()->getIsAppliantAttribute() === false) {
            return back()->withInput();
        }

        //Is a postulant so we need to show the the file
        try {
            //deberia de retornar el archivo
            $archiveModel = Archive::where('user_id', $request->user()->id)->first();
        } catch (\Exception $e) {
            return back()->withInput()->withErrors($e); //Regresa a la pagina donde se encuentra mostando errores
        }

        //No existe archivo
        if ($archiveModel == null) {
            // return  new JsonResponse ($request->user()->id);
            return '<h1>No existe expediente para el postulante</h1>';
        }

        //Carga modelos de archivo
        $archiveModel->loadMissing([
            // Cosas del aplicante
            'appliant',
            'announcement.academicProgram',
            // Documentos y secciones para expedinte
            'personalDocuments',
            'requiredDocuments',
            'curricularDocuments',
            'entranceDocuments',
            'intentionLetter',
            'recommendationLetter',
            'myRecommendationLetter',
            'academicDegrees.requiredDocuments',
            'appliantLanguages.requiredDocuments',
            'appliantWorkingExperiences',
            'scientificProductions.authors',
            'humanCapitals'
        ]);

        $academic_program = $archiveModel->announcement->academicProgram;

        //function that returns the info complete in the appliant model
        $appliant = $this->getDataFromSessionUser($request);


        #Set the image according to academic program
        //Doctorado en ciencias ambientales
        $img = 'DOCTORADO-SUPERIOR.png';

        if ($academic_program->name == 'Maestría en ciencias ambientales') {
            $img = 'PMPCA-SUPERIOR.png';
        } else if ($academic_program->name == 'Maestría en ciencias ambientales, doble titulación') {
            $img = 'ENREM-SUPERIOR.png';
        } else if ($academic_program->name == 'Maestría Interdisciplinaria en ciudades sostenibles') {
            $img = 'IMAREC-SUPERIOR.png';
        }

        $header_academic_program = asset('storage/headers/' . $img);

        $location_letterCommitment_DCA =    asset('storage/DocumentoExtra/LetterCommitment/DCA.docx');
        $location_letterCommitment_MCA =    asset('storage/DocumentoExtra/LetterCommitment/MCA.docx');
        $location_letterCommitment_IMaREC = asset('storage/DocumentoExtra/LetterCommitment/IMaREC.docx');


        $letters_Commitment = [];
        array_push($letters_Commitment, $location_letterCommitment_MCA);    // [0] Maestria en ciencias ambientales, normal y doble titulacion
        array_push($letters_Commitment, $location_letterCommitment_DCA);    // [1] Doctorado en ciencias
        array_push($letters_Commitment, $location_letterCommitment_IMaREC); // [2]  ciudades sosteniles

        // $location_letterCommitment = asset('storage/DocumentoExtra/Carta_postulación_NAMC_FINAL.pdf');
        // dd($appliant);
        //Change for the view of appliant
        return view('postulacion.show')
            ->with('archive', $archiveModel)
            ->with('appliant', $appliant)
            ->with('academic_program', $academic_program)
            ->with('recommendation_letters', $archiveModel->myRecommendationLetter)
            ->with('archives_recommendation_letters', $archiveModel->recommendationLetter)
            ->with('header_academic_program', $header_academic_program)
            ->with('letters_Commitment', $letters_Commitment)
            ->with('viewer', $request->session()->get('user'));
    }

    public function updateMotivation(Request $request)
    {
        Archive::where('id', $request->archive_id)->update(['motivation' => $request->motivation]);

        return new JsonResponse(
            Archive::select('motivation')->firstWhere('id', $request->archive_id)
        );
    }


    public function updateArchivePersonalDocument(Request $request)
    {
        $archive = Archive::find($request->archive_id);

        # Archivo de la solicitud

        $ruta = $request->file('file')->storeAs(
            'archives/' . $request->archive_id . '/personalDocuments',
            $request->requiredDocumentId . '.pdf'
        );

        # Asocia los documentos requeridos.
        $archive->personalDocuments()->detach($request->requiredDocumentId);
        $archive->personalDocuments()->attach($request->requiredDocumentId, ['location' => $ruta]);

        return new JsonResponse(
            $archive->personalDocuments()
                ->select('required_documents.*', 'archive_required_document.location as location')
                ->where('id', $request->requiredDocumentId)
                ->first()
        );
    }

    public function updateArchiveEntranceDocument(Request $request)
    {
        $archive = Archive::find($request->archive_id);

        # Archivo de la solicitud
        $ruta = $request->file('file')->storeAs(
            'archives/' . $request->archive_id . '/entranceDocuments',
            $request->requiredDocumentId . '.pdf'
        );

        # Asocia los documentos requeridos.
        $archive->entranceDocuments()->detach($request->requiredDocumentId);
        $archive->entranceDocuments()->attach($request->requiredDocumentId, ['location' => $ruta]);
        /**Problema al regresar el json, marca un erro en la consulta */
        return new JsonResponse(
            $archive->entranceDocuments()
                ->select('required_documents.*', 'archive_required_document.location as location')
                ->where('id', $request->requiredDocumentId)
                ->first()
        );
    }

    /*---------------------------------------- ACADEMIC DEGREE  ---------------------------------------- */

    public function latestAcademicDegree(Request $request, Archive $archive)
    {
        return new JsonResponse($archive->latestAcademicDegree);
    }

    public function updateAcademicDegree(UpdateAcademicDegreeRequest $request)
    {


        try {
            $academic_degree = AcademicDegree::find($request->id);
            $academic_degree->update($request->safe()->toArray());
            $academic_degree->save();
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al actualizar información'], 500);
        }

        return new JsonResponse(['message' => $academic_degree], 200);
    }


    public function addAcademicDegree(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try {
            $academic_degree = AcademicDegree::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);

            $academic_degree->loadMissing([
                'requiredDocuments'
            ]);

            $academic_degree->save();
        } catch (\Exception $e) {
            return new JsonResponse('Error al crear registro academico para el usuario', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Programa academico agregado, inserta los datos necesarios para continuar con tu postulacion', 'model' => $academic_degree], 200);
    }

    public function deleteAcademicDegree(Request $request)
    {

        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);

        try {
            //Find the exact model of academic degree to delete
            $deleted = AcademicDegree::where('id', $request->id,)->where('archive_id', $request->archive_id)->delete();
        } catch (\Exception $e) {
            return new JsonResponse('Error eliminar el registro de datos academicos seleccionado', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Programa academico eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'], 200);
    }


    public function updateAcademicDegreeRequiredDocument(Request $request)
    {
        //FUncion guarda el archivo y actualiza el archivo que se guardo al momento de ver,
        //Pero hasta que subes el archivo por 2 vez aparece el mensaje que se a guardado con exito

        $academic_degree = AcademicDegree::find($request->id);

        # Archivo de la solicitud
        $ruta = $request->file('file')->storeAs(
            'archives/' . $request->archive_id . '/academicDocuments',
            $request->requiredDocumentId . '.pdf'
        );

        # Asocia los documentos requeridos.
        $academic_degree->requiredDocuments()->detach($request->requiredDocumentId);
        $academic_degree->requiredDocuments()->attach($request->requiredDocumentId, ['location' => $ruta]);

        return new JsonResponse(
            $academic_degree->requiredDocuments()
                ->select('required_documents.*', 'academic_degree_required_document.location as location')
                ->where('id', $request->requiredDocumentId)
                ->first()
        );
    }

    /*---------------------------------------- WORKING EXPERIENCE  ---------------------------------------- */

    public function addWorkingExperience(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try {
            $working_experience = WorkingExperience::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
        } catch (\Exception $e) {
            return new JsonResponse('Error al agregar nueva experiencia de trabajo para el aplicante', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Experiencia de trabajo agregada, inserta los datos necesarios para continuar con tu postulacion', 'model' => $working_experience], 200);
    }

    public function deleteWorkingExperience(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);

        try {
            //Find the exact model of academic degree to delete
            $deleted = WorkingExperience::where('id', $request->id,)->where('archive_id', $request->archive_id)->delete();
        } catch (\Exception $e) {
            return new JsonResponse('Error al eliminar experiencia laboral seleccionada', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Experiencia laboral eliminada correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'], 200);
    }


    public function updateWorkingExperience(UpdateWorkingExperienceRequest $request)
    {
        WorkingExperience::where('id', $request->id)->update($request->safe()->toArray());

        return new JsonResponse(WorkingExperience::find($request->id));
    }

    /* -------------------------- LANGUAGUES OF APPLIANT --------------------------*/

    public function addAppliantLanguage(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try {
            $appliant_language = AppliantLanguage::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
            $appliant_language->loadMissing([
                'requiredDocuments'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse('Error al agregar nuevo idioma para el aplicante', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Idioma agregado, inserta los datos necesarios para continuar con tu postulacion', 'model' => $appliant_language], 200);
    }


    public function deleteAppliantLanguage(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);

        try {
            //Find the exact model of academic degree to delete
            $deleted = AppliantLanguage::where('id', $request->id,)->where('archive_id', $request->archive_id)->delete();
        } catch (\Exception $e) {
            return new JsonResponse('Error al eliminar idioma seleccionado', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Idioma eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'], 200);
    }

    public function updateAppliantLanguage(UpdateAppliantLanguageRequest $request)
    {
        AppliantLanguage::where('id', $request->id)->update($request->safe()->toArray());

        return new JsonResponse(AppliantLanguage::find($request->id));
    }

    public function updateAppliantLanguageRequiredDocument(Request $request)
    {
        $appliant_language = AppliantLanguage::find($request->id);

        # Archivo de la solicitud
        $ruta = $request->file('file')->storeAs(
            'archives/' . $request->archive_id . '/laguageDocuments',
            $request->id . '_' . $request->requiredDocumentId . '.pdf'
        );

        # Asocia los documentos requeridos.
        $appliant_language->requiredDocuments()->detach($request->requiredDocumentId);
        $appliant_language->requiredDocuments()->attach($request->requiredDocumentId, ['location' => $ruta]);

        return new JsonResponse(
            $appliant_language->requiredDocuments()
                ->select('required_documents.*', 'appliant_language_required_document.location as location')
                ->where('id', $request->requiredDocumentId)
                ->first()
        );
    }

    /* -------------------------- INVESTIGACIÓN CIENTIFICA DEL APLICANTE --------------------------*/

    public function addScientificProduction(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try {
            $scientific_production = ScientificProduction::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
        } catch (\Exception $e) {
            return new JsonResponse('Error al agregar produccion cientifica para el aplicante', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Produccion cientifica agregada, inserta los datos necesarios para continuar con tu postulacion', 'model' => $scientific_production], 200);
    }

    public function updateScientificProduction(UpdateScientificProductionRequest $request)
    {
        # Actualiza los datos generales de la producción científica.
        $scipro = ScientificProduction::where('id', $request->id)
            ->update($request->safe()->only('state', 'title', 'publish_date', 'type'));
        $type = $request->type;

        # Determina si el tipo de producción científica cambió
        # y borra la producción científica anterior.
        if ($type !== null && $type !== $request->type && Schema::hasTable($type)) {
            DB::table($type)->where('scientific_production_id', $request->id)->delete();
        }

        $upsert_array = [];
        $identifiers = ['scientific_production_id' => $request->id];

        switch ($request->type) {
            case 'articles':
                $upsert_array = ['magazine_name' => $request->magazine_name];
                break;
            case 'published_chapters':
                $upsert_array = ['article_name' => $request->article_name];
                break;
            case 'technical_reports':
                $upsert_array = ['institution' => $request->institution];
                break;
            case 'working_documents':
                $upsert_array = ['post_title' => $request->post_title];
                break;
            case 'working_memories':
                $upsert_array = ['post_title' => $request->post_title];
                break;
            case 'reviews_cp':
                $upsert_array = ['post_title' => $request->post_title];
                break;
        }

        # Actualiza los datos adicionales de la producción científica.
        if (count($upsert_array) > 0)
            DB::table($request->type)->updateOrInsert($identifiers, $upsert_array);
        

        return new JsonResponse(
            ScientificProduction::leftJoin(
                'articles',
                'articles.scientific_production_id',
                'scientific_productions.id'
            )->leftJoin(
                'published_chapters',
                'published_chapters.scientific_production_id',
                'scientific_productions.id'
            )->leftJoin(
                'technical_reports',
                'technical_reports.scientific_production_id',
                'scientific_productions.id'
            )->leftJoin(
                'working_documents',
                'working_documents.scientific_production_id',
                'scientific_productions.id'
            )->leftJoin(
                'working_memories',
                'working_memories.scientific_production_id',
                'scientific_productions.id'
            )->leftJoin(
                'reviews_cp',
                'reviews_cp.scientific_production_id',
                'scientific_productions.id'
            )->first()
        );
    }

    public function addScientificProductionAuthor(AddScientificProductionAuthorRequest $request)
    {
        ScientificProduction::where('id', $request->scientific_production_id)->update($request->only('type'));

        return new JsonResponse(Author::create($request->safe()->only('scientific_production_id', 'name')));
    }

    public function deleteScientificProductionAuthor(UpdateScientificProductionAuthorRequest $request)
    {
        try {
            //Find the exact model of academic degree to delete
            $deleted = Author::where('id', $request->id)->where('scientific_production_id', $request->scientific_production_id)->delete();
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error eliminar el registro autor de producción cientifica seleccionado'], 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Autor eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'], 200);
    }

    public function deleteScientificProduction(Request $request)
    {

        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);

        try {
            //Find the exact model of academic degree to delete
            $deleted = ScientificProduction::where('id', $request->id,)->where('archive_id', $request->archive_id)->delete();
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error eliminar el registro de producción cientifica seleccionado'], 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Programa academico eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'], 200);
    }


    public function updateScientificProductionAuthor(UpdateScientificProductionAuthorRequest $request)
    {
        ScientificProduction::where('id', $request->scientific_production_id)->update($request->only('type'));
        Author::where('id', $request->id)
            ->update($request->safe()->only('scientific_production_id', 'name'));

        return new JsonResponse(Author::find($request->id));
    }


    /* -------------------------- CAPITAL HUMANO DEL APLICANTE --------------------------*/
    public function addHumanCapital(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try {
            $human_capital = HumanCapital::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
        } catch (\Exception $e) {
            return new JsonResponse('Error al agregar capital humano para el aplicante', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Capital Humano, inserta los datos necesarios para continuar con tu postulacion', 'model' => $human_capital], 200);
    }

    public function updateHumanCapital(UpdateHumanCapitalRequest $request)
    {
        HumanCapital::where('id', $request->id)->update($request->validated());

        return new JsonResponse(HumanCapital::find($request->id));
    }

    public function deleteHumanCapital(Request $request)
    {

        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);

        try {
            //Find the exact model of academic degree to delete
            $deleted = HumanCapital::where('id', $request->id,)->where('archive_id', $request->archive_id)->delete();
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error eliminar el registro de producción cientifica seleccionado'], 502);
        }
        //Recibe la información 
        return new JsonResponse(['message' => 'Programa academico eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'], 200);
    }

    /* -------------------------- CARTA DE RECOMENDACION DEL APLICANTE --------------------------*/
    public function sentEmailRecommendationLetter(Request $request)
    {

        // Variables locales
        $message = 'Exito, el correo ha sido enviado'; // Mensaje de retorno
        $my_token = Str::random(20);    //Token para identificar carta recomendacion

        //validacion de datos
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'academic_program' => ['required'],
            'appliant' => ['required'],
            'letter_created' => ['required'],
            'recommendation_letter' => ['required_if:letter_created,1'] // ya existe carta por lo tanto es requerido
        ]);

        #Se envia correo si todo salio correcto
        if ($request->letter_created == 1) { //cambia string ya que existe carta de recomendacion
            $my_token = $request->recommendation_letter['token'];
            //return json response
        }

        //Imgs to put in the email
        $url_LogoAA = asset('/storage/headers/logod.png');
        $url_ContactoAA = asset('/storage/logos/rtic.png');

        try {
            //Email enviado
            Mail::to($request->email)->send(new SendRecommendationLetter($request->email, $request->appliant, $request->academic_program, $my_token, $url_LogoAA, $url_ContactoAA));
        } catch (\Exception $e) {
            return new JsonResponse('Error: ' . $e->getMessage(), 200);
        }


        //Se busca el archivo por el USER ID
        $archive = Archive::where('user_id', $request->appliant['id'])->first();
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
            'humanCapitals'
        ]);

        # Cartas de recomendacion en expediente
        $num_recommendation_letter_count = $archive->archiveRequiredDocuments()
            ->whereNotNull('location')
            ->whereIsRecommendationLetter()
            ->count();

        #Se verifica el numero de cartas de recomendacion ya enviadas por archivo de solicitante
        if ($num_recommendation_letter_count > 2) {
            return new JsonResponse('Maximo numero de cartas contestadas, ya no se permiten mas respuestas', 200);
        }

        #Ids para relacion a archive required document table
        // $required_document_id  = ($num_recommendation_letter_count < 1) ? 19 : 20; //Maximo de dos cartas, por lo tanto sera solo (0,1)
        $required_document_id = 19;

        //Se verifica si ya existe un registro de Carta o se necesita crear
        if ($request->letter_created == 1) { //ya existe registro de carta
            $rlsCompare = $archive->myRecommendationLetter;
            foreach ($archive->myRecommendationLetter as $rl) {

                #Se verifica que no exista el mismo correo para contestar la carta en otra
                foreach ($rlsCompare as $rlCompare) {
                    //carta diferente
                    if ($rlCompare->id != $rl->id) {
                        if (strcmp($rlCompare->email_evaluator, $rl->email_evaluator) == 0) {
                            return new JsonResponse('Correo existente, intente con uno diferente', 200);
                        }
                    }
                }

                //checa si es el mismo id
                if ($rl->id == $request->recommendation_letter['id']) {

                    //Si son  diferentes actualizar registro
                    if (strcmp($rl->email_evaluator, $request->email) != 0) {
                        $change_email = 1; // el email a cambiado
                        $rl->email_evaluator = $request->email;
                        $rl->save();
                        break;
                    }
                }
            }
        } else { //no existe carta

            foreach ($archive->myRecommendationLetter as $rl) {
                if (strcmp($rl->email_evaluator, $request->email) == 0) {
                    return new JsonResponse('Correo registrado para otra carta, intente uno diferente', 200);
                }
            }

            #SE REQUIERE CREAR CAMPOS EN TABLAS
            try {
                $rl = MyRecommendationLetter::create([
                    'email_evaluator' => $request->email,
                    'archive_id' => $archive->id,
                    'token' => $my_token,  //random token to verify
                    'answer' => 0 //not answer
                ]); //Ahora se espera la respuesta del evaluador

                // Archivo requerido
                $archive_rd = ArchiveRequiredDocument::create([
                    'archive_id' => $archive->id,
                    'required_document_id' => $required_document_id
                ]);

                // Carta de recomendacion (Relacion de carta a archivo requerido)
                $archive_rl = RecommendationLetter::create([
                    'rl_id' => intval($rl->id),
                    'required_document_id' => intval($archive_rd->id),
                ]);
            } catch (\Exception $e) {
                return new JsonResponse('Error al crear la carta de recomendación comuniquese con Agenda Ambiental', 200);
            }
        }

        #Carta enviada, lista para llenar en base de datos

        #Correo enviado
        #Filas Creadas

        //Recommendation letter
        //ArchiveRequiredDocument
        //ArchiveRecommendationLetter
        return new JsonResponse(
            $message,
            200
        );
    }

    public function recommendationLetter(Request $request, $token)
    {

        // #Busqueda de Carta de recomendacion y archivo
        // try {
        //     //Busqueda de carta con token y correo       
        // } catch (\Exception $e) {
        //     return view('postulacion.error-noAppliant')
        //         ->with('user_id', $request->token);
        // }

        $rl = MyRecommendationLetter::where(
            'token',
            $token
        )->first();

        // return new JsonResponse(
        //     $rl,
        //     200
        // );

        // Se extrae el archivo de postulacion
        $archive = Archive::where('id', $rl->archive_id)->first();

        #Carga de modelos en archivo
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
            'humanCapitals'
        ]);


        #Verificacion de carta no contestada
        if ($rl->answer >= 1) {
            return view('postulacion.error-lettersSent')
                ->with($archive->appliant->id);
        }

        #Extraccion de variables necesarias
        try {
            // Extrae TODOS LOS PARAMETROS A EVALUAR
            $parameters = Parameter::all();
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        // Cartas de recomendacion en expediente
        $num_recommendation_letter_count = $archive->archiveRequiredDocuments()
            ->whereNotNull('location')
            ->whereIsRecommendationLetter()
            ->count();
        $announcement = $archive->announcement;
        $appliant = $archive->appliant;
        $academic_program = $archive->announcement->academicProgram;

        //Enviar los datos necesarios 
        return view('postulacion.recommendation-letter')
            ->with('recommendation_letter', $rl)
            ->with('appliant', $appliant)                   //usuario 
            ->with('announcement', $announcement)
            ->with('parameters', $parameters); //programa academico
    }
}
