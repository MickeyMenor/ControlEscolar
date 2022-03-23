<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\LoginService;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * El controlador consume al proovedor de servicios de Mi Portal.
     *
     * @var \App\Helpers\LoginService
     */
    private $loginService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware('guest')->except(['logout']);
        $this->loginService = new LoginService;
    }

    /**
     * Recupera los usuarios del sistema autenticado.
     *
     * @param \Illuminate\Http\Request  $request
     * @param User $user
     * @return void
     */
    private function getUsers(Request $request, User $user)
    {
        # Guarda al usuario autenticado.
        $request->session()->put('user', $user);

        # Solo solicita los datos, siempre y cuando el usuario sea un postulante.
        
        /** @var User */
        # Carga otros datos que requiere el modelo.
        $user->load(['academicAreas', 'academicEntities']);

        # Busca a los postulantes.
        $appliants = User::with(['latestArchive.intentionLetters:archive_intention_letter.user_id,archive_intention_letter.user_type'])
            ->hasArchive()
            ->appliant()
            ->pluck('id');

        # Busca a los profesores en el sistema.
        $professors = User::role(['profesor_nb','admin','control_escolar','personal_apoyo'])->pluck('id');
        
        # Fusiona a los usuarios.
        $users = $professors->merge($appliants)->toArray();


        #Peticion a portal (Busca usuario)
        $user_data =  $this->miPortalService-> miPortalGet('api/usuarios',['filter[id]' => Auth::user()->id])->collect();
        $request->session()->put('user_data', $user_data[0]);

        # Carga modelos si es administrador o profesor
        if($user_data[0]['user_type'] != 'students'){

            # Usuario trabajador / Administrador
            $response = $this->miPortalService->miPortalGet('api/usuarios', [
                'filter[userModules.id]' => env('MIPORTAL_MODULE_ID'),
                //'fields[users]' => 'id,name,middlename,surname,type,curp,email',
                'filter[id]' => $users
            ]);

            # Recolecta el resultado.
            $miPortal_appliants = $response->collect()->whereNotIn('user_type', ['workers']);
            $miPortal_workers = $response->collect()->where('user_type', 'workers');

            # Guarda a los usuarios del sistema central en la sesión.
            $request->session()->put('appliants', $miPortal_appliants);
            $request->session()->put('workers', $miPortal_workers);
        }

    }

    public function prelogin(){
        //
        if(auth()->check()){
            return redirect(route('home'));
        }
        return redirect(route('pre-registro.index'));
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */

    public function login(LoginRequest $request)
    {
        //puse esta condicion para entrar en local, 
        //se debe quitar y descomentar la funcion de arriab despues
        // if(!isset($request)){
        //     return redirect(route('pre-registro.index'));
        // }else
        // {
            # Determina si se requiere solicitar autorización.
            ///*
            if (!$this->loginService->isCallbackRequest($request))
                return $this->loginService->requestAuthorization($request);

            # Busca al usuario en el sistema central.
            $user_response = $this->loginService->loginGet('api/users/whoami', $request->code);
            
            # Si la respuesta fue errónea, devuele el error.
            ///*
            if ($user_response->failed())
                return back()->withErrors($user_response->collect());
            //*/

            # Recolecta los datos del usuario.
            $miportal_user = $user_response->collect();

            # Busca al usuario en el sistema.
            $user = User::where('id', $miportal_user['id'])->where('type', $miportal_user['user_type'])->first();

            # Si el usuario no está en el sistema, manda error.
            ///*
            if ($user === null)
                return back()->withErrors(['motivo' => 'Usuario no registrado en el sistema']);
            //*/

            # Autentica al usuario y guarda los datos del sistema central.
            $miportal_user['roles'] = $user->roles;
            Auth::login($user);

            $this->getUsers($request, $user);
            
            // }

        # Redirecciona a la página principal.
        return redirect()->route('authenticate.home');
    }

    /**
     * Handle a test login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function testLogin(Request $request, $user)
    {
        # Determina si se requiere solicitar autorización.
        Auth::loginUsingId($user);
        
        /** @var User */
        $user = Auth::user();
        $user->load('roles');
        $this->getUsers($request, $user);

        # Redirecciona a la página principal.
        return redirect(route('authenticate.home'));
    }

    public function preAuth(Request $request, $user)
    {
        # Determina si se requiere solicitar autorización.
        Auth::loginUsingId($user);
        
        /** @var User */
        $user = Auth::user();
        $user->load('roles');
        $this->getUsers($request, $user);
    }
        
    //login depues del preregistro ->  se manda a llamar desde POST y recibe id_user
    public function userFromPortal(Request $request,$user_id)
    {
         //Obtiene usuario de control escolar
        $u = User::where('id',$user_id)->first();
        
        //Autho2
        // if($request->ak!='' && $request->ak == env('CONTROL_ESCOLAR_ACCESS_KEY') && $u){   
            $this->testLogin($request,$user_id);
        // }
       
       return redirect(route('pre-registro.index'));

    }


    public function register(Request $request)
    {
        //dd($request);
        //return $this->crearusuarioAA($request);
        //return $request;
        //return "hola";
        //validar datos

        //switch para checar tipo de usuario
        ///*
        switch($request->tipo_usuario){
            case 'Comunidad AA':
                //creamos cuenta para alumno con cuenta en la agenda ambiental
                return $request;
            break;
            case 'Comunidad UASLP':
                //creamos cuenta en en este sistema y en el portal tambien a usuario partiendo de cuenta de uaslp
                break;
            default:
                //creamos cuenta en este sistema y en el portal tambien a usuario completamente externo
            break;
        }
        //crear usuario
        //*/
    }
/*
    private function crearusuario(){
        User::create([
            'id' => $request->clave_uaslp
        ]);
    }
    */

    private function crearusuarioAA($req){
        /*
        Http::post("",[
            "email" => $req->email,
            "CorreoAlterno" => $req->email_alterno,
            "Pais" => $req->,
            "LugarResidencia" => $req->,
            "CURP" => $req->,
            "nombres" => $req->,
            "ApellidoP" => $req->,
            "ApellidoM" => $req->,
            "Edad" => $req->,
            "Genero" => $req->,
            "Celular" => $req->,
            "CP" => $req->,
            "Ocupacion" => $req->,
            "GEtnico" => $req->,
            "Discapacidad" => $req->
        ]);
        */
        $res = Http::post("http://127.0.0.1:8000/register",[
            "_token" => csrf_field(),
            "email" => "a278737@alumnos.uaslp.mx",
            "CorreoAlterno" => "a278737@alumnos.uaslp.mx",
            "Pais" => "Mexico",
            "LugarResidencia" => "Mexico",
            "CURP" => "TUEM980929HSPRSG00",
            "Nombres" => "Miguel",
            "ApellidoP" => "Trujillo",
            "ApellidoM" => "Esquivel",
            "Edad" => "22",
            "Genero" => "Masculino",
            "Tel" => "4443203350",
            "CP" => "78438",
            "Ocupacion" => "Programador",
            "GEtnico" => "No",
            "Discapacidad" => "no"
        ]);

        return $res;
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        //return redirect(route('authenticate.prelogin'));
        return redirect('https://ambiental.uaslp.mx/Miportal');
    }
}
