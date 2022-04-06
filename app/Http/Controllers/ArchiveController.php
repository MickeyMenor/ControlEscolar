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
    
    /* -------------------------- DASHBOARD DEL APLICANTE --------------------------*/

    /**
     * Show the archives dashboard. 
     *
     * @return \Illuminate\Contracts\Support\Renderable
     * 
     */
    public function index(Request $request)
    {

        return view('postulacion.index')
            ->with('user', $request->user())
            ->with('academic_programs', AcademicProgram::with('latestAnnouncement')->get());

    }
    
    /* -------------------------- ADMIN VIEW FUNCTIONS --------------------------*/

    /**
     * Obtiene los expedientes, vía api. 
     *
     * @return \Illuminate\Contracts\Support\Renderable
     * 
     */

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
            return new JsonResponse(['message' => "No existen archivos para las fechas y modalidad indicada"] , 502);
        }

        //Comprobar que todos los modelos de appliant tengan la información necesaria
        //Caso contrario insertar información en modelos

        foreach($archives as $k => $archive){
            //El postulante no tiene toda la información
           if(!$archive->appliant->name){
               
               try{
                $user_data_collect =  $this->service->miPortalGet('api/usuarios', ['filter[id]' => $archive->appliant->id])->collect();
               }catch (\Exception $e) {
                   return new JsonResponse($e->getMessage(), 200); //Ver info archivos en consola
               }
               
                if(sizeof($user_data_collect)>0){

                    //ArchiveResource create $user_data_collect[0];
                    $user_data = $user_data_collect[0];
                    //Se guarda el nombre del usuario en el modelo
                    $archive->appliant->setAttribute('name',$user_data['name'].' '.$user_data['middlename'].' '.$user_data['surname']);
                
                    //Eliminar mi archivo para produccion
                    //Descomentar en local
                    // if($archive->appliant->name == 'ULISES URIEL DOMINGUEZ PEREZ'){
                    //     unset($archives[$k]);
                    // }

                }else{
                    //No existe aplicante por lo que no se podra ver expediente
                    $archive->appliant->setAttribute('name','Usuario invalido');
                    $archive->id = -1;

                    //Elimina al usuario invalido de la lista
                    unset($archives[$k]);
                }

                
               
           }
        }
        // return new JsonResponse($archives, 200); //Ver info archivos en consola
        return ArchiveResource::collection($archives);
    }

     /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function appliantFile_AdminView(Request $request, $archiveusr)
    {
        $archiveModel = Archive::where('id', $archiveusr)->first();
        if ($archiveModel == null) {
            return '<h1>No existe expediente para el postulante</h1>';
        }

        $archiveModel->loadMissing([
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

        $academic_program = $archiveModel->announcement->academicProgram;
         
        //function that returns the info complete in the appliant model
        $appliant = $this->getDataFromPortalUser($archiveModel->appliant);

        #Set the image according to academic program
        //Doctorado en ciencias ambientales
        $img = 'DOCTORADO-SUPERIOR.png';

        if($academic_program->name == 'Maestría en ciencias ambientales' ){
            $img = 'PMPCA-SUPERIOR.png';
        }else if($academic_program->name == 'Maestría en ciencias ambientales, doble titulación'){
            $img = 'ENREM-SUPERIOR.png';
        }else if($academic_program->name == 'Maestría Interdisciplinaria en ciudades sostenibles'){
            $img = 'IMAREC-SUPERIOR.png';
        }

        $header_academic_program = asset('storage/headers/'.$img);

        // dd($archiveModel->personalDocuments);

        //Change to the view for admin
        return view('postulacion.show')
            ->with('archive', $archiveModel)
            ->with('appliant', $appliant)
            ->with('academic_program', $academic_program)
            ->with('recommendation_letters', $archiveModel->myRecommendationLetter)
            ->with('archives_recommendation_letters', $archiveModel->recommendationLetter)
            ->with('header_academic_program', $header_academic_program)
            ->with('viewer', $request->session()->get('user'));
    }
    
    /** 
        * Match the data of the user in ControlEscolar and Portal
        * @return \Illuminate\Contracts\Support\Renderable
    */
    public function getDataFromPortalUser($appliant)
    {
        #Search user in portal
        $user_data_collect =  $this->service->miPortalGet('api/usuarios', ['filter[id]' => $appliant->id])->collect();

        #Save only the first user that the portal get reach

        $user_data = $user_data_collect[0];

        #Add data of user from portal to the collection in controlescolar
        $appliant->setAttribute('birth_date',$user_data['birth_date']);
        $appliant->setAttribute('curp',$user_data['curp']);
        $appliant->setAttribute('name',$user_data['name']);
        $appliant->setAttribute('middlename',$user_data['middlename']);
        $appliant->setAttribute('surname',$user_data['surname']);
        $appliant->setAttribute('age',$user_data['age']);
        $appliant->setAttribute('gender',$user_data['gender']);
        $appliant->setAttribute('birth_country',$user_data['nationality']);
        $appliant->setAttribute('residence_country',$user_data['residence']);
        $appliant->setAttribute('phone_number',$user_data['phone_number']);
        $appliant->setAttribute('email',$user_data['email']);
        $appliant->setAttribute('altern_email',$user_data['altern_email']);
            
        return $appliant;
    }
    
    /* -------------------------- APPLIANT VIEW --------------------------*/

     /**
     * Match the data from session of the user in ControlEscolar and Portal
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    
    public function getDataFromSessionUser(Request $request)
    {
        //User data from ControlEscolar
        $appliant = $request->session()->get('user');
        //Usser data from portal
        $user_data = $request->session()->get('user_data');
        
        //Add data portal to local collection USER
        $appliant->setAttribute('birth_date',$user_data['birth_date']);
        $appliant->setAttribute('curp',$user_data['curp']);
        $appliant->setAttribute('name',$user_data['name']);
        $appliant->setAttribute('middlename',$user_data['middlename']);
        $appliant->setAttribute('surname',$user_data['surname']);
        $appliant->setAttribute('age',$user_data['age']);
        $appliant->setAttribute('gender',$user_data['gender']);
        $appliant->setAttribute('birth_country',$user_data['nationality']);
        $appliant->setAttribute('residence_country',$user_data['residence']);
        $appliant->setAttribute('phone_number',$user_data['phone_number']);
        $appliant->setAttribute('email',$user_data['email']);
        $appliant->setAttribute('altern_email',$user_data['altern_email']);
        return $appliant;
    }
  

    /**
     * Show the file of each user
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
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

        $academic_program = $archiveModel->announcement->academicProgram;
        
        //function that returns the info complete in the appliant model
        $appliant = $this->getDataFromSessionUser($request);
        

        #Set the image according to academic program
        //Doctorado en ciencias ambientales
        $img = 'DOCTORADO-SUPERIOR.png';

        if($academic_program->name == 'Maestría en ciencias ambientales' ){
            $img = 'PMPCA-SUPERIOR.png';
        }else if($academic_program->name == 'Maestría en ciencias ambientales, doble titulación'){
            $img = 'ENREM-SUPERIOR.png';
        }else if($academic_program->name == 'Maestría Interdisciplinaria en ciudades sostenibles'){
            $img = 'IMAREC-SUPERIOR.png';
        }

        $header_academic_program = asset('storage/headers/'.$img);
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
            ->with('viewer', $request->session()->get('user'));
    }


    /**
     * Actualiza un documento requerido, para el grado académico
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function updateMotivation(Request $request)
    {
        Archive::where('id', $request->archive_id)->update(['motivation' => $request->motivation]);

        return new JsonResponse(
            Archive::select('motivation')->firstWhere('id', $request->archive_id)
        );
    }

    /**
     * 
     * Actualiza un documento requerido, para el grado académico
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
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

    /**
     * Actualiza un documento requerido, para el grado académico
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
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

    /**
     * Obtiene el grado académico más reciente.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function latestAcademicDegree(Request $request, Archive $archive)
    {
        return new JsonResponse($archive->latestAcademicDegree);
    }

    /**
     * Actualiza los datos académicos del postulante.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function updateAcademicDegree(Request $request)
    {
        

        try{
            $request->validate([
                'id' => ['required','exists:academic_degrees,id'],
                'archive_id' => ['required','exists:academic_degrees,archive_id'],
                'state' => ['required', 'string'],
                'degree' => ['nullable', 'required_if:state,Completo'],
                'degree_type' => ['nullable', 'required_if:state,Completo', 'string'],
                'cvu' => ['nullable', Rule::requiredIf($this->degreeType === 'Maestría' && $this->state === 'Completo'), 'numeric'],
                'cedula' => ['nullable', Rule::requiredIf($this->status === 'Grado obtenido' && $this->state === 'Completo'), 'numeric'],
                'country' => ['nullable', 'required_if:state,Completo', 'string'],
                'university' => ['nullable', 'required_if:state,Completo', 'string'],
                'status' => ['nullable', 'required_if:state,Completo', 'in:Pasante,Grado obtenido,Título o grado en proceso', 'string'],
                'average' => ['nullable', 'required_if:state,Completo', 'numeric'],
                'min_avg' => ['nullable', 'required_if:state,Completo', 'numeric'],
                'max_avg' => ['nullable', 'required_if:state,Completo', 'numeric'],
                'knowledge_card' => ['nullable', Rule::requiredIf($this->degreeType === 'Maestría' && $this->state === 'Completo'), 'in:Si,No', 'string'],
                'digital_signature' => ['nullable', Rule::requiredIf($this->degreeType === 'Maestría' && $this->state === 'Completo'), 'in:Si,No', 'string'],
                'titration_date' => ['nullable', 'required_if:state,Completo'],
            ]);
        } catch (\Exception $e){
            return new JsonResponse(['message'=> 'Los datos tiene un formato invalido intente mas tarde'],400);
        }

        try{
            $academic_degree = AcademicDegree::find($request->id);
            $academic_degree->fill($request);
            $academic_degree->save();
    
        } catch (\Exception $e){
            return new JsonResponse(['message'=>'Error al actualizar información'],500);
        }
       
        return new JsonResponse(['message'=>$academic_degree] ,200);
    }

    /**
     * Crea nuevo registro de datos academicos para el postulante
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    
    public function addAcademicDegree(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try{
            $academic_degree = AcademicDegree::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
        }catch (\Exception $e){
            return new JsonResponse('Error al crear registro academico para el usuario', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Programa academico agregado, inserta los datos necesarios para continuar con tu postulacion', 'model'=>$academic_degree ],200);
    }

    /**
     * Elimina datos académicos del postulante del registro seleccionado
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    
    public function deleteAcademicDegree(Request $request)
    {

        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);
        
        try{
            //Find the exact model of academic degree to delete
            $deleted = AcademicDegree::where('id', $request->id,)->where('archive_id',$request->archive_id)->delete();

        }catch (\Exception $e){
            return new JsonResponse('Error eliminar el registro de datos academicos seleccionado', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Programa academico eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'],200);
    }


    /**
     * Actualiza un documento requerido, para el grado académico
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
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
        
     /**
     * Add working experience to the model 
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function addWorkingExperience(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try{
            $working_experience = WorkingExperience::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
        }catch (\Exception $e){
            return new JsonResponse('Error al agregar nueva experiencia de trabajo para el aplicante', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Experiencia de trabajo agregada, inserta los datos necesarios para continuar con tu postulacion', 'model' => $working_experience],200);
    }
    
     /**
     * Actualiza un documento requerido, para el grado académico
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function deleteWorkingExperience(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);
        
        try{
            //Find the exact model of academic degree to delete
            $deleted = WorkingExperience::where('id', $request->id,)->where('archive_id',$request->archive_id)->delete();

        }catch (\Exception $e){
            return new JsonResponse('Error al eliminar experiencia laboral seleccionada', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Experiencia laboral eliminada correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'],200);
    }


    /**
     * Actualiza los campos de la experiencia laboral seleccionada
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function updateWorkingExperience(UpdateWorkingExperienceRequest $request)
    {
        WorkingExperience::where('id', $request->id)->update($request->safe()->toArray());

        return new JsonResponse(WorkingExperience::find($request->id));
    }

    /* -------------------------- LANGUAGUES OF APPLIANT --------------------------*/

     /**
     * Actualiza un documento requerido, para el grado académico
     * route post -> controlescolar / solicitud / addAppliantLanguage'
     * 
     * @return \Illuminate\Contracts\Support\Renderable
     */

     public function addAppliantLanguage(Request $request)
     {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try{
            $appliant_language = AppliantLanguage::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
        }catch (\Exception $e){
            return new JsonResponse('Error al agregar nuevo idioma para el aplicante', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Idioma agregado, inserta los datos necesarios para continuar con tu postulacion', 'model' => $appliant_language],200);
     }

     /**
     * Actualiza un documento requerido, para el grado académico
     * route post -> controlescolar / solicitud / deleteAppliantLanguage'
     * 
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function deleteAppliantLanguage(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);
        
        try{
            //Find the exact model of academic degree to delete
            $deleted = AppliantLanguage::where('id', $request->id,)->where('archive_id',$request->archive_id)->delete();

        }catch (\Exception $e){
            return new JsonResponse('Error al eliminar idioma seleccionado', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Idioma eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'],200);
    }

    /**
     * Actualiza un documento requerido, para el grado académico
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function updateAppliantLanguage(UpdateAppliantLanguageRequest $request)
    {
        AppliantLanguage::where('id', $request->id)->update($request->safe()->toArray());

        return new JsonResponse(AppliantLanguage::find($request->id));
    }


    /**
     * Actualiza la lengua extranjera de un postulante.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function updateAppliantLanguageRequiredDocument(Request $request)
    {
        $appliant_language = AppliantLanguage::find($request->id);

        # Archivo de la solicitud
        $ruta = $request->file('file')->storeAs(
            'archives/' . $request->archive_id . '/laguageDocuments/',
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

        
     /**
     * Add working experience to the model 
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function addScientificProduction(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try{
            $scientific_production = ScientificProduction::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
        }catch (\Exception $e){
            return new JsonResponse('Error al agregar produccion cientifica para el aplicante', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Produccion cientifica agregada, inserta los datos necesarios para continuar con tu postulacion', 'model' => $scientific_production],200);
    }

    /**
     * Actualiza la lengua extranjera de un postulante.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function updateScientificProduction(UpdateScientificProductionRequest $request)
    {
        $type = ScientificProduction::where('id', $request->id)->value('type');

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
        }

        # Actualiza los datos adicionales de la producción científica.
        if (count($upsert_array) > 0)
            DB::table($request->type)->updateOrInsert($upsert_array, $identifiers);

        # Actualiza los datos generales de la producción científica.
        ScientificProduction::where('id', $request->id)
            ->update($request->safe()->only('state', 'title', 'publish_date', 'type'));

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
            )->first()
        );
    }

    /**
     * Agrega un autor a la producción científica.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function addScientificProductionAuthor(AddScientificProductionAuthorRequest $request)
    {
        ScientificProduction::where('id', $request->scientific_production_id)->update($request->only('type'));

        return new JsonResponse(Author::create($request->safe()->only('scientific_production_id', 'name')));
    }

    public function deleteScientificProduction(Request $request)
    {

        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'id' => ['required', 'numeric']
        ]);
        
        try{
            //Find the exact model of academic degree to delete
            $deleted = ScientificProduction::where('id', $request->id,)->where('archive_id',$request->archive_id)->delete();

        }catch (\Exception $e){
            return new JsonResponse(['message'=>'Error eliminar el registro de producción cientifica seleccionado'], 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Programa academico eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'],200);
    }


    /**
     * Actualiza un autor de una producción científica.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function updateScientificProductionAuthor(UpdateScientificProductionAuthorRequest $request)
    {
        ScientificProduction::where('id', $request->scientific_production_id)->update($request->only('type'));
        Author::where('id', $request->id)
            ->update($request->safe()->only('scientific_production_id', 'name'));

        return new JsonResponse(Author::find($request->id));
    }

        
    /* -------------------------- CAPITAL HUMANO DEL APLICANTE --------------------------*/

       
     /**
     * Add working experience to the model 
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function addHumanCapital(Request $request)
    {
        $request->validate([
            'archive_id' => ['required', 'numeric'],
            'state' => ['required', 'string', 'max:255']
        ]);

        try{
            $human_capital = HumanCapital::create([
                'archive_id' => $request->archive_id,
                'state' => $request->state
            ]);
        }catch (\Exception $e){
            return new JsonResponse('Error al agregar capital humano para el aplicante', 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Capital Humano, inserta los datos necesarios para continuar con tu postulacion', 'model' => $human_capital],200);
    }


    /**
     * Actualiza el capital humano de un postulante.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
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
        
        try{
            //Find the exact model of academic degree to delete
            $deleted = HumanCapital::where('id', $request->id,)->where('archive_id',$request->archive_id)->delete();

        }catch (\Exception $e){
            return new JsonResponse(['message'=>'Error eliminar el registro de producción cientifica seleccionado'], 502);
        }
        //Recibe la información 
        return new JsonResponse(['message'=>'Programa academico eliminado correctamente, podras agregar y rellenar nuevamente los datos si asi lo deseas'],200);
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
            return new JsonResponse('Error: '.$e->getMessage(), 200);
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

    
    /**
     * Show recommendation letter view
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    //aqui solo recibira una cadena 
    //public function recommendationLetter(Request $request)
    /*
    request tendra

    token
    email evaluator
    answer (boleano numerico)
    */
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
