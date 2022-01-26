<?php namespace App;

use Validator;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use DB;
use App\Suite;
use App\Dashboard;

class PeriodCompare
{
    private $_porcentageBan = 0.77;
    private $_porcentageVid = 0.23;
    
    
      public function csatPreviousPeriod($db,$db2, $survey,$mes,$annio,$indicator){
        $dash = new Dashboard;
        $endCsat = $dash->getEndCsat($survey);
        $fieldBd = $dash->getFielInDbCsat($survey);
        
        $monthAnt = $mes-1;
        if($monthAnt == 0){
            $mes = 12;
            $annio = $annio-1;
        }
        
        
        for ($i=1; $i <= $endCsat; $i++) {
        $data = DB::select("SELECT ROUND(SUM(csat$i)) AS csat FROM (SELECT ((COUNT(if($fieldBd$i = 9 OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($indicator !=99,1,NULL )))*$this->_porcentageBan AS csat$i
        FROM customer_banmedica.$db
        WHERE mes = $mes AND annio = $annio
        UNION
        SELECT ((COUNT(if($fieldBd$i = 9 OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($indicator !=99,1,NULL )))*$this->_porcentageBan AS csat$i
        FROM customer_banmedica.$db2
        WHERE mes = $mes AND annio = $annio) AS A");
        //var_dump($data[0]->csat);
        return $data[0]->csat;
           
        }
       
    }
    
    
    public function GetPeriod($request, $jwt){
        $struct='two';
        $filter='all';
        $period = $request->get('period');
        if (!isset($period)) {
           $period = date('Y'); 
        }
       
        $indicatorCSAT = 'csat';
        
        $filterClient   = ($request->client === null)?'all': $request->client;
        $indetifyClient = substr($request->get('survey'),0,3);
        $indetifyClient = ($filterClient == 'all') ? $indetifyClient:$filterClient;
        $db         = 'adata_'.$indetifyClient.'_'.substr($request->survey,3,6);
        $indicatordb = ($indetifyClient == 'vid')?'ban':'vid';
        
        $db2        = 'adata_'.$indicatordb.'_'.substr($request->survey,3,6);
        $survey = $request->get('survey');
        //if(!isset($period) || !isset($survey))
        if(!isset($survey))
        {
            return ['datas'=>'Parametros faltantes', 'status'=>Response::HTTP_UNPROCESSABLE_ENTITY];
        }
        if(date('Y') != $period){
            $dateEnd = $period.'-12-31';
            $dateIni = $period.'-01-01';    
            $monthActive = 12;
            $monthPrevius = 11;
        }
        if(date('Y') == $period){
            $dateEnd = $period.'-'.date('m-d');
            $dateIni = $period.'-01-01'; 
            $monthActive = date('m');
        }
        //echo $dateEnd.'---'.$dateIni;
        // $db,$db2, $survey, $indicatorCSAT,  $dateEnd,$dateIni, $filter, 
        // 'adata_ban_rel','adata_vid_rel', 'banrel', 'csat',  '2021-12-31', '2021-01-01','all', 'two'
        //echo $survey;
        $dash = new Dashboard;
        $endCsat = $dash->getEndCsat($survey);
        $fieldBd = $dash->getFielInDbCsat($survey);
        $query = "";
        $query2 = "";
        $select = "";
        
        if($filter == 'all'){
            $fieldBd = $dash->getFielInDbCsat($survey);
            //echo $fieldBd.$endCsat;
            $query = "";
            for ($i=1; $i < $endCsat; $i++) {
                $select .= " ROUND(SUM(csat$i)) AS csat$i, ";
                 if($i != $endCsat){
                    $query .= "     ((COUNT(if($fieldBd$i = 9 OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageBan AS csat$i,";
                }
                ($i == ($endCsat -1)) ? $i++ : 0;
                if($i == $endCsat){
                    $select .= " ROUND(SUM(csat$i)) AS csat$i ";
                    $query .= " ((COUNT(if($fieldBd$i = 9  OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageBan AS csat$i ";
                }
            }
            
            for ($i=1; $i <= $endCsat; $i++) {
                 if($i != $endCsat){
                    $query2 .= " ((COUNT(if($fieldBd$i = 9  OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageVid  AS csat$i,";
                }
                
                if($i == $endCsat){
                    $query2 .= " ((COUNT(if($fieldBd$i = 9  OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )))*$this->_porcentageVid  AS csat$i ";
                }
                
            }
            $query1 = "SELECT $query,date_survey, mes
                                FROM customer_banmedica.$db as A
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' GROUP BY mes";
                                
            $query2 = "SELECT $query2,date_survey, mes
                                FROM customer_banmedica.$db2 as A
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' GROUP BY mes";
            $queryPrin = "SELECT $select, mes FROM ($query1 UNION $query2) as A GROUP BY mes ORDER BY date_survey";
            //print_r($queryPrin);
            $data = DB::select($queryPrin);
        }
        
        //ESTA PARTE NO VA
        
        if($filter != 'all'){
            $fieldBd = $dash->getFielInDbCsat($survey);
            $query = "";
            for ($i=1; $i <= $endCsat; $i++) {
                if($i != $endCsat){
                    $query .= " (COUNT(if( $fieldBd$i >= 9, $fieldBd$i, NULL))* 100)/COUNT(*) AS csat$i, ((count(if(csat$i < 7, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as detractor$i, 
                                            ((count(if(csat$i > 8, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as promotor$i, 
                                            ((count(if(csat$i <= 8 AND csat$i >=7, csat$i, NULL))*100)/COUNT(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageBan) as neutral$i,";
                }
                if($i == $endCsat){
                    $query .= " (COUNT(if( $fieldBd$i >= 9, $fieldBd$i, NULL))* 100)/COUNT(*) AS csat$i, ((count(if(csat$i < 7, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as detractor$i, 
                                            ((count(if(csat$i > 8, csat$i, NULL))*100)/count(*)*$this->_porcentageBan) as promotor$i, 
                                            ((count(if(csat$i <= 8 AND csat$i >=7, csat$i, NULL))*100)/COUNT(if($fieldBd$i !=99,1,NULL ))*$this->_porcentageBan) as neutral$i ";
                }
                
            }
            
            $data = DB::select("SELECT $query,date_survey, mes
                                FROM customer_banmedica.$db as A
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' 
                                ORDER BY date_survey");
        }
        $indexData = count($data);
        $suite = new Suite;
        foreach ($data as $key => $value){
             if(substr(date("F", strtotime(date('Y-'.$value->mes.'-d'))),0,3) === 'Jan'){
                 $column["period".$value->mes] = 'Ene';
             }else if(substr(date("F", strtotime(date('Y-'.$value->mes.'-d'))),0,3) === 'Apr'){
                 $column["period".$value->mes] = 'Abr';
             }else if(substr(date("F", strtotime(date('Y-'.$value->mes.'-d'))),0,3) === 'Aug'){
                 $column["period".$value->mes] = 'Ago';
             }else if(substr(date("F", strtotime(date('Y-'.$value->mes.'-d'))),0,3) === 'Dec'){
                 $column["period".$value->mes] = 'Dic';
             }else{
            $column["period".$value->mes] = substr(date("F", strtotime(date('Y-'.$value->mes.'-d'))),0,3);}
            $periods = null;
            if($key == 0){
                for($i=1; $i <= $endCsat; $i++) {
                        $total=0;
                    foreach ($data as $index => $period){
                        $r      = 'csat'.$i;
                        $total  = $period->$r+$total;
                        $detail["driver"]               = $suite->getInformationDriver($survey.'_'.$r);
                        $detail["period".$period->mes]  =(int)$period->$r;
                        if($period->mes == $monthActive){
                            if($monthActive == 1){
                                $diff = 0;
                            }
                            if($monthActive > 1){
                                if(isset($detail["period".$monthPrevius])){
                                $valuePrevius = $detail["period".$monthPrevius];
                                $diff = $period->$r-$valuePrevius;
                                }
                                if(!isset($detail["period".$monthPrevius])){
                                    $diff = 0;
                                }
                            }
                        }
                    }
                    $detail["ytd"] = ROUND($total/$indexData);
                    $detail["diff"] = $diff;
                    $details[]=[$detail];
                    $objets=$details;
                }
            }
        }
        //print_r($column);
        
        $startColumns   = ["driver" => ""];
        $endColumns     = [
                            "ytd"=> "YTD",
                            "diff"=> "+/-"
                        ];
        $resp = array_merge($startColumns,$column);
        $resp = array_merge($resp,$endColumns);
        
        return [
            "datas"=> 
                ["columns" => 
                    [$resp],
                "values"=>$objets
                ], 
            "status"=>Response::HTTP_OK
            ];
       
    }
}

?>