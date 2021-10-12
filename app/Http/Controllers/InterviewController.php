<?php

namespace App\Http\Controllers;

use App\Helpers\ZoomService;
use App\Http\Requests\CreateMeetingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    # Tipos de reunión.
    private const MEETING_TYPE_INSTANT = 1;
    private const MEETING_TYPE_SCHEDULE = 2;
    private const MEETING_TYPE_RECURRING = 3;
    private const MEETING_TYPE_FIXED_RECURRING_FIXED = 8;
    
    /**
     * Ruta para las reniones programadas con la cuenta del PMPCA.
     * 
     * @var string
     */
    private const USER_MEETINGS_URL = 'users/me/meetings';

    /**
     * Ruta para las reniones programadas con la cuenta del PMPCA.
     * 
     * @var string
     */
    private const MEETINGS_URL = 'meetings/';

    /**
     * El controlador consume al proovedor de servicios de zoom.
     *
     * @var \App\Helpers\ZoomService
     */
    private $zoomService;

    public function __construct()
    {
        $this->zoomService = new ZoomService;
    }

    /**
     * Devuelve la vista del.
     * 
     * @param Request $request
     */
    public function calendario()
    {
        return view('entrevistas.index');
    }


    /**
     * Enlista todas las reuniones de zoom.
     * 
     * @param Request $request
     */
    public function index(): JsonResponse
    {
        # Obtiene el listado de reuniones.
        $response = $this->zoomService->zoomGet(self::USER_MEETINGS_URL, ['page_size' => 300]);
        
        # Recolecta el resultado.
        $data = $response->collect();
    
        # Devuelve el resultado
        return new JsonResponse($data, $response->status());
    }

    /**
     * Genera una nueva reunión de zoom.
     * 
     * @param CreateMeetingRequest $request
     **/
    public function store(CreateMeetingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['type'] = self::MEETING_TYPE_SCHEDULE;
        $data['timezone'] = 'America/Mexico_City';

        $response = $this->zoomService->zoomPost(self::USER_MEETINGS_URL, $data);
    
        # Devuelve el resultado
        return new JsonResponse($response->collect(), $response->status());
    }

    /**
     * Obtiene los detalles de una reunión de zoom.
     * 
     * @param string $id
     **/
    public function show(string $id) 
    {
        # Obtiene el listado de reuniones.
        $response = $this->zoomService->zoomGet(self::MEETINGS_URL.$id);
                
        # Recolecta el resultado.
        $data = $response->collect();

        # Devuelve el resultado
        return new JsonResponse($data, $response->status());
    }

    /**
     * Actualiza una reunión.
     * 
     * @param Request $request
     **/
    public function update(Request $request, string $id) 
    {
        $data = $request->validated();
        $data['type'] = self::MEETING_TYPE_SCHEDULE;
        $data['timezone'] = 'America/Mexico_City';

        $response = $this->zoomService->zoomPatch(self::MEETINGS_URL.$id, $data);
    
        # Devuelve el resultado
        return new JsonResponse($response->collect(), $response->status());
    }

    /**
     * Elimina una reunión.
     * 
     * @param string $id
     **/
    public function delete(string $id) 
    {
        $response = $this->zoomDelete(self::MEETINGS_URL.$id);

        # Devuelve el resultado
        return new JsonResponse($response->collect(), $response->status());
    }
}
