<?php namespace App;

use Validator;
use Freshwork\ChileanBundle\Rut;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use DB;

class Suite
{
    private $_high                  = 'ALTA';
    private $_medium                = 'MEDIA';
    private $_low                   = 'BAJA';
    private $_activeSurvey          = 'banamb';
    public function __construct()
    {
        //
    }
    public function saveUpdate($request, $jwt)
    {
        $rules = [
            "survey" => 'required|string|max:6',
            "ticket" => 'required|numeric',
            "status" => 'required|numeric',
            "detail" => 'required|string'
        ];

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return [
                "datas" => $validator->errors(),
                "status" => Response::HTTP_UNPROCESSABLE_ENTITY
            ];
        }
        try {
            $resp = DB::table('adata_'.substr($request->survey,0,3).'_'.substr($request->survey,3,6).'_start')->where('id', $request->ticket)->update(['estado_close' => $request->status, 'det_close' => $request->detail, 'fec_close'=>date('Y-m-d')]);
            if($resp===1){
                $namev = DB::table('adata_'.substr($request->survey,0,3).'_'.substr($request->survey,3,6).'_start')->where('id', $request->ticket)->first();
                $this->sendedmail($namev->nom, $namev->mail, $namev->token, $request->survey);
            }
            return[
                'datas'  => 'complet',
                'status' => Response::HTTP_CREATED
            ];
        }catch(\Throwable $e) {
            return [
                'datas'  => $e->getMessage(),
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY
            ];
        }
    }
    public function getSurvey($request,$jwt)
    {
        try{
            //$codCustomer = ($jwt[env('AUTH0_AUD')]->client === null) ? 'BAN001' : $jwt[env('AUTH0_AUD')]->client;
            $codCustomer = ($request->get('company') !== null) ? $request->get('company'): $jwt[env('AUTH0_AUD')]->client;
            $resp = DB::table('customer_banmedica.survey')->where('codCustomer', $codCustomer)->get();
            //$resp = DB::table('survey')->get();
        }catch (\Throwable $e) {
            return $data = [
                'datas'  => $e->getMessage(),
                'status'=> Response::HTTP_UNPROCESSABLE_ENTITY
            ];
        }
        if($resp)
        {
            foreach ($resp as $key => $value) {
                $surveys[] = [
                    'name'      => $value->nomSurvey,
                    'base'      => $value->codDbase,
                    'customer'  => $value->codCustomer,
                    //'client'    => $jwt[env('AUTH0_AUD')]->client
                ];
            }
        }
        $data = [
            'datas'     => isset($surveys) ? $surveys: 'NO ENCONTRAMOS INFORMACION',
            'status'    => Response::HTTP_OK
        ];
        return $data;
    }
    public function indicatorPrincipal($request, $jwt)
    {
        $ticketCreated  = 0;
        $ticketClosed   = 0;
        $convertion     = 0;
        $convertionRate = 0;
        $ticketOpen     = 0;
        $ticketManage   = 0;
        $ticketPending  = 0;
        $ticketNoContact  = 0;
        $high=0;
        $medium=0;
        $low=0;
        //
        try {
            $client = ($request->get('company') !== null) ? $request->get('company'): $jwt[env('AUTH0_AUD')]->client;
            $survey = ($request->get('survey') === null) ? $jwt[env('AUTH0_AUD')]->survey: $request->get('survey');
            $survey = $this->buildSurvey($survey,$client);
            $dbQuery = DB::table('customer_banmedica.' . $client . '_' . $survey);
            $dbQuery->where('etapaencuesta', 'P2');
            $dbQuery->where('contenido','!=', '');
            $resp = $dbQuery->whereIn('nps', [0,1,2,3,4,5,6])->get();
        } catch (\Throwable $e) {
            return $data = [
                'datas'     => $e->getMessage(),
                'status'    => Response::HTTP_UNPROCESSABLE_ENTITY
            ];
        }
        foreach ($resp as $key => $value) {
            $ticketCreated++;
            //estado_close 0 es sin gestion, gestionado = 1, pendiente = 2, datos no corresponde = 3
            //TODO visita = 0 and estado_close = 0 (ticketOpen)
            if ($value->visita == 0 && $value->estado == 0) {
                $ticketOpen++;
            }
            if ($value->estado == 1 && $value->visita == 0) {
                $ticketManage++;
            }
            if ($value->estado == 2 && $value->visita == 0) {
                $ticketPending++;
            }
            if ($value->estado == 3 && $value->visita == 0) {
                $ticketNoContact++;
            }
            //TODO  estado_close= 4  and  visita = 1
            if ($value->estado == 4 && $value->visita == 1)
            //estado_close = 4 (cerrado)
            {
                $ticketClosed++;
                if ($value->nps_cierre > 8) {
                    $convertion++;
                }
            }
            $ticketProb= $this->calculateProb(0, $value->nps);
                if($ticketProb == $this->_high ){
                    $high++;
                }elseif($ticketProb ==  $this->_medium ){
                    $medium++;
                }elseif($ticketProb ==  $this->_low ){
                    $low++;
                }
        }
        if ($convertion > 0)
            $convertionRate = (($convertion / $ticketClosed) * 100);
        $closedRate = 0;
        if ($ticketCreated > 0)
            $closedRate = round(($ticketClosed / $ticketCreated) * 100);
        return [
            'datas'  => [
                'client'            => ($client == 'VID001') ? 'Vida Tres' : 'Banmedica',
                'clients'           => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: null,
                'ticketCreated'     => (object)['high' =>$high,'medium' =>$medium, 'low' =>$low] ,
                "ticketOpen"        => $ticketOpen,
                "ticketClosed"      => $ticketClosed,
                "closedRate"        => $closedRate,
                "convertionRate"    => $convertionRate,
                "ticketManage"      => $ticketManage,
                "ticketPending"     => $ticketPending,
                "ticketNoContact"   => $ticketNoContact,
                "totalTiket"        => $ticketCreated
            ],
            'status' => Response::HTTP_OK
        ];
    }
    private function buildSurvey($survey, $client)
    {
        if($client == 'VID001'){
            //substr($survey,0,3).'_'.substr($survey,3,6)
            return 'vid'.substr($survey,3,6);
            
        }
        if($client == 'BAN001'){
             return 'ban'.substr($survey,3,6);
        }
        return $survey;
    }
    public function resumenIndicator($request, $jwt)
    {
        
        $validFilterKeys = array("nps"); // <-- keys habilitadas para filtrar
        $validOrderKeys = array("nps", "date"); // <-- keys habilitadas para Ordenar
        
        try{
            $client = ($request->get('company') !== null) ? $request->get('company'): $jwt[env('AUTH0_AUD')]->client;
            
            /* 
            $client =  $jwt[env('AUTH0_AUD')]->client;
            // TODO validar client
            
            $survey = $request->get('survey') || $jwt[env('AUTH0_AUD')]->survey;
            // TODO validar survey
    
            $dbQuery = DB::table('customer_banmedica.'.$client.'_'.$survey);
            */
            //echo $jwt[env('AUTH0_AUD')]->client.'_'.$request->get('survey');
            //exit;
            $survey = ($request->get('survey') === null) ? $jwt[env('AUTH0_AUD')]->survey: $request->get('survey');
            $survey = $this->buildSurvey($survey,$client);
            $dbQuery = DB::table($client.'_'.$survey);
            $dbQuery->where('etapaencuesta', 'P2');
            $dbQuery->where('contenido','!=', '');
            $dbQuery->whereBetween('nps', [0,6]);
            //$dbQuery = DB::table('dataSuite_banmedica');
            
            // Filtramos
            if($request->get('filters') !== null) {
                $filters = (json_decode($request->get('filters')));
                if ($filters) {
                    foreach ($filters as $key => $value) {
                        if(in_array($value->key, $validFilterKeys)) {
                            $dbQuery->where($value->key,  $value->value);
                        }
                    }
                }
            }

            // Filtramos por query de busqueda
            if($request->get('search') !== null) {
                $search = $request->get('search');
                $dbQuery->where("nom", "like", "%".$search."%");
            }

            // Filtramos por fecha
            if($request->get('startDate') !== null) {
                $startDate = $request->get('startDate');
                // TODO validar startDate
                $dbQuery->where('date', '>', $startDate);
            } else {
                $dbQuery->where('date', '>', date('Y-m-d', strtotime(date('Y-m-d')."-7 days")));
            }

            if($request->get('endDate') !== null) {
                $endDate = $request->get('endDate');
                // TODO validar endDate
                $dbQuery->where('date', '<', $endDate);
            }

            // Ordenamos
            if($request->get('orders') !== null) {
                $orders = (json_decode($request->get('orders')));
                if ($orders) {
                    foreach ($orders as $key => $value) {
                        if(
                            in_array($value->key, $validOrderKeys) &&
                            ($value->value === "asc" || $value->value === "desc")
                        ) {
                            $dbQuery->orderBy($value->key,  $value->value);
                        }
                    }
                }
            }

            $resp = $dbQuery->paginate(10);
            
        }catch (\Throwable $e) {
            return $data = [
                'datas'  => $e->getMessage(),
                'status'=> Response::HTTP_UNPROCESSABLE_ENTITY
            ];
        }
        $data=[];
        if($resp)
        {
            foreach ($resp as $key => $value) {
                // print_r($value);
                 $journey=[];
                for ($i=1; $i <= 11; $i++) { 
                    //echo($value->csat1);
                    $csatx = 'csat'.$i;
                    if(isset($value->$csatx)){
                        
                        $journey[] = array(
                            'text' => $this->getInformationDriver($survey.'_'.$csatx),
                            'value' => $value->$csatx,
                        );
                    }
                }
                $data[] = [
                    "ticket" => $value->ticket,
                    "client" => array(
                        'name' => $value->nom,
                        'rut'  => $value->rut
                    ),
                    "ltv"       => 'N/A',
                    "canal"     => $value->canal,
                    "cxv"       => 'N/A',
                    "nps"       => $value->nps,
                    "csat"      => $value->csat,
                    "cbi"       => 'N/A',
                    "status"    => $value->estado,
                    "npsCierre" => $value->nps_cierre,
                    "churnProb" => $this->calculateProb($value->csat,$value->nps),
                    "churnPred" => 'N/A',
                    "tableName" =>$value->tableName,
                    "visita"    => $value->visita,
                    "estapaEncuesta"=> $value->etapaencuesta,
                    "comentarios" => array(
                        'date'      => $value->fechacarga, 
                        'content'   => $value->contenido,
                    ),
                    "journeyMap" => $journey,
                    "observaciones" => array(
                        "date"    => $value->fechaCierre,
                        "content"   => $value->Content
                    ),
                    "ejecutivo" => array(
                        "name" => $value->nombreEjecutivo
                    ),
                ];
            }
            
            $datos = [
            'client'            => ($client == 'VID001') ? 'Vida Tres' : 'Banmedica',
            "total"         => $resp->total(),
            "lastPage"      => $resp->lastPage(),
            "perPage"       => $resp->perPage(),
            "currentPage"   => $resp->resolveCurrentPage(),
            "nextPage"      => $resp->nextPageUrl(),
            "data"          => $data
        ];
        
         if($resp->total() == 0){
            $datos='No existen datos para mostrar';
        }
        }
       
        $data = [
            'datas'      => $datos,
            'status'    => Response::HTTP_OK
        ];
        return $data;
    }
    public function resumenIndicator999($jwt)
    {
        try{
            $resp = DB::table('dataSuite_banmedica')->paginate(10);
        }catch (\Throwable $e) {
            return $data = [
                'data'  => $e->getMessage(),
                'status'=> Response::HTTP_UNPROCESSABLE_ENTITY
            ];
        }
        if($resp)
        {
            foreach ($resp as $key => $value) {
                $data[$key+1] = [
                    "client" => array(
                        'name' => $value->nom,
                        'rut' => $value->rut
                    ),
                    "ltv"   => 7,
                    "canal" => $value->canal,
                    "cxv"   => 8,
                    "nps"   => $value->nps,
                    "cbi"   => $value->csat,
                    "status" => $value->estado,
                    "npsCierre" => $value->nps_cierre,
                    "churnProb" => 5,
                    "churnPred" => 3,
                    "comentarios" => array(
                        'date'      => date('Y-m-d'),
                        'content'   => $value->conenido,
                    ),
                    "journeyMap" => array(
                        "name"  => [
                            'csat',
                            'csat1',
                            'csat2',
                        ],
                        "uv"    => [
                            34,
                            22,
                            44
                        ]
                    ),
                    "observaciones" => array(
                        "date"  => date('Y-m-d'),
                        "content"    => $value->Content
                    ),
                    "ejecutivo" => array(
                        "name" => 'EJECUTIVO'
                    )
                ];
            }
        }
        $datos = [
            "total"         =>  $resp->total(),
            "lastPage"      => $resp->lastPage(),
            "perPage"       => $resp->perPage(),
            "currentPage"   => $resp->resolveCurrentPage(),
            "nextPage"      => $resp->nextPageUrl(),
            "data"          => $data
        ];
        $data = [
            'datas'      => $datos,
            'status'    => Response::HTTP_OK
        ];
        return $data;
    }
    
    
     public function calculateProb($cbi, $nps){
        if(0 == $nps || $nps == 1 ){
            //$hight++;
            return  $this->_high;
        }elseif($nps >= 2 && $nps <= 4){
            //$med++;
            return  $this->_medium;
        }elseif($nps >= 5 && $nps <= 6){
            //$low++;
            return  $this->_low;
        }
    }
    
    
    private function sendedmail($nombre,$mail,$hash,$encuesta){
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL =>'https://customerscoops.com/srv/suitemail/sendmail.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('nom' => $nombre,'mail' => $mail,'token' => $hash,'encuesta' => $encuesta),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        echo $response;
    }
    public function getInformationDriver($searchDriver){
        $datas = [
            "banamb_csat1" => "Satisfacción agendamiento",
		    "banamb_csat2" => "Satisfacción exámen",
            "banamb_csat3" => "Amabilidad personal administrativo",
            "banamb_csat4" => "Emisión de bonos",
            "banamb_csat5" => "Puntualidad personal médico",
            "banamb_csat6" => "Amabilidad personal médico",
            "banamb_csat7" => "Información entregada por médico",
            "banamb_csat8" => "Solicitud reembolso",
            "banamb_csat9" => "Revisión estado reembolso",
            "banamb_csat10" => "Tiempo espera reembolso",
            
            "banasi_csat1" => "Tiempo espera atención",
            "banasi_csat2" => "Empatía del ejecutivo",
            "banasi_csat3" => "Conocimientos del ejecutivo",
            "banasi_csat4" => "Solución a consulta/solicitud",
            "banasi_csat5" => "Claridad información entregada",
            "banasi_csat6" => "Rapidez resolución trámite/dudas",
            "banasi_csat7" => "Satisfacción canal remoto",
            "banasi_csat8" => "Satisfacción resolución requerimiento",
            
            "bancon_csat1" => "Tiempo espera atención",
            "bancon_csat2" => "Empatía del ejecutivo",
            "bancon_csat3" => "Conocimientos del ejecutivo",
            "bancon_csat4" => "Respuesta/solución consulta",
            "bancon_csat5" => "Rapidez resolución trámite/dudas",
            "bancon_csat6" => "Satisfacción resolución requerimiento",
            
            "banges_csat1" => "Calidad de prestadores",
            "banges_csat2" => "Disponibilidad de horarios",
            "banges_csat3" => "Tiempo, solicitud de hora y fecha disponible", //Tiempo, hora y fecha disponible
            "banges_csat4" => "Tiempo espera atención",
            "banges_csat5" => "Calidad y disponibilidad médica",
            "banges_csat6" => "Facilidad de acceso a medicamentos",
            "banges_csat7" => "Tiempo, Alta médica y consulta GES",
            "banges_csat8" => "Proceso de pago",
             "banges_csat9" => "Tiempo, alta medica y seguimiento",
            
            "banhos_csat1" => "Información sobre orden médica", //Disponibilidad de información sobre los pasos a seguir en la Isapre al recibir la orden médica para hospitalizarte u operarte.
            "banhos_csat2" => "Claridad información presupuesto", //Claridad y detalle de la información contenida en el presupuesto entregado por la Isapre .
            "banhos_csat3" => "Asesoria sobre presupuesto",
            "banhos_csat4" => "Proceso cambio a Isapre",
            "banhos_csat5" => "Información de operación",
            "banhos_csat6" => "Proceso licencia médica",
            "banhos_csat7" => "Notificaciones estado de cuenta",
            "banhos_csat8" => "Proceso de pago",
            
            "banlic_csat1" => "Comprensión información licencia médica",
            "banlic_csat2" => "Plazo resolución licencia médica",
            "banlic_csat3" => "Plazo pago licencia médica",
            "banlic_csat4" => "Comprensión, no autorización licencia",
            "banlic_csat5" => "Conformidad, no autorización licencia",
            "banlic_csat6" => "Posibilidad de apelar a resolución",
            "banlic_csat7" => "Tiempo, presentación licencia y resolución",
            "banlic_csat8" => "Tiempo, resolución licencia y pago",
            "banlic_csat9" => "Asesoría ejecutivo de la sucursal",
            "banlic_csat10" => "Asesoría ejecutivo de Contact Center",

            "banmod_csat1" => "Cantidad de información",
            "banmod_csat2" => "Claridad y precisión de información",
            "banmod_csat3" => "Asesoría entregada",
            "banmod_csat4" => "Empatía del ejecutivo",
            "banmod_csat5" => "Simplicidad y rapidez del proceso",
            "banmod_csat6" => "Comunicación estado de solicitud",
            "banmod_csat7" => "Tiempo modificación y resolución",
            
            "banrel_csat1" => "Cobertura de plan de salud",
            "banrel_csat2" => "Cuidado salud",
            "banrel_csat3" => "Tiempo respuesta solicitudes",
            "banrel_csat4" => "Resolución de requerimientos",
            "banrel_csat5" => "Fácil uso beneficios de plan",
            "banrel_csat6" => "Fácil comunicación",
            "banrel_csat7" => "Información fácil de entender",
            "banrel_csat8" => "Información transparente",
            "banrel_csat9" => "Iniciativas y mejoras",
            "banrel_csat10" => "Probabilidad continuar Banmédica",

            "bansuc_csat1" => "Espacio físico",
            "bansuc_csat2" => "Tiempo de espera",
            "bansuc_csat3" => "Empatía del ejecutivo",
            "bansuc_csat4" => "Orientación entregada ejecutivo",
            "bansuc_csat5" => "Respuesta/solución consulta",
            "bansuc_csat6" => "CLaridad información y documentación",
            "bansuc_csat7" => "Rapidez resolución trámite/dudas",
            "bansuc_csat8" => "Resolución exitosa trámite/duda",
            
            "bantel_csat1" => "Tiempo de espera",
            "bantel_csat2" => "Amabilidad personal médico",
            "bantel_csat3" => "Instrucciones al paciente",
            "bantel_csat4" => "Satisfacción general de atención",
            "bantel_csat5" => "Atención resolutiva",
            "bantel_csat6" => "Disponibilidad agendar servicio",
            "bantel_csat7" => "Calidad de conexión",
            
            "banven_csat1" => "Cantidad información sobre planes",
            "banven_csat2" => "Información clara sobre planes",
            "banven_csat3" => "Facilidad uso simulador de plan",
            "banven_csat4" => "Tiempo envío formulario y contacto",
            "banven_csat5" => "Claridad información ejecutivo",
            "banven_csat6" => "Asesoría ejecutivo",
            "banven_csat7" => "Confiabilidad ejecutivo",
            "banven_csat8" => "Empatía ejecutivo",
            "banven_csat9" => "Conocimiento ejecutivo",
            "banven_csat10" => "Simple proceso de afiliación",
            "banven_csat11" => "Probabilidad continuar Banmédica",
            
            "banweb_csat1" => "Facilidad de búsqueda",
            "banweb_csat2" => "Claridad información en página web",
            "banweb_csat3" => "Simplicidad de trámites",
            "banweb_csat4" => "Posibilidad seguimiento trámites",
            "banweb_csat5" => "Resolución satisfactoria requerimiento",

        ];
        if(array_key_exists($searchDriver, $datas)){
            return $datas[$searchDriver];
        }
        if(!array_key_exists($searchDriver, $datas)){
            $complet = explode("_",$searchDriver);
            return $complet[1];
        }
        //return $searchDriver;
    }
    
}