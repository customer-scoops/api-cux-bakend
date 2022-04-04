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
use Carbon\Carbon;
class Dashboard extends Generic
{
    private $_activeSurvey = 'banrel';

    //SE LE APLICAN CUANDO SE UNEN LAS TABLAS, SI SE FILTRAA POR SURVEY NO SE APLICAN %
    private $_porcentageBan         = 0.77;
    private $_porcentageVid         = 0.23;
    private $_high                  = 'ALTA';
    private $_medium                = 'MEDIA';
    private $_low                   = 'BAJA';
    private $_periodCxWord          = 2;
    private $expiresAtCache         = '';
    private $generalInfo            = '';
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
    private $_fieldSuc;
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
    private $_valueMinAnomalias = 0;
    private $_valueMaxAnomalias = 0;
    private $_valueMinAnomaliasText = -20;
    private $_valueMaxAnomaliasText = 30;
    private $_valueAnomaliasPorcentajeText = 30;

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

    private function getFielInDb($survey)
    {
        $npsInDb = 'nps';
        return $npsInDb;
    }

    private function contentfilter($data, $type)
    {
        $content = [];
        $count = count($data);

        foreach ($data as $key => $value) {
            $namefilters = $this->textsfilters($type . $value->$type);
            $content[($namefilters !== false) ? $namefilters : $value->$type] = $value->$type;
        }
        return ($content);
    }

    private function textsfilters($cod)
    {
        $arr =  [
            'region1' => 'Tarapacá',
            'region2' => 'Antofagasta',
            'region3' => 'Atacama',
            'region4' => 'Coquimbo',
            'region5' => 'Valparaíso',
            'region6' => "O'Higgins",
            'region7' => 'El Maule',
            'region8' => 'El Bío Bío',
            'region9' => 'La Araucanía',
            'region10' => 'Los Lagos',
            'region11' => 'Aysén',
            'region12' => 'Magallanes y Antártica Chilena',
            'region13' => 'Región Metropolitana de Santiago',
            'region14' => 'Los Ríos',
            'region15' => 'Arica y Parinacota',
            'region16' => 'Nuble',
            'sex1' => 'masculino',
            'sex2' => 'femenino'
        ];

        if (array_key_exists($cod, $arr)) {
            return $arr[$cod];
        }
        if (!array_key_exists($cod, $arr)) {
            return false;
        }
    }

    public function filters($request, $jwt)
    {
        $survey = $request->get('survey');
        $content = '';
        $regiones =         [];
        $genero =           [];
        $tramo =            [];
        $nicho =            [];
        $sucursal =         [];
        $web =              [];
        $macrosegmento =    [];
        $modAtencion =      [];
        $tipoCliente =      [];
        $tipoCanal =        [];
        $tipoAtencion =     [];
        $CenAtencionn =     [];
        $TipoClienteT =     [];
        $TipoServicio =     [];
        $CondServicio =     [];
        $Zona =             [];
        $Sentido =          [];
        $Reserva =          [];
        $CanalT =            [];
        $Convenio =         [];
        $db = 'adata_' . substr($survey, 0, 3) . '_' . substr($survey, 3, 6);
        $dbC = substr($survey, 3, 6);


        //BANMEDICA
        if ($this->_dbSelected  == 'customer_banmedica') {

            //REGION
            //echo $this->_dbSelected.$db."_start" ;
            $data = DB::select("SELECT DISTINCT(region) 
                                FROM $this->_dbSelected.".$db."_start
                                WHERE region != ''");
            $regiones = ['filter' => 'regiones', 'datas' => $this->contentfilter($data, 'region')];
            
            //TRAMO
            $data = DB::select("SELECT DISTINCT(tramo) 
                                FROM  $this->_dbSelected.".$db."_start
                                WHERE tramo != '#N/A' AND tramo != ''");
            $tramo = ['filter' => 'tramo', 'datas' => $this->contentfilter($data, 'tramo')];

            //NICHO
            $data = DB::select("SELECT DISTINCT(nicho) 
                                FROM  $this->_dbSelected.".$db."_start 
                                WHERE nicho != 'SN' and nicho != ''");
            $nicho = ['filter' => 'nicho', 'datas' => $this->contentfilter($data, 'nicho')];

            //GENERO
            $data = DB::select("SELECT  DISTINCT($this->_fieldSex)
                                FROM  $this->_dbSelected.".$db."_start
                                Where $this->_fieldSex != '#N/D' AND $this->_fieldSex !=''");
            $genero = ['filter' => 'genero', 'datas' => $this->contentfilter($data, $this->_fieldSex)];


            if ($dbC == 'ges' || $dbC == 'suc' || $dbC == 'con') {
                //SUCURSAL
                $data = DB::select("SELECT DISTINCT(nomSuc) 
                                    FROM  $this->_dbSelected.".$db."_start
                                    where nomSuc != ''
                                    ORDER BY nomSuc");
                $sucursal = ['filter' => 'sucursal', 'datas' => $this->contentfilter($data, 'nomSuc')];

                return ['filters' => [(object)$regiones, (object)$genero, (object)$tramo, (object)$nicho, (object)$sucursal], 'status' => Response::HTTP_OK];
            }

            if ($dbC == 'web'){
                //SITIOWEB
                $data = DB::select("SELECT DISTINCT(sitioWeb)
                                    FROM $this->_dbSelected." . $db . "_start
                                    where sitioWeb != ''");
                $web = ['filter' => 'web', 'datas' => $this->contentfilter($data, 'sitioWeb')];

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


        //MUTUAL
        if ($this->_dbSelected  == 'customer_colmena' && substr($survey, 0, 3) == 'mut'  && $survey != 'mutred') {
            if ($survey == "muthos" || $survey == "muturg" || $survey == "mutamb" || $survey == "mutimg" || $survey == "mutreh") {
                $db = 'MUT001_mutcon_resp';
                if ($request->client) {
                    $db = 'adata_' . trim(substr($request->client, 0, 3)) . '_' . trim(substr($request->client, 3, 6));
                    $dbC = substr($request->client, 3, 6);
                }
            }
            $filters = null;

            if ($dbC == 'be' || $dbC == 'ges') {
                $data = DB::select("SELECT DISTINCT(macroseg)
                                    FROM $this->_dbSelected.adata_mut_".$dbC."_start
                                    WHERE macroseg != '0' and macroseg != '9' and macroseg != '8'");

                $this->_fieldSelectInQuery = 'macroseg';

                $macrosegmento = ['filter' => 'Macrosegmento', 'datas' => $this->contentfilter($data, 'macroseg')];
            }

            if ($dbC == 'eri' || $dbC == 'cas') {
                $data = DB::select("SELECT DISTINCT(tatencion)
                                    FROM $this->_dbSelected.adata_mut_".$dbC."_start
                                    WHERE tatencion != '0' AND tatencion != 'NO APLICA'");

                $this->_fieldSelectInQuery = 'tatencion';

                $modAtencion = ['filter' => 'ModalidadAtencion', 'datas' => $this->contentfilter($data, 'tatencion')];

                return ['filters' => [(object)$modAtencion], 'status' => Response::HTTP_OK];
            }

            if ($dbC == 'ges') {
                $data = DB::select("SELECT DISTINCT(tipcliente)
                                    FROM $this->_dbSelected.adata_mut_" . $dbC . "_start ");

                $this->_fieldSelectInQuery = 'tipcliente';

                $tipoCliente = ['filter' => 'TipoCliente', 'datas' => $this->contentfilter($data, 'tipcliente')];
            }

            if ($dbC == 'ges') {
                $data = DB::select("SELECT DISTINCT(canal)
                                    FROM $this->_dbSelected.adata_mut_" . $dbC . "_start");
                $this->_fieldSelectInQuery = 'canal';

                $tipoCanal = ['filter' => 'Canal', 'datas' => $this->contentfilter($data, 'canal')];

                return ['filters' => [(object)$tipoCliente, (object)$macrosegmento, (object)$tipoCanal], 'status' => Response::HTTP_OK];
            }


            if ($dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh') {
                $data = DB::select("SELECT DISTINCT(tatencion)
                                FROM $this->_dbSelected.adata_mut_" . $dbC . "_start
                                where tatencion != '0'");

                $this->_fieldSelectInQuery = 'tatencion';

                $tipAtencion = ['filter' => 'TipoAtencion', 'datas' => $this->contentfilter($data, 'tatencion')];

            }

            if ($dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh') {
                $data = DB::select("SELECT DISTINCT(catencion)
                                FROM $this->_dbSelected.adata_mut_" . $dbC . "_start ");

                $this->_fieldSelectInQuery = 'catencion';

                $CenAtencion = ['filter' => 'CentroAtencion', 'datas' => $this->contentfilter($data, 'catencion')];

                return ['filters' => [(object)$tipAtencion, (object)$CenAtencion], 'status' => Response::HTTP_OK];
            }

            if ($dbC == 'hos') {
                $data = DB::select("SELECT DISTINCT(catencion)
                                FROM $this->_dbSelected.adata_mut_" . $dbC . "_start");
                $this->_fieldSelectInQuery = 'catencion';

                $CenAtencionn = ['filter' => 'CentroAtencion', 'datas' => $this->contentfilter($data, 'catencion')];

                return ['filters' => [(object)$CenAtencionn], 'status' => Response::HTTP_OK];
            }

            return ['filters' => [(object)$macrosegmento], 'status' => Response::HTTP_OK];
        }

        //TRANSVIP

        if ($this->_dbSelected  == 'customer_colmena' && substr($survey, 0, 3) == 'tra') {
            $data = DB::select("SELECT DISTINCT(tipocliente)
                                FROM $this->_dbSelected.adata_tra_via_start
                                WHERE tipocliente != '' ");

            $TipoClienteT = ['filter' => 'tipoCliente', 'datas' => $this->contentfilter($data, 'tipocliente')];


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

            return ['filters' => [(object)$TipoClienteT, (object)$TipoServicio, (object)$CondServicio, (object)$Sentido, (object)$Zona, (object)$Reserva, (object)$CanalT, (object)$Convenio], 'status' => Response::HTTP_OK];
        }
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

        if($survey != 'travia'){
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
        if ($value)
            return $value;
        $dataMatriz = $this->matriz($request);
        if ($dataMatriz['datas'] === null) {
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
        if ($dataMatriz['datas']->cx->gainpoint == null) {
            $gainPoint =  $this->_anomaliasGain;
        }
        if ($dataMatriz['datas']->cx->painpoint != null) {
            $painPoint = array_merge($dataMatriz['datas']->cx->painpoint, $this->_anomaliasPain);
        }
        if ($dataMatriz['datas']->cx->painpoint == null) {
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
        //array_push($resp,$data);
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
        //$data = DB::select("SELECT COUNT(*) as ticketCreated, COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, COUNT(if(B.estado_close = 2, B.id, NULL)) as ticketPending, COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL)) as ticketInProgres FROM $db as A INNER JOIN ".$db."_start as B ON (A.token = B.token) WHERE B.fechacarga BETWEEN '2021-12-01' AND '2021-12-31' AND p1 IN (0,1,2,3,4,5,6)");
        $closedRate = 0;
        //var_dump($data[0]->ticketCreated);
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
                            WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps!= 99");
        };

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

    public function generalInfo($request, $jwt){
        $indicators = new Suite($this->_jwt);
        $data = [];
        $surveys = $indicators->getSurvey($request, $jwt);
        //print_r($surveys);
        if ($surveys['status'] == 200) {
            foreach ($surveys['datas'] as $key => $value) {
               
                if ($value['base'] != 'mutred'){
                    $db = 'adata_'.substr($value['base'],0,3).'_'.substr($value['base'],3,6);
                    $db2 = $this->primaryTable($db);
                    $npsInDb = 'nps';
                    $csatInDb = 'csat';
                    $cesInDb = 'ces';
                    $infoNps = $this->infoNps($db,date('m'),date('Y'),$npsInDb,$this->_initialFilter); 
                    $otherGraph = [$this->infoCsat($db,date('m'),date('Y'), $csatInDb,$this->_initialFilter)];
                    
                    if(substr($value['base'],0,3) == 'mut'){
                        $otherGraph = [$this->infoCsat($db,date('m'),date('Y'), $csatInDb,$this->_initialFilter), $this->ces($db,$this->_initialFilter,date('m'),date('Y'), $cesInDb)];
                    } 

                    if (substr($value['base'],0,3) == 'tra'){
                        $db = 'adata_tra_via';
                        $datas = $this->npsPreviousPeriod('adata_tra_via',date('m'),date('Y'),'csat','' );
                        $isn =  [
                            "name"          => "INS",
                            "value"         => $datas['insAct'],
                            "percentage"    => $datas['insAct']-$datas['ins'],

                        ];
                    }
                    
                    if (substr($value['base'],0,3) == 'jet'){
                        $infoNps = $this->cbiResp($db, '', date('Y-m-d'),date('Y-m-01'));
                        $otherGraph = [$this->infoNps($db,date('m'),date('Y'),$npsInDb,$this->_initialFilter) ,$this->infoCsat($db,date('m'),date('Y'), $csatInDb,$this->_initialFilter),$this->ces($db,$this->_initialFilter,date('m'),date('Y'), $cesInDb)];
                    }

                    //print_r($otherGraph);
                    $data[] = [
                        'client'        => $this->_nameClient, 'clients'  => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: null,
                        "title"         => ucwords(strtolower($value['name'])),
                        "identifier"    => $value['base'],
                        "nps"           => $infoNps,
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
    
    //OKK

    private function infoJorneyMaps($db, $dateIni, $dateEnd, $survey, $filter){
        $db2 = $this->primaryTable($db);
        
        $endCsat = $this->getEndCsat($survey);
       
        if($endCsat){
            if($filter == 'all'){
                $fieldBd = $this->getFielInDbCsat($survey);
                $fieldBd2 = $this->getFielInDbCsat($survey);
                //$fieldBd2 = ($db == 'adata_ban_web')?'csat':$fieldBd;
                $query = "";
                $query2 = "";
                for ($i=1; $i <= $endCsat; $i++) {
                    if($i != $endCsat){
                        $query .= " AVG($fieldBd$i)*$this->_porcentageBan AS csat$i, ";
                        $query2 .= " AVG($fieldBd2$i)*$this->_porcentageVid AS csat$i, ";
                    }
                    if($i == $endCsat){
                        $query .= " AVG($fieldBd$i)*$this->_porcentageBan AS csat$i ";
                        $query2 .= " AVG($fieldBd2$i)*$this->_porcentageVid AS csat$i ";
                    }
                }
                $data = DB::select("SELECT $query FROM (SELECT $query,date_survey
                                    FROM $this->_dbSelected.$db as A
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' 
                                    UNION
                                    SELECT $query2,date_survey
                                    FROM $this->_dbSelected.$db2 as A
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2'
                                    ORDER BY date_survey) AS A");
            }
            if($filter != 'all'){
                $fieldBd = $this->getFielInDbCsat($survey);
                $query = "";
                for ($i=1; $i <= $endCsat; $i++) {
                    if($i != $endCsat){
                        $query .= " AVG($fieldBd$i) AS csat$i, ";
                    }
                    if($i == $endCsat){
                        $query .= " AVG($fieldBd$i) AS csat$i ";
                    }
                    
                }
                $data = DB::select("SELECT $query,date_survey
                                    FROM $this->dbSelected.$db as A
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' 
                                    ORDER BY date_survey");
            }
            $journey =[];
            $indicator = new Suite($this->_jwt);
           
            foreach ($data[0] as $key => $value) {
             
                $journey[] = [
                    'text'  => $indicator->getInformationDriver($survey.'_'.$key),
                    'values' => round($value,2),
                    ];
            }
    
            return $journey;
        }
        if(!$endCsat){
            return null;
        }
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
            "mutamb" => "2",
            "mutbe"  => "5",
            "mutcas" => "2",
            "muteri" => "6",
            "muthos" => "2",
            "mutimg" => "2",
            "mutreh" => "2",
            "muturg" => "2",
            //demo
            "demdem" => "8",
            //transvip
            "travia" => "11",
            //JetSmart
            "jetvia" => "8",
            "jetcom" => "6",
        ];
        if (array_key_exists($survey, $datas)) {
            return $datas[$survey];
        }
        if (!array_key_exists($survey, $datas)) {
            return false;
        }
    }


    //---------------------------------------------------------------------------------------------------------------------
    //OKK
    private function npsPreviousPeriod($table, $mes, $annio, $indicador, $datafilters)
    {
        $datafilters = str_replace(' AND date_survey between date_sub(NOW(), interval 9 week) and NOW()', '', $datafilters);

        $monthAnt = $mes - 1;
        if ($monthAnt == 0) {
            $monthAnt = 12;
            $annio = $annio - 1;
        }

        $table2 = $this->primaryTable($table);

        if ($this->_dbSelected == 'customer_colmena' && substr($table, 6, 3) == 'tra') {
            if (substr($datafilters, 30, 3) == 'NOW') {
                $datafilters = '';
            }

            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN csat BETWEEN 6 AND 7 THEN 1 END) -
            COUNT(CASE WHEN csat BETWEEN 1 AND 4 THEN 1 END)) /
            (COUNT(CASE WHEN csat != 99 THEN csat END)) * 100),1) AS INS,
            ROUND(((COUNT(CASE WHEN nps BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN nps BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS
            FROM $this->_dbSelected.$table as a
            left join $this->_dbSelected." . $table . "_start as b
            on a.token = b.token
            WHERE a.mes = $monthAnt AND a.annio = $annio $datafilters");

            $data2 = DB::select("SELECT COUNT(CASE WHEN a.csat!=99 THEN 1 END) as Total, 
            ROUND(((COUNT(CASE WHEN a.csat BETWEEN 6 AND 7 THEN 1 END) - COUNT(CASE WHEN a.csat BETWEEN 1 AND 4 THEN 1 END)) / (COUNT(CASE WHEN a.csat!=99 THEN 1 END)) * 100),1) AS INS,
            ROUND(((COUNT(CASE WHEN nps BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - COUNT(CASE WHEN nps BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS,
            a.mes, a.annio, date_survey, WEEK(date_survey) AS week
            from $this->_dbSelected.$table as a
            left join $this->_dbSelected." . $table . "_start as b
            on a.token = b.token
            where a.mes= $mes and a.annio = $annio $datafilters
            GROUP by a.mes, a.annio
            ORDER by a.date_survey ASC");

            return ['ins' => $data[0]->INS, 'nps' => $data[0]->NPS, 'insAct' => $data2[0]->INS, 'npsAct' => $data2[0]->NPS];
        }


        if ($this->_dbSelected == 'customer_colmena' && substr($table, 6, 3) == 'mut' || substr($table, 0, 3) == 'MUT') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS
            FROM $this->_dbSelected.$table as a
            left join $this->_dbSelected." . $table . "_start as b
            on a.token = b.token
            WHERE a.mes = $monthAnt AND a.annio = $annio $datafilters");

            return $data[0]->NPS;
        }
        if ($this->_dbSelected == 'customer_demo') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS
            FROM $this->_dbSelected.$table
            WHERE mes = $monthAnt AND annio = $annio $datafilters");
            return $data[0]->NPS;
        }

        if ($this->_dbSelected == 'customer_banmedica') {
            $data = DB::select("SELECT SUM(NPS) AS NPS FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageBan AS NPS
            FROM $this->_dbSelected.$table as a
            left join $this->_dbSelected.".$table."_start AS b
            ON a.token = b.token
            WHERE a.mes = $monthAnt AND a.annio = $annio $datafilters
            UNION
            SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageVid AS NPS
            FROM  $this->_dbSelected.$table2 as a
            left join $this->_dbSelected." . $table2 . "_start AS b
            ON a.token = b.token
            WHERE a.mes = $monthAnt AND a.annio = $annio $datafilters) AS A");
            return round($data[0]->NPS);
        }
    }

    //Función CBI periodo previo para JetSmart

    private function cbiPreviousPeriod($table, $mes, $annio, $indicador, $datafilters)
    {
        $datafilters = str_replace(' AND date_survey between date_sub(NOW(), interval 9 week) and NOW()', '', $datafilters);
        
        $monthAnt = $mes - 1;
        if ($monthAnt == 0) {
            $monthAnt = 12;
            $annio = $annio - 1;
        }

        //$table2 = $this->primaryTable($table);

        if ($this->_dbSelected == 'customer_jetsmart') {

            $data = DB::select("SELECT ROUND((COUNT(CASE WHEN $indicador BETWEEN 4 AND 5 THEN 1 END)  /
            (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100)) AS CBI
            FROM $this->_dbSelected.$table 
            WHERE mes = $monthAnt AND annio = $annio $datafilters");
            return $data;
        }

    }

    //Función promedio 6 mese CBI

    private function AVGLast6MonthCBI($table,$dateIni,$dateEnd,$indicador){
        // echo "SELECT sum(CBI) as total from (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 4 AND 5 THEN 1 END)) /
        // (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100)) AS CBI, mes, annio
        // FROM $this->_dbSelected.$table
        // WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' 
        // group by annio, mes) as a";

            $data = DB::select("SELECT sum(CBI) as total from (SELECT 
                    ROUND(((COUNT(CASE WHEN $indicador BETWEEN 4 AND  5 THEN 1 END)) 
                    /(COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100)) AS CBI, mes, annio
                    FROM $this->_dbSelected.$table
                    WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' 
                    group by annio, mes) as a");

        return (int)($data[0]->total / 6);
    }

    private function AVGLast6MonthNPS($table,$table2,$dateIni,$dateEnd,$indicador, $filter){
      
        if($filter == 'all'){              

            $data = DB::select("SELECT sum(NPSS) as total from (SELECT round(SUM(NPS)) AS NPSS FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador  BETWEEN 9 AND 10 THEN 1 END) -
                                COUNT(CASE WHEN $indicador  BETWEEN 0 AND 6 THEN 1 END)) /
                                (COUNT($indicador ) - COUNT(CASE WHEN $indicador =99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS, mes, annio
                                FROM $this->_dbSelected.$table
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                                group by annio, mes
                                union
                                SELECT ROUND(((COUNT(CASE WHEN $indicador  BETWEEN 9 AND 10 THEN 1 END) -
                                COUNT(CASE WHEN $indicador  BETWEEN 0 AND 6 THEN 1 END)) /
                                (COUNT($indicador ) - COUNT(CASE WHEN $indicador =99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS, mes, annio
                                FROM $this->_dbSelected.$table2
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                                group by annio, mes) AS A
                                group by annio, mes) as b");
        }

        if ($filter != 'all') {
            $data = DB::select("SELECT sum(NPS) as total from (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1) AS NPS, mes, annio
                                FROM $this->_dbSelected.$table
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' 
                                group by annio, mes) as a");
        }
        return (int)($data[0]->total / 6);
    }


    private function primaryTable($table)
    {
        $db = explode('_', $table);
        $indicatordb = ($db[1] == 'vid') ? 'ban' : 'vid';
        return $table2 = $db[0] . '_' . $indicatordb . '_' . $db[2];
    }

    //OKK
    private function resumenNps($table, $mes, $annio, $indicador, $filter, $datafilters = null)
    {
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
                		WHERE a.mes = $mes AND a.annio = $annio $datafilters
                		GROUP BY a.mes, a.annio
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
        				WHERE a.mes = $mes AND a.annio = $annio $datafilters
        				GROUP BY a.mes, a.annio
        				) AS A ";

            $data = DB::select($query);
        }

        if ($filter != 'all') {
            $data = DB::select("SELECT count(*) as total, 
            ((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)) as detractor, 
            ((count(if($indicador = 9 or  $indicador =10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)) as promotor,
    		((count(if($indicador = 7 OR $indicador = 8, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)) as neutral,
            AVG($indicador) as promedio,
            ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                              (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS,  $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$table as a
            LEFT JOIN $this->_dbSelected." . $table . "_start as b
            on a.token = b.token
            WHERE a.mes = $mes AND a.annio = $annio $datafilters
            GROUP BY a.mes, a.annio
            ORDER BY date_survey ASC");
        }


        if (($data == null) || $data[0]->total == null || $data[0]->total == 0) {
            $npsActive = (isset($data[0]->NPS)) ? $data[0]->NPS : 0;
            $npsPreviousPeriod = $this->npsPreviousPeriod($table, $mes, $annio, $indicador, $datafilters);
            return [
                "name"          => "nps",
                "value"         => 'N/A',
                "promotors"     => 0,
                "neutrals"      => 0,
                "detractors"    => 0,
                "percentage"    => $npsActive - $npsPreviousPeriod,
                "smAvg"         => $this->AVGLast6MonthNPS($table, $table2, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $indicador, $filter)
            ];
        }

        if ($data[0]->total != 0) {
            $npsActive = (isset($data[0]->NPS)) ? $data[0]->NPS : 0;
            $npsPreviousPeriod = $this->npsPreviousPeriod($table, $mes, $annio, $indicador, $datafilters);
            if ($npsPreviousPeriod  === null) {
                $npsPreviousPeriod = 0;
            }
            if (substr($table, 6, 3) == 'tra')
                $npsPreviousPeriod = $npsPreviousPeriod['nps'];
            if(substr($table, 6, 3) == 'jet'){
                return [
                    "name"              => "nps",
                    "value"             => round($npsActive),
                    "percentage"        => $npsActive - round($npsPreviousPeriod),
                    "smAvg"             => $this->AVGLast6MonthNPS($table, $table2, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $indicador, $filter),
                    "percentageNPSAC"   => $npsActive,
                    'NPSPReV'           => $npsPreviousPeriod,
                    'mes'               => $mes,
                    'annio'             => $annio,
                ];
            }
            return [
                "name"              => "nps",
                "value"             => round($npsActive),
                "promotors"         => round($data[0]->promotor),
                "neutrals"          => round($data[0]->neutral),
                "detractors"        => round($data[0]->detractor),
                "percentage"        => $npsActive - round($npsPreviousPeriod),
                "smAvg"             => $this->AVGLast6MonthNPS($table, $table2, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $indicador, $filter),
                "percentageNPSAC"   => $npsActive,
                'NPSPReV'           => $npsPreviousPeriod,
                'mes'               => $mes,
                'annio'             => $annio,
            ];
        }
    }

    //OKK
    private function infoNps($table, $mes, $annio, $indicador, $filter)
    {
        $generalDataNps             = $this->resumenNps($table, $mes, $annio, $indicador, $filter);
        $generalDataNps['graph']    = $this->graphNps($table, $mes, $annio, $indicador, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $filter, 'one');

        return $generalDataNps;
    }

    //OKK
    private function graphNps($table, $mes, $annio, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    {
        $table2 = $this->primaryTable($table);
        $group2 = "mes, annio";
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

        $graphNPS   = [];
        
        if ($filter != 'all') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1) AS NPS, 
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
					            count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
					            count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,              
                                count(*) as total, 
                                ((count(if($indicador < 7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as detractor, 
                                ((count(if($indicador = 9 OR $indicador =10, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as promotor, 
                                ((count(if($indicador <= 8 AND $indicador >=7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as neutral,              
                                a.mes, a.annio, WEEK(date_survey) AS week,$this->_fieldSelectInQuery  
                                FROM $this->_dbSelected.$table as a
                                INNER JOIN $this->_dbSelected." . $table . "_start as b ON a.token = b.token 
                                WHERE  $where $datafilters 
                                GROUP BY $group2
                                ORDER BY date_survey ASC");
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
                LEFT JOIN $this->_dbSelected." . $table . "_start as b ON a.token = b.token 
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
                GROUP BY $group
                ) AS A GROUP BY $group2
                ORDER BY date_survey ASC");
            //}
        }

        if ($data) {
            if ($data[0]->total === null) {
                foreach ($data as $key => $value) {
                    if ($struct != 'one') {
                        $graphNPS[] = [
                            'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                            'values' => [
                                "promoters"     => round($value->promotor),
                                "neutrals"      => 100 - (round($value->promotor) + round($value->detractor)),
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
                            'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                            'values' => [
                                "promoters"     => round($value->promotor),
                                "neutrals"      => 100 - (round($value->promotor) + round($value->detractor)),
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
                        'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (0)',
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
                "text" => "INS ",
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

    private function graphCsatMutual($table, $mes, $annio, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    {
        if ($group !== null) {
            //$where = " date_survey between date_sub(NOW(), interval 9 week) and NOW() and WEEK(date_survey) != 0 ";
            $where = $datafilters;
            $datafilters = '';
        }

        if ($group === null) {
            $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
            $group = " a.mes, a.annio ";
        }


        if ($datafilters)
            $datafilters = " AND $datafilters";

        $graphCsatM  = [];

        if ($filter != 'all') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador BETWEEN $this->_minCsat AND $this->_maxCsat THEN 1 END)) / 
                                              (COUNT(CASE WHEN $indicador!=99 THEN 1 END)) * 100),1) AS CSAT, 
                                count(if($indicador < $this->_minMediumCsat, $indicador, NULL)) as Cdet,
					            count(if($indicador = $this->_minMaxCsat AND $indicador = $this->_maxMaxCsat, $indicador, NULL)) as Cpro,
					            count(if($indicador=$this->_maxMediumCsat, $indicador, NULL)) as Cneu,              
                                count(*) as total, 
                                ((count(if($indicador < $this->_minMediumCsat, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as detractor, 
                                ((count(if($indicador = $this->_minMaxCsat OR $indicador = $this->_maxMaxCsat, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as promotor, 
                                ((count(if($indicador=$this->_maxMediumCsat, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as neutral,              
                                              a.mes, a.annio, WEEK(date_survey) AS week,$this->_fieldSelectInQuery  
                FROM $this->_dbSelected.$table as a
                INNER JOIN $this->_dbSelected." . $table . "_start as b ON a.token = b.token 
                WHERE  $where $datafilters 
                GROUP BY $group
                ORDER BY date_survey ASC");
        }

        foreach ($data as $key => $value) {
            if ($struct != 'one') {
                $graphCsatM[] = [
                    'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                    'values' => [
                        "promoters"     => round($value->promotor),
                        "neutrals"      => round($value->neutral),
                        "detractors"    => round($value->detractor),
                        "csat"          => round($value->CSAT)
                    ],
                ];
            }
        }
        return $graphCsatM;
    }   

    //OKK
    private function csatPreviousPeriod($table, $mes, $annio, $indicador, $filter, $datafilters)
    {
        // if ($datafilters)
        // $datafilters = " AND $datafilters";

        $monthAnt       = $mes - 1;
        if ($monthAnt == 0) {
            $monthAnt   = 12;
            $annio      = $annio - 1;
        }

        if ($filter != 'all') {
            if (substr($table, 6, 3) == 'mut' || substr($table, 0, 3) == 'MUT') {
                $data = DB::select("SELECT ((COUNT(CASE WHEN $indicador  BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)) -
                    (COUNT(CASE WHEN $indicador  BETWEEN $this->_minCsat AND $this->_maxCsat THEN $indicador  END)))*100/count(CASE WHEN $indicador  != 99 THEN csat END) as CSAT
                    FROM $this->_dbSelected.$table
                    WHERE mes = $monthAnt AND annio = $annio");
            }

            if (substr($table, 6, 3) != 'mut') {
                $data = DB::select("SELECT ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as CSAT
                FROM $this->_dbSelected.$table
                WHERE mes = $monthAnt AND annio = $annio");
            }
        }

        if ($filter == 'all') {
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            $data = DB::select("SELECT SUM(CSAT) AS CSAT 
            FROM (SELECT ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageBan as CSAT
            from $this->_dbSelected.$table as a
            left join $this->_dbSelected." . $table . "_start as b
            on a.token = b.token
            WHERE a.mes = $monthAnt AND a.annio = $annio  $datafilters
            UNION 
            SELECT ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageVid as CSAT
            from $this->_dbSelected.$table2 as a
            left join $this->_dbSelected.".$table2."_start as b
            on a.token = b.token
            WHERE a.mes = $monthAnt AND a.annio = $annio $datafilters ) AS A");

        }

        return $data[0]->CSAT;
    }



    //OKK
    private function resumenCsat($table, $mes, $annio, $indicador, $filter, $datafilters = null)
    {
        $table2 = '';
        if ($datafilters)
        $datafilters = " AND $datafilters";

        if ($filter != 'all') {
            if (substr($table, 6, 3) != 'mut') {
                $data = DB::select("SELECT count(*) as total,
                    ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as csat, 
                    $this->_fieldSelectInQuery
                    FROM $this->_dbSelected.$table as a
                    INNER JOIN $this->_dbSelected." . $table . "_start as b  ON a.token  =  b.token 
                    WHERE a.mes = $mes AND a.annio = $annio $datafilters
                    GROUP BY a.mes, a.annio");
            }

            if (substr($table, 6, 3) == 'mut') {

                $data = DB::select("SELECT count(*) as total,
                    ((COUNT(CASE WHEN $indicador  BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador  END))-
                    (COUNT(CASE WHEN $indicador  BETWEEN $this->_minCsat AND $this->_maxCsat THEN $indicador  END)))*100/count(CASE WHEN $indicador  != 99 THEN csat END) as csat, 
                    $this->_fieldSelectInQuery
                    FROM $this->_dbSelected.$table as a
                    INNER JOIN $this->_dbSelected." . $table . "_start as b ON a.token = b.token
                    WHERE a.mes =$mes and a.annio = $annio");
            }
        }

        if ($filter == 'all') {
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            //echo $datafilters; 
            $data = DB::select("SELECT SUM(total) AS total, SUM(csat) AS csat, $this->_fieldSelectInQuery
            FROM (  SELECT count(*) as total, date_survey, a.mes, a.annio,
                     ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageBan as csat, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$table as a
            INNER JOIN $this->_dbSelected.".$table."_start as b  ON a.token  =  b.token 
            WHERE a.mes = $mes AND a.annio = $annio  $datafilters
            GROUP BY a.mes, a.annio
            UNION
            SELECT count(*) as total, date_survey, a.mes, a.annio,
            ((COUNT(CASE WHEN $indicador2 BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador2 END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageVid as csat, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$table2 as a
            INNER JOIN $this->_dbSelected.".$table2."_start as b  ON a.token  =  b.token 
            WHERE a.mes = $mes AND a.annio = $annio  $datafilters
            GROUP BY a.mes, a.annio) AS A ");
        }

        $csatPreviousPeriod = $this->csatPreviousPeriod($table, $mes, $annio, $indicador, $filter,  $datafilters);

        $csatActive = 0;

        if (($data == null) || $data[0]->total === null) {

            $csatActive =  $csatActive;
            return [
                "name"          => "csat",
                "value"         => 'N/A',
                "percentage"    => $csatActive - $csatPreviousPeriod,
                //"smAvg"         => $csatActive-$csatPreviousPeriod,
                "smAvg"         => 0,

            ];
        }

        if ($data[0]->total != null) {
            $csatActive = $data[0]->csat;
            return [
                "name"          => "csat",
                "value"         => ROUND($data[0]->csat),
                "percentage"    => ROUND($data[0]->csat) - ROUND($csatPreviousPeriod),
                "smAvg"         => 0,

            ];
        }
    }

    private function infoCsat($table, $mes, $annio, $indicador)
    {
        $generalDataCsat            = $this->resumenCsat($table, $mes, $annio, $indicador, $this->_initialFilter);
        $generalDataCsat['graph']   = $this->graphCsat($table, $mes, $annio, $indicador, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $this->_initialFilter, 'one');
        return $generalDataCsat;
    }


    private function graphCsat($table, $mes, $annio, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null)
    {
        if ($datafilters)
            $datafilters = " AND $datafilters";
        //echo 'Ini: '.$dateIni.'End: '.$dateEnd;
        $graphCSAT = array();
        if ($filter != 'all') {
            if (substr($table, 6, 3) != 'mut') {
                $data = DB::select("SELECT COUNT(if( $indicador between $this->_minMaxCsat and $this->_maxMaxCsat, $indicador, NULL))/COUNT(CASE WHEN $indicador != 99 THEN $indicador END)*100 AS csat, 
                                    a.mes, a.annio, date_survey, $this->_fieldSelectInQuery 
                                    FROM $this->_dbSelected.$table as a
                                    INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b. token 
                                    WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters
                                    GROUP BY a.mes
                                    ORDER BY date_survey asc");
            }

            if (substr($table, 6, 3) == 'mut') {
                $data = DB::select("SELECT ((COUNT(CASE WHEN $indicador  BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador  END))-
                                    (COUNT(CASE WHEN $indicador  BETWEEN $this->_minCsat AND $this->_maxCsat THEN $indicador  END)))*100/count(CASE WHEN $indicador  != 99 THEN csat END) as csat, 
                                    a.mes, a.annio, date_survey, $this->_fieldSelectInQuery 
                                    FROM $this->_dbSelected.$table as a
                                    INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b. token 
                                    WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters
                                    GROUP BY a.mes
                                    ORDER BY date_survey asc");
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
                GROUP BY a.mes
                UNION
                SELECT ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END))*0.23 AS csat,
                a.mes, a.annio, date_survey, $this->_fieldSelectInQuery
                FROM $this->_dbSelected.$table2 as a
                INNER JOIN $this->_dbSelected." . $table2 . "_start as b on a.token = b. token
                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' and  $indicador != 99  $datafilters
                GROUP BY a.mes
                ) AS A
                GROUP BY mes
                ORDER BY date_survey ASC ");
        }

        if (!empty($data)) {
            foreach ($data as $key => $value) {
                // echo '---'.$value->csat;
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

    private function closedloopTransvip($datafilters, $dateEnd, $dateIni)
    {
        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";
        $data = DB::select("SELECT count(case when estado_close = 0 then 1 end) as created,
                    count(case when estado_close = 1 then 1 end) as close, date_survey, b.mes, b.annio
                    from customer_colmena.adata_tra_via_start as a
                    left join customer_colmena.adata_tra_via as b
                    on a.token = b.token
                    where date_survey BETWEEN '$dateIni' AND'$dateEnd' and etapaencuesta = 'P2' $datafilters
                    GROUP by  b.mes, b.annio
                    order by  b.annio, b.mes");



        foreach ($data as $key => $value) {
            $closedLoopTransvip[] = [
                'xLegend'   => (string)$value->mes . '-' . $value->annio,
                'values'    => [
                    "create"    => (int)$value->created,
                    "close"     => (int)$value->close,
                ],
            ];
        }

        return  $closedLoopTransvip;
    }

    // Grph CBI para jestmart
    private function graphCbi($table, $mes, $annio, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    {
        if ($datafilters)
        $datafilters = " AND $datafilters";
    //echo 'Ini: '.$dateIni.'End: '.$dateEnd;
    $graphCBI = array();

 
    $data = DB::select("SELECT COUNT(if( $indicador between 4 and 5, $indicador, NULL))/COUNT(CASE WHEN $indicador != 99 THEN $indicador END)*100 AS cbi, 
            a.mes, a.annio, date_survey, $this->_fieldSelectInQuery 
            FROM $this->_dbSelected.$table as a
            INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b. token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters
            GROUP BY a.mes
            ORDER BY date_survey asc");
      
    if (!empty($data)) {
        foreach ($data as $key => $value) {
            // echo '---'.$value->csat;
            if ($struct != 'one') {
                $graphCBI[] = [
                    'xLegend'  => (string)$value->mes . '-' . $value->annio,
                    'values'   => [
                        'cbi' => (string)ROUND($value->cbi)
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

    private function cbiResp($db, $datafilters, $dateIni, $dateEnd)
    {
        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";
        
        $data = DB::select("SELECT count(case when cbi between 4 and 5 then 1 end)*100/count(case when cbi != 99 then 1 end) as cbi,
            count(case when cbi != 99 then 1 end) as Total, a.mes, a.annio, date_survey 
            from $this->_dbSelected.$db as a
            left join $this->_dbSelected." . $db . "_start as b 
            on a.token = b.token 
            WHERE etapaencuesta = 'P2' and date_survey BETWEEN '$dateEnd' AND'$dateIni' $datafilters
            group by  mes, annio
            order by annio, mes");

        if(substr($db,6,3 == 'tra')){
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
            return $cbiResp;
        }

        if(substr($db,6,3) == 'jet'){
           
            $monthAct = date('m');
            $annioAct = date('Y');
            
            $cbiSmAvg = $this->AVGLast6MonthCBI($db, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), 'cbi', $datafilters);
            $cbiActive = DB::select("SELECT ROUND((COUNT(CASE WHEN cbi BETWEEN 4 AND 5 THEN 1 END)  /
                        (COUNT(CASE WHEN cbi != 99 THEN 'cbi' END)) * 100)) AS CBI
                        FROM $this->_dbSelected.$db 
                        WHERE mes = $monthAct AND annio = $annioAct $datafilters");
            
            $cbiPreviousPeriod = $this->cbiPreviousPeriod($db, date('m'), date('Y'), 'cbi', $datafilters);
            $generalDataCbi = [                 
                "name"=> "cbi", //Ver después como hacemos
                "value"=> (int)$cbiActive[0]->CBI,
                "percentage"=> $cbiActive[0]->CBI - $cbiPreviousPeriod[0]->CBI,                 
                "smAvg"=> $cbiSmAvg,
            ];
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


    private function globales($db, $mes, $annio, $indicatorBD, $indicatorName, $indic1, $indic2, $height, $datafilters)
    {
        if (substr($datafilters, 30, 3) == 'NOW') {
            $datafilters = '';
        }

        if ($datafilters)
            $datafilters = " AND $datafilters";

        $queryTra = "SELECT DISTINCT(b.$indicatorBD) as $indicatorName, 
                    count(case when nps != 99 then 1 end) as Total, 
                    round(((count(case when csat between 6 and 7 then 1 end) - count(case when csat between 1 and 5 then 1 end))*100)/count(case when csat != 99 then 1 end)) as $indic2,
                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as nps,
                    count(case when $indic1 between 4 and 5 then 1 end)*100/count(case when $indic1 != 99 then 1 end) as $indic1,
                    a.mes, a.annio 
                    FROM $this->_dbSelected.$db as a
                    left join $this->_dbSelected." . $db . "_start as b
                    on a.token = b.token 
                    where a.mes = '$mes' and a.annio = '$annio' and b.$indicatorBD != ''  $datafilters
                    GROUP by $indicatorName
                    order by $indicatorName";

        $data = DB::select($queryTra);
        $lastSentido  = '';
        $values = [];
        $meses = [];

        for ($i = -11; $i < 1; $i++) {
            array_push(
                $meses,
                (int)date("m", mktime(0, 0, 0, date("m") + $i, date("d"), date("Y")))
            );
        }

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
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 3][0]['period' . $dato->mes]  = round($dato->nps) . '%';
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 3][0]['rowSpan']  = ['cells' => 3, 'key' => "Respuestas"];
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 3][0]['textColor']  = ['color' => $this->setTextAnomalias($dato->nps), 'key' => 'period' . $dato->mes];
                        $this->setAnomalias($dato->nps, $lastSentido);
                    }
                }
                if ($value->$indic2 != null) { //INS
                    if ($lastSentido == $dato->$indicatorName) {
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 2][0]['period' . $dato->mes] = round($dato->$indic2) . '%';
                    }
                }
                if ($value->$indic1 != null) { //CBI
                    if ($lastSentido == $dato->$indicatorName) {
                        $values[$lastSentido][sizeof($values[$lastSentido]) - 1][0]['period' . $dato->mes] = round($dato->$indic1) . '%';
                    }
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
            $indicatorName => $indicatorName,
            'Indicator' => 'Indicadores',
        ];
      
        $colums['period'.$meses[11]]=$numberToMonth[$meses[11]];
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
    private function NpsIsnTransvip($table,$mes,$annio,$indicadorNPS, $indicadorINS,$datafilters, $group,$dateIni, $dateEnd){
        if($group != null){
            $where = $datafilters;
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

        if ($datafilters)
            $datafilters = " AND $datafilters";

        $data = DB::select("SELECT COUNT(CASE WHEN a.$indicadorNPS!=99 THEN 1 END) as Total, 
                        ROUND(((COUNT(CASE WHEN a.$indicadorNPS BETWEEN 9 AND 10 THEN 1 END) - COUNT(CASE WHEN a.$indicadorNPS BETWEEN 0 AND 6 THEN 1 END)) / (COUNT(CASE WHEN a.$indicadorNPS!=99 THEN 1 END)) * 100),1) AS NPS, 
                        ROUND(((COUNT(CASE WHEN a.$indicadorINS BETWEEN 6 AND 7 THEN 1 END) - COUNT(CASE WHEN a.$indicadorINS BETWEEN 1 AND 4 THEN 1 END)) / (COUNT(CASE WHEN a.$indicadorINS!=99 THEN 1 END)) * 100),1) AS INS,
                        a.mes, a.annio, date_survey, WEEK(date_survey) AS week
                        from $this->_dbSelected.$table as a
                        left join $this->_dbSelected." . $table . "_start as b
                        on a.token = b.token
                        where $where $datafilters
                        GROUP by $group
                        ORDER by a.date_survey ASC");
      
        foreach ($data as $key => $value) {
            $insActive = (isset($value->INS)) ? $value->INS : 0;
            if ($key == 0) {
                $insPreviousPeriod = 0;
            }

            $NpsInsTransvip[] = [
                'xLegend'   => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Total) . ')' : 'Semana ' . $value->week . ' (' . ($value->Total) . ')',
                'values'    => [
                    "nps"           => Round($value->NPS),
                    "ins"           => Round($value->INS),
                    "percentage"    => round($insActive) - round($insPreviousPeriod)
                ],
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
			a.mes, a.annio, date_survey, $this->_fieldSelectInQuery 
      		FROM $this->_dbSelected.$table as a
      		INNER JOIN $this->_dbSelected." . $table . "_start as b on a.token = b.token
      		WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters	
      		Group BY a.mes");
        }

        foreach ($data as $key => $value) {
            //echo ' pro '.round($value->promotor),' det '.round($value->detractor), ' neu '. (100-(round($value->promotor)+round($value->detractor)));

            $graphNPSBanVid[] = [
                'xLegend'   => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                'values'    => [
                    "promoters"     => Round($value->promotor),
                    "neutrals"      => 100 - (Round($value->promotor) + Round($value->detractor)),
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

        //echo $startDate.$endDate;
        //if(!isset($startDate)&& !isset($endDate) && !isset($survey)){return ['datas'=>'unauthorized', 'status'=>Response::HTTP_NOT_ACCEPTABLE];}
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
            if ($indetifyClient == 'vid')
                $db = $this->primaryTable($db);

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

        if ($filter == 'all') {
            $db2    = $this->primaryTable($db);


            $data   = DB::select("SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, age, $this->_fieldSelectInQuery
                                FROM (SELECT COUNT(*) as Total, b.age,
                                ROUND((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                COUNT(CASE WHEN a.$indicatorNPS!=99 THEN 1 END) * 100)*$this->_porcentageBan AS NPS,
                                ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS CSAT, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$db as a
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token 
                                      WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') and nps!= 99  $datafilters
                                GROUP BY (b.age BETWEEN 14 AND 22), (b.age BETWEEN 23 AND 38), (b.age BETWEEN 39 AND 54), (b.age BETWEEN 55 AND 73), (b.age BETWEEN 74 AND 99)
                            UNION
                            SELECT COUNT(*) as Total, b.age,
                                ROUND((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                COUNT(CASE WHEN a.$indicatorNPS!=99 THEN 1 END) * 100)*$this->_porcentageVid AS NPS,
                                ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid AS CSAT, $this->_fieldSelectInQuery
                                FROM $this->_dbSelected.$db2 as a
                                LEFT JOIN $this->_dbSelected." . $db2 . "_start as b on a.token = b.token 
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') and nps!= 99  $datafilters
                                GROUP BY (b.age BETWEEN 14 AND 22), (b.age BETWEEN 23 AND 38), (b.age BETWEEN 39 AND 54), (b.age BETWEEN 55 AND 73), (b.age BETWEEN 74 AND 99)) AS A
                                GROUP BY (age BETWEEN 14 AND 22), (age BETWEEN 23 AND 38), (age BETWEEN 39 AND 54), (age BETWEEN 55 AND 73), (age BETWEEN 74 AND 99)
                                ORDER BY age");
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
                //echo $value->NPS;
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
            if ($indetifyClient == 'vid')
                $db =  $this->primaryTable($db);

            $data = DB::select("SELECT COUNT(*) as Total, 
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) AS NPS, 
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
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) *$this->_porcentageBan AS NPS, 
            ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS CSAT, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$db as a 
            LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' and nps!= 99 $datafilters
            GROUP BY sex
            UNION
            SELECT COUNT(*) as Total, 
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS, 
            ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid AS CSAT, $this->_fieldSelectInQuery 
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
        if ($datafilters)
            $datafilters = " AND $datafilters";

        $fieldBd = $this->getFielInDbCsat($survey);
        //$fieldBd = 'csat';
        $endCsat = $this->getEndCsat($survey);
        $query = "";
        //$endCsat = 11;
        for ($i = 1; $i <= $endCsat; $i++) {

            if ($i != $endCsat) {
                $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, ";
            }
            if ($i == $endCsat) {
                $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i ";
            }
        }

        $data = DB::select("SELECT $query,date_survey, A.mes, A.annio
                                FROM $this->_dbSelected.$db as A
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b
                                on A.token = b.token 
                                WHERE date_survey BETWEEN '$dateEnd' AND  '$dateIni' $datafilters
                                group by A.mes, A.annio
                                ORDER BY date_survey");

        foreach ($data as $key => $value) {
            $values = [];

            for ($i = 1; $i <= $endCsat; $i++) {

                $r   = 'csat' . $i;
                $csat = $value->$r;
                $values = array_merge($values, [$r  => round($csat)]);
                //}
            }

            $graphCSAT[] = [
                'xLegend'  => (string)$value->mes . '-' . $value->annio,
                'values' => $values
            ];
        }
        return $graphCSAT;
    }  
    
    private function graphCsatTransvip($graphCSAT){
       $colors = ['#A2F584', '#F5C478', '#90C8F5', '#F580E7', '#3DA3F5', '#F5483E', '#8F65C2', '#F5EB4E', '#FFB203','#F5C76C', '#7DF5C5'];
       $drivers = ['Canal','Tiempo encontrar un conductor','Coordinación en Andén','Puntualidad del servicio','Tiempo llegada del vehículo',
       'Tiempo de espera aeropuerto','Seguridad al trasladarte','Medidas Covid','Tiempo de traslado','Atención del Conductor','Conducción'];
                $fields = [];
        for($i=1; $i <= 11; $i++){
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

            $data = DB::select("SELECT $query,date_survey
                                FROM $this->_dbSelected.$db as A
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b
                                on A.token = b.token 
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd'  $datafilters
                                ORDER BY date_survey");
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
                                "neutrals"      => (int)round(100 - (round($value->$det) + round($value->$pro))),
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
                                "promoters"     => round0,
                                "neutrals"      => (int)0,
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

    private function GraphCSATDriversMutual($db, $db2, $survey, $indicatorCSAT, $dateEnd, $dateIni, $filter, $struct = 'two', $datafilters = null, $IniDateMonth, $group)
    {
        $graphCSAT = [];

        $endCsat = $this->getEndCsat($survey);

        $fieldBd = $this->getFielInDbCsat($survey);

        $query = "";
        $query2 = "";
        $select = "";
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter != 'all') {
            $fieldBd = $this->getFielInDbCsat($survey);

            $query = "";
            for ($i = 1; $i <= $endCsat; $i++) {

                if ($i != $endCsat) {
                    $query .= " ((COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))- count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL)))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                ((count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                            ((count(if( $fieldBd$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat,  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                            ((count(if( $fieldBd$i <= $this->_maxMediumCsat AND  $fieldBd$i >= $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN   $fieldBd$i END)) as neutral$i,";
                }
                if ($i == $endCsat) {
                    $query .= " ((COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL)) - count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL)))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                            ((count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                            ((count(if( $fieldBd$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat,  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                            ((count(if( $fieldBd$i <= $this->_maxMediumCsat AND  $fieldBd$i >= $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN  $fieldBd$i END)) as neutral$i ";
                }
            }

            $data = DB::select("SELECT $query,date_survey, WEEK(date_survey) AS week, a.mes
                                FROM $this->_dbSelected.$db as a
                                LEFT JOIN $this->_dbSelected." . $db . "_start as b
                                on a.token = b.token 
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' $datafilters
                                ORDER BY date_survey");
        }

        $suite = new Suite($this->_jwt);
        foreach ($data as $key => $value) {
            for ($i = 1; $i <= $endCsat; $i++) {
                $r   = 'csat' . $i;
                $pro = 'promotor' . $i;
                $neu = 'neutral' . $i;
                $det = 'detractor' . $i;
                $csat = $value->$r;
                if ($struct == 'two') {
                    $graphCSAT[] = [
                        'xLegend' => $suite->getInformationDriver($survey . '_' . $r),
                        'values' =>
                        [
                            "promoters"     => (int)ROUND($value->$pro),
                            "neutrals"      => (int)100 - (ROUND($value->$pro) + ROUND($value->$det)),
                            "detractors"    => (int)ROUND($value->$det),
                            "csat"          => (int)ROUND($csat)
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
        return $graphCSAT;
    }

    private function nameSurvey($name)
    {
        $data = DB::select("SELECT nomSurvey FROM $this->_dbSelected.survey WHERE codDbase = '$name'");
        return $data[0]->nomSurvey;
    }

    private function closedLoop($db, $indicador, $dateEnd, $dateIni, $filter, $datafilters = null)
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
        //echo 'hoola'.$client;
        if($client == 'ban' &&  $filterClient != 'all'){
           return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageBan'/></span></div>";

        }
        if ($client == 'vid' &&  $filterClient != 'all') {
            return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageVid'/></span></div>";
        }

        if($filterClient == 'one' && $nameEncuesta !== 'Ambulatorios' && $nameEncuesta !== 'Imagenologia (img)' && $nameEncuesta !== 'Urgencias' && $nameEncuesta !== 'Rehabilitacion' && $nameEncuesta != 'Hospitalizacion'){ 
            return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageClient'/></span></div>";
        }
        if($client == 'mut' &&  $filterClient != 'all'){
            return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageClient'/></span></div>";
        }
        if ($table == 'MUT001_mutcon_resp') {
            return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard Consolidado !</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px'  src='$this->_imageClient'/></span></div>";
        }
        return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard Consolidado !</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageBan'/><img width='120px' src='$this->_imageVid'/></span></div>";
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
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageBan AS CSAT,  $this->_fieldSelectInQuery
        FROM $this->_dbSelected.$db as a
        LEFT JOIN $this->_dbSelected." . $db . "_start as b on a.token = b.token
              WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and $fieldFilter != '' and nps!= 99  $datafilters
        GROUP BY $fieldFilter
        UNION
        SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageVid AS CSAT,  $this->_fieldSelectInQuery
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
  
    private function ces($db,$filter, $mes, $annio, $ces){
        $data = null;   
        $str = substr($db,10,3);
        
        if($str == 'ges' || $str == 'eri' || $str == 'com'){
          
            $data = DB::select("SELECT COUNT(*) as Total,
                    (COUNT(if($ces between  $this->limFac1Ces and  $this->limFac2Ces  , $ces, NULL)) - COUNT(if($ces between $this->limDif1Ces and $this->limDif2Ces , $ces, NULL)))/COUNT(if(ces !=99,1,NULL ))* 100 AS CES 
                    FROM $this->_dbSelected.$db as a
                    WHERE mes = $mes AND annio = $annio");
                    $cesPrev = $this->cesPreviousPeriod($db, $mes, $annio);
        } 

        if($data == null || $data[0]->Total == null){
            return [
                "name"          => "CES",
                "value"         => "N/A",
                "m2m"           => "0"
            ];
        }
        
        if($data[0]->Total != null){
            return [
            "name"      => "CES",
            "value"     => ROUND($data[0]->CES),
            "m2m"       => ROUND($data[0]->CES)-ROUND($cesPrev['AntCes']),
        ];
        } 
    }
   
    private function cesPreviousPeriod($db,$mes,$annio){
        $data = [];
        $monthAnt = $mes - 1;
        if ($monthAnt == 0) {
            $monthAnt = 12;
            $annio = $annio - 1;
        }

        $data = DB::select("SELECT COUNT(*) as Total,
        (COUNT(if(ces between  $this->limFac1Ces and  $this->limFac2Ces , ces, NULL)) - COUNT(if(ces between $this->limDif1Ces and $this->limDif2Ces, ces, NULL)))/COUNT(if(ces !=99,1,NULL ))* 100 AS CES 
        FROM $this->_dbSelected.$db as a 
        WHERE mes = $monthAnt AND annio = $annio");

        return ['AntCes' => $data[0]->CES];
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
            $query =  "SELECT COUNT(*) as Total,
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
            $query = "SELECT SUM(nps) AS nps, mes, annio, $indicatorName FROM (
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
        )As a group by $group, a.mes, a.annio  ORDER BY $group, a.annio, a.mes";
        }

        if ($filterClient != 'all') {
            $query = "SELECT $indicatorBD as $indicatorName, b.mes,b.annio,date_survey,
            round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as nps
            from $this->_dbSelected." . $db . "_start as a
            left join $this->_dbSelected.$db as b
            on a.token = b.token
            where $whereInd!= '' and date_survey between  '2021-01-01' and '$startDate' and etapaencuesta = 'P2'  $where
            group by $group, a.mes, a.annio
            ORDER BY $group, a.annio, a.mes";
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
            $query = "          SELECT $indicatorName, SUM(nps) as nps, mes, annio from (SELECT $indicatorBD as $indicatorName, b.mes,b.annio,date_survey,
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
        //}
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
    private function rankingSucursal($db,$indicatordb, $indicator, $endDateFilterMonth, $startDateFilterMonth, $filterClient, $width){
        if($filterClient != 'all'){
            $querydataTop = "SELECT UPPER($indicatordb) as  $indicator,
                            round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                            b.annio
                            FROM $this->_dbSelected." . $db . "_start as a
                            left join $this->_dbSelected.$db as b
                            on a.token = b.token
                            where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != ''
                            group by  $indicator
                            order by CNPS DESC
                            LIMIT 5 ";
        
            $querydataBottom = "SELECT * from (SELECT UPPER($indicatordb) as  $indicator, count(UPPER($indicatordb)) as total,
                                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                                    b.annio
                                    FROM $this->_dbSelected." . $db . "_start as a
                                    left join $this->_dbSelected.$db as b
                                    on a.token = b.token
                                    where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != ''
                                    group by  $indicator
                                    order by CNPS asc
                                    LIMIT 5) as a
                                    order by CNPS desc";
        }

        if($filterClient == 'all'){
            $querydataTop = "SELECT $indicator, sum(CNPS) as CNPS, annio from (SELECT UPPER($indicatordb) as  $indicator,
                            round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                            b.annio
                            FROM $this->_dbSelected.".$db."_start as a
                            left join $this->_dbSelected.$db as b
                            on a.token = b.token
                            where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != ''
                            group by  $indicator
                            UNION
                            SELECT UPPER($indicatordb) as  $indicator,
                            round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                            b.annio
                            FROM $this->_dbSelected.".$db."_start as a
                            left join $this->_dbSelected.$db as b
                            on a.token = b.token
                            where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != ''
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
                                    where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != ''
                                    group by  $indicator
                                    UNION
                                    SELECT UPPER($indicatordb) as  $indicator, count(UPPER($indicatordb)) as total,
                                    round((count(case when nps = 9 OR nps =10 then 1 end)-count(case when nps between  0 and  6 then 1 end)) / count(case when nps != 99 then 1 end) *100) as CNPS,
                                    b.annio
                                    FROM $this->_dbSelected.".$db."_start as a
                                    left join $this->_dbSelected.$db as b
                                    on a.token = b.token
                                    where date_survey between '$startDateFilterMonth' and '$endDateFilterMonth' and etapaencuesta = 'P2' and $indicatordb != ''
                                    group by  $indicator) AS A
                                    group by  $indicator
                                    order by CNPS asc
                                    LIMIT 5";
        }

       $dataTop = DB::select($querydataTop);
       $dataBottom = DB::select($querydataBottom);

        if($dataTop){
                foreach ($dataTop as $key => $value){
                    $arrayTop[]= $value-> $indicator.' - '.$value->CNPS.'%';
                }
        }
        if ($dataBottom) {
            foreach ($dataBottom as $key => $value) {
                $arrayBottom[] = $value->$indicator . ' - ' . $value->CNPS . '%';
            }
        }

        return  [
            "height" => 4,
            "width" => $width,
            "type" => "lists",
            "props" => [
                "icon" => "arrow-right",
                "text" => "RANKING By  $indicator",
                "lists" => [
                    [
                        "header" => "Top Five ",
                        "color" => "#17C784",
                        "items" => $arrayTop,
                        "numbered" => true
                    ],
                    [
                        "header" => "Last Five",
                        "color" => "#F07667",
                        "items" => $arrayBottom,
                        "numbered" => true
                    ]
                ]
            ]
        ];
    }

    private function detailsProcedencia($db, $endDate, $startDate, $filterClient)
    {
        if ($filterClient != 'all') {
            $data = DB::select("select *, ROUND(proce/total*100, 2) as porcentaje  from 
                                    (SELECT count(procedencia) as proce, procedencia 
                                    FROM $this->_dbSelected." . $db . "_start  as a
                                    left join $this->_dbSelected.$db as b
                                    on a.token = b.token
                                    where procedencia != '' and procedencia != '-' and b.date_survey BETWEEN '$startDate' and '$endDate' AND etapaencuesta = 'P2'
                                    group by procedencia) as a join 
                                    (select COUNT(*) as total 
                                    from $this->_dbSelected." . $db . "_start as a
                                    left join $this->_dbSelected.$db as b
                                    on a.token = b.token
                                    where procedencia != '' and procedencia != '-' and b.date_survey BETWEEN '$startDate' and '$endDate' AND etapaencuesta = 'P2')
                                    AS b on true
                                    ORDER BY porcentaje desc");
            if ($data) {
                foreach ($data as $key => $value) {
                    $resp[] = [
                        "text"        => $value->procedencia,
                        "cant"        => $value->proce,
                        "porcentaje"  => $value->porcentaje . " %"
                    ];
                }
            }

            if (!$data) {
                $resp[] = [

                    "text"        => 'N/A',
                    "cant"        => 0,
                    "porcentaje"  => 0
                ];
            }
        }
        return $resp;
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
                "text" => "NPS - INS",
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
                            "text" => "INS",
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


    private function graphProcedencia($db, $startDateFilterMonth, $endDateFilterMonth, $filterClient)
    {
        $dataProcedencia = $this->detailsProcedencia($db, $startDateFilterMonth, $endDateFilterMonth, $filterClient);
        //var_dump($dataProcedencia);
        $standarStruct = [
            [
                "text" => "Procendecia",
                "key" => "text",
                "cellColor" => "#17C784",
                "textAlign" => "left"
            ],
            [
                "text" => "Cantidad",
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
        return  [
            "height" =>  3,
            "width" =>  12,
            "type" =>  "tables",
            "props" =>  [
                "icon" => "arrow-right",
                "text" => "Procedencia",
                "tables" => [
                    [
                        "columns" => [
                            $standarStruct[0],
                            $standarStruct[1],
                            $standarStruct[2]
                        ],
                        "values" => $dataProcedencia,
                    ],
                ]
            ]
        ];
    }

    private function statsByTaps($db, $db2, $mes, $year, $npsInDb, $csatInDb, $startDateFilterMonth, $endDateFilterMonth, $datafilters = null, $filterClient, $indetifyClient)
    {
        if ($filterClient != 'all'){
            if($indetifyClient == 'vid'){
                $db = $this->primaryTable($db);
            }
        }

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
                    $datasNichosStruct
                    ,
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

    private function structfilter($request, $fieldbd, $fieldurl, $where)
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

        //BANMEDICA
        $where .= $this->structfilter($request, 'sex',       'genero',   $where);
        $where .= $this->structfilter($request, 'region',    'regiones', $where);
        $where .= $this->structfilter($request, 'nicho',     'nicho',    $where);
        $where .= $this->structfilter($request, 'tramo',     'tramo',    $where);
        $where .= $this->structfilter($request, 'nomSuc',    'sucursal', $where);
        $where .= $this->structfilter($request, 'sitioWeb',  'Web',      $where);

        //MUTUAL
        $where .= $this->structfilter($request, 'macroseg',   'Macrosegmento',     $where);
        $where .= $this->structfilter($request, 'tatencion',  'ModalidadAtencion', $where);
        $where .= $this->structfilter($request, 'tipcliente', 'TipoCliente',       $where);
        $where .= $this->structfilter($request, 'canal',      'Canal',             $where);
        $where .= $this->structfilter($request, 'tatencion',  'TipoAtencion',      $where);
        $where .= $this->structfilter($request, 'catencion',  'CentroAtencion',    $where);

        //TRANSVIP
        $where .= $this->structfilter($request, 'tipocliente',       'tipoCliente',       $where);
        $where .= $this->structfilter($request, 'tiposervicio',      'TipoServicio',      $where);
        $where .= $this->structfilter($request, 'condicionservicio', 'CondiciónServicio', $where);
        $where .= $this->structfilter($request, 'sentido',           'Sentido',           $where);
        $where .= $this->structfilter($request, 'zon',               'Zona',              $where);
        $where .= $this->structfilter($request, 'tipoReserva',       'TipoReserva',       $where);
        $where .= $this->structfilter($request, 'canal',             'Canal',             $where);
        $where .= $this->structfilter($request, 'convenio',          'Convenio',          $where);

        return $where;
    }

    private function cardsPerformace($dataNps, $dataCsat, $survey, $datafilters)
    {
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($this->_dbSelected == 'customer_colmena') {
            $name = 'CES';
            $val = 'N/A';
            $percentage = 0;
        }
        
        if($this->_dbSelected == 'customer_colmena' && $survey == 'tra'){
            $insPreviousPeriod = $this->npsPreviousPeriod('adata_tra_via',date('m'),date('Y'),'csat',$datafilters );
            $name= 'INS';
            $val = $insPreviousPeriod['insAct'];
            $percentage= round($insPreviousPeriod['insAct']-$insPreviousPeriod['ins']);  
        }

        if ($this->_dbSelected == 'customer_banmedica') {
            $name = $dataCsat['name'];
            $val = $dataCsat['value'];
            $percentage =  (int)$dataCsat['percentage'];
        }
        $this->_valueMinAnomalias = (int)$dataNps['value'] - 20;
        $this->_valueMaxAnomalias = (int)$dataNps['value'] + 30;

        return [
            "height" => 1,
            "width" => 6,
            "type" => "performance",
            "props" => [
                "icon" => "arrow-right",
                "performances" => [
                    [
                        "name"    => $dataNps['name'],
                        "value"   => $dataNps['value'],
                        "m2m"     => (int)round($dataNps['percentage']),
                    ],
                    [
                        "name"    => $name,
                        "value"   => $val,
                        "m2m"     => $percentage,
                    ],
                ],
            ],
        ];
    }

    private function welcome($client, $filterClient, $bd, $table = null)
    {
        $nameEncuesta = ucwords(strtolower($this->nameSurvey(trim($bd))));

        return [
            "height" =>  1,
            "width" =>  6,
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

    private function cardCsatDriversMutual($csat, $name, $graphCsatM, $ButFilterWeeks)
    {
        return [
            "height" => 3,
            "width" => 6,
            "type" => "chart",
            "props" => [
                "callToAction" => $ButFilterWeeks,
                "icon" => "arrow-right",
                "text" => $csat . " • " . $name,
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
                    "values" => $graphCsatM
                ],
            ],
        ];
    }

    private function cardNpsBanmedica($nameIndicatorPrincipal, $dataNPSGraph)
    {
        return [
            "height" => 3,
            "width" => 6,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => "NPS Consolidado • " . $nameIndicatorPrincipal,
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
                "text" => "NPS Consolidado • " . $nameIndicatorPrincipal2,
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

    private function CSATJourney($graphCSATDrivers)
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

    private function CSATDrivers($graphCSATDrivers){

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
                array_push($this->_anomaliasPain, $group);
            }
            if ($this->_valueMaxAnomaliasText <= $value) {
                array_push($this->_anomaliasGain, $group);
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
        if ($request->dateIni !== null && $request->dateEnd !== null) {
            $dateIni = $request->dateEnd;
            $dateEnd = $request->dateIni;
            $startDateFilterMonth   = $request->dateIni;
            $endDateFilterMonth     = $request->dateEnd;
        }

        $datafilters = $this->infofilters($request);

        if ($request->filterWeeks !== null) {
            $interval = is_numeric($request->filterWeeks) ? $request->filterWeeks : 9;
            if ($datafilters != '') {
                $datafilters .= ' and date_survey between date_sub(NOW(), interval 9 week) and NOW() ';
                $group = " week ";
            }
            if ($datafilters == '') {
                $datafilters .= ' date_survey between date_sub(NOW(), interval 9 week) and NOW() ';
                $group = " week ";
            }
        }

        $filterClient  = ($request->client === null) ? $this->_initialFilter : $request->client;
        $indetifyClient = substr($request->survey, 0, 3);
        $indetifyClient = ($filterClient == 'all') ? $indetifyClient : $filterClient;
        $npsInDb    = $this->getFielInDb($request->survey);
        $csatInDb   = $this->getFielInDbCsat($request->survey);
        $db         = 'adata_'.$indetifyClient.'_'.trim(substr($request->survey,3,6));
        
        if(substr($request->survey,0,3) == 'mut'){
            $db = 'adata_'.substr($request->survey,0,3).'_'.trim(substr($request->survey,3,6));
        }

        if(substr($request->survey,0,3) == 'jet'){
            $db = 'adata_'.substr($request->survey,0,3).'_'.trim(substr($request->survey,3,6));
        }
        
        $tipeSurvey = $request->survey;
        $clientes = null;

        if ($tipeSurvey == "muthos" || $tipeSurvey == "muturg" || $tipeSurvey == "mutamb" || $tipeSurvey == "mutimg" || $tipeSurvey == "mutreh") {
            $db = 'MUT001_mutcon_resp';

            if ($request->client) {
                $db = 'adata_' . trim(substr($request->client, 0, 3)) . '_' . trim(substr($request->client, 3, 6));
            }
            $clientes = [
                        "Ambulatorio"=>"mutamb",
                        "Imagenología"=>"mutimg",
                        "Urgencias"=>"muturg",
                        "Rehabilitación"=>"mutreh",
                        "Hospitalización"=>"muthos"
            ]; 
        }
       
        $rankingSuc = null;
        $ges = null;
        $ejecutivo = null;
        $sucNpsCsat =  null;
        $regiones = null;
        $sucursal = null;
        $call = null;
        $venta = null;
        $super = null;
        $Procedencia = null;
        $csat1 = null;
        $csat2 = null;
        $indicatordb = ($indetifyClient == 'vid') ? 'ban' : 'vid';
        $nameIndicatorPrincipal  = ($indetifyClient == 'vid') ? 'Vida Tres' : 'Banmédica';   //banmedica
        $nameIndicatorPrincipal2 = ($indetifyClient == 'vid') ? 'Banmédica' : 'Vida Tres';  //vidatres

        $dbVT       = 'adata_' . $indicatordb . '_' . substr($request->survey, 3, 6);

        if (substr($request->survey, 0, 3) == 'tra') {
            $db = 'adata_tra_via';
        }

        $dataNps    = $this->resumenNps($db, date('m'), date('Y'), $npsInDb, $filterClient, $datafilters);
        $dataCsat   = $this->resumenCsat($db, date('m'), date('Y'), $csatInDb, $filterClient, $datafilters);

        $dataCsatGraph   = $this->graphCsat($db, date('m'), date('Y'), $csatInDb, $endDateFilterMonth, $startDateFilterMonth,  $filterClient, $datafilters);

        if ($this->_dbSelected  == 'customer_banmedica') {
            $name =  $nameIndicatorPrincipal . ' & ' . $nameIndicatorPrincipal2;
            $db2 = ($indetifyClient == 'vid') ? 'adata_ban_' . trim(substr($request->survey, 3, 6)) : 'adata_vid_' . trim(substr($request->survey, 3, 6));

            $dataNPSGraph         = $this->graphNps($db, date('m'), date('Y'), $npsInDb, $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
            $dataNPSGraph2        = $this->graphNps($dbVT, date('m'), date('Y'), $npsInDb, $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
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
                $ejecutivo = $this->npsByIndicator($db, $dateEnd, $dateIni, $filterClient, "DISTINCT(UPPER(nombreEjecutivo)like 'Ext%')", 'nombreEjecutivo', 'CountExt', "(nombreEjecutivo NOT like 'Ext%')", 'Ejecutivos', 2);
            } 
            if ($db == 'adata_ban_suc' || $db == 'adata_vid_suc') {
                $sucursal   = $this->npsNew($db, $dateEnd, $dateIni, 4, $filterClient);
                $regiones   = $this->npsByRegiones($db, $dateEnd, $dateIni, $filterClient, 'ubicSuc', 'regiones', 'Regiones y Region Metropolitana');
                $sucNpsCsat = $this->npsCsatbyIndicator($db, $dateEnd, $dateIni, 'nomSuc', 'Sucursal', 'csat3', 'csat4', 6, $filterClient);
                $rankingSuc = $this->rankingSucursal($db, 'nomsuc', 'Sucursal', $endDateFilterMonth, $startDateFilterMonth, $filterClient, 8);
                if ($db == 'adata_ban_suc') {
                    $db = 'adata_ban_con';
                    $ges = $this->npsByIndicator($db, $dateEnd, $dateIni, $filterClient, 'canal', 'canal', 'canal', 'canal', 'Canal', 2);
                }
                if ($db == 'adata_vid_suc') {
                    $db = 'adata_vid_con';
                    $ges = $this->npsByIndicator($db, $dateEnd, $dateIni, $filterClient, 'canal', 'canal', 'canal', 'canal', 'Canal', 2);
                }
            }

            $welcome            = $this->welcome($indetifyClient, $filterClient, $request->survey);
            $performance        = $this->cardsPerformace($dataNps, $dataCsat, substr($request->survey, 0, 3), $datafilters);
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
        }

        if ($this->_dbSelected  == 'customer_colmena'  && substr($request->survey, 0, 3) == 'mut') {

            $name = 'Mutual';
            $nameCsat1 = 'Tiempo espera para tu atención';
            $nameCsat2 = 'Amabilidad profesionales';
            //$dataCes              = $this->ces($db, $datafilters, date('m'), date('Y'), $request->survey);
            $dataNPSGraph         = $this->graphNps($db, date('m'), date('Y'), $npsInDb, $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
            $dataCsat1Graph       = $this->graphCsatMutual($db, date('m'), date('Y'), 'csat1', $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
            $dataCsat2Graph       = $this->graphCsatMutual($db, date('m'), date('Y'), 'csat2', $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
            $graphCSATDrivers     = $this->GraphCSATDriversMutual($db, null, trim($request->survey), $csatInDb, $endDateFilterMonth, $startDateFilterMonth, 'one', 'two', $datafilters, $dateEnd, $group);
            $datasStatsByTaps     = null;

            if ($db == 'adata_mut_amb' ||  $db == 'adata_mut_urg' ||  $db == 'adata_mut_reh') {
                $csat1 = $this->cardCsatDriversMutual($nameCsat1, $name, $dataCsat1Graph, $this->ButFilterWeeks);
                $csat2 = $this->cardCsatDriversMutual($nameCsat2, $name, $dataCsat2Graph, $this->ButFilterWeeks);
            }
            if ($db == 'adata_mut_img') {
                $Procedencia = $this->graphProcedencia($db, $endDateFilterMonth, $startDateFilterMonth, $filterClient);
            }

            $welcome            = $this->welcome(($request->client !== null) ? 'mut' : $request->client, $filterClient, ($request->client !== null) ? $request->client : $request->survey, $db);
            $performance        = $this->cardsPerformace($dataNps, $dataCsat, substr($request->survey, 0, 3), $datafilters);
            $npsConsolidado     = $this->cardNpsConsolidado($name, $dataNPSGraph, $this->ButFilterWeeks);
            $npsBan             = null;
            $npsVid             = null;
            $csatJourney        = $this->CSATJourney($graphCSATDrivers);
            $csatDrivers        = $this->CSATDrivers($graphCSATDrivers);
            $cx                 = null;
            $wordCloud          = null;
            $closedLoop         = $csat1;
            $detailGender       = $csat2;
            $detailGeneration   = $this->closedLoop($db, $npsInDb, $endDateFilterMonth, $startDateFilterMonth, $filterClient, $datafilters);
            $detailsProcedencia = $Procedencia;
            $box14              = $venta;
            $box15              = $call;
            $box16              = $sucursal;
            $box17              = $regiones;
            $box18              = $ejecutivo;
            $box19              = $ges;
            $box20              = $sucNpsCsat;
            $box21              = $rankingSuc;
        }

        if ($this->_dbSelected  == 'customer_colmena'  && substr($request->survey, 0, 3) == 'tra') {

            $name = 'Transvip';
            $datasStatsByTaps   = null;
            $dataCL             = $this->closedloopTransvip($datafilters, $dateIni, $dateEnd);
            //REVISAR QUERYS SE DEMORAN 2 SEG DESDE ACA
            $datasCbiResp       = $this->cbiResp($db,$datafilters, $dateIni, $dateEnd);
            $drivers            = $this->csatsDriversTransvip($db, trim($request->survey), $dateIni, $dateEnd, $datafilters);
            $graphCSATDrivers   = $this->GraphCSATDrivers($db, '', trim($request->survey), $csatInDb, $endDateFilterMonth, $startDateFilterMonth,  'one', 'two', $datafilters, $group);
            $dataisn            = $this->NpsIsnTransvip($db, date('m'), date('Y'), $npsInDb, $csatInDb, $datafilters, $group, $dateIni, $dateEnd);
            $tiempoVehiculo     = $this->NpsIsnTransvip($db, date('m'), date('Y'), $npsInDb, 'csat2', $datafilters, null, $dateIni, $dateEnd);
            $coordAnden         = $this->NpsIsnTransvip($db, date('m'), date('Y'), $npsInDb, 'csat3', $datafilters, null, $dateIni, $dateEnd);
            //HASTA ACA
            $tiempoAeropuerto   = $this->NpsIsnTransvip($db, date('m'), date('Y'), $npsInDb, 'csat6', $datafilters, null, $dateIni, $dateEnd);
            $tiempoLlegadaAnden = $this->NpsIsnTransvip($db, date('m'), date('Y'), $npsInDb, 'csat5', $datafilters, null, $dateIni, $dateEnd);
            $welcome            = $this->welcome(($request->client !== null) ? 'tra' : $request->client, $filterClient, ($request->client !== null) ? $request->client : $request->survey, $db);
            $performance        = $this->cardsPerformace($dataNps, $dataisn, substr($request->survey, 0, 3), $datafilters);
            $npsConsolidado     = $this->graphNpsIsn($dataisn, $this->ButFilterWeeks);
            $npsVid             = $this->wordCloud($request); //null;
            $csatJourney        = $this->CSATJourney($graphCSATDrivers);
            $csatDrivers        = $this->graphCLTransvip($dataCL);
            $cx                 = $this->graphCbiResp($datasCbiResp);
            $wordCloud          = $this->globales($db, date('m'), date('Y'), 'sentido', 'Sentido', 'cbi', 'ins', 4, $datafilters);
            $closedLoop         = $this->globales($db, date('m'), date('Y'), 'tiposervicio', 'Vehículo', 'cbi', 'ins', 4, $datafilters);
            $detailGender       = $this->globales($db, date('m'), date('Y'), 'sucursal', 'Sucursal', 'cbi', 'ins', 4, $datafilters);
            $detailGeneration   = $this->rankingSucursal($db, 'convenio', 'Convenio', $endDateFilterMonth, $startDateFilterMonth, $filterClient, 6);
            $detailsProcedencia = $this->graphINS($tiempoVehiculo, $coordAnden, $tiempoAeropuerto, $tiempoLlegadaAnden);
            $box14              = $this->graphCsatTransvip($drivers);
            $box15              = null;
            $box16              = null;
            $box17              = null;
            $box18              = null;
            $box19              = null;
            $box20              = null;
            $box21              = null;
            $npsBan             = $this->cxIntelligence($request);
        }

        if ($this->_dbSelected  == 'customer_jetsmart') {

            $name = 'JetSmart';
            $datasStatsByTaps   = null;
            $welcome            = $this->welcome(($request->client !== null) ? 'jet' : $request->client, $filterClient, ($request->client !== null) ? $request->client : $request->survey, $db);
            $performance        = $this->graphCbi($db, date('m'), date('Y'), 'cbi', date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")),  $datafilters, 'one');//$this->cardsPerformace($dataNps, $dataisn, substr($request->survey, 0, 3), $datafilters);
            $npsConsolidado     = null; //$this->graphNpsIsn($dataisn, $this->ButFilterWeeks);
            $npsVid             = null; //$this->wordCloud($request); //null;
            $csatJourney        = null; //$this->CSATJourney($graphCSATDrivers);
            $csatDrivers        = null; //$this->graphCLTransvip($dataCL);
            $cx                 = null; //$this->graphCbiResp($datasCbiResp);
            $wordCloud          = null; //$this->globales($db, date('m'), date('Y'), 'sentido', 'Sentido', 'cbi', 'ins', 4, $datafilters);
            $closedLoop         = null; //$this->globales($db, date('m'), date('Y'), 'tiposervicio', 'Vehículo', 'cbi', 'ins', 4, $datafilters);
            $detailGender       = null; //$this->globales($db, date('m'), date('Y'), 'sucursal', 'Sucursal', 'cbi', 'ins', 4, $datafilters);
            $detailGeneration   = null; //$this->rankingSucursal($db, 'convenio', 'Convenio', $endDateFilterMonth, $startDateFilterMonth, $filterClient, 6);
            $detailsProcedencia = null; //$this->graphINS($tiempoVehiculo, $coordAnden, $tiempoAeropuerto, $tiempoLlegadaAnden);
            $box14              = null; //$this->graphCsatTransvip($drivers);
            $box15              = null;
            $box16              = null;
            $box17              = null;
            $box18              = null;
            $box19              = null;
            $box20              = null;
            $box21              = null;
            $npsBan             = $this->cxIntelligence($request);
        }

        $filters = $this->filters($request, $jwt);
        $data = [
            'client' => $this->_nameClient,
            'clients' => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients : $clientes,

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
                $box21
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
            $this->_obsNps              = 'obs_csat';
            $this->_imageClient         = 'https://customerscoops.com/assets/companies-images/mutual_logo.png';
            $this->_nameClient          = 'Mutual';
            $this->ButFilterWeeks       = [["text" => "Anual", "key" => "filterWeeks", "value" => ""], ["text" => "Semanal", "key" => "filterWeeks", "value" => "10"]];
            $this->limDif1Ces           = 1;
            $this->limDif2Ces           = 4;
            $this->limFac1Ces           = 6;
            $this->limFac2Ces           = 7;
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
            $this->_imageClient         = 'https://customerscoops.com/assets/companies-images/logo_cs.png';
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
           $this->_maxCsat             = 4;
           $this->_minMediumCsat       = 5;
           $this->_maxMediumCsat       = 5;
           $this->_minMaxCsat          = 9;
           $this->_maxMaxCsat          = 10;
           $this->_obsNps              = 'obs';
           $this->_imageClient         = 'https://customerscoops.com/assets/companies-images/logo_cs.png';
           $this->_nameClient          = 'JetSmart';
           $this->ButFilterWeeks       = '';
           $this->limDif1Ces           = 1;
           $this->limDif2Ces           = 2;
           $this->limFac1Ces           = 4;
           $this->limFac2Ces           = 5;
        }
    }
}
