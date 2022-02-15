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
    
    
    public function getDBSelect(){
        return $this->_dbSelected;
    }
    
    public function getParams($field){
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
        $regiones =         [];
        $genero =           [];
        $tramo =            [];
        $nicho =            [];
        $sucursal =         [];
        $macrosegmento =    [];
        $modAtencion =      [];
        $tipoCliente =      [];
        $tipoCanal =        [];
        $tipoAtencion =     [];
        $CenAtencionn =     [];
        $db = 'adata_'.substr($survey,0,3).'_'.substr($survey,3,6);
        $dbC = substr($survey,3,6);
        
        
        //BANMEDICA
        if($this->_dbSelected  == 'customer_banmedica'){
        
            //REGION
            $data = DB::select("SELECT DISTINCT(region) 
                                FROM ".$db."_start 
                                WHERE region != ''"); 
            $regiones = ['filter'=>'regiones','datas'=>$this->contentfilter($data, 'region')];
              
            //TRAMO
            $data = DB::select("SELECT DISTINCT(tramo) 
                                FROM  ".$db."_start
                                WHERE tramo != '#N/A' AND tramo != ''");
            $tramo =['filter'=>'tramo', 'datas'=>$this->contentfilter($data, 'tramo')];
            
            //NICHO
            $data = DB::select("SELECT DISTINCT(nicho) 
                                FROM  ".$db."_start 
                                WHERE nicho != 'SN' and nicho != ''");
            $nicho =['filter'=>'nicho', 'datas'=>$this->contentfilter($data, 'nicho')];
             
            //GENERO
            $data = DB::select("SELECT  DISTINCT($this->_fieldSex)
                                FROM  $this->_dbSelected.".$db."_start
                                Where $this->_fieldSex != '#N/D' AND $this->_fieldSex !=''");
            $genero = ['filter'=>'genero', 'datas'=>$this->contentfilter($data, $this->_fieldSex)];  
            
            
            if($dbC == 'ges' || $dbC == 'suc' || $dbC == 'con'){
            //SUCURSAL
                $data = DB::select("SELECT DISTINCT(nomSuc) 
                                    FROM  ".$db."_start
                                    where nomSuc != ''");
                $sucursal = ['filter'=>'sucursal', 'datas'=>$this->contentfilter($data, 'nomSuc')];      
    
                return ['filters'=>[(object)$regiones, (object)$genero, (object)$tramo, (object)$nicho, (object)$sucursal], 'status'=>Response::HTTP_OK];
            }                    
        
            return ['filters'=>[(object)$regiones, (object)$genero, (object)$tramo, (object)$nicho], 'status'=>Response::HTTP_OK];
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
        if($this->_dbSelected  == 'customer_colmena'){
            $filters = null;
            
            if($dbC == 'be' || $dbC == 'ges'){
                $data = DB::select("SELECT DISTINCT(macroseg), B.nomMacro
                                    FROM $this->_dbSelected.adata_mut_".$dbC."_start AS A 
                                    LEFT JOIN $this->_dbSelected.macrosegmento AS B ON A.macroseg = B.id
                                    WHERE macroseg != 0");
                
                foreach($data as $value){
                    $macros[$value->nomMacro] = $value->macroseg;
                }
                $this->_fieldSelectInQuery = 'macroseg';
                
                $macrosegmento =['filter'=>'Macrosegmento', 'datas'=>$macros];
        } 
            
            
        if($dbC == 'eri'){
                $data = DB::select("SELECT DISTINCT(tatencion), B.nomModalidad 
                                    FROM $this->_dbSelected.adata_mut_".$dbC."_start AS A 
                                    LEFT JOIN $this->_dbSelected.modalidadAtencion AS B ON A.tatencion = B.codModalidad
                                    WHERE tatencion != 0 and tatencion != 7
                                    GROUP BY tatencion");   
                //SELECT DISTINCT(tatencion), B.nomModalidad FROM `adata_mut_eri_start` AS A LEFT JOIN modalidadAtencion AS B ON A.tatencion = B.codModalidad                    
                foreach($data as $value){
                    $atencion[$value->nomModalidad] = $value->tatencion;
                }
                $this->_fieldSelectInQuery = 'tatencion';
            
                $modAtencion =['filter'=>'ModalidadAtencion', 'datas'=>$atencion];
                
                return ['filters'=>[(object)$modAtencion], 'status'=>Response::HTTP_OK]; 
        }
            
            
        if($dbC == 'ges'){
                $data = DB::select("SELECT DISTINCT(tipcliente), B.nomTipoCliente 
                                    FROM $this->_dbSelected.adata_mut_".$dbC."_start AS A 
                                    LEFT JOIN $this->_dbSelected.tipoCliente AS B ON A.tipcliente = B.id 
                                    WHERE nomTipoCliente != 'null'");
                                    
                foreach($data as $value){
                    $cliente[$value->nomTipoCliente] = $value->tipcliente;
                }
                $this->_fieldSelectInQuery = 'tipcliente';
            
                $tipoCliente =['filter'=>'TipoCliente', 'datas'=>$cliente];
        }
            
            
        if($dbC == 'ges'){
                $data = DB::select("SELECT DISTINCT(canal), B.nomCanalGes 
                                    FROM $this->_dbSelected.adata_mut_".$dbC."_start as A 
                                    LEFT JOIN $this->_dbSelected.canalGes as B on A.canal = B.codCanalGes 
                                    WHERE B.codCanalGes != 'null'");
                
                foreach($data as $value){
                    $canal[$value->nomCanalGes] = $value->canal;
                }
                $this->_fieldSelectInQuery = 'canal';
            
                $tipoCanal =['filter'=>'Canal', 'datas'=>$canal];
                                        
                return ['filters'=>[(object)$tipoCliente, (object)$macrosegmento, (object)$tipoCanal], 'status'=>Response::HTTP_OK]; 
        }
        
            
        if($dbC == 'cas'){
           $data = DB::select("SELECT DISTINCT(tatencion), B.nomtatencion 
                                FROM $this->_dbSelected.adata_mut_".$dbC."_start AS A 
                                LEFT JOIN $this->_dbSelected.tipoatencion AS B ON A.tatencion = B.id 
                                WHERE B.nomtatencion != 'null'");
                                    
                foreach($data as $value){
                    $nomAtencion[$value->nomtatencion] = $value->tatencion;
                }
                $this->_fieldSelectInQuery = 'tatencion';
            
                $tipoAtencion =['filter'=>'TipoAtencion', 'datas'=>$nomAtencion];  
                
            return ['filters'=>[(object)$tipoAtencion], 'status'=>Response::HTTP_OK]; 
        }
            
            
            
        if($dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh' ){
              $data = DB::select("SELECT DISTINCT(tatencion), B.nomtatencion 
                                FROM $this->_dbSelected.adata_mut_".$dbC."_start AS A 
                                LEFT JOIN $this->_dbSelected.tipoatencion AS B ON A.tatencion = B.id 
                                WHERE B.nomtatencion != 'null'");
            foreach($data as $value){
                    $nomAtencionn[$value->nomtatencion] = $value->tatencion;
                }
                $this->_fieldSelectInQuery = 'tatencion';
            
                $tipAtencion =['filter'=>'TipoAtencion', 'datas'=>$nomAtencionn];  
                
            //return ['filters'=>[(object)$tipAtencion], 'status'=>Response::HTTP_OK];
        } 
        
          if($dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh'){
              $data = DB::select("SELECT DISTINCT(catencion), B.nomCentro 
                                FROM $this->_dbSelected.adata_mut_".$dbC."_start AS A 
                                LEFT JOIN $this->_dbSelected.centatenmutual AS B ON A.catencion = B.codCentro 
                                WHERE B.nomCentro != 'null'");
            foreach($data as $value){
                    $cenAtencion[$value->nomCentro] = $value->catencion;
                }
                $this->_fieldSelectInQuery = 'catencion';
            
                $CenAtencion =['filter'=>'CentroAtencion', 'datas'=>$cenAtencion];  
                
            return ['filters'=>[(object)$tipAtencion, (object)$CenAtencion], 'status'=>Response::HTTP_OK];
        } 
        
        if($dbC == 'hos'){
            $data = DB::select("SELECT DISTINCT(catencion), B.nomCentro 
                                FROM $this->_dbSelected.adata_mut_".$dbC."_start AS A 
                                LEFT JOIN $this->_dbSelected.centatenmutual AS B ON A.catencion = B.codCentro 
                                WHERE B.nomCentro != 'null'");
            foreach($data as $value){
                    $cenAtencion[$value->nomCentro] = $value->catencion;
                }
                $this->_fieldSelectInQuery = 'catencion';
            
                $CenAtencionn =['filter'=>'CentroAtencion', 'datas'=>$cenAtencion];  
                
            return ['filters'=>[(object)$CenAtencionn], 'status'=>Response::HTTP_OK];
        }

        return ['filters'=>[(object)$macrosegmento], 'status'=>Response::HTTP_OK];
        
        }
        
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
        $dataEmail  = $this->email('adata_'.substr($survey,0,3).'_'.substr($survey,3,6),date('Y-m-01'),date('Y-m-d'), $this->_initialFilter);
        $data       = $this->infoClosedLoop('adata_'.substr($survey,0,3).'_'.substr($survey,3,6),date('Y-m-01'),date('Y-m-d'),$npsInDb, $this->_initialFilter);
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
        if($datafilters)
            $datafilters = " AND $datafilters";
        //echo $this->_dbSelected;
        if($filter != 'all'){
            $data = DB::select("SELECT COUNT(*) as ticketCreated,
            COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
            COUNT(if(B.estado_close = 2, B.id, NULL)) as ticketPending, 
            COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL)) as ticketInProgres,  $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$db as A 
            INNER JOIN $this->_dbSelected.".$db."_start as B ON (A.token = B.token) 
            WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND $this->_obsNps != '' $datafilters");
        }
        if($filter == 'all')
        {
            $data = DB::select("SELECT SUM(ticketCreated) AS ticketCreated,SUM(ticketClosed) AS ticketClosed, SUM(ticketPending) AS ticketPending, SUM(ticketInProgres) AS ticketInProgres
            FROM (SELECT COUNT(*) as ticketCreated, 
            COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
            ((COUNT(if(B.estado_close = 2, B.id, NULL))*100)/COUNT(*))*$this->_porcentageBan as ticketPending, 
            ((COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL))*100)/COUNT(*))*$this->_porcentageBan as ticketInProgres
            FROM $this->_dbSelected.$db as A 
            INNER JOIN $this->_dbSelected.".$db."_start as B ON (A.token = B.token) 
            WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND obs_nps != '' $datafilters
            UNION
            SELECT COUNT(*) as ticketCreated, 
            COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
            ((COUNT(if(B.estado_close = 2, B.id, NULL))*100)/COUNT(*))*$this->_porcentageVid as ticketPending, 
            ((COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL))*100)/COUNT(*))*$this->_porcentageVid as ticketInProgres
            FROM $this->_dbSelected.$db2 as A 
            INNER JOIN $this->_dbSelected.".$db2."_start as B ON (A.token = B.token) 
            WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND obs_nps != '' $datafilters) AS A");
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
    
    private function email($db, $dateIni, $dateEnd, $filter){
        $db2 = $this->primaryTable($db);
        //echo $filter;
        if($filter == 'all'){
        $data = DB::select("SELECT SUM(TOTAL) AS TOTAL 
                FROM (SELECT COUNT(*) AS TOTAL 
                FROM $this->_dbSelected.".$db."_start 
                WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' 
                UNION SELECT COUNT(*) AS TOTAL 
                FROM $this->_dbSelected.".$db2."_start 
                WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' ) 
                AS A");
        $EmailSend = $data[0]->TOTAL;
         
        $data2 = DB::select("SELECT SUM(RESP) AS RESP FROM 
                                (SELECT COUNT(*) AS RESP 
                                FROM $this->_dbSelected.$db 
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' 
                                UNION 
                                SELECT COUNT(*) AS RESP 
                                FROM $this->_dbSelected.$db2 
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd') AS A");
        };
        
        
        if($filter != 'all'){
        //print_r($this->_dbSelected);    
        $data = DB::select("SELECT COUNT(*) AS TOTAL FROM $this->_dbSelected.".$db."_start WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd'" );
        $EmailSend = $data[0]->TOTAL;
         
        $data2 = DB::select("SELECT COUNT(*) AS RESP 
                            FROM $this->_dbSelected.$db 
                            WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' ");
        };
        
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
                            "value" => ($EmailSend==0)?0:round(($EmailRESP/ $EmailSend)*100).' %',
                            "text"  => "Tasa de respuesta",
                        ],
                ],
        ];
      
    }
    
    
    public function generalInfo($request, $jwt)
    {
        $indicators=new Suite($this->_jwt);
        $data = [];
        $surveys = $indicators->getSurvey($request,$jwt);
        //var_dump ($surveys); 
   
        
        if($surveys['status'] == 200){
            foreach ($surveys['datas'] as $key => $value) {
                $db = 'adata_'.substr($value['base'],0,3).'_'.substr($value['base'],3,6);
                $db2 = $this->primaryTable($db);
                $npsInDb = 'nps';
                $csatInDb = 'csat';
                $cesInDb = 'ces';
                $otherGraph = [$this->infoCsat($db,date('m'),date('Y'), $csatInDb,$this->_initialFilter)];
                if(substr($value['base'],0,3) == 'mut'){
                    $otherGraph = [[
                    "name"          => "CES",
                    "value"         => "N/A",
                ]];
                }
                if($value['base'] == 'mutges'){
                     $otherGraph = [$this->infoCsat($db,date('m'),date('Y'), $csatInDb,$this->_initialFilter), $this->ces($db,$this->_initialFilter,date('m'),date('Y'), $csatInDb)];
                } else if ($value['base'] == 'muteri'){
                     $otherGraph = [$this->ces($db,$this->_initialFilter,date('m'),date('Y'), $csatInDb)];
                } 
           
                 
                
                $data[] = [
                    'client'     => $this->_nameClient, 'clients'  => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: null,
                    //'client'     => $this->_nameClient, 'clients'  => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: $clientt,
                    "title"         => ucwords(strtolower($value['name'])),
                    //"title"         => ucwords(strtolower('relacional')),
                    "identifier"    => $value['base'],
                    "nps"           => $this->infoNps($db,date('m'),date('Y'),$npsInDb,$this->_initialFilter),
                    //$db,$db2, $survey, $indicatorCSAT,  $dateEnd,$dateIni, $filter
                    "journeyMap"    => $this->GraphCSATDrivers($db,$db2,$value['base'],$csatInDb,date('Y-m-d'),date('Y-m-01'),$this->_initialFilter,'one'),
                    "otherGraphs"   => $otherGraph
                ];
                //}
            }
        }
        
        //var_dump($value['base']);
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
            //var_dump ($data);
          //print_r($data);
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
            //TO DO
            //COMPLETAR CON CSATS
            "mutges" => "5",
            "mutamb" => "2",
            "mutbe"  => "5",
            "mutcas" => "2",
            "muteri" => "6",
            "muthos" => "2",
            "mutimg" => "2",
            "mutreh" => "2",
            "muturg" => "2",
            "demdem"=> "8"
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
        if($this->_dbSelected == 'customer_colmena'){
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageBan AS NPS
            FROM $this->_dbSelected.$table
            WHERE mes = $mes AND annio = $annio");
            return $data[0]->NPS;
        }
        if($this->_dbSelected == 'customer_demo'){
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageBan AS NPS
            FROM $this->_dbSelected.$table
            WHERE mes = $mes AND annio = $annio");
            return $data[0]->NPS;
        }
        $data = DB::select("SELECT SUM(NPS) AS NPS FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
        COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
        (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageBan AS NPS
        FROM $this->_dbSelected.$table
        WHERE mes = $mes AND annio = $annio
        UNION
        SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
        COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
        (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1)*$this->_porcentageVid AS NPS
        FROM  $this->_dbSelected.$table2
        WHERE mes = $mes AND annio = $annio) AS A");
        return $data[0]->NPS;
    }
    
     
    private function AVGLast6MonthNPS($table,$table2,$dateIni,$dateEnd,$indicador, $filter){
        if($filter == 'all'){
            $data = DB::select("SELECT SUM(NPS) AS NPS FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS
                                FROM $this->_dbSelected.$table
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'
                                union
                            SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS
                                FROM $this->_dbSelected.$table2
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni') AS A");
        }
        
        if($filter != 'all'){
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                                COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
                                (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1) AS NPS
                                FROM $this->_dbSelected.$table
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
        //echo $datafilters;
        if($datafilters)
            $datafilters = " AND $datafilters";
        if($filter == 'all'){
            $table2 = $this->primaryTable($table);

             $data = DB::select("SELECT sum(NPS) AS NPS, SUM(total) as total, SUM(detractor) as detractor, SUM(promotor) AS promotor, SUM(neutral) AS neutral, AVG(promedio) AS promedio, $this->_fieldSelectInQuery 
                        FROM (SELECT COUNT(CASE WHEN $indicador != 99 THEN 1 END) as total,
                    	((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)*$this->_porcentageBan) as detractor, 
                        ((count(if($indicador > 8  AND $indicador<=10, $indicador,NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageBan) as promotor,
                    	((count(if($indicador = 7 OR $indicador = 8, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageBan) as neutral,
                    		AVG($indicador) as promedio ,a.mes, a.annio, date_survey,   
                    		ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
                    		COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
                    		COUNT(CASE WHEN $indicador!=99 THEN $indicador END) * 100),1)*$this->_porcentageBan AS NPS, $this->_fieldSelectInQuery 
                    		FROM $this->_dbSelected.$table as a
                    		LEFT JOIN $this->_dbSelected.".$table."_start as b
                    		on a.token = b.token
                    		WHERE a.mes = $mes AND a.annio = $annio $datafilters
                    		GROUP BY a.mes, a.annio
                    		UNION
                    		SELECT COUNT(CASE WHEN $indicador != 99 THEN 1 END) as total,
                    		((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)*$this->_porcentageVid) as detractor, 
                            ((count(if($indicador> 8 AND $indicador<=10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageVid) as promotor,
                    		((count(if($indicador = 7 OR $indicador = 8, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)*$this->_porcentageVid) as neutral,
            				AVG($indicador) as promedio ,a.mes, a.annio, date_survey,  
            				ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            				COUNT(CASE WHEN $indicador BETWEEN 0 AND 6 THEN 1 END)) /
            				COUNT(CASE WHEN $indicador!=99 THEN $indicador END) * 100),1)*$this->_porcentageVid AS NPS, $this->_fieldSelectInQuery
            				FROM $this->_dbSelected.$table2 as a
            				LEFT JOIN $this->_dbSelected.".$table2."_start as b
                    		on a.token = b.token
            				WHERE a.mes = $mes AND a.annio = $annio $datafilters
            				GROUP BY a.mes, a.annio
            				) AS A ");
        }
        
        if($filter != 'all'){
           
            $data = DB::select("SELECT count(*) as total, 
            ((count(if($indicador < 7, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador !=99 THEN 1 END)) as detractor, 
            ((count(if($indicador> 8 AND $indicador<=10, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)) as promotor,
    		((count(if($indicador = 7 OR $indicador = 8, $indicador, NULL))*100)/COUNT(CASE WHEN $indicador != 99 THEN 1 END)) as neutral,
            AVG($indicador) as promedio,
            ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                              (COUNT(CASE WHEN $indicador != 99 THEN $indicador END)) * 100),1) AS NPS,  $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$table as a
            LEFT JOIN $this->_dbSelected.".$table."_start as b
            on a.token = b.token
            WHERE a.mes = $mes AND a.annio = $annio $datafilters
            GROUP BY a.mes, a.annio
            ORDER BY date_survey ASC");

        }
        
        //var_dump($data);
        if(($data == null) || $data[0]->total === null){
            //echo 'paso';
               //if($data[0]->total== null || $data[0]->total== 0){
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
             //}
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
    }
    
    //OKK
    private function infoNps($table,$mes,$annio,$indicador,$filter)
    {
        $generalDataNps             = $this->resumenNps($table,$mes,$annio,$indicador,$filter);
        $generalDataNps['graph']    = $this->graphNps($table,$mes,$annio,$indicador,date('Y-m-d'), date('Y-m-d',strtotime(date('Y-m-d')."- 5 month")), $filter,'one');
        return $generalDataNps;
    }
    
    //OKK
    private function graphNps($table,$mes,$annio,$indicador,$dateIni, $dateEnd, $filter,$struct = 'two', $datafilters = null, $group =null){
        $table2 = $this->primaryTable($table);
     //echo $datafilters;   
     //echo 'group'.$group;
      //echo 'Ini: '.$dateIni.'End: '.$dateEnd;
        
        if($group !== null){
            //$where = " date_survey between date_sub(NOW(), interval 9 week) and NOW() and WEEK(date_survey) != 0 ";
            $where = $datafilters;
            $datafilters = '';
        }
       
        
        if($group === null){
            $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
            $group = " a.mes, a.annio ";
        } 
        
         //echo $where;
        if($datafilters)
            $datafilters = " AND $datafilters";

     // GROUP BY week
        $graphNPS   = [];
        //$indicador  = ($table=='adata_vid_web')?'nps':$indicador;
        if($filter != 'all'){
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
                INNER JOIN $this->_dbSelected.".$table."_start as b ON a.token = b.token 
                WHERE  $where $datafilters 
                GROUP BY $group
                ORDER BY date_survey ASC");
             
   
        }
        if($filter == 'all'){
            
            $indicador2 = $indicador;
          
            
            $data = DB::select("SELECT SUM(NPS) AS NPS, SUM(total) as total,SUM(detractor) as detractor,SUM(promotor) as promotor,SUM(neutral) as neutral, mes , annio, sum(Cdet) as Cdet, sum(Cpro) as Cpro, sum(Cneu) as Cneu, $this->_fieldSelectInQuery
                                FROM (SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                              (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS, 
                                count(if($indicador < 7, $indicador, NULL)) as Cdet,
					            count(if($indicador> 8 AND $indicador <=10, $indicador, NULL)) as Cpro,
					            count(if($indicador=8 OR $indicador=7, $indicador, NULL)) as Cneu,
                                count(*) as total, 
                                ((count(if($indicador < 7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as detractor, 
                                ((count(if($indicador > 8, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as promotor, 
                                ((count(if($indicador <= 8 AND $indicador >=7, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)*$this->_porcentageBan) as neutral, a.mes, a.annio,date_survey, $this->_fieldSelectInQuery
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
                                                    a.mes, a.annio,date_survey, $this->_fieldSelectInQuery
                FROM $this->_dbSelected.$table2 as a
                LEFT JOIN $this->_dbSelected.".$table2."_start as b ON a.token = b.token 
                WHERE $where $datafilters
                GROUP BY $group
                ) AS A GROUP BY mes, annio
                ORDER BY date_survey ASC");
                
        }
        
        //var_dump($group);
        foreach ($data as $key => $value) {
            if($struct != 'one'){
                $graphNPS[]=[ 
                    'xLegend'  => (trim($group) != 'week')?'Mes '.$value->mes.'-'.$value->annio.' ('.($value->Cdet + $value->Cpro + $value->Cneu).')' : 'Semana '.$value->week.' ('.($value->Cdet + $value->Cpro + $value->Cneu).')'  ,
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
    
    
    private function graphCsatMutual($table,$mes,$annio,$indicador,$dateIni, $dateEnd, $filter,$struct = 'two', $datafilters = null, $group =null){
        if($group !== null){
            //$where = " date_survey between date_sub(NOW(), interval 9 week) and NOW() and WEEK(date_survey) != 0 ";
            $where = $datafilters;
            $datafilters = '';
        }
       
        if($group === null){
            $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
            $group = " a.mes, a.annio ";
        } 
        
      
        if($datafilters)
            $datafilters = " AND $datafilters";

        // GROUP BY week
        $graphCsatM  = [];
       
        if($filter != 'all'){
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN 1 END) - 
                                               COUNT(CASE WHEN $indicador BETWEEN $this->_minCsat AND $this->_maxCsat THEN 1 END)) / 
                                              (COUNT($indicador) - COUNT(CASE WHEN $indicador=99 THEN 1 END)) * 100),1) AS CSAT, 
                                count(if($indicador < $this->_minMediumCsat, $indicador, NULL)) as Cdet,
					            count(if($indicador = $this->_minMaxCsat AND $indicador = $this->_maxMaxCsat, $indicador, NULL)) as Cpro,
					            count(if($indicador=$this->_maxMediumCsat, $indicador, NULL)) as Cneu,              
                                count(*) as total, 
                                ((count(if($indicador < $this->_minMediumCsat, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as detractor, 
                                ((count(if($indicador = $this->_minMaxCsat OR $indicador = $this->_maxMaxCsat, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as promotor, 
                                ((count(if($indicador=$this->_maxMediumCsat, $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as neutral,              
                                              a.mes, a.annio, WEEK(date_survey) AS week,$this->_fieldSelectInQuery  
                FROM $this->_dbSelected.$table as a
                INNER JOIN $this->_dbSelected.".$table."_start as b ON a.token = b.token 
                WHERE  $where $datafilters 
                GROUP BY $group
                ORDER BY date_survey ASC");
        }
        
         
        
         foreach ($data as $key => $value) {
            if($struct != 'one'){
                $graphCsatM[]=[ 
                    'xLegend'  => (trim($group) != 'week')?'Mes '.$value->mes.'-'.$value->annio.' ('.($value->Cdet + $value->Cpro + $value->Cneu).')' : 'Semana '.$value->week.' ('.($value->Cdet + $value->Cpro + $value->Cneu).')'  ,
                    'values' => [
                                    "promoters"     => round($value->promotor),
                                    "neutrals"      => round($value->neutral),
                                    "detractors"    => round($value->detractor),
                                    "nps"           => round($value->CSAT)
                                ],
                ];
            }
            // if($struct == 'one'){
            //   $graphNPS[] = [
            //       "value" => $value->NPS
            //       ];
            // }
        }
        return $graphCsatM;
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
            FROM $this->_dbSelected.$table
            WHERE mes = $monthAnt AND annio = $annio");
        }
        if($filter == 'all'){
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            $data = DB::select("SELECT SUM(CSAT) AS CSAT 
            FROM (SELECT ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageBan as CSAT
            FROM $this->_dbSelected.$table
            WHERE mes = $monthAnt AND annio = $annio and etapaencuesta = 'P2'
            UNION 
            SELECT ((COUNT(CASE WHEN $indicador BETWEEN 9 AND 10 THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageVid as CSAT
            FROM $this->_dbSelected.$table
            WHERE mes = $monthAnt AND annio = $annio and etapaencuesta = 'P2') AS A");
        }
        return $data[0]->CSAT;
    }
    
    

    
    

    //OKK
    private function resumenCsat($table,$mes,$annio,$indicador,$filter, $datafilters = null)
    {
        $table2 ='';
        if($datafilters)
            $datafilters = " AND $datafilters";
        
        if($filter != 'all'){
            $data = DB::select("SELECT count(*) as total,
            ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as csat, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$table as a
            INNER JOIN $this->_dbSelected.".$table."_start as b  ON a.token  =  b.token 
            WHERE a.mes = $mes AND a.annio = $annio $datafilters
            GROUP BY a.mes, a.annio");
        }
    
        
        if($filter == 'all'){
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            
            $data = DB::select("SELECT SUM(total) AS total, SUM(csat) AS csat, $this->_fieldSelectInQuery
            FROM (  SELECT count(*) as total, date_survey, a.mes, a.annio,
                     ((COUNT(CASE WHEN $indicador BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageBan as csat, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$table as a
            INNER JOIN $this->_dbSelected.".$table."_start as b  ON a.token  =  b.token 
            WHERE a.mes = $mes AND a.annio = $annio and etapaencuesta = 'P2' $datafilters
            GROUP BY a.mes, a.annio
            UNION
            SELECT count(*) as total, date_survey, a.mes, a.annio,
            ((COUNT(CASE WHEN $indicador2 BETWEEN $this->_minMaxCsat AND $this->_maxMaxCsat THEN $indicador2 END)*100)/COUNT(CASE WHEN $indicador2 != 99 THEN $indicador2 END))*$this->_porcentageVid as csat, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$table2 as a
            INNER JOIN $this->_dbSelected.".$table2."_start as b  ON a.token  =  b.token 
            WHERE a.mes = $mes AND a.annio = $annio and etapaencuesta = 'P2' $datafilters
            GROUP BY a.mes, a.annio) AS A ");
        }
        //print_r($data);
        $csatPreviousPeriod = $this->csatPreviousPeriod($table,$mes,$annio,$indicador,$filter);
        
        $csatActive = 0;
        
        if(($data == null) || $data[0]->total === null){
            $csatActive =  $csatActive;
            return [
            "name"          => "csat",
            "value"         => 'N/A',
            "percentage"    => $csatActive-$csatPreviousPeriod,
            //"smAvg"         => $csatActive-$csatPreviousPeriod,
            "smAvg"         => 0,
            
        ];
        }
        
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
    }
    
    private function infoCsat($table,$mes,$annio,$indicador)
    {
        $generalDataCsat            = $this->resumenCsat($table,$mes,$annio,$indicador,$this->_initialFilter);
        $generalDataCsat['graph']   = $this->graphCsat($table,$mes,$annio,$indicador,date('Y-m-d'), date('Y-m-d',strtotime(date('Y-m-d')."- 5 month")),$this->_initialFilter,'one');
        return $generalDataCsat;
    }


    private function graphCsat($table,$mes,$annio,$indicador,$dateIni, $dateEnd, $filter, $struct='two', $datafilters= null){
         if($datafilters)
            $datafilters = " AND $datafilters";
        //echo 'Ini: '.$dateIni.'End: '.$dateEnd;
        $graphCSAT = array();
        if($filter != 'all'){
            $data = DB::select("SELECT COUNT(if( $indicador >= 9, $indicador, NULL)* 100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END) AS csat, 
                                a.mes, a.annio, date_survey, $this->_fieldSelectInQuery 
                FROM $this->_dbSelected.$table as a
                INNER JOIN $this->_dbSelected.".$table."_start as b on a.token = b. token 
                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters
                GROUP BY a.mes
                ORDER BY date_survey DESC");
        }
        if($filter == 'all')
        {
            $table2 = $this->primaryTable($table);
            $indicador2 = $indicador;
            $data = DB::select("SELECT SUM(csat) as csat, mes, annio, date_survey FROM 
            (SELECT COUNT(if( $indicador >= 9, $indicador, NULL)* 100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END) AS csat, 
                            a.mes, a.annio, date_survey, $this->_fieldSelectInQuery  
            FROM $this->_dbSelected.$table as a
            INNER JOIN $this->_dbSelected.".$table."_start as b on a.token = b. token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters
            GROUP BY a.mes
            UNION
            SELECT COUNT(if( $indicador >= 9, $indicador, NULL)* 100)/COUNT(CASE WHEN $indicador != 99 THEN $indicador END) AS csat, 
                            a.mes, a.annio, date_survey, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$table2 as a
            INNER JOIN $this->_dbSelected.".$table2."_start as b on a.token = b. token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters
            GROUP BY a.mes
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
    
    public function getFielInDbCes($survey)
    {
        $cesInDb = 'ces';
        return $cesInDb;
    } 
    
    
    private function graphNpsBanVid($table,$table2,$mes,$annio,$indicador,$dateIni, $dateEnd, $datafilters){
     
         if($datafilters)
            $datafilters = " AND $datafilters";
            
            
            if($table2 != null){
        $graphNPSBanVid=[];
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
      		INNER JOIN $this->_dbSelected.".$table."_start as b on a.token = b.token
      		WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters	
      		Group BY a.mes
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
      		INNER JOIN $this->_dbSelected.".$table2."_start as b on a.token = b.token 
      				WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters Group BY a.mes ) as A 
            Group BY mes 
            ORDER BY date_survey
			ASC"); 
            
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
      		INNER JOIN $this->_dbSelected.".$table."_start as b on a.token = b.token
      		WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni'  $datafilters	
      		Group BY a.mes"); 
            }
                                
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
    
    private function detailGeneration($db,$indicatorNPS,$indicatorCSAT,$dateIni, $dateEnd, $filter, $datafilters = null){
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
    
        if($datafilters)
        $datafilters = " AND $datafilters";
        
         if($filter != 'all')
        {
            $data = DB::select("SELECT COUNT(*) as Total,  
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) AS NPS, 
            ROUND(COUNT(if($indicatorCSAT between 9 and 10, $indicatorCSAT, NULL)* 100)/COUNT(if($indicatorCSAT!= 99,1,null))) AS CSAT, age, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$db as a 
            LEFT JOIN $this->_dbSelected.".$db."_start as b on a.token = b.token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') $datafilters
            GROUP BY (b.age BETWEEN 14 AND 22), (b.age BETWEEN 23 AND 38), (b.age BETWEEN 39 AND 54), (b.age BETWEEN 55 AND 73), (b.age BETWEEN 74 AND 91)");
        }
        if($filter == 'all')
        {
            $db2    = $this->primaryTable($db);
            
           
             $data   = DB::select("SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, age, $this->_fieldSelectInQuery
                                        FROM (SELECT COUNT(*) as Total, b.age,
                                        ROUND((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                        COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                        COUNT(CASE WHEN a.$indicatorNPS!=99 THEN 1 END) * 100)*$this->_porcentageBan AS NPS,
                                        ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS CSAT, $this->_fieldSelectInQuery
                                        FROM $this->_dbSelected.$db as a
                                        LEFT JOIN $this->_dbSelected.".$db."_start as b on a.token = b.token 
                                              WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') $datafilters
                                        GROUP BY (b.age BETWEEN 14 AND 22), (b.age BETWEEN 23 AND 38), (b.age BETWEEN 39 AND 54), (b.age BETWEEN 55 AND 73), (b.age BETWEEN 74 AND 91)
                                    UNION
                                    SELECT COUNT(*) as Total, b.age,
                                        ROUND((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) - 
                                        COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) / 
                                        COUNT(CASE WHEN a.$indicatorNPS!=99 THEN 1 END) * 100)*$this->_porcentageVid AS NPS,
                                        ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid AS CSAT, $this->_fieldSelectInQuery
                                        FROM $this->_dbSelected.$db2 as a
                                        LEFT JOIN $this->_dbSelected.".$db2."_start as b on a.token = b.token WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND sex in(1,2,'F','M') $datafilters
                                        GROUP BY (b.age BETWEEN 14 AND 22), (b.age BETWEEN 23 AND 38), (b.age BETWEEN 39 AND 54), (b.age BETWEEN 55 AND 73), (b.age BETWEEN 74 AND 91)) AS A
                                        GROUP BY (age BETWEEN 14 AND 22), (age BETWEEN 23 AND 38), (age BETWEEN 39 AND 54), (age BETWEEN 55 AND 73), (age BETWEEN 74 AND 91)
                                        ORDER BY age");
         }
         
          
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
            $data = DB::select("SELECT COUNT(*) as Total, 
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) AS NPS, 
            ROUND(COUNT(if( $indicatorCSAT >= 9, $indicatorCSAT, NULL)* 100)/COUNT(if($indicatorCSAT !=99,1,NULL ))) AS CSAT, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$db as a 
            LEFT JOIN $this->_dbSelected.".$db."_start as b on a.token = b.token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND $this->_fieldSex in(1,2,'F','M')  $datafilters
            GROUP BY $this->_fieldSex");
        }
        if($filter == 'all')
        {
            $db2  = $this->primaryTable($db);
            //$indicador2 = ($db2 == 'adata_vid_web')?'nps':$indicador;
           
            $data   = DB::select("SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, $this->_fieldSelectInQuery
            FROM (SELECT COUNT(*) as Total, 
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1) *$this->_porcentageBan AS NPS, 
            ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageBan AS CSAT, $this->_fieldSelectInQuery
            FROM $this->_dbSelected.$db as a 
            LEFT JOIN $this->_dbSelected.".$db."_start as b on a.token = b.token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters
            GROUP BY sex
            UNION
            SELECT COUNT(*) as Total, 
            ROUND(((COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
            COUNT(CASE WHEN a.$indicatorNPS BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
            (COUNT(a.$indicatorNPS) - COUNT(CASE WHEN a.$indicatorNPS=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS, 
            ROUND(COUNT(if($indicatorCSAT between  9 and  10 , $indicatorCSAT, NULL))* 100/COUNT(if($indicatorCSAT !=99,1,NULL )))*$this->_porcentageVid AS CSAT, $this->_fieldSelectInQuery 
            FROM $this->_dbSelected.$db2 as a 
            LEFT JOIN $this->_dbSelected.".$db2."_start as b on a.token = b.token 
            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' $datafilters
            GROUP BY sex) AS A
            GROUP BY sex
            " );
        }
        
        foreach ($data as $key => $value) {
            $sex = (string)$this->_fieldSex;
                if($value->$sex =='M' || $value->$sex =='1'){
                    $quantityM=$value->Total;
                    $csatM =$value->CSAT;
                    $npsM=$value->NPS;
                } 
                if($value->$sex =='F' ||  $value->$sex =='2'){
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
    
    private function GraphCSATDrivers($db,$db2, $survey, $indicatorCSAT,  $dateEnd,$dateIni, $filter, $struct='two', $datafilters = null){

        $graphCSAT = [];
        
        $endCsat = $this->getEndCsat($survey);
        
       
        //var_dump($survey);
        $fieldBd = $this->getFielInDbCsat($survey);
        $fieldBd2 = $this->getFielInDbCsat($survey);
       // $fieldBd2 = ($db == 'adata_ban_web')?'csat':$fieldBd;
        $query = "";
        $query2 = "";
        $select = "";
        if($datafilters)
            $datafilters = " AND $datafilters";
            
         if($filter == 'all'){
            $fieldBd = $this->getFielInDbCsat($survey);
            $query = "";
            for ($i=1; $i <= $endCsat; $i++) {
                $select .= " ROUND(SUM(csat$i)) AS csat$i, SUM(detractor$i) AS detractor$i, SUM(promotor$i) AS promotor$i, SUM(neutral$i) AS neutral$i,";
                 if($i != $endCsat){
                    $query .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageBan AS csat$i,
                                ((count(if(csat$i <=  $this->_maxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageBan) as detractor$i, 
                                ((count(if(csat$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageBan) as promotor$i, 
                                ((count(if(csat$i <= $this->_maxMediumCsat AND csat$i >= $this->_minMediumCsat, csat$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)*$this->_porcentageBan) as neutral$i,";
                }
                
                if($i == $endCsat){
                    $select .= " ROUND(SUM(csat$i)) AS csat$i, SUM(detractor$i) AS detractor$i, SUM(promotor$i) AS promotor$i, SUM(neutral$i) AS neutral$i ";
                    $query .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat  OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageBan AS csat$i, 
                                    ((count(if(csat$i <=  $this->_maxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageBan) as detractor$i, 
                                    ((count(if(csat$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageBan) as promotor$i, 
                                    ((count(if(csat$i <= $this->_maxMediumCsat AND csat$i >= $this->_minMediumCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageBan) as neutral$i ";
                }
            }
            
            for ($i=1; $i <= $endCsat; $i++) {
                 if($i != $endCsat){
                    $query2 .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat  OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageVid  AS csat$i, 
                                    ((count(if(csat$i <=  $this->_maxCsat, csat$i, NULL))*100))/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageVid as detractor$i, 
                                    ((count(if(csat$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageVid) as promotor$i, 
                                    ((count(if(csat$i <= $this->_maxMediumCsat AND csat$i >= $this->_minMediumCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageVid) as neutral$i,";
                }
                
                if($i == $endCsat){
                    $query2 .= " ((COUNT(if($fieldBd$i = $this->_minMaxCsat  OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageVid  AS csat$i, 
                                    ((count(if(csat$i <=  $this->_maxCsat, csat$i, NULL))*100))/count(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageVid as detractor$i, 
                                    ((count(if(csat$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageVid) as promotor$i, 
                                    ((count(if(csat$i <= $this->_maxMediumCsat AND csat$i >= $this->_minMediumCsat, csat$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL))*$this->_porcentageVid) as neutral$i ";
                }
            }
            
            $query1 = "SELECT $query,date_survey,  $this->_fieldSelectInQuery
                        FROM $this->_dbSelected.$db as A
                        LEFT JOIN $this->_dbSelected.".$db."_start as b
                        on A.token = b.token
                        WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' $datafilters";
                                
            $query2 = "SELECT $query2,date_survey,  $this->_fieldSelectInQuery
                        FROM $this->_dbSelected.$db2 as A
                        LEFT JOIN $this->_dbSelected.".$db2."_start as b
                        on A.token = b.token 
                        WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' $datafilters";
                                
            $queryPrin = "SELECT $select,$this->_fieldSelectInQuery FROM ($query1 UNION $query2) as A ORDER BY date_survey";
           
            $data = DB::select($queryPrin);
        }
        if($filter != 'all'){
            $fieldBd = $this->getFielInDbCsat($survey);
           
            $query = "";
            for ($i=1; $i <= $endCsat; $i++) {
            
                if($i != $endCsat){
                    $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                ((count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                ((count(if( $fieldBd$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat,  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                ((count(if( $fieldBd$i <= $this->_maxMediumCsat AND  $fieldBd$i >= $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN   $fieldBd$i END)) as neutral$i,";
                }
                if($i == $endCsat){
                    $query .= " (COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                ((count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                ((count(if( $fieldBd$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat,  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                ((count(if( $fieldBd$i <= $this->_maxMediumCsat AND  $fieldBd$i >= $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN  $fieldBd$i END)) as neutral$i ";
                }
                
            }
            
            $data = DB::select("SELECT $query,date_survey
                                FROM $this->_dbSelected.$db as A
                                LEFT JOIN $this->_dbSelected.".$db."_start as b
                                on A.token = b.token 
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' $datafilters
                                ORDER BY date_survey");
        }

        
        $suite = new Suite($this->_jwt);
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
                            "csat"          => (int)$csat
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
    
    private function GraphCSATDriversMutual($db,$db2, $survey, $indicatorCSAT,$dateEnd,$dateIni, $filter, $struct='two', $datafilters = null, $IniDateMonth,$group){
        $graphCSAT = [];
   
       
        // if($group !== null){
        //     //$where = " date_survey between date_sub(NOW(), interval 9 week) and NOW() and WEEK(date_survey) != 0 ";
        //     $where = $datafilters;
        //     $datafilters = '';
        // }
       
        
        // if($group === null){
        //     $where = " date_survey BETWEEN '$dateEnd' AND '$dateIni' ";
        //     $group = " a.mes ";
        // } 
        

        $endCsat = $this->getEndCsat($survey);
       
        $fieldBd = $this->getFielInDbCsat($survey);
        //$fieldBd2 = $this->getFielInDbCsat($survey);
       
        $query = "";
        $query2 = "";
        $select = "";
        if($datafilters)
            $datafilters = " AND $datafilters";
            
         if($filter != 'all'){
            $fieldBd = $this->getFielInDbCsat($survey);
           
            $query = "";
            for ($i=1; $i <= $endCsat; $i++) {
            
                if($i != $endCsat){
                    $query .= " ((COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL))- count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL)))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                ((count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                            ((count(if( $fieldBd$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat,  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                            ((count(if( $fieldBd$i <= $this->_maxMediumCsat AND  $fieldBd$i >= $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN   $fieldBd$i END)) as neutral$i,";
                }
                if($i == $endCsat){
                    $query .= " ((COUNT(if( $fieldBd$i = $this->_minMaxCsat OR $fieldBd$i = $this->_maxMaxCsat, $fieldBd$i, NULL)) - count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL)))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                            ((count(if( $fieldBd$i < $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when csat$i != 99 THEN  csat$i END)) as detractor$i, 
                                            ((count(if( $fieldBd$i > $this->_maxMediumCsat AND $fieldBd$i <= $this->_maxMaxCsat,  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                            ((count(if( $fieldBd$i <= $this->_maxMediumCsat AND  $fieldBd$i >= $this->_minMediumCsat,  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN  $fieldBd$i END)) as neutral$i ";
                }
            }
            
            $data = DB::select("SELECT $query,date_survey, WEEK(date_survey) AS week, a.mes
                                FROM $this->_dbSelected.$db as a
                                LEFT JOIN $this->_dbSelected.".$db."_start as b
                                on a.token = b.token 
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' $datafilters
                                ORDER BY date_survey");
                                
                    

        }
        
        $suite = new Suite($this->_jwt);
        foreach ($data as $key => $value) {
            for ($i=1; $i <= $endCsat; $i++) { 
                $r   = 'csat'.$i;
                $pro = 'promotor'.$i;
                $neu = 'neutral'.$i;
                $det = 'detractor'.$i;
                $csat = $value->$r;
                if($struct == 'two'){
                    $graphCSAT[] = ['xLegend' => $suite->getInformationDriver($survey.'_'.$r),
                    'values' =>
                        [
                            "promoters"     => (int)ROUND($value->$pro),
                            "neutrals"      => (int)ROUND($value->$neu),
                            "detractors"    => (int)ROUND($value->$det),
                            "csat"          => (int)ROUND($csat)
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
        //echo "SELECT nomSurvey FROM $this->_dbSelected.survey WHERE codDbase = '$name'";
        $data = DB::select("SELECT nomSurvey FROM $this->_dbSelected.survey WHERE codDbase = '$name'");
        //print_r($data);
        //echo "SELECT nomSurvey FROM $this->_dbSelected.survey WHERE codDbase = 'trim($name)'";
        return $data[0]->nomSurvey;
    }
    
    private function closedLoop($db, $indicador,$dateEnd, $dateIni, $filter, $datafilters = null){
   
        $db2     = $this->primaryTable($db);
          if($datafilters)
            $datafilters = " AND $datafilters";
            
 
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
                            COUNT(IF(nps = 5 OR nps = 6, $indicador , null )) as high, $this->_fieldSelectInQuery 
                            FROM $this->_dbSelected.$db as a 
                            LEFT JOIN $this->_dbSelected.".$db."_start as b on (a.token = b.token) 
                            WHERE nps in(0,1,2,3,4,5,6) AND etapaencuesta = 'P2' AND $this->_obsNps != '' AND date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters
                            ORDER BY date_survey DESC");
        }
        if($filter == 'all'){
            
            //$indicador2 = ($db2 == 'adata_vid_web')?'nps':$indicador;
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
                                LEFT JOIN $this->_dbSelected.".$db."_start as b on (a.token = b.token) 
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
                                LEFT JOIN $this->_dbSelected.".$db2."_start as b on (a.token = b.token) 
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
                "height"=> 4,
                "width"=> 8,
                "type"=> "summary",
                "props"=> [
                  "icon"=> "arrow-right",
                  "text"=> "Close The Loop",
                //   "callToAction"=> [
                //     "text"=> "Ir a la suite",
                //     "icon"=> "arrow-right",
                //     "url"=> "https://www.suite.customerscoops.app/",
                //   ],
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
    
    
    private function imagen($client, $filterClient,$nameEncuesta, $table = null){
        //echo '--'.$filterClient.'-----'.$client;
        if($client == 'ban' &&  $filterClient != 'all'){
           return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageBan'/></span></div>";
        }
        if($client == 'vid' &&  $filterClient != 'all'){
           return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageVid'/></span></div>";
        }
        if($client == 'mut' &&  $filterClient != 'all'){
            return   "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageClient'/></span></div>";
        }
        if($table == 'MUT001_mutcon_resp'){
            return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard Consolidado !</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px'  src='$this->_imageClient'/></span></div>";
        }
        return  "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span>¡Este es tu Dashboard Consolidado !</span><span style='display:flex; justify-content:flex-start;align-items:center; gap:10px; margin-top:10px'><img width='120px' src='$this->_imageBan'/><img width='120px' src='$this->_imageVid'/></span></div>";
    }
    
    private function getDetailsForIndicator($db, $db2,$month,$year,$npsInDb,$csatInDb, $dateIni, $dateEnd, $fieldFilter, $datafilters = null)
    {
        $db2     = $this->primaryTable($db);
          if($datafilters)
            $datafilters = " AND $datafilters";
        
        $query = "SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, $this->_fieldSelectInQuery
        FROM (SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageBan AS CSAT,  $this->_fieldSelectInQuery
        FROM $this->_dbSelected.$db as a
        LEFT JOIN $this->_dbSelected.".$db."_start as b on a.token = b.token
              WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters
        GROUP BY $fieldFilter
        UNION
        SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageVid AS CSAT,  $this->_fieldSelectInQuery
        FROM $this->_dbSelected.$db2 as a
        LEFT JOIN $this->_dbSelected.".$db2."_start as b on a.token = b.token 
        WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters
        GROUP BY $fieldFilter) AS A GROUP BY $fieldFilter";
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
    
    private function ces($db, $datafilters, $mes, $annio, $survey){
        $data = null;
        
        $ces =  $this->getFielInDbCes($survey);
        //echo $survey;
        $str = substr($survey,3,6);
        if($str === 'ges' || $str === 'eri'){
        $data = DB::select("SELECT COUNT(*) as Total,
        (COUNT(if($ces between  6 and  7 , $ces, NULL)) - COUNT(if($ces between  1 and  4 , $ces, NULL)))/COUNT(if(ces !=99,1,NULL ))* 100 AS CES 
        FROM $this->_dbSelected.$db as a
        WHERE mes = $mes AND annio = $annio");
        }
      
        if($data == null){
            return [
            "name"          => "CES",
            "value"         => "N/A",
            ];
        }
        
        
        if($data[0]->Total != null){
            return [
            "name"          => "CES",
            "value"         => ROUND($data[0]->CES),
        ];
        }
        
        if($data[0]->Total == null){
            return [
            "name"          => "CES",
            "value"         => "N/A",
        ];
        }
    }

   
    private function getDetailsAntiquity($db, $db2,$month,$year,$npsInDb,$csatInDb, $dateIni, $dateEnd,$fieldFilter, $datafilters = null)
    {
        $db2     = $this->primaryTable($db);
          if($datafilters)
            $datafilters = " AND $datafilters";
              
        $query = "SELECT SUM(Total) as Total, SUM(NPS) AS NPS, sum(CSAT) AS CSAT, $fieldFilter, $this->_fieldSelectInQuery
        FROM (SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageBan AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageBan AS CSAT,  $fieldFilter, $this->_fieldSelectInQuery
        FROM $this->_dbSelected.$db as a
        LEFT JOIN $this->_dbSelected.".$db."_start as b on a.token = b.token
              WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters
        GROUP BY ($fieldFilter BETWEEN 0 AND 1), ($fieldFilter BETWEEN 1 AND 2),($fieldFilter BETWEEN 2 AND 5),($fieldFilter BETWEEN 5 AND 100)
        UNION
        SELECT COUNT(*) as Total,
        ROUND(((COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minMaxNps AND $this->_maxMaxNps THEN 1 END) -
        COUNT(CASE WHEN a.$npsInDb BETWEEN $this->_minNps AND $this->_maxNps THEN 1 END)) /
        (COUNT(a.$npsInDb) - COUNT(CASE WHEN a.$npsInDb=99 THEN 1 END)) * 100),1)*$this->_porcentageVid AS NPS,
        ROUND(COUNT(if($csatInDb between  9 and  10 , $csatInDb, NULL))* 100/COUNT(if($csatInDb !=99,1,NULL )))*$this->_porcentageVid AS CSAT,  $fieldFilter, $this->_fieldSelectInQuery
        FROM $this->_dbSelected.$db2 as a
        LEFT JOIN $this->_dbSelected.".$db2."_start as b on a.token = b.token 
        WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' $datafilters
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
    
    private function detailsProcedencia($db,$endDate, $startDate,$filterClient){
        if($filterClient != 'all'){
            //echo "SELECT count(*) as procedencia, procedencia FROM $this->_dbSelected.".$db."_start where procedencia != '' and procedencia != '-' group by procedencia";
            $data = DB::select("SELECT count(*) as procedencia, procedencia FROM $this->_dbSelected.".$db."_start where procedencia != '' and procedencia != '-' group by procedencia");
            print_r($data);
              if($data == null){
                return [
                "name"          => "CES",
                "value"         => "N/A",
                ];
            }
        }
        
    }
    
    private function graphProcedencia($db, $startDateFilterMonth, $endDateFilterMonth ,$filterClient){
        $dataProcedencia = $this->detailsProcedencia($db, $startDateFilterMonth, $endDateFilterMonth ,$filterClient);
        
        $standarStruct = [
            [
                "text"=> "Cantidad de respuesta",
                "key"=> "nps",
                "cellColor"=> "#17C784"
            ],
            [
                "text"=> "%",
                "key"=> "csat",
                "cellColor"=> "#17C784"
            ]
        ];
        return [
            "height"=>  9,
            "width"=>  14,
            "type"=>  "tables",
            "props"=>  [
                "icon"=> "arrow-right",
                "text"=> "Procedencia",
                "tables"=>[
                            [
                                "columns"=> [
                                                [
                                                    "text"=> "Procedencia",
                                                    "key"=> "sections",
                                                    "headerColor"=> "#17C784",
                                                    "cellColor"=> "#949494",
                                                    "textAlign"=> "left"
                                                ],
                                            $standarStruct[0],
                                       
                                            ],
                                "values"=> $dataProcedencia,
                            ],
                        ]
                    ]
            ];
    } 
    
    private function statsByTaps($db, $db2,$mes,$year,$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth,$datafilters = null)
    {
        //TODO 
        //FILTRAR POR UNA ENCUESTA
        $datasTramos = $this->getDetailsForIndicator($db, $db2,date('m'),date('Y'),$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'tramo',$datafilters);
        //print_r($datasTramos);
        $datasNichos = $this->getDetailsForIndicator($db, $db2,date('m'),date('Y'),$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth, 'nicho',$datafilters);
        $datasAntiguedad = $this->getDetailsAntiquity($db, $db2,date('m'),date('Y'),$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth,'antIsapre', $datafilters);
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
        
        //BANMEDICA
        $where .= $this->structfilter($request, 'sex',    'genero',   $where);
        $where .= $this->structfilter($request, 'region', 'regiones', $where);
        $where .= $this->structfilter($request, 'nicho',  'nicho',    $where);
        $where .= $this->structfilter($request, 'tramo',  'tramo',    $where);
        $where .= $this->structfilter($request, 'nomSuc', 'sucursal', $where);
        
        //MUTUAL
        $where .= $this->structfilter($request, 'macroseg',   'Macrosegmento',     $where);
        $where .= $this->structfilter($request, 'tatencion',  'ModalidadAtencion', $where);
        $where .= $this->structfilter($request, 'tipcliente', 'TipoCliente',       $where);
        $where .= $this->structfilter($request, 'canal',      'Canal',             $where);
        $where .= $this->structfilter($request, 'tatencion',  'TipoAtencion',      $where);
        $where .= $this->structfilter($request, 'catencion',  'CentroAtencion',    $where);
        
        return $where;
    }
    
    private function cardsPerformace($dataNps,$dataCsat)
    {
            $name= $dataCsat['name'];
            $val = $dataCsat['value'];
            $percentage=  (int)$dataCsat['percentage'];
        
        if($this->_dbSelected == 'customer_colmena'){
            $name= 'CES';
            $val = 'N/A';
            $percentage= 0;
        }
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
                          "m2m"     => (int)$dataNps['percentage'],
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
    private function welcome($client, $filterClient,$bd, $table = null){
        // echo $bd;
        
        $nameEncuesta = ucwords(strtolower($this->nameSurvey(trim($bd))));
        // echo $nameEncuesta;
        return [
            "height" =>  1,
            "width" =>  6,
            "type" =>  "welcome",
            "props" =>  [
                "icon"=> "smile",
                "text"=> $this->imagen($client, $filterClient, $nameEncuesta, $table),
                //"text"=> "<div style='display:flex; flex-direction:column'><span><span style='color:rgb(23, 199, 132)'>Hola</span> ¡Este es tu Dashboard de la Encuesta $nameEncuesta!</span><span style='display:flex; justify-content:flex-start; align-items:center; gap:10px; margin-top:10px'><img width='120px' src='https://customerscoops.com/assets/companies-images/bm_logo.svg'/><img width='120px' src='https://customerscoops.com/assets/companies-images/vidatres_logo.svg'/></span></div>",
                
            ],
        ];
    }

    private function cardNpsConsolidado($name, $dataNPSGraphBanVid, $ButFilterWeeks){
    return [
            "height"=> 4,
             "width"=> 12,
             "type"=> "chart",
             "props"=> [
                "callToAction"=> $ButFilterWeeks,
                "icon"=> "arrow-right",
                "text"=> "NPS Consolidado • $name",
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
        ];   
    }
    
    private function cardCsatDriversMutual($csat,$name,$graphCsatM, $ButFilterWeeks){
        return [
            "height"=>3,
            "width"=>6,
            "type"=>"chart",
            "props"=>[
                "callToAction"=> $ButFilterWeeks,
                "icon"=>"arrow-right",
                "text"=>$csat." • ".$name,
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
                    "values"=>$graphCsatM
                    ],
            ],
        ];
    }
    




    private function cardNpsBanmedica($nameIndicatorPrincipal, $dataNPSGraph){
    return [
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
    ];
    }

    private function cardNpsVidaTres($nameIndicatorPrincipal2, $dataNPSGraph2){
    return [
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
    ];
    }

    private function CSATJourney($graphCSATDrivers){
    return [
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
            ];
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
        
       
        $group =  null;
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
        if($request->filterWeeks !== null ){
            //var_dump($datafilters);
            $interval = is_numeric($request->filterWeeks)? $request->filterWeeks : 10;
            if($datafilters != ''){
            
                $datafilters.= ' and date_survey between date_sub(NOW(), interval 10 week) and NOW() ';
                $group = " week ";
            }
            if($datafilters == ''){
                $datafilters.= ' date_survey between date_sub(NOW(), interval 10 week) and NOW() ';
                $group = " week ";
            }
        }
      
        
        
        $filterClient  = ($request->client === null)? $this->_initialFilter : $request->client;
        
        //echo  $request->client;
        //echo $filterClient;
        $indetifyClient = substr($request->survey,0,3);
        $indetifyClient = ($filterClient == 'all') ? $indetifyClient:$filterClient;
        
        $npsInDb    = $this->getFielInDb($request->survey);
        
        $csatInDb   = $this->getFielInDbCsat($request->survey);
        //$csatInDb = ($csatInDb=='p')?'p3':$csatInDb;
        $db         = 'adata_'.trim(substr($request->survey,0,3)).'_'.trim(substr($request->survey,3,6));
        
        
        $tipeSurvey = $request->survey;
        $clientes = null;
         
        if(str_contains($tipeSurvey, "hos") || str_contains($tipeSurvey, 'urg') || str_contains($tipeSurvey, 'amb') || str_contains($tipeSurvey, 'img') || str_contains($tipeSurvey, 'reh'))
        {
            $db = 'MUT001_mutcon_resp';
            if($request->client){
                $db = 'adata_'.trim(substr($request->client,0,3)).'_'.trim(substr($request->client,3,6));
            }
            $clientes = [
                        "Ambulatorio"=>"mutamb",
                        "Imagenología"=>"mutimg",
                        "Urgencias"=>"muturg",
                        "Rehabilitación"=>"mutreh",
                        "Hospitalización"=>"muthos"
            ]; 
        }
      
        


        $Procendecia=null;
        $csat1 = null;
        $csat2 = null;
        $indicatordb = ($indetifyClient == 'vid')?'ban':'vid';
        $nameIndicatorPrincipal  = ($indetifyClient == 'vid')?'Vida Tres':'Banmédica';   //banmedica
        $nameIndicatorPrincipal2 = ($indetifyClient == 'vid')?'Banmédica':'Vida Tres';  //vidatres
        
        $dbVT       = 'adata_'.$indicatordb.'_'.substr($request->survey,3,6);
        //echo $nameIndicatorPrincipal2;

        //OKK
        //echo '---'.$filterClient.'-----'.$this->_dbSelected.'-----'.$this->_initialFilter ;
        $dataNps    = $this->resumenNps($db,date('m'),date('Y'),$npsInDb, $filterClient, $datafilters);
       
        //print_r ($dataNps);exit;
        //OKK
        $dataCsat   = $this->resumenCsat($db,date('m'),date('Y'),$csatInDb, $filterClient, $datafilters);
        
        //OKK
        $dataCsatGraph   = $this->graphCsat($db,date('m'),date('Y'),$csatInDb,$endDateFilterMonth,$startDateFilterMonth,  $filterClient, $datafilters);
       
        //OKK
        //echo $datafilters;
        if($this->_dbSelected  == 'customer_banmedica'){
            $name =  $nameIndicatorPrincipal.' & '.$nameIndicatorPrincipal2;   
            $db2 = ($indetifyClient=='vid')?'adata_ban_'.trim(substr($request->survey,3,6)):'adata_vid_'.trim(substr($request->survey,3,6));
            
            $dataNPSGraph         = $this->graphNps($db,date('m'),date('Y'),$npsInDb,$dateIni, $dateEnd,'one', 'two', $datafilters, $group);
            $dataNPSGraph2        = $this->graphNps($dbVT,date('m'),date('Y'),$npsInDb,$dateIni, $dateEnd,'one', 'two', $datafilters, $group);
            $dataNPSGraphBanVid   = $this->graphNpsBanVid($db, $db2,date('m'),date('Y'),$npsInDb, $dateIni, $dateEnd, $datafilters);
            $graphCSATDrivers     = $this->GraphCSATDrivers($db, $db2, trim($request->survey), $csatInDb,$endDateFilterMonth, $startDateFilterMonth,  'all','two', $datafilters);
            $datasStatsByTaps     = $this->statsByTaps($db, $db2,date('m'),date('Y'),$npsInDb,$csatInDb, $startDateFilterMonth, $endDateFilterMonth, $datafilters);
        
            $welcome            = $this->welcome($indetifyClient, $filterClient, $request->survey);
            $performance        = $this->cardsPerformace($dataNps, $dataCsat);
            $npsConsolidado     = $this->cardNpsConsolidado($name, $dataNPSGraphBanVid,  $this->ButFilterWeeks);
            $npsBan             = $this->cardNpsBanmedica($nameIndicatorPrincipal, $dataNPSGraph);
            $npsVid             = $this->cardNpsVidaTres($nameIndicatorPrincipal2, $dataNPSGraph2);
            $csatJourney        = $this->CSATJourney($graphCSATDrivers);
            $csatDrivers        = $this->CSATDrivers($graphCSATDrivers);
            $cx                 = $this->cxIntelligence($request);
            $wordCloud          = $this->wordCloud($request);
            $closedLoop         = $this->closedLoop($db, $npsInDb, $endDateFilterMonth, $startDateFilterMonth, $filterClient, $datafilters);
            $detailGender       = $this->detailsGender($db, $npsInDb, $csatInDb,$endDateFilterMonth, $startDateFilterMonth,  $filterClient, $datafilters);
            $detailGeneration   = $this->detailGeneration($db, $npsInDb, $csatInDb,$endDateFilterMonth, $startDateFilterMonth, $filterClient,  $datafilters);
            $detailsProcedencia = null;
        }
        
        if($this->_dbSelected  != 'customer_banmedica'){
            $name = 'Mutual';
            $nameCsat1 = 'Tiempo espera para tu atención';
            $nameCsat2 = 'Amabilidad profesionales';
            
            $dataCes              = $this->ces($db, $datafilters, date('m'),date('Y'), $request->survey);
            $dataNPSGraph         = $this->graphNps($db,date('m'),date('Y'),$npsInDb,$dateIni, $dateEnd,'one', 'two', $datafilters, $group);
            $dataCsat1Graph       = $this->graphCsatMutual($db,date('m'),date('Y'),'csat1',$dateIni, $dateEnd,'one', 'two', $datafilters, $group);
            $dataCsat2Graph       = $this->graphCsatMutual($db,date('m'),date('Y'),'csat2',$dateIni, $dateEnd,'one', 'two', $datafilters, $group);
            $graphCSATDrivers     = $this->GraphCSATDriversMutual($db, null, trim($request->survey), $csatInDb,$endDateFilterMonth, $startDateFilterMonth, 'one','two', $datafilters, $dateEnd, $group);
            $datasStatsByTaps     = null;
            
            
            
            if ($db == 'adata_mut_amb' ||  $db == 'adata_mut_urg' ||  $db == 'adata_mut_reh'){
                $csat1 = $this->cardCsatDriversMutual($nameCsat1,$name, $dataCsat1Graph, $this->ButFilterWeeks);
                $csat2 = $this->cardCsatDriversMutual($nameCsat2,$name, $dataCsat2Graph, $this->ButFilterWeeks);
            }
            if($db = 'adata_mut_img'){
               $Procedencia = $this->detailsProcedencia($db,$endDateFilterMonth, $startDateFilterMonth,$filterClient);
            }
            
            $welcome            = $this->welcome(($request->client!== null)?'mut':$request->client, $filterClient, ($request->client!== null)?$request->client: $request->survey, $db);
            $performance        = $this->cardsPerformace($dataNps, $dataCsat);
            $npsConsolidado     = $this->cardNpsConsolidado($name, $dataNPSGraph, $this->ButFilterWeeks);
            $npsBan             = null;
            $npsVid             = null;
            $csatJourney        = $this->CSATJourney($graphCSATDrivers);
            $csatDrivers        = $this->CSATDrivers($graphCSATDrivers);;
            $cx                 = null;
            $wordCloud          = null;
            $closedLoop         = $csat1;
            $detailGender       = $csat2;
            $detailGeneration   = $this->closedLoop($db, $npsInDb, $endDateFilterMonth, $startDateFilterMonth, $filterClient, $datafilters);
            $detailsProcedencia = $Procendecia;
        }

        
        $filters = $this->filters($request, $jwt);
        //print_r($filters);
        //$data = [$this->filters($request, $jwt)][0];
        
       
        $data = [
            'client' => $this->_nameClient,
            'clients'=> isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: $clientes,
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
                    ]
                ];
        return [
            'datas'     => $data,
            'status'    => Response::HTTP_OK
        ];
    }
    
    private function setDetailsClient($client)
    {
        if($client == 'VID001' || $client == 'BAN001')
        {
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
            if($client == 'VID001'){$this->_nameClient = 'Vida Tres';}             
            if($client == 'BAN001'){$this->_nameClient = 'Banmedica'; }
            $this->_renderInfo = [    
            ];
            $this->ButFilterWeeks       = [["text"=>"Anual", "key"=>"filterWeeks", "value"=>""],["text"=>"Semanal", "key"=>"filterWeeks", "value"=>"10"]];
        }
        
        if($client == 'MUT001'){
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
            $this->ButFilterWeeks       = [["text"=>"Anual", "key"=>"filterWeeks", "value"=>""],["text"=>"Semanal", "key"=>"filterWeeks", "value"=>"10"]];
        }
        if($client == 'DEM001'){
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
    }
}
