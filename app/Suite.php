<?php namespace App;

use Validator;
use Freshwork\ChileanBundle\Rut;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
//use DB;
use Illuminate\Support\Facades\DB;

class Suite
{
    private $_high                  = 'ALTA';
    private $_medium                = 'MEDIA';
    private $_low                   = 'BAJA';
    private $_activeSurvey          = 'banamb';
    private $_jwt;
    private $_dbSelected;
    private $_startMinNps;
    private $_startMaxNps;
    private $_nameClient;
    private $_daysActiveSurvey;
    private $_dateStartClient;
    
    public function __construct($_jwt)
    {
        $this->_jwt = $_jwt;
        $this->setDetailsClient($this->_jwt[env('AUTH0_AUD')]->client);
        //echo $this->_jwt[env('AUTH0_AUD')]->client;
        //$this->nameDbSelected($this->_jwt[env('AUTH0_AUD')]->client);
        //$this->minMaxIndicatorNps($this->_jwt[env('AUTH0_AUD')]->client);
    }

    public function getDBSelected(){
        return $this->_dbSelected;
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
            $resp = DB::table($this->_dbSelected.'.'.'adata_'.substr($request->survey,0,3).'_'.substr($request->survey,3,6).'_start')->where('id', $request->ticket)->update(['estado_close' => $request->status, 'det_close' => $request->detail, 'fec_close'=>date('Y-m-d')]);
            //echo $resp;
            if($resp===1){
                $namev = DB::table($this->_dbSelected.'.'.'adata_'.substr($request->survey,0,3).'_'.substr($request->survey,3,6).'_start')->where('id', $request->ticket)->first();
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
    private function getCompany($client)
    {
        $codCustomer = $client;
        if($client == 'vid'){
            $codCustomer = 'VID001';
        }
        if($client == 'ban'){
            $codCustomer = 'BAN001';
        }
        return $codCustomer;
    }
    public function getSurvey($request,$jwt)
    {   

        try{
            //$codCustomer = ($jwt[env('AUTH0_AUD')]->client === null) ? 'BAN001' : $jwt[env('AUTH0_AUD')]->client;
            $codCustomer = $jwt[env('AUTH0_AUD')]->client;
            //echo $codCustomer;exit;  
            if($request->get('company') !== null){
                $codCustomer = $this->getCompany($request->get('company'));
            }
            $db = DB::table($this->_dbSelected.'.'.'survey')->where('codCustomer', $codCustomer)->where('activeSurvey', 1);
            if (isset($jwt[env('AUTH0_AUD')]->surveysActive)) {
                foreach ($jwt[env('AUTH0_AUD')]->surveysActive as $key => $value) {
                    $surv[] = $value; 
                }
                $db->whereIn('codDbase',$surv);
                unset($surv);
            }

            $resp = $db->get();
            //$codCustomer = ($request->get('company') !== null) ? $request->get('company'): $jwt[env('AUTH0_AUD')]->client;
            //$resp = DB::table($this->_dbSelected.'.'.'survey')->where('codCustomer', $codCustomer)->where('activeSurvey', 1)->get();
            //echo $resp;exit;  
            //dd(\DB::getQueryLog());
            if($codCustomer == 'TRA001')
                $resp = DB::table($this->_dbSelected.'.'.'survey')->where('codCustomer', $codCustomer)->where('activeSurvey', 1)->where('codsurvey','TRA_VIA')->orWhere('codsurvey','TRA_COND')->get();
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
                ];
            }
        }
        $data = [
            'datas'     => isset($surveys) ? $surveys: 'NO ENCONTRAMOS INFORMACION',
            'status'    => Response::HTTP_OK
        ];
        //print_r($data);exit;
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
            //$client = ($request->get('company') !== null) ? $request->get('company'): $jwt[env('AUTH0_AUD')]->client;
            $client = $jwt[env('AUTH0_AUD')]->client;
     
            if($request->get('company') !== null){
                $client = $this->getCompany($request->get('company'));
            }
            
            $survey = ($request->get('survey') === null) ? $jwt[env('AUTH0_AUD')]->survey: $request->get('survey');
            $survey = $this->buildSurvey($survey,$client);
            
            $dbQuery = DB::table($this->_dbSelected.'.'. $client . '_' . $survey);
            $dbQuery->where('etapaencuesta', 'P2');
            $dbQuery->where('contenido','!=', '');
            $dbQuery->where('date','>=', $this->_dateStartClient);
            $resp = $dbQuery->whereBetween('nps', [$this->_startMinNps,$this->_startMaxNps])->get();
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
            $closedRate = round(($ticketClosed / $ticketCreated) * 100,1);
        return [
            'datas'  => [
                'client'            => $this->_nameClient,
                'survey'            => $survey,
                'startCalendar'     => $this->_dateStartClient,
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
    public function resumenIndicator($request, $jwt)
    {
        //echo $this->_jwt[env('AUTH0_AUD')]->client;
        $validFilterKeys    = array("nps","csat","estado", "dateSchedule", "nps_cierre"); // <-- keys habilitadas para filtrar
        $validOrderKeys     = array("nps", "date","csat"); // <-- keys habilitadas para Ordenar
        
        try{
            //$client = ($request->get('company') !== null) ? $request->get('company'): $jwt[env('AUTH0_AUD')]->client;
            $client = $jwt[env('AUTH0_AUD')]->client;
            //echo $request->get('company');
            if($request->get('company') !== null){
                $client = $this->getCompany($request->get('company'));
            }
            
            $survey = ($request->get('survey') === null) ? $jwt[env('AUTH0_AUD')]->survey: $request->get('survey');
            //echo $jwt[env('AUTH0_AUD')]->email;exit;
            $survey = $this->buildSurvey($survey,$client);
            $dbQuery = DB::table($this->_dbSelected.'.'.$client.'_'.$survey);
            //echo $this->_dbSelected.'.'.$client.'_'.$survey;
            
            $dbQuery->where('etapaencuesta', 'P2');
            $dbQuery->where('contenido','!=', '');
            if($client != 'BAN001' && $client != 'VID001')
                $dbQuery->whereBetween('nps', [$this->_startMinNps,$this->_startMaxNps]);
            $dbQuery->where('date','>=', $this->_dateStartClient);
            
            if($client == 'BAN001' || $client == 'VID001')
                if(in_array('Loyalty',$jwt[env('AUTH0_AUD')]->roles)){
                    $dbQuery->where('ejecutivo', $jwt[env('AUTH0_AUD')]->email);
                }
            
            // Filtramos
            if($request->get('filters') !== null) {
                $filters = (json_decode($request->get('filters')));
                if ($filters) {
                    foreach ($filters as $key => $value) {
                        if($value->key == 'typeClient')
                        {
                            if($value->value == 'detractor')
                                $dbQuery->whereBetween('nps', [0,6]);
                            if($value->value == 'neutral')
                                $dbQuery->whereBetween('nps', [7,8]);
                            if($value->value == 'promotor')
                                $dbQuery->whereBetween('nps', [9,10]);
                        }
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
                $dbQuery->where('date', '>', date('Y-m-d', strtotime(date('Y-m-d')."$this->_daysActiveSurvey days")));
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
                    "survey" => $survey,
                    "client" => array(
                        'name' => $value->nom,
                        'rut'  => $value->rut,
                        'rut2'  => (isset($value->rut2)) ? $value->rut2 : '',
                        'phone' => (isset($value->phone)) ?  $value->phone : '',
                        'celu' => (isset($value->celu)) ?  $value->celu : '',
                        'dateSchedule' =>  (isset($value->dateSchedule)) ? date('d-m-Y', strtotime($value->dateSchedule)) : '',
                        'timeSchedule' =>(isset($value->timeSchedule)) ?  date('H:i', strtotime($value->timeSchedule)) : ''
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
                    "subStatus1" => (isset($value->field_1)) ? $value->field_1 : '',
                    "subStatus2" => (isset($value->field_2)) ? $value->field_2 : '',
                    "caso"       => (isset($value->field_3)) ? $value->field_3 : '',
                    "cliente_det_close"=> (isset($value->cliente_det_close)) ? $value->cliente_det_close : '',
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
            'client'        => $this->_nameClient,
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

    protected function sendedEmail($nombre,$mail,$hash,$encuesta){
        $this->sendedmail($nombre,$mail,$hash,$encuesta);
    }

    private function sendedmail($nombre,$mail,$hash,$encuesta){
        $endpoint = 'sendmail.php';
        if(substr($encuesta,0,3) == 'vid'){
            $endpoint = 'sendmail2.php';
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL =>'https://customerscoops.com/srv/suitemail/'.$endpoint,
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
            "banamb_csat1" => "Satisfacci??n agendamiento",
		    "banamb_csat2" => "Satisfacci??n ex??men",
            "banamb_csat3" => "Amabilidad personal administrativo",
            "banamb_csat4" => "Emisi??n de bonos",
            "banamb_csat5" => "Puntualidad personal m??dico",
            "banamb_csat6" => "Amabilidad personal m??dico",
            "banamb_csat7" => "Informaci??n entregada por m??dico",
            "banamb_csat8" => "Solicitud reembolso",
            "banamb_csat9" => "Revisi??n estado reembolso",
            "banamb_csat10" => "Tiempo espera reembolso",
            
            "banasi_csat1" => "Tiempo espera atenci??n",
            "banasi_csat2" => "Empat??a del ejecutivo",
            "banasi_csat3" => "Conocimientos del ejecutivo",
            "banasi_csat4" => "Soluci??n a consulta/solicitud",
            "banasi_csat5" => "Claridad informaci??n entregada",
            "banasi_csat6" => "Rapidez resoluci??n tr??mite/dudas",
            "banasi_csat7" => "Satisfacci??n canal remoto",
            "banasi_csat8" => "Satisfacci??n resoluci??n requerimiento",
            
            "bancon_csat1" => "Tiempo espera atenci??n",
            "bancon_csat2" => "Empat??a del ejecutivo",
            "bancon_csat3" => "Conocimientos del ejecutivo",
            "bancon_csat4" => "Respuesta/soluci??n consulta",
            "bancon_csat5" => "Claridad de informaci??n entregada",
            "bancon_csat6" => "Rapidez resoluci??n tr??mite/dudas",
            "bancon_csat7" => "Satisfacci??n resoluci??n requerimiento",
            
            "banges_csat1" => "Calidad de prestadores",
            "banges_csat2" => "Disponibilidad de horarios",
            "banges_csat3" => "Tiempo, solicitud de hora y fecha disponible", //Tiempo, hora y fecha disponible
            "banges_csat4" => "Tiempo espera atenci??n",
            "banges_csat5" => "Calidad y disponibilidad m??dica",
            "banges_csat6" => "Facilidad de acceso a medicamentos",
            "banges_csat7" => "Tiempo, Alta m??dica y consulta GES",
            "banges_csat8" => "Proceso de pago",
            "banges_csat9" => "Tiempo, alta medica y seguimiento",
            
            "banhos_csat1" => "Informaci??n sobre orden m??dica", //Disponibilidad de informaci??n sobre los pasos a seguir en la Isapre al recibir la orden m??dica para hospitalizarte u operarte.
            "banhos_csat2" => "Claridad informaci??n presupuesto", //Claridad y detalle de la informaci??n contenida en el presupuesto entregado por la Isapre .
            "banhos_csat3" => "Asesoria sobre presupuesto",
            "banhos_csat4" => "Proceso cambio a Isapre",
            "banhos_csat5" => "Informaci??n de operaci??n",
            "banhos_csat6" => "Proceso licencia m??dica",
            "banhos_csat7" => "Notificaciones estado de cuenta",
            "banhos_csat8" => "Proceso de pago",
            
            "banlic_csat1" => "Comprensi??n informaci??n licencia m??dica",
            "banlic_csat2" => "Plazo resoluci??n licencia m??dica",
            "banlic_csat3" => "Plazo pago licencia m??dica",
            "banlic_csat4" => "Comprensi??n, no autorizaci??n licencia",
            "banlic_csat5" => "Conformidad, no autorizaci??n licencia",
            "banlic_csat6" => "Posibilidad de apelar a resoluci??n",
            "banlic_csat7" => "Tiempo, presentaci??n licencia y resoluci??n",
            "banlic_csat8" => "Tiempo, resoluci??n licencia y pago",
            "banlic_csat9" => "Asesor??a ejecutivo de la sucursal",
            "banlic_csat10" => "Asesor??a ejecutivo de Contact Center",

            "banmod_csat1" => "Cantidad de informaci??n",
            "banmod_csat2" => "Claridad y precisi??n de informaci??n",
            "banmod_csat3" => "Asesor??a entregada",
            "banmod_csat4" => "Empat??a del ejecutivo",
            "banmod_csat5" => "Simplicidad y rapidez del proceso",
            "banmod_csat6" => "Comunicaci??n estado de solicitud",
            "banmod_csat7" => "Tiempo modificaci??n y resoluci??n",
            
            "banrel_csat1" => "Cobertura de plan de salud",
            "banrel_csat2" => "Cuidado salud",
            "banrel_csat3" => "Tiempo respuesta solicitudes",
            "banrel_csat4" => "Resoluci??n de requerimientos",
            "banrel_csat5" => "F??cil uso beneficios de plan",
            "banrel_csat6" => "F??cil comunicaci??n",
            "banrel_csat7" => "Informaci??n f??cil de entender",
            "banrel_csat8" => "Informaci??n transparente",
            "banrel_csat9" => "Iniciativas y mejoras",
            "banrel_csat10" => "Probabilidad continuar Banm??dica",

            "bansuc_csat1" => "Espacio f??sico",
            "bansuc_csat2" => "Tiempo de espera",
            "bansuc_csat3" => "Empat??a del ejecutivo",
            "bansuc_csat4" => "Orientaci??n entregada ejecutivo",
            "bansuc_csat5" => "Respuesta/soluci??n consulta",
            "bansuc_csat6" => "Claridad informaci??n y documentaci??n",
            "bansuc_csat7" => "Rapidez resoluci??n tr??mite/dudas",
            "bansuc_csat8" => "Resoluci??n exitosa tr??mite/duda",
            
            "bantel_csat1" => "Tiempo de espera",
            "bantel_csat2" => "Amabilidad personal m??dico",
            "bantel_csat3" => "Instrucciones al paciente",
            "bantel_csat4" => "Satisfacci??n general de atenci??n",
            "bantel_csat5" => "Atenci??n resolutiva",
            "bantel_csat6" => "Disponibilidad agendar servicio",
            "bantel_csat7" => "Calidad de conexi??n",
            
            "banven_csat1" => "Cantidad informaci??n sobre planes",
            "banven_csat2" => "Informaci??n clara sobre planes",
            "banven_csat3" => "Facilidad uso simulador de plan",
            "banven_csat4" => "Tiempo env??o formulario y contacto",
            "banven_csat5" => "Claridad informaci??n ejecutivo",
            "banven_csat6" => "Asesor??a ejecutivo",
            "banven_csat7" => "Confiabilidad ejecutivo",
            "banven_csat8" => "Empat??a ejecutivo",
            "banven_csat9" => "Conocimiento ejecutivo",
            "banven_csat10" => "Simple proceso de afiliaci??n",
            "banven_csat11" => "Probabilidad continuar Banm??dica",
            
            "banweb_csat1" => "Facilidad de b??squeda",
            "banweb_csat2" => "Claridad informaci??n en p??gina web",
            "banweb_csat3" => "Simplicidad de tr??mites",
            "banweb_csat4" => "Posibilidad seguimiento tr??mites",
            "banweb_csat5" => "Resoluci??n satisfactoria requerimiento",
            
            
            //MUTUAL
            "muteri_csat1" => "Escucha y comprensi??n por parte del profesional",
            "muteri_csat2" => "Informaci??n y recomendaci??n del profesional",
            "muteri_csat3" => "Preocupaci??n del profesional en la informaci??n",
            "muteri_csat4" => "Claridad del profesional para resolver dudas",
            "muteri_csat5" => "Claridad de informaci??n de qu?? hacer y d??nde acudir",
            "muteri_csat6" => "Modalidad de la atenci??n Telef??nica",
            
            "mutges_csat1" => "Resoluci??n de la solicitud",
            "mutges_csat2" => "Equipo de ejecutivos y resolutores",
            "mutges_csat3" => "Canales para generar una solicitud ",
            "mutges_csat4" => "Claridad en la respuesta recibida",
            "mutges_csat5" => "Plazo de entrega de respuesta a tu solicitud",
            
            "mutbe_csat1" => "Claridad de los documentos para que se emita el pago",
            "mutbe_csat2" => "Facilidad para entregar la documentaci??n requerida",
            "mutbe_csat3" => "Informaci??n del proceso de pago",
            "mutbe_csat4" => "Cumplimiento de la fecha y medio de pago",
            "mutbe_csat5" => "Disponibilidad de canales para el pago de licencias",
            
            "mutreh_csat1" => "Tiempo espera para tu atenci??n",
            "mutreh_csat2" => "Amabilidad profesionales Mutual",
            "mutreh_csat3" => "Claridad informaci??n entregada",
            "mutreh_csat4" => "Instalaciones y equipamiento para atenci??n",
            "mutreh_csat5" => "Resultados obtenidos con rehabilitaci??n",
            
            "muturg_csat1" => "Tiempo espera para tu atenci??n",
            "muturg_csat2" => "Amabilidad profesionales Mutual",
            "muturg_csat3" => "Amabilidad personal m??dico",
            "muturg_csat4" => "Claridad informaci??n entregada",
            "muturg_csat5" => "Instalaciones y equipamiento para atenci??n",
            
            "muthos_csat1" => "Amabilidad personal cl??nico",
            "muthos_csat2" => "Amabilidad personal m??dico",
            "muthos_csat3" => "Claridad informaci??n entregada",
            "muthos_csat4" => "Resoluci??n problema salud",
            "muthos_csat5" => "Instalaciones y equipamiento para atenci??n",
            
            "mutcas_csat1" => "Tiempo espera para tu atenci??n",
            "mutcas_csat2" => "Amabilidad profesionales Mutual",
            
            "mutamb_csat1" => "Tiempo espera para tu atenci??n",
            "mutamb_csat2" => "Amabilidad profesionales Mutual",
            "mutamb_csat3" => "Amabilidad personal m??dico",
            "mutamb_csat4" => "Claridad informaci??n entregada",
            "mutamb_csat5" => "Comodidad de instalaciones",
            
            "mutimg_csat1" => "Tiempo espera para tu atenci??n",
            "mutimg_csat2" => "Amabilidad profesionales Mutual",
            "mutimg_csat3" => "Amabilidad personal cl??nico",
            "mutimg_csat4" => "Comodidad recepci??n",
            "mutimg_csat5" => "Claridad informaci??n entregada",

            "mutcet_csat1" => "csat1",
            "mutcet_csat2" => "csat2",
            "mutcet_csat3" => "csat3",
            "mutcet_csat4" => "csat4",
            "mutcet_csat5" => "csat5",

            "mutred_csat1" => "csat1",
            "mutred_csat2" => "csat2",
            "mutred_csat3" => "csat3",
            "mutred_csat4" => "csat4",
            
            //TRANSVIP
            "travia_csat1" => "Creaci??n reserva",
            "travia_csat2" => "Tiempo para encontrar conductor",
            "travia_csat3" => "Coordinaci??n proceso embarque aeropuerto",
            "travia_csat4" => "Puntualidad del servicio",
            "travia_csat5" => "Tiempo llegada veh??culo",
            "travia_csat6" => "Espera veh??culo en aeropuerto",
            "travia_csat7" => "Conducci??n segura",
            "travia_csat8" => "Medidas Covid",
            "travia_csat9" => "Ruta y tiempo de traslado",
            "travia_csat10" => "Atenci??n del Conductor",
            "travia_csat11" => "Conducci??n",

            "tracond_csat1" => "Proceso de inscripci??n, registro y activaci??n",
            "tracond_csat2" => "Orientaci??n inicial",
            "tracond_csat3" => "Aplicaci??n Conductores",
            "tracond_csat4" => "Medidas de identificaci??n y verificaci??n de pasajeros",
            "tracond_csat5" => "Central de operaciones - Tr??fico",
            "tracond_csat6" => "Soporte",
            "tracond_csat7" => "Pago de producci??n mensual",

            //JETSMART
            "jetvia_csat1"  => "Proceso de compra online/web realizado",
            "jetvia_csat2"  => "Proceso de pago al comprar tu boleto",
            "jetvia_csat3"  => "Informaci??n en email de confirmaci??n de compra",
            "jetvia_csat4"  => "Informaci??n recibida posterior al proceso de compra",
            "jetvia_csat5"  => "Check in realizado",
            "jetvia_csat6"  => "Proceso de registro de equipaje",
            "jetvia_csat7"  => "Abordaje del vuelo realizado",
            "jetvia_csat8"  => "Vuelo realizado",
            "jetvia_csat9"  => "Momento de llegada del vuelo",
            "jetvia_csat10" => "Servicio al cliente",
            
            "jetcom_csat1"  => "Utilizar el sitio web",
            "jetcom_csat2"  => "Selecci??n de pasajes",
            "jetcom_csat3"  => "Selecci??n y compra de equipaje",
            "jetcom_csat4"  => "Selecci??n de asientos",
            "jetcom_csat5"  => "Proceso de pago",
            "jetcom_csat6"  => "Informaci??n en email de confirmaci??n de compra",

            "jetvue_csat1"  => "Uso de sitio Web",
            "jetvue_csat2"  => "Selecci??n de pasajes",
            "jetvue_csat3"  => "Selecci??n de equipaje",
            "jetvue_csat4"  => "Selecci??n de asientos",
            "jetvue_csat5"  => "Proceso de pago",
            "jetvue_csat6"  => "Informaci??n email",

            "jetcpe_csat1"  => "Utilizar el sitio web",
            "jetcpe_csat2"  => "Selecci??n de pasajes",
            "jetcpe_csat3"  => "Selecci??n y compra de equipaje",
            "jetcpe_csat4"  => "Selecci??n de asientos",
            "jetcpe_csat5"  => "Proceso de pago",
            "jetcpe_csat6"  => "Informaci??n en email de confirmaci??n de compra",
        
        ];
        
        if(array_key_exists($searchDriver, $datas)){
            return $datas[$searchDriver];
        }
        if(!array_key_exists($searchDriver, $datas)){
            $complet = explode("_",$searchDriver);
            return $complet[1];
        }
    }
    
    //FUNCIONES PARA CONFIGURACION DE CLIENTES
    private function setDetailsClient($client){
        if($client == 'VID001' || $client == 'BAN001'){
        //if($client == 'VID001' || $client == 'BAN001'){
            $this->_dateStartClient = '2022-01-01';
            $this->_dbSelected   = 'customer_banmedica';
            $this->_startMinNps = 0;
            $this->_startMaxNps = 6;
            $this->_daysActiveSurvey = -15;
            if($client == 'VID001'){
                $this->_nameClient = 'Vida Tres';
            }
            if($client == 'BAN001'){
                $this->_nameClient = 'Banmedica';
            }
        }
        if($client == 'MUT001'){
            $this->_dateStartClient = '2022-01-01';
            $this->_dbSelected  = 'customer_colmena';
            $this->_startMinNps = 0;
            $this->_startMaxNps = 4;
            $this->_nameClient = 'Mutual';
            $this->_daysActiveSurvey = -7;
        }
        if($client == 'DEM001'){
            $this->_dateStartClient = '2021-01-01';
            $this->_dbSelected  = 'customer_demo';
            $this->_startMinNps = 0;
            $this->_startMaxNps = 6;
            $this->_nameClient = 'Demo';
            $this->_daysActiveSurvey = -365;
        }
        if($client == 'TRA001'){
            $this->_dateStartClient = '2022-01-01';
            $this->_dbSelected  = 'customer_colmena';
            $this->_startMinNps = 0;
            $this->_startMaxNps = 6;
            $this->_nameClient = 'Transvip';
            $this->_daysActiveSurvey = -7;
        }
        if($client == 'JET001'){
            $this->_dateStartClient = '2022-01-01';
            $this->_dbSelected  = 'customer_jetsmart';
            $this->_startMinNps = 0;
            $this->_startMaxNps = 6;
            $this->_nameClient = 'JetSmart';
            $this->_daysActiveSurvey = -7;
        }
    }

    private function buildSurvey($survey, $client)
    {
        if($client == 'VID001')
            return 'vid'.substr($survey,3,6);
        if($client == 'BAN001')
            return 'ban'.substr($survey,3,6);
        if($client == 'TRA001')
            return 'tra'.substr($survey,3,6);
        return $survey;
    }
    //FIN CONFIGURACION DE CLIENTES

    // public function setMinNps($value){
    //     $this->_startMinNps = $value;
    // }
    
    // public function setMaxNps($value){
    //     $this->_startMaxNps = $value;
    // }

}