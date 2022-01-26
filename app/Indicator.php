<?php namespace App;

use Validator;
use Freshwork\ChileanBundle\Rut;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use DB;

class Indicator
{
    private $_high ='ALTA';
    private $_medium ='MEDIA';
    private $_low ='BAJA';
    
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
    public function getSurvey($jwt)
    {
        try{
            $codCustomer = ($jwt[env('AUTH0_AUD')]->client === null) ? 'BAN001' : $jwt[env('AUTH0_AUD')]->client;
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
        
        try {
            //$dbQuery = DB::table('customer_banmedica.' . $jwt[env('AUTH0_AUD')]->client . '_' . $request->get('survey'));
            $survey = ($request->get('survey') === null) ? 'banamb': $request->get('survey');
            $dbQuery = DB::table('customer_banmedica.' . $jwt[env('AUTH0_AUD')]->client . '_' . $survey);
            //$dbQuery = DB::table('data_suite');
            //$resp = $dbQuery->where('fechaCarga', '>',  date('Y-m-d', strtotime(date('Y-m-d') . "-7 days")))->get();
            $dbQuery->where('etapaencuesta', 'P2');
            $dbQuery->where('contenido','!=', '');
            $resp = $dbQuery->whereBetween('nps', [0,6])->get();
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
                'ticketCreated'     => (object)['high' =>$high,'medium' =>$medium, 'low' =>$low] ,
                "ticketOpen"        => $ticketOpen,
                "ticketClosed"      => $ticketClosed,
                "closedRate"        => $closedRate,
                "convertionRate"    => $convertionRate,
                "ticketManage"      => $ticketManage,
                "ticketPending"     => $ticketPending,
                "ticketNoContact"   => $ticketNoContact,
            ],
            'status' => Response::HTTP_OK
        ];
    }
    public function resumenIndicator($request, $jwt)
    {
        $validFilterKeys = array("nps"); // <-- keys habilitadas para filtrar
        $validOrderKeys = array("nps", "date"); // <-- keys habilitadas para Ordenar
        
        try{
            /* 
            $client =  $jwt[env('AUTH0_AUD')]->client;
            // TODO validar client
            
            $survey = $request->get('survey') || $jwt[env('AUTH0_AUD')]->survey;
            // TODO validar survey
    
            $dbQuery = DB::table('customer_banmedica.'.$client.'_'.$survey);
            */
            //echo $jwt[env('AUTH0_AUD')]->client.'_'.$request->get('survey');
            //exit;
            $survey = ($request->get('survey') === null) ? 'banamb': $request->get('survey');
            $dbQuery = DB::table($jwt[env('AUTH0_AUD')]->client.'_'.$survey);
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
    
    
     private function calculateProb($cbi, $nps){
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
    private function getInformationDriver($searchDriver){
        $datas = [
            "banamb_csat1" => "Satisfaccion agendamiento",
		    "banamb_csat2" => "Satisfaccion examen",
            "banamb_csat3" => "Amabilidad personal administrativo",
            "banamb_csat4" => "Emision de bonos",
            "banamb_csat5" => "Puntualidad personal medico",
            "banamb_csat6" => "Amabilidad personal medico",
            "banamb_csat7" => "Informacion entregada por medico",
            "banamb_csat8" => "Solicitud reembolso",
            "banamb_csat9" => "Revision estado reembolso",
            "banamb_csat10" => "Tiempo espera reembolso",
            
             "banasi_csat1" => "Tiempo espera atencion",
            "banasi_csat2" => "Empatía del ejecutivo",
            "banasi_csat3" => "Conocimientos del ejecutivo",
            "banasi_csat4" => "Solucion a consulta/solicitud",
            "banasi_csat5" => "Claridad informacion entregada",
            "banasi_csat6" => "Rapidez resolucion tramite/dudas",
            "banasi_csat7" => "Satisfaccion canal remoto",
            "banasi_csat8" => "Satisfaccion resolucion requerimiento",
            
            "bancon_csat1" => "Tiempo espera atencion",
            "bancon_csat2" => "Empatía del ejecutivo",
            "bancon_csat3" => "Conocimientos del ejecutivo",
            "bancon_csat4" => "Respuesta/solucion consulta",
            "bancon_csat5" => "Rapidez resolucion tramite/dudas",
            "bancon_csat6" => "Satisfaccion resolucion requerimiento",
            
            "banges_csat1" => "Calidad de prestadores",
            "banges_csat2" => "Disponibilidad de horarios",
            "banges_csat3" => "Tiempo, solicitud de hora y fecha disponible", //Tiempo, hora y fecha disponible
            "banges_csat4" => "Tiempo espera atencion",
            "banges_csat5" => "Calidad y disponibilidad medica",
            "banges_csat6" => "Facilidad de acceso a medicamentos",
            "banges_csat7" => "Tiempo, Alta médica y consulta GES",
            "banges_csat8" => "Proceso de pago",
            
            "banhos_csat1" => "Informacion sobre orden medica", //Disponibilidad de información sobre los pasos a seguir en la Isapre al recibir la orden médica para hospitalizarte u operarte.
            "banhos_csat2" => "Claridad informacion presupuesto", //Claridad y detalle de la información contenida en el presupuesto entregado por la Isapre .
            "banhos_csat3" => "Asesoria sobre presupuesto",
            "banhos_csat4" => "Proceso cambio a Isapre",
            "banhos_csat5" => "Informacion de operacion",
            "banhos_csat6" => "Proceso licencia medica",
            "banhos_csat7" => "Notificaciones estado de cuenta",
            "banhos_csat8" => "Proceso de pago"

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
  
    
    /*public function resumenIndicator($jwt)
    {
        try{
            $resp = DB::select(DB::raw("SELECT * FROM users"));
        }catch (\Throwable $e) {
            return $data = [
                'data' => $e->getMessage(),
                'status'=> Response::HTTP_UNPROCESSABLE_ENTITY
            ];;
        }
        $resp = array(
            "1"=> array(
                "client" => array(
                    'name' => 'prueba',
                    'rut' => '1111-7',
                ),
                "ltv"   => 7,
                "canal" => 'WEB',
                "cxv"   => 8,
                "nps"   => 9,
                "cbi"   => 3,
                "status" => 'Cerrado',
                "npsCierre" => 7,
                "churnProb" => 5,
                "churnPred" => 3,
                "comentarios" => array(
                    'date'      => date('Y-m-d'),
                    'content'   => 'comentario de prueba',
                ),
                "journeyMap" => array(
                    "name"  => [
                        'csat',
                        'csat1',
                        'csat2',
                    ],
                    "uv"    => [
                        11,
                        34,
                        55
                    ]
                ),
                "observaciones" => array(
                    "date"  => date('Y-m-d'),
                    "content"    => 'observaciones de prueba'
                ),
                "ejecutivo" => array(
                    "name" => 'EJECUTIVO'
                )
            ),
            "2"=> array(
                "client" => array(
                    'name' => 'prueba',
                    'rut' => '1111-7',
                ),
                "ltv"   => 7,
                "canal" => 'WEB',
                "cxv"   => 8,
                "nps"   => 9,
                "cbi"   => 3,
                "status" => 'Cerrado',
                "npsCierre" => 7,
                "churnProb" => 5,
                "churnPred" => 3,
                "comentarios" => array(
                    'date'      => date('Y-m-d'),
                    'content'   => 'comentario de prueba',
                ),
                "journeyMap" => array(
                    "name"  => [
                        'csat',
                        'csat1',
                        'csat2',
                    ],
                    "uv"    => [
                        11,
                        34,
                        55
                    ]
                ),
                "observaciones" => array(
                    "date"  => date('Y-m-d'),
                    "content"    => 'observaciones de prueba'
                ),
                "ejecutivo" => array(
                    "name" => 'EJECUTIVO'
                )
            )
        );
        $data = [
            'data' => $resp,
            'status'=> Response::HTTP_OK
        ];
        return $data;
    }
    */
    
    
  
