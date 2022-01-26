<?php namespace App;

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
    private $_activeSurvey ='banrel';
    
    //SE LE APLICAN CUANDO SE UNEN LAS TABLAS, SI SE FILTRAA POR SURVEY NO SE APLICAN %
    private $_porcentageBan = 0.77;
    private $_porcentageVid = 0.23;
    private $_high                  = 'ALTA';
    private $_medium                = 'MEDIA';
    private $_low                   = 'BAJA';
    private $_periodCxWord          = 2;
    private $expiresAtCache         = '';
    private $generalInfo            = '';
    
    public function __construct()
    {
        //$generalInfo = Generic::configInitial($jwt[env('AUTH0_AUD')]->client);
        $this->expiresAtCache = Carbon::now()->addHours(24);
    }
    private function getFielInDb($survey)
    {
        $npsInDb = 'nps';
        return $npsInDb;
    }
    
    private function contentfilter($data, $type){
         $content = [];
        $count = count($data); 
        
        //echo $count.'<br>';
        foreach($data as $key => $value){
            $namefilters = $this->textsfilters($type.$value->$type);
            $content[($namefilters!== false)?$namefilters:$value->$type]=$value->$type;
        } 
        //print_r($content);
        return ($content);
    }
    
    
    private function textsfilters($cod){
        $arr=  ['region1'=>'Tarapacá',
                'region2'=>'Antofagasta', 
                'region3'=>'Atacama',
                'region4'=>'Coquimbo',
                'region5'=>'Valparaíso',
                'region6'=>"O'Higgins",
                'region7'=>'El Maule',
                'region8'=>'El Bío Bío',
                'region9'=>'La Araucanía',
                'region10'=>'Los Lagos',
                'region11'=>'Aysén',
                'region12'=>'Magallanes y Antártica Chilena',
                'region13'=>'Región Metropolitana de Santiago',
                'region14'=>'Los Ríos',
                'region15'=>'Arica y Parinacota',
                'region16'=>'Nuble',
                'sex1'=>'masculino',
                'sex2'=>'femenino'];
            
        if(array_key_exists($cod, $arr)){
            return $arr[$cod];
        }
        if(!array_key_exists($cod, $arr)){
            return false;
        }
    }
    
    
    public function filters($request, $jwt){
        $survey = $request->get('survey');
        $content = '';
        $db = 'adata_'.substr($survey,0,3).'_'.substr($survey,3,6);
        
        //REGION
        $data = DB::select("SELECT DISTINCT(region) 
                 FROM ".$db."_start 
                 WHERE region != ''"); 
         
        $regiones = ['filter'=>'regiones','datas'=>$this->contentfilter($data, 'region')];
        //return $regiones;
          
        //GENERO
        $data = DB::select("SELECT  DISTINCT(sex)
                            FROM  ".$db."_start
                            Where sex!= '#N/D' AND sex!=''");
        //print_r($data);
        $genero = ['filter'=>'genero', 'datas'=>$this->contentfilter($data, 'sex')];  
          
        
        //TRAMO
        $data = DB::select("SELECT DISTINCT(tramo) 
                                FROM  ".$db."_start
                                 WHERE tramo != '#N/A' AND tramo != ''");
        //print_r($data);                        
        $tramo =['filter'=>'tramo', 'datas'=>$this->contentfilter($data, 'tramo')];
        
        
        //NICHO
        $data = DB::select("SELECT DISTINCT(nicho) 
                            FROM  ".$db."_start 
                           WHERE nicho != 'SN' and nicho != ''");
                           
         $nicho =['filter'=>'nicho', 'datas'=>$this->contentfilter($data, 'nicho')];
        
 
        return ['filters'=>[(object)$regiones, (object)$genero, (object)$tramo, (object)$nicho], 'status'=>Response::HTTP_OK];  
                           
        
        // //COMUNA
        // $data = DB::select("SELECT DISTINCT(comuna) 
        //                         FROM  ".$db."_start");
        
        // //ANTIGUEDAD
        // $data = DB::select("SELECT antIsapre 
        //                         FROM  ".$db."_start
        //                         GROUP BY (antIsapre < 1), (antIsapre = 1 || antIsapre < 2), (antIsapre = 2 || antIsapre < 5), (antIsapre >= 5)");
        
        
        // //GENERACION
        // $data = DB::select("SELECT age
        //                         FROM  ".$db."_start
        //                         GROUP BY (age BETWEEN 14 AND 22), (age BETWEEN 23 AND 38), (age BETWEEN 39 AND 54), (age BETWEEN 55 AND 73), (age BETWEEN 74 AND 91)");
        
        
        //ZONA
        
    }
    
    
    
    
    public function detailsDashCxWord($request,$jwt)
    {
        //$startDate = $request->get('startDate');
        //$endDate = $request->get('endDate');
        
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
        //var_dump($dataTextMining);
        $wordCloud = ($dataTextMining['datas']->wordCloud);
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
    private function cxIntelligence($request){
        $request->merge([
            'startDate' => date('Y-m-d',strtotime(date('Y-m-01')."- $this->_periodCxWord month")),
            'endDate'   => date('Y-m-d'),
        ]);
        $survey = $request->get('survey');
        $value  = \Cache::get('cx'.$survey.$request->get('startDate').$request->get('endDate'));
        //$value = \Cache::pull('cx'.$survey.$request->get('startDate').$request->get('endDate'));
        if($value)
            return $value;
        $dataMatriz = $this->matriz($request);
        if($dataMatriz['datas'] === null){
            return  $resp = [
                "height"    => 4,
                "width"=> 8,
                "type"=> "lists",
                "props"=> [
                  "icon"=> "arrow-right",
                  "text"=> "CX Intelligence",
                  "lists"=> [
                    [
                      "header"=> "Pain Points",
                      "color"=> "#F07667",
                      "items"=> [],
                      "numbered"=> true
                    ],
                    [
                      "header"=> "Gain Points",
                      "color"=> "#17C784",
                      "items"=> [],
                      "numbered"=> true
                    ]
                  ]
                ]
            ];
        }
        $painPoint = $dataMatriz['datas']->cx->painpoint;
        $gainPoint = $dataMatriz['datas']->cx->gainpoint;
        //print_r($painPoint);
        $resp = [
                "height"=> 4,
                "width"=> 8,
                "type"=> "lists",
                "props"=> [
                  "icon"=> "arrow-right",
                  "text"=> "CX Intelligence",
                  "lists"=> [
                    [
                      "header"=> "Pain Points",
                      "color"=> "#F07667",
                      "items"=> $painPoint,
                      "numbered"=> true
                    ],
                    [
                      "header"=> "Gain Points",
                      "color"=> "#17C784",
                      "items"=> $gainPoint,
                      "numbered"=> true
                    ]
                  ]
                ]
            ];
            \Cache::put('cx'.$survey.$request->get('startDate').$request->get('endDate'), $resp, $this->expiresAtCache);
            return $resp;
    }
    
    
    public function backCards($request,$jwt)
    {
        $survey     = ($request->get('survey') === null) ? $this->_activeSurvey : $request->get('survey');
        $npsInDb    = $this->getFielInDb($survey);
        $dataEmail  = $this->email('adata_'.substr($survey,0,3).'_'.substr($survey,3,6),date('Y-m-01'),date('Y-m-d'));
        $data       = $this->infoClosedLoop('adata_'.substr($survey,0,3).'_'.substr($survey,3,6),date('Y-m-01'),date('Y-m-d'),$npsInDb, 'all');
        $resp = [$dataEmail, $data];
        //array_push($resp,$data);
        return [

            'datas'     => $resp,
            'status'    => Response::HTTP_OK
        ];
    }
    private function infoClosedLoop($db, $dateIni, $dateEnd, $fieldInBd, $filter)
    {
        if($filter != 'all'){
            $data = DB::select("SELECT COUNT(*) as ticketCreated, B.region, B.tramo, B.sex, B.nicho,
            COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
            COUNT(if(B.estado_close = 2, B.id, NULL)) as ticketPending, 
            COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL)) as ticketInProgres, 
            FROM $db as A 
            INNER JOIN ".$db."_start as B ON (A.token = B.token) 
            WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND obs_nps != ''");
        }
        if($filter == 'all')
        {
            $db2 = $this->primaryTable($db);
            $data = DB::select("SELECT SUM(ticketCreated) AS ticketCreated,SUM(ticketClosed) AS ticketClosed, SUM(ticketPending) AS ticketPending, SUM(ticketInProgres) AS ticketInProgres
            FROM (SELECT COUNT(*) as ticketCreated, 
            COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
            ((COUNT(if(B.estado_close = 2, B.id, NULL))*100)/COUNT(*))*$this->_porcentageBan as ticketPending, 
            ((COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL))*100)/COUNT(*))*$this->_porcentageBan as ticketInProgres,
            B.region, B.tramo, B.sex, B.nicho
            FROM $db as A 
            INNER JOIN ".$db."_start as B ON (A.token = B.token) 
            WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND obs_nps != ''
            UNION
            SELECT COUNT(*) as ticketCreated, 
            COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
            ((COUNT(if(B.estado_close = 2, B.id, NULL))*100)/COUNT(*))*$this->_porcentageVid as ticketPending, 
            ((COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL))*100)/COUNT(*))*$this->_porcentageVid as ticketInProgres,
            B.region, B.tramo, B.sex, B.nicho
            FROM $db2 as A 
            INNER JOIN ".$db2."_start as B ON (A.token = B.token) 
            WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND obs_nps != '') AS A");
        }
        //$data = DB::select("SELECT COUNT(*) as ticketCreated, COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, COUNT(if(B.estado_close = 2, B.id, NULL)) as ticketPending, COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL)) as ticketInProgres FROM $db as A INNER JOIN ".$db."_start as B ON (A.token = B.token) WHERE B.fechacarga BETWEEN '2021-12-01' AND '2021-12-31' AND p1 IN (0,1,2,3,4,5,6)");
        $closedRate = 0;
        //var_dump($data[0]->ticketCreated);
        if( $data[0]->ticketCreated != "0"){
            $closedRate = round(($data[0]->ticketClosed / $data[0]->ticketCreated) * 100, 3);
        }
        return [
            "name"          => "Close loop report",
            "icon"          => "closed-loop",
            "variant"       => "boxes", 
            "box" =>[ 
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
    
    private function email($db, $dateIni, $dateEnd){
        $db2 = $this->primaryTable($db);
        
        $data = DB::select("SELECT SUM(TOTAL) AS TOTAL FROM (SELECT COUNT(*) AS TOTAL FROM ".$db."_start WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' 
        UNION SELECT COUNT(*) AS TOTAL FROM ".$db2."_start WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' ) AS A");
        $EmailSend = $data[0]->TOTAL;
         
        $data2 = DB::select("SELECT SUM(RESP) AS RESP FROM (SELECT COUNT(*) AS RESP FROM $db WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' 
        UNION SELECT COUNT(*) AS RESP FROM $db2 WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd') AS A");
        
        // echo "SELECT SUM(RESP) AS RESP FROM (SELECT COUNT(*) AS RESP FROM $db WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' 
        // UNION SELECT COUNT(*) AS RESP FROM $db2 WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd') AS A";
        
        //print_r($data2);
        $EmailRESP = $data2[0]->RESP;
         
        return [
            "name"          => "Tracking de envíos",
            "icon"          => "email-stats",
            "variant"       => "boxes", 
            "box" =>[ 
                        [
                            "value" => (!$EmailSend)?0:$EmailSend,
                            "text"  => "Enviados",
                        ],
                        [
                            "value" => (!$EmailRESP)?0:$EmailRESP,
                            "text"  => "Respondidos",
                        ],
                        [
                            "value" => (!$EmailRESP)?0:round(($EmailRESP/ $EmailSend)*100).' %',
                            "text"  => "Tasa de respuesta",
                        ],
                ],
        ];
      
    }
    
    
    public function generalInfo($request, $jwt)
    {
        $indicators=new Suite;
        $data = [];
        $surveys = $indicators->getSurvey($request,$jwt);
        //var_dump ($surveys); 
     
        if($surveys['status'] == 200){
            foreach ($surveys['datas'] as $key => $value) {
                $db = 'adata_'.substr($value['base'],0,3).'_'.substr($value['base'],3,6);
                $db2 = $this->primaryTable($db);
                $npsInDb = 'nps';
                $csatInDb = 'csat';
                
                    $data[] = [
                        "title"         => ucwords(strtolower($value['name'])),
                        "identifier"    => $value['base'],
                        "nps"           => $this->infoNps($db,date('m'),date('Y'),$npsInDb,'all'),
                        //$db,$db2, $survey, $indicatorCSAT,  $dateEnd,$dateIni, $filter
                        "journeyMap"    => $this->GraphCSATDrivers($db,$db2,$value['base'],$csatInDb,date('Y-m-d'),date('Y-m-01'),'all','one'),
                        "otherGraphs"   => [$this->infoCsat($db,date('m'),date('Y'), $csatInDb,'all')],
                    ];
                //}
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
                                    FROM customer_banmedica.$db as A
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' 
                                    UNION
                                    SELECT $query2,date_survey
                                    FROM customer_banmedica.$db2 as A
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
                                    FROM customer_banmedica.$db as A
                                    WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' 
                                    ORDER BY date_survey");
            }
            //var_dump ($data);
          //print_r($data);
            $journey =[];
            $indicator = new Suite;
            
           
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
        ];
        if(array_key_exists($survey, $datas)){
            return $datas[$survey];
        }
        if(!array_key_exists($survey, $datas)){
            return false;
        }
    }

    
    //---------------------------------------------------------------------------------------------------------------------
    //OKK
    private function npsPreviousPeriod($table,$mes,$annio,$indicador){
        $monthAnt = $mes-1;
        if($monthAnt == 0){
            $mes = 12;
            $annio = $annio-1;
        }
        $table2 = $this->primaryTable($table);
        $data = DB::select("SELECT SUM(NPS) AS NPS FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) -
        COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
        (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageBan AS NPS
        FROM $table
        WHERE mes = $mes AND annio = $annio
        UNION
        SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) -
        COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
        (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageVid AS NPS
        FROM $table2
        WHERE mes = $mes AND annio = $annio) AS A");
        return $data[0]->NPS;
    }
    
     
    private function AVGLast6MonthNPS($table,$table2,$dateIni,$dateEnd,$indicador, $filter){
        if($filter == 'all'){
            $data = DB::select("SELECT SUM(NPS) AS NPS FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS
                                FROM $table
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                                union
                            SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS
                                FROM $table2
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni') AS A");
        }
        
        if($filter != 'all'){
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1) AS NPS
                                FROM $table
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'");
        }
        return (int)$data[0]->NPS;
    }
    
    
    private function primaryTable($table)
    {
        $db=explode('_',$table);
        //print_r($db);
        $indicatordb = ($db[1]=='vid')?'ban':'vid';
        return $table2=$db[0].'_'.$indicatordb.'_'.$db[2];
    }
    //OKK
    private function resumenNps($table,$mes,$annio,$indicador,$filter, $datafilters = null){
        $table2 = '';
        if($datafilters)
            $datafilters = " AND $datafilters";
        if($filter == 'all'){
            $table2 = $this->primaryTable($table);
           // $indicador2 = ($table2 == 'adata_vid_web')?'nps':$indicador;
            
     $data = DB::select("SELECT sum(NPS) AS NPS, SUM(total) as total, SUM(detractor) as detractor, SUM(promotor) AS promotor, SUM(neutral) AS neutral, AVG(promedio) AS promedio, nicho, sex, tramo, region
                        FROM (SELECT COUNT(CASE WHEN $indicador != 99 THEN 1 END) as total,
                    	((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)*$this->_porcentageBan) as detractor, 
                        ((count(if($indicador > 8  AND $indicador<=10, $indicador,NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageBan) as promotor,
                    	((count(if($indicador = 7 OR $indicador = 8, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageBan) as neutral,
                    		AVG($indicador) as promedio ,a.mes, a.annio, date_survey, b.nicho, b.sex, b.tramo, b.region,
                    		ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) -
                    		COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
                    		COUNT(CASE WHEN $indicador!=99 THEN $indicador END) * 100),1)*$this->_porcentageBan AS NPS
                    		FROM $table as a
                    		LEFT JOIN ".$table."_start as b
                    		on a.token = b.token
                    		WHERE a.mes = $mes AND a.annio = $annio $datafilters
                    		GROUP BY a.mes, a.annio
                    		UNION
                    		SELECT COUNT(CASE WHEN $indicador != 99 THEN 1 END) as total,
                    		((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)*$this->_porcentageVid) as detractor, 
                            ((count(if($indicador> 8 AND $indicador<=10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageVid) as promotor,
                    		((count(if($indicador = 7 OR $indicador = 8, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageVid) as neutral,
            				AVG($indicador) as promedio ,a.mes, a.annio, date_survey, b.nicho, b.sex, b.tramo, b.region,
            				ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) -
            				COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
            				COUNT(CASE WHEN $indicador!=99 THEN $indicador END) * 100),1)*$this->_porcentageVid AS NPS
            				FROM $table2 as a
            				LEFT JOIN ".$table2."_start as b
                    		on a.token = b.token
            				WHERE a.mes = $mes AND a.annio = $annio $datafilters
            				GROUP BY a.mes, a.annio
            				) AS A ");
        }
        
        if($filter != 'all'){
            $data = DB::select("SELECT count(*) as total, nicho, sex, tramo, region,
            count(if($indicador < 7, $indicador, NULL)) as detractor, 
            count(if($indicador > 8, $indicador, NULL)) as promotor, 
            count(if($indicador <= 8 AND $indicador >=7, $indicador, NULL)) as neutral, 
            AVG($indicador) as promedio,
            ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) / 
                                              (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS
            FROM $table
            LEFT JOIN ".$table."_start as b
            on a.token = b.token
            WHERE mes = $mes AND annio = $annio $datafilters
            GROUP BY mes, annio
            ORDER BY date_survey ASC");
        }
        
        if($data[0]->total!=0){
            $npsActive = (isset($data[0]->NPS)) ? $data[0]->NPS: 0;
            $npsPreviousPeriod = $this->npsPreviousPeriod($table,$mes,$annio,$indicador);
            return [
                "name"          => "nps",
                "value"         => round($npsActive),
                "promotors"     => round($data[0]->promotor),
                "neutrals"      => round($data[0]->neutral),
                "detractors"    => round($data[0]->detractor),
                "percentage"    => $npsActive-$npsPreviousPeriod,
                //"smAvg"         => round($data[0]->promedio),
                "smAvg"         => $this->AVGLast6MonthNPS($table,$table2,date('Y-m-d'),date('Y-m-d',strtotime(date('Y-m-d')."- 6 month")),$indicador,$filter)
            ];
       }
       
       if($data[0]->total== null){
            $npsActive = (isset($data[0]->NPS)) ? $data[0]->NPS: 0;
            $npsPreviousPeriod = $this->npsPreviousPeriod($table,$mes,$annio,$indicador);
            return [
                "name"          => "nps",
                "value"         => 'N/A',
                "promotors"     => 0,
                "neutrals"      => 0,
                "detractors"    => 0,
                "percentage"    => $npsActive-$npsPreviousPeriod,
                //"smAvg"         => round($data[0]->promedio),
                "smAvg"         => $this->AVGLast6MonthNPS($table,$table2,date('Y-m-d'),date('Y-m-d',strtotime(date('Y-m-d')."- 5 month")),$indicador,$filter)
            ];
       }
      
    }
    
    //OKK
    private function infoNps($table,$mes,$annio,$indicador,$filter)
    {
        $generalDataNps             = $this->resumenNps($table,$mes,$annio,$indicador,$filter);
        $generalDataNps['graph']    = $this->graphNps($table,$mes,$annio,$indicador,date('Y-m-d'), date('Y-m-d',strtotime(date('Y-m-d')."- 5 month")), $filter,'one');
        return $generalDataNps;
    }
    
    //OKK
    private function graphNps($table,$mes,$annio,$indicador,$dateIni, $dateEnd, $filter,$struct = 'two'){
        $graphNPS   = [];
        //$indicador  = ($table=='adata_vid_web')?'nps':$indicador;
        if($filter != 'all'){
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) / 
                                              (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1) AS NPS, 
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
					            count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
					            count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,              
                                count(*) as total, 
                                ((count(if($indicador < 7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as detractor, 
                                ((count(if($indicador > 8, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as promotor, 
                                ((count(if($indicador <= 8 AND $indicador >=7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as neutral,              
                                                    mes, annio  
                FROM $table
                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                GROUP BY mes, annio
                ORDER BY date_survey ASC");
        }
        if($filter == 'all'){
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            $data = DB::select("SELECT SUM(NPS) AS NPS, SUM(total) as total,SUM(detractor) as detractor,SUM(promotor) as promotor,SUM(neutral) as neutral, mes , annio, sum(Cdet) as Cdet, sum(Cpro) as Cpro, sum(Cneu) as Cneu
                                FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) / 
                                              (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS, 
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
					            count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
					            count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
                                count(*) as total, 
                                ((count(if($indicador < 7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as detractor, 
                                ((count(if($indicador > 8, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as promotor, 
                                ((count(if($indicador <= 8 AND $indicador >=7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as neutral, mes, annio,date_survey  
                FROM $table
                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                GROUP BY mes, annio
                UNION
                SELECT ROUND(((COUNT(CASE WHEN $indicador2 BETWEEN 9 AND 10 THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador2 BETWEEN 0 AND 6 THEN 1 END)) / 
                                              (COUNT($indicador2) - COUNT(CASE WHEN $indicador2=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS, 
                        count(if($indicador < 7, $indicador, NULL)) as Cdet,
					    count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
					    count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
                                count(*) as total, 
                                ((count(if($indicador2 < 7, $indicador2, NULL))*100)/count(CASE WHEN $indicador2 != 99 THEN $indicador2 END)*$this->_porcentageVid) as detractor, 
                                ((count(if($indicador2 > 8, $indicador2, NULL))*100)/count(CASE WHEN $indicador2 != 99 THEN $indicador2 END)*$this->_porcentageVid) as promotor, 
                                ((count(if($indicador2 <= 8 AND $indicador2 >=7, $indicador2, NULL))*100)/count(CASE WHEN $indicador2 != 99 THEN $indicador2 END)*$this->_porcentageVid) as neutral,              
                                                    mes, annio,date_survey
                FROM $table2
                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                GROUP BY mes, annio
                ) AS A GROUP BY mes, annio
                ORDER BY date_survey ASC");

        }
        
        //print_r($data);
        foreach ($data as $key => $value) {
            if($struct != 'one'){
                $graphNPS[]=[ 
                    'xLegend'  => 'Mes '.$value->mes.'-'.$value->annio.' ('.($value->Cdet + $value->Cpro + $value->Cneu).')',
                    'values' => [
                                    "promoters"     => round($value->promotor),
                                    "neutrals"      => round($value->neutral),
                                    "detractors"    => round($value->detractor),
                                    "nps"           => round($value->NPS)
                                ],
                ];
            }
            if($struct == 'one'){
               $graphNPS[] = [
                   "value" => $value->NPS
                   ];
            }
        }
        return $graphNPS;
    }
    
    
    //OKK
    private function csatPreviousPeriod($table,$mes,$annio,$indicador,$filter){
        $monthAnt       = $mes-1;
        if($monthAnt == 0){
            $monthAnt   = 12;
            $annio      = $annio-1;
        }
        if($filter != 'all'){
            $data = DB::select("SELECT ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as CSAT
            FROM $table
            WHERE mes = $monthAnt AND annio = $annio");
        }
        if($filter == 'all'){
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            $data = DB::select("SELECT SUM(CSAT) AS CSAT 
            FROM (SELECT ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageBan as CSAT
            FROM $table
            WHERE mes = $monthAnt AND annio = $annio and etapaencuesta = 'P2'
            UNION 
            SELECT ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageVid as CSAT
            FROM $table
            WHERE mes = $monthAnt AND annio = $annio and etapaencuesta = 'P2') AS A");
        }
        return $data[0]->CSAT;
    }
    

    //OKK
    private function resumenCsat($table,$mes,$annio,$indicador,$filter)
    {
        if($filter != 'all'){
            $data = DB::select("SELECT count(*) as total, 
            ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as csat
            FROM $table
            WHERE mes = $mes AND annio = $annio
            GROUP BY mes, annio");
        }
        if($filter == 'all'){
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            
            $data = DB::select("SELECT SUM(total) AS total, SUM(csat) AS csat 
            FROM (  SELECT count(*) as total, date_survey, mes, annio,
                     ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageBan as csat
            FROM $table
            WHERE mes = $mes AND annio = $annio and etapaencuesta = 'P2'
            GROUP BY mes, annio
            UNION
            SELECT count(*) as total, date_survey, mes, annio,
            ((COUNT(CASE WHEN $indicador2 BETWEEN 9 AND 10 THEN $indicador2 END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageVid as csat
            FROM $table2
            WHERE mes = $mes AND annio = $annio and etapaencuesta = 'P2'
            GROUP BY mes, annio) AS A
           ");
        }
        //print_r($data);
        $csatPreviousPeriod = $this->csatPreviousPeriod($table,$mes,$annio,$indicador,$filter);
        
        $csatActive = 0;
        
        if($data[0]->total != null){
            $csatActive = $data[0]->csat;
            return [
            "name"          => "csat",
            "value"         => ROUND($data[0]->csat),
            "percentage"    => round($csatActive)-round($csatPreviousPeriod),
            //"active"        => round($csatActive),
            //"previus"       => ($csatPreviousPeriod),
            //"smAvg"         => $csatActive-$csatPreviousPeriod,
            "smAvg"         => 0,
            
        ];
        }
        
        if($data[0]->total == null){
            $csatActive = $data[0]->csat;
            return [
            "name"          => "csat",
            "value"         => 'N/A',
            "percentage"    => $csatActive-$csatPreviousPeriod,
            //"smAvg"         => $csatActive-$csatPreviousPeriod,
            "smAvg"         => 0,
            
        ];
        }
    }
    
    private function infoCsat($table,$mes,$annio,$indicador)
    {
        $generalDataCsat            = $this->resumenCsat($table,$mes,$annio,$indicador,'all');
        $generalDataCsat['graph']   = $this->graphCsat($table,$mes,$annio,$indicador,date('Y-m-d'), date('Y-m-d',strtotime(date('Y-m-d')."- 5 month")),'all','one');
        return $generalDataCsat;
    }


    private function graphCsat($table,$mes,$annio,$indicador,$dateIni, $dateEnd, $filter, $struct='two'){
        $graphCSAT = array();
        if($filter != 'all'){
            $data = DB::select("SELECT COUNT(if( $indicador >= 9, $indicador, NULL)* 100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END) AS csat, 
                                mes, annio, date_survey  
                FROM $table
                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                GROUP BY mes
                ORDER BY date_survey DESC");
        }
        if($filter == 'all')
        {
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            $data = DB::select("SELECT SUM(csat) as csat, mes, annio, date_survey FROM 
            (SELECT COUNT(if( $indicador >= 9, $indicador, NULL)* 100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END) AS csat, 
                            mes, annio, date_survey  
            FROM $table
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
            GROUP BY mes
            UNION
            SELECT COUNT(if( $indicador >= 9, $indicador, NULL)* 100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END) AS csat, 
                            mes, annio, date_survey  
            FROM $table2
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
            GROUP BY mes
            ) AS A
            GROUP BY mes
            ORDER BY date_survey ASC");
            
        }
        if(!empty($data)){
            foreach ($data as $key => $value) {
                if($struct != 'one'){
                    $graphCSAT[] = [ 
                        'xLegend'  => (string)$value->mes.'-'.$value->annio,
                        'values'   => [ 
                            'csat' =>(string)$value->csat
                            ]
                    ];   
                }
                if($struct == 'one'){
                    $graphCSAT[] = [
                        "value" => (string)$value->csat
                    ];
                }
            }
        }
        return $graphCSAT;
    }
        
    public function getFielInDbCsat($survey)
    {
        $npsInDb = 'csat';
        return $npsInDb;
    } 
    
    private function graphNpsBanVid($table,$table2,$mes,$annio,$indicador,$dateIni, $dateEnd){
        $graphNPSBanVid=[];
            $data = DB::select("SELECT sum(NPS) as NPS,sum(detractor) AS detractor,sum(promotor) AS promotor,sum(neutral) as neutral, mes, annio, sum(Cdet) as Cdet, sum(Cpro) as Cpro, sum(Cneu) as Cneu
                    FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) -
						COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
						COUNT(CASE WHEN $indicador!=99 THEN 1 END) * 100),1)*$this->_porcentageBan AS NPS,
					    count(if($indicador < 7, $indicador, NULL)) as Cdet,
					    count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
					    count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
			((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as detractor, 
      		((count(if($indicador> 8 AND $indicador <=10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as promotor, 
      		((count(if($indicador=8 OR $indicador=7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageBan as neutral,
			mes, annio, date_survey 
      		FROM $table 
      		WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' 	
      		Group BY mes
			UNION 
      		SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN 1 END) - 
                           COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) / COUNT(CASE WHEN $indicador!=99 THEN 1 END) * 100),1)*$this->_porcentageVid AS NPS,
                        count(if($indicador < 7, $indicador, NULL)) as Cdet,
					    count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
					    count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
      				((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageVid as detractor,
					((count(if($indicador> 8 AND $indicador <=10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageVid as promotor, 
      				((count(if($indicador=8 OR $indicador=7, nps, NULL))*100)/COUNT(CASE WHEN $indicador!=99 THEN 1 END))*$this->_porcentageVid as neutral, 
      				mes, annio, date_survey 
      				FROM $table2 
      				WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' Group BY mes ) as A 
            Group BY mes 
            ORDER BY date_survey
			ASC"); 
                                
            foreach ($data as $key => $value) {
                 $graphNPSBanVid[]=[ 
                'xLegend'   => 'Mes '.$value->mes.'-'.$value->annio.' ('.($value->Cdet + $value->Cpro + $value->Cneu).')',
                'values'    => [
                                "promoters"     => (int)($value->promotor),
                                "neutrals"      => (int)($value->neutral),
                                "detractors"    => (int)($value->detractor),
                                "nps"           => (int)($value->NPS)
                            ],
            ]; 
            }
             return $graphNPSBanVid;
    }
    
    public function matriz($request)
    {
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $survey = $request->get('survey');
        //if(!isset($startDate)&& !isset($endDate) && !isset($survey)){return ['datas'=>'unauthorized', 'status'=>Response::HTTP_NOT_ACCEPTABLE];}
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL =>"https://customerscoops.com/matriz/calculo_matriz_full.php?startDate=$startDate&endDate=$endDate&survey=$survey",
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
        return ['datas'=>json_decode($response), 'status'=>Response::HTTP_OK];
    }
    
    public function textMining($request){
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $survey = $request->get('survey');
        //echo "https://customerscoops.com/text_mining/text_analytics_new.php?startDate=$startDate&endDate=$endDate&survey=$survey";
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
        return ['datas'=>json_decode($response), 'status'=>Response::HTTP_OK];
    }
    
    
  private function detailGeneration($db,$indicatorNPS,$indicatorCSAT,$dateIni, $dateEnd, $filter){
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
      
         if($filter != 'all')
        {
            $data = DB::select("SELECT COUNT(*) as Total,nicho, tramo, sex, region,
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN 9 AND 10 THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN 0 AND 6 THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) AS NPS, 
            ROUND(COUNT(if($indicatorCSAT between 9 and 10, $indicatorCSAT, NULL)* 100)/COUNT(if($indicatorCSAT!= 99,1,null))) AS CSAT, age
            FROM $db as a 
            LEFT JOIN ".$db."_start as b on a.token = b.token WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') GROUP BY age");
        }
        if($filter == 'all')
        {
            $db2    = $this->primaryTable($db);
            
            //$indicador2 = ($db2 == 'adata_vid_web')?'nps':$indicador;
           
             $data   = DB::select("SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, age, nicho, tramo, sex, region
                                        FROM (SELECT COUNT(*) as Total,b.nicho, b.tramo, b.sex, b.region,
                                        ROUND((COUNT(CASE WHEN a.$indicatorNPS BETWEEN 9 AND 10 THEN 1 END) - 
                                        COUNT(CASE WHEN a.$indicatorNPS BETWEEN 0 AND 6 THEN 1 END)) / 
                                        COUNT(CASE WHEN a.$indicatorNPS!=99 THEN 1 END) * 100)*$this->_porcentageBan AS NPS,
                                        ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS CSAT, age
                                        FROM $db as a
                                        LEFT JOIN ".$db."_start as b on a.token = b.token 
                                              WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M')
                                        GROUP BY (age BETWEEN 14 AND 22), (age BETWEEN 23 AND 38), (age BETWEEN 39 AND 54), (age BETWEEN 55 AND 73), (age BETWEEN 74 AND 91)
                                    UNION
                                    SELECT COUNT(*) as Total, b.nicho, b.tramo, b.sex, b.region,
                                        ROUND((COUNT(CASE WHEN a.$indicatorNPS BETWEEN 9 AND 10 THEN 1 END) - 
                                        COUNT(CASE WHEN a.$indicatorNPS BETWEEN 0 AND 6 THEN 1 END)) / 
                                        COUNT(CASE WHEN a.$indicatorNPS!=99 THEN 1 END) * 100)*$this->_porcentageVid AS NPS,
                                        ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid AS CSAT, age
                                        FROM $db2 as a
                                        LEFT JOIN ".$db2."_start as b on a.token = b.token WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M')
                                        GROUP BY (age BETWEEN 14 AND 22), (age BETWEEN 23 AND 38), (age BETWEEN 39 AND 54), (age BETWEEN 55 AND 73), (age BETWEEN 74 AND 91)) AS A
                                        GROUP BY (age BETWEEN 14 AND 22), (age BETWEEN 23 AND 38), (age BETWEEN 39 AND 54), (age BETWEEN 55 AND 73), (age BETWEEN 74 AND 91)
                                        ORDER BY age");
           
         }
         
        //print_r($data);
         
          
        foreach ($data as $key => $value) {
            //var_dump($value->age);
                if($value->age >= 14 && $value->age <= 22){
                    $quantityz=$value->Total;
                    $csatz =$value->CSAT;
                    $npsz=$value->NPS;
                } 
                if($value->age >= 23 && $value->age <= 38){
                    $quantitym = $value->Total;
                    $csatm     = $value->CSAT;
                    $npsm      = $value->NPS;
                }
                if($value->age >= 39 && $value->age <= 54){
                    $quantityx= $value->Total;
                    $csatx    = $value->CSAT;
                    $npsx     = $value->NPS;
                }
                if($value->age >= 55 && $value->age <= 73){
                    $quantityb= $value->Total;
                    $csatb    = $value->CSAT;
                    $npsb     = $value->NPS;
                }
                if($value->age >= 74 && $value->age <= 91){
                    $quantitys= $value->Total;
                    $csats    = $value->CSAT;
                    $npss     = $value->NPS;
                }
            }
           


  return [     "height"=> 3,
                 "width"=> 12,
                 "type"=> "compare-list",
                 "props"=> [
                     "icon"=> "arrow-right",
                     "text"=> "STATS by Generation",
                     "compareList"=> [
                                        [
                                        "icon"=> "genz",
                                        "percentage"=> 'GEN Z',
                                        "quantity"=>  '14 - 22',
                                        "items"=> [
                                                    [
                                                    "type"=> "NPS",
                                                    "value"=> $npsz,
                                                    "aditionalText" => "%"
                                                    ],
                                                    [
                                                    "type"=> "CSAT",
                                                    "value"=> $csatz,
                                                    "aditionalText" => "%"
                                                    ],
                                                    [
                                                    "type"=> "Cantidad de respuestas",
                                                    "value"=>  $quantityz,
                                                    "textColor"=> '#000'
                                                    ]
                                                  ],
                                        ],
                                        [
                                        "icon"=> "genmille",
                                        "percentage"=>  'GEN MILLE',
                                        "quantity"  => '23 - 38',
                                        "items"=> [
                                                      [
                                                        "type"=> "NPS",
                                                        "value"=> $npsm,
                                                        "aditionalText" => "%"
                                                      ],
                                                      [
                                                        "type"=> "CSAT",
                                                        "value"=> $csatm,
                                                        "aditionalText" => "%"
                                                      ],
                                                      [
                                                    "type"=> "Cantidad de respuestas",
                                                    "value"=>  $quantitym,
                                                    "textColor"=> '#000'
                                                    ]
                                                  ],
                                        ],
                                        [
                                        "icon"=> "genx",
                                        "percentage"=> 'GEN X',
                                        "quantity"=>   '39 - 54',
                                         "items"=> [
                                                        [
                                                            "type"=> "NPS",
                                                            "value"=> $npsx,
                                                            "aditionalText" => "%"
                                                        ],
                                                        [
                                                            "type"=> "CSAT",
                                                            "value"=> $csatx,
                                                            "aditionalText" => "%"
                                                        ],
                                                        [
                                                            "type"=> "Cantidad de respuestas",
                                                            "value"=>  $quantityx,
                                                            "textColor"=> '#000'
                                                        ]
                                                    ],
                                        ],
                                        [
                                        "icon"=> "genbb",
                                        "percentage"=> 'GEN BB',
                                        "quantity"=>   '55 - 73',
                                        "items"=> [
                                                        [
                                                            "type"=> "NPS",
                                                            "value"=> $npsb,
                                                            "aditionalText" => "%"
                                                        ],
                                                        [
                                                            "type"=> "CSAT",
                                                            "value"=> $csatb,
                                                            "aditionalText" => "%"
                                                        ],
                                                        [
                                                            "type"=> "Cantidad de respuestas",
                                                            "value"=>  $quantityb,
                                                            "textColor"=> '#000'
                                                        ]
                                                  ],
                                        ],
                                        [
                                        "icon"=> "gensil",
                                        "percentage"=> 'GEN SIL',
                                        "quantity"=>   '74 - 91',
                                        "items"=>   [
                                                        [
                                                            "type"=> "NPS",
                                                            "value"=> $npss,
                                                            "aditionalText" => "%"
                                                        ],
                                                        [
                                                            "type"=> "CSAT",
                                                            "value"=> $csats,
                                                            "aditionalText" => "%"
                                                        ],
                                                        [
                                                            "type"=> "Cantidad de respuestas",
                                                            "value"=>  $quantitys,
                                                            "textColor"=> '#000'
                                                        ]
                                                    ],
                                        ],
                                    ],
                            ]
        ];


  }
    
 
    
    private function detailsGender($db,$indicatorNPS, $indicatorCSAT, $dateIni, $dateEnd, $filter, $datafilters = null){
        $promedioF = 0;
        $promedioM = 0;
        $quantityF  = 0;
        $quantityM  = 0;
        $npsF       = 0;
        $csatF      = 0;
        $csatM      = 0;
        $npsM       = 0;
        if($datafilters)
            $datafilters = " AND $datafilters";
        if($filter != 'all')
        {
            $data = DB::select("SELECT COUNT(*) as Total,b.nicho, b.tramo,  b.region,
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN 9 AND 10 THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN 0 AND 6 THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) AS NPS, 
            ROUND(COUNT(if( $indicatorCSAT >= 9, $indicatorCSAT, NULL)* 100)/COUNT(if($indicatorCSAT !=99,1,NULL ))) AS CSAT, sex 
            FROM $db as a 
            LEFT JOIN ".$db."_start as b on a.token = b.token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M')  $datafilters
            GROUP BY sex");
        }
        if($filter == 'all')
        {
            $db2    = $this->primaryTable($db);
            //$indicador2 = ($db2 == 'adata_vid_web')?'nps':$indicador;
           
            $data   = DB::select("SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, sex, nicho, tramo, region
            FROM (SELECT COUNT(*) as Total,b.nicho, b.tramo, b.region,
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN 9 AND 10 THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN 0 AND 6 THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) *$this->_porcentageBan AS NPS, 
            ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS CSAT, sex 
            FROM $db as a 
            LEFT JOIN ".$db."_start as b on a.token = b.token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters
            GROUP BY sex
            UNION
            SELECT COUNT(*) as Total,b.nicho, b.tramo, b.region,
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN 9 AND 10 THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN 0 AND 6 THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS, 
            ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid AS CSAT, sex 
            FROM $db2 as a 
            LEFT JOIN ".$db2."_start as b on a.token = b.token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters
            GROUP BY sex) AS A
            GROUP BY sex
            " );
            
            // echo "SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, sex, nicho, tramo, region
            // FROM (SELECT COUNT(*) as Total,b.nicho, b.tramo, b.region,
            // ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN 9 AND 10 THEN 1 END) -
            // COUNT(CASE WHEN a.$indicatorNPS BETWEEN 0 AND 6 THEN 1 END)) /
            // (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) *$this->_porcentageBan AS NPS, 
            // ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS CSAT, sex 
            // FROM $db as a 
            // LEFT JOIN ".$db."_start as b on a.token = b.token 
            // WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters
            // GROUP BY sex
            // UNION
            // SELECT COUNT(*) as Total,b.nicho, b.tramo, b.region,
            // ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN 9 AND 10 THEN 1 END) -
            // COUNT(CASE WHEN a.$indicatorNPS BETWEEN 0 AND 6 THEN 1 END)) /
            // (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS, 
            // ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid AS CSAT, sex 
            // FROM $db2 as a 
            // LEFT JOIN ".$db2."_start as b on a.token = b.token 
            // WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters
            // GROUP BY sex) AS A
            // GROUP BY sex
            // ";
        }
        
        foreach ($data as $key => $value) {
                if($value->sex =='M' || $value->sex =='1'){
                    $quantityM=$value->Total;
                    $csatM =$value->CSAT;
                    $npsM=$value->NPS;
                } 
                if($value->sex =='F' ||  $value->sex =='2'){
                    $quantityF=$value->Total;
                    $csatF=$value->CSAT;
                    $npsF=$value->NPS;
                } 
            }
            $promedio=0;
            if($quantityF+$quantityM != 0){
                 $promedioF =  round($quantityF*100/($quantityF+$quantityM));
                 $promedioM =  round($quantityM*100/($quantityF+$quantityM));
            }
      
            
        
      
      return [  "height"=> 4,
                "width"=> 4,
                "type"=> "compare-list",
                "props"=> [
                    "icon"=> "arrow-right",
                    "text"=> "STATS by Gender",
                    "compareList"=> [
                      [
                        "icon"=> "mujer",
                        "percentage"=> (int)$promedioF,
                        //"quantity"=>   (int)$quantityF,
                        "items"=> [
                          [
                            "type"=> "NPS",
                            "value"=> ROUND($npsF),
                            "aditionalText" => "%"
                          ],
                           [
                            "type"=> "CSAT",
                            "value"=> $csatF,
                            "aditionalText" => "%"
                          ],
                          [
                            "type"=> "Respuestas",
                            "value"=> (int)$quantityF,
                            "textColor"=> '#000',
                          ]
                        ],
                      ],
                      [
                        "icon"=> "hombre",
                        "percentage"=>  $promedioM,
                        //"quantity"  => (int)$quantityM,
                        "items"=> [
                          [
                            "type"=> "NPS",
                            "value"=> $npsM,
                            "aditionalText" => "%"
                          ],
                          [
                            "type"=> "CSAT",
                            "value"=> $csatM,
                            "aditionalText" => "%"
                          ],
                           [
                            "type"=> "Respuestas",
                            "value"=> (int)$quantityM,
                            "textColor"=> '#000',
                          ]
                        ],
                      ],
                    ],
                  ],
                ];
    }
    
    private function detailsClosedLoop($db,$indicatorNPS, $indicatorCSAT, $dateIni, $dateEnd, $filter)
    {
        /*$ticketCreated      = 0;
        $ticketClosed       = 0;
        $convertion         = 0;
        $convertionRate     = 0;
        $ticketOpen         = 0;
        $ticketManage       = 0;
        $ticketPending      = 0;
        $ticketNoContact    = 0;
        $high               = 0;
        $medium             = 0;
        $low                = 0;
        if($filter != 'all'){
            $data = DB::select("SELECT 
                FROM $table as A
                LEFT JOIN $table_start as B on (A.token = B.token)
                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                GROUP BY mes
                ORDER BY date_survey DESC");
        }
        if($filter == 'all')
        {
            $table2 = $this->primaryTable($table);
            $indicador2 = ($table2 == 'adata_vid_web')?'nps':$indicador;
            $data = DB::select("SELECT SUM(csat) as csat, mes, annio, date_survey FROM 
            (SELECT COUNT(if( $indicador >= 9, $indicador, NULL)* 100)/COUNT($indicador) AS csat, 
                            mes, annio, date_survey  
            FROM $table
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
            GROUP BY mes
            UNION
            SELECT COUNT(if( $indicador >= 9, $indicador, NULL)* 100)/COUNT($indicador) AS csat, 
                            mes, annio, date_survey  
            FROM $table2
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
            GROUP BY mes
            ) AS A
            GROUP BY mes
            ORDER BY date_survey DESC");
        }
        //print_r($data);
        if(!empty($data)){
            foreach ($data as $key => $value) {
                $graphCSAT[] = [ 
                    'text'  => (string)$value->mes.'-'.$value->annio,
                    'value' => (string)$value->csat
                ];   
            }
        }
        
        
        
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
                "totalTiket"        => $ticketCreated
            ],
            'status' => Response::HTTP_OK
        ];
        */
    }
    
    private function GraphCSATDrivers($db,$db2, $survey, $indicatorCSAT,  $dateEnd,$dateIni, $filter, $struct='two'){
        //echo $survey;
        $endCsat = $this->getEndCsat($survey);
        $fieldBd = $this->getFielInDbCsat($survey);
        $fieldBd2 = $this->getFielInDbCsat($survey);
       // $fieldBd2 = ($db == 'adata_ban_web')?'csat':$fieldBd;
        $query = "";
        $query2 = "";
        $select = "";
        if($filter == 'all'){
            $fieldBd = $this->getFielInDbCsat($survey);
            $query = "";
            for ($i=1; $i <= $endCsat; $i++) {
                $select .= " ROUND(SUM(csat$i)) AS csat$i, SUM(detractor$i) AS detractor$i, SUM(promotor$i) AS promotor$i, SUM(neutral$i) AS neutral$i, ";
                 if($i != $endCsat){
                    $query .= "     ((COUNT(if($fieldBd$i = 9 OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS csat$i,
                                    ((count(if(csat$i < 7, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as detractor$i, 
                                            ((count(if(csat$i > 8, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as promotor$i, 
                                            ((count(if(csat$i <= 8 AND csat$i >=7, csat$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)*$this->_porcentageBan) as neutral$i,";
                }
                
                if($i == $endCsat){
                    $select .= " ROUND(SUM(csat$i)) AS csat$i, SUM(detractor$i) AS detractor$i, SUM(promotor$i) AS promotor$i, SUM(neutral$i) AS neutral$i ";
                    $query .= " ((COUNT(if($fieldBd$i = 9  OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS csat$i, 
                                    ((count(if(csat$i < 7, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as detractor$i, 
                                    ((count(if(csat$i > 8, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as promotor$i, 
                                    ((count(if(csat$i <= 8 AND csat$i >=7, csat$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)*$this->_porcentageBan) as neutral$i ";
                }
            }
            
            for ($i=1; $i <= $endCsat; $i++) {
                 if($i != $endCsat){
                    $query2 .= " ((COUNT(if($fieldBd$i = 9  OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid  AS csat$i, 
                                    ((count(if(csat$i < 7, csat$i, NULL))*100))/count(*)*$this->_porcentageVid as detractor$i, 
                                    ((count(if(csat$i > 8, csat$i, NULL))*100)/count(*)*$this->_porcentageVid) as promotor$i, 
                                    ((count(if(csat$i <= 8 AND csat$i >=7, csat$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)*$this->_porcentageVid) as neutral$i,";
                }
                
                if($i == $endCsat){
                    $query2 .= " ((COUNT(if($fieldBd$i = 9  OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid  AS csat$i, 
                                    ((count(if(csat$i < 7, csat$i, NULL))*100))/count(*)*$this->_porcentageVid as detractor$i, 
                                    ((count(if(csat$i > 8, csat$i, NULL))*100)/count(*)*$this->_porcentageVid) as promotor$i, 
                                    ((count(if(csat$i <= 8 AND csat$i >=7, csat$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)*$this->_porcentageVid) as neutral$i ";
                }
                
            }
            
            $query1 = "SELECT $query,date_survey
                                FROM customer_banmedica.$db as A
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' 
                                ";
                                
            $query2 = "SELECT $query2,date_survey
                                FROM customer_banmedica.$db2 as A
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' 
                                ";
                                
            $queryPrin = "SELECT $select FROM ($query1 UNION $query2) as A ORDER BY date_survey";
            //echo $queryPrin;
            $data = DB::select($queryPrin);
        }
        if($filter != 'all'){
            $fieldBd = $this->getFielInDbCsat($survey);
            $query = "";
            for ($i=1; $i <= $endCsat; $i++) {
                if($i != $endCsat){
                    $query .= " (COUNT(if( $fieldBd$i >= 9, $fieldBd$i, NULL))* 100)/COUNT(*) AS csat$i, ((count(if(csat$i < 7, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as detractor$i, 
                                            ((count(if(csat$i > 8, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as promotor$i, 
                                            ((count(if(csat$i <= 8 AND csat$i >=7, csat$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)*$this->_porcentageBan) as neutral$i,";
                }
                if($i == $endCsat){
                    $query .= " (COUNT(if( $fieldBd$i >= 9, $fieldBd$i, NULL))* 100)/COUNT(*) AS csat$i, ((count(if(csat$i < 7, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as detractor$i, 
                                            ((count(if(csat$i > 8, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as promotor$i, 
                                            ((count(if(csat$i <= 8 AND csat$i >=7, csat$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)*$this->_porcentageBan) as neutral$i ";
                }
                
            }
            
            $data = DB::select("SELECT $query,date_survey
                                FROM customer_banmedica.$db as A
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' 
                                ORDER BY date_survey");
        }
        
        //print_r($data);
        $suite = new Suite;
        foreach ($data as $key => $value) {
            for ($i=1; $i <= $endCsat; $i++) { 
                $r   = 'csat'.$i;
                $pro = 'promotor'.$i;
                $neu = 'neutral'.$i;
                $det = 'detractor'.$i;
                $csat = $value->$r;
                if($struct == 'two'){
                    $graphCSAT[] = ['xLegend'  => $suite->getInformationDriver($survey.'_'.$r),
                    'values' =>
                        [
                            "promoters"     => (int)($value->$pro),
                            "neutrals"      => (int)($value->$neu),
                            "detractors"    => (int)($value->$det),
                            "csat"          => $csat
                        ]
                ];
                }
                
                if($struct == 'one'){
                $graphCSAT[] = 
                [   'text'  =>  $suite->getInformationDriver($survey.'_'.$r),
                    'values' => ROUND($csat)
                ];
                }
            }
        }
        return $graphCSAT;
    }
    
    private function nameSurvey($name){
        $data = DB::select("SELECT nomSurvey FROM survey WHERE codDbase = '$name'");
        //print_r($data);
        return $data[0]->nomSurvey;
    }
    
    private function closedLoop($db, $indicador,$dateEnd, $dateIni, $filter){
        if($filter != 'all'){
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
                            COUNT(IF(nps = 5 OR nps = 6, $indicador , null )) as high 
                            FROM $db as a LEFT JOIN ".$db."_start as b on (a.token = b.token) 
                            WHERE nps in(0,1,2,3,4,5,6) AND etapaencuesta = 'P2' AND obs_nps != '' AND date_survey BETWEEN '$dateIni' AND '$dateEnd' 
                            ORDER BY date_survey DESC");
        }
        if($filter == 'all'){
            $db2     = $this->primaryTable($db);
            //$indicador2 = ($db2 == 'adata_vid_web')?'nps':$indicador;
            $data = DB::select("SELECT SUM(ticketCreated) AS ticketCreated, sum(ticketOpen) as ticketOpen,sum(ticketManage) as ticketManage,sum(ticketPending) as ticketPending,sum(ticketNoContact) as ticketNoContact,
            sum(ticketClosed) as ticketClosed,sum(convertion) as convertion,sum(low) as low,sum(medium) as medium,sum(high) as high,visita,estado,nps_cierre,etapaencuesta,contenido
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
                                COUNT(IF(nps = 5 OR nps = 6, $indicador , null )) as high 
                                FROM $db as a LEFT JOIN ".$db."_start as b on (a.token = b.token) 
                                WHERE nps in(0,1,2,3,4,5,6) AND obs_nps != '' AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' 
                                
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
                                COUNT(IF(nps = 5 OR nps = 6, $indicador , null )) as high 
                                FROM $db2 as a LEFT JOIN ".$db2."_start as b on (a.token = b.token) 
                                WHERE nps in(0,1,2,3,4,5,6) AND obs_nps != '' AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' 
                                ) AS A");
        }
        $closedRate     = 0;
        $convertionRate = 0;
        if ($data[0]->ticketCreated > 0)
            $closedRate = round(($data[0]->ticketClosed / $data[0]->ticketCreated) * 100);
        if ($data[0]->ticketClosed > 0)
            $convertionRate = (($data[0]->convertion / $data[0]->ticketClosed) * 100);
        return [
                "height"=> 4,
                "width"=> 8,
                "type"=> "summary",
                "props"=> [
                  "icon"=> "arrow-right",
                  "text"=> "Close The Loop",
                  "callToAction"=> [
                    "text"=> "Ir a la suite",
                    "icon"=> "arrow-right",
                    "url"=> "https://www.suite.customerscoops.app/",
                  ],
                  "sumaries"=> [
                    [
                      "icon"        => "tickets-created",
                      "text"        => "Ticket creados",
                      "value"       => $data[0]->ticketCreated,
                      "valueColor"  => "#17C784",
                      "detail"=> [
                        'CHP <span style="display: inline-block; width: 24px; height: 24px; border-radius: 50%; background-color: #E0DFDF; text-align: center;">?</span>',
                        '<span style="color: #F07667">●</span> Alta: '.$data[0]->high,
                        '<span style="color: #FFC700">●</span> Media: '.$data[0]->medium,
                        '<span style="color: #00CCB1">●</span> Baja: '.$data[0]->low,
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
    
    
    private function imagen($client, $filterClient,$nameEncuesta){
        if($client == 'ban' &&  $filterClient != 'all'){
           return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span> ¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start; align-items:center; gap:10px; margin-top:10px'><img width='120px' src='https://customerscoops.com/assets/companies-images/bm_logo.svg'/></span></div>";
        }
        if($client == 'vid' &&  $filterClient != 'all'){
           return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span> ¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start; align-items:center; gap:10px; margin-top:10px'><img width='120px' src='https://customerscoops.com/assets/companies-images/vidatres_logo.svg'/></span></div>";
        }
        return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span> ¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start; align-items:center; gap:10px; margin-top:10px'><img width='120px' src='https://customerscoops.com/assets/companies-images/bm_logo.svg'/><img width='120px' src='https://customerscoops.com/assets/companies-images/vidatres_logo.svg'/></span></div>";
    }
    
    private function getDetailsForIndicator($db, $db2,$month,$year,$npsInDb,$csatInDb, $dateIni, $dateEnd, $fieldFilter)
    {
        $query = "SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, $fieldFilter
        FROM (SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN 9 AND 10 THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN 0 AND 6 THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageBan AS CSAT,  $fieldFilter
        FROM $db as a
        LEFT JOIN ".$db."_start as b on a.token = b.token
              WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' 
        GROUP BY $fieldFilter
        UNION
        SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN 9 AND 10 THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN 0 AND 6 THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageVid AS CSAT,  $fieldFilter
        FROM $db2 as a
        LEFT JOIN ".$db2."_start as b on a.token = b.token WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd'
              GROUP BY $fieldFilter) AS A
        GROUP BY $fieldFilter";
        //echo $query;
        $data = $data = DB::select($query);
        $resp = array();
        $text = "sections";
        if($fieldFilter == 'nicho'){
            $text = 'niche';
        }
        if($data){
            foreach ($data as $key => $value) {
                //$index      =  '$value->'.$fieldFilter;
                $resp[] = [
                        $text       => $value->$fieldFilter,
                        "nps"       => ROUND($value->NPS)." %",
                        "csat"      => ROUND($value->CSAT)." %",
                        "quantity"  => $value->Total
                ];
            
          }
        }
        //print_r($resp);
        return $resp;
    }
    private function getDetailsAntiquity($db, $db2,$month,$year,$npsInDb,$csatInDb, $dateIni, $dateEnd,$fieldFilter)
    {
        $query = "SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, $fieldFilter
        FROM (SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN 9 AND 10 THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN 0 AND 6 THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageBan AS CSAT,  $fieldFilter
        FROM $db as a
        LEFT JOIN ".$db."_start as b on a.token = b.token
              WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd'
        GROUP BY ($fieldFilter BETWEEN 0 AND 1), ($fieldFilter BETWEEN 1 AND 2),($fieldFilter BETWEEN 2 AND 5),($fieldFilter BETWEEN 5 AND 100)
        UNION
        SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN 9 AND 10 THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN 0 AND 6 THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageVid AS CSAT,  $fieldFilter
        FROM $db2 as a
        LEFT JOIN ".$db2."_start as b on a.token = b.token WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd'
              GROUP BY ($fieldFilter BETWEEN 0 AND 1), ($fieldFilter BETWEEN 1 AND 2),($fieldFilter BETWEEN 2 AND 5),($fieldFilter BETWEEN 5 AND 100)) AS A
        GROUP BY ($fieldFilter BETWEEN 0 AND 1), ($fieldFilter BETWEEN 1 AND 2),($fieldFilter BETWEEN 2 AND 5),($fieldFilter BETWEEN 5 AND 100)";
        //echo $query;
        $data = $data = DB::select($query);
        $resp = array();
        $text = "sections";
        $lessOne = 0;
        $lessOneNps= 0;
        $lessOneCsat= 0;
        $lessTwo= 0;
        $lessTwoNps= 0;
        $lessTwoCsat= 0;
        $lessThree= 0;
        $lessThreeNps= 0;
        $lessThreeCsat= 0;
        $higherThree= 0;
        $higherThreeNps= 0;
        $higherThreeCsat= 0;
        if($data){
            foreach ($data as $key => $value) {
                if($value->$fieldFilter <= 1){
                    $lessOne        = $value->Total+$lessOne;
                    $lessOneNps     = $value->NPS+$lessOneNps;
                    $lessOneCsat    = $value->CSAT+$lessOneCsat;
                }
                if($value->$fieldFilter > 1 && $value->$fieldFilter <= 2){
                    $lessTwo        = $value->Total+$lessTwo;
                    $lessTwoNps     = $value->NPS+$lessTwoNps;
                    $lessTwoCsat    = $value->CSAT+$lessTwoCsat;
                }
                if($value->$fieldFilter > 2 && $value->$fieldFilter <= 5){
                    $lessThree        = $value->Total+$lessThree;
                    $lessThreeNps     = $value->NPS+$lessThreeNps;
                    $lessThreeCsat    = $value->CSAT+$lessThreeCsat;
                }
                if($value->$fieldFilter > 5){
                    $higherThree        = $value->Total+$higherThree;
                    $higherThreeNps     = $value->NPS+$higherThreeNps;
                    $higherThreeCsat    = $value->CSAT+$higherThreeCsat;
                }
            
          }
        }
        $resp = [
            [
            "antiquity"=> "Menor a 1 año",
            "nps"=> ROUND($lessOneNps)." %",
            "csat"=> ROUND($lessOneCsat)." %",
            "quantity"=> $lessOne
          ],
          [
            "antiquity"=> "1 a 2 años",
            "nps"=> ROUND($lessTwoNps)." %",
            "csat"=> ROUND($lessTwoCsat)." %",
            "quantity"=> $lessTwo
          ],
          [
            "antiquity"=> "2 a 5 años",
            "nps"=> ROUND($lessThreeNps)." %",
            "csat"=> ROUND($lessThreeCsat)." %",
            "quantity"=> $lessThree
          ],
          [
            "antiquity"=> "5 años o mas",
            "nps"=> ROUND($higherThreeNps)." %",
            "csat"=> ROUND($higherThreeCsat)." %",
            "quantity"=> $higherThree
          ]
          ];
        //print_r($resp);
        return $resp;
    }
    private function statsByTaps($db, $db2,$mes,$year,$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth)
    {
        $datasTramos = $this->getDetailsForIndicator($db, $db2,date('m'),date('Y'),$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'tramo');
        //print_r($datasTramos);
        $datasNichos = $this->getDetailsForIndicator($db, $db2,date('m'),date('Y'),$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'nicho');
        $datasAntiguedad = $this->getDetailsAntiquity($db, $db2,date('m'),date('Y'),$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth,'antIsapre');
        //antIsapre
        $standarStruct = [
             [
                "text"=> "NPS",
                "key"=> "nps",
                "cellColor"=> "#17C784"
             ],
             [
                "text"=> "CSAT",
                "key"=> "csat",
                "cellColor"=> "#17C784"
             ],
             [
                "text"=> "Cantidad de respuesta",
                "key"=> "quantity",
                "cellColor"=> "#17C784"
             ]
        ];
        return [
            "height"=>  2.5,
            "width"=>  12,
            "type"=>  "tables",
            "props"=>  [
                "icon"=> "arrow-right",
                "text"=> "STATS by business segments",
                "tables"=>[
                    [
                        "columns"=> [
                                [
                                    "text"=> "TRAMOS",
                                    "key"=> "sections",
                                    "headerColor"=> "#17C784",
                                    "cellColor"=> "#949494",
                                    "textAlign"=> "left"
                                ],
                                $standarStruct[0],
                                $standarStruct[1],
                                $standarStruct[2],
                            ],
                        "values"=> $datasTramos,
                    ],
                    [
                        "columns"=> [
                            [
                                "text"=> "NICHOS",
                                "key"=> "niche",
                                "headerColor"=> "#17C784",
                                "cellColor"=> "#949494",
                                "textAlign"=> "left"
                            ],
                            $standarStruct[0],
                            $standarStruct[1],
                            $standarStruct[2],
                        ],
                        "values"=> $datasNichos,
                    ],
                    [
                        "columns"=> [
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
                        "values"=> $datasAntiguedad,
                    ]
                    
                ]
            ]
        ];
    }
    
    
    private function structfilter($request, $fieldbd, $fieldurl, $where){
        //$where='';
        if($request->get($fieldurl) === null)
            return '';
        if($request->get($fieldurl)){
            if($where!=''){
               $where = " AND $fieldbd = '".$request->get($fieldurl)."'"; 
            }
             if($where==''){
               $where = " $fieldbd = '".$request->get($fieldurl)."'"; 
            }
        }
        return $where;
    }
    
    private function infofilters($request){
        $where='';
        $where .= $this->structfilter($request, 'sex', 'genero', $where);
        //echo $where. '<br>';
        $where .= $this->structfilter($request, 'region', 'regiones', $where);
        //echo $where. '<br>';
        $where .= $this->structfilter($request, 'nicho', 'nicho', $where);
        $where .= $this->structfilter($request, 'tramo', 'tramo', $where);
        //echo $where. '<br>';
        return $where;
    }
    
    
    
    //DETAILS DASH
    public function detailsDash($request, $jwt){
        
        //dashboard:read
        //$request->client trae vid-amb
        if($request->survey === null){
            return [
                'datas'     => ['No estas enviando una survey'],
                'status'    => Response::HTTP_NOT_ACCEPTABLE
            ];
        }
        //$request->survey banrel vidrel
        $startDateFilterMonth   = date('Y-m-01');
        $endDateFilterMonth     = date('Y-m-d');
        $dateIni = date('Y-m-d');                                        //2022-01-08
        $dateEnd = date('Y-m-d', strtotime(date('Y-m-d')."- 12 month")); //2021-01-08
        if($request->dateIni !== null && $request->dateEnd !== null)
        {
            $dateIni = $request->dateEnd;
            $dateEnd = $request->dateIni;
            $startDateFilterMonth   = $request->dateIni;
            $endDateFilterMonth     = $request->dateEnd;
        }
        
        $datafilters = $this->infofilters($request);
        //print_r($datafilters); exit;
        //echo $dateIni.'--'.$dateEnd;
        
        $filterClient   = ($request->client === null)?'all': $request->client;
        $indetifyClient = substr($request->survey,0,3);
        $indetifyClient = ($filterClient == 'all') ? $indetifyClient:$filterClient;
        
        $npsInDb    = $this->getFielInDb($request->survey);
        
        $csatInDb   = $this->getFielInDbCsat($request->survey);
        $csatInDb   =($csatInDb=='p')?'p3':$csatInDb;
        $db         = 'adata_'.$indetifyClient.'_'.substr($request->survey,3,6);
        //echo $db;
        $CL= $this->closedLoop($db, $npsInDb, $endDateFilterMonth, $startDateFilterMonth, $filterClient, $datafilters);
        //echo 'db '.$db;
        $indicatordb = ($indetifyClient == 'vid')?'ban':'vid';
        $nameIndicatorPrincipal = ($indetifyClient == 'vid')?'Vida Tres':'Banmédica';   //banmedica
        $nameIndicatorPrincipal2 = ($indetifyClient == 'vid')?'Banmédica':'Vida Tres';  //vidatres
        
        $dbVT        = 'adata_'.$indicatordb.'_'.substr($request->survey,3,6);
        //echo $nameIndicatorPrincipal2;
        
        //$nameSurvey = $this->nameSurvey($indetifyClient.substr($request->survey,3,6));
        //$graphCSATDrivers= $this->GraphCSATDrivers($db,$request->survey, $csatInDb, $dateIni, $dateEnd, 'all');
      
        $extraWhere = "'comuna' = 13";
        
        //OKK
        $dataNps    = $this->resumenNps($db,date('m'),date('Y'),$npsInDb, $filterClient, $datafilters);
        //$dataNps    = $this->resumenNps($db,date('m'),date('Y'),$npsInDb, $filterClient, $extraWhere);
        
        //OKK
        $dataCsat   = $this->resumenCsat($db,date('m'),date('Y'),$csatInDb, $filterClient);
        
        //OKK
        
        $dataCsatGraph   = $this->graphCsat($db,date('m'),date('Y'),$csatInDb,$endDateFilterMonth,$startDateFilterMonth,  $filterClient);
       
        //OKK
        $dataNPSGraph    = $this->graphNps($db,date('m'),date('Y'),$npsInDb,$dateIni, $dateEnd,'one');
        $dataNPSGraph2   = $this->graphNps($dbVT,date('m'),date('Y'),$npsInDb,$dateIni, $dateEnd,'one');
        
        //OKK
        $db2 = ($indetifyClient=='vid')?'adata_ban_'.substr($request->survey,3,6):'adata_vid_'.substr($request->survey,3,6);
        
        
        $graphCSATDrivers= $this->GraphCSATDrivers($db, $db2, $request->survey, $csatInDb,$endDateFilterMonth, $startDateFilterMonth,  'all');
        //OKK
        $dataNPSGraphBanVid   = $this->graphNpsBanVid($db, $db2,date('m'),date('Y'),$npsInDb, $dateIni, $dateEnd);
        
        
        $datasStatsByTaps = $this->statsByTaps($db, $db2,date('m'),date('Y'),$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth);
        
        $filters = $this->filters($request, $jwt);
        //print_r($filters);
        $data = [$this->filters($request, $jwt)][0];
        $data += ["indicators" => [
                $this->welcome($indetifyClient, $filterClient,$indetifyClient.substr($request->survey,3,6)),
                [
                    "height" => 1,
                    "width" => 6,
                    "type" => "performance",
                    "props" => [
                      "icon" => "arrow-right",
                      "performances" => [
                        [
                          "name"    => $dataNps['name'],
                          "value"   => $dataNps['value'],
                          "m2m"     => (int)$dataNps['percentage'],
                        ],
                        [
                          "name"    => $dataCsat['name'],
                          "value"   => $dataCsat['value'],
                          "m2m"     => (int)$dataCsat['percentage'],
                        ],
                      ],
                    ],
                ],
                [
                    "height"=> 4,
                     "width"=> 12,
                     "type"=> "chart",
                     "props"=> [
                        "icon"=> "arrow-right",
                        "text"=> "NPS Consolidado • $nameIndicatorPrincipal & $nameIndicatorPrincipal2",
                        "chart"=> [ 
                          "fields"=> [
                            [
                              "type"=> "stacked-bar",
                              "key"=> "detractors",
                              "text"=> "Detractores",
                              "bgColor"=> "#fe4560",
                            ],
                            [
                              "type"=> "stacked-bar",
                              "key"=> "neutrals",
                              "text"=> "Neutrales",
                              "bgColor"=> "#FFC700",
                            ],
                            [
                              "type"=> "stacked-bar",
                              "key"=> "promoters",
                              "text"=> "Promotores",
                              "bgColor"=> "#17C784",
                            ],
                            [
                              "type"=> "line",
                              "key"=> "nps",
                              "text"=> "NPS",
                              "bgColor"=> "#1a90ff",
                            ],
                        ],
                    "values"=> $dataNPSGraphBanVid
                        ],
                    ], 
                ],
                [
                    "height"=>3,
                    "width"=>6,
                    "type"=>"chart",
                    "props"=>[
                        "icon"=>"arrow-right",
                        "text"=>"NPS Consolidado • ".$nameIndicatorPrincipal,
                        "chart"=>[
                            "fields"=>[
                                [
                                    "type"=>"stacked-bar",
                                    "key"=>"detractors",
                                    "text"=>"Detractores",
                                    "bgColor"=>"#fe4560",
                                  ],
                                 
                                  [
                                    "type"=>"stacked-bar",
                                    "key"=>"neutrals",
                                    "text"=>"Neutrales",
                                    "bgColor"=>"#FFC700",
                                  ],
                                   [
                                    "type"=>"stacked-bar",
                                    "key"=>"promoters",
                                    "text"=>"Promotores",
                                    "bgColor"=>"#17C784",
                                  ],
                                  [
                                    "type"=>"line",
                                    "key"=>"nps",
                                    "text"=>"NPS",
                                    "bgColor"=>"#1a90ff",
                                  ],
                                ],
                            "values"=>$dataNPSGraph
                            ],
                    ],
                ],
                [
                    "height"=>3,
                    "width"=>6,
                    "type"=>"chart",
                    "props"=>[
                        "icon"=>"arrow-right",
                        "text"=>"NPS Consolidado • ".$nameIndicatorPrincipal2,
                        "chart"=>[
                            "fields"=>[
                                   [
                                    "type"=>"stacked-bar",
                                    "key"=>"detractors",
                                    "text"=>"Detractores",
                                    "bgColor"=>"#fe4560",
                                  ],
                                  [
                                    "type"=>"stacked-bar",
                                    "key"=>"neutrals",
                                    "text"=>"Neutrales",
                                    "bgColor"=>"#FFC700",
                                  ],
                                   [
                                    "type"=>"stacked-bar",
                                    "key"=>"promoters",
                                    "text"=>"Promotores",
                                    "bgColor"=>"#17C784",
                                  ],
                                  [
                                    "type"=>"line",
                                    "key"=>"nps",
                                    "text"=>"NPS",
                                    "bgColor"=>"#1a90ff",
                                  ],
                                ],
                            "values"=>$dataNPSGraph2
                            ],
                    ],
                ],
                [
                    "height"=> 4,
                    "width"=> 12,
                    "type"=> "chart",
                    "props"=> [
                    "icon"=> "arrow-right",
                    "text"=> "CSAT Journey",
                    "iconGraph"=>true,
                    "chart"=> [
                        "fields"=> [
                            [
                              "type"=> "area",
                              "key"=> "csat",
                              "text"=> "CSAT",
                              "bgColor"=> "#E9F4FE",
                              "strokeColor"=> "#008FFB",
                            ],
                        ],
                        "values"=> $graphCSATDrivers
                    ],
                    ]
                ],
                [
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
                                      "bgColor"=> "#FFC700",
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
                              "values" =>$graphCSATDrivers
                              ],
                             ],
                    ],
                $this->cxIntelligence($request),
                $this->wordCloud($request),
                $CL,
                $this->detailsGender($db, $npsInDb, $csatInDb,$endDateFilterMonth, $startDateFilterMonth,  $filterClient, $datafilters),
                $this->detailGeneration($db, $npsInDb, $csatInDb,$endDateFilterMonth, $startDateFilterMonth, $filterClient),
                $datasStatsByTaps,
               
            ]
        ];
        return [
            'datas'     => $data,
            'status'    => Response::HTTP_OK
        ];
    }
    private function welcome($client, $filterClient,$bd){
        $nameEncuesta = ucwords(strtolower($this->nameSurvey($bd)));
        return [
            "height" =>  1,
            "width" =>  6,
            "type" =>  "welcome",
            "props" =>  [
                "icon"=> "smile",
                "text"=> $this->imagen($client, $filterClient, $nameEncuesta),
                //"text"=> "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span> ¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start; align-items:center; gap:10px; margin-top:10px'><img width='120px' src='https://customerscoops.com/assets/companies-images/bm_logo.svg'/><img width='120px' src='https://customerscoops.com/assets/companies-images/vidatres_logo.svg'/></span></div>",
                
            ],
        ];
    }
}
