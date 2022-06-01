<?php

namespace App;

use Validator;
use Illuminate\Http\Response;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use DB;
use App\Suite;
use App\Generic;
use ArrayObject;
use Carbon\Carbon;
use Mockery\Undefined;

class Dashboard extends Generic
{
    private $_activeSurvey = 'banrel';

    //SE LE APLICAN CUANDO SE UNEN LAS TABLAS, SI SE FILTRAA POR SURVEY NO SE APLICAN %
    private $_porcentageBan         = 0.77;
    private $_porcentageVid         = 0.23;
    private $_periodCxWord          = 2;
    private $expiresAtCache         = '';
    private $_jwt;
    private $_dbSelected;
    private $_initialFilter;
    private $_fieldSelectInQuery;
    private $_minNps;
    private $_maxNps;
    private $_minMediumNps;
    private $_maxMediumNps;
    private $_minMaxNps;
    private $_maxMaxNps;
    private $_obsNps;
    private $_fieldSex;
    private $_imageBan;
    private $_imageVid;
    private $_imageClient;
    private $_maxCsat;
    private $_minCsat;
    private $_minMediumCsat;
    private $_maxMediumCsat;
    private $_minMaxCsat;
    private $_maxMaxCsat;
    private $_anomaliasPain = [];
    private $_anomaliasGain = [];
    private $_anomaliasPainCBI = [];
    private $_anomaliasGainCBI = [];
    private $_valueMinAnomalias = 0;
    private $_valueMaxAnomalias = 0;
    private $_valueMinAnomaliasCBI = -10;
    private $_valueMaxAnomaliasCBI = 10;
    private $_valueMinAnomaliasText = -20;
    private $_valueMaxAnomaliasText = 30;
    private $_valueAnomaliasPorcentajeText = 30;

    /* Función para saber el dia */

    protected function getFirstMond()
    {
        $day = date("N");
        $resta = 0;
        switch ($day) 
        {
            case 1:
                $resta = 1;
                break;
            case 2:
                $resta = 2;
                break;
            case 3:
                $resta = 3;
                break;
            case 4:
                $resta = 4;
                break;
            case 5:
                $resta = 5;
                break;
            case 6:
                $resta = 6;
                break;
        }
        return date('Y-m-d', strtotime(date('Y-m-d') . "- $resta day")); //Aca obtengo la fecha del lunes de la semana
    }

    /* Fin funcion para saber el dia */

    public function getDBSelect()
    {
        return $this->_dbSelected;
    }

    public function getParams($field)
    {
        return $this->$field;
    }

    public function __construct($_jwt)
    {
        $this->_jwt = $_jwt;
        $this->setDetailsClient($_jwt[env('AUTH0_AUD')]->client);
        //$generalInfo = Generic::configInitial($jwt[env('AUTH0_AUD')]->client);
        $this->expiresAtCache = Carbon::now()->addHours(24);
    }

    protected function getFielInDb($survey)
    {
        $npsInDb = 'nps';
        return $npsInDb;
    }

    protected function contentfilter($data, $type)
    {
        $content = [];
        foreach ($data as $key => $value) {
            $namefilters = $this->textsfilters($type . $value->$type);
            $content[($namefilters !== false) ? $namefilters : $value->$type] = $value->$type;
        }
        return ($content);
    }

    private function textsfilters($cod)
    {
        $arr =  [
            'region1'   => 'Tarapacá',
            'region2'   => 'Antofagasta',
            'region3'   => 'Atacama',
            'region4'   => 'Coquimbo',
            'region5'   => 'Valparaíso',
            'region6'   => "O'Higgins",
            'region7'   => 'El Maule',
            'region8'   => 'El Bío Bío',
            'region9'   => 'La Araucanía',
            'region10'  => 'Los Lagos',
            'region11'  => 'Aysén',
            'region12'  => 'Magallanes y Antártica Chilena',
            'region13'  => 'Región Metropolitana de Santiago',
            'region14'  => 'Los Ríos',
            'region15'  => 'Arica y Parinacota',
            'region16'  => 'Nuble',
            'sex1'      => 'masculino',
            'sex2'      => 'femenino',
            //Transvip Conductores
            'contrato1' => 'Freelance',
            'contrato2' => 'Leasing',
            'contrato3' => 'Freelance Nuevo',
            'contrato4' => 'Leasing Nuevo'
        ];

        if (array_key_exists($cod, $arr)) {
            return $arr[$cod];
        }
        if (!array_key_exists($cod, $arr)) {
            return false;
        }
    }

    public function filters($request, $jwt, $datafilters = null)
    {
        $survey = $request->get('survey');
        $regiones       =   [];
        $genero         =   [];
        $tramo          =   [];
        $nicho =            [];
        $sucursal =         [];
        $web =              [];
        $TipoClienteT =     [];
        $TipoServicio =     [];
        $CondServicio =     [];
        $Zona =             [];
        $Sentido =          [];
        $Reserva =          [];
        $CanalT =           [];
        $Convenio =         [];
        $db = 'adata_' . substr($survey, 0, 3) . '_' . substr($survey, 3, 6);
        $dbC = substr($survey, 3, 6);

        //BANMEDICA
        if ($this->_dbSelected  == 'customer_banmedica') {
            //REGION
            $data = DB::select("SELECT DISTINCT(region) 
                                FROM $this->_dbSelected.".$db."_start
                                WHERE region != ''");
            $regiones = ['filter' => 'Regiones', 'datas' => $this->contentfilter($data, 'region')];
            
            //TRAMO
            $data = DB::select("SELECT DISTINCT(tramo) 
                                FROM  $this->_dbSelected.".$db."_start
                                WHERE tramo != '#N/A' AND tramo != ''");
            $tramo = ['filter' => 'Tramo', 'datas' => $this->contentfilter($data, 'tramo')];

            //NICHO
            $data = DB::select("SELECT DISTINCT(nicho) 
                                FROM  $this->_dbSelected.".$db."_start 
                                WHERE nicho != 'SN' and nicho != ''");
            $nicho = ['filter' => 'Nicho', 'datas' => $this->contentfilter($data, 'nicho')];

            //GENERO
            $data = DB::select("SELECT  DISTINCT($this->_fieldSex)
                                FROM  $this->_dbSelected.".$db."_start
                                Where $this->_fieldSex != '#N/D' AND $this->_fieldSex !=''");
            $genero = ['filter' => 'Genero', 'datas' => $this->contentfilter($data, $this->_fieldSex)];


            if ($dbC == 'ges' || $dbC == 'suc' || $dbC == 'con') {
                //SUCURSAL
                $data = DB::select("SELECT DISTINCT(nomSuc) 
                                    FROM  $this->_dbSelected.".$db."_start
                                    where nomSuc != ''
                                    ORDER BY nomSuc");
                $sucursal = ['filter' => 'Sucursal', 'datas' => $this->contentfilter($data, 'nomSuc')];

                return ['filters' => [(object)$regiones, (object)$genero, (object)$tramo, (object)$nicho, (object)$sucursal], 'status' => Response::HTTP_OK];
            }

            if ($dbC == 'web'){
                //SITIOWEB
                $data = DB::select("SELECT DISTINCT(sitioWeb)
                                    FROM $this->_dbSelected." . $db . "_start
                                    where sitioWeb != ''");
                $web = ['filter' => 'Web', 'datas' => $this->contentfilter($data, 'sitioWeb')];

                return ['filters' => [(object)$regiones, (object)$genero, (object)$tramo, (object)$web], 'status' => Response::HTTP_OK];
            }

            return ['filters' => [(object)$regiones, (object)$genero, (object)$tramo, (object)$nicho], 'status' => Response::HTTP_OK];
        }

        // //ANTIGUEDAD
        // $data = DB::select("SELECT antIsapre 
        //                         FROM  ".$db."_start
        //                         GROUP BY (antIsapre < 1), (antIsapre = 1 || antIsapre < 2), (antIsapre = 2 || antIsapre < 5), (antIsapre >= 5)");

        // //GENERACION
        // $data = DB::select("SELECT age
        //                         FROM  ".$db."_start
        //                         GROUP BY (age BETWEEN 14 AND 22), (age BETWEEN 23 AND 38), (age BETWEEN 39 AND 54), (age BETWEEN 55 AND 73), (age BETWEEN 74 AND 91)");
        // 


        //TRANSVIP

        if ($this->_dbSelected  == 'customer_colmena' && substr($survey, 0, 3) == 'tra') {
            
            if(substr($survey, 3, 3) == 'via'){
                
                $filtersInCache = \Cache::get('customer_colmena-tra');
                if($filtersInCache){
                    return $filtersInCache;
                }
                $data = DB::select("SELECT DISTINCT(tipocliente)
                                    FROM $this->_dbSelected.adata_tra_via_start
                                    WHERE tipocliente != '' ");

                $TipoClienteT = ['filter' => 'TipoCliente', 'datas' => $this->contentfilter($data, 'tipocliente')];

                $data = DB::select("SELECT DISTINCT(tiposervicio)
                                    FROM $this->_dbSelected.adata_tra_via_start
                                    WHERE tiposervicio = 'TAXI' or tiposervicio = 'MINIBUS'");

                $TipoServicio = ['filter' => 'TipoServicio', 'datas' => $this->contentfilter($data, 'tiposervicio')];

                $data = DB::select("SELECT DISTINCT(condicionservicio)
                                    FROM $this->_dbSelected.adata_tra_via_start
                                    WHERE condicionservicio != '' ");

                $CondServicio = ['filter' => 'CondicionServicio', 'datas' => $this->contentfilter($data, 'condicionservicio')];

                $data = DB::select("SELECT DISTINCT(zon)
                                    FROM $this->_dbSelected.adata_tra_via_start
                                    WHERE zon != '' ");

                $Zona = ['filter' => 'Zona', 'datas' => $this->contentfilter($data, 'zon')];

                $data = DB::select("SELECT DISTINCT(sentido)
                                    FROM $this->_dbSelected.adata_tra_via_start
                                    WHERE sentido != '' ");

                $Sentido = ['filter' => 'Sentido', 'datas' => $this->contentfilter($data, 'sentido')];

                $data = DB::select("SELECT DISTINCT(tipoReserva)
                                    FROM $this->_dbSelected.adata_tra_via_start
                                    WHERE tipoReserva != '' and tipoReserva != '0' ");

                $Reserva = ['filter' => 'Reserva', 'datas' => $this->contentfilter($data, 'tipoReserva')];

                $data = DB::select("SELECT DISTINCT(canal)
                                    FROM $this->_dbSelected.adata_tra_via_start
                                    WHERE canal != '' and canal != '' ");

                $CanalT = ['filter' => 'Canal', 'datas' => $this->contentfilter($data, 'canal')];

                $data = DB::select("SELECT DISTINCT(convenio)
                                    FROM $this->_dbSelected.adata_tra_via_start
                                    WHERE convenio != '' and convenio != '0' ");

                $Convenio = ['filter' => 'Convenio', 'datas' => $this->contentfilter($data, 'convenio')];

                $response = ['filters' => [(object)$TipoClienteT, (object)$TipoServicio, (object)$CondServicio, (object)$Sentido, (object)$Zona, (object)$Reserva, (object)$CanalT, (object)$Convenio], 'status' => Response::HTTP_OK];
                \Cache::put('customer_colmena-tra', $response, $this->expiresAtCache);
            }

            if(substr($survey, 3, 3) == 'con'){

                $filtersInCache = \Cache::get('customer_colmena-cond');
                if($filtersInCache){
                    return $filtersInCache;
                }

                $data = DB::select("SELECT DISTINCT(contrato)
                FROM $this->_dbSelected.adata_tra_cond_start
                WHERE contrato != '' and contrato != '0' ");
                
                $contrato = ['filter' => 'Contrato', 'datas' => $this->contentfilter($data, 'contrato')];

                $response = ['filters' => [(object)$contrato], 'status' => Response::HTTP_OK];
                \Cache::put('customer_colmena-cond', $response, $this->expiresAtCache);
            }


            return $response;
        }

        return ['filters' =>[]];
    }


    public function detailsDashCxWord($request,$jwt)
    {
        $request->merge([
            'startDate' => date('Y-m-d',strtotime(date('Y-m-01')."- $this->_periodCxWord month")),
            'endDate'   => date('Y-m-d'),
        ]);
        //if(!isset($startDate)&& !isset($endDate) && !isset($survey)){return ['datas'=>'unauthorized', 'status'=>Response::HTTP_NOT_ACCEPTABLE];}
        return ['datas'     => [$this->cxIntelligence($request),$this->wordCloud($request)],
                'status'    => Response::HTTP_OK
                ];
    }

    private function wordCloud($request)
    {
        $request->merge([
            'startDate' => date('Y-m-d',strtotime(date('Y-m-01')."- $this->_periodCxWord month")),
            'endDate'   => date('Y-m-d'),
        ]);
        $survey = $request->get('survey');

        $value = \Cache::get('word'.$survey.$request->get('startDate').$request->get('endDate'));
        //$value = \Cache::pull('word'.$survey.$request->get('startDate').$request->get('endDate'));
        if($value)
            return $value;

        $dataTextMining = $this->textMining($request);

        if($survey != 'travia' && $survey != 'tracond'){
        foreach ($dataTextMining['datas']->values as $value){
            foreach($value as $key => $detail){
                foreach($detail as $key1 => $index){
                isset($index->percentaje3)?$this->setAnomaliasTextAnalitics( $index->percentaje3, $index->nps3, $index->word3, $index->group3): null ;
                }
            }
        }
        }
        if($dataTextMining['datas'] == null ){
            $wordCloud = '';
        }
        if($dataTextMining['datas'] !== null ){
            $wordCloud = ($dataTextMining['datas']->wordCloud);
        }

        $resp = [
            "height"=> 4,
            "width"=> 4,
            "type"=> "word-cloud",
            "props"=> [
              "text"=> "Word cloud",
              "icon"=> "arrow-right",
              "wordCloud"=> $wordCloud
            ]
        ];
        \Cache::put('word'.$survey.$request->get('startDate').$request->get('endDate'), $resp, $this->expiresAtCache);
        return $resp;
    }

    private function cxIntelligence($request)
    {
        $request->merge([
            'startDate' => date('Y-m-d', strtotime(date('Y-m-01') . "- $this->_periodCxWord month")),
            'endDate'   => date('Y-m-d'),
        ]);
        
        $survey = $request->get('survey');
        $value  = \Cache::get('cx' . $survey . $request->get('startDate') . $request->get('endDate'));
        //$value = \Cache::pull('cx'.$survey.$request->get('startDate').$request->get('endDate'));
        if ($value){return $value;}
        
        $dataMatriz = $this->matriz($request);
       //print_r($dataMatriz);exit;
        if ($dataMatriz['datas'] == null) {
            return  $resp = [
                "height"    => 4,
                "width" => 8,
                "type" => "lists",
                "props" => [
                    "icon" => "arrow-right",
                    "text" => "CX Intelligence",
                    "lists" => [
                                    [
                                        "header" => "Pain Points",
                                        "color" => "#F07667",
                                        "items" => [],
                                        "numbered" => true
                                    ],
                                    [
                                        "header" => "Gain Points",
                                        "color" => "#17C784",
                                        "items" => [],
                                        "numbered" => true
                                    ]
                                ]
                            ]
                        ];
                    }
        
        if ($dataMatriz['datas']->cx->gainpoint != null) {
            $gainPoint = array_merge($dataMatriz['datas']->cx->gainpoint, $this->_anomaliasGain);
        }
        if ($dataMatriz['datas']->cx->gainpoint == null || $dataMatriz['datas']->cx->gainpoint == 0) {
            $gainPoint =  $this->_anomaliasGain;
        }
        if ($dataMatriz['datas']->cx->painpoint != null) {
            $painPoint = array_merge($dataMatriz['datas']->cx->painpoint, $this->_anomaliasPain);
        }
        if ($dataMatriz['datas']->cx->painpoint == null || $dataMatriz['datas']->cx->painpoint == 0) {
            $painPoint = $this->_anomaliasPain;
        }

        $resp = [
            "height" => 4,
            "width" => 8,
            "type" => "lists",
            "props" => [
                "icon" => "arrow-right",
                "text" => "CX Intelligence",
                "lists" => [
                    [
                        "header" => "Pain Points",
                        "color" => "#F07667",
                        "items" => $painPoint,
                        "numbered" => true
                    ],
                    [
                        "header" => "Gain Points",
                        "color" => "#17C784",
                        "items" => $gainPoint,
                        "numbered" => true
                    ]
                ]
            ]
        ];
        \Cache::put('cx' . $survey . $request->get('startDate') . $request->get('endDate'), $resp, $this->expiresAtCache);
        return $resp;
    }

    public function backCards($request, $jwt)
    {
        $survey     = ($request->get('survey') === null) ? $this->_activeSurvey : $request->get('survey');
        $npsInDb    = $this->getFielInDb($survey);
        $dataEmail  = $this->email('adata_' . substr($survey, 0, 3) . '_' . substr($survey, 3, 6), date('Y-m-01'), date('Y-m-d'), $this->_initialFilter);
        $data       = $this->infoClosedLoop('adata_' . substr($survey, 0, 3) . '_' . substr($survey, 3, 6), date('Y-m-01'), date('Y-m-d'), $npsInDb, $this->_initialFilter);
        $resp = [$dataEmail, $data];

        return [

            'datas'     => $resp,
            'status'    => Response::HTTP_OK
        ];
    }

    private function infoClosedLoop($db, $dateIni, $dateEnd, $fieldInBd, $filter, $datafilters = null)
    {
        $db2 = $this->primaryTable($db);
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter != 'all') {
            $data = DB::select("SELECT COUNT(*) as ticketCreated,
                                COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
                                COUNT(if(B.estado_close = 2, B.id, NULL)) as ticketPending, 
                                COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL)) as ticketInProgres,  $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$db as A 
                                INNER JOIN $this->_dbSelected." . $db . "_start as B ON (A.token = B.token) 
                                WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND $this->_obsNps != '' $datafilters");
        }
        if ($filter == 'all') {
            $data = DB::select("SELECT SUM(ticketCreated) AS ticketCreated,SUM(ticketClosed) AS ticketClosed, SUM(ticketPending) AS ticketPending, SUM(ticketInProgres) AS ticketInProgres
                                FROM (SELECT COUNT(*) as ticketCreated, 
                                COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
                                ((COUNT(if(B.estado_close = 2, B.id, NULL))*100)/COUNT(*))*$this->_porcentageBan as ticketPending, 
                                ((COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL))*100)/COUNT(*))*$this->_porcentageBan as ticketInProgres
                                FROM $this->_dbSelected.$db as A 
                                INNER JOIN $this->_dbSelected." . $db . "_start as B ON (A.token = B.token) 
                                WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND obs_nps != '' $datafilters
                                UNION
                                SELECT COUNT(*) as ticketCreated, 
                                COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
                                ((COUNT(if(B.estado_close = 2, B.id, NULL))*100)/COUNT(*))*$this->_porcentageVid as ticketPending, 
                                ((COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL))*100)/COUNT(*))*$this->_porcentageVid as ticketInProgres
                                FROM $this->_dbSelected.$db2 as A 
                                INNER JOIN $this->_dbSelected." . $db2 . "_start as B ON (A.token = B.token) 
                                WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND obs_nps != '' $datafilters) AS A");
        }
        $closedRate = 0;

        if ($data[0]->ticketCreated != "0") {
            $closedRate = round(($data[0]->ticketClosed / $data[0]->ticketCreated) * 100, 3);
        }
        return [
            "name"          => "Close loop report",
            "icon"          => "closed-loop",
            "variant"       => "boxes",
            "box" => [
                [
                    "value"      => $closedRate,
                    "text"       => "Closed rate",
                    "percentage" => 0,
                ],
                [
                    "value" => (int)$data[0]->ticketCreated,
                    "text"  => "Ticket created",
                ],
                [
                    "value" => (int)$data[0]->ticketClosed,
                    "text"  => "Closed ticket",
                ],
            ],
        ];
    }

    private function email($db, $dateIni, $dateEnd, $filter)
    {
        $db2 = $this->primaryTable($db);

        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($db, 10, 3) == 'ban' || substr($db, 10, 3) == 'vid')
            $activeP2 ='';
        if(substr($db, 6, 3) != 'tra')
        {
            if ($filter == 'all') {
                $data = DB::select("SELECT SUM(TOTAL) AS TOTAL 
                                    FROM (SELECT COUNT(*) AS TOTAL 
                                    FROM $this->_dbSelected." . $db . "_start 
                                    WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' 
                                    UNION SELECT COUNT(*) AS TOTAL 
                                    FROM $this->_dbSelected." . $db2 . "_start 
                                    WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' ) 
                                    AS A");
                $EmailSend = $data[0]->TOTAL;

                $data2 = DB::select("SELECT SUM(RESP) AS RESP FROM 
                                    (SELECT COUNT(*) AS RESP 
                                    FROM $this->_dbSelected.$db 
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd'  and nps!= 99
                                    UNION 
                                    SELECT COUNT(*) AS RESP 
                                    FROM $this->_dbSelected.$db2 
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps!= 99) AS A");              
            };
        
            if($filter != 'all'){
        
                $data = DB::select("SELECT COUNT(*) AS TOTAL FROM $this->_dbSelected.".$db."_start WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd'" );
                $EmailSend = $data[0]->TOTAL;

                $data2 = DB::select("SELECT COUNT(*) AS RESP 
                                    FROM $this->_dbSelected.$db 
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps!= 99 $activeP2");
            };
        }

        if(substr($db, 6, 3) == 'tra')
        {
            $surveyName = substr($db, 10, 4);
            $dataT = DB::select("SELECT SUM(enviados) AS TOTAL 
                                FROM $this->_dbSelected.datasengrid_transvip 
                                WHERE tipo = 1 AND fechasend BETWEEN '$dateIni' AND '$dateEnd' and encuesta = '$surveyName'" );

            if(substr($db, 10, 3) != 'via')    
            {
                $data2 = DB::select("SELECT COUNT(*) AS RESP 
                                    FROM $this->_dbSelected.$db 
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps!= 99 $activeP2");
            }
            
            if(substr($db, 10, 3) == 'via')    
            {
                $data2 = DB::select("SELECT COUNT(*) AS RESP 
                                    from $this->_dbSelected.$db as a
                                    left join $this->_dbSelected." . $db . "_start as b
                                    on a.token = b.token
								    where fechaservicio BETWEEN '$dateIni' AND '$dateEnd' and nps!= 99 $activeP2");
            }

            $EmailSend = $dataT[0]->TOTAL;
        }

        $EmailRESP = $data2[0]->RESP;
        return [
            "name"          => "Tracking de envíos",
            "icon"          => "email-stats",
            "variant"       => "boxes",
            "box" => [
                [
                    "value" => (!$EmailSend) ? 0 : $EmailSend,
                    "text"  => "Enviados",
                ],
                [
                    "value" => (!$EmailRESP) ? 0 : $EmailRESP,
                    "text"  => "Respondidos",
                ],
                [
                    "value" => ($EmailSend == 0) ? 0 : round(($EmailRESP / $EmailSend) * 100) . ' %',
                    "text"  => "Tasa de respuesta",
                ],
            ],
        ];
    }
    protected function getDataSurvey($request, $jwt){
        $indicators = new Suite($this->_jwt);
        return $indicators->getSurvey($request, $jwt);
    }

    public function generalInfo($request, $jwt)
    {
        $surveys = $this->getDataSurvey($request, $jwt);
        $data = [];
        $otherGraph = [];
        if ($surveys['status'] == 200) {
            foreach ($surveys['datas'] as $key => $value) {
                if ($value['base'] != 'mutred'){
                    $db = 'adata_'.substr($value['base'],0,3).'_'.substr($value['base'],3,6);
                    $db2 = $this->primaryTable($db);
                    $npsInDb = 'nps';
                    $csatInDb = 'csat';
                    $cesInDb = 'ces';
                    $infoNps =[$this->infoNps($db, date('Y-m-d'),date('Y-m-01'),$npsInDb,$this->_initialFilter)]; 
                    $otherGraph = [$this->infoCsat($db, date('Y-m-d'),date('Y-m-01'), $csatInDb,$this->_initialFilter)];
                     
                    if (substr($value['base'],0,3) == 'tra'){
                        if(substr($value['base'],3,3) == 'con')
                            $db = 'adata_tra_cond';
                        if(substr($value['base'],3,3) == 'via')
                            $db = 'adata_tra_via';
                        $datas = $this->npsPreviousPeriod($db,date('Y-m-d'),date('Y-m-01'),'csat','' );

                        $otherGraph =  [[
                            "name"          => "ISN",
                            "value"         => $datas['insAct'] == 'N/A' ? 'N/A' : Round($datas['insAct']),
                            "percentage"    => $datas['insAct'] == 'N/A' ? round(-$datas['ins']) : round($datas['insAct']-$datas['ins']),
                        ]];
                    }
                    
                    if (substr($value['base'],0,3) == 'jet'){
                        $infoNps = [$this->cbiResp($db, '', date('Y-m-d'),date('Y-m-01')), $this->infoNps($db,date('Y-m-d'),date('Y-m-01'),$npsInDb,$this->_initialFilter)];
        
                        if (substr($value['base'],3,3) == 'com') 
                            $otherGraph = [$this->infoCsat($db,date('Y-m-d'),date('Y-m-01'), $csatInDb,$this->_initialFilter), $this->ces($db,date('Y-m-d'),date('Y-m-01'), $cesInDb)];
                        
                        if (substr($value['base'],3,3) == 'via' || substr($value['base'],3,3) == 'vue')
                            $otherGraph = [$this->infoCsat($db,date('Y-m-d'),date('Y-m-01'), $csatInDb,$this->_initialFilter)];
                    }

                        $data[] = [
                            'client'        => $this->_nameClient, 'clients'  => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: null,
                            "title"         => ucwords(strtolower($value['name'])),
                            "identifier"    => $value['base'],
                            "principalIndicator" => $infoNps,
                            "journeyMap"    => $this->GraphCSATDrivers($db,$db2,$value['base'],$csatInDb,date('Y-m-d'),date('Y-m-01'),$this->_initialFilter,'one'),
                            "otherGraphs"   => $otherGraph
                        ];
                    
                }
            }
        }
        return [
            'datas'     => $data,
            'status'    => Response::HTTP_OK
        ];
        }
    

    public function getEndCsat($survey){
        $datas = [
            //banemdica
            "banamb" => "10",
            "banasi" => "8",
            "bancon" => "7",
            "banges" => "9",
            "banhos" => "8",
            "banlic" => "10",
            "banmod" => "7",
            "banweb" => "4",
            "banrel" => "10",
            "bansuc" => "8",
            "bantel" => "7",
            "banven" => "11",
            //mutual
            "mutges" => "5",
            "mutamb" => "5",
            "mutbe"  => "5",
            "mutcas" => "2",
            "muteri" => "6",
            "muthos" => "5",
            "mutimg" => "5",
            "mutreh" => "5",
            "muturg" => "5",
            "mutcon" => "5",
            "mutred" => "4",
            "mutcet" => "5",
            //demo
            "demdem" => "8",
            //transvip
            "travia" => "11",
            "tracond" => "7",
            //JetSmart
            "jetvia" => "10",
            "jetcom" => "6",
            "jetvue" => "6",
        ];
        if (array_key_exists($survey, $datas)) {
            return $datas[$survey];
        }
        if (!array_key_exists($survey, $datas)) {
            return false;
        }
    }

    private function traking($db,$dateIni,$dateEnd) {

        $surveyName = substr($db, 10, 4);
        $dataT = DB::select("SELECT SUM(enviados) AS TOTAL 
                            FROM $this->_dbSelected.datasengrid_transvip 
                            WHERE tipo = 1 AND fechasend BETWEEN '$dateIni' AND '$dateEnd' and encuesta = '$surveyName'" );

        if(substr($db, 10, 3) != 'via')    
        {
            $data = DB::select("SELECT COUNT(*) AS RESP 
                                FROM $this->_dbSelected.$db 
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps!= 99 and etapaencuesta = 'P2'");
        }
        
        if(substr($db, 10, 3) == 'via')    
        {
            $data = DB::select("SELECT COUNT(*) AS RESP 
                                from $this->_dbSelected.$db as a
                                left join $this->_dbSelected." . $db . "_start as b
                                on a.token = b.token
                                where fechaservicio BETWEEN '$dateIni' AND '$dateEnd' and nps!= 99 and etapaencuesta = 'P2'");
        }

        $reenv = DB::select("SELECT SUM(enviados) as reenv
                            FROM $this->_dbSelected.datasengrid_transvip
                            WHERE fechasend BETWEEN '$dateIni' AND '$dateEnd' AND tipo = 2 and encuesta = '$surveyName'");  

        $queryT = DB::select("SELECT 
                            SUM(abiertos) as opened, 
                            SUM(click) as clicks 
                            FROM $this->_dbSelected.datasengrid_transvip
                            WHERE fechasend BETWEEN '$dateIni' AND '$dateEnd' and encuesta = '$surveyName'");

        $queryX = DB::select("SELECT SUM(enviados) as sended, 
                            SUM(rebotados) as bounced,
                            SUM(entregados) AS delivered, 
                            SUM(spam) as spam  
                            FROM $this->_dbSelected.datasengrid_transvip
                            WHERE fechasend BETWEEN '$dateIni' AND '$dateEnd' AND tipo = 1 and encuesta = '$surveyName'");
   
    return [
        "height"=> 4,
        "width"=> 12,
        "type"=> "summary",
        "props"=> [
            "icon"=> "arrow-right",
            "text"=> "Tracking de envíos",
            "sumaries"=> [
                    [
                        "icon"=> "enviados",
                        "text"=> "Enviados",
                        "value"=> $dataT[0]->TOTAL,
                        "textColor"=> "#fff",
                        "valueColor" => "#000",
                        "bgColor"=> "#17C784",
                        "direction" => "row",
                    ],
                    [
                        "icon"=> "entregados",
                        "text"=> "Entregados",
                        "value"=> $queryX[0]->delivered,
                        "valueColor"=> "#FFB203",
                        "direction" => "row",
                    ],
                    [
                        "icon"=> "contestados",
                        "text"=> "Contestados",
                        "value"=> $data[0]->RESP,
                        "valueColor"=> "#FFB203",
                        "direction" => "row",
                    ],
                    [
                        "icon"=> "tasarespuesta",
                        "text"=> "Tasa Respuesta",
                        "value"=> ($dataT[0]->TOTAL == 0) ? 0 : round(($data[0]->RESP / $dataT[0]->TOTAL) * 100, 1) . ' %',
                        "textColor"=> "#fff",
                        "valueColor" => "#000",
                        "bgColor"=>  "#FFC700",
                        "direction" => "row",
                    ],
                    [
                        "icon"=> "abierto",
                        "text"=> "Abiertos",
                        "value"=> $queryT[0]->opened,
                        "valueColor"=> "#FFB203",
                        "direction" => "row",
                    ],
                    [
                        "icon"=> "click",
                        "text"=> "Clicks",
                        "value"=> $queryT[0]->clicks,
                        "valueColor"=> "#FFB203",
                        "direction" => "row",
                    ],
                    [
                        "icon"=> "reenviados",
                        "text"=> "Reenviados",
                        "value"=> $reenv[0]->reenv,
                        "valueColor"=> "#FFB203",
                        "direction" => "row",
                    ],
                    [
                        "icon"=> "rebotados",
                        "text"=> "Rebotados",
                        "value"=> $queryX[0]->bounced,
                        "valueColor"=> "#FFB203",
                        "direction"=> "row",
                    ],
                    [
                        "icon"=> "spam",
                        "text"=> "Spam",
                        "value"=> $queryX[0]->spam,
                        "valueColor"=> "#FFB203",
                        "direction"=> "row",
                    ],
                ],
            ],
        ];
    }

    //---------------------------------------------------------------------------------------------------------------------
    //OKK
    private function npsPreviousPeriod($table, $dateEnd, $dateIni, $indicador, $datafilters)
    {
        $datafilters = str_replace(' AND date_survey between date_sub(NOW(), interval 9 week) and NOW()', '', $datafilters);

        $monthAntEnd = date('m') - 1;
        $annio = date('Y');
        $monthActualEnd= substr($dateIni, 5,2); 
    
        if($monthActualEnd > 1 && $monthActualEnd < 11){
            $monthAntEnd = '0'.($monthActualEnd - 1);
        }
        if($monthActualEnd == 1){
            $monthAntEnd = 12;
            $annio = date('Y') - 1;
        }
        if($monthActualEnd > 10){
            $monthAntEnd = $monthActualEnd - 1;
        }

        $mes = $monthAntEnd;
       
        $table2 = $this->primaryTable($table);

        
        if ($this->_dbSelected == 'customer_colmena' && substr($table, 6, 3) == 'tra') {

            
            if (substr($datafilters, 30, 3) == 'NOW') {
                $datafilters = '';
            }

            if (substr($table, 10, 3) == 'via')
            {
                $datafilters = str_replace(' AND fechaservicio between date_sub(NOW(), interval 9 week) and NOW()', '', $datafilters);

                $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN csat BETWEEN 6 AND 7 THEN 1 END) -
                                    COUNT(CASE WHEN csat BETWEEN 1 AND 4 THEN 1 END)) /
                                    (COUNT(CASE WHEN csat != 99 THEN csat END)) * 100),1) AS INS,
                                    ROUND(((COUNT(CASE WHEN nps BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                    COUNT(CASE WHEN nps BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                    (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS
                                    FROM $this->_dbSelected.$table as a
                                    left join $this->_dbSelected." . $table . "_start as b
                                    on a.token = b.token
                                    WHERE  MONTH(fechaservicio) =  $mes and YEAR(fechaservicio) = $annio  AND etapaencuesta = 'P2' $datafilters");

                $data2 = DB::select("SELECT COUNT(CASE WHEN a.csat!=99 THEN 1 END) as Total, 
                                    ROUND(((COUNT(CASE WHEN a.csat BETWEEN 6 AND 7 THEN 1 END) - COUNT(CASE WHEN a.csat BETWEEN 1 AND 4 THEN 1 END)) / (COUNT(CASE WHEN a.csat!=99 THEN 1 END)) * 100),1) AS INS,
                                    ROUND(((COUNT(CASE WHEN nps BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - COUNT(CASE WHEN nps BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                    (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS,
                                    MONTH(fechaservicio) as mes, YEAR(fechaservicio) as annio, fechaservicio, WEEK(date_survey) AS week
                                    from $this->_dbSelected.$table as a
                                    left join $this->_dbSelected." . $table . "_start as b
                                    on a.token = b.token
                                    WHERE fechaservicio between '$dateIni' and '$dateEnd' $datafilters AND etapaencuesta = 'P2'
                                    ORDER by fechaservicio ASC");
            }

            if (substr($table, 10, 3) == 'con')
            {
                $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN csat BETWEEN 6 AND 7 THEN 1 END) -
                                    COUNT(CASE WHEN csat BETWEEN 1 AND 4 THEN 1 END)) /
                                    (COUNT(CASE WHEN csat != 99 THEN csat END)) * 100),1) AS INS,
                                    ROUND(((COUNT(CASE WHEN nps BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                    COUNT(CASE WHEN nps BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                    (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS
                                    FROM $this->_dbSelected.$table as a
                                    left join $this->_dbSelected." . $table . "_start as b
                                    on a.token = b.token
                                    WHERE  a.mes =  $mes and a.annio = $annio  AND etapaencuesta = 'P2' $datafilters");



                $data2 = DB::select("SELECT COUNT(CASE WHEN a.csat!=99 THEN 1 END) as Total, 
                                    ROUND(((COUNT(CASE WHEN a.csat BETWEEN 6 AND 7 THEN 1 END) - COUNT(CASE WHEN a.csat BETWEEN 1 AND 4 THEN 1 END)) / (COUNT(CASE WHEN a.csat!=99 THEN 1 END)) * 100),1) AS INS,
                                    ROUND(((COUNT(CASE WHEN nps BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - COUNT(CASE WHEN nps BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                    (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS,
                                    a.mes, a.annio, date_survey, WEEK(date_survey) AS week
                                    from $this->_dbSelected.$table as a
                                    left join $this->_dbSelected." . $table . "_start as b
                                    on a.token = b.token
                                    WHERE a.mes = $monthActualEnd and a.annio = $annio $datafilters AND etapaencuesta = 'P2'
                                    GROUP by a.mes, a.annio
                                    ORDER by a.date_survey ASC");
            }
            
            return ['ins' => $data[0]->INS == null ? 0 : $data[0]->INS, 'nps' => $data[0]->NPS == null ? 0 : $data[0]->NPS, 'insAct' => count($data2) === 0 ? 'N/A' : $data2[0]->INS, 'npsAct' => count($data2) === 0 ? 'N/A' : $data2[0]->NPS];
        }

        if ($this->_dbSelected == 'customer_jetsmart') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS
                                FROM $this->_dbSelected.$table as a
                                left join $this->_dbSelected." . $table . "_start as b
                                on a.token = b.token
                                WHERE a.mes = $mes and a.annio = $annio and etapaencuesta = 'P2' $datafilters");
            return $data[0]->NPS;
        }

        if ($this->_dbSelected == 'customer_colmena' && substr($table, 6, 3) == 'mut' || substr($table, 0, 3) == 'MUT' ) {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS
                                FROM $this->_dbSelected.$table as a
                                left join $this->_dbSelected." . $table . "_start as b
                                on a.token = b.token
                                WHERE a.mes = $mes and a.annio = $annio $datafilters AND etapaencuesta = 'P2'");
            return $data[0]->NPS;
        }
        if ($this->_dbSelected == 'customer_demo') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS
                                FROM $this->_dbSelected.$table
                                WHERE a.mes = $mes and a.annio = $annio $datafilters");
            
            return $data[0]->NPS;
        }

        if ($this->_dbSelected == 'customer_banmedica') {
            $data = DB::select("SELECT SUM(NPS) AS NPS FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                    (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageBan AS NPS
                    FROM $this->_dbSelected.$table as a
                    left join $this->_dbSelected.".$table."_start AS b
                    ON a.token = b.token
                    WHERE a.mes = $mes and a.annio = $annio $datafilters
                    UNION
                    SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                    (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageVid AS NPS
                    FROM  $this->_dbSelected.$table2 as a
                    left join $this->_dbSelected." . $table2 . "_start AS b
                    ON a.token = b.token
                    WHERE a.mes = $mes and a.annio = $annio $datafilters) AS A");

            return round($data[0]->NPS);
        }
    }

    //Función CBI periodo previo para JetSmart

    private function cbiPreviousPeriod($table,$dateEnd, $dateIni, $indicador, $datafilters)
    {
        $datafilters = str_replace(' AND date_survey between date_sub(NOW(), interval 9 week) and NOW()', '', $datafilters);
        $monthAntEnd = date('m') - 1;
        $annio = date('Y');
        $monthActualEnd= substr($dateIni, 5,2); 
    
        if($monthActualEnd > 1 && $monthActualEnd < 11){
            $monthAntEnd = '0'.($monthActualEnd - 1);
        }
        if($monthActualEnd == 1){
            $monthAntEnd = 12;
            $annio = date('Y') - 1;
        }
        if($monthActualEnd > 10){
            $monthAntEnd = $monthActualEnd - 1;
        }

        $mes = $monthAntEnd;

        if ($this->_dbSelected == 'customer_jetsmart') {

            $data = DB::select("SELECT ROUND((COUNT(CASE WHEN $indicador BETWEEN 4 AND 5 THEN 1 END)  /
                                (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100)) AS CBI
                                FROM $this->_dbSelected.$table 
                                WHERE mes = $mes AND annio = $annio $datafilters");
                                return $data;
        }
    }

    //Función promedio 6 mese CBI

    private function AVGLast6MonthCBI($table,$dateIni,$dateEnd,$indicador){
            $data = DB::select("SELECT sum(CBI) as total, COUNT(distinct mes) as meses from (SELECT 
                                ROUND(((COUNT(CASE WHEN $indicador BETWEEN 4 AND  5 THEN 1 END)) 
                                /(COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100)) AS CBI, mes, annio
                                FROM $this->_dbSelected.$table
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' 
                                group by annio, mes) as a");
        if ($data[0]->meses == null || $data[0]->meses == 0)
            return 'N/A';

        return (int)($data[0]->total / $data[0]->meses);
    }

    private function AVGLast6MonthNPS($table,$table2,$dateIni,$dateEnd,$indicador, $filter){

        $activeP2 = " etapaencuesta = 'P2' AND ";
        if(substr($table, 6, 3) == 'ban' || substr($table, 6, 3) == 'vid')
            $activeP2 ='';

        if($filter == 'all'){              

            $data = DB::select("SELECT sum(NPSS) as total, COUNT(distinct mes) as meses from (SELECT round(SUM(NPS)) AS NPSS, mes FROM 
                               (SELECT ROUND(((COUNT(CASE WHEN $indicador  BETWEEN 9 AND 10 THEN 1 END) -
                               COUNT(CASE WHEN $indicador  BETWEEN 0 AND 6 THEN 1 END)) * 100 /
                               COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END))*$this->_porcentageBan) AS NPS, mes, annio
                               FROM $this->_dbSelected.$table
                               WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                               group by annio, mes
                               union
                               SELECT ROUND(((COUNT(CASE WHEN $indicador  BETWEEN 9 AND 10 THEN 1 END) -
                               COUNT(CASE WHEN $indicador  BETWEEN 0 AND 6 THEN 1 END)) * 100/
                               COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END))*$this->_porcentageVid) AS NPS, mes, annio
                               FROM $this->_dbSelected.$table2
                               WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                               group by annio, mes) AS A
                               group by annio, mes) as b");
        }

        if ($filter != 'all') {

            if(substr($table, 6, 7) != 'tra_via')
            {
                $data = DB::select("SELECT sum(NPS) as total, COUNT(distinct mes) as meses from (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                    (COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END)) * 100),1) AS NPS, mes, annio
                                    FROM $this->_dbSelected.$table 
                                    WHERE $activeP2 date_survey BETWEEN '$dateEnd' AND '$dateIni' 
                                    GROUP BY annio, mes) as a"); //Qury revisada con tra_cond OK
            }

            if(substr($table, 6, 7) == 'tra_via')
            {
                $data = DB::select("SELECT sum(NPS) as total, COUNT(distinct mes) as meses from (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) *100 /
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END))) AS NPS, MONTH(fechaservicio) as mes, YEAR(fechaservicio)
                                    FROM $this->_dbSelected.$table as c
                                    LEFT JOIN $this->_dbSelected." . $table . "_start as b
                                    on c.token = b.token
                                    WHERE $activeP2 fechaservicio BETWEEN '$dateEnd' AND '$dateIni'
                                    GROUP BY MONTH(fechaservicio), YEAR(fechaservicio)) as a"); //Query revisada con tra_via OK
                                    
            }
        }
        
        if ($data[0]->meses == null || $data[0]->meses == 0)
            return 'N/A';

        return (string)(round($data[0]->total / $data[0]->meses));
    }

    private function primaryTable($table)
    {
        $db = explode('_', $table);
        $indicatordb = ($db[1] == 'vid') ? 'ban' : 'vid';

        return $table2 = $db[0] . '_' . $indicatordb . '_' . $db[2];
    }

    protected function activeP2($table):string{
        if(substr($table, 6, 3) == 'jet')
            return " AND etapaencuesta = 'P2' ";
        return '';
    }

    // public function resumenNps($table,$dateIni,$dateEnd, $indicador, $filter,$datafilters){
    //     if ($filter == 'all') {
    //         $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN 1 END) - 
    //                             COUNT(CASE WHEN $indicador BETWEEN $this->_minCsat AND $this->_maxCsat THEN 1 END)) / 
    //                             (COUNT(CASE WHEN $indicador!=99 THEN 1 END)) * 100),1) AS ISN, 
    //                             a.mes, a.annio ,$this->_fieldSelectInQuery  
    //                             FROM $this->_dbSelected.$table as a
    //                             INNER JOIN $this->_dbSelected." . $table . "_start as b ON a.token = b.token 
    //                             WHERE  date_survey BETWEEN '$dateEnd' AND '$dateIni' AND etapaencuesta = 'P2' $datafilters 
    //                             GROUP BY  a.mes, a.annio 
    //                             ORDER BY date_survey ASC");


    //         $data2 = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN 1 END) - 
    //         COUNT(CASE WHEN $indicador BETWEEN $this->_minCsat AND $this->_maxCsat THEN 1 END)) / 
    //         (COUNT(CASE WHEN $indicador!=99 THEN 1 END)) * 100),1) AS ISN, 
    //         a.mes, a.annio ,$this->_fieldSelectInQuery  
    //         FROM $this->_dbSelected.$table as a
    //         INNER JOIN $this->_dbSelected." . $table . "_start as b ON a.token = b.token 
    //         WHERE  a.mes = $mes  AND a.annio = $annio AND etapaencuesta = 'P2'
    //         GROUP BY  a.mes, a.annio 
    //         ORDER BY date_survey ASC");
    //     }
    //     if ($data != null && $data[0]->ISN != null){
    //         return[
    //             "name"              => "isn",
    //             "value"             => round($data[0]->ISN),
    //             "percentage"        => round($data[0]->ISN - $data2[0]->ISN),
    //         ];
    //     }


    //     if ($data == null || $data[0]->ISN == null){
    //         return[
    //             "name"              => "isn",
    //             "value"             => round(0),
    //             "percentage"        => round(0),
    //         ];
    //     }
   
    // } 

    //OKK
    private function resumenNps($table,  $dateEnd, $dateIni, $indicador, $filter, $datafilters = null)
    {
        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($table, 6, 3) == 'ban' || substr($table, 6, 3) == 'vid')
            $activeP2 ='';

        // $dateSurvey = 'date_survey';
        // $groupBy = ' GROUP BY a.mes, a.annio ';
        // if(substr($table, 6, 3) == 'tra' && substr($table, 10, 3) == 'via')
        // {
        //     $dateSurvey = 'fechaservicio';
        //     $groupBy = '';
        // }    

        $table2 = '';
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter == 'all') {
            $table2 = $this->primaryTable($table);

            $query = "SELECT sum(NPS) AS NPS, SUM(total) as total, SUM(detractor) as detractor, SUM(promotor) AS promotor, SUM(neutral) AS neutral, AVG(promedio) AS promedio, $this->_fieldSelectInQuery 
                      FROM (SELECT COUNT(CASE WHEN $indicador != 99 THEN 1 END) as total,
                      ((count(if($indicador between 0 and  6, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)*$this->_porcentageBan) as detractor, 
                      ((count(if($indicador = 9  or $indicador =10, $indicador,NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageBan) as promotor,
                      ((count(if($indicador = 7 OR $indicador = 8, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageBan) as neutral,
                      AVG($indicador) as promedio ,a.mes, a.annio, date_survey,   
                      ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
                      COUNT(CASE WHEN $indicador!=99 THEN $indicador END) * 100),1)*$this->_porcentageBan AS NPS, $this->_fieldSelectInQuery 
                      FROM $this->_dbSelected.$table as a
                      LEFT JOIN $this->_dbSelected." . $table . "_start as b
                      on a.token = b.token
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters
                      UNION
                      SELECT COUNT(CASE WHEN $indicador != 99 THEN 1 END) as total,
                      ((count(if($indicador between 0 and  6, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)*$this->_porcentageVid) as detractor, 
                      ((count(if($indicador = 9  or $indicador =10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageVid) as promotor,
                      ((count(if($indicador = 7 OR $indicador = 8, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageVid) as neutral,
                      AVG($indicador) as promedio ,a.mes, a.annio, date_survey,  
                      ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                      COUNT(CASE WHEN $indicador!=99 THEN $indicador END) * 100),1)*$this->_porcentageVid AS NPS, $this->_fieldSelectInQuery
                      FROM $this->_dbSelected.$table2 as a
                      LEFT JOIN $this->_dbSelected." . $table2 . "_start as b
                      on a.token = b.token
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters) AS A ";

            $data = DB::select($query);
        }

       
        if ($filter != 'all') {
          
            if(substr($table, 6, 7) == 'tra_via')
            {
                $data = DB::select("SELECT count(*) as total, 
                                ((count(if($indicador <= $this->_maxNps, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)) as detractor, 
                                ((count(if($indicador = $this->_minMaxNps or  $indicador = $this->_maxMaxNps , $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)) as promotor,
                                ((count(if($indicador =  $this->_maxMediumNps OR $indicador = $this->_minMediumNps, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)) as neutral,
                                AVG($indicador) as promedio,
                                ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS,  $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$table as a
                                LEFT JOIN $this->_dbSelected." . $table . "_start as b
                                on a.token = b.token
                                WHERE fechaservicio BETWEEN '$dateIni' AND '$dateEnd' $datafilters $activeP2
                                ORDER BY MONTH(fechaservicio), YEAR(fechaservicio) ASC"); //Ver si se le agrega "GROUP BY MONTH(fechaservicio), YEAR(fechaservicio)" despues de $activeP2
            } 

            if(substr($table, 6, 7) != 'tra_via')
            {
                $data = DB::select("SELECT count(*) as total, 
                                    ((count(if($indicador <= $this->_maxNps, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)) as detractor, 
                                    ((count(if($indicador = $this->_minMaxNps or  $indicador = $this->_maxMaxNps , $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)) as promotor,
                                    ((count(if($indicador =  $this->_maxMediumNps OR $indicador = $this->_minMediumNps, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)) as neutral,
                                    AVG($indicador) as promedio,
                                    ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                    (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS,  $this->_fieldSelectInQuery
                                    FROM $this->_dbSelected.$table as a
                                    LEFT JOIN $this->_dbSelected." . $table . "_start as b
                                    on a.token = b.token
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters $activeP2
                                    ORDER BY date_survey ASC");
            }
        }

        if (($data == null) || $data[0]->total == null || $data[0]->total == 0) {
            $npsActive = (isset($data[0]->NPS)) ? $data[0]->NPS : 0;
            $npsPreviousPeriod = $this->npsPreviousPeriod($table, $dateEnd, $dateIni, $indicador, $datafilters);
            
            return [
                "name"          => "nps",
                "value"         => 'N/A',
                "percentageGraph"=>true,
                "promotors"     => 0,
                "neutrals"      => 0,
                "detractors"    => 0,
                "percentage"    => substr($table, 6, 3) != 'tra' ? $npsActive - $npsPreviousPeriod : $npsActive - $npsPreviousPeriod['nps'],
                "smAvg"         => $this->AVGLast6MonthNPS($table, $table2, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $indicador, $filter)
            ];
        }

        if ($data[0]->total != 0) {
            $npsActive = (isset($data[0]->NPS)) ? $data[0]->NPS : 0;
            $npsPreviousPeriod = $this->npsPreviousPeriod($table, $dateEnd, $dateIni, $indicador, $datafilters);
            
            if ($npsPreviousPeriod  === null) {
                $npsPreviousPeriod = 0;
            }

            if (substr($table, 6, 3) == 'tra'){
                $npsPreviousPeriod = $npsPreviousPeriod['nps'];
            }
       
            return [
                "name"              => "nps",
                "value"             => round($npsActive),
                "percentageGraph"   => true,
                "promotors"         => round($data[0]->promotor),
                "neutrals"          => ((round($data[0]->promotor) == 0) && (round($data[0]->detractor) == 0)) ? round($data[0]->neutral) : 100 - (round($data[0]->detractor) + round($data[0]->promotor)),
                "detractors"        => round($data[0]->detractor),
                "percentage"        => substr($table, 6, 3) == 'mut'? 0 : $npsActive - round($npsPreviousPeriod),
                "smAvg"             => substr($table, 6, 3) == 'mut'? '0' :$this->AVGLast6MonthNPS($table, $table2, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $indicador, $filter),
                'NPSPReV'           => $npsPreviousPeriod,
                // 'mes'               => $mes,
                // 'annio'             => $annio,
            ];
        }
    }

    //OKK
    private function infoNps($table,  $dateIni, $dateEnd, $indicador, $filter, $dataFilters = NULL)
    {
        $generalDataNps             = $this->resumenNps($table,  $dateIni, $dateEnd, $indicador, $filter, $dataFilters);
        $generalDataNps['graph']    = $this->graphNps($table,  $indicador, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $filter, 'one');

        return $generalDataNps;
    }

    //OKK
    private function graphNps($table, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    {
        $graphNPS  = [];

        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($table, 6, 3) == 'ban' || substr($table, 6, 3) == 'vid')
            $activeP2 ='';

        $group2 = " GROUP BY mes, annio ";

        
        $table2 = $this->primaryTable($table);
        
        if ($group !== null) {
            $where = $datafilters;
            $datafilters = '';
            $group2 = "week";
        }

        if ($group === null) {
            $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
            $group = " a.mes, a.annio ";
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";
        if ($filter != 'all') {

        
            if(substr($table, 6, 7) == 'tra_via')
            {

                $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                    (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1) AS NPS, 
                                    count(if($indicador <= $this->_maxNps , $indicador, NULL)) as Cdet,
                                    count(if($indicador = $this->_minMaxNps or $indicador =$this->_maxMaxNps, $indicador, NULL)) as Cpro,
                                    count(if($indicador=$this->_maxMediumNps OR $indicador=$this->_minMediumNps, $indicador, NULL)) as Cneu,              
                                    count(*) as total, 
                                    ((count(if($indicador <= $this->_maxNps, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as detractor, 
                                    ((count(if($indicador = $this->_minMaxNps OR $indicador =$this->_maxMaxNps, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as promotor, 
                                    ((count(if($indicador=$this->_maxMediumNps OR $indicador=$this->_minMediumNps, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as neutral,              
                                    MONTH(fechaservicio) as mes, YEAR(fechaservicio) as annio, WEEK(fechaservicio) AS week, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek, $this->_fieldSelectInQuery  
                                    FROM $this->_dbSelected.$table as a
                                    INNER JOIN $this->_dbSelected." . $table . "_start as b ON a.token = b.token 
                                    WHERE  fechaservicio BETWEEN '$dateEnd' AND '$dateIni' $activeP2 $datafilters 
                                    GROUP BY MONTH(fechaservicio), YEAR(fechaservicio)
                                    ORDER BY YEAR(fechaservicio), MONTH(fechaservicio) ASC");
            }

            if(substr($table, 6, 7) != 'tra_via')
            {
                $data = DB::select("SELECT ROUND(((
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                    (COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END)) * 100),1) AS NPS, 
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END) as Cdet,
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) as Cpro,
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minMediumNps AND $this->_maxMediumNps THEN 1 END) as Cneu,              
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END) as total, 
                                    ((COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)*100)/
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END)) as detractor, 
                                    ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END)*100)/
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END)) as promotor, 
                                    ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMediumNps AND $this->_maxMediumNps THEN 1 END)*100)/
                                    COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END)) as neutral,              
                                    a.mes, a.annio, WEEK(date_survey) AS week, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek, $this->_fieldSelectInQuery  
                                    FROM $this->_dbSelected.$table as a
                                    INNER JOIN $this->_dbSelected." . $table . "_start as b ON a.token = b.token 
                                    WHERE  $where $activeP2 $datafilters 
                                    GROUP BY $group
                                    ORDER BY date_survey ASC");
            }
        }

        if ($filter == 'all') {
            $indicador2 = $indicador;

            $data = DB::select("SELECT SUM(NPS) AS NPS, SUM(total) as total,SUM(detractor) as detractor,SUM(promotor) as promotor,SUM(neutral) as neutral, mes , annio, sum(Cdet) as Cdet, sum(Cpro) as Cpro, sum(Cneu) as Cneu, WEEK(date_survey) AS week, $this->_fieldSelectInQuery
                                FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS, 
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
					            count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
					            count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
                                count(*) as total, 
                                ((count(if($indicador < 7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as detractor, 
                                ((count(if($indicador > 8, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as promotor, 
                                ((count(if($indicador <= 8 AND $indicador >=7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as neutral, a.mes, a.annio,date_survey,WEEK(date_survey) AS week, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$table as a
                                LEFT JOIN $this->_dbSelected.".$table."_start as b ON a.token = b.token 
                                WHERE $where $datafilters
                                GROUP BY $group
                                UNION
                                SELECT ROUND(((COUNT(CASE WHEN $indicador2 BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                COUNT(CASE WHEN $indicador2 BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                (COUNT($indicador2) - COUNT(CASE WHEN $indicador2=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS, 
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
                                count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
                                count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
                                count(*) as total, 
                                ((count(if($indicador2 < 7, $indicador2, NULL))*100)/count(CASE WHEN $indicador2 != 99 THEN $indicador2 END)*$this->_porcentageVid) as detractor, 
                                ((count(if($indicador2 > 8, $indicador2, NULL))*100)/count(CASE WHEN $indicador2 != 99 THEN $indicador2 END)*$this->_porcentageVid) as promotor, 
                                ((count(if($indicador2 <= 8 AND $indicador2 >=7, $indicador2, NULL))*100)/count(CASE WHEN $indicador2 != 99 THEN $indicador2 END)*$this->_porcentageVid) as neutral,              
                                a.mes, a.annio,date_survey, WEEK(date_survey) AS week, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$table2 as a
                                LEFT JOIN $this->_dbSelected." . $table2 . "_start as b ON a.token = b.token 
                                WHERE $where $datafilters
                                GROUP BY $group) AS A " . $group2 . "ORDER BY date_survey ASC");
        }

        if ($data) {
            if ($data[0]->total === null) {
                foreach ($data as $key => $value) {
                    if ($struct != 'one') {
                        $graphNPS[] = [
                            'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Lun ' . date('d',strtotime($value->mondayWeek)). '-' .date('m',strtotime($value->mondayWeek)) . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                            'values' => [
                                "promoters"     => round($value->promotor),
                                "neutrals"      => ($value->promotor == 0 && $value->detractor == 0) ? round($value->neutral) : 100 - (round($value->detractor) + round($value->promotor)),//100 - (round($value->promotor) + round($value->detractor)),
                                "detractors"    => round($value->detractor),
                                "nps"           => round($value->NPS)
                            ],
                        ];
                    }
                    if ($struct == 'one') {
                        $graphNPS[] = [
                            "value" => $value->NPS
                        ];
                    }
                }
            }
            if ($data[0]->total !== null) {
                foreach ($data as $key => $value) {
                    if ($struct != 'one') {
                        $graphNPS[] = [
                            'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Lun ' . date('d',strtotime($value->mondayWeek)). '-' .date('m',strtotime($value->mondayWeek)) . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                            'values' => [
                                "promoters"     => round($value->promotor),
                                "neutrals"      => ($value->promotor == 0 && $value->detractor == 0) ? round($value->neutral) : 100 - (round($value->detractor) + round($value->promotor)),//100 - (round($value->promotor) + round($value->detractor)),
                                "detractors"    => round($value->detractor),
                                "nps"           => round($value->NPS)
                            ],
                        ];
                    }
                    if ($struct == 'one') {
                        $graphNPS[] = [
                            "value" => $value->NPS
                        ];
                    }
                }
            }
        }
       
        if ($data === null) {
                if ($struct != 'one') {
                    $graphNPS[] = [
                        'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Lun ' . date('d',strtotime($value->mondayWeek)). '-' .date('m',strtotime($value->mondayWeek)) . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                        'values' => [
                            "promoters"     => 0,
                            "neutrals"      => 0,
                            "detractors"    => 0,
                            "nps"           => 0,
                        ],
                    ];
                }
                if ($struct == 'one') {
                    $graphNPS[] = [
                        "value" => 0
                    ];
                }
        }

        return $graphNPS;
    }


    private function graphINS($tiempoVehiculo, $coordAnden, $tiempoAeropuerto, $tiempoLlegadaAnden)
    {
        foreach ($tiempoVehiculo as $key => $value) {

            $graphISN[] = [
                'xLegend'  => $value['xLegend'],
                'values' => [
                    "TV"            => $value['values']['ins'],
                    "CA"            => $coordAnden[$key]['values']['ins'],
                    "TA"            => $tiempoAeropuerto[$key]['values']['ins'],
                    "TLA"           => $tiempoLlegadaAnden[$key]['values']['ins']
                ],
            ];
        }
        return [
            "height" => 4,
            "width" => 12,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => "ISN ",
                "chart" => [
                    "fields" => [
                        [
                            "type" => "stacked-bar",
                            "key" => "TV",
                            "text" => "Tiempo espera vehículo",
                            "bgColor" => "#90C8F5",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "CA",
                            "text" => "Coordinación andén",
                            "bgColor" => "#FFB203",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "TA",
                            "text" => "Tiempo espera aeropuerto",
                            "bgColor" => "#17C784",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "TLA",
                            "text" => "Tiempo llegada andén",
                            "bgColor" => "#F580E7",
                        ],
                        //'#90C8F5', '#F580E7'
                    ],
                    "values" =>  $graphISN
                ],
            ],
        ];
    }

    //OKK
    private function csatPreviousPeriod($table, $dateEnd, $dateIni, $indicador, $filter, $datafilters)
    {
        $monthAntEnd = date('m') - 1; 
        $annio = date('Y'); 
        $monthActualEnd= substr($dateIni, 5,2); 
    
        if($monthActualEnd > 1 && $monthActualEnd < 11){
            $monthAntEnd = '0'.($monthActualEnd - 1);
        }
        if($monthActualEnd == 1){
            $monthAntEnd = 12;
            $annio = date('Y') - 1;
        }
        if($monthActualEnd > 10){
            $monthAntEnd = $monthActualEnd - 1;
        }

        $mes = $monthAntEnd;

        if ($filter != 'all') {
            //if(substr($table, 6, 7) != 'tra_via') {
                $data = DB::select("SELECT ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as CSAT
                                    FROM $this->_dbSelected.$table
                                    WHERE mes = $mes AND annio = $annio");
            //}
            // if(substr($table, 6, 7) == 'tra_via') {
            //         $dateSurvey = 'fechaservicio';
            //         $fecha = $annio . '-' . $mes . '-01' ;
            //         $fecha2 = $annio . '-' . $mes . '-31';
            //         $dateFilter = $dateSurvey . " between '" . $fecha . "' and '" . $fecha2 . "'";
    
            //     $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN csat BETWEEN 6 AND 7 THEN 1 END) -
            //                         COUNT(CASE WHEN csat BETWEEN 1 AND 4 THEN 1 END)) /
            //                         (COUNT(CASE WHEN csat != 99 THEN csat END)) * 100),1) AS INS,
            //                         ROUND(((COUNT(CASE WHEN nps BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            //                         COUNT(CASE WHEN nps BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            //                         (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS
            //                         FROM $this->_dbSelected.$table as a
            //                         left join $this->_dbSelected." . $table . "_start as b
            //                         on a.token = b.token
            //                         WHERE " . $dateFilter ." AND etapaencuesta = 'P2' $datafilters");
            // }
        }

        if ($filter == 'all') {
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            $data = DB::select("SELECT SUM(CSAT) AS CSAT 
                                FROM (SELECT ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageBan as CSAT
                                from $this->_dbSelected.$table as a
                                left join $this->_dbSelected." . $table . "_start as b
                                on a.token = b.token
                                WHERE a.mes = $mes AND a.annio = $annio  $datafilters
                                UNION 
                                SELECT ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageVid as CSAT
                                from $this->_dbSelected.$table2 as a
                                left join $this->_dbSelected.".$table2."_start as b
                                on a.token = b.token
                                WHERE a.mes = $mes AND a.annio = $annio $datafilters ) AS A");
        }

            return $data[0]->CSAT;
    }

    //OKK
    private function resumenCsat($table, $dateIni, $dateEnd, $indicador, $filter, $datafilters = null)
    {
        $table2 = '';
        if ($datafilters)
        $datafilters = " AND $datafilters";

        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($table, 6, 3) == 'ban' || substr($table, 6, 3) == 'vid')
            $activeP2 ='';
        
        $dateSurvey = 'date_survey';
        $groupBy = ''; // ' GROUP BY a.mes, a.annio ';
        if(substr($table, 6, 3) == 'tra' && substr($table, 10, 3) == 'via')
        {
            $dateSurvey = 'fechaservicio';
            $groupBy = '';
        };

        if ($filter != 'all') {
            if (substr($table, 6, 3) != 'mut') {
                $data = DB::select("SELECT count(*) as total,
                                    ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as csat, 
                                    $this->_fieldSelectInQuery
                                    FROM $this->_dbSelected.$table as a
                                    INNER JOIN $this->_dbSelected." . $table . "_start as b  ON a.token  =  b.token 
                                         WHERE " . $dateSurvey . " BETWEEN '$dateEnd' AND '$dateIni'  $activeP2 $datafilters
                                         " . $groupBy);
     
            }
        }

        if ($filter == 'all') {
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;

            $data = DB::select("SELECT SUM(total) AS total, SUM(csat) AS csat, $this->_fieldSelectInQuery
                                FROM (  SELECT count(*) as total, date_survey, a.mes, a.annio,
                                ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageBan as csat, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$table as a
                                INNER JOIN $this->_dbSelected.".$table."_start as b  ON a.token  =  b.token 
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters
                                UNION
                                SELECT count(*) as total, date_survey, a.mes, a.annio,
                                ((COUNT(CASE WHEN $indicador2 BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador2 END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageVid as csat, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$table2 as a
                                INNER JOIN $this->_dbSelected.".$table2."_start as b  ON a.token  =  b.token 
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters) AS A ");
        }

        $csatPreviousPeriod = $this->csatPreviousPeriod($table,$dateIni, $dateEnd, $indicador, $filter,  $datafilters);

        $csatActive = 0;
       // print_r($data);
        if (($data == null) || $data[0]->total == null || $data[0]->csat == null) {
          
            $csatActive =  $csatActive;
            return [
                "name"          => "csat",
                "value"         => 'N/A',
                "percentage"    => (string)Round($csatActive-$csatPreviousPeriod),
                "smAvg"         => '',
                //"smAvg"         => 0,
            ];
        }

        if ($data[0]->total != null) {
           
            $csatActive = $data[0]->csat;
            return [
                "name"          => "csat",
                "value"         => ROUND($data[0]->csat),
                "percentage"    => ROUND($data[0]->csat) - ROUND($csatPreviousPeriod),
                //"smAvg"         => 0,
            ];
        }
    }

    private function infoCsat($table, $dateIni, $dateEnd, $indicador)
    {
        $generalDataCsat            = $this->resumenCsat($table, $dateIni, $dateEnd, $indicador, $this->_initialFilter);
        $generalDataCsat['graph']   = $this->graphCsat($table,  $indicador, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $this->_initialFilter, 'one');
        return $generalDataCsat;
    }


    private function graphCsat($table,  $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    { 
        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($table, 6, 3) == 'ban' || substr($table, 6, 3) == 'vid')
            $activeP2 ='';

        if ($group !== null) {
            $where = $datafilters;
            $datafilters = '';
            $group2 = "week";
        }

        if ($group === null) {
            $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
            $group = " a.mes, a.annio ";
        }
        if ($datafilters)
            $datafilters = " AND $datafilters";

        $dateSurvey = 'date_survey';
        $groupBy = ' GROUP BY a.mes ';
        if(substr($table, 6, 3) == 'tra' && substr($table, 10, 3) == 'via')
        {
            $dateSurvey = 'fechaservicio';
            $groupBy = '';
        }  

        $graphCSAT = array();
        if ($filter != 'all') {
            if (substr($table, 6, 3) != 'mut') {

                $data = DB::select("SELECT COUNT(if( $indicador between $this->_minMaxCsat and $this->_maxMaxCsat, $indicador, NULL))/COUNT(CASE WHEN $indicador != 99 THEN $indicador END)*100 AS csat, 
                                    ROUND((count(if($indicador <= $this->_maxCsat , $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as detractor, 
                                    ROUND((count(if($indicador = $this->_minMaxCsat OR $indicador = $this->_maxMaxCsat, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as promotor, 
                                    ROUND((count(if($indicador = $this->_minMediumCsat OR $indicador = $this->_maxMediumCsat, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as neutral,              
                                    count(if($indicador <= $this->_maxCsat, $indicador, NULL)) as Cinsa, 
                                    count(if($indicador = $this->_minMaxCsat OR $indicador = $this->_maxMaxCsat, $indicador, NULL)) as Csati, 
                                    count(if($indicador = $this->_minMediumCsat OR $indicador = $this->_maxMediumCsat, $indicador, NULL)) as Cneut,
                                    a.mes, a.annio, date_survey, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek, $this->_fieldSelectInQuery 
                                    FROM $this->_dbSelected.$table as a
                                    INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b. token 
                                    WHERE " . $dateSurvey . " BETWEEN '$dateEnd' AND '$dateIni' $activeP2 $datafilters
                                    " . $groupBy . "
                                    ORDER BY " . $dateSurvey . " asc");

            }

        }
        if ($filter == 'all') {
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;

            $data = DB::select("SELECT SUM(csat) as csat, mes, annio, date_survey FROM
                                (SELECT ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END))*0.77 AS csat,
                                a.mes, a.annio, date_survey, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$table as a
                                INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b. token
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' and  $indicador != 99  $datafilters
                                GROUP BY $group
                                UNION
                                SELECT ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END))*0.23 AS csat,
                                a.mes, a.annio, date_survey, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$table2 as a
                                INNER JOIN $this->_dbSelected." . $table2 . "_start as b on a.token = b. token
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' and  $indicador != 99  $datafilters
                                GROUP BY $group
                                ) AS A
                                GROUP BY mes, annio
                                ORDER BY date_survey ASC ");
        }
       
        if (!empty($data)) {
            if(substr($table, 6, 3) != 'jet'){       
                foreach ($data as $key => $value) {
                    if ($struct != 'one') {
                        $graphCSAT[] = [
                            'xLegend'  => (string)$value->mes . '-' . $value->annio,
                            'values'   => [
                                'csat' => (string)ROUND($value->csat)
                            ]
                        ];
                    }
                    
                    if ($struct == 'one') {
                        $graphCSAT[] = [
                            "value" => (string)ROUND($value->csat)
                        ];
                    }
                }
            }
            if(substr($table, 6, 3) == 'jet'){       
                foreach ($data as $key => $value) {
                    if ($struct != 'one') {
                        $graphCSAT[] = [
                          
                            'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cinsa + $value->Cneut + $value->Csati) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cinsa + $value->Cneut + $value->Csati) . ')',
                            'values' => [
                                "promoters"     => round($value->promotor),
                                "neutrals"      => ($value->promotor == 0 && $value->detractor == 0) ? $value->neutral : 100 - (round($value->detractor) + round($value->promotor)),//100 - (round($value->promotor) + round($value->detractor)),
                                "detractors"    => round($value->detractor),
                                "csat"          => (string)ROUND($value->csat),
                            ],
                        ];
                    }
                    if ($struct == 'one') {
                        $graphCSAT[] = [
                            "value" => (string)ROUND($value->csat)
                        ];
                    }
                }
            }    
        }
        //print_r($graphCSAT);exit;
        return $graphCSAT;
    }

    public function getFielInDbCsat($survey)
    {
        $csatInDb = 'csat';
        return $csatInDb;
    }

    public function getFielInDbCes($survey)
    {
        $cesInDb = 'ces';
        return $cesInDb;
    }

    private function closedloopTransvip($datafilters, $dateEnd, $dateIni, $survey)
    {
        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";

        if(substr($survey,3,3) == 'con'){
            $db = 'adata_tra_cond';

            $data = DB::select("SELECT count(case when estado_close = 0 then 1 end) as created,
                                count(case when estado_close = 1 then 1 end) as close, date_survey, b.mes, b.annio
                                from customer_colmena.".$db."_start as a
                                left join customer_colmena." .$db." as b
                                on a.token = b.token
                                where date_survey BETWEEN '$dateIni' AND'$dateEnd' and etapaencuesta = 'P2' $datafilters
                                GROUP by  b.mes, b.annio
                                order by  b.annio, b.mes asc");
        }
        
        if(substr($survey,3,3) == 'via')
        {
            $db = 'adata_tra_via';

            $data = DB::select("SELECT count(case when estado_close = 0 then 1 end) as created,
                                count(case when estado_close = 1 then 1 end) as close, fechaservicio, MONTH(fechaservicio) as mes, YEAR(fechaservicio) as annio
                                from customer_colmena.".$db."_start as a
                                left join customer_colmena." .$db." as b
                                on a.token = b.token
                                where fechaservicio BETWEEN '$dateIni' AND'$dateEnd' and etapaencuesta = 'P2' $datafilters
                                GROUP BY MONTH(fechaservicio), YEAR(fechaservicio)
                                ORDER BY YEAR(fechaservicio), MONTH(fechaservicio) ASC");
        }

        if($data)
        {
            foreach ($data as $key => $value) {
                $closedLoopTransvip[] = [
                    'xLegend'   => (string)$value->mes . '-' . $value->annio,
                    'values'    => [
                        "create"    => (int)$value->created,
                        "close"     => (int)$value->close,
                    ],
                ];
            }
        }

        if(!$data)
        {
            $closedLoopTransvip[] = [
                'xLegend'   => 'N/A',
                'values'    => [
                    "create"    => 'N/A',
                    "close"     => 'N/A',
                ],
            ];   
        }

        return  $closedLoopTransvip;
    }

    // Funciones para JETSMART
    // Grph CBI para jestmart
    private function graphCbi($table, $mes, $annio, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null)
    {
        if ($datafilters)
        $datafilters = " AND $datafilters";
        $graphCBI = [];
        $activeP2 ='';

        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($table, 6, 3) == 'ban' || substr($table, 6, 3) == 'vid')
            $activeP2 ='';

        $data = DB::select("SELECT COUNT(CASE WHEN $indicador = 4 OR $indicador = 5 THEN 1 END)/COUNT(CASE WHEN $indicador BETWEEN 1 AND 5 THEN 1 END)*100 AS cbi,
                            COUNT(CASE WHEN $indicador BETWEEN 1 AND 5 THEN 1 END) as total,
                            COUNT(CASE WHEN $indicador BETWEEN 1 AND 2 THEN 1 END) as Cnretorna,
                            COUNT(CASE WHEN $indicador = 3 THEN 1 END) as Cnsabe,
                            COUNT(CASE WHEN $indicador BETWEEN 4 AND 5 THEN 1 END) as Cretorna,
                            ROUND(COUNT(CASE WHEN $indicador BETWEEN 1 AND 2 THEN 1 END)/COUNT(CASE WHEN $indicador BETWEEN 1 AND 5 THEN 1 END)*100) as nretorna,
                            ROUND(COUNT(CASE WHEN $indicador = 3 THEN 1 END)/COUNT(CASE WHEN $indicador BETWEEN 1 AND 5 THEN 1 END)*100) as nsabe,
                            ROUND(COUNT(CASE WHEN $indicador BETWEEN 4 AND 5 THEN 1 END)/COUNT(CASE WHEN $indicador BETWEEN 1 AND 5 THEN 1 END)*100) as retorna,
                            a.mes, a.annio, date_survey, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek, $this->_fieldSelectInQuery 
                            FROM $this->_dbSelected.$table as a
                            INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b. token 
                            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $activeP2 $datafilters
                            GROUP BY a.mes
                            ORDER BY date_survey asc");
        
        if (!empty($data)) {
            foreach ($data as $key => $value) {
            
                if ($struct != 'one') {
                    $graphCBI[] = [
                        //'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                        'xLegend'  => (string)$value->mes . '-' . $value->annio . ' (' . $value->total . ')',
                        'values'   => [
                            'cbi' => (string)ROUND($value->cbi),
                            'promoters' => (string)ROUND($value->retorna),
                            'neutrals' => (ROUND($value->retorna) == 0 && ROUND($value->nretorna) == 0) ? (string)ROUND($value->nsabe) : (string)(100 - ROUND($value->nretorna) - ROUND($value->retorna)),
                            'detractors' => (string)ROUND($value->nretorna)
                        ]
                    ];
                }
                if ($struct == 'one') {
                    $graphCBI[] = [
                        "value" => (string)ROUND($value->cbi)
                    ];
                }
            }
        }
        return $graphCBI;
    }

    //Función para valores de los gráficos de CES

    private function graphCes($table, $mes, $annio, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    { 
        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($table, 10, 3) == 'ban' || substr($table, 10, 3) == 'vid')
            $activeP2 ='';

        if ($group !== null) {
            $where = $datafilters;
            $datafilters = '';
            $group2 = "week";
        }

        if ($group === null) {
            $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
            $group = " a.mes, a.annio ";
        }
        if ($datafilters)
            $datafilters = " AND $datafilters";
    
        $graphCES = array();

        if (substr($table, 10, 3) == 'com') {
        
            $data = DB::select("SELECT (COUNT(if($indicador between   $this->_minMaxCes and $this->_maxMaxCes  , $indicador, NULL)) - 
                                COUNT(if($indicador between $this->_minCes and $this->_maxCes , $indicador, NULL))) * 100
                                /COUNT(CASE WHEN $indicador != 99 THEN $indicador END) AS ces, 
                                COUNT(CASE WHEN $indicador != 99 THEN $indicador END) AS total, 
                                ROUND((count(if($indicador = $this->_minCes OR $indicador = $this->_maxCes, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as dificil, 
                                ROUND((count(if($indicador = $this->_minMaxCes OR $indicador = $this->_maxMaxCes, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as facil, 
                                ROUND((count(if($indicador =  $this->_minMediumCes, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as neutral,
                                a.mes, a.annio, date_survey, gen, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek 
                                FROM $this->_dbSelected.$table as a
                                INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b. token 
                                WHERE $where $activeP2 $datafilters
                                GROUP BY $group
                                ORDER BY date_survey asc");
        }

        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if ($struct != 'one') {
                    $graphCES[] = [
                        //'xLegend'  => (string)$value->mes . '-' . $value->annio,
                        'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->total) . ')' : 'Semana ' . $value->week . ' (' . ($value->total) . ')',
                        'values' => [
                            "promoters"  => round($value->facil),
                            "neutrals"   => ($value->facil == 0 && $value->dificil == 0) ? round($value->neutral) : 100 - (round($value->facil) + round($value->dificil)),//100 - (round($value->facil) + round($value->dificil)),
                            "detractors" => round($value->dificil),
                            'ces' => (string)ROUND($value->ces)
                        ],
                    ];
                }
                if ($struct == 'one') {
                    $graphCES[] = [
                        "value" => (string)ROUND($value->ces)
                    ];
                }
            }


        }

        if (empty($data)) {         
                    $graphCES[] = [
                        'xLegend'  => (string)date('m') . '-' . date('Y'),
                        'values' => [
                            "promoters"  => 0,
                            "neutrals"   => 0,
                            "detractors" => 0,
                            'ces' => ''
                        ],
                    ];
                
                if ($struct == 'one') {
                    $graphCES[] = [
                        "value" => ''
                    ];
                }
        }

        return $graphCES;
    }

    // Graph CBI propiedades para mandar al front

    private function graphsStruct($data, $width, $key)
    {
        return [
            "height" => 3,
            "width" => $width,
            "type" => "chart",
            "props" => [
                "callToAction" => null,
                "icon" => "arrow-right",
                "text" => strtoupper($key),
                "chart" => [
                    "fields" => [
                        // [
                        //     "type" => "bar",
                        //     "key" => $key,
                        //     "text" => strtoupper($key),
                        //     "bgColor" => "#FFB203",
                        // ],
                        [
                            "type" => "stacked-bar",
                            "key" => "detractors",
                            "text" => 'No Volverían',
                            "bgColor" => "#fe4560",
                        ],

                        [
                            "type" => "stacked-bar",
                            "key" => "neutrals",
                            "text" => "Neutro",
                            "bgColor" => "#FFC700",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "promoters",
                            "text" => "Volverían",
                            "bgColor" => "#17C784",
                        ],
                        [
                            "type" => "line",
                            "key" => "cbi",
                            "text" => 'CBI',
                            "bgColor" => "#1a90ff",
                        ],
                    ],
                    "values" => $data,
                ]
            ]

        ];
    }

    private function detailStats($db, $indicatorCBI, $indicatorNPS, $indicatorCSAT, $indicatorGroup, $dateIni, $dateEnd, $filter, $datafilters = null, $jetNames)
    {
        if ($datafilters)
            $datafilters = " AND $datafilters";

        $data = DB::select("SELECT COUNT(CASE WHEN a.$indicatorCBI BETWEEN 1 AND 5 THEN 1 END) as Total, 
                            ROUND(COUNT(CASE WHEN a.$indicatorCBI BETWEEN 4 AND 5 THEN 1 END) * 100 /
                            COUNT(CASE WHEN a.$indicatorCBI BETWEEN 1 AND 5 THEN 1 END)) AS CBI,
                            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                            COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                            (COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxMaxNps THEN 1 END)) * 100),1) AS NPS, 
                            ROUND(COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxCsat AND $this->_minMaxCsat THEN 1 END) * 100 /
                            COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END)) AS CSAT, 
                            $indicatorGroup, $this->_fieldSelectInQuery
                            FROM $this->_dbSelected.$db as a 
                            LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token 
                            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND $indicatorGroup != 99 AND etapaencuesta = 'P2' $datafilters
                            GROUP BY $indicatorGroup");

        $count = 0;
        $dataArray = [];

        if ($data != null) {
            foreach ($data as $key => $value) {

                $this->setAnomalias($value->NPS, $jetNames['title'] . ' ' . $jetNames['data'][$count]["percentage"] . ' NPS');
                $this->setAnomaliasCBI($value->CBI, $jetNames['title'] . ' ' . $jetNames['data'][$count]["percentage"] . ' CBI');
                $dataObj = [
                    "icon" => $jetNames['data'][$count]["icon"],
                    "percentage" => $jetNames['data'][$count]["percentage"],
                    "quantity" =>  $jetNames['data'][$count]["quantity"],
                    "items" => [
                        [
                            "type" => "CBI",
                            "value" =>  $value->CBI,
                            "aditionalText" => "%" . $this->setTextAnomaliasCBI($value->CBI)['text'],
                            "textColor" => $this->setTextAnomaliasCBI($value->CBI)['color'], //'rgb(0,0,0)'
                        ],
                        [
                            "type" => "NPS",
                            "value" =>  $value->NPS,
                            "aditionalText" => "%" . $this->setTextAnomalias($value->NPS)['text'],
                            "textColor" => $this->setTextAnomalias($value->NPS)['color']
                        ],
                        [
                            "type" => "CSAT",
                            "value" => $value->CSAT,
                            "aditionalText" => "%",
                            "textColor" => 'rgb(0,0,0)'
                        ],
                        [
                            "type" => "Cantidad de respuestas",
                            "value" =>  $value->Total,
                            "textColor" => '#000'
                        ]
                    ]
                ];
             
                array_push($dataArray, $dataObj);
                $count++;
            }
        }

        if ($data == null) {
            foreach ($jetNames['data'] as $key => $jetName) {
                $dataObj = [
                    "icon" =>  $jetName["icon"],
                    "percentage" => $jetName["percentage"],
                    "quantity" =>   $jetName["quantity"],
                    "items" => [
                        [
                            "type" => "CBI",
                            "value" =>  0,
                            "aditionalText" => "%",
                            "textColor" => '-'
                        ],
                        [
                            "type" => "NPS",
                            "value" =>  0,
                            "aditionalText" => "%",
                            "textColor" => '-'
                        ],
                        [
                            "type" => "CSAT",
                            "value" => 0,
                            "aditionalText" => "%",
                            "textColor" => 'rgb(0,0,0)'
                        ],
                        [
                            "type" => "Cantidad de respuestas",
                            "value" =>  0,
                            "textColor" => '#000'
                        ]
                    ]
                ];

                array_push($dataArray, $dataObj);
                $count++;
            }
        }

        return [
            "height" => 3,
            "width" => 12,
            "type" => "compare-list",
            "props" => [
                "icon" => "arrow-right",
                "text" => "STATS by " . $jetNames['title'],
                "compareList" => $dataArray
            ]
        ];
    }


    //Funcion para calcular promedios para grafico GAP

    private function gapJetsmart($db, $survey,$indicador,$dateIni, $dateEnd, $struct, $datafilters = null){
        if ($datafilters)
            $datafilters = " AND $datafilters";

        $dataArr = [];
        $query = '';

        $endCsat = $this->getEndCsat($survey);

        for($i = 1; $i <=  $endCsat; $i++)
        {
            if ($i != $endCsat)
                $query .= "ROUND(AVG(CASE WHEN $indicador$i != 99 THEN $indicador$i END), 1) AS $indicador$i, ";
            
            if ($i == $endCsat)
                $query .= "ROUND(AVG(CASE WHEN $indicador$i != 99 THEN $indicador$i END), 1) AS $indicador$i";

        }

        $data = DB::select("SELECT $query FROM $this->_dbSelected.$db WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND etapaencuesta = 'P2' $datafilters"); //Cambiar mes = 3 por la variable $mes

        for($i = 1; $i <= $endCsat; $i++)
        {
            $ind =  $indicador.strval($i);

            if($struct[$i-1]['name'] != 'N/A')
            {
                if($data[0]->$ind != null)
                {   
                    $obj = [
                        'xLegend' => $struct[$i-1]['name'],
                        'values' =>[
                            'exp' =>  $struct[$i-1]['exp'],
                            'driver' =>  $data[0]->$ind,
                            'dif' => round(  $data[0]->$ind - $struct[$i-1]['exp'], 1)
                        ]
                    ];
                }

                if($data[0]->$ind == null)
                {   
                    $obj = [
                        'xLegend' => $struct[$i-1]['name'],
                        'values' =>[
                            'exp' =>  $struct[$i-1]['exp'],
                            'driver' => 0,
                            'dif' => 0 - $struct[$i-1]['exp']
                        ]
                    ];
                }
                array_push($dataArr,$obj);
            }
        }
    return 
 
    [
        "height" => 4,
        "width" => 12,
        "type" => "chart",
        "props" => 
            [
                "icon" => "arrow-right",
                "text" => "Expectativa vs. Realidad",
                'chart' =>
                    [
                        'yAxis' => false,
                        'xAxisPadding' => 15,
                        'fields' =>
                            [
                                [
                                    'type' => "line",
                                    'key' => "exp",
                                    'text' => "Expectativa",
                                    'strokeColor' => "red",
                                    'activeDot' => false,
                                    'customDot' => true,
                                ],
                                [
                                    'type' => "line",
                                    'key' => "driver",
                                    'text' => "Realidad",
                                    'strokeColor' => "blue",
                                    'activeDot' => false,
                                    'customDot' => true,
                                ],
                                [
                                    'type' => "line",
                                    'key' => "dif",
                                    'text' => "GAP",
                                    'strokeColor' => "gray",
                                    'activeDot' => false,
                                    'strokeDash' => "4 2",
                                ]
                            ], //Aca termina field
                        'values' => $dataArr
                    ]
            ]
    ];
    }

    // Fin Funciones para JETSMART

    // Funciones para Transvip
    private function GraphCSATDriversTransvip($db, $survey,  $dateEnd, $dateIni, $filter, $struct = 'two', $datafilters = null)
    {
        $graphCSAT = [];

        $endCsat = $this->getEndCsat($survey);
        $fieldBd = $this->getFielInDbCsat($survey);

        $activeP2 = " etapaencuesta = 'P2' AND ";
        if(substr($db, 6, 3) == 'ban' || substr($db, 6, 3) == 'vid')
            $activeP2 ='';

        $query = "";

        if ($datafilters)
            $datafilters = " AND $datafilters";

        $fieldBd = $this->getFielInDbCsat($survey);
        $query = "";
        for ($i = 1; $i <= $endCsat; $i++) {

            if ($i != $endCsat) {
                $query .= " ((COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN 1 END) - COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxCsat THEN 1 END)) * 100) / COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END) AS  $fieldBd$i, 
                            ((COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxCsat THEN 1 END) * 100) / COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END)) AS detractor$i, 
                            ((COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN 1 END) * 100) / COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END)) AS promotor$i, 
                            ((COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_maxMediumCsat  AND $this->_minMediumCsat THEN 1 END) * 100) / COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END)) AS neutral$i,";
            }
            if ($i == $endCsat) {
                $query .= " ((COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN 1 END) - COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxCsat THEN 1 END)) * 100) / COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END) AS  $fieldBd$i, 
                            ((COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxCsat THEN 1 END) * 100) / COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END)) AS detractor$i, 
                            ((COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN 1 END) * 100) / COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END)) AS promotor$i, 
                            ((COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_maxMediumCsat  AND $this->_minMediumCsat THEN 1 END) * 100) / COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND $this->_maxMaxCsat THEN 1 END)) AS neutral$i ";
            }
        }
       
        if(substr($db, 6, 7) != 'tra_via')
        {   
            $data = DB::select("SELECT $query, date_survey
                                FROM $this->_dbSelected.$db AS a
                                LEFT JOIN $this->_dbSelected." . $db . "_start AS b
                                ON a.token = b.token 
                                WHERE $activeP2 date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters
                                ORDER BY date_survey" );
        }

        if(substr($db, 6, 7) == 'tra_via')
        {   
            $data = DB::select("SELECT $query, fechaservicio
                                FROM $this->_dbSelected.$db AS a
                                LEFT JOIN $this->_dbSelected." . $db . "_start AS b
                                ON a.token = b.token 
                                WHERE  $activeP2 fechaservicio BETWEEN '$dateIni' AND '$dateEnd' $datafilters
                                ORDER BY fechaservicio" );
        }

        $suite = new Suite($this->_jwt);

        if ($data != null) {
            foreach ($data as $key => $value) {
                for ($i = 1; $i <= $endCsat; $i++) {
                    $r   = 'csat' . $i;
                    $pro = 'promotor' . $i;
                    $neu = 'neutral' . $i;
                    $det = 'detractor' . $i;
                    $csat = $value->$r;

                    if ($struct == 'two') {
                        $graphCSAT[] = [
                            'xLegend'  => $suite->getInformationDriver($survey . '_' . $r),
                            'values' =>
                            [
                                "promoters"     => round($value->$pro),
                                "neutrals"      => ($value->$pro == 0 && $value->$det == 0) ? round($value->$neu) : 100 - (round($value->$det) + round($value->$pro)),
                                "detractors"    => round($value->$det),
                                "csat"          => round($csat)
                            ]
                        ];
                    }

                    if ($struct == 'one') {
                        $graphCSAT[] =
                            [
                                'text'  =>  $suite->getInformationDriver($survey . '_' . $r),
                                'values' => ROUND($csat)
                            ];
                    }
                }
            }
        }

        if ($data == null) {
            foreach ($data as $key => $value) {
                for ($i = 1; $i <= $endCsat; $i++) {
                    $r   = 'csat' . $i;
                    $pro = 'promotor' . $i;
                    $neu = 'neutral' . $i;
                    $det = 'detractor' . $i;
                    $csat = $value->$r;

                    if ($struct == 'two') {
                        $graphCSAT[] = [
                            'xLegend'  => $suite->getInformationDriver($survey . '_' . $r),
                            'values' =>
                            [
                                "promoters"     => 0,
                                "neutrals"      => 0,
                                "detractors"    => 0,
                                "csat"          => 0
                            ]
                        ];
                    }

                    if ($struct == 'one') {
                        $graphCSAT[] =
                            [
                                'text'  => '',
                                'values' => 0
                            ];
                    }
                }
            }
        }
      
        return $graphCSAT;
    }

    private function rankingTransvipData($db, $datafilters, $dateIni, $dateEnd, $indicador, $text)
    {
        $values = [];
        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";

        if($text != "Atributos más importantes")
        {
            $query = "SELECT $indicador AS nombre, COUNT(CASE WHEN $indicador != 99 AND $indicador != '' THEN 1 END) AS total
                      FROM $this->_dbSelected.$db AS a
                      LEFT JOIN $this->_dbSelected." . $db . "_start AS b 
                      ON a.token = b.token 
                      WHERE etapaencuesta = 'P2' AND date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters AND $indicador != 99 AND $indicador != '' 
                      GROUP BY  $indicador
                      ORDER BY total DESC";
         
            $data = DB::select($query);

            $totalAcum = 0;
    
            foreach ($data as $key => $value) {
                $totalAcum = $totalAcum + $value->total;
            }
    
            foreach ($data as $key => $value) {
                $values[] = [
                    'text'  => $value->nombre == "0" ? "No" : ($value->nombre == "1" ? "Si" : str_replace("u00f3", "ó", str_replace("&nt", "ños", html_entity_decode(str_replace("&amp;","&",$value->nombre))))),
                    'cant'   => $value->total,
                    'porcentaje'   => ROUND($value->total * 100 / $totalAcum) . " %",
                    ];
            
            }
        }
        
        if($text == "Atributos más importantes")
        {
            $query = '';

            $fields = [
                'calidApp' => 'Calidad y funcionamiento de la App Conductores', 
                'cantServ' => 'Cantidad de servicios ofrecidos', 
                'seguridad' => 'Seguridad', 
                'canCom' => 'Canales de comunicaciu00f3n con empresa', 
                'ingProm' => 'Ingreso promedio por viaje', 
                'flexHor' =>'Flexibilidad de horario', 
                'tipoClient' =>'Tipo de Cliente'
            ];

            $count = 0; 

            foreach ($fields as $key => $value) {
                if($count == (count($fields)-1))
                {
                    $query .= " COUNT(CASE WHEN json_contains(`mult1`, '" . '"'. $value . '"'. "', '$') THEN 1 END) AS " . $key ." ";
                } 
                else
                {
                    $query .= " COUNT(CASE WHEN json_contains(`mult1`, '" . '"'. $value . '"'. "', '$') THEN 1 END) AS " . $key .",";
                }
                $count++;
            }
            $query = "SELECT $query
                      FROM $this->_dbSelected.$db AS a
                      LEFT JOIN $this->_dbSelected." . $db . "_start as b 
                      ON a.token = b.token 
                      WHERE etapaencuesta = 'P2' AND date_survey  BETWEEN '$dateEnd' AND '$dateIni' $datafilters AND $indicador != 99 AND $indicador != ''";

            $data = DB::select($query);
            $totalAcum = 0;
            $dataVal = array();
            foreach ($fields as $key => $value) {
                $totalAcum = $totalAcum + intval($data[0]->$key);
                $dataVal[$key] = $data[0]->$key;
            }
            arsort($dataVal);
            if ($totalAcum != 0) 
            {
                foreach ($dataVal as $key => $value) {
            
                    $values[] = [
                        'text'  => str_replace("u00f3", "ó",$fields[$key]),
                        'cant'  => $value,
                        'porcentaje'   => ROUND($value * 100 / $totalAcum) . " %",
                    ];
                
                }
            }
            if ($totalAcum == 0)
            {
                $values = [];
            } 
        }

        return $values;
    }

    private function rankingTransvip($db, $datafilters, $dateIni, $dateEnd, $indicador, $text, $height, $width)
    {
        $values = $this->rankingTransvipData($db, $datafilters, $dateIni, $dateEnd, $indicador, $text);

        $standarStruct = [
            [
                "text" => "Nombres",
                "key" => "text",
                "cellColor" => "#17C784",
                "textAlign" => "left"
            ],
            [
                "text" => "Cant. Resp.",
                "key" => "cant",
                "cellColor" => "#17C784",
                "textAlign" => "center"
            ],
            [
                "text" => "Porcentaje",
                "key" => "porcentaje",
                "cellColor" => "#17C784",
                "textAlign" => "center"
            ]
        ];

        return [
            "height" =>  $height,
            "width" =>  $width,
            "type" =>  "tables",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => $text,
                "tables" => [
                    [
                        "columns" => [
                            $standarStruct[0],
                            $standarStruct[1],
                            $standarStruct[2]
                        ],
                        "values" => $values,
                    ],
                ]
            ]
        ];
        
    }

    private function rankingInconvLlegada($db, $datafilters, $dateIni, $dateEnd, $indicador, $text, $height, $width)
    {
        $values = [];

        $sino2 = $this->rankingTransvipData($db, $datafilters, $dateIni, $dateEnd, 'sino2', 'Compra equipaje WEB');
        $sino3 = $this->rankingTransvipData($db, $datafilters, $dateIni, $dateEnd, 'sino3', 'Notificación itinerario vuelo');
        $sino4 = $this->rankingTransvipData($db, $datafilters, $dateIni, $dateEnd, 'sino4', 'Atención Contact Center');

        foreach ($sino2 as $key => $value) {
            if($value['text'] == 'Si'){
                $value['text'] = 'Compra equipaje WEB';
                $values[] = $value;
            }
        }

        foreach ($sino3 as $key => $value) {
            if($value['text'] == 'Si'){
                $value['text'] = 'Notificación itinerario vuelo';
                $values[] = $value;
            }
        }

        foreach ($sino4 as $key => $value) {
            if($value['text'] == 'Si'){
                $value['text'] = 'Atención Contact Center';
                $values[] = $value;
            }
        }

        $standarStruct = [
            [
                "text" => "Nombres",
                "key" => "text",
                "cellColor" => "#17C784",
                "textAlign" => "left"
            ],
            [
                "text" => "Cant. Resp.",
                "key" => "cant",
                "cellColor" => "#17C784",
                "textAlign" => "center"
            ],
            [
                "text" => "Porcentaje",
                "key" => "porcentaje",
                "cellColor" => "#17C784",
                "textAlign" => "center"
            ]
        ];

        return [
            "height" =>  $height,
            "width" =>  $width,
            "type" =>  "tables",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => $text,
                "tables" => [
                    [
                        "columns" => [
                            $standarStruct[0],
                            $standarStruct[1],
                            $standarStruct[2],
                        ],
                        "values" => $values,
                    ],
                ]
            ]
        ];
        
    }

    //Fin funciones para Transvip

    private function cbiResp($db, $datafilters, $dateIni, $dateEnd)
    {
        $activeP2 = " etapaencuesta = 'P2' AND ";
        if(substr($db, 6, 3) == 'ban' || substr($db, 6, 3) == 'vid')
            $activeP2 ='';

        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";


        if(substr($db, 6, 7) != 'tra_via' && substr($db,6,3) != 'jet')
        {

            $data = DB::select("SELECT COUNT(CASE WHEN cbi BETWEEN 4 AND 5 THEN 1 END)*100/COUNT(CASE WHEN cbi BETWEEN 1 AND 5 THEN 1 END) AS cbi,
                                COUNT(CASE WHEN cbi BETWEEN 1 AND 5 THEN 1 END) AS Total, a.mes, a.annio, date_survey 
                                FROM $this->_dbSelected.$db AS a
                                LEFT JOIN $this->_dbSelected." . $db . "_start AS b 
                                ON a.token = b.token 
                                WHERE $activeP2 cbi != 99 AND date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters
                                GROUP BY a.mes, a.annio ORDER BY a.annio, a.mes ASC");
        }

        if(substr($db, 6, 7) == 'tra_via')
        {
            $data = DB::select("SELECT COUNT(CASE WHEN cbi BETWEEN 4 AND 5 THEN 1 END) * 100 / COUNT(CASE WHEN cbi BETWEEN 1 AND 5 THEN 1 END) AS cbi,
                                COUNT(CASE WHEN cbi BETWEEN 1 AND 5 THEN 1 END) AS Total, MONTH(fechaservicio) AS mes, YEAR(fechaservicio) AS annio, fechaservicio 
                                FROM $this->_dbSelected.$db AS a
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b 
                                ON a.token = b.token 
                                WHERE $activeP2 fechaservicio BETWEEN '$dateEnd' AND '$dateIni' $datafilters
                                GROUP BY MONTH(fechaservicio), YEAR(fechaservicio) ORDER BY YEAR(fechaservicio), MONTH(fechaservicio) ASC");
        }
        
                        
        if(substr($db,6,3) == 'tra'){
            if($data)
            {
                $acumuladoResp = 0;
                foreach ($data as $key => $value) {
                    $acumuladoResp += (int)$value->Total;
                    $cbiResp[] = [
                        'xLegend'   => (string)$value->mes . '-' . $value->annio . '(' . $value->Total . ')',
                        'values'    => [
                            "cbi"               => (int)$value->cbi,
                            "total"             => (int)$value->Total,
                            "acumuladoResp"     => (int)$acumuladoResp,
                        ],
                    ];
                }
            }

            if(!$data)
            {
                $cbiResp[] = [
                    'xLegend'   => 'N/A',
                    'values'    => [
                        "cbi"               => 'N/A',
                        "total"             => 'N/A',
                        "acumuladoResp"     => 'N/A',
                    ],
                ];   
            }

            return $cbiResp;
        }

        if(substr($db,6,3) == 'jet'){
           
            $monthAct = date('m');
            $annioAct = date('Y');
            
            $cbiSmAvg = $this->AVGLast6MonthCBI($db, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), 'cbi', $datafilters);

            $data = DB::select("SELECT ROUND((COUNT(CASE WHEN cbi BETWEEN 4 AND 5 THEN 1 END)  /
                                    (COUNT(CASE WHEN cbi != 99 THEN 'cbi' END)) * 100)) AS CBI
                                    from $this->_dbSelected.$db as a
                                    left join $this->_dbSelected." . $db . "_start as b 
                                    on a.token = b.token  
                                    WHERE $activeP2 date_survey BETWEEN '$dateEnd' AND '$dateIni' AND etapaencuesta = 'P2' $datafilters
                                    "); // Ver si group by a.mes, a.annio se agrega despues de $datafilters

            $cbiPreviousPeriod = $this->cbiPreviousPeriod($db, $dateIni, $dateEnd, 'cbi', $datafilters);
           

            if($data && $data[0]->CBI != NULL){
                foreach ($data as $key => $value) { 
                    $generalDataCbi = [                 
                        "name"          => "cbi", //Ver después como hacemos
                        "value"         => (int)$value->CBI,
                        "percentage"    => ROUND($value->CBI - $cbiPreviousPeriod[0]->CBI),                 
                        "smAvg"         => $cbiSmAvg,
                    ];
                }
            }

            if(!$data || $data[0]->CBI == NULL){
                $generalDataCbi = [                 
                    "name"          => "cbi", //Ver después como hacemos
                    "value"         => 'N/A',
                    "percentage"    => ROUND(- $cbiPreviousPeriod[0]->CBI),                 
                    "smAvg"         => $cbiSmAvg,
                ];
            }
            $generalDataCbi['graph'] = $this->graphCbi($db, date('m'), date('Y'), 'cbi', date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")),  $datafilters, 'one');
            //print_r($generalDataCbi);
            return $generalDataCbi;
        }
        
    }


    private function graphCbiResp($datasCbiResp)
    {
        return [
            "height" => 3,
            "width" => 6,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => "CBI vs Respuestas",
                "chart" => [
                    "fields" => [
                        [
                            "type"          => "area",
                            "key"           => "acumuladoResp",
                            "text"          => "Acumulados",
                            "strokeColor"   => "#D0D0D0",
                            "bgColor"       => "#D0D0D0",
                        ],
                        [
                            "type" => "line",
                            "key" => "cbi",
                            "text" => "CBI",
                            "strokeColor" => "#17C784",
                            "yAxisId" => "axis-right",
                        ],
                        [
                            "type" => "y-axis",
                            "orientation" => "right",
                            "unit" => "%",
                            "key" => "cbi",
                            "text" => "helper text",
                            "value" => "axis-right",
                        ],
                        [
                            "type" => "line",
                            "key" => "total",
                            "text" => "Respuestas",
                            "strokeColor" => "#FFB203",
                        ],
                    ],
                    "values" => $datasCbiResp
                ],
            ],
        ];
    }


    private function globales($db, $dateIni, $dateEnd, $indicatorBD, $indicatorName, $indic1, $indic2, $height, $datafilters)
    {
        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";

        if(substr($db, 6, 7) != 'tra_via')
        {
            $queryTra = "SELECT DISTINCT(b.$indicatorBD) as $indicatorName, 
                    count(case when nps != 99 then 1 end) as Total, 
                    round(((count(case when csat between 6 and 7 then 1 end) - count(case when csat between 1 and 5 then 1 end))*100)/count(case when csat != 99 then 1 end)) as $indic2,
                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as nps,
                    count(case when $indic1 between 4 and 5 then 1 end)*100/count(case when $indic1 != 99 then 1 end) as $indic1,
                    a.mes, a.annio 
                    FROM $this->_dbSelected.$db as a
                    left join $this->_dbSelected." . $db . "_start as b
                    on a.token = b.token 
                    where datesurvey BETWEEN '$dateEnd' and '$dateIni' and b.$indicatorBD != '' and etapaencuesta = 'P2'  $datafilters
                    GROUP by $indicatorName
                    order by $indicatorName";
        }

        if(substr($db, 6, 7) == 'tra_via')
        {
            $group = $indicatorName;
            if($indicatorName == 'Reserva'){
                $group = 'b.'.$indicatorBD;
            }

            $queryTra = "SELECT DISTINCT(b.$indicatorBD) as $indicatorName, 
                         count(case when nps != 99 then 1 end) as Total, 
                         round(((count(case when csat between 6 and 7 then 1 end) - count(case when csat between 1 and 5 then 1 end))*100)/count(case when csat != 99 then 1 end)) as $indic2,
                         round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as nps,
                         count(case when $indic1 between 4 and 5 then 1 end)*100/count(case when $indic1 != 99 then 1 end) as $indic1,
                         MONTH(fechaservicio) as mes, YEAR(fechaservicio) as annio
                         FROM $this->_dbSelected.$db as a
                         left join $this->_dbSelected." . $db . "_start as b
                         on a.token = b.token 
                         where fechaservicio BETWEEN '$dateEnd' and '$dateIni' and b.$indicatorBD != '' and etapaencuesta = 'P2' $datafilters
                         GROUP by $group
                         order by $indicatorName";
        }

        $data = DB::select($queryTra);
        $lastSentido  = '';
        $values = [];

        foreach ($data as $key => $value) {

            if ($value->$indicatorName != $lastSentido) {
                $lastSentido = $value->$indicatorName;
                $values[$lastSentido] = [];
                $rowData = [];

                array_push(
                    $values[$value->$indicatorName],
                    [
                        array_merge(
                            ['Indicator' => 'NPS'],
                            $rowData
                        )
                    ]
                );
                array_push(
                    $values[$value->$indicatorName],
                    [
                        array_merge(
                            ['Indicator' => 'INS'],
                            $rowData
                        )
                    ]
                );
                array_push(
                    $values[$value->$indicatorName],
                    [
                        array_merge(
                            ['Indicator' => 'CBI'],
                            $rowData
                        )
                    ]
                );
            };
    
            foreach ($data as $index => $dato) {
                if ($value->nps != null) {
                    if ($lastSentido == $dato->$indicatorName) {
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 3][0]['Respuestas']         = $value->Total;
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 3][0]['Valor']  = round($dato->nps) . '%';
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 3][0]['rowSpan']  = ['cells' => 3, 'key' => "Respuestas"];
                        // $values[$lastSentido][sizeof($values[$lastSentido]) - 3][0]['textColor']  = ['color' => $this->setTextAnomalias($dato->nps), 'key' => 'nps'];
                        // $this->setAnomalias($dato->nps, $lastSentido);
                    }
                }

                if ($value->$indic2 != null) { //INS
                    if ($lastSentido == $dato->$indicatorName) {
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 2][0]['Valor'] = round($dato->$indic2) . '%';
                    }
                }
                if ($value->$indic1 != null) { //CBI
                    if ($lastSentido == $dato->$indicatorName) {
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['Valor'] = round($dato->$indic1) . '%';
                    }
                }
            }
            
        }
    
        $colums = [
            $indicatorName => $indicatorName,
            'Indicator' => 'Indicadores',
        ];
        $colums['Valor']='Valor';
        $colums['Respuestas']='Respuestas';

        return [
            "height" => $height,
            "width" => 6,
            "type" =>  "compose-table",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "NPS, INS y CBI By " . $indicatorName,
                "data" => [
                    "columns" => [$colums],
                    "values" => $values,
                    "colors" => [
                        $indicatorName => "#17C784",
                        'Indicator' => "#17C784",
                    ]
                ],
            ]
        ];
    }

    private function NpsIsnTransvip($table,$dateIni, $dateEnd,$indicadorNPS, $indicadorINS,$datafilters, $group, $perf = null){
        $data = [];
        
        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($table, 6, 3) == 'ban' || substr($table, 6, 3) == 'vid')
            $activeP2 ='';
        
        if ($datafilters && $group == null)
            $datafilters = " AND $datafilters ";

        if(substr($table, 6, 7) != 'tra_via')
        {
            if($group != null){
                $where = $datafilters;
                // exit;
                $datafilters = '';
                $group = "week";
            }
    
    
            if ($group == null) {
                $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
                $group = " a.mes, a.annio ";
                if (substr($datafilters, 30, 3) == 'NOW') {
                    $datafilters = '';
                }
            }

            $data = DB::select("SELECT COUNT(CASE WHEN a.$indicadorNPS!=99 THEN 1 END) as Total, 
                                ROUND(((COUNT(CASE WHEN a.$indicadorNPS BETWEEN 9 AND 10 THEN 1 END) - COUNT(CASE WHEN a.$indicadorNPS BETWEEN 0 AND 6 THEN 1 END)) / (COUNT(CASE WHEN a.$indicadorNPS!=99 THEN 1 END)) * 100),1) AS NPS, 
                                ROUND(((COUNT(CASE WHEN a.$indicadorINS BETWEEN 6 AND 7 THEN 1 END) - COUNT(CASE WHEN a.$indicadorINS BETWEEN 1 AND 4 THEN 1 END)) / (COUNT(CASE WHEN a.$indicadorINS!=99 THEN 1 END)) * 100),1) AS INS,
                                a.mes, a.annio, date_survey, WEEK(date_survey) AS week, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek
                                from $this->_dbSelected.$table as a
                                left join $this->_dbSelected." . $table . "_start as b
                                on a.token = b.token
                                where $where $activeP2 $datafilters
                                GROUP by $group
                                ORDER by a.date_survey ASC"); 
        }

        if(substr($table, 6, 7) == 'tra_via')
        {
            if($group != null){
                $where = $datafilters;
                $datafilters = '';
                $group = "week";
            }
    
    
            if ($group == null) {
                $where = " fechaservicio BETWEEN '$dateEnd' AND '$dateIni' ";
                $group = " MONTH(fechaservicio), YEAR(fechaservicio) ";
                if (substr($datafilters, 30, 3) == 'NOW') {
                    $datafilters = '';
                }
            }

            $data = DB::select("SELECT COUNT(CASE WHEN a.$indicadorNPS!=99 THEN 1 END) as Total, 
                                ROUND(((COUNT(CASE WHEN a.$indicadorNPS BETWEEN 9 AND 10 THEN 1 END) - COUNT(CASE WHEN a.$indicadorNPS BETWEEN 0 AND 6 THEN 1 END)) / (COUNT(CASE WHEN a.$indicadorNPS!=99 THEN 1 END)) * 100),1) AS NPS, 
                                ROUND(((COUNT(CASE WHEN a.$indicadorINS BETWEEN 6 AND 7 THEN 1 END) - COUNT(CASE WHEN a.$indicadorINS BETWEEN 1 AND 4 THEN 1 END)) / (COUNT(CASE WHEN a.$indicadorINS!=99 THEN 1 END)) * 100),1) AS INS,
                                MONTH(fechaservicio) as mes, YEAR(fechaservicio) as annio, fechaservicio, WEEK(fechaservicio) AS week, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek
                                from $this->_dbSelected.$table as a
                                left join $this->_dbSelected." . $table . "_start as b
                                on a.token = b.token
                                where $where $activeP2 $datafilters
                                GROUP BY $group
                                ORDER by fechaservicio ASC"); 
        }

        if(count($data) != 0)
        {
            foreach ($data as $key => $value) {
                if ($key == 0) {
                    $insPreviousPeriod = 0;
                }
               
                $NpsInsTransvip[] = [
                    'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Total) . ')' : 'Lun ' . date('d',strtotime($value->mondayWeek)). '-' .date('m',strtotime($value->mondayWeek)) . ' (' . ($value->Total) . ')',
                    'values'    => [
                        "nps"           => Round($value->NPS),
                        "ins"           => Round($value->INS),
                        "percentage"    => round($value->INS) - round($insPreviousPeriod)
                    ],
                ];
                // $count -= 1;
            }
        }
        
        if(count($data) == 0)
        {
            $NpsInsTransvip[] = [
                'xLegend'  => 'N/A',
                'values'    => [
                    "nps"           => 0,
                    "ins"           => 0,
                    "percentage"    => 0
                ],
            ];

            if($perf == 'x'){
                return [
                    "name"          => "ISN",
                    "value"         => "N/A",
                    "percentage"    => "N/A"
                ];
            }
        }
        
        if($perf == 'x'){
            return [
                "name"          =>"ISN",
                "value"         =>$value->mes == date('m') ? Round($value->INS) : 'N/A',
                "percentage"    => 0
            ];
        }
       
        return $NpsInsTransvip;
    }
    
    private function graphNpsBanVid($table,$table2,$mes,$annio,$indicador,$dateIni, $dateEnd, $datafilters, $group = null){
        $table2 = $this->primaryTable($table);
        $group2 = "week";
        if ($group !== null) {
            //$where = " date_survey between date_sub(NOW(), interval 9 week) and NOW() and WEEK(date_survey) != 0 ";
            $where = $datafilters;
            $datafilters = '';
            //$group = " week ";
        }

        if ($group === null) {
            $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
            $group = " mes, annio ";
            $group2 = " mes, annio ";
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($table2 != null) {
            $graphNPSBanVid = [];
            $data = DB::select("SELECT sum(NPS) as NPS,sum(detractor) AS detractor,sum(promotor) AS promotor,sum(neutral) as neutral, mes, annio, sum(Cdet) as Cdet, sum(Cpro) as Cpro, sum(Cneu) as Cneu, $this->_fieldSelectInQuery
                                FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                COUNT(CASE WHEN $indicador!=99 THEN 1 END) * 100),1)*$this->_porcentageBan AS NPS,
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
                                count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
                                count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
                                ((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as detractor, 
                                ((count(if($indicador> 8 AND $indicador <=10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as promotor, 
                                ((count(if($indicador=8 OR $indicador=7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as neutral,
                                a.mes, a.annio, date_survey, $this->_fieldSelectInQuery 
                                FROM $this->_dbSelected.$table as a
                                INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b.token
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters	
                                Group BY a.annio,a.mes
                                UNION 
                                SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / COUNT(CASE WHEN $indicador!=99 THEN 1 END) * 100),1)*$this->_porcentageVid AS NPS,
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
                                count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
                                count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
                                ((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageVid as detractor,
                                ((count(if($indicador> 8 AND $indicador <=10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageVid as promotor, 
                                ((count(if($indicador=8 OR $indicador=7, nps, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageVid as neutral, 
                                a.mes, a.annio, date_survey, $this->_fieldSelectInQuery  
                                FROM $this->_dbSelected.$table2 as a
                                INNER JOIN $this->_dbSelected." . $table2 . "_start as b on a.token = b.token 
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters Group BY a.annio, a.mes ) as A 
                                Group BY annio, mes 
                                ORDER BY date_survey ASC"); 
                                
            }
            
            if($table2 == null){
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                COUNT(CASE WHEN $indicador!=99 THEN 1 END) * 100),1)*$this->_porcentageBan AS NPS,
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
                                count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
                                count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
                                ((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as detractor, 
                                ((count(if($indicador> 8 AND $indicador <=10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as promotor, 
                                ((count(if($indicador=8 OR $indicador=7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as neutral,
                                a.mes, a.annio, date_survey, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek, $this->_fieldSelectInQuery 
                                FROM $this->_dbSelected.$table as a
                                INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b.token
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters	
                                Group BY a.mes");
        }

        foreach ($data as $key => $value) {

            $graphNPSBanVid[] = [
                'xLegend'   => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                'values'    => [
                    "promoters"     => Round($value->promotor),
                    "neutrals"      => ($value->promotor == 0 && $value->detractor == 0) ? round($value->neutral) : 100 - (round($value->detractor) + round($value->promotor)),//100 - (Round($value->promotor) + Round($value->detractor)),
                    "detractors"    => Round($value->detractor),
                    "nps"           => Round($value->NPS)
                ],
            ];
        }

        return $graphNPSBanVid;
    }

    public function downloadExcel($request, $jwt)
    {
        $startDate  = $request->get('startDate');
        $endDate    = $request->get('endDate');
        $survey     = $request->get('survey');
        if (!isset($startDate) && !isset($endDate) && !isset($survey)) {
            return ['datas' => 'unauthorized', 'status' => Response::HTTP_NOT_ACCEPTABLE];
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://customerscoops.com/descarga_bases/download_excel.php?startDate=" . $startDate . "&endDate=" . $endDate . "&survey=" . $survey,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        return ($response);
        exit;
    }

    public function matriz($request)
    {
        if ($request->get('startDate') == null) {
            $request->merge([
                'startDate' => date('Y-m-d', strtotime(date('Y-m-01') . "- $this->_periodCxWord month")),
                'endDate'   => date('Y-m-d'),
            ]);
        }

        if ($request->get('startDate') != null) {
            $request->merge([
                'startDate' => $request->get('startDate'),
                'endDate'   => $request->get('endDate'),
            ]);
        }

        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $survey = $request->get('survey');

        $value  = \Cache::get('mt' . $survey . $request->get('startDate') . $request->get('endDate'));
        //$value = \Cache::pull('mt'.$survey.$request->get('startDate').$request->get('endDate'));
        if ($value)
            return $value;

        if(!isset($startDate)&& !isset($endDate) && !isset($survey)){return ['datas'=>'unauthorized', 'status'=>Response::HTTP_NOT_ACCEPTABLE];}
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://customerscoops.com/matriz/calculo_matriz_full.php?startDate=$startDate&endDate=$endDate&survey=$survey",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            //CURLOPT_POSTFIELDS => array('nom' => $nombre,'mail' => $mail,'token' => $hash,'encuesta' => $encuesta),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $resp = ['datas' => json_decode($response), 'status' => Response::HTTP_OK];

        \Cache::put('mt' . $survey . $request->get('startDate') . $request->get('endDate'), $resp, $this->expiresAtCache);
        return $resp;
    }

    public function textMining($request){

        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $survey = $request->get('survey');

        if(substr($survey,0,3) == 'mut'){

            return ['datas'=>'unauthorized', 'status'=>Response::HTTP_NOT_ACCEPTABLE];
        }
        //if(!isset($startDate)&& !isset($endDate) && !isset($survey)){return ['datas'=>'unauthorized', 'status'=>Response::HTTP_NOT_ACCEPTABLE];}
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL =>"https://customerscoops.com/text_mining/text_analytics_new.php?startDate=$startDate&endDate=$endDate&survey=$survey",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        //CURLOPT_POSTFIELDS => array('nom' => $nombre,'mail' => $mail,'token' => $hash,'encuesta' => $encuesta),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        if($response)
            return ['datas'=>json_decode($response), 'status'=>Response::HTTP_OK];
    }

    private function detailGeneration($db, $indicatorNPS, $indicatorCSAT, $dateIni, $dateEnd, $filter, $datafilters = null, $indetifyClient)
    {
        $quantityz  = 0;
        $quantitym  = 0;
        $quantityx  = 0;
        $quantityb  = 0;
        $quantitys  = 0;

        $npsz       = 0;
        $npsm       = 0;
        $npsx       = 0;
        $npsb       = 0;
        $npss       = 0;

        $csatz      = 0;
        $csatm      = 0;
        $csatx      = 0;
        $csatb      = 0;
        $csats      = 0;

        $anomaliasZ = 0;
        $anomaliasM = 0;
        $anomaliasX = 0;
        $anomaliasB = 0;
        $anomaliasS = 0;

        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter != 'all') {
            
            if (substr($db, 6, 3) != 'jet'){
               // if ($indetifyClient == 'vid')
                   // $db = $this->primaryTable($db);

                $data = DB::select("SELECT COUNT(*) as Total,  
                                    ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                    COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                    (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) AS NPS, 
                                    ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL ))) AS CSAT, age, $this->_fieldSelectInQuery
                                    FROM $this->_dbSelected.$db as a 
                                    LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token 
                                    WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') and nps!= 99  $datafilters
                                    GROUP BY (b.age BETWEEN 14 AND 22), (b.age BETWEEN 23 AND 38), (b.age BETWEEN 39 AND 54), (b.age BETWEEN 55 AND 73), (b.age BETWEEN 74 AND 99)");
            }
        }

        if ($filter == 'all') {
            $db2    = $this->primaryTable($db);

            $data   = DB::select("SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, age, $this->_fieldSelectInQuery
                                  FROM (SELECT COUNT(*) as Total, b.age,
                                  ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                  COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                  COUNT(CASE WHEN a.$indicatorNPS!=99 THEN 1 END) * 100)*$this->_porcentageBan) AS NPS,
                                  ROUND((COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan) AS CSAT, $this->_fieldSelectInQuery
                                  FROM $this->_dbSelected.$db as a
                                  LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token 
                                  WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') and nps!= 99  $datafilters
                                  GROUP BY (b.age BETWEEN 14 AND 22), (b.age BETWEEN 23 AND 38), (b.age BETWEEN 39 AND 54), (b.age BETWEEN 55 AND 73), (b.age BETWEEN 74 AND 99)
                                  UNION
                                  SELECT COUNT(*) as Total, b.age,
                                  ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                  COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                  COUNT(CASE WHEN a.$indicatorNPS!=99 THEN 1 END) * 100)*$this->_porcentageVid) AS NPS,
                                  ROUND((COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid) AS CSAT, $this->_fieldSelectInQuery
                                  FROM $this->_dbSelected.$db2 as a
                                  LEFT JOIN $this->_dbSelected." . $db2 . "_start as b on a.token = b.token 
                                  WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') and nps!= 99  $datafilters
                                  GROUP BY (b.age BETWEEN 14 AND 22), (b.age BETWEEN 23 AND 38), (b.age BETWEEN 39 AND 54), (b.age BETWEEN 55 AND 73), (b.age BETWEEN 74 AND 99)) AS A
                                  GROUP BY (age BETWEEN 14 AND 22), (age BETWEEN 23 AND 38), (age BETWEEN 39 AND 54), (age BETWEEN 55 AND 73), (age BETWEEN 74 AND 99)");
        }


        foreach ($data as $key => $value) {
            //var_dump($value->age);
            if ($value->age >= 14 && $value->age <= 22) {
                $this->setAnomalias($value->NPS, 'GEN Z');
                // $this->setAnomalias($value->CSAT, 'GEN Z');
                $quantityz = $value->Total;
                $csatz = $value->CSAT;
                $npsz = $value->NPS;
            }
            if ($value->age >= 23 && $value->age <= 38) {
                $this->setAnomalias($value->NPS, 'GEN MILLE');
                //$this->setAnomalias($value->CSAT, 'GEN MILLE');
                $quantitym = $value->Total;
                $csatm     = $value->CSAT;
                $npsm      = $value->NPS;
            }
            if ($value->age >= 39 && $value->age <= 54) {
                $this->setAnomalias($value->NPS, 'GEN X');
                // $this->setAnomalias($value->CSAT, 'GEN X');
                $quantityx = $value->Total;
                $csatx    = $value->CSAT;
                $npsx     = $value->NPS;
            }
            if ($value->age >= 55 && $value->age <= 73) {
                $this->setAnomalias($value->NPS, 'GEN BB');
                // $this->setAnomalias($value->CSAT, 'GEN BB');
                $quantityb = $value->Total;
                $csatb    = $value->CSAT;
                $npsb     = $value->NPS;
            }
            if ($value->age >= 74 && $value->age <= 99) {
                $this->setAnomalias($value->NPS, 'GEN SIL');
                //$this->setAnomalias($value->CSAT, 'GEN SIL');
                $quantitys = $value->Total;
                $csats    = $value->CSAT;
                $npss     = $value->NPS;
            }
            $anomaliasZ = $this->setTextAnomalias($npsz);
            $anomaliasM = $this->setTextAnomalias($npsm);
            $anomaliasX = $this->setTextAnomalias($npsx);
            $anomaliasB = $this->setTextAnomalias($npsb);
            $anomaliasS = $this->setTextAnomalias($npss);
        }

        return [
            "height" => 3,
            "width" => 12,
            "type" => "compare-list",
            "props" => [
                "icon" => "arrow-right",
                "text" => "STATS by Generation",
                "compareList" => [
                    [
                        "icon" => "genz",
                        "percentage" => 'GEN Z',
                        "quantity" =>  '14 - 22',
                        "items" => [
                            [
                                "type" => "NPS",
                                "value" => $npsz,
                                "aditionalText" => "%".$anomaliasZ['text'],
                                "textColor" => $anomaliasZ['color']
                            ],
                            [
                                "type" => "CSAT",
                                "value" => $csatz,
                                "aditionalText" => "%",
                                "textColor"=> 'rgb(0,0,0)'
                            ],
                            [
                                "type" => "Cantidad de respuestas",
                                "value" =>  $quantityz,
                                "textColor" => '#000'
                            ]
                        ],
                    ],
                    [
                        "icon" => "genmille",
                        "percentage" =>  'GEN MILLE',
                        "quantity"  => '23 - 38',
                        "items" => [
                            [
                                "type" => "NPS",
                                "value" => $npsm,
                                "aditionalText" => "%".$anomaliasM['text'],
                                "textColor" => $anomaliasM['color']
                            ],
                            [
                                "type" => "CSAT",
                                "value" => $csatm,
                                "aditionalText" => "%",
                                "textColor"=> 'rgb(0,0,0)'
                            ],
                            [
                                "type" => "Cantidad de respuestas",
                                "value" =>  $quantitym,
                                "textColor" => '#000'
                            ]
                        ],
                    ],
                    [
                        "icon" => "genx",
                        "percentage" => 'GEN X',
                        "quantity" =>   '39 - 54',
                        "items" => [
                            [
                                "type" => "NPS",
                                "value" => $npsx,
                                "aditionalText" => "%".$anomaliasX['text'],
                                "textColor" => $anomaliasX['color']
                            ],
                            [
                                "type" => "CSAT",
                                "value" => $csatx,
                                "aditionalText" => "%",
                                "textColor"=> 'rgb(0,0,0)'
                            ],
                            [
                                "type" => "Cantidad de respuestas",
                                "value" =>  $quantityx,
                                "textColor" => '#000'
                            ]
                        ],
                    ],
                    [
                        "icon" => "genbb",
                        "percentage" => 'GEN BB',
                        "quantity" =>   '55 - 73',
                        "items" => [
                            [
                                "type" => "NPS",
                                "value" => $npsb,
                                "aditionalText" => "%".$anomaliasB['text'],
                                "textColor" => $anomaliasB['color']
                            ],
                            [
                                "type" => "CSAT",
                                "value" => $csatb,
                                "aditionalText" => "%",
                                "textColor"=> 'rgb(0,0,0)'
                            ],
                            [
                                "type" => "Cantidad de respuestas",
                                "value" =>  $quantityb,
                                "textColor" => '#000'
                            ]
                        ],
                    ],
                    [
                        "icon" => "gensil",
                        "percentage" => 'GEN SIL',
                        "quantity" =>   '74 - 91',
                        "items" =>   [
                            [
                                "type" => "NPS",
                                "value" => $npss,
                                "aditionalText" => "%".$anomaliasS['text'],
                                "textColor" => $anomaliasS['color']
                            ],
                            [
                                "type" => "CSAT",
                                "value" => $csats,
                                "aditionalText" => "%",
                                "textColor"=> 'rgb(0,0,0)'
                            ],
                            [
                                "type" => "Cantidad de respuestas",
                                "value" =>  $quantitys,
                                "textColor" => '#000'
                            ]
                        ],
                    ],
                ],
            ]
        ];
    }


    private function detailsGender($db, $indicatorNPS, $indicatorCSAT, $dateIni, $dateEnd, $filter, $datafilters = null, $indetifyClient)
    {
        $promedioF = 0;
        $promedioM = 0;
        $quantityF  = 0;
        $quantityM  = 0;
        $npsF       = 0;
        $csatF      = 0;
        $csatM      = 0;
        $npsM       = 0;
        if ($datafilters)
            $datafilters = " AND $datafilters";
        if ($filter != 'all') {
            //if ($indetifyClient == 'vid')
                //$db =  $this->primaryTable($db);

            $data = DB::select("SELECT COUNT(*) as Total, 
                                ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100)) AS NPS, 
                                ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL ))) AS CSAT, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$db as a 
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token 
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND $this->_fieldSex in(1,2,'F','M') and nps!= 99 $datafilters
                                GROUP BY $this->_fieldSex");
        }

        if ($filter == 'all') {
            $db2  = $this->primaryTable($db);
            //$indicador2 = ($db2 == 'adata_vid_web')?'nps':$indicador;

            $data   = DB::select("SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, $this->_fieldSelectInQuery
                                  FROM (SELECT COUNT(*) as Total, 
                                  ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                  COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                  (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100) *$this->_porcentageBan) AS NPS, 
                                  ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL ))*$this->_porcentageBan) AS CSAT, $this->_fieldSelectInQuery
                                  FROM $this->_dbSelected.$db as a 
                                  LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token 
                                  WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' and nps!= 99 $datafilters
                                  GROUP BY sex
                                  UNION
                                  SELECT COUNT(*) as Total, 
                                  ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                  COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                  (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100)*$this->_porcentageVid) AS NPS, 
                                  ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL ))*$this->_porcentageVid) AS CSAT, $this->_fieldSelectInQuery 
                                  FROM $this->_dbSelected.$db2 as a 
                                  LEFT JOIN $this->_dbSelected." . $db2 . "_start as b on a.token = b.token 
                                  WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' and nps!= 99 $datafilters
                                  GROUP BY sex) AS A
                                  GROUP BY sex");
        }

        foreach ($data as $key => $value) {
            $sex = (string)$this->_fieldSex;
            if ($value->$sex == 'M' || $value->$sex == '1') {
                $this->setAnomalias($value->NPS, 'GENDER MALE');
                //$this->setAnomalias($value->CSAT, 'Gender Male');
                $quantityM = $value->Total;
                $csatM = $value->CSAT;
                $npsM = $value->NPS;
            }
            if ($value->$sex == 'F' ||  $value->$sex == '2') {
                $this->setAnomalias($value->NPS, 'GENDER FEMALE');
                //$this->setAnomalias($value->CSAT, 'Gender Female');
                $quantityF = $value->Total;
                $csatF = $value->CSAT;
                $npsF = $value->NPS;
            }
        }
        $promedio = 0;
        if ($quantityF + $quantityM != 0) {
            $promedioF =  round($quantityF * 100 / ($quantityF + $quantityM));
            $promedioM =  round($quantityM * 100 / ($quantityF + $quantityM));
        }

        $anomaliasF = $this->setTextAnomalias($npsF);
        $anomaliasM = $this->setTextAnomalias($npsM);

        return [
            "height" => 4,
            "width" => 4,
            "type" => "compare-list",
            "props" => [
                "icon" => "arrow-right",
                "text" => "STATS by Gender",
                "compareList" => [
                    [
                        "icon" => "mujer",
                        "percentage" => (int)$promedioF,
                        //"quantity"=>   (int)$quantityF,
                        "items" => [
                            [
                                "type" => "NPS",
                                "value" => ROUND($npsF),
                                "aditionalText" => "%".$anomaliasF['text'],
                                "textColor" => $anomaliasF['color']
                            ],
                            [
                                "type" => "CSAT",
                                "value" => $csatF,
                                "aditionalText" => "%",
                                "textColor"=> 'rgb(0,0,0)'
                            ],
                            [
                                "type" => "Respuestas",
                                "value" => (int)$quantityF,
                                "textColor" => '#000',
                            ]
                        ],
                    ],
                    [
                        "icon" => "hombre",
                        "percentage" =>  $promedioM,
                        //"quantity"  => (int)$quantityM,
                        "items" => [
                            [
                                "type" => "NPS",
                                "value" => $npsM,
                                "aditionalText" => "%".$anomaliasM['text'],
                                "textColor" => $anomaliasM['color']
                            ],
                            [
                                "type" => "CSAT",
                                "value" => $csatM,
                                "aditionalText" => "%",
                                "textColor"=> 'rgb(0,0,0)'
                            ],
                            [
                                "type" => "Respuestas",
                                "value" => (int)$quantityM,
                                "textColor" => '#000',
                            ]
                        ],
                    ],
                ],
            ],
        ];
    }

    private function csatsDriversTransvip($db, $survey, $dateIni, $dateEnd, $datafilters)
    {
        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        $activeP2 = " AND etapaencuesta = 'P2' ";

        if ($datafilters)
            $datafilters = " AND $datafilters";

        $fieldBd = $this->getFielInDbCsat($survey);
        //$fieldBd = 'csat';
        $endCsat = $this->getEndCsat($survey);
        $query = "";
        //$endCsat = 11;

        if(substr($db, 10, 3) != 'via')
        {
            for ($i = 1; $i <= $endCsat; $i++) {

                if ($i != $endCsat) {
                    $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, ";
                }
                if ($i == $endCsat) {
                    $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i ";
                }
            }

            $data = DB::select("SELECT $query, date_survey, A.mes, A.annio
                                FROM $this->_dbSelected.$db as A
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b
                                on A.token = b.token 
                                WHERE date_survey  BETWEEN '$dateEnd' AND  '$dateIni'  $activeP2 $datafilters 
                                group by A.mes, A.annio ORDER BY date_survey");
        }  

        if(substr($db, 10, 3) == 'via')
        {
            for ($i = 1; $i <= $endCsat; $i++) {

                if ($i != $endCsat) {
                    $query .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL)) - COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND  $this->_maxCsat THEN 1 END))* 100)/COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND  $this->_maxMaxCsat THEN 1 END) AS  $fieldBd$i, ";
                }
                if ($i == $endCsat) {
                    $query .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL)) - COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND  $this->_maxCsat THEN 1 END))* 100)/COUNT(CASE WHEN $fieldBd$i BETWEEN $this->_minCsat AND  $this->_maxMaxCsat THEN 1 END) AS  $fieldBd$i ";
                }
            }

            $data = DB::select("SELECT $query, fechaservicio, MONTH(fechaservicio) as mes, YEAR(fechaservicio) as annio
                                FROM $this->_dbSelected.$db as A
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b
                                on A.token = b.token 
                                WHERE fechaservicio  BETWEEN '$dateEnd' AND  '$dateIni'  $activeP2 $datafilters 
                                group by MONTH(fechaservicio), YEAR(fechaservicio) ORDER BY fechaservicio");
        }  

        if($data)
        {
            foreach ($data as $key => $value) {
                $values = [];

                for ($i = 1; $i <= $endCsat; $i++) {

                    $r   = 'csat' . $i;
                    $csat = $value->$r;
                    $values = array_merge($values, [$r  => round($csat)]);
                }

                $graphCSAT[] = [
                    'xLegend'  => (string)$value->mes . '-' . $value->annio,
                    'values' => $values
                ];
            }
        }

        if(!$data)
        {
            $graphCSAT[] = [
                'xLegend'  => 'N/A',
                'values' => 'N/A'
            ];
        }

        return $graphCSAT;
    }  
    
    private function graphCsatTransvip($graphCSAT, $survey){
       
        if(substr($survey,3,3) == 'via')
        {
            $colors = ['#A2F584', '#F5C478', '#90C8F5', '#F580E7', '#3DA3F5', '#F5483E', '#8F65C2', '#F5EB4E', '#FFB203','#F5C76C', '#7DF5C5'];
            $drivers = ['Canal','Tiempo encontrar un conductor','Coordinación en Andén','Puntualidad del servicio','Tiempo llegada del vehículo',
            'Tiempo de espera aeropuerto','Seguridad al trasladarte','Medidas Covid','Tiempo de traslado','Atención del Conductor','Conducción'];
        }
        if(substr($survey,3,3) == 'con')
        {
            $colors = ['#A2F584', '#F5C478', '#90C8F5', '#F580E7', '#3DA3F5', '#F5483E', '#8F65C2', '#F5EB4E'];
            $drivers = [
                "Proceso de inscripción, registro y activación",
                "Orientación inicial",
                "Aplicación Conductores",
                "Medidas de identificación y verificación de pasajeros",
                "Central de operaciones - Tráfico",
                "Soporte",
                "Pago de producción mensual",
            ];
        }
                $fields = [];
        for($i=1; $i <= count($drivers); $i++){
            array_push($fields,[
                        "type"=>"line",
                        "key"=>"csat".$i,
                        "text"=>$drivers[$i-1],
                        "strokeColor"=> $colors[$i-1],
                    ]);
        }
      
        return [
            "height" => 4,
            "width" => 12,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => "Csat Drivers",
                "chart" => [
                    "fields" => $fields,
                    "values" => $graphCSAT
                ],
            ],
        ];
    }

    private function GraphCSATDrivers($db, $db2, $survey, $indicatorCSAT,  $dateEnd, $dateIni, $filter, $struct = 'two', $datafilters = null)
    {
        $graphCSAT = [];

        $endCsat = $this->getEndCsat($survey);
        $fieldBd = $this->getFielInDbCsat($survey);
        $fieldBd2 = $this->getFielInDbCsat($survey);

        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($db, 6, 3) == 'ban' || substr($db, 6, 3) == 'vid')
            $activeP2 ='';

        $query = "";
        $query2 = "";
        $select = "";
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter == 'all') {
            $fieldBd = $this->getFielInDbCsat($survey);
            $query = "";
            for ($i = 1; $i <= $endCsat; $i++) {
                $select .= " ROUND(SUM(csat$i)) AS csat$i, SUM(detractor$i) AS detractor$i, SUM(promotor$i) AS promotor$i, SUM(neutral$i) AS neutral$i,";
                if ($i != $endCsat) {
                    $query .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageBan AS csat$i,
                                ((count(if(csat$i between $this->_minCsat and $this->_maxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageBan) as detractor$i, 
                                ((count(if(csat$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageBan) as promotor$i, 
                                ((count(if(csat$i = $this->_maxMediumCsat or  csat$i = $this->_minMediumCsat, csat$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)*$this->_porcentageBan) as neutral$i,";
                }

                if ($i == $endCsat) {
                    $select .= " ROUND(SUM(csat$i)) AS csat$i, SUM(detractor$i) AS detractor$i, SUM(promotor$i) AS promotor$i, SUM(neutral$i) AS neutral$i ";
                    $query .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat  OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageBan AS csat$i, 
                                ((count(if(csat$i between $this->_minCsat and $this->_maxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageBan) as detractor$i, 
                                ((count(if(csat$i = $this->_minMaxCsat  OR csat$i = $this->_maxMaxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageBan) as promotor$i, 
                                ((count(if(csat$i = $this->_maxMediumCsat or csat$i = $this->_minMediumCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageBan) as neutral$i ";
                }
            }

            for ($i = 1; $i <= $endCsat; $i++) {
                if ($i != $endCsat) {
                    $query2 .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat  OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageVid  AS csat$i, 
                                 ((count(if(csat$i between $this->_minCsat and $this->_maxCsat, csat$i, NULL))*100))/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageVid as detractor$i, 
                                 ((count(if(csat$i  = $this->_minMaxCsat  OR csat$i = $this->_maxMaxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageVid) as promotor$i, 
                                 ((count(if(csat$i = $this->_maxMediumCsat  or csat$i = $this->_minMediumCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageVid) as neutral$i,";
                }

                if ($i == $endCsat) {
                    $query2 .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat  OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageVid  AS csat$i, 
                                 ((count(if(csat$i between $this->_minCsat and $this->_maxCsat, csat$i, NULL))*100))/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageVid as detractor$i, 
                                 ((count(if(csat$i  = $this->_minMaxCsat  OR csat$i = $this->_maxMaxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageVid) as promotor$i, 
                                 ((count(if(csat$i = $this->_maxMediumCsat  or csat$i = $this->_minMediumCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageVid) as neutral$i ";
                }
            }

            $query1 = "SELECT $query,date_survey,  $this->_fieldSelectInQuery
                        FROM $this->_dbSelected.$db as A
                        LEFT JOIN $this->_dbSelected." . $db . "_start as b
                        on A.token = b.token
                        WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd'  $datafilters";

            $query2 = "SELECT $query2,date_survey,  $this->_fieldSelectInQuery
                        FROM $this->_dbSelected.$db2 as A
                        LEFT JOIN $this->_dbSelected." . $db2 . "_start as b
                        on A.token = b.token 
                        WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd'  $datafilters";

            $queryPrin = "SELECT $select,$this->_fieldSelectInQuery FROM ($query1 UNION $query2) as A ORDER BY date_survey";

            $data = DB::select($queryPrin);
        }
        if ($filter != 'all') {
            $fieldBd = $this->getFielInDbCsat($survey);
            $query = "";
            if(substr($db, 6, 7) != 'jet_vue' && substr($db, 6, 7) != 'jet_com'){
            
                for ($i = 1; $i <= $endCsat; $i++) {

                    if ($i != $endCsat) {
                        $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                    ((count(if(csat$i between $this->_minCsat and $this->_maxCsat,  $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                    ((count(if(csat$i  = $this->_minMaxCsat  OR csat$i = $this->_maxMaxCsat,  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                    ((count(if(csat$i = $this->_maxMediumCsat  or csat$i = $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN   $fieldBd$i END)) as neutral$i,";
                    }
                    if ($i == $endCsat) {
                        $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                    ((count(if(csat$i between $this->_minCsat and $this->_maxCsat,  $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                    ((count(if(csat$i  = $this->_minMaxCsat  OR csat$i = $this->_maxMaxCsat,  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                    ((count(if(csat$i = $this->_maxMediumCsat  or csat$i = $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN  $fieldBd$i END)) as neutral$i ";
                    }
                }
            }

            if(substr($db, 6, 7) == 'jet_vue' || substr($db, 6, 7) == 'jet_com'){

                for ($i = 1; $i <= $endCsat; $i++) {

                    if ($i != $endCsat) {
                        $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCes OR $fieldBd$i = $this->_maxMaxCes, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                    ((count(if(csat$i between $this->_minCes and $this->_maxCes, $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                    ((count(if(csat$i  = $this->_minMaxCes OR csat$i = $this->_maxMaxCes, $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                    ((count(if(csat$i = $this->_minMediumCes or csat$i = $this->_minMediumCes,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN   $fieldBd$i END)) as neutral$i,";
                    }
                    if ($i == $endCsat) {
                        $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCes OR $fieldBd$i = $this->_maxMaxCes, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                    ((count(if(csat$i between $this->_minCes and $this->_maxCes, $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                    ((count(if(csat$i  = $this->_minMaxCes OR csat$i = $this->_maxMaxCes, $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                    ((count(if(csat$i = $this->_minMediumCes or csat$i = $this->_minMediumCes,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN  $fieldBd$i END)) as neutral$i ";
                    }
                }
            }

            if(substr($db, 6, 7) != 'tra_via')
            {   
                $data = DB::select("SELECT $query,date_survey
                    FROM $this->_dbSelected.$db as A
                    LEFT JOIN $this->_dbSelected." . $db . "_start as b
                    on A.token = b.token 
                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $activeP2  $datafilters
                    ORDER BY date_survey" );
            }

            if(substr($db, 6, 7) == 'tra_via')
            {   
                $data = DB::select("SELECT $query, fechaservicio
                    FROM $this->_dbSelected.$db as A
                    LEFT JOIN $this->_dbSelected." . $db . "_start as b
                    on A.token = b.token 
                    WHERE fechaservicio BETWEEN '$dateIni' AND '$dateEnd' $activeP2  $datafilters
                    ORDER BY fechaservicio" );
            }
                             
        }

        $suite = new Suite($this->_jwt);

        if ($data != null) {
            foreach ($data as $key => $value) {
                for ($i = 1; $i <= $endCsat; $i++) {
                    $r   = 'csat' . $i;
                    $pro = 'promotor' . $i;
                    $neu = 'neutral' . $i;
                    $det = 'detractor' . $i;
                    $csat = $value->$r;

                    if ($struct == 'two') {
                        $graphCSAT[] = [
                            'xLegend'  => $suite->getInformationDriver($survey . '_' . $r),
                            'values' =>
                            [
                                "promoters"     => round($value->$pro),
                                "neutrals"      => ($value->$pro == 0 && $value->$det == 0) ? round(round($value->$neu)) : round(100 - (round($value->$det) + round($value->$pro))),//(int)round(100 - (round($value->$det) + round($value->$pro))),
                                "detractors"    => round($value->$det),
                                "csat"          => round($csat)
                            ]
                        ];
                    }

                    if ($struct == 'one') {
                        $graphCSAT[] =
                            [
                                'text'  =>  $suite->getInformationDriver($survey . '_' . $r),
                                'values' => ROUND($csat)
                            ];
                    }
                }
            }
        }

        if ($data == null) {
            foreach ($data as $key => $value) {
                for ($i = 1; $i <= $endCsat; $i++) {
                    $r   = 'csat' . $i;
                    $pro = 'promotor' . $i;
                    $neu = 'neutral' . $i;
                    $det = 'detractor' . $i;
                    $csat = $value->$r;

                    if ($struct == 'two') {
                        $graphCSAT[] = [
                            'xLegend'  => $suite->getInformationDriver($survey . '_' . $r),
                            'values' =>
                            [
                                "promoters"     => 0,
                                "neutrals"      => 0,
                                "detractors"    => 0,
                                "csat"          => 0
                            ]
                        ];
                    }

                    if ($struct == 'one') {
                        $graphCSAT[] =
                            [
                                'text'  => '',
                                'values' => 0
                            ];
                    }
                }
            }
        }
      
        return $graphCSAT;
    }

    /*** GraphCsatDriversAtributos***/

    public function getEndCsatNameAtr($survey, $csat){
        
        $datas = [
            //JetSmart
            "jetcom" => 
            [
                "csat1" => 
                [
                    "end" => "4",
                    "name" => "Experiencia en sitio web",
                    "names" => 
                    [
                        "1"=> "Velocidad de carga",
                        "2"=> "Sitio rápido/ágil",
                        "3"=> "Facilidad para comprar",
                        "4"=> "Facilidad para navegar",
                    ]
                ],
                "csat2" => 
                [
                    "end" => "5",
                    "name" => "Selección de pasajes",
                    "names" => 
                    [
                        "1"=> "Disponibilidad horaria",
                        "2"=> "Tarifas bajas",
                        "3"=> "Beneficio Club de Descuentos",
                        "4"=> "Elección de vuelo",
                        "5"=> "Ingreso de datos de los pasajeros",
                    ]
                ],
                "csat3" => 
                [
                    "end" => "2",
                    "name" => "Selección y compra de equipaje",
                    "names" => 
                    [
                        "1"=> "Precio adecuado",
                        "2"=> "No se entiende selección y precios",
                    ]
                ],
                "csat4" => 
                [
                    "end" => "5",
                    "name" => "Selección de asientos",
                    "names" => 
                    [
                        "1"=> "Información clara",
                        "2"=> "Proceso fácil y rápido",
                        "3"=> "Costo extra",
                        "4"=> "Selección aleatoria",
                        "5"=> "Elección de opcionales/extras",
                    ]
                ],
                "csat5" =>
                [
                    "end" => "6",
                    "name" => "Proceso de pago",
                    "names" => 
                    [
                        "1"=> "Proceso fácil",
                        "2"=> "Procesp rápido/ágil",
                        "3"=> "Proceso seguro",
                        "4"=> "Cantidad de medios de pago",
                        "5"=> "Pago de cuotas sin interés",
                        "6"=> "Generación de errors de transacción",
                    ]
                ],
                "csat6" => 
                [
                    "end" => "3",
                    "name" => "Información en email de confirmación de compra",
                    "names" => 
                    [
                        "1"=> "Información justa y necesaria",
                        "2"=> "Velocidad de confirmación",
                        "3"=> "Existencia de otros canales",
                    ]
                ],
            ],
            "jetvue" => 
            [
                "csat1" => 
                [
                    "end" => "4",
                    "name" => "Check in",
                    "names" => 
                    [
                        "1"=> "Realización del check in",
                        "2"=> "Tiempo de espera",
                        "3"=> "Solicitud de información",
                        "4"=> "Trato durante el check in",
                    ]
                ],
                "csat2" => 
                [
                    "end" => "5",
                    "name" => "Registro de equipaje",
                    "names" => 
                    [
                        "1"=> "Tiempo de espera",
                        "2"=> "Proceso",
                        "3"=> "Tiempo de antelación",
                        "4"=> "Proceso de pago",
                        "5"=> "Trato derante el registro",
                    ]
                ],
                "csat3" => 
                [
                    "end" => "3",
                    "name" => "Embarque del vuelo",
                    "names" => 
                    [
                        "1"=> "Información para encontrar la puerta",
                        "2"=> "Anuncio de embarque",
                        "3"=> "Cobro de equipaje en puerta de embarque",
                    ]
                ],
                "csat4" =>  
                [
                    "end" => "3",
                    "name" => "Abordaje del vuelo",
                    "names" => 
                    [
                        "1"=> "Fluidez en el ingreso",
                        "2"=> "Orientación clara",
                        "3"=> "Sistema de asignación de asientos",
                    ]
                ],
                "csat5" =>  
                [
                    "end" => "3",
                    "name" => "Experiencia durante el vuelo",
                    "names" => 
                    [
                        "1"=> "Amabilidad de la tripulación",
                        "2"=> "Cargos extra por consumos abordo",
                        "3"=> "Modalidad de pago de consumos abordo",
                    ]
                ],
                "csat6" => 
                [
                    "end" => "5",
                    "name" => "Momento de llegada",
                    "names" => 
                    [
                        "1"=> "Proceso de desembarque",
                        "2"=> "Fluidez en el desembarque",
                        "3"=> "Retiro de equipaje",
                        "4"=> "Tiempo de espera para retirar el equipaje",
                        "5"=> "Estado de llegada del equipaje",
                    ]
                ],
                "csat7" =>  
                [
                    "end" => "3",
                    "name" => "Proceso porterior al viaje",
                    "names" => 
                    [
                        "1"=> "Respuesta a las solicitudes",
                        "2"=> "Contacto por inconvenientes durante el vuelo",
                        "3"=> "Tiempo de respuesta",
                    ]
                ],
            ],
        ];

        if (array_key_exists($survey, $datas)) {
            return $datas[$survey][$csat];
        }
        if (!array_key_exists($survey, $datas)) {
            return false;
        }
    }

    protected function CSATDriversAtr($graphCSATDrivers, $title, $height, $width){
        return [
              "height" => $height,
              "width" => $width,
              "type"  => "chart-horizontal",
              "props" => [
                  "icon" => "arrow-right",
                  "text" => $title,
                  "chart" => [
                      "fields" => [
                          [
                              "type" => "stacked-bar",
                              "key" => "detractors",
                              "text" => "Insatisfecho",
                              "bgColor" => "#fe4560",
                          ],
                          [
                              "type" => "stacked-bar",
                              "key" => "neutrals",
                              "text" => "Neutro",
                              "bgColor" => "#FFC700",
                          ],
                          [
                              "type" => "stacked-bar",
                              "key" => "promoters",
                              "text" => "Satisfecho",
                              "bgColor" => "#17C784",
                          ],
                          [
                              "type" => "total",
                              "key" => "csat",
                              "text" => "",
                          ],
                      ],
                      "values" => $graphCSATDrivers
                  ],
              ],
          ];
      }

    private function GraphCSATAtributos($db, $survey, $indicatorCSAT,  $dateEnd, $dateIni, $filter, $struct = 'two', $datafilters = null)
    {
        $graphCSAT = [];

        $endCsatAtr = $this->getEndCsatNameAtr($survey, $indicatorCSAT);
     
        $activeP2 = " AND etapaencuesta = 'P2' ";
        if(substr($db, 6, 3) == 'ban' || substr($db, 6, 3) == 'vid')
            $activeP2 ='';

        $query = "";
        
        if ($datafilters)
            $datafilters = " AND $datafilters";
        
        $query = "(COUNT(if($indicatorCSAT = $this->_maxMaxCes, $indicatorCSAT, NULL))* 100)/COUNT(if($indicatorCSAT !=99,1,NULL )) AS  $indicatorCSAT, 
                 ((count(if($indicatorCSAT between $this->_minCes and $this->_minMediumCes,  $indicatorCSAT, NULL))*100)/count(case when $indicatorCSAT != 99 THEN  $indicatorCSAT END)) as detractor, 
                 ((count(if($indicatorCSAT = $this->_maxMaxCes, $indicatorCSAT, NULL))*100)/count(if($indicatorCSAT !=99,1,NULL ))) as promotor, 
                 ((count(if($indicatorCSAT = $this->_minMaxCes  or $indicatorCSAT = $this->_minMaxCes,  $indicatorCSAT, NULL))*100)/count(case when  $indicatorCSAT != 99 THEN   $indicatorCSAT END)) as neutral,";

        for ($i = 1; $i <= $endCsatAtr["end"]; $i++) {
            if(substr($db, 6, 3) == 'jet' && substr($db, 10, 3) == 'com')
            {
                $indAtrib = "atr".$i."_". $indicatorCSAT;
            }
            if(substr($db, 6, 3) == 'jet' && substr($db, 10, 3) == 'vue')
            {
                $indAtrib = "atr_".$i."_".substr($indicatorCSAT, 0, 4)."_".substr($indicatorCSAT, 4, 1);
            }
            
            if ($i != $endCsatAtr["end"]) {
                $query .= " (COUNT(if($indAtrib = $this->_maxMaxCes, 1, NULL))* 100)/COUNT(if($indAtrib !=99, 1, NULL)) AS  $indAtrib, 
                           ((count(if($indAtrib between $this->_minCes and $this->_minMediumCes,  $indAtrib, NULL))*100)/count(case when $indAtrib != 99 THEN  $indAtrib END)) as detractor$i, 
                           ((count(if($indAtrib = $this->_maxMaxCes, $indAtrib, NULL))*100)/count(if($indAtrib !=99,1,NULL ))) as promotor$i, 
                           ((count(if($indAtrib = $this->_minMaxCes  or $indAtrib = $this->_minMaxCes,  $indAtrib, NULL))*100)/count(case when  $indAtrib != 99 THEN $indAtrib END)) as neutral$i,";
            }
            if ($i == $endCsatAtr["end"]) {
                $query .= " (COUNT(if($indAtrib = $this->_maxMaxCes, 1, NULL))* 100)/COUNT(if($indAtrib !=99, 1, NULL)) AS  $indAtrib, 
                           ((count(if($indAtrib between $this->_minCes and $this->_minMediumCes,  $indAtrib, NULL))*100)/count(case when $indAtrib != 99 THEN  $indAtrib END)) as detractor$i, 
                           ((count(if($indAtrib = $this->_maxMaxCes, $indAtrib, NULL))*100)/count(if($indAtrib !=99,1,NULL ))) as promotor$i, 
                           ((count(if($indAtrib = $this->_minMaxCes  or $indAtrib = $this->_minMaxCes,  $indAtrib, NULL))*100)/count(case when  $indAtrib != 99 THEN  $indAtrib END)) as neutral$i ";
            }
        }

        $data = DB::select("SELECT $query,date_survey
            FROM $this->_dbSelected.$db as A
            LEFT JOIN $this->_dbSelected." . $db . "_start as b
            on A.token = b.token 
            WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $activeP2  $datafilters
            ORDER BY date_survey" );
        
        if ($data[0]->$indicatorCSAT != null) 
        {
            $graphCSAT[] = [
                'xLegend'  => $endCsatAtr["name"] . " - CSAT",
                'values' =>
                [
                    "promoters"     => round($data[0]->$indicatorCSAT),
                    "neutrals"      => ($data[0]->promotor == 0 && $data[0]->detractor == 0) ? round(round($data[0]->neutral)) : round(100 - (round($data[0]->detractor) + round($data[0]->promotor))),//(int)round(100 - (round($value->$det) + round($value->$pro))),
                    "detractors"    => round($data[0]->detractor),
                    "csat"          => round($data[0]->promotor)
                ]
            ];
            
            for ($i = 1; $i <= $endCsatAtr["end"]; $i++) {
                $total   = 'atr' . $i.'_'.$indicatorCSAT;
                $pro = 'promotor' . $i;
                $neu = 'neutral' . $i;
                $det = 'detractor' . $i;

                $graphCSAT[] = [
                    'xLegend'  => $endCsatAtr["names"][$i] . " - ACSAT",
                    'values' =>
                    [
                        "promoters"     => round($data[0]->$pro),
                        "neutrals"      => ($data[0]->$pro == 0 && $data[0]->$det == 0) ? round(round($data[0]->$neu)) : round(100 - (round($data[0]->$det) + round($data[0]->$pro))),//(int)round(100 - (round($value->$det) + round($value->$pro))),
                        "detractors"    => round($data[0]->$det),
                        "csat"          => round($data[0]->$total)
                    ]
                ];
                
            }
        }

        if ($data[0]->$indicatorCSAT == null) {
            $graphCSAT[] = [
                'xLegend'  => $endCsatAtr["name"] . " - CSAT",
                'values' =>
                [
                    "promoters"     => 0,
                    "neutrals"      => 0,
                    "detractors"    => 0,
                    "csat"          => 0
                ]
            ];
            
            for ($i = 1; $i <= $endCsatAtr["end"]; $i++) {

                $graphCSAT[] = [
                    'xLegend'  => $endCsatAtr["names"][$i] . " - ACSAT",
                    'values' =>
                    [
                        "promoters"     => 0,
                        "neutrals"      => 0,
                        "detractors"    => 0,
                        "csat"          => 0
                    ]
                ];
                
            }
        }

        $resp = $this->CSATDriversAtr($graphCSAT, $endCsatAtr["name"], 4, 6);
        return $resp;
    }

    private function statsJetSmart($db, $npsInDb, $csatInDb, $dateIni, $dateEnd, $fieldFilter, $text, $datafilters = null)
    {
        
        $query = "SELECT COUNT(*) as Total,
                      ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                      (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1) AS NPS,
                      ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL ))) AS CSAT
                      FROM $this->_dbSelected.$db as a
                      LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and $fieldFilter != 0 and nps!= 99 and csat!= 99  $datafilters";

        $data = $data = DB::select($query);

                $resp = [
                    "text"      => $text,
                    "nps"       => !$data[0]->NPS ? 'N/A' : ROUND($data[0]->NPS) . " %",
                    "csat"      => !$data[0]->CSAT ? 'N/A' : ROUND($data[0]->CSAT) . " %",
                    "quantity"  => $data[0]->Total,
                ];

        return $resp;
    }

    private function statsJetSmartResp($db, $npsInDb, $csatInDb, $dateIni, $dateEnd, $datafilters = null)
    {
        $statsEmbAero   = $this->statsJetSmart($db, $npsInDb, $csatInDb, $dateIni, $dateEnd, 'hasbag', 'Entraga equipaje Aeropuerto',$datafilters);
        $statsCheckIn   = $this->statsJetSmart($db, $npsInDb, $csatInDb, $dateIni, $dateEnd, 'hasach', 'Check-in Aeropuerto',$datafilters);
        $statsEmbPriori = $this->statsJetSmart($db, $npsInDb, $csatInDb, $dateIni, $dateEnd, 'haspbd', 'Embarque prioritario',$datafilters);
        $data = [$statsEmbAero, $statsCheckIn, $statsEmbPriori];
        $standarStruct = [
            [
                "text" => "NPS",
                "key" => "nps",
                "cellColor" => "rgb(0,0,0)",
            ],
            [
                "text" => "CSAT",
                "key" => "csat",
                "cellColor" => "rgb(0,0,0)",
            ],
            [
                "text" => "Cantidad de respuesta",
                "key" => "quantity",
                "cellColor" => "rgb(0,0,0)",
            ]
        ];

        return [
            "height" =>  2,
            "width" =>  8,
            "type" =>  "tables",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "STATS by clients",
                "tables" => [
                    [
                        "columns" => [
                            [
                                "text" => "Clientes",
                                "key" => "text",
                                "headerColor" => "#17C784",
                                "cellColor" => "#949494",
                                "textAlign" => "left"
                            ],
                            $standarStruct[0],
                            $standarStruct[1],
                            $standarStruct[2],
                        ],
                        "values" => $data,
                    ]
                ]
            ]
        ];


    }

    private function nameSurvey($name)
    {
        if($name== 'mutcon'){
            return 'Consolidado';
        }
        if($name== 'mutcop'){
            return 'Consolidado';
        }

        $data = DB::select("SELECT nomSurvey FROM $this->_dbSelected.survey WHERE codDbase = '$name'");
        return $data[0]->nomSurvey;
    }

    protected function closedLoop($db, $indicador, $dateEnd, $dateIni, $filter, $datafilters = null)
    {

        $db2     = $this->primaryTable($db);
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter != 'all') {
            $data = DB::select("SELECT  b.visita as visita, b.estado_close as estado, 
                                b.cod_auth AS nps_cierre, a.etapaencuesta AS etapaencuesta, 
                                b.det_close AS contenido,
                                COUNT(*) AS ticketCreated,
                                COUNT(IF(b.visita = 0 AND b.estado_close = 0, $indicador , null )) as ticketOpen, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 1, $indicador , null )) as ticketManage, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 2, $indicador , null )) as ticketPending, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 3, $indicador , null )) as ticketNoContact, 
                                COUNT(IF(b.visita = 1 AND b.estado_close = 4, $indicador , null )) as ticketClosed, 
                                COUNT(IF(b.visita = 1 AND b.estado_close = 4 AND b.cod_auth > 8, $indicador , null )) as convertion,
                                COUNT(IF(nps = 0 OR nps = 1, $indicador , null )) as low, 
                                COUNT(IF(nps = 2 OR nps = 3 OR nps = 4, $indicador , null )) as medium, 
                                COUNT(IF(nps = 5 OR nps = 6, $indicador , null )) as high, $this->_fieldSelectInQuery 
                                FROM $this->_dbSelected.$db as a 
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b on (a.token = b.token) 
                                WHERE nps in(0,1,2,3,4,5,6) AND etapaencuesta = 'P2' AND $this->_obsNps != '' AND date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters
                                ORDER BY date_survey DESC");
        }
        if ($filter == 'all') {
            $data = DB::select("SELECT SUM(ticketCreated) AS ticketCreated, sum(ticketOpen) as ticketOpen,sum(ticketManage) as ticketManage,sum(ticketPending) as ticketPending,sum(ticketNoContact) as ticketNoContact,
                                sum(ticketClosed) as ticketClosed,sum(convertion) as convertion,sum(low) as low,sum(medium) as medium,sum(high) as high,visita,estado,nps_cierre,etapaencuesta,contenido,  $this->_fieldSelectInQuery
                                FROM
                                (SELECT b.visita as visita, b.estado_close as estado, 
                                b.cod_auth AS nps_cierre, a.etapaencuesta AS etapaencuesta, 
                                b.det_close AS contenido,
                                COUNT(*) AS ticketCreated,
                                COUNT(IF(b.visita = 0 AND b.estado_close = 0, $indicador , null )) as ticketOpen, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 1, $indicador , null )) as ticketManage, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 2, $indicador , null )) as ticketPending, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 3, $indicador , null )) as ticketNoContact, 
                                COUNT(IF(b.visita = 1 AND b.estado_close = 4, $indicador , null )) as ticketClosed, 
                                COUNT(IF(b.visita = 1 AND b.estado_close = 4 AND b.cod_auth > 8, $indicador , null )) as convertion,
                                COUNT(IF(nps = 0 OR nps = 1, $indicador , null )) as low, 
                                COUNT(IF(nps = 2 OR nps = 3 OR nps = 4, $indicador , null )) as medium, 
                                COUNT(IF(nps = 5 OR nps = 6, $indicador , null )) as high , $this->_fieldSelectInQuery 
                                FROM $this->_dbSelected.$db as a 
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b on (a.token = b.token) 
                                WHERE nps in(0,1,2,3,4,5,6) AND obs_nps != '' AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' $datafilters
                                UNION
                                SELECT  b.visita as visita, b.estado_close as estado, 
                                b.cod_auth AS nps_cierre, a.etapaencuesta AS etapaencuesta, 
                                b.det_close AS contenido,
                                COUNT(*) AS ticketCreated,
                                COUNT(IF(b.visita = 0 AND b.estado_close = 0, $indicador , null )) as ticketOpen, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 1, $indicador , null )) as ticketManage, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 2, $indicador , null )) as ticketPending, 
                                COUNT(IF(b.visita = 0 AND b.estado_close = 3, $indicador , null )) as ticketNoContact, 
                                COUNT(IF(b.visita = 1 AND b.estado_close = 4, $indicador , null )) as ticketClosed, 
                                COUNT(IF(b.visita = 1 AND b.estado_close = 4 AND b.cod_auth > 8, $indicador , null )) as convertion,
                                COUNT(IF(nps = 0 OR nps = 1, $indicador , null )) as low, 
                                COUNT(IF(nps = 2 OR nps = 3 OR nps = 4, $indicador , null )) as medium, 
                                COUNT(IF(nps = 5 OR nps = 6, $indicador , null )) as high , $this->_fieldSelectInQuery 
                                FROM $this->_dbSelected.$db2 as a 
                                LEFT JOIN $this->_dbSelected." . $db2 . "_start as b on (a.token = b.token) 
                                WHERE nps in(0,1,2,3,4,5,6) AND obs_nps != '' AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' $datafilters
                                ) AS A");
        }
        $closedRate     = 0;
        $convertionRate = 0;
        if ($data[0]->ticketCreated > 0)
            $closedRate = round(($data[0]->ticketClosed / $data[0]->ticketCreated) * 100);
        if ($data[0]->ticketClosed > 0)
            $convertionRate = (($data[0]->convertion / $data[0]->ticketClosed) * 100);
        return [
            "height" => 4,
            "width" => 8,
            "type" => "summary",
            "props" => [
                "icon" => "arrow-right",
                "text" => "Close The Loop",
                //   "callToAction"=> [
                //     "text"=> "Ir a la suite",
                //     "icon"=> "arrow-right",
                //     "url"=> "https://www.suite.customerscoops.app/",
                //   ],
                "sumaries" => [
                    [
                        "icon"        => "tickets-created",
                        "text"        => "Ticket creados",
                        "value"       => $data[0]->ticketCreated,
                        "valueColor"  => "#17C784",
                        "detail" => [
                            'CHP <span style="display: inline-block; width: 24px; height: 24px; border-radius: 50%; background-color: #E0DFDF; text-align: center;">?</span>',
                            '<span style="color: #F07667">●</span> Alta: ' . $data[0]->high,
                            '<span style="color: #FFC700">●</span> Media: ' . $data[0]->medium,
                            '<span style="color: #00CCB1">●</span> Baja: ' . $data[0]->low,
                        ],
                    ],
                    [
                        "icon"        => "tickets-open",
                        "text"        => "Ticket Abiertos",
                        "value"       => $data[0]->ticketOpen,
                        "valueColor"  => "#17C784",
                    ],
                    [
                        "icon"        => "tickets-closed",
                        "text"        => "Ticket Cerrados",
                        "value"       => $data[0]->ticketClosed,
                        "valueColor"  => "#17C784",
                    ],
                    [
                        "icon"        => "closed-rate",
                        "text"        => "Closed Rate",
                        "value"       => $closedRate,
                        "valueColor"  => "#17C784",
                    ],
                    [
                        "icon"        => "conversion-rate",
                        "text"        => "Conversion Rate",
                        "value"       => $convertionRate,
                        "valueColor"  => "#17C784",
                    ],
                ],
            ]
        ];
    }

    private function imagen($client, $filterClient,$nameEncuesta, $table = null){
      
        if($client == 'ban' &&  $filterClient != 'all'){
           return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola </span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageBan'/></span></div>";
        }
        if ($client == 'vid' &&  $filterClient != 'all') {
            return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola </span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageVid'/></span></div>";
        }
        if($client == 'mut' &&  $filterClient != 'all'){
            return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola </span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageClient'/></span></div>";
        }
        if($client == 'jet' &&  $filterClient != 'all'){
            return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola </span>¡Este es tu Dashboard de la Encuesta $nameEncuesta! <img width='120px' src='$this->_imageClient'/></span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'></span></div>";
        }
        if($client == 'tra' &&  $filterClient != 'all'){
            return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola </span>¡Este es tu Dashboard de la Encuesta $nameEncuesta! <img width='120px' src='$this->_imageClient'/></span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'></span></div>";
        }
      
        return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola </span>¡Este es tu Dashboard Consolidado de $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageBanVid'/></span></div>";
    }

    private function getDetailsForIndicator($db, $db2, $month, $year, $npsInDb, $csatInDb, $dateIni, $dateEnd, $fieldFilter, $datafilters = null, $filter)
    {

        $db2 = $this->primaryTable($db);
          if($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter == 'all') {
            $query = "SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, $this->_fieldSelectInQuery
                      FROM (SELECT COUNT(*) as Total,
                      ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                      (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100)*$this->_porcentageBan) AS NPS,
                      ROUND((COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageBan) AS CSAT,  $this->_fieldSelectInQuery
                      FROM $this->_dbSelected.$db as a
                      LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and $fieldFilter != '' and nps!= 99  $datafilters
                      GROUP BY $fieldFilter
                      UNION
                      SELECT COUNT(*) as Total,
                      ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                      (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100)*$this->_porcentageVid) AS NPS,
                      ROUND((COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageVid) AS CSAT,  $this->_fieldSelectInQuery
                      FROM $this->_dbSelected.$db2 as a
                      LEFT JOIN $this->_dbSelected." . $db2 . "_start as b on a.token = b.token 
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and $fieldFilter != '' and nps!= 99  $datafilters
                      GROUP BY $fieldFilter) AS A GROUP BY $fieldFilter";
        }

        if ($filter != 'all') {
            $query = "SELECT COUNT(*) as Total,
                      ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                      (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1) AS NPS,
                      ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL ))) AS CSAT,  
                      $this->_fieldSelectInQuery
                      FROM $this->_dbSelected.$db as a
                      LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and $fieldFilter != '' and nps!= 99  $datafilters
                      GROUP BY $fieldFilter";
        }

        $data = $data = DB::select($query);
        $resp = array();
        $text = "sections";
        $val = 'TRAMO';
        if ($fieldFilter == 'nicho') {
            $text = 'niche';
            $val = 'NICHO';
        }

        if($data){
            foreach ($data as $key => $value) {
                $this->setAnomalias($value->NPS, $val . ' ' . $value->$fieldFilter);
                $resp[] = [
                    $text       => $value->$fieldFilter,
                    "nps"       => ROUND($value->NPS) . " %",
                    "csat"      => ROUND($value->CSAT) . " %",
                    "quantity"  => $value->Total,
                    //"textColor" => '#000'
                ];
            }
        }

        return $resp;
    }
  
    protected function ces($db, $dateIni, $dateEnd, $ces, $datafilters=null){
        $data = null;   
        $str = substr($db,10,3);
        $cesPrev = 0;
        // $activeP2 ='';
        // if(substr($db, 6, 3) == 'jet')
        //     $activeP2 = " AND etapaencuesta = 'P2' ";

        if ($datafilters)
            $datafilters = " AND $datafilters";

        
        if($str == 'ges' || $str == 'eri' || $str == 'com'){
          
            $data = DB::select("SELECT COUNT(if(ces !=99,1,NULL )) as Total,
                                (COUNT(if($ces between  $this->_minMaxCes and  $this->_maxMaxCes  , $ces, NULL)) - COUNT(if($ces between $this->_minCes and $this->_maxCes , $ces, NULL)))/COUNT(if(ces !=99,1,NULL ))* 100 AS CES 
                                FROM $this->_dbSelected.$db as a
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b 
                                on a.token = b.token
                                WHERE date_survey BETWEEN  '$dateEnd'AND  '$dateIni'and etapaencuesta = 'P2' $datafilters");
         
                                $cesPrev = $this->cesPreviousPeriod($db, $dateIni, $dateEnd);
        } 

        if($data == null || $data[0]->Total == null){
            return [
                "name"          => "CES",
                "value"         => "N/A",
                "percentage"    => 0-ROUND($cesPrev)
            ]; 
        }
        if($data[0]->Total != null){
            return [
            "name"              => "CES",
            "value"             => ROUND($data[0]->CES),
            "percentage"        => ROUND($data[0]->CES)-ROUND($cesPrev),
            ];
        } 
    }
   
    private function cesPreviousPeriod($db, $dateEnd, $dateIni, $datafilters =null){

    $monthAntEnd = date('m') - 1;
    $annio = date('Y');

        if ($datafilters)
            $datafilters = " AND $datafilters";

            $activeP2 ='';
            if(substr($db, 10, 3) == 'con' || substr($db, 10, 3) == 'via')
                $activeP2 = " AND etapaencuesta = 'P2' ";
    
        $data = [];

        $monthActualEnd= substr($dateIni, 5,2); 
    
        if($monthActualEnd > 1 && $monthActualEnd < 11){
            $monthAntEnd = '0'.($monthActualEnd - 1);
        }
        if($monthActualEnd == 1){
            $monthAntEnd = 12;
            $annio = date('Y') -1;
        }
        if($monthActualEnd > 10){
            $monthAntEnd = $monthActualEnd - 1;
        }

        $mes = $monthAntEnd;
     

        $data = DB::select("SELECT COUNT(*) as Total,
                            (COUNT(if(ces between  $this->_minMaxCes and  $this->_maxMaxCes , ces, NULL)) - COUNT(if(ces between $this->_minCes and $this->_maxCes, ces, NULL)))/COUNT(if(ces !=99,1,NULL ))* 100 AS CES 
                            FROM $this->_dbSelected.$db as a 
                            LEFT JOIN $this->_dbSelected." . $db . "_start as b 
                            on a.token = b.token
                            WHERE a.mes = $mes AND a.annio = $annio $activeP2 $datafilters");

        return $data[0]->CES;
    }

    private function getDetailsAntiquity($db, $db2,$month,$year,$npsInDb,$csatInDb, $dateIni, $dateEnd,$fieldFilter, $datafilters = null, $filter)
    {
        $db2     = $this->primaryTable($db);
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter == 'all') {
            $query = "SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, $fieldFilter, $this->_fieldSelectInQuery
                      FROM (SELECT COUNT(*) as Total,
                      ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                      (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS,
                      ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageBan AS CSAT,  $fieldFilter, $this->_fieldSelectInQuery
                      FROM $this->_dbSelected.$db as a
                      LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps != 99 $datafilters
                      GROUP BY ($fieldFilter > 0 AND $fieldFilter <1), ($fieldFilter >= 1 AND $fieldFilter < 2),($fieldFilter >= 2 AND $fieldFilter < 5),($fieldFilter >= 5 AND $fieldFilter< 100)
                      UNION
                      SELECT COUNT(*) as Total,
                      ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                      (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS,
                      ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageVid AS CSAT,  $fieldFilter, $this->_fieldSelectInQuery
                      FROM $this->_dbSelected.$db2 as a
                      LEFT JOIN $this->_dbSelected." . $db2 . "_start as b on a.token = b.token 
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps != 99  $datafilters
                      GROUP BY ($fieldFilter > 0 AND $fieldFilter <1), ($fieldFilter >= 1 AND $fieldFilter < 2),($fieldFilter >= 2 AND $fieldFilter < 5),($fieldFilter >= 5 AND $fieldFilter< 100)) AS A
                      GROUP BY ($fieldFilter > 0 AND $fieldFilter <1), ($fieldFilter >= 1 AND $fieldFilter < 2),($fieldFilter >= 2 AND $fieldFilter < 5),($fieldFilter >= 5 AND $fieldFilter< 100)";
        }

        if ($filter != 'all') {
            $query = "SELECT COUNT(*) as Total,
                      ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                      COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                      (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1) AS NPS,
                      ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL ))) AS CSAT,  $fieldFilter, $this->_fieldSelectInQuery
                      FROM $this->_dbSelected.$db as a
                      LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token
                      WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps != 99  $datafilters
                      GROUP BY ($fieldFilter > 0 AND $fieldFilter <1), ($fieldFilter >= 1 AND $fieldFilter < 2),($fieldFilter >= 2 AND $fieldFilter < 5),($fieldFilter >= 5 AND $fieldFilter< 100)";
        }

        $data = $data = DB::select($query);
        $resp = array();
        $text = "sections";
        $lessOne = 0;
        $lessOneNps = 0;
        $lessOneCsat = 0;
        $lessTwo = 0;
        $lessTwoNps = 0;
        $lessTwoCsat = 0;
        $lessThree = 0;
        $lessThreeNps = 0;
        $lessThreeCsat = 0;
        $higherThree = 0;
        $higherThreeNps = 0;
        $higherThreeCsat = 0;
        $count1 = 0;
        $count2 = 0;
        $count3 = 0;
        $count4 = 0;
        if ($data) {
            foreach ($data as $key => $value) {
                if ($value->$fieldFilter < 1) {
                    $count1 += 1;
                    $lessOne        = $value->Total + $lessOne;
                    $lessOneNps     = $value->NPS + $lessOneNps;
                    $lessOneCsat    = $value->CSAT + $lessOneCsat;
                }
                if ($value->$fieldFilter >= 1 && $value->$fieldFilter < 2) {
                    $count2 += 1;
                    $lessTwo        = $value->Total + $lessTwo;
                    $lessTwoNps     = $value->NPS + $lessTwoNps;
                    $lessTwoCsat    = $value->CSAT + $lessTwoCsat;
                }
                if ($value->$fieldFilter >= 2 && $value->$fieldFilter < 5) {
                    $count3 += 1;
                    $lessThree        = $value->Total + $lessThree;
                    $lessThreeNps     = $value->NPS + $lessThreeNps;
                    $lessThreeCsat    = $value->CSAT + $lessThreeCsat;
                }
                if ($value->$fieldFilter >= 5) {
                    $count4 += 1;
                    $higherThree        = $value->Total + $higherThree;
                    $higherThreeNps     = $value->NPS + $higherThreeNps;
                    $higherThreeCsat    = $value->CSAT + $higherThreeCsat;
                }
            }
            $this->setAnomalias($lessOneNps, 'ANTIGÜEDAD Menor a 1 año');
            $this->setAnomalias($lessTwoNps, 'ANTIGÜEDAD 1 a 2 años');
            $this->setAnomalias($lessThreeNps, 'ANTIGÜEDAD 2 a 5 años');
            $this->setAnomalias($higherThreeNps, 'ANTIGÜEDAD 5 años o mas');
        }
        $resp = [
            [
                "antiquity" => "Menor a 1 año",
                "nps" => ($count1 == 0) ? 0 : ROUND($lessOneNps / $count1) . " %",
                "csat" => ($count1 == 0) ? 0 : ROUND($lessOneCsat / $count1) . " %",
                "quantity" => $lessOne,

            ],
            [
                "antiquity" => "1 a 2 años",
                "nps" => ($count2 == 0) ? 0 : ROUND($lessTwoNps / $count2) . " %",
                "csat" => ($count2 == 0) ? 0 : ROUND($lessTwoCsat / $count2) . " %",
                "quantity" => $lessTwo
            ],
            [
                "antiquity" => "2 a 5 años",
                "nps" => ($count3 == 0) ? 0 : ROUND($lessThreeNps / $count3) . " %",
                "csat" => ($count3 == 0) ? 0 : ROUND($lessThreeCsat / $count3) . " %",
                "quantity" => $lessThree
            ],
            [
                "antiquity" => "5 años o mas",
                "nps" => ($count4 == 0) ? 0 : ROUND($higherThreeNps / $count4) . " %",
                "csat" => ($count4 == 0) ? 0 : ROUND($higherThreeCsat / $count4) . " %",
                "quantity" => $higherThree
            ]
        ];

        return $resp;
    }

    private function npsByIndicator($db, $endDate, $startDate, $filterClient, $indicatorBD, $whereInd, $indicatorName, $group, $name, $height)
    {
        $db2 = $this->primaryTable($db);
        $where = '';
        if ($indicatorBD == 'canal') {
            $where = " and canal = 'GES Remoto'";
        }
        
        if ($filterClient == 'all') {
            $query = "SELECT $indicatorName, mes, annio, date_survey,SUM(nps) AS nps FROM (
                      SELECT $indicatorBD as $indicatorName, b.mes,b.annio,date_survey, 
                      round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between 0 and 6 then 1 end)) / 
                      count(case when nps != 99 then 1 end) *100)*$this->_porcentageBan as nps 
                      from $this->_dbSelected." . $db . "_start as a 
                      left join $this->_dbSelected.$db as b 
                      on a.token = b.token 
                      where $whereInd!= '' and date_survey between '2021-01-01' and '$startDate' and etapaencuesta = 'P2' $where
                      group by $group, a.mes, a.annio
                      UNION 
                      SELECT $indicatorBD as $indicatorName, b.mes,b.annio,date_survey, 
                      round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between 0 and 6 then 1 end)) / 
                      count(case when nps != 99 then 1 end) *100)*$this->_porcentageVid as nps 
                      from $this->_dbSelected." . $db2 . "_start as a
                      left join $this->_dbSelected.$db2 as b 
                      on a.token = b.token 
                      where $whereInd!= '' and date_survey between '2021-01-01' and '$startDate' and etapaencuesta = 'P2' $where
                      group by $group, a.mes, a.annio 
                      )As a group by $group, a.mes, a.annio  ORDER BY $group, a.annio, a.mes asc";
    
        }

        if ($filterClient != 'all') {
            $query = "SELECT $indicatorBD as $indicatorName, b.mes,b.annio,date_survey,
                      round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as nps
                      from $this->_dbSelected." . $db . "_start as a
                      left join $this->_dbSelected.$db as b
                      on a.token = b.token
                      where $whereInd!= '' and date_survey between  '2021-01-01' and '$startDate' and etapaencuesta = 'P2'  $where
                      group by $group, a.mes, a.annio
                      ORDER BY $group, a.annio, a.mes asc";
                                        
        }

        $data = DB::select($query);
        $lastSupervisor = '';
        $values = [];
        $count = 0;
        $sum = 0;
        $countLY = 0;
        $sumLY = 0;
        $meses = [];

        for ($i = -11; $i < 1; $i++) {
            array_push(
                $meses,
                (int)date("m", mktime(0, 0, 0, date("m") + $i, date("d"), date("Y")))
            );
        }

        foreach ($data as $key => $value) {
            if ($value->$indicatorName != $lastSupervisor) {
                
                $lastSupervisor = $value->$indicatorName;

                if ($countLY > 0) {
                    $values[sizeof($values) - 1][0]['YTD 2021'] =  round($sumLY / $countLY);
                }

                if ($count > 0) {
                    $values[sizeof($values) - 1][0]['YTD'] =  round($sum / $count);
                }

                $count = 0;
                $sum = 0;
                $countLY = 0;
                $sumLY = 0;

                if ($value->$indicatorName == '1') {
                    $value->$indicatorName = 'Externos';
                }
                if ($value->$indicatorName == '0') {
                    $value->$indicatorName = 'Internos';
                }
               
                $rowData = [
                    $indicatorName => $value->$indicatorName,
                    'YTD' => '-',
                    'YTD 2021' => '-',
                ];

                for ($i = 0; $i < 12; $i++) {
                    $rowData['period' . $meses[$i]] = '-';
                }
                array_push(
                    $values,
                    [$rowData]
                );
            }

            if ($value->annio == '2021' && $value->mes >= 1) {
                $countLY += 1;
                $sumLY += ROUND($value->nps);
            }

            if ($value->annio == '2022') {
                $count += 1;
                $sum += ROUND($value->nps);
            }

            if ($value->annio == '2021' && $value->mes > date("m"))
                $values[sizeof($values) - 1][0]['period' . $value->mes] = round($value->nps);
            if ($value->annio == '2022' && $value->mes >= 1)
                $values[sizeof($values) - 1][0]['period' . $value->mes] = round($value->nps);
        }

        if ($count > 0) {
            $values[sizeof($values) - 1][0]['YTD'] =  round($sum / $count);
        }

        if ($countLY > 0) {
            $values[sizeof($values) - 1][0]['YTD 2021'] = round($sumLY / $countLY);
        }
        $count = 0;
        $sum = 0;
        $countLY = 0;
        $sumLY = 0;
        $numberToMonth = [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ];

        $colums =  [
            $indicatorName => $name,
        ];

        for ($i = 0; $i < 12; $i++) {
            $colums['period' . $meses[$i]] = $numberToMonth[$meses[$i]];
        }


        $colums['YTD'] = 'YTD';
        $colums['YTD 2021'] = 'YTD 2021';

        return [
            "height" => $height,
            "width" =>  12,
            "type" =>  "table-period",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "NPS By " . $name,
                "data" => [
                    "columns" => [$colums],
                    "values" => $values,
                    "colors" => [
                        $indicatorName => "#17C784",
                        "YTD" => "#17C784"
                    ]
                ],

            ]
        ];
    }
   
     private function npsByRegiones($db,$endDate, $startDate,$filterClient, $indicatorBD, $indicatorName, $name){
        $db2 = $this->primaryTable($db);

        if($filterClient != 'all'){
            $query = "SELECT $indicatorBD as $indicatorName, sum(nps) as nps, b.mes,b.annio,date_survey,
                      round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as nps
                      from $this->_dbSelected.".$db."_start as a
                      left join $this->_dbSelected.$db as b
                      on a.token = b.token
                      where $indicatorBD!= '' and date_survey between  '2021-01-01' and '$startDate' and etapaencuesta = 'P2' and  $indicatorBD != 'Contact Center' and  $indicatorBD != 'Web'
                      group by $indicatorBD, a.mes, a.annio
                      ORDER BY $indicatorBD, a.annio, a.mes";
        }

        if($filterClient = 'all'){
            $query = "SELECT $indicatorName, SUM(nps) as nps, mes, annio from (SELECT $indicatorBD as $indicatorName, b.mes,b.annio,date_survey,
                      round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100)*$this->_porcentageBan as nps
                      from $this->_dbSelected.".$db."_start as a
                      left join $this->_dbSelected.$db as b
                      on a.token = b.token
                      where $indicatorBD!= '' and date_survey between  '2021-01-01' and '$startDate' and etapaencuesta = 'P2' and  $indicatorBD != 'Contact Center' and  $indicatorBD != 'Web'
                      group by $indicatorName, a.mes, a.annio
                      UNION 
                      SELECT $indicatorBD as $indicatorName, b.mes,b.annio,date_survey,
                      round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100)*$this->_porcentageVid as nps
                      from $this->_dbSelected.".$db2."_start as a
                      left join $this->_dbSelected.$db2 as b
                      on a.token = b.token
                      where $indicatorBD!= '' and date_survey between  '2021-01-01' and '$startDate' and etapaencuesta = 'P2' and  $indicatorBD != 'Contact Center' and  $indicatorBD != 'Web'
                      group by $indicatorName, a.mes, a.annio
                      ) AS A 
                      group by $indicatorName, mes, annio
                      ORDER BY $indicatorName, annio, mes";
            }


        $data = DB::select($query);                    
        $lastSupervisor = '';
        $values = [];
        $count = 0;
        $sum = 0;
        $countLY = 0;
        $sumLY = 0;
        $meses = [];

        for ($i = -11; $i < 1; $i++) {
            array_push(
                $meses,
                (int)date("m", mktime(0, 0, 0, date("m") + $i, date("d"), date("Y")))
            );
        }

        foreach ($data as $key => $value) {
            if ($value->$indicatorName != $lastSupervisor) {
                $lastSupervisor = $value->$indicatorName;
                if ($countLY > 0) {
                    $values[sizeof($values) - 1][0]['YTD 2021'] =  round($sumLY / $countLY);
                }

                if ($count > 0) {
                    $values[sizeof($values) - 1][0]['YTD'] =  round($sum / $count);
                }
                $count = 0;
                $sum = 0;
                $countLY = 0;
                $sumLY = 0;

                $rowData = [
                    $indicatorName => $value->$indicatorName,
                    'YTD' => '-',
                    'YTD 2021' => '-',
                ];
                for ($i = 0; $i < 12; $i++) {

                    $rowData['period' . $meses[$i]] = '-';
                }
                array_push(
                    $values,
                    [$rowData]
                );
            }

            if ($value->annio == '2021' && $value->mes >= 1) {
                $countLY += 1;
                $sumLY += ROUND($value->nps);
            }

            if ($value->annio == '2022') {
                $count += 1;
                $sum += $value->nps;
            }

            if ($value->annio == '2021' && $value->mes > date("m"))
                $values[sizeof($values) - 1][0]['period' . $value->mes] = round($value->nps);
            if ($value->annio == '2022' && $value->mes >= 1)
                $values[sizeof($values) - 1][0]['period' . $value->mes] = round($value->nps);
        }

        if ($count > 0) {
            $values[sizeof($values) - 1][0]['YTD'] =  round($sum / $count);
        }
        if ($countLY > 0) {
            $values[sizeof($values) - 1][0]['YTD 2021'] = round($sumLY / $countLY);
        }

        $count = 0;
        $sum = 0;
        $countLY = 0;
        $sumLY = 0;
        $numberToMonth = [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ];

        $colums =  [
            $indicatorName => $name,
        ];

        for ($i = 0; $i < 12; $i++) {
            $colums['period' . $meses[$i]] = $numberToMonth[$meses[$i]];
        }

        $colums['YTD'] = 'YTD';
        $colums['YTD 2021'] = 'YTD 2021';

        return [
            "height" => 2,
            "width" =>  12,
            "type" =>  "table-period",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "NPS By " . $name,
                "data" => [
                    "columns" => [$colums],
                    "values"  => $values,
                    "colors" => [
                        $indicatorName => "#17C784",
                        "YTD" => "#17C784"
                    ]
                ],

            ]
        ];
    }
  
     private function npsNew($db,$endDate, $startDate, $height, $filterClient){
        $db2 = $this->primaryTable($db);
        if($filterClient != 'all'){
        $dbQuery = "SELECT zonaSuc as Zona, region as Region, b.mes,b.annio,date_survey,
                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as nps
                    from $this->_dbSelected.".$db."_start as a
                    left join $this->_dbSelected.$db as b
                    on a.token = b.token
                    where region != '' and zonaSuc != '' and zonaSuc != 'Web' and zonaSuc != 'Contact Center' and date_survey between  '2021-01-01' and '$startDate' and etapaencuesta = 'P2'
                    group by zonaSuc, a.mes, a.annio
                    ORDER BY zonaSuc, region, a.annio, a.mes";
        }
        if($filterClient == 'all'){
        $dbQuery = "SELECT sum(nps) as nps, Zona, Region, mes, annio from (SELECT zonaSuc as Zona, region as Region, b.mes,b.annio,date_survey,
                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100)*$this->_porcentageBan as nps
                    from $this->_dbSelected.".$db."_start as a
                    LEFT JOIN $this->_dbSelected.$db as b
                    on a.token = b.token
                    where region != '' and zonaSuc != '' and zonaSuc != 'Web' and zonaSuc != 'Contact Center' and date_survey between  '2021-01-01' and '$startDate' and etapaencuesta = 'P2'
                    group by zonaSuc, a.mes, a.annio
                    UNION
                    SELECT zonaSuc as Zona, region as Region, b.mes,b.annio,date_survey,
                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100)*$this->_porcentageVid as nps
                    from $this->_dbSelected.".$db2."_start as a
                    left join $this->_dbSelected.$db2 as b
                    on a.token = b.token
                    where region != '' and zonaSuc != '' and zonaSuc != 'Web' and zonaSuc != 'Contact Center' and date_survey between  '2021-01-01' and '$startDate' and etapaencuesta = 'P2'
                    group by zonaSuc, a.mes, a.annio
                    ) as a
                    group by Zona, a.mes, a.annio
                    ORDER BY Zona, region, a.annio, a.mes";                   
        }

       $data = DB::select($dbQuery);
        $lastRegion = '';
        $lastZona  = '';
        $values = [];
        $count = 0;
        $sum = 0;
        $countLY = 0;
        $sumLY = 0;
        $meses = [];

        for ($i = -11; $i < 1; $i++) {
            array_push(
                $meses,
                (int)date("m", mktime(0, 0, 0, date("m") + $i, date("d"), date("Y")))
            );
        }

        foreach ($data as $key => $value) {
            if ($value->Zona != $lastZona) {
                if ($count > 0) {
                    $values[$lastZona][sizeof($values[$lastZona]) - 1][0]['YTD'] =  round($sum / $count) . '%';
                }
                if ($countLY > 0) {
                    $values[$lastZona][sizeof($values[$lastZona]) - 1][0]['YTD 2021'] =  round($sumLY / $countLY) . '%';
                }

                $count = 0;
                $sum = 0;
                $countLY = 0;
                $sumLY = 0;

                $lastZona = $value->Zona;

                $lastRegion = "";

                $values[$lastZona] = [];
            };
  
            if($value->Region != $lastRegion){
                if($count > 0){
                    $values[$lastZona][sizeof($values[$lastZona])-1][0]['YTD'] =  round($sum / $count).'%';
                }

                if ($countLY > 0) {
                    $values[$lastZona][sizeof($values[$lastZona]) - 1][0]['YTD 2021'] =  round($sumLY / $countLY) . '%';
                }

                $count = 0;
                $sum = 0;
                $countLY = 0;
                $sumLY = 0;

                $lastRegion = $value->Region;
                $rowData = [
                    'Region' => $value->Region,
                ];

                for ($i = 0; $i < 12; $i++) {
                    $rowData['period' . $meses[$i]] = '-';
                }

                $rowData['YTD'] = '-';
                $rowData['YTD 2021'] = '-';

                array_push(
                    $values[$value->Zona],
                    [$rowData]
                );
            }

            if ($value->annio == '2022') {
                $count += 1;
                $sum += $value->nps;
            }
            if ($value->annio == '2021') {
                $countLY += 1;
                $sumLY += $value->nps;
            }

            if ($value->annio == '2021' && $value->mes > date("m"))
                $values[$lastZona][sizeof($values[$lastZona]) - 1][0]['period' . $value->mes] = round($value->nps) . '%';

            if ($value->annio == '2022' && $value->mes >= 1)
                $values[$lastZona][sizeof($values[$lastZona]) - 1][0]['period' . $value->mes] = round($value->nps) . '%';
        }

        if ($count > 0) {
            $values[$lastZona][sizeof($values[$lastZona]) - 1][0]['YTD'] =  round($sum / $count) . '%';
        }
        if ($countLY > 0) {
            $values[$lastZona][sizeof($values[$lastZona]) - 1][0]['YTD 2021'] =  round($sumLY / $countLY) . '%';
        }

        $count = 0;
        $sum = 0;
        $countLY = 0;
        $sumLY = 0;

        $numberToMonth = [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ];

        $colums = [
            'Zona' => 'Zona',
            'Region' => 'Región',
        ];
        
        for($i = 0; $i < 12; $i++){
            $colums['period'.$meses[$i]]=$numberToMonth[$meses[$i]];
        }
        
        $colums['YTD']='YTD';
        $colums['YTD 2021']='YTD 2021';
        
        return [
            "height" => $height,
            "width" =>  12,
            "type" =>  "compose-table",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "NPS By Zonas",
                "data" => [
                    "columns" => [$colums],
                    "values" => $values,
                    "colors" => [
                        'Zona' => "#17C784",
                        'Region' => "#17C784",
                        "YTD" => "#17C784"
                    ]
                ],
            ]
        ];
    }
    
    private function npsCsatbyIndicator($db,$endDate, $startDate, $indicatorBD, $indicatorName, $csat1, $csat2, $height, $filterClient){
        $db2 = $this->primaryTable($db);
        if($filterClient != null){
            $queryBan = "SELECT  UPPER($indicatorBD) as $indicatorName,
                         round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as nps,
                         ROUND(COUNT(if($csat1 between  9 and  10 , $csat1, NULL))* 100/COUNT(if($csat1 !=99,1,NULL ))) AS $csat1,
                         ROUND(COUNT(if($csat2 between  9 and  10 , $csat2, NULL))* 100/COUNT(if($csat2 !=99,1,NULL ))) AS $csat2,  
                         b.mes, b.annio
                         FROM $this->_dbSelected.".$db."_start as a 
                         left join $this->_dbSelected.$db as b
                         on a.token = b.token
                         where  date_survey between '2021-01-01' and '$startDate' and etapaencuesta = 'P2' and $indicatorBD != ''
                         group by $indicatorName, b.mes, b.annio
                         order by $indicatorName,b.annio,b.mes";
        }   

        if($filterClient == null){
            $queryBan = "SELECT $indicatorName, sum(nps) as nps, sum($csat1) as $csat1,sum($csat2) as $csat2, mes, annio from 
                         (SELECT  UPPER($indicatorBD) as $indicatorName,
                         round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100)*$this->_porcentageBan as nps,
                         ROUND(COUNT(if($csat1 between  9 and  10 , $csat1, NULL))* 100/COUNT(if($csat1 !=99,1,NULL )))*$this->_porcentageBan AS $csat1,
                         ROUND(COUNT(if($csat2 between  9 and  10 , $csat2, NULL))* 100/COUNT(if($csat2 !=99,1,NULL )))*$this->_porcentageBan AS $csat2,  
                         b.mes, b.annio
                         FROM $this->_dbSelected." . $db . "_start as a 
                         left join $this->_dbSelected.$db as b
                         on a.token = b.token
                         where  date_survey between '2021-01-01' and '$startDate' and etapaencuesta = 'P2' and $indicatorBD != ''
                         group by $indicatorName, b.mes, b.annio
                         UNION
                         SELECT  UPPER($indicatorBD) as $indicatorName,
                         round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100)*$this->_porcentageVid as nps,
                         ROUND(COUNT(if($csat1 between  9 and  10 , $csat1, NULL))* 100/COUNT(if($csat1 !=99,1,NULL )))*$this->_porcentageVid AS $csat1,
                         ROUND(COUNT(if($csat2 between  9 and  10 , $csat2, NULL))* 100/COUNT(if($csat2 !=99,1,NULL )))*$this->_porcentageVid AS $csat2,  
                         b.mes, b.annio
                         FROM $this->_dbSelected.".$db2."_start as a 
                         left join $this->_dbSelected.$db2 as b
                         on a.token = b.token
                         where  date_survey between '2021-01-01' and '$startDate' and etapaencuesta = 'P2' and $indicatorBD != ''
                         group by $indicatorName, b.mes, b.annio
                         ) as a
                         group by $indicatorName, b.mes, b.annio
                         order by $indicatorName,b.annio,b.mes";
        } 

        $data = DB::select($queryBan);
        $lastSucursal  = '';
        $values = [];
        $countNps = 0;
        $sumNps = 0;
        $countCsat3 = 0;
        $sumCsat3 = 0;
        $countCsat4 = 0;
        $sumCsat4 = 0;
        $countCsat4LY = 0;
        $sumCsat4LY = 0;
        $countCsat3LY = 0;
        $sumCsat3LY = 0;
        $countNpsLY = 0;
        $sumNpsLY = 0;
        $meses = [];

        for ($i = -11; $i < 1; $i++) {
            array_push(
                $meses,
                (int)date("m", mktime(0, 0, 0, date("m") + $i, date("d"), date("Y")))
            );
        }

        foreach ($data as $key => $value) {
            if ($value->$indicatorName != $lastSucursal) {
                if ($countNps > 0) {
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 3][0]['YTD'] =  round($sumNps / $countNps) . '%';
                }
                if ($countNpsLY > 0) {
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 3][0]['YTD 2021'] =  round($sumNpsLY / $countNpsLY) . '%';
                }
                $countNps = 0;
                $sumNps = 0;
                $countNpsLY = 0;
                $sumNpsLY = 0;
                if ($countCsat3 > 0) {
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 2][0]['YTD'] =  round($sumCsat3 / $countCsat3) . '%';
                }
                if ($countCsat3LY > 0) {
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 2][0]['YTD 2021'] =  round($sumCsat3LY / $countCsat3LY) . '%';
                }
                $countCsat3 = 0;
                $sumCsat3 = 0;
                $countCsat3LY = 0;
                $sumCsat3LY = 0;

                if ($countCsat4 > 0) {
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 1][0]['YTD'] =  round($sumCsat4 / $countCsat4) . '%';
                }
                if ($countCsat4LY > 0) {
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 1][0]['YTD 2021'] =  round($sumCsat4LY / $countCsat4LY) . '%';
                }
                $countCsat4 = 0;
                $sumCsat4 = 0;
                $countCsat4LY = 0;
                $sumCsat4LY = 0;

                $lastSucursal = $value->$indicatorName;

                $values[$lastSucursal] = [];

                $rowData = [];

                for ($i = 0; $i < 12; $i++) {
                    $rowData['period' . $meses[$i]] = '-';
                }

                $rowData['YTD'] = '-';
                $rowData['YTD 2021'] = '-';

                array_push(
                    $values[$value->$indicatorName],
                    [
                        array_merge(
                            ['Indicator' => 'NPS'],
                            $rowData
                        )
                    ]
                );
                array_push(
                    $values[$value->$indicatorName],
                    [
                        array_merge(
                            ['Indicator' => 'Calidez y empatía'],
                            $rowData
                        )
                    ]
                );
                array_push(
                    $values[$value->$indicatorName],
                    [
                        array_merge(
                            ['Indicator' => 'Conocimientos y orientación'],
                            $rowData
                        )
                    ]
                );
            };

            if ($value->nps) {
                if ($value->annio == '2022') {
                    $countNps += 1;
                    $sumNps += $value->nps;
                }
                if ($value->annio == '2021') {
                    $countNpsLY += 1;
                    $sumNpsLY += $value->nps;
                }
                if ($value->annio == '2021' && $value->mes > date("m"))
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 3][0]['period' . $value->mes] = round($value->nps) . '%';
                if ($value->annio == '2022' && $value->mes >= 1)
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 3][0]['period' . $value->mes] = round($value->nps) . '%';
            }
            if ($value->$csat1) {
                if ($value->annio == '2022') {
                    $countCsat3 += 1;
                    $sumCsat3 += $value->$csat1;
                }
                if ($value->annio == '2021') {
                    $countCsat3LY += 1;
                    $sumCsat3LY += $value->$csat1;
                }
                if ($value->annio == '2021' && $value->mes > date("m"))
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 2][0]['period' . $value->mes] = round($value->$csat1) . '%';
                if ($value->annio == '2022' && $value->mes >= 1)
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 2][0]['period' . $value->mes] = round($value->$csat1) . '%';
            }
            if ($value->$csat2) {
                if ($value->annio == '2022') {
                    $countCsat4 += 1;
                    $sumCsat4 += $value->$csat2;
                }
                if ($value->annio == '2021') {
                    $countCsat4LY += 1;
                    $sumCsat4LY += $value->$csat2;
                }
                if ($value->annio == '2021' && $value->mes > date("m"))
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 1][0]['period' . $value->mes] = round($value->$csat2) . '%';
                if ($value->annio == '2022' && $value->mes >= 1)
                    $values[$lastSucursal][sizeof($values[$lastSucursal]) - 1][0]['period' . $value->mes] = round($value->$csat2) . '%';
            }
        }
        
        if($countNps > 0){
            $values[$lastSucursal][sizeof($values[$lastSucursal])-3][0]['YTD'] =  round($sumNps / $countNps).'%';
        }
        if ($countNpsLY > 0) {
            $values[$lastSucursal][sizeof($values[$lastSucursal]) - 3][0]['YTD 2021'] =  round($sumNpsLY / $countNpsLY) . '%';
        }
        $countNps = 0;
        $sumNps = 0;
        $countNpsLY = 0;
        $sumNpsLY = 0;
        if ($countCsat3 > 0) {
            $values[$lastSucursal][sizeof($values[$lastSucursal]) - 2][0]['YTD'] =  round($sumCsat3 / $countCsat3) . '%';
        }
        if ($countCsat3LY > 0) {
            $values[$lastSucursal][sizeof($values[$lastSucursal]) - 2][0]['YTD 2021'] =  round($sumCsat3LY / $countCsat3LY) . '%';
        }
        $countCsat3 = 0;
        $sumCsat3 = 0;
        $countCsat3LY = 0;
        $sumCsat3LY = 0;

        if ($countCsat4 > 0) {
            $values[$lastSucursal][sizeof($values[$lastSucursal]) - 1][0]['YTD'] =  round($sumCsat4 / $countCsat4) . '%';
        }
        if ($countCsat4LY > 0) {
            $values[$lastSucursal][sizeof($values[$lastSucursal]) - 1][0]['YTD 2021'] =  round($sumCsat4LY / $countCsat4LY) . '%';
        }
        $countCsat4 = 0;
        $sumCsat4 = 0;
        $countCsat4LY = 0;
        $sumCsat4LY = 0;
        $numberToMonth = [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ];

        $colums = [
            $indicatorName => $indicatorName,
            'Indicator' => 'Indicadores'
        ];

        for ($i = 0; $i < 12; $i++) {
            $colums['period' . $meses[$i]] = $numberToMonth[$meses[$i]];
        }

        $colums['YTD'] = 'YTD';
        $colums['YTD 2021'] = 'YTD 2021';

        return [
            "height"=> $height,
            "width"=>  12,
            "type"=>  "compose-table",
            "props"=>  [
                "icon"=> "arrow-right",
                "text"=> "NPS y CSAT By ".$indicatorName,
                "data"=>[
                    "columns" => [$colums],
                    "values" => $values,
                    "colors" => [
                        $indicatorName => "#17C784",
                        'Indicator' => "#17C784",
                        "YTD" => "#17C784"
                    ]
                ],
            ]
        ];
    }

    protected function ranking($db, $indicatordb, $indicator, $endDateFilterMonth, $startDateFilterMonth, $filterClient, $datafilters, $width, $height){
        if ($datafilters)
            $datafilters = " AND $datafilters";            
        
        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        $arrayTop =[];
        
        if($filterClient != 'all'){
            if (substr($db, 6, 3) != 'mut'){

                if(substr($db, 6, 7) != 'tra_via'){

                    $querydataTop = "SELECT UPPER($indicatordb) as  $indicator,
                                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                                    b.annio
                                    FROM $this->_dbSelected." . $db . "_start as a
                                    left join $this->_dbSelected.$db as b
                                    on a.token = b.token
                                    where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                                    group by  $indicator
                                    order by CNPS DESC
                                    LIMIT 5 ";

                    $querydataBottom = "SELECT * from (SELECT UPPER($indicatordb) as  $indicator, count(UPPER($indicatordb)) as total,
                                        round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                                        b.annio
                                        FROM $this->_dbSelected." . $db . "_start as a
                                        left join $this->_dbSelected.$db as b
                                        on a.token = b.token
                                        where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                                        group by  $indicator
                                        order by CNPS asc
                                        LIMIT 5) as a
                                        order by CNPS ";
                }

                if(substr($db, 6, 7) == 'tra_via'){
                    
                    $querydataTop = "SELECT UPPER($indicatordb) as  $indicator,
                                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                                    YEAR(fechaservicio) as annio
                                    FROM $this->_dbSelected." . $db . "_start as a
                                    left join $this->_dbSelected.$db as b
                                    on a.token = b.token
                                    where fechaservicio between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                                    group by  $indicator
                                    order by CNPS DESC
                                    LIMIT 5 ";

                    $querydataBottom = "SELECT * from (SELECT UPPER($indicatordb) as  $indicator, count(UPPER($indicatordb)) as total,
                                        round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                                        YEAR(fechaservicio) as annio
                                        FROM $this->_dbSelected." . $db . "_start as a
                                        left join $this->_dbSelected.$db as b
                                        on a.token = b.token
                                        where fechaservicio between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                                        group by  $indicator
                                        order by CNPS asc
                                        LIMIT 5) as a
                                        order by CNPS ";
                }
        
            
            }

            if (substr($db, 6, 3) == 'mut'){
                $dataTop = "SELECT UPPER($indicatordb) as  $indicator,
                            count(case when csat != 99 then 1 end) as total,
                            round((count(case when nps = 6 OR nps =7 then 1 end)-count(case when nps between  1 and  4 then 1 end)) / count(case when nps != 99 then 1 end) *100) as NPS,
                            round((count(case when csat = 6 OR csat = 7 then 1 end)-count(case when csat between  1 and  4 then 1 end)) / count(case when csat != 99 then 1 end) *100) as ISN,
                            b.annio
                            FROM $this->_dbSelected." . $db . "_start as a
                            left join $this->_dbSelected.$db as b
                            on a.token = b.token
                            where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                            group by  $indicator
                            order by total DESC
                            LIMIT 10 ";

                $data = DB::select($dataTop); 
  
                if($data){
                    $pos = 1;
                    foreach ($data as $key => $value) {
                        $arrayTop[]= [
                            'pos'   => $pos,
                            'text'  => $value->CentroAtencion,
                            'nps'   => $value->NPS . "%",
                            'isn'   => $value->ISN . "%",
                            'cant'  => $value-> total,
                        ];
                        $pos++;
                    }
                }   
                $tableStruct = ["Posición", "Centro", "NPS", "ISN", "Cant. Resp."];
                $tableKeys = ["pos", "text", "nps", "isn", "cant"];

                for($i = 0; $i < count($tableStruct); $i++){
                    $standarStruct[] =  [
                        "text" => $tableStruct[$i],
                        "key" => $tableKeys[$i],
                        "cellColor" => "#17C784",
                        "textAlign" => "left"
                    ];
                }
               
                return [
                    "height" =>  $height,
                    "width" =>  $width,
                    "type" =>  "tables",
                    "props" =>  [
                        "icon" => "arrow-right",
                        "text" => "RANKING By Centro de atención",
                        "tables" => [
                            [
                                "title" => "Top Ten",
                                "bgColor" => "#17C784",
                                "columns" => $standarStruct,
                                "values" => $arrayTop,
                            ],
                        ]
                    ]
                ];
            }
        }

        if($filterClient == 'all'){
            $querydataTop = "SELECT $indicator, sum(CNPS) as CNPS, annio from (SELECT UPPER($indicatordb) as  $indicator,
                            round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                            b.annio
                            FROM $this->_dbSelected.".$db."_start as a
                            left join $this->_dbSelected.$db as b
                            on a.token = b.token
                            where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                            group by  $indicator
                            UNION
                            SELECT UPPER($indicatordb) as  $indicator,
                            round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                            b.annio
                            FROM $this->_dbSelected.".$db."_start as a
                            left join $this->_dbSelected.$db as b
                            on a.token = b.token
                            where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                            group by  $indicator) as a
                            group by  $indicator
                            order by CNPS DESC
                            LIMIT 5 ";            
        
            $querydataBottom = "SELECT $indicator, sum(CNPS) as CNPS, total, annio from (SELECT UPPER($indicatordb) as  $indicator, count(UPPER($indicatordb)) as total,
                                round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                                b.annio
                                FROM $this->_dbSelected.".$db."_start as a
                                left join $this->_dbSelected.$db as b
                                on a.token = b.token
                                where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                                group by  $indicator
                                UNION
                                SELECT UPPER($indicatordb) as  $indicator, count(UPPER($indicatordb)) as total,
                                round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                                b.annio
                                FROM $this->_dbSelected.".$db."_start as a
                                left join $this->_dbSelected.$db as b
                                on a.token = b.token
                                where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != '' $datafilters
                                group by  $indicator) AS A
                                group by  $indicator
                                order by CNPS DESC
                                LIMIT 5";
        }

        $dataTop = DB::select($querydataTop);
        $dataBottom = DB::select($querydataBottom);
        $arrayTop = [];
        $arrayBottom = [];
        $pos = 1;

        if($dataTop){
            foreach ($dataTop as $key => $value) {
                $arrayTop[]= [
                    'pos'   => $pos,
                    'text'  => $value->$indicator,
                    'cnps'   => $value->CNPS . "%",
                ];
                $pos++;
            }
        }

        $pos = 1;
        if ($dataBottom) {
            foreach ($dataBottom as $key => $value) {
                $arrayBottom[]= [
                    'pos'   => $pos,
                    'text'  => $value->$indicator,
                    'cnps'   => $value->CNPS . "%",
                ];
                $pos++;
            }
        }

        $tableStruct = ["Posición", $indicator, "CNPS"];
        $tableKeys = ["pos", "text", "cnps"];

        for($i = 0; $i < count($tableStruct); $i++){
            $standarStruct[] =  [
                "text" => $tableStruct[$i],
                "key" => $tableKeys[$i],
                "cellColor" => "#17C784",
                "textAlign" => "left"
            ];
        }

        return [
            "height" =>  $height,
            "width" =>  $width,
            "type" =>  "tables",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "RANKING By $indicator",
                "tables" => [
                    [
                        "title" => "Top Five",
                        "bgColor" => "#17C784",
                        "columns" => $standarStruct,
                        "values" => $arrayTop,
                    ],
                    [
                        "title" => "Last Five",
                        "bgColor" => "#F07667",
                        "columns" => $standarStruct,
                        "values" => $arrayBottom,
                    ],
                ]
            ]
        ];
    }

    private function graphCLTransvip($dataCL)
    {
        return [
            "height" => 3,
            "width" => 6,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => "Closed Loop",
                "chart" => [
                    "fields" => [
                        [
                            "type" => "line",
                            "key" => "create",
                            "text" => "Creados",
                            "strokeColor" => "#FFB203",
                        ],

                        [
                            "type" => "line",
                            "key" => "close",
                            "text" => "Cerrados",
                            "strokeColor" => "#17C784",
                        ],
                    ],
                    "values" => $dataCL
                ],
            ],
        ];
    }

    private function graphNpsIsn($dataisn, $ButFilterWeeks)
    {
        return [
            "height" => 4,
            "width" => 12,
            "type" => "chart",
            "props" => [
                "callToAction" => $ButFilterWeeks,
                "icon" => "arrow-right",
                "text" => "NPS - ISN",
                "chart" => [
                    "fields" => [
                        [
                            "type" => "bar",
                            "key" => "nps",
                            "text" => "NPS",
                            "bgColor" => "#FFB203",
                        ],
                        [
                            "type" => "bar",
                            "key" => "ins",
                            "text" => "ISN",
                            "bgColor" => " #17C784",
                        ],
                        [
                            "type" => "reference-line",
                            "text" => "Esperado",
                            "strokeColor" => "purple",
                            "value" => 70,
                        ],
                        [
                            "type" => "reference-line",
                            "text" => "Sobre lo esperado",
                            "strokeColor" => "red",
                            "value" => 75,
                        ],
                    ],
                    "values" => $dataisn,
                ]
            ]

        ];
    }
    
    private function OrdenAerolineas($db, $dateIni, $dateEnd){
        $query = '';
        $aero = 6;
        for($i = 1; $i<= $aero; $i++ ){
            if($i != $aero)
                $query .= " round(COUNT(if(aero1_$i = 1,1,NULL)))  AS  aero$i, ";

            if($i == $aero)    
                $query .= " round(COUNT(if(aero1_$i = 1,1,NULL)))  AS  aero$i ";
        }

        $data = DB::select("SELECT $query, mes 
                            FROM $this->_dbSelected.$db 
                            WHERE  date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2'");

        $lastSentido  = '';
        $values = [];
        $meses = [];
        $suma = 0;
        $resultado = 0;
        for ($i = -11; $i < 1; $i++) {
            array_push(
                $meses,
                (int)date("m", mktime(0, 0, 0, date("m") + $i, date("d"), date("Y")))
            );
        }
        
        foreach ($data as $key => $value) {
                $suma += $value->aero1;
                $suma += $value->aero2;
                $suma += $value->aero3;
                $suma += $value->aero4;
                $suma += $value->aero5;
                $suma += $value->aero6;
                if($suma != 0)
                    $resultado = ROUND(($value->aero1*100)/$suma);
        
            if ('Preferencia' != $lastSentido) {
                $lastSentido = 'Preferencia';
                $values[$lastSentido] = [];

                array_push(
                    $values['Preferencia'],
                    [
                        array_merge(
                            ['Indicator' => 'Resultado']
                        )
                    ]
                );
                
            };

            foreach ($data as $index => $dato) {              
                if ($lastSentido == 'Preferencia') {
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['Total']         = $suma;
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['period' . $meses[11]]  = $resultado.'%';
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['rowSpan']  = ['cells' => 3, 'key' => "Total"];
                }
            }
        }

        $numberToMonth = [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic',
        ];

        $colums = [
            'Aerolineas' => 'Aerolineas',
        ];

        $colums['period'.$meses[11]]=$numberToMonth[$meses[11]];
        $colums['Total']='Total';

        return [
            "height" => 2,
            "width" => 4,
            "type" =>  "compose-table",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "Orden de prioridades aerolineas ",
                "data" => [
                    "columns" => [$colums],
                    "values" => $values,
                    "colors" => [
                        'Aerolineas' => "#17C784",
                    ]
                ],
            ]
        ];
    }

    private function BrandAwareness($db, $dateIni, $dateEnd){
        $query = '';
        $aero = 6;
        for($i = 1; $i<= $aero; $i++ ){
            if($i != $aero)
                $query .= " round(COUNT(if(aero2_$i = 1,1,NULL)))  AS  aero$i, ";

            if($i == $aero)    
                $query .= " round(COUNT(if(aero2_$i = 1,1,NULL)))  AS  aero$i ";
        }

        $data = DB::select("SELECT $query, mes 
                            FROM $this->_dbSelected.$db 
                            WHERE  date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2'");

        $lastSentido  = '';
        $values = [];
        $meses = [];
        $suma = 0;
        $resultado1 = 0;
        $resultado2 = 0;
        $resultado3 = 0;
        $resultado4 = 0;
        $resultado5 = 0;
        $resultado6 = 0;

        for ($i = -11; $i < 1; $i++) {
            array_push(
                $meses,
                (int)date("m", mktime(0, 0, 0, date("m") + $i, date("d"), date("Y")))
            );
        }
        
        foreach ($data as $key => $value) {
                $suma += $value->aero1;
                $suma += $value->aero2;
                $suma += $value->aero3;
                $suma += $value->aero4;
                $suma += $value->aero5;
                $suma += $value->aero6;
                if($suma != 0){
                    $resultado1 = ROUND(($value->aero1*100)/$suma);
                    $resultado2 = ROUND(($value->aero2*100)/$suma);
                    $resultado3 = ROUND(($value->aero3*100)/$suma);
                    $resultado4 = ROUND(($value->aero4*100)/$suma);
                    $resultado5 = ROUND(($value->aero5*100)/$suma);
                    $resultado6 = ROUND(($value->aero6*100)/$suma);
                }
        
            if ('Reconocimiento' != $lastSentido) {
                $lastSentido = 'Reconocimiento';
                $values[$lastSentido] = [];

                array_push(
                    $values['Reconocimiento'],
                    [
                        array_merge(
                            ['Indicator' => 'Resultado']
                        )
                    ]
                );
                
            };

            foreach ($data as $index => $dato) {              
                if ($lastSentido == 'Reconocimiento') {
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['Total']                = $suma;
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['Jetsmart']             = $resultado1.'%';
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['SKY']                  = $resultado2.'%';
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['LATAM']                = $resultado3.'%';
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['American Airlines']    = $resultado4.'%';
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['COPA']                 = $resultado5.'%';
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['IBERIA']               = $resultado6.'%';
                    $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['rowSpan']              = ['cells' => 3, 'key' => "Total"];
                }
            }
        }

        $colums = [
            'BrandAwareness' => 'Brand Awareness',
        ];

        $colums['Jetsmart']             = 'Jetsmart';
        $colums['SKY']                  = 'SKY';
        $colums['LATAM']                = 'LATAM';
        $colums['American Airlines']    = 'American Airlines';
        $colums['COPA']                 = 'COPA';
        $colums['IBERIA']               = 'IBERIA';
        $colums['Total']='Total';

        return [
            "height" => 2,
            "width" => 8,
            "type" =>  "compose-table",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "Brand Awareness",
                "data" => [
                    "columns" => [$colums],
                    "values" => $values,
                    "colors" => [
                        'BrandAwareness' => "#17C784",
                    ]
                ],
            ]
        ];
    }

    private function statsByTaps($db, $db2, $mes, $year, $npsInDb, $csatInDb, $startDateFilterMonth, $endDateFilterMonth, $datafilters = null, $filterClient, $indetifyClient)
    {
        $standarStruct = [
            [
                "text" => "NPS",
                "key" => "nps",
                "cellColor" => "rgb(0,0,0)",
            ],
            [
                "text" => "CSAT",
                "key" => "csat",
                "cellColor" => "rgb(0,0,0)",
            ],
            [
                "text" => "Cantidad de respuesta",
                "key" => "quantity",
                "cellColor" => "rgb(0,0,0)",
            ]
        ];

        if ( substr($db , 10, 3) == 'web'){
            $datasTramos = $this->getDetailsForIndicator($db, $db2, date('m'), date('Y'), $npsInDb, $csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'tramo', $datafilters, $filterClient);
            $datasAntiguedad = $this->getDetailsAntiquity($db, $db2, date('m'), date('Y'), $npsInDb, $csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'antIsapre', $datafilters, $filterClient);
            $datasNichosStruct = ['columns' => [], 'values' => []];    
        }

        if( substr($db , 10, 3) != 'web'){
            $datasNichos = $this->getDetailsForIndicator($db, $db2, date('m'), date('Y'), $npsInDb, $csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'nicho', $datafilters, $filterClient);
            $datasTramos = $this->getDetailsForIndicator($db, $db2, date('m'), date('Y'), $npsInDb, $csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'tramo', $datafilters, $filterClient);
            $datasAntiguedad = $this->getDetailsAntiquity($db, $db2, date('m'), date('Y'), $npsInDb, $csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'antIsapre', $datafilters, $filterClient);
            $datasNichosStruct = [
                "columns" => [
                    [
                        "text" => "NICHOS",
                        "key" => "niche",
                        "headerColor" => "#17C784",
                        "cellColor" => "#949494",
                        "textAlign" => "left"
                    ],
                    $standarStruct[0],
                    $standarStruct[1],
                    $standarStruct[2],
                ],
                "values" => $datasNichos,
            ];
        }

        return [
            "height" =>  3,
            "width" =>  12,
            "type" =>  "tables",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "STATS by business segments",
                "tables" => [
                    [
                        "columns" => [
                            [
                                "text" => "TRAMOS",
                                "key" => "sections",
                                "headerColor" => "#17C784",
                                "cellColor" => "#949494",
                                "textAlign" => "left"
                            ],
                            $standarStruct[0],
                            $standarStruct[1],
                            $standarStruct[2],
                        ],
                        "values" => $datasTramos,
                    ],
                    $datasNichosStruct,
                    [
                        "columns" => [
                            [
                                "text"          => "ANTIGÜEDAD",
                                "key"           => "antiquity",
                                "headerColor"   => "#17C784",
                                "cellColor"     => "#949494",
                                "textAlign"     => "left"
                            ],
                            $standarStruct[0],
                            $standarStruct[1],
                            $standarStruct[2],
                        ],
                        "values" => $datasAntiguedad,
                    ]

                ]
            ]
        ];
    }

    protected function structfilter($request, $fieldbd, $fieldurl, $where)
    {   
        if ($request->get($fieldurl) === null)
            return '';
        if ($request->get($fieldurl)) {
            if ($where != '') {
                $where = " AND $fieldbd = '" . $request->get($fieldurl) . "'";
            }
            if ($where == '') {
                $where = " $fieldbd = '" . $request->get($fieldurl) . "'";
            }
        }
        return $where;
    }

    private function infofilters($request)
    {
        $where = '';
        
        //TRANSVIP
        if(substr($request->survey,0,3) == 'tra'){
        $where .= $this->structfilter($request, 'tipocliente',       'TipoCliente',       $where);
        $where .= $this->structfilter($request, 'tiposervicio',      'TipoServicio',      $where);
        $where .= $this->structfilter($request, 'condicionservicio', 'CondiciónServicio', $where);
        $where .= $this->structfilter($request, 'sentido',           'Sentido',           $where);
        $where .= $this->structfilter($request, 'zon',               'Zona',              $where);
        $where .= $this->structfilter($request, 'tipoReserva',       'TipoReserva',       $where);
        $where .= $this->structfilter($request, 'canal',             'Canal',             $where);
        $where .= $this->structfilter($request, 'convenio',          'Convenio',          $where);
        $where .= $this->structfilter($request, 'contrato',          'Contrato',          $where);

        return $where;
        }

        //BANMEDICA
        $where .= $this->structfilter($request, 'sex',       'Genero',   $where);
        $where .= $this->structfilter($request, 'region',    'Regiones', $where);
        $where .= $this->structfilter($request, 'nicho',     'Nicho',    $where);
        $where .= $this->structfilter($request, 'tramo',     'Tramo',    $where);
        $where .= $this->structfilter($request, 'nomSuc',    'Sucursal', $where);
        $where .= $this->structfilter($request, 'sitioWeb',  'Web',      $where);

        return $where;
    }

    protected function cardsPerformace($dataNps, $dataCsat,$dateEnd, $dateIni, $survey, $datafilters, $dataCes = null, $dataCbi = null)
    {
        $width = 6;
        $resp = [];

        if ($datafilters)
            $datafilters = " $datafilters";

        if ($this->_dbSelected == 'customer_colmena' &&  substr($survey, 0, 3) == 'mut') {    
            $name = $dataCsat['name'];
            $val = $dataCsat['value'];
            $percentage = $dataCsat['percentage'];
        }
       
        if($this->_dbSelected == 'customer_colmena' && substr($survey, 0, 3) == 'tra'){
            // if(substr($survey,3,3) == 'con')
            //     $db = 'adata_tra_cond';
            // if(substr($survey,3,3) == 'via')
            //     $db = 'adata_tra_via';
            // $ins = $this->NpsIsnTransvip($db, $dateIni, $dateEnd,'nps','csat',$datafilters,'', 'x' );

            // $insPreviousPeriod = $this->npsPreviousPeriod($db,$dateEnd, $dateIni,'csat',''); 
            $name = 'ISN';
            $val =  $dataCsat['insAct'] == 'N/A' ? 'N/A' : round($dataCsat['insAct']);
            $percentage= $dataCsat['insAct'] == 'N/A' ? round(-$dataCsat['ins']) : round($dataCsat['insAct']-$dataCsat['ins']);  
        }

        if ($this->_dbSelected == 'customer_banmedica') {
            $name = $dataCsat['name'];
            $val = $dataCsat['value'];
            $percentage =  (int)$dataCsat['percentage'];
        }
       
        $this->_valueMinAnomalias = (int)$dataNps['value'] - 20;
        $this->_valueMaxAnomalias = (int)$dataNps['value'] + 30;
       
        if ($this->_dbSelected != 'customer_jetsmart') { 
            $resp = [
                        [
                            "name"    => $dataNps['name'],
                            "value"   => $dataNps['value'],
                            "m2m"     => (int)round($dataNps['percentage']),
                        ],
                        [
                            "name"    => $name,
                            "value"   => $val,
                            "m2m"  => $percentage,
                        ],
                        
                    ];
        }
        if ($this->_dbSelected == 'customer_jetsmart') { 
            $width = 12;
            if(substr($survey, 3, 3) == 'com'){

              $resp = [
                            [
                                "name"    => $dataCbi != '' ? $dataCbi['name'] : 'CBI',
                                "value"   => $dataCbi != '' ? $dataCbi['value'] : 'N/A',
                                "m2m"     => $dataCbi != '' ? (int)round($dataCbi['percentage']) : 'N/A',
                                "color"   => $dataCbi != '' ? ($dataCbi['value'] != 'N/A' ? ($dataCbi['value'] > 80 ? "#17C784" : ($dataCbi['value'] < 60 ? "#fe4560" : "#FFC700")) : "#DFDEDE" ) : "#DFDEDE",
                            ],
                            [
                                "name"    => $dataNps['name'],
                                "value"   => $dataNps['value'],
                                "m2m"     => (int)round($dataNps['percentage']),
                                "color"   => $dataNps['value'] != 'N/A' ? ($dataNps['value'] > 50 ? "#17C784" : ($dataNps['value'] < 40 ? "#fe4560" : "#FFC700")) : "#DFDEDE",
                            ],
                            [
                                "name"    => $dataCsat['name'],
                                "value"   => $dataCsat['value'],
                                "m2m"     => (int)round($dataCsat['percentage']),
                                "color"   => $dataCsat['value'] != 'N/A' ? ($dataCsat['value'] > 50 ? "#17C784" : ($dataCsat['value'] < 40 ? "#fe4560" : "#FFC700")) : "#DFDEDE", 
                            ],
                            [
                                "name"    => $dataCes['name'],
                                "value"   => $dataCes['value'],
                                "m2m"     => (int)round($dataCes['percentage']),
                                "color"   => $dataCes['value'] != 'N/A' ? ($dataCes['value'] > 80 ? "#17C784" : ($dataCes['value'] < 60 ? "#fe4560" : "#FFC700")) : "#DFDEDE", 
                            ]
                        ];
            }

            if(substr($survey, 3, 3) == 'via' || substr($survey, 3, 3) == 'vue'){

                $resp = [
                            [
                                "name"    => $dataCbi != '' ? $dataCbi['name'] : 'CBI',
                                "value"   => $dataCbi != '' ? $dataCbi['value'] : 'N/A',
                                "m2m"     => $dataCbi != '' ? (int)round($dataCbi['percentage']) : 'N/A',
                                "color"   => $dataCbi != '' ? ($dataCbi['value'] != 'N/A' ? ($dataCbi['value'] > 80 ? "#17C784" : ($dataCbi['value'] < 60 ? "#fe4560" : "#FFC700")) : "#DFDEDE" ) : "#DFDEDE",
                            ],
                            [
                                "name"    => $dataNps['name'],
                                "value"   => $dataNps['value'],
                                "m2m"     => (int)round($dataNps['percentage']),
                                "color"   => $dataNps['value'] != 'N/A' ? ($dataNps['value'] > 50 ? "#17C784" : ($dataNps['value'] < 40 ? "#fe4560" : "#FFC700")) : "#DFDEDE",
                            ],
                            [
                                "name"    =>  substr($survey, 0, 3) == 'mut'? 'ISN' : $dataCsat['name'],
                                "value"   => $dataCsat['value'] != 'N/A' ? round($dataCsat['value']) : 'N/A',
                                "m2m"     => $dataCsat['value'] != 'N/A' ? (int)round($dataCsat['percentage']) : 'N/A',
                                "color"   => $dataCes['value'] != 'N/A' ? ($dataCes['value'] > 80 ? "#17C784" : ($dataCes['value'] < 60 ? "#fe4560" : "#FFC700")) : "#DFDEDE",
                            ],
                        ];
            }
        }

        return [
            "height" => 1,
            "width" => $width,
            "type" => "performance",
            "props" => [
                "icon" => "arrow-right",
                "performances" => $resp
            ],
        ];
    }

    protected function welcome($client, $filterClient, $bd, $table = null)
    {
        $nameEncuesta = ucwords(strtolower($this->nameSurvey(trim($bd))));

        return [
            "height" =>  1,
            "width" =>  $client == 'jet'? 12 : 6,
            "type" =>  "welcome",
            "props" =>  [
                "icon"=> "smile",
                "text"=> $this->imagen($client, $filterClient, $nameEncuesta, $table),
            ],
        ];
    }

    private function cardNpsConsolidado($name, $dataNPSGraphBanVid, $ButFilterWeeks)
    {
        return [
            "height" => 4,
            "width" => 12,
            "type" => "chart",
            "props" => [
                "callToAction" => $ButFilterWeeks,
                "icon" => "arrow-right",
                "text" => "NPS Consolidado • $name",
                "chart" => [
                    "fields" => [
                        [
                            "type" => "stacked-bar",
                            "key" => "detractors",
                            "text" => "Detractores",
                            "bgColor" => "#fe4560",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "neutrals",
                            "text" => "Neutrales",
                            "bgColor" => "#FFC700",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "promoters",
                            "text" => "Promotores",
                            "bgColor" => "#17C784",
                        ],
                        [
                            "type" => "line",
                            "key" => "nps",
                            "text" => "NPS",
                            "bgColor" => "#1a90ff",
                        ],
                    ],
                    "values" => $dataNPSGraphBanVid
                ],
            ],
        ];
    }

    
    private function cardNpsBanmedica($nameIndicatorPrincipal, $dataNPSGraph, $indicador = 'NPS')
    {
        //$indicador === 'CSAT' ? print_r($dataNPSGraph) : print_r('nada');
        return [
            "height" => 3,
            "width" => 6,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => $nameIndicatorPrincipal != 'JetSmart' ? "NPS • " . $nameIndicatorPrincipal : $indicador,
                "chart" => [
                    "fields" => [
                        [
                            "type" => "stacked-bar",
                            "key" => "detractors",
                            "text" => $indicador === 'NPS' ? "Detractores" : ($indicador === 'CSAT' ? "Insatisfechos" : "Difícil"),
                            "bgColor" => "#fe4560",
                        ],

                        [
                            "type" => "stacked-bar",
                            "key" => "neutrals",
                            "text" => $indicador === 'NPS' || $indicador === 'CSAT' ? "Neutrales" : "Neutro",
                            "bgColor" => "#FFC700",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "promoters",
                            "text" => $indicador === 'NPS' ? "Promotores" : ($indicador === 'CSAT' ? "Satisfechos" : "Fácil"),
                            "bgColor" => "#17C784",
                        ],
                        [
                            "type" => "line",
                            "key" => $indicador === 'NPS' ? "nps" : ($indicador === 'CSAT' ? 'csat' : 'ces'),
                            "text" => $indicador === 'NPS' ? "NPS" : ($indicador === 'CSAT' ? 'CSAT' : 'CES'),
                            "bgColor" => "#1a90ff",
                        ],
                    ],
                    "values" => $dataNPSGraph
                ],
            ],
        ];
    }

    private function cardNpsVidaTres($nameIndicatorPrincipal2, $dataNPSGraph2)
    {
        return [
            "height" => 3,
            "width" => 6,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => "NPS • " . $nameIndicatorPrincipal2,
                "chart" => [
                    "fields" => [
                        [
                            "type" => "stacked-bar",
                            "key" => "detractors",
                            "text" => "Detractores",
                            "bgColor" => "#fe4560",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "neutrals",
                            "text" => "Neutrales",
                            "bgColor" => "#FFC700",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "promoters",
                            "text" => "Promotores",
                            "bgColor" => "#17C784",
                        ],
                        [
                            "type" => "line",
                            "key" => "nps",
                            "text" => "NPS",
                            "bgColor" => "#1a90ff",
                        ],
                    ],
                    "values" => $dataNPSGraph2
                ],
            ],
        ];
    }

    protected function CSATJourney($graphCSATDrivers)
    {
        return [
            "height" => 4,
            "width" => 12,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => "CSAT Journey",
                "iconGraph" => true,
                "chart" => [
                    "fields" => [
                        [
                            "type" => "area",
                            "key" => "csat",
                            "text" => "CSAT",
                            "bgColor" => "#E9F4FE",
                            "strokeColor" => "#008FFB",
                        ],
                    ],
                    "values" => $graphCSATDrivers
                ],
            ]
        ];
    }

    protected function CSATDrivers($graphCSATDrivers){
      return [
            "height" => 4,
            "width" => 12,
            "type"  => "chart-horizontal",
            "props" => [
                "icon" => "arrow-right",
                "text" => "CSAT Drivers",
                "chart" => [
                    "fields" => [
                        [
                            "type" => "stacked-bar",
                            "key" => "detractors",
                            "text" => "Insatisfechos",
                            "bgColor" => "#fe4560",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "neutrals",
                            "text" => "Neutrales",
                            "bgColor" => "#FFC700",
                        ],
                        [
                            "type" => "stacked-bar",
                            "key" => "promoters",
                            "text" => "Satisfechos",
                            "bgColor" => "#17C784",
                        ],
                        [
                            "type" => "total",
                            "key" => "csat",
                            "text" => "CSAT",
                        ],
                    ],
                    "values" => $graphCSATDrivers
                ],
            ],
        ];
    }

    private function setAnomaliasTextAnalitics($cant, $value, $text, $group)
    {
            if ($this->_valueAnomaliasPorcentajeText < $cant) {
                if ($this->_valueMinAnomaliasText >= $value) {
                    //if(!in_array($group,$this->_anomaliasPain)){
                       // array_push($this->_anomaliasPain, $group);
                    //}
                }
                if ($this->_valueMaxAnomaliasText <= $value) {
                    
                    //if(!in_array($group,$this->_anomaliasGain)){
                        //array_push($this->_anomaliasGain, $group);
                    //}
                }
            }    
    }
    
    private function setAnomalias($value, $text){
        
        if($this->_valueMinAnomalias >= $value) {
            array_push($this->_anomaliasPain, $text);
        }
        if ($this->_valueMaxAnomalias <= $value) {
            array_push($this->_anomaliasGain, $text);
        }
        //print_r($this->_anomaliasPain);exit;
    }

    private function setAnomaliasCBI($value, $text){
        if($this->_valueMinAnomaliasCBI >= $value) {
            array_push($this->_anomaliasPainCBI, $text);
        }
        if ($this->_valueMaxAnomaliasCBI <= $value) {
            array_push($this->_anomaliasGainCBI, $text);
        }
    }

    private function setTextAnomalias($value)
    {
        if ($this->_valueMinAnomalias >= $value) {
            return ['color'=>'rgb(254, 69, 96)','text'=>'▼'];
        }
        if ($this->_valueMaxAnomalias <= $value) {
            return ['color'=>'rgb(23, 199, 132)','text'=>'▲'];
        }
        return ['color'=>'rgb(0,0,0)','text'=>''];
    }

    private function setTextAnomaliasCBI($value)
    {
        if ($this->_valueMinAnomaliasCBI >= $value) {
            return ['color'=>'rgb(254, 69, 96)','text'=>'▼'];
        }
        if ($this->_valueMaxAnomaliasCBI <= $value) {
            return ['color'=>'rgb(23, 199, 132)','text'=>'▲'];
        }
        return ['color'=>'rgb(0,0,0)','text'=>''];
    }

    // public function arrayPushToValues($array,$valuesReferences,$keyReferences){
    //     if($keyReferences == 'GAP'){
    //         foreach($valuesReferences as $key => $value){
    //             array_push($array, ['name' => $value, 'exp' => 9]);
    //         }
    //     }
    //     if($keyReferences == 'frec'){
    //         foreach($valuesReferences as $key => $value){
    //             array_push($array, ["icon" => "plane","percentage"=> $value, "quantity" => '']);
    //         }
    //     }
    //     if($keyReferences == 'lab'){
    //         foreach($valuesReferences as $key => $value){
    //             array_push($array, ["icon" => "star", "percentage" => $value, "quantity" =>  '']);
    //         }
    //     }
    //     return $array;
    // }


    //DETAILS DASH
    public function detailsDash($request, $jwt)
    {
        if ($request->survey === null) {
            return [
                'datas'     => ['No estas enviando una survey'],
                'status'    => Response::HTTP_NOT_ACCEPTABLE
            ];
        }

        $group =  null;
        $startDateFilterMonth   = date('Y-m-01');
        $endDateFilterMonth     = date('Y-m-d');
        $dateIni = date('Y-m-d');                                        //2022-01-08
        $dateEnd = date('Y-m-d', strtotime(date('Y-m-d') . "- 12 month")); //2021-01-08
        $dateEndIndicatorPrincipal = date('Y-m-01'); 
        
        if ($request->dateIni !== null && $request->dateEnd !== null) {
            $dateIni = $request->dateEnd;
            $dateEnd = $request->dateIni;
            $startDateFilterMonth   = $request->dateIni;
            $endDateFilterMonth     = $request->dateEnd;
            $dateEndIndicatorPrincipal = $request->dateIni;
        }

        $datafilters = $this->infofilters($request);

        if ($request->filterWeeks !== null) {
            $interval = is_numeric($request->filterWeeks) ? $request->filterWeeks : 9;
            if ($datafilters != '') {
                if(substr($request->survey, 3, 3) == 'via')
                {
                    $datafilters .= ' and fechaservicio between date_sub(NOW(), interval 9 week) and NOW() ';
                    $group = " week ";
                }
                if(substr($request->survey, 3, 3) != 'via')
                {
                    $datafilters .= ' and date_survey between date_sub(NOW(), interval 9 week) and NOW() ';
                    $group = " week ";
                }
            }
            if ($datafilters == '') {
                if(substr($request->survey, 3, 3) == 'via')
                {
                    $datafilters .= ' fechaservicio between date_sub(NOW(), interval 9 week) and NOW() ';
                    $group = " week ";
                }
                if(substr($request->survey, 3, 3) != 'via')
                {
                    $datafilters .= ' date_survey between date_sub(NOW(), interval 9 week) and NOW() ';
                    $group = " week ";
                }
              
            }
        }

        $filterClient  = ($request->client === null) ? $this->_initialFilter : $request->client;
        $indetifyClient = substr($request->survey, 0, 3);
        $indetifyClient = ($filterClient == 'all') ? $indetifyClient : $filterClient;
        $npsInDb    = $this->getFielInDb($request->survey);
        $csatInDb   = $this->getFielInDbCsat($request->survey);
        $db         = 'adata_'.$indetifyClient.'_'.trim(substr($request->survey,3,6));
        
        if(substr($request->survey,0,3) == 'jet'){
            $db = 'adata_'.substr($request->survey,0,3).'_'.trim(substr($request->survey,3,6));
        }
       
        $brandAwareness = null;
        $aerolineas = null;
        $rankingSuc = null;
        $ges = null;
        $ejecutivo = null;
        $sucNpsCsat =  null;
        $regiones = null;
        $sucursal = null;
        $call = null;
        $venta = null;
        $super = null;
        $indicatordb = ($indetifyClient == 'vid') ? 'ban' : 'vid';
        $nameIndicatorPrincipal  = ($indetifyClient == 'vid') ? 'Vida Tres' : 'Banmédica';   //banmedica
        $nameIndicatorPrincipal2 = ($indetifyClient == 'vid') ? 'Banmédica' : 'Vida Tres';  //vidatres

        $dbVT       = 'adata_' . $indicatordb . '_' . substr($request->survey, 3, 6);

        if (substr($request->survey, 0, 3) == 'tra') {
            if(substr($request->survey,3,3) == 'con')
                $db = 'adata_tra_cond';
            if(substr($request->survey,3,3) == 'via')
                $db = 'adata_tra_via';
           
        }

        $dataNps    = $this->resumenNps($db, $dateIni, $dateEndIndicatorPrincipal, $npsInDb, $filterClient, $datafilters);
        $dataCsat   = $this->resumenCsat($db, $dateIni, $dateEndIndicatorPrincipal, $csatInDb, $filterClient, $datafilters);
 
        if (substr($request->survey, 0, 3) == 'jet') {
            $dataCsatGraph   = $this->graphCsat($db,  $csatInDb, $dateIni, $dateEnd, $filterClient, $datafilters);
        }
       
        if (substr($request->survey, 0, 3) != 'jet') {
            $dataCsatGraph   = $this->graphCsat($db,  $csatInDb, $endDateFilterMonth, $startDateFilterMonth,  $filterClient, $datafilters);
        }
     
        $jetNamesGene = [
            'title' => 'Generation',
            'data' => [
                [
                    "icon" => "genz",
                    "percentage" => 'GEN Z',
                    "quantity" =>  '14-22',
                ],
                [
                    "icon" => "genmille",
                    "percentage" => 'GEN MILLE',
                    "quantity" =>  '23-38',
                ],
                [
                    "icon" => "genx",
                    "percentage" => 'GEN X',
                    "quantity" =>  '39-54',
                ],
                [
                    "icon" => "genbb",
                    "percentage" => 'GEN BB',
                    "quantity" =>  '55-73',
                ],
                [
                    "icon" => "gensil",
                    "percentage" => 'GEN SIL',
                    "quantity" =>  '74-91',
                ],
            ]
        ];

        $jetNamesLab = [
            'title' => 'Situación Laboral',
            'data' => [
                [
                    "icon" => "star",
                    "percentage" => 'Cesante',
                    "quantity" =>  '',
                ],
                [
                    "icon" => "star",
                    "percentage" => 'Empleado',
                    "quantity" =>  '',
                ],
                [
                    "icon" => "star",
                    "percentage" => 'Emprendedor',
                    "quantity" =>  '',
                ],
                [
                    "icon" => "star",
                    "percentage" => 'Estudiante',
                    "quantity" =>  '',
                ],
                [
                    "icon" => "star",
                    "percentage" => 'Ret/Jub',
                    "quantity" =>  '',
                ],
            ]
        ];

        $jetNamesFrecVuelo = [
            'title' => 'Frecuencia de Vuelo',
            'data' => [
               // $this->arrayPushToValues([],['1 / semana','2-3 / mes','1 / mes','2+ al  año','Act. no viajo','1 / año'],'frec')
                [
                    "icon" => "plane",
                    "percentage"=> '1 / semana',
                    "quantity" =>  '',
                ],
                [
                    "icon" => "plane",
                    "percentage"  => '2-3 / mes',
                    "quantity"=>  '',
                ],
                [
                    "icon" => "plane",
                    "percentage" => '1 / mes',
                    "quantity" =>  '',
                ],
                [
                    "icon" => "plane",
                    "percentage" => '2+ al  año',
                    "quantity" =>  '',
                ],
                [
                    "icon" => "plane",
                    "percentage" => '1 / año',
                    "quantity" =>  '',
                ],
                [
                    "icon" => "plane",
                    "percentage" => 'Act. no viajo',
                    "quantity" =>  '',
                ],
            ]
        ];

        $structGAPJetSmart = [
            [
                'name' => 'Compra',
                'exp' => 9,
            ],
            [
                'name' => 'Pago',
                'exp' => 9,
            ],            
            [
                'name' => 'N/A',
                'exp' => 9,
            ],
            [
                'name' => 'Confirmación',
                'exp' => 9,
            ],
            [
                'name' => 'Check in',
                'exp' => 9,
            ],
            [
                'name' => 'Registro equipaje',
                'exp' => 9,
            ],
            [
                'name' => 'Abordaje',
                'exp' => 9,
            ],
            [
                'name' => 'Vuelo',
                'exp' => 9,
            ],
            [
                'name' => 'Llegada',
                'exp' => 9,
            ],
            [
                'name' => 'Atención cliente',
                'exp' => 9,
            ],
        ];

        
        if ($this->_dbSelected  == 'customer_banmedica') {
            $name =  $nameIndicatorPrincipal . ' & ' . $nameIndicatorPrincipal2;
            $db2 = ($indetifyClient == 'vid') ? 'adata_ban_' . trim(substr($request->survey, 3, 6)) : 'adata_vid_' . trim(substr($request->survey, 3, 6));
            
            $dataNPSGraph         = $this->graphNps($db, $npsInDb, $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
            $dataNPSGraph2        = $this->graphNps($dbVT, $npsInDb, $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
            $dataNPSGraphBanVid   = $this->graphNpsBanVid($db, $db2, date('m'), date('Y'), $npsInDb, $dateIni, $dateEnd, $datafilters, $group);
            if ($request->client == 'vid' || $request->client == 'ban') {
                $graphCSATDrivers     = $this->GraphCSATDrivers($db, $db2, trim($request->survey), $csatInDb, $endDateFilterMonth, $startDateFilterMonth,  'two', 'two', $datafilters, $group);
            }
            if ($request->client == null) {
                $graphCSATDrivers     = $this->GraphCSATDrivers($db, $db2, trim($request->survey), $csatInDb, $endDateFilterMonth, $startDateFilterMonth,  'all', 'two', $datafilters, $group);
            }

            if ($db == 'adata_ban_ven' || $db == 'adata_vid_ven') {
                $super = $this->npsByIndicator($db, $dateEnd, $dateIni, $filterClient, 'nomSuper', 'nomSuper', 'supervisor', 'supervisor', 'Supervisor', 4);
                $venta = $this->npsByIndicator($db, $dateEnd, $dateIni, $filterClient, 'nomFuerVent', 'nomFuerVent', 'FuerzaVenta', 'FuerzaVenta', 'Fuerza de venta', 4);
            } 
            if ($db == 'adata_ban_asi' || $db == 'adata_vid_asi' || $db == 'adata_vid_con' || $db == 'adata_ban_con') {
                $call = $this->npsByIndicator($db, $dateEnd, $dateIni, $filterClient, 'dirLlamada', 'dirLlamada', 'llamada', 'llamada', 'Llamadas', 2);
                $sucNpsCsat = $this->npsCsatbyIndicator($db, $dateEnd, $dateIni, 'UPPER(nombreEjecutivo)', 'Ejecutivo', 'csat2', 'csat3', 6, $filterClient);
            } 
            if ($db == 'adata_ban_con' || $db == 'adata_vid_con') {
                $ejecutivo = $this->npsByIndicator($db, $dateEnd, $dateIni, $filterClient, "DISTINCT(UPPER(nombreEjecutivo)like 'Ext%')", 'nombreEjecutivo', 'nombreEjecutivo', "(nombreEjecutivo NOT like 'Ext%')", 'Ejecutivos', 2);
            } 
            if ($db == 'adata_ban_suc' || $db == 'adata_vid_suc') {
                $sucursal   = $this->npsNew($db, $dateEnd, $dateIni, 4, $filterClient);
                $regiones   = $this->npsByRegiones($db, $dateEnd, $dateIni, $filterClient, 'ubicSuc', 'regiones', 'Regiones y Region Metropolitana');
                $sucNpsCsat = $this->npsCsatbyIndicator($db, $dateEnd, $dateIni, 'nomSuc', 'Sucursal', 'csat3', 'csat4', 6, $filterClient);
                $rankingSuc = $this->ranking($db, 'nomsuc', 'Sucursal', $endDateFilterMonth, $startDateFilterMonth, $filterClient, $datafilters,8, 4);
                if ($db == 'adata_ban_suc') {
                    $db = 'adata_ban_con';
                    $ges = $this->npsByIndicator($db, $dateEnd, $dateIni, $filterClient, 'canal', 'canal', 'canal', 'canal', 'Canal', 2);
                    $db = 'adata_ban_suc';
                }
            }

            $welcome            = $this->welcome($indetifyClient, $filterClient, $request->survey);
            $performance        = $this->cardsPerformace($dataNps, $dataCsat, $dateEnd, $dateIni, $request->survey, $datafilters);
            $npsConsolidado     = $this->cardNpsConsolidado($name, $dataNPSGraphBanVid, $this->ButFilterWeeks);
            $npsBan             = $this->cardNpsBanmedica($nameIndicatorPrincipal, $dataNPSGraph);
            $npsVid             = $this->cardNpsVidaTres($nameIndicatorPrincipal2, $dataNPSGraph2);
            $csatJourney        = $this->CSATJourney($graphCSATDrivers);
            $csatDrivers        = $this->CSATDrivers($graphCSATDrivers);
            $closedLoop         = $this->closedLoop($db, $npsInDb, $endDateFilterMonth, $startDateFilterMonth, $filterClient, $datafilters);
            $detailGender       = $this->detailsGender($db, $npsInDb, $csatInDb, $endDateFilterMonth, $startDateFilterMonth,  $filterClient, $datafilters, $indetifyClient);
            $detailGeneration   = $this->detailGeneration($db, $npsInDb, $csatInDb, $endDateFilterMonth, $startDateFilterMonth, $filterClient,  $datafilters, $indetifyClient);
            $datasStatsByTaps   = $this->statsByTaps($db, $db2, date('m'), date('Y'), $npsInDb, $csatInDb, $startDateFilterMonth, $endDateFilterMonth, $datafilters, $filterClient, $indetifyClient);
            $wordCloud          = $this->wordCloud($request); //null; 
            $detailsProcedencia = $super;
            $box14              = $venta;
            $box15              = $call;
            $box16              = $regiones;
            $box17              = $sucursal;
            $box18              = $ejecutivo;
            $box19              = $ges;
            $box20              = $sucNpsCsat;
            $cx                 = $this->cxIntelligence($request);
            $box21              = $rankingSuc;
            $box22              = null;
        }

        if ($this->_dbSelected  == 'customer_colmena'  && substr($request->survey, 0, 3) == 'tra') {
            $proveedor      = $frecCon      = $contactoEmpresas = $atrImport        = $canalPref        = null;
            $graphCbiResp   = $graphIsnResp = $globalSentido    = $graphClTra       = $globalesVehi     = null;
            $globalesSuc    = $globalesServ = $globalesCliente  = $globalesReserva  = $rankingConvenio  = null;

            $graphCSATDrivers   = $this->GraphCSATDriversTransvip($db, trim($request->survey), $dateIni, $startDateFilterMonth, null, 'two', $datafilters);
            
            if($db == 'adata_tra_cond'){
                $proveedor          = $this->rankingTransvip($db, $datafilters, $dateIni, $startDateFilterMonth, 'cbi', "Continuar Como Proveedor", 3, 4);
                $frecCon            = $this->rankingTransvip($db, $datafilters, $dateIni, $startDateFilterMonth, 'opc', "Frecuencia de conexión", 3, 4);
                $contactoEmpresas   = $this->rankingTransvip($db, $datafilters, $dateIni, $startDateFilterMonth, 'sino1', "Contacto otras empresas", 3, 4);
                $atrImport          = $this->rankingTransvip($db, $datafilters, $dateIni, $startDateFilterMonth, 'mult1', "Atributos más importantes", 3, 4);
                $canalPref          = $this->rankingTransvip($db, $datafilters, $dateIni, $startDateFilterMonth, 'opc2', "Canal Preferido", 3, 4);
                $csatDriv           = $this->CSATDrivers($graphCSATDrivers);
            }
            
            if($db == 'adata_tra_via'){
                $datasCbiResp       = $this->cbiResp($db,$datafilters, $dateIni, $dateEnd);
                $graphCbiResp       = $this->graphCbiResp($datasCbiResp);
                $drivers            = $this->csatsDriversTransvip($db, trim($request->survey), $dateIni, $dateEnd, $datafilters);
                $csatDriv           = $this->graphCsatTransvip($drivers, $request->survey);
                $tiempoVehiculo     = $this->NpsIsnTransvip($db, $dateIni, $dateEnd, $npsInDb, 'csat2', $datafilters, null);
                $coordAnden         = $this->NpsIsnTransvip($db, $dateIni, $dateEnd, $npsInDb, 'csat3', $datafilters, null);
                $tiempoAeropuerto   = $this->NpsIsnTransvip($db, $dateIni,$dateEnd, $npsInDb, 'csat6', $datafilters, null);
                $tiempoLlegadaAnden = $this->NpsIsnTransvip($db, $dateIni, $dateEnd, $npsInDb, 'csat5', $datafilters, null);
                $graphIsnResp       = $this->graphINS($tiempoVehiculo, $coordAnden, $tiempoAeropuerto, $tiempoLlegadaAnden);
                $globalSentido      = $this->globales($db, $dateIni, $dateEnd, 'sentido', 'Sentido', 'cbi', 'ins', 4, $datafilters);
                $dataCL             = $this->closedloopTransvip($datafilters, $dateIni, $dateEnd, $request->survey);
                $graphClTra         = $this->graphCLTransvip($dataCL);
                $globalesVehi       = $this->globales($db, $dateIni, $dateEnd, 'tiposervicio', 'Vehículo', 'cbi', 'ins', 4, $datafilters);
                $globalesSuc        = $this->globales($db, $dateIni, $dateEnd, 'sucursal', 'Sucursal', 'cbi', 'ins', 4, $datafilters);
                $globalesServ       = $this->globales($db, $dateIni, $dateEnd, 'condicionservicio', 'Servicio', 'cbi', 'ins', 4, $datafilters);
                $globalesCliente    = $this->globales($db, $dateIni, $dateEnd, 'tipocliente', 'Cliente', 'cbi', 'ins', 4, $datafilters);
                $globalesReserva    = $this->globales($db, $dateIni, $dateEnd, 'tipoReserva', 'Reserva', 'cbi', 'ins', 4, $datafilters);
                $rankingConvenio    = $this->ranking($db, 'convenio', 'Convenio', $endDateFilterMonth, $startDateFilterMonth, $filterClient,$datafilters, 12, 3);
            }

            $name = 'Transvip';         
            $dataisn            = $this->NpsIsnTransvip($db, $dateIni, $dateEnd, $npsInDb, $csatInDb, $datafilters, $group);
            $dataIsnPerf        = $this->npsPreviousPeriod($db,$dateIni,$dateEndIndicatorPrincipal,'isn','' );

            $welcome            = $this->welcome(substr($request->survey, 0, 3), $filterClient, $request->survey, $db);
            $performance        = $this->cardsPerformace($dataNps, $dataIsnPerf, $dateEnd, $dateIni, $request->survey, $datafilters);
            $npsConsolidado     = $this->graphNpsIsn($dataisn, $this->ButFilterWeeks);
            $npsVid             = $this->wordCloud($request);
            $csatJourney        = $this->CSATJourney($graphCSATDrivers);
            $csatDrivers        = $graphClTra;
            $cx                 = $graphCbiResp;
            $wordCloud          = $globalSentido;
            $closedLoop         = $globalesVehi;
            $detailGender       = $globalesSuc;
            $detailGeneration   = $globalesServ;
            $datasStatsByTaps   = $globalesCliente;
            $detailsProcedencia = $globalesReserva;
            $box14              = $rankingConvenio;
            $box15              = $graphIsnResp;
            $box16              = $csatDriv;
            $box17              = $canalPref;
            $box18              = $proveedor;
            $box19              = $frecCon;
            $box20              = $contactoEmpresas;
            $box21              = $atrImport;
            $box22              = $this->traking($db, $startDateFilterMonth, $endDateFilterMonth);
            $npsBan             = $this->cxIntelligence($request);
        }

        if ($this->_dbSelected  == 'customer_jetsmart') {
            $ces=true;
            $name = 'JetSmart';
            if ($db == 'adata_jet_via') {
                $ces=false;
                $aerolineas = $this->OrdenAerolineas($db, $startDateFilterMonth, $endDateFilterMonth);
                $brandAwareness = $this->BrandAwareness($db, $startDateFilterMonth, $endDateFilterMonth);
            }

            $dataCes            = $this->ces($db, $dateIni, $dateEndIndicatorPrincipal, 'ces', $datafilters);
            $dataNPSGraph       = $this->graphNps($db, $npsInDb, $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
            $dataCsatGraph      = $this->graphCsat($db, $csatInDb, $dateIni, $dateEnd,  $filterClient, 'two' ,$datafilters);
            $dataCesGraph       = $this->graphCes($db, date('m'), date('Y'), 'ces', $dateIni, $dateEnd,  $filterClient, 'two' ,$datafilters);
            $dataCbi            = $this->cbiResp($db, '', $dateIni, $dateEndIndicatorPrincipal);
            $graphCSATDrivers   = $this->GraphCSATDrivers($db, '', trim($request->survey), $csatInDb, $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters, $group);
            $dataisn            = $this->graphCbi($db, date('m'), date('Y'), 'cbi', $dateIni, $dateEnd, $datafilters, 'two');
            $welcome            = $this->welcome(substr($request->survey, 0, 3), $filterClient,$request->survey, $db);
            $performance        = $this->cardsPerformace($dataNps, $dataCsat, $dateEnd, $dateIni, $request->survey, $datafilters,  $dataCes, $dataCbi,$ces);
            $npsConsolidado     = $this->graphsStruct($dataisn, 12, 'cbi');
            $npsVid             = $this->cardNpsBanmedica($this->_nameClient, $dataNPSGraph); //NPS
            $csatJourney        = $this->cardNpsBanmedica($this->_nameClient , $dataCsatGraph, 'CSAT');//Csat
            $csatDrivers        = substr($db, 10, 3) == 'com' ?  $this->cardNpsBanmedica($this->_nameClient, $dataCesGraph, 'CES') : null; //Ces
            $wordCloud          = $this->CSATJourney($graphCSATDrivers);;
            $closedLoop         = null; 
            $detailGender       = substr($db, 10, 3) == 'via' ? $this->gapJetsmart($db, $request->survey,'csat', $dateIni, $dateEnd, $structGAPJetSmart, $datafilters): $this->GraphCSATAtributos($db, trim($request->survey), 'csat1',  $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters);
            $detailGeneration   = substr($db, 10, 3) == 'via' ? $this->detailStats($db, 'cbi', $npsInDb, $csatInDb, 'gene', $endDateFilterMonth, $startDateFilterMonth,  $filterClient,  $datafilters, $jetNamesGene) : $this->GraphCSATAtributos($db, trim($request->survey), 'csat2',  $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters);
            $datasStatsByTaps   = substr($db, 10, 3) != 'via' ? $this->GraphCSATAtributos($db, trim($request->survey), 'csat3',  $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters): null;
            $detailsProcedencia = substr($db, 10, 3) == 'via' ? $this->detailStats($db, 'cbi', $npsInDb, $csatInDb, 'laboral' , $endDateFilterMonth,$startDateFilterMonth, $filterClient, $datafilters, $jetNamesLab) : $this->GraphCSATAtributos($db, trim($request->survey), 'csat4',  $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters);
            $box14              = substr($db, 10, 3) == 'via' ? $this->detailStats($db, 'cbi', $npsInDb, $csatInDb, 'frec2' , $endDateFilterMonth,$startDateFilterMonth, $filterClient, $datafilters, $jetNamesFrecVuelo) : $this->GraphCSATAtributos($db, trim($request->survey), 'csat5',  $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters);
            $box15              = substr($db, 10, 3) != 'via' ? $this->GraphCSATAtributos($db, trim($request->survey), 'csat6',  $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters) : null;
            $box16              = substr($db, 10, 3) == 'vue' ? $this->GraphCSATAtributos($db, trim($request->survey), 'csat7',  $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters) : null;
            $box17              = substr($db, 10, 3) == 'com' ? $this->rankingTransvip($db, $datafilters, $dateIni, $startDateFilterMonth, 'opc_1', "Ingreso", 2, 4) : (substr($db, 10, 3) == 'vue' ? $this->rankingTransvip($db, $datafilters, $dateIni, $startDateFilterMonth, 'opc_1', "Motivo de Vuelo", 4, 4): null);
            $box18              = substr($db, 10, 3) == 'vue' ? $this->rankingTransvip($db, $datafilters, $dateIni, $startDateFilterMonth, 'sino1', "Inconveniente llegada", 2, 4) : null;
            $box19              = substr($db, 10, 3) == 'vue' ? $this->rankingInconvLlegada($db, $datafilters, $dateIni, $startDateFilterMonth, 'sino1', "Tipo Inconveniente", 2, 4) : null;
            $box20              = substr($db, 10, 3) == 'vue' ? $this->statsJetSmartResp($db, $npsInDb, $csatInDb, $dateIni, $dateEnd, $datafilters) : null;
            $box21              = $aerolineas;
            $box22              = $brandAwareness;
            $npsBan             = null;
            $cx                 = $this->cxIntelligence($request);
        }

        $filters = $this->filters($request, $jwt, $datafilters);
        $data = [
            'client' => ($name !== 'Mutual')? $this->_nameClient : $this->setNameClient('_nameClient'),
            'clients' => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients : '',

            'filters' => $filters['filters'],
            "indicators" => [
                $welcome,
                $performance,
                $npsConsolidado,
                $npsBan,
                $npsVid,
                $csatJourney,
                $csatDrivers,
                $cx,
                $wordCloud,
                $closedLoop,
                $detailGender,
                $detailGeneration,
                $datasStatsByTaps,
                $detailsProcedencia,
                $box14,
                $box15,
                $box16,
                $box17,
                $box18,
                $box19,
                $box20,
                $box21,
                $box22
            ]
        ];

        return [
            'datas'     => $data,
            'status'    => Response::HTTP_OK
        ];
    }

    private function setDetailsClient($client)
    {
        if ($client == 'VID001' || $client == 'BAN001') {
            //TO DO
            //WEB NO TIENE NICHO REVISAR
            $this->_dbSelected          = 'customer_banmedica';
            $this->_initialFilter       = 'all';
            $this->_fieldSelectInQuery  = 'nicho, sex, tramo, region';
            $this->_fieldSex            = 'sex';
            $this->_fieldSuc            = 'nomSuc';
            $this->_minNps              = 0;
            $this->_maxNps              = 6;
            $this->_minMediumNps        = 7;
            $this->_maxMediumNps        = 8;
            $this->_minMaxNps           = 9;
            $this->_maxMaxNps           = 10;
            $this->_minCsat             = 0;
            $this->_maxCsat             = 6;
            $this->_minMediumCsat       = 7;
            $this->_maxMediumCsat       = 8;
            $this->_minMaxCsat          = 9;
            $this->_maxMaxCsat          = 10;
            $this->_obsNps              = 'obs_nps';

            $this->_imageBan = 'https://customerscoops.com/assets/companies-images/bm_logo.svg';
            $this->_imageVid = 'https://customerscoops.com/assets/companies-images/vidatres_logo.svg';
            $this->_imageBanVid = 'https://customerscoops.com/assets/companies-images/logos_banmedicaVida3.svg';

            if ($client == 'VID001') {
                $this->_nameClient = 'Vida Tres';
            }
            if ($client == 'BAN001') {
                $this->_nameClient = 'Banmedica';
            }
            $this->_renderInfo = [];
            $this->ButFilterWeeks       = null;
        }

        if ($client == 'MUT001') {
            $this->_dbSelected          = 'customer_colmena';
            $this->_initialFilter       = 'one';
            $this->_fieldSelectInQuery  = 'sexo';
            $this->_fieldSex            = 'sexo';
            $this->_fieldSuc            = '';
            $this->_minNps              = 1;
            $this->_maxNps              = 4;
            $this->_minMediumNps        = 5;
            $this->_maxMediumNps        = 5;
            $this->_minMaxNps           = 6;
            $this->_maxMaxNps           = 7;
            $this->_minCsat             = 1;
            $this->_maxCsat             = 4;
            $this->_minMediumCsat       = 5;
            $this->_maxMediumCsat       = 5;
            $this->_minMaxCsat          = 6;
            $this->_maxMaxCsat          = 7;
            $this->_obsNps              = 'obs_csat';
            $this->_imageClient         = 'https://customerscoops.com/assets/companies-images/mutual_logo.png';
            //$this->_nameClient          = 'Mutual';
            $this->ButFilterWeeks       = [["text" => "Anual", "key" => "filterWeeks", "value" => ""], ["text" => "Semanal", "key" => "filterWeeks", "value" => "10"]];
            $this->_minCes              = 1;
            $this->_maxCes              = 4;
            $this->_minMediumCes        = 5;
            $this->_minMaxCes           = 6;
            $this->_maxMaxCes           = 7;
        }

        if ($client == 'DEM001') {
            $this->_dbSelected          = 'customer_demo';
            $this->_initialFilter       = 'one';
            $this->_fieldSelectInQuery  = 'sex';
            $this->_fieldSex            = 'sex';
            $this->_minNps              = 1;
            $this->_maxNps              = 4;
            $this->_minMediumNps        = 5;
            $this->_maxMediumNps        = 5;
            $this->_minMaxNps           = 6;
            $this->_maxMaxNps           = 7;
            $this->_minCsat             = 1;
            $this->_maxCsat             = 4;
            $this->_minMediumCsat       = 5;
            $this->_maxMediumCsat       = 5;
            $this->_minMaxCsat          = 6;
            $this->_maxMaxCsat          = 7;
            $this->_obsNps              = 'obs_nps';
            $this->_imageClient         = 'https://customerscoops.com/assets/companies-images/logo_cs.png';
            $this->_nameClient          = 'Demo';
            $this->ButFilterWeeks       = '';
        }

        if ($client == 'TRA001') {
            $this->_dbSelected          = 'customer_colmena';
            $this->_initialFilter       = 'one';
            $this->_fieldSelectInQuery  = 'sex';
            $this->_fieldSex            = 'sex';
            $this->_minNps              = 0;
            $this->_maxNps              = 6;
            $this->_minMediumNps        = 7;
            $this->_maxMediumNps        = 8;
            $this->_minMaxNps           = 9;
            $this->_maxMaxNps           = 10;
            $this->_minCsat             = 1;
            $this->_maxCsat             = 4;
            $this->_minMediumCsat       = 5;
            $this->_maxMediumCsat       = 5;
            $this->_minMaxCsat          = 6;
            $this->_maxMaxCsat          = 7;
            $this->_obsNps              = 'obs';
            $this->_imageClient         = 'https://customerscoops.com/assets/companies-images/logo_transvip.svg';
            $this->_nameClient          = 'Transvip';
            $this->ButFilterWeeks       = [["text" => "Anual", "key" => "filterWeeks", "value" => ""], ["text" => "Semanal", "key" => "filterWeeks", "value" => "10"]];
        }

        // JetSmart
        if ($client == 'JET001') {
           $this->_dbSelected          = 'customer_jetsmart';
           $this->_initialFilter       = 'one';
           $this->_fieldSelectInQuery  = 'gen';
           $this->_fieldSex            = 'gen';
           $this->_minNps              = 0;
           $this->_maxNps              = 6;
           $this->_minMediumNps        = 7;
           $this->_maxMediumNps        = 8;
           $this->_minMaxNps           = 9;
           $this->_maxMaxNps           = 10;
           $this->_minCsat             = 1;
           $this->_maxCsat             = 6;
           $this->_minMediumCsat       = 7;
           $this->_maxMediumCsat       = 8;
           $this->_minMaxCsat          = 9;
           $this->_maxMaxCsat          = 10;
           $this->_obsNps              = 'obs';
           $this->_imageClient         = 'https://customerscoops.com/assets/companies-images/JetSMART_logo.jpg';
           $this->_nameClient          = 'JetSmart';
           $this->ButFilterWeeks       = [["text" => "Anual", "key" => "filterWeeks", "value" => ""], ["text" => "Semanal", "key" => "filterWeeks", "value" => "10"]];
           $this->_minCes              = 1;
           $this->_maxCes              = 2;
           $this->_minMediumCes        = 3;
           $this->_minMaxCes           = 4;
           $this->_maxMaxCes           = 5;
        }
    }

    public function getInitialFilter(){
        return $this->_initialFilter;
    }

    public function getValueParams($params){
        return $this->$params;
    }
    public function setNameClient($value)
    {
        $this->_nameClient = $value;
    }

}