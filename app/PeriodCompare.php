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
    
    //   public function csatPreviousPeriod($db,$db2, $survey,$mes,$annio,$indicator){
    //     $dash = new Dashboard;
    //     $dbSelected    = $dash->getDBSelect();
    //     $endCsat = $dash->getEndCsat($survey);
    //     $fieldBd = $dash->getFielInDbCsat($survey);
        
    //     $monthAnt = $mes-1;
    //     if($monthAnt == 0){
    //         $mes = 12;
    //         $annio = $annio-1;
    //     }
        
    //     for ($i=1; $i <= $endCsat; $i++) {
    //     $data = DB::select("SELECT ROUND(SUM(csat$i)) AS csat FROM (SELECT ((COUNT(if($fieldBd$i = 9 OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($indicator !=99,1,NULL )))*$this->_porcentageBan AS csat$i
    //     FROM  customer_banmedica.$db
    //     WHERE mes = $mes AND annio = $annio
    //     UNION
    //     SELECT ((COUNT(if($fieldBd$i = 9 OR $fieldBd$i = 10, $fieldBd$i, NULL))* 100)/COUNT(if($indicator !=99,1,NULL )))*$this->_porcentageBan AS csat$i
    //     FROM  customer_banmedica.$db2
    //     WHERE mes = $mes AND annio = $annio) AS A");
    //     //var_dump($data[0]->csat);
    //     return $data[0]->csat;
           
    //     }
    // }
    
    public function GetPeriod($request, $jwt){
        $struct='two';
        $filter='all';
        $period = $request->get('period');
        if (!isset($period)) {
           $period = date('Y'); 
        }
 
        $filterClient   = ($request->client === null)?'all': $request->client;
        $indetifyClient = substr($request->get('survey'),0,3);
        $indetifyClient = ($filterClient == 'all') ? $indetifyClient:$filterClient;
        $db         = 'adata_'.$indetifyClient.'_'.substr($request->survey,3,6);
        $indicatordb = substr($request->get('survey'),0,3);
        $db2        = 'adata_vid_'.substr($request->survey,3,6);
        
        $survey = $request->get('survey');
       
        if(!isset($survey) || $survey == 'mutcon')
        {
            return ['datas'=>'Parametros faltantes', 'status'=>Response::HTTP_UNPROCESSABLE_ENTITY];
        }
        
        if(date('Y') != $period){
            $dateEnd = $period.'-12-31';
            $dateIni = $period.'-01-01';    
            $monthActive = 12;
            $monthPrevius = 11;
            $weekActive = (int)(date('W'));
            $weekPrev= $weekActive - 1;
        }
        if(date('Y') == $period){
            $dateEnd = $period.'-'.date('m-d');
            $dateIni = $period.'-01-01'; 
            $monthActive = (int)date('m');
            $monthPrevius = $monthActive - 1;
            $weekActive = (int)(date('W'));
            $weekPrev= $weekActive - 1;
        }
        
        $where = " date_survey BETWEEN '$dateIni' AND '$dateEnd'";
        $group=  " mes ";
        $current = 1;
        $but = null;
        if(substr($survey, 0,3) == 'mut'){
            $group=  " a.mes ";
        }
        
         if($request->filterWeeks !== null ){
            $interval = is_numeric($request->filterWeeks)? $request->filterWeeks : 10;
                $where= ' date_survey between date_sub(NOW(), interval 10 week) and NOW() ';
                $group = " week ";
                $current = 2;
                $but = ["text"=>"Semanal", "key"=>"filterWeeks", "value"=>"10"];
        }
        
        $dash = new Dashboard($jwt);
        $dashMut = new DashboardMutual($jwt,$request);
        $dbSelected    = $dash->getDBSelect();
        
        $maxCsat       = $dash->getParams('_maxCsat');
        $minCsat       = $dash->getParams('_minCsat');
        $minMediumCsat = $dash->getParams('_minMediumCsat');
        $maxMediumCsat = $dash->getParams('_maxMediumCsat');
        $minMaxCsat    = $dash->getParams('_minMaxCsat');
        $maxMaxCsat    = $dash->getParams('_maxMaxCsat');

        // $maxCes       = $dash->getParams('_maxCes');
        // $minCes       = $dash->getParams('_minCes');
        // $minMediumCes = $dash->getParams('_minMediumCes');
        // $minMaxCes    = $dash->getParams('_minMaxCes');
        
       
        $endCsat = $dash->getEndCsat($survey);
        $fieldBd = $dash->getFielInDbCsat($survey);
        $query = "";
        $query2 = "";
        $select = "";
        $diff = 0;

        ($dbSelected !== 'customer_banmedica')? $filter = 'one' : $filter = 'all'; 
        
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
            $query1 = "SELECT $query,date_survey, mes, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek
                                FROM $dbSelected.$db as A
                                WHERE $where  GROUP BY $group";
                                
            $query2 = "SELECT $query2,date_survey, mes, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek
                                FROM $dbSelected.$db2 as A
                                WHERE $where  GROUP BY $group";
            $queryPrin = "SELECT $select, mes, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek FROM ($query1 UNION $query2) as A GROUP BY $group ORDER BY date_survey";

            //echo "SELECT $select, mes FROM ($query1 UNION $query2) as A GROUP BY $group ORDER BY date_survey";
          
            $data = DB::select($queryPrin);
        }
        
        if($filter != 'all')
        {
            $fieldBd = $dash->getFielInDbCsat($survey);
            $query = "";

            if(substr($db, 6, 7) != 'tra_via')
            {
                if(substr($db, 6, 7) != 'jet_com' && substr($db, 6, 7) != 'jet_vue' && substr($db, 6, 7) != 'jet_via' && substr($db, 6, 7) != 'jet_cpe'){
                    for ($i=1; $i <= $endCsat; $i++) 
                    {
                        if($i != $endCsat)
                        {
                            $query .= " ROUND(
                                            (COUNT(if( $fieldBd$i = $minMaxCsat OR $fieldBd$i = $maxMaxCsat, $fieldBd$i, NULL)) - count(if($fieldBd$i <=  $maxCsat, $fieldBd$i, NULL))* 100) / COUNT(if($fieldBd$i !=99,1,NULL )), 0) AS csat$i, ";
                        }
                        
                        if($i == $endCsat)
                        {
                            $query .= " ROUND((COUNT(if( $fieldBd$i = $minMaxCsat OR $fieldBd$i = $maxMaxCsat, $fieldBd$i, NULL))-count(if($fieldBd$i <=  $maxCsat, $fieldBd$i, NULL)))* 100/COUNT(if($fieldBd$i !=99,1,NULL )),0) AS csat$i ";
                        }
                    }

                    $dashMut->surveyFilterZona($survey, $jwt, $request);
                    $dashMut->surveyFilterCentro($survey, $jwt, $request);
                    $dashMut->surveyFilterGerencia($survey, $jwt, $request);

                }

                if(substr($db, 6, 7) == 'jet_via'){
                    for ($i=1; $i <= $endCsat; $i++) 
                    {
                        if($i != $endCsat)
                        {
                            $query .= " ROUND(
                                            (COUNT(if( $fieldBd$i = $minMaxCsat OR $fieldBd$i = $maxMaxCsat, $fieldBd$i, NULL)) * 100) / COUNT(if($fieldBd$i !=99,1,NULL )), 0) AS csat$i, ";
                        }
                        
                        if($i == $endCsat)
                        {
                            $query .= " ROUND((COUNT(if( $fieldBd$i = $minMaxCsat OR $fieldBd$i = $maxMaxCsat, $fieldBd$i, NULL)))* 100/COUNT(if($fieldBd$i !=99,1,NULL )),0) AS csat$i ";
                        }
                    }

                }

                if(substr($db, 6, 7) == 'jet_com' || substr($db, 6, 7) == 'jet_vue' || substr($db, 6, 7) == 'jet_cpe')
                {
                    $maxMaxCes    = $dash->getParams('_maxMaxCes');
                    for ($i = 1; $i <= $endCsat; $i++) {

                        if ($i != $endCsat) {
                            $query .= " ROUND((COUNT(if( $fieldBd$i = $maxMaxCes, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )),0) AS  $fieldBd$i, ";
                        }
                        if ($i == $endCsat) {
                            $query .= " ROUND((COUNT(if( $fieldBd$i = $maxMaxCes, $fieldBd$i, NULL))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )),0) AS  $fieldBd$i ";
                        }
                    }
                }

                if(substr($db, 6, 3) == 'mut'){
                $data = DB::select("SELECT $query,date_survey, a.mes,  SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek
                                FROM $dbSelected.$db as a
                                left join $dbSelected.".$db."_start as b
                                on a.token = b.token   
                                WHERE $where AND etapaencuesta = 'P2'  ".$dashMut->filterZona." ". $dashMut->filterCentro." ".$dashMut->filterGerencia."
                                GROUP BY $group
                                ORDER BY date_survey");
                }

                if(substr($db, 6, 3) != 'mut'){
                $data = DB::select("SELECT $query,date_survey, mes,  SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek
                                    FROM $dbSelected.$db 
                                    WHERE $where AND etapaencuesta = 'P2' 
                                    GROUP BY $group
                                    ORDER BY date_survey");
                }
            }

            if(substr($db, 6, 7) == 'tra_via')
            {
                $where = " fechaservicio BETWEEN '$dateIni' AND '$dateEnd'";

                if($request->filterWeeks !== null ){
                    $interval = is_numeric($request->filterWeeks)? $request->filterWeeks : 10;
                        $where= ' fechaservicio between date_sub(NOW(), interval 10 week) and NOW() ';
                        $group = " week ";
                        $current = 2;
                        $but = ["text"=>"Semanal", "key"=>"filterWeeks", "value"=>"10"];
                }

                for ($i=1; $i <= $endCsat; $i++) 
                {
                    if ($i != $endCsat) {
                        $query .= " ROUND(((COUNT(if($fieldBd$i = $minMaxCsat OR $fieldBd$i = $maxMaxCsat, $fieldBd$i, NULL)) - COUNT(CASE WHEN $fieldBd$i BETWEEN $minCsat AND  $maxCsat THEN 1 END))* 100)/COUNT(CASE WHEN $fieldBd$i BETWEEN $minCsat AND  $maxMaxCsat THEN 1 END),0) AS  $fieldBd$i, ";
                    }
                    if ($i == $endCsat) {
                        $query .= " ROUND(((COUNT(if($fieldBd$i = $minMaxCsat OR $fieldBd$i = $maxMaxCsat, $fieldBd$i, NULL)) - COUNT(CASE WHEN $fieldBd$i BETWEEN $minCsat AND  $maxCsat THEN 1 END))* 100)/COUNT(CASE WHEN $fieldBd$i BETWEEN $minCsat AND  $maxMaxCsat THEN 1 END),0) AS  $fieldBd$i ";
                    }
                }

                $data = DB::select("SELECT $query, fechaservicio, MONTH(fechaservicio) as mes, WEEK(SUBDATE(fechaservicio, WEEKDAY(fechaservicio))) AS week, 
                                    SUBDATE(fechaservicio, WEEKDAY(fechaservicio)) as mondayWeek
                                    FROM $dbSelected.$db as a
                                    LEFT JOIN  $dbSelected." . $db . "_start as b
                                    on a.token = b.token
                                    WHERE $where AND etapaencuesta = 'P2' 
                                    GROUP BY $group
                                    ORDER BY fechaservicio");
            }
        }

        $indexData = count($data);
        $suite = new Suite($jwt);
        $column = [];
       
        if($current == 1 && $data != '' && $data != null){ 
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
            $column["period".$value->mes] = substr(date("F", strtotime(date('Y-'.$value->mes.'-d'))),0,3);
             }
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
                    $detail["diff"] = ROUND($diff);
                    $details[]=[$detail];
                    $objets=$details;
                }
            }
        }
        }
        if($current == 1 && ($data == '' || $data == null)){ 
            for($i=1; $i <= $endCsat; $i++) {
                $total=0;
                $r      = 'csat'.$i;
                $detail["driver"]               = $suite->getInformationDriver($survey.'_'.$r);
                $detail["ytd"] = '';
                $detail["diff"] = '';
                $details[]=[$detail];
                $objets=$details;
            }
        }
        
        if($current == 2){
         
        foreach ($data as $key => $value){
           
            $column["period".$value->week] = "Lun ". date('d',strtotime($value->mondayWeek)). '-' .date('m',strtotime($value->mondayWeek)) ;
            $periods = null;

            if($key == 0){
                for($i=1; $i <= $endCsat; $i++) {
                        $total=0;

                    foreach ($data as $index => $period){
                        $r      = 'csat'.$i;
                        $total  = $period->$r+$total;
                        $detail["driver"]                = $suite->getInformationDriver($survey.'_'.$r);
                        $detail["period".$period->week]  =(int)$period->$r;
                        //$detail["Lun ". date('d',strtotime($value->mondayWeek)). '-' .date('m',strtotime($value->mondayWeek)).' ('.$period->week.')']  =(int)$period->$r;
                        //echo $period->week . '....' . $weekActive;exit;
                        if($period->week == $weekActive){
                           
                            if($weekActive == 1){
                                $diff = 0;
                            }
                            if($weekActive > 1){
                                if(isset($detail["period".$weekPrev])){
                                    
                                $valuePrevius = $detail["period".$weekPrev];
                                $diff = $period->$r-$valuePrevius;
                                }
                                if(!isset($detail["period".$weekPrev])){
                                    $diff = 0;
                                }
                            }
                        }
                        if($period->week != $weekActive){
                            if($weekActive > 1){
                                if(isset($detail["period".$weekPrev])){
                                    
                                $valuePrevius = $detail["period".$weekPrev];
                                $diff = 0-$valuePrevius;
                                }
                                if(!isset($detail["period".$weekPrev])){
                                    $diff = 0;
                                }
                            }
                        }
                    }
                    $detail["ytd"] = ROUND($total/$indexData);
                    $detail["diff"] = ROUND($diff);
                    $details[]=[$detail];
                    $objets=$details;
                }
            }
        }
        }

        $startColumns   = ["driver" => ""];
        $endColumns     = [
                            "ytd"=> "YTD",
                            "diff"=> "+/-"
                        ];

                       //print_r($column);
        $resp = array_merge($startColumns,$column);
        $resp = array_merge($resp,$endColumns);
        
        return [
            "datas"=> 
                [
                    //"type"=>"table-period",
                    "callToAction"=> $but,
                    "columns" => [$resp],
                    "values"=>$objets
                ], 
            "status"=>Response::HTTP_OK
            ];
    }
}