<?php namespace App;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;

class DashboardMutual extends Dashboard
{
    private $filterZona = '';
    private $filterCentro = '';
    private $whereCons = '';
    private $_activeSurvey = 'mutamb';
    private $_suveysConsolidado = ['mutamb', 'muthos', 'muturg', 'mutreh', 'mutimg'];
 
    public function __construct($jwt, $request)
    {
        parent::__construct($jwt);
    }

    public function generalInfo($request, $jwt)
    {
        $c = 0;
        $surveys = parent::getDataSurvey($request, $jwt); 
        $data = [];
        $otherGraph = [];
   
        if ($surveys['status'] == 200) {
            if($surveys['datas'][0]['customer'] == 'MUT001'){
                for($i=0; $i<sizeof($surveys['datas']); $i++){
                    if (in_array($surveys['datas'][$i]['base'], $this->_suveysConsolidado)){
                        $c++;
                    }
                }
                if ($c == 5){
                    array_push($surveys['datas'], $this->consolidateMutualPeso());
                }
                if ($c>1 && $c<5){
                    array_push($surveys['datas'], $this->consolidateMutual());
                }
            }

            foreach ($surveys['datas'] as $key => $value) {
                if ($value['base'] != 'mutredsms'){
                    $this->surveyFilterZona($value['base'], $jwt, $request);
                    $this->surveyFilterCentro($value['base'], $jwt, $request);
                    $this->whereConsolidado($value['base'],$jwt);
                    
                    $db         = 'adata_'.substr($value['base'],0,3).'_'.substr($value['base'],3,6);
                    $infoNps    = [$this->infoNpsMutual($db, date('Y-m-d'),date('Y-m-01'),'nps',null,$this->getInitialFilter(), '')]; 
                    $otherGraph = [$this->infoISNMutual($db, date('Y-m-d'),date('Y-m-01'), 'csat',$this->getInitialFilter())];
                      
                    $data[] = [
                        "client"                => $this->setNameClient('_nameClient'), 'clients'  => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: null,
                        "title"                 => ucwords(strtolower($value['name'])),
                        "identifier"            => $value['base'],
                        "principalIndicator"    => $infoNps,
                        "journeyMap"            => $value['base'] == 'mutcon' || $value['base'] == 'mutcop'? null : $this->GraphCSATDriversMutual($db,$value['base'],date('Y-m-d'),date('Y-m-01'),$this->getInitialFilter(),$struct = 'one'),
                        "otherGraphs"           => $otherGraph
                    ];
                }
            }
        }
        return [
            'datas'     => $data,
            'status'    => Response::HTTP_OK
        ];
        unset($c);
    }

    private function whereConsolidado($base,$jwt){
        $this->whereCons = '';
        if ($base == 'mutcon' || $base == 'mutcop'){
            if (isset($jwt[env('AUTH0_AUD')]->surveysActive)){
                $surveys = $jwt[env('AUTH0_AUD')]->surveysActive;
                $text = "'".$surveys[0]."'";
                for($i =1; $i<count($surveys); $i++)
                {
                    if(in_array( $surveys[$i],$this->surveysConsolidado())){
                    $text .= ", '".$surveys[$i]."'";
                    }
                }

                $this->whereCons  = " and survey in (". $text .")";
            }
        }
    }

    protected function infoNpsMutual($table,  $dateIni, $dateEnd, $indicador, $filter, $dataFilter)
    {
        $generalDataNps             = $this->resumenNpsM($table,  $dateIni, $dateEnd, $indicador, $filter, '');
        $generalDataNps['graph']    = $this->graphNps($table,  $indicador, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $filter, 'one');

        return $generalDataNps;
    }

    protected function infoISNMutual($table, $dateIni, $dateEnd, $indicador)
    {
        $generalDataCsat            = $this->resumenISN($table, $dateIni, $dateEnd, $indicador, $this->getValueParams('_initialFilter'));
        $generalDataCsat['graph']   = $this->graphIsn($table,  $indicador, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $this->getValueParams('_initialFilter'), 'one');
        return $generalDataCsat;
    }

    public function backCards($request, $jwt)
    {
        $this->surveyFilterZona($request->get('survey'), $jwt, $request);
        $this->surveyFilterCentro($request->get('survey'), $jwt, $request);
        $this->whereConsolidado($request->get('survey'),$jwt);
        if ($request->get('survey') != 'mutredsms'){
                    
        $survey     = ($request->get('survey') === null) ? $this->_activeSurvey : $request->get('survey');
        $npsInDb    = $this->getFielInDb($survey);
        $dataEmail  = $this->email('adata_' . substr($survey, 0, 3) . '_' . substr($survey, 3, 6), date('Y-m-01'), date('Y-m-d'), $this->getValueParams('_initialFilter'));
        $data       = $this->infoClosedLoop('adata_' . substr($survey, 0, 3) . '_' . substr($survey, 3, 6), date('Y-m-01'), date('Y-m-d'), $npsInDb, $this->getValueParams('_initialFilter'));
        $resp = [$dataEmail, $data];

        return [
            'datas'     => $resp,
            'status'    => Response::HTTP_OK
        ];
        }
    }

    private function infoClosedLoop($db, $dateIni, $dateEnd, $fieldInBd, $filter, $datafilters = null)
    {
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter != 'all') {
            $data = DB::select("SELECT COUNT(*) as ticketCreated,
                                COUNT(if(B.estado_close = 4, B.id, NULL)) as ticketClosed, 
                                COUNT(if(B.estado_close = 2, B.id, NULL)) as ticketPending, 
                                COUNT(if(B.estado_close = 1 OR B.estado_close = 3, B.id, NULL)) as ticketInProgres,  ".$this->getValueParams('_fieldSelectInQuery')."
                                FROM ".$this->getValueParams('_dbSelected').".$db as A 
                                INNER JOIN ".$this->getValueParams('_dbSelected')."." . $db . "_start as B ON (A.token = B.token) 
                                WHERE B.fechacarga BETWEEN '$dateIni' AND '$dateEnd' AND $fieldInBd IN (0,1,2,3,4,5,6) AND  
                                ".$this->getValueParams('_obsNps')." != '' $datafilters ".$this->filterZona." ". $this->filterCentro." ".$this->whereCons ." ");                 
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
        $activeP2 = " AND etapaencuesta = 'P2' ";
 
        $activeP2 ='';
        $data = DB::select("SELECT COUNT(*) AS TOTAL FROM  ".$this->getValueParams('_dbSelected').".$db as a
                            left join ".$this->getValueParams('_dbSelected')."." . $db . "_start as b
                            on a.token = b.token
                            WHERE mailsended = 1 AND fechacarga BETWEEN '$dateIni' AND '$dateEnd' ".$this->filterZona." 
                            ". $this->filterCentro." ".$this->whereCons ."  " );

        $EmailSend = $data[0]->TOTAL;

        $data2 = DB::select("SELECT COUNT(*) AS RESP 
                            FROM ".$this->getValueParams('_dbSelected').".$db as a
                            left join ".$this->getValueParams('_dbSelected')."." . $db . "_start as b
                             on a.token = b.token
                            WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' and nps!= 99 $activeP2  ".$this->filterZona." 
                            ". $this->filterCentro." ".$this->whereCons ."");

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


    private function graphIsn($table,  $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null){
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

        $graphCSAT = array();
        if ($filter != 'all') {
            { //CALCULA EL ISN, NO EL CSAT
                $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minMaxCsat')." AND ".$this->getValueParams('_maxMaxCsat')." THEN 1 END) - 
                                    COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minCsat')." AND ".$this->getValueParams('_maxCsat')." THEN 1 END)) / 
                                    (COUNT(CASE WHEN $indicador!=99 THEN 1 END)) * 100),1) AS csat, 
                                    a.mes, a.annio, date_survey, ".$this->getValueParams('_fieldSelectInQuery')." 
                                    FROM ".$this->getValueParams('_dbSelected').".$table as a
                                    INNER JOIN ".$this->getValueParams('_dbSelected')."." . $table . "_start as b on a.token = b. token 
                                    WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' AND etapaencuesta = 'P2' $datafilters  ".$this->filterZona." ". $this->filterCentro." ".$this->whereCons ."
                                    GROUP BY a.mes, a.annio
                                    ORDER BY date_survey asc"); 
            }
        }

        if (!empty($data)) {
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
  
        return $graphCSAT;
    }

    private function resumenISN($table, $dateIni, $dateEnd, $indicador, $filter, $datafilters = null){
        $data = DB::select("SELECT count(*) as total,
                            ((COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minMaxCsat')." AND ".$this->getValueParams('_maxMaxCsat')." THEN $indicador  END))-
                            (COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minCsat')." AND ".$this->getValueParams('_maxCsat')." THEN $indicador  END)))*100/count(CASE WHEN $indicador  != 99 THEN csat END) as isn, 
                            ".$this->getValueParams('_fieldSelectInQuery')."
                            FROM ".$this->getValueParams('_dbSelected').".$table as a
                            INNER JOIN ".$this->getValueParams('_dbSelected')."." . $table . "_start as b ON a.token = b.token
                            WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' and etapaencuesta = 'P2'  ".$this->filterZona." ". $this->filterCentro." ".$this->whereCons ."");

        $isnPreviousPeriod = $this->isnPreviousPeriod($table,$dateIni, $dateEnd, $indicador, $filter,  $datafilters);

        $isnActive = 0;
        if (($data == null) || $data[0]->total == null || $data[0]->isn == null) {
            
            $isnActive =  $isnActive;
            return [
                "name"          => 'isn',
                "value"         => 'N/A',
                "percentage"    => (string)Round($isnActive-$isnPreviousPeriod),
                "smAvg"         => '',
            ];
        }

        if ($data[0]->total != null) {
            
            $isnActive = $data[0]->isn;
            return [
                "name"          => 'isn',
                "value"         => ROUND($data[0]->isn),
                "percentage"    => ROUND($data[0]->isn) - ROUND($isnPreviousPeriod),
            ];
        }
    }

    private function dbResumenNps1 ($table,$indicador,$dateIni,$dateEnd, $filter, $datafilters)
    {
        $query = "SELECT count(*) as total, 
        ((count(if(nps <= ".$this->getValueParams('_maxNps').", nps, NULL))*100)/COUNT(CASE WHEN nps !=99 THEN 1 END)) as detractor, 
        ((count(if(nps = ".$this->getValueParams('_minMaxNps')." or  nps = ".$this->getValueParams('_maxMaxNps')." , nps, NULL))*100)/COUNT(CASE WHEN nps != 99 THEN 1 END)) as promotor,
        ((count(if(nps =  ".$this->getValueParams('_maxMediumNps')." OR nps = ".$this->getValueParams('_minMediumNps').", nps, NULL))*100)/COUNT(CASE WHEN nps != 99 THEN 1 END)) as neutral,
        AVG(nps) as promedio,
        ROUND(((COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minMaxNps')." AND ".$this->getValueParams('_maxMaxNps')." THEN 1 END) - 
        COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minNps')." AND ".$this->getValueParams('_maxNps')." THEN 1 END)) / 
        (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),0) AS NPS,  ".$this->getValueParams('_fieldSelectInQuery')."
        FROM ".$this->getValueParams('_dbSelected').".$table as a
        LEFT JOIN ".$this->getValueParams('_dbSelected')."." . $table . "_start as b
        on a.token = b.token
        WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2'  $datafilters ".$this->filterZona." ". $this->filterCentro." ".$this->whereCons ."
        GROUP BY a.mes, a.annio
        ORDER BY date_survey ASC";
        //echo $query;
        $data = DB::select($query);

        return $data;
    }

    private function isnPreviousPeriod($table, $dateEnd, $dateIni, $indicador, $filter, $datafilters){
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
                $data = DB::select("SELECT ((COUNT(CASE WHEN $indicador  BETWEEN ".$this->getValueParams('_minMaxCsat')." AND ".$this->getValueParams('_maxMaxCsat')." THEN $indicador END)) -
                                    (COUNT(CASE WHEN $indicador  BETWEEN ".$this->getValueParams('_minCsat')." AND ".$this->getValueParams('_maxCsat')." THEN $indicador  END)))*100/count(CASE WHEN $indicador  != 99 THEN csat END) as isn
                                    FROM ".$this->getValueParams('_dbSelected').".$table as a
                                    LEFT JOIN ".$this->getValueParams('_dbSelected')."." . $table . "_start as b
                                    on a.token = b.token
                                    WHERE a.mes = $mes AND a.annio = $annio AND etapaencuesta = 'P2'  ".$this->filterZona." ". $this->filterCentro." ".$this->whereCons ." ");
        }

        return $data[0]->isn;
    }
              
    private function resumenNpsM($table,  $dateEnd, $dateIni, $indicador, $filter, $datafilters)
    {
        if ($datafilters)
            $datafilters = " AND $datafilters";
        
        $data = $this->dbResumenNps1($table,$indicador,$dateIni,$dateEnd, '', $datafilters);
    
        if (($data == null) || $data[0]->total == null || $data[0]->total == 0) {
            $npsActive = (isset($data[0]->NPS)) ? $data[0]->NPS : 0;
            $npsPreviousPeriod = $this->npsPreviousPeriod($table, $dateEnd, $dateIni, $indicador, $datafilters);
            
            return [
                "name"              => "nps",
                "value"             => 'N/A',
                "percentageGraph"   => true,
                "promotors"         => 0,
                "neutrals"          => 0,
                "detractors"        => 0,
                "percentage"        => $npsActive - $npsPreviousPeriod,
                "smAvg"             => $this->AVGLast6MonthNPS($table, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $indicador, $filter)
            ];
        }

        if ($data[0]->total != 0) {
            $npsActive = (isset($data[0]->NPS)) ? $data[0]->NPS : 0;
            $npsPreviousPeriod = $this->npsPreviousPeriod($table, $dateEnd, $dateIni, $indicador, $datafilters);
            
            if ($npsPreviousPeriod  === null) {
                $npsPreviousPeriod = 0;
            }
           
            return [
                "name"              => "nps",
                "value"             => round($npsActive),
                "percentageGraph"   => true,
                "promotors"         => round($data[0]->promotor),
                "neutrals"          => ((round($data[0]->promotor) == 0) && (round($data[0]->detractor) == 0)) ? round($data[0]->neutral) : 100 - round(($data[0]->detractor) + ($data[0]->promotor)),
                "detractors"        => round($data[0]->detractor),
                "percentage"        => '0',
                "smAvg"             => '0',
                'NPSPReV'           => $npsPreviousPeriod,
            ];
        }
    }

    private function graphNps($table, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    {
        $graphNPS  = [];

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

        if ($filter != 'all') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minMaxNps')." AND ".$this->getValueParams('_maxMaxNps')." THEN 1 END) - 
                                COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minNps')." AND ".$this->getValueParams('_maxNps')." THEN 1 END)) / 
                                COUNT(CASE WHEN nps!=99 THEN 1 END) * 100),1) AS NPS, 
                                count(if(nps <= ".$this->getValueParams('_maxNps')." , nps, NULL)) as Cdet,
					            count(if(nps = ".$this->getValueParams('_minMaxNps')." or nps =".$this->getValueParams('_maxMaxNps').", nps, NULL)) as Cpro,
					            count(if(nps=".$this->getValueParams('_maxMediumNps')." OR nps=".$this->getValueParams('_minMediumNps').", nps, NULL)) as Cneu,              
                                count(*) as total, 
                                ((count(if(nps <= ".$this->getValueParams('_maxNps').", nps, NULL))*100)/count(CASE WHEN nps != 99 THEN nps END)) as detractor, 
                                ((count(if(nps = ".$this->getValueParams('_minMaxNps')." OR nps =".$this->getValueParams('_maxMaxNps').", nps, NULL))*100)/count(CASE WHEN nps != 99 THEN nps END)) as promotor, 
                                ((count(if(nps=".$this->getValueParams('_maxMediumNps')." OR nps=".$this->getValueParams('_minMediumNps').", nps, NULL))*100)/count(CASE WHEN nps != 99 THEN nps END)) as neutral,              
                                a.mes, a.annio, WEEK(date_survey) AS week, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek,".$this->getValueParams('_fieldSelectInQuery')."  
                                FROM ".$this->getValueParams('_dbSelected').".$table as a
                                INNER JOIN ".$this->getValueParams('_dbSelected').".".$table."_start as b ON a.token = b.token 
                                WHERE  $where  $datafilters ".$this->filterZona." ". $this->filterCentro." ".$this->whereCons ."
                                GROUP BY $group2
                                ORDER BY date_survey ASC");               
        }

        if ($data) {
            if ($data[0]->total === null) {
                foreach ($data as $key => $value) {
                    if ($struct != 'one') {
                        $graphNPS[] = [
                            'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Lun ' . date('d',strtotime($value->mondayWeek)). '-' .date('m',strtotime($value->mondayWeek)) . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                            'values' => [
                                "promoters"     => round($value->promotor),
                                "neutrals"      => ((round($value->promotor) == 0) && (round($value->detractor) == 0)) ? round($value->neutral) : 100 - (round($value->detractor) + round($value->promotor)),//100 - (round($value->promotor) + round($value->detractor)),
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
                                "neutrals"      => ((round($value->promotor) == 0) && (round($value->detractor) == 0)) ? round($value->neutral) : 100 - (round($value->detractor) + round($value->promotor)),//100 - (round($value->promotor) + round($value->detractor)),
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

    private function AVGLast6MonthNPS($table,$dateIni,$dateEnd,$indicador, $filter){
        if ($filter != 'all') {
            $data = DB::select("SELECT sum(NPS) as total, COUNT(distinct mes) as meses from 
                                (SELECT ROUND(((COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minMaxNps')." AND ".$this->getValueParams('_maxMaxNps')." THEN 1 END) -
                                COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minNps')." AND ".$this->getValueParams('_maxNps')." THEN 1 END)) /
                                COUNT(CASE WHEN nps != 99 THEN 1 END) * 100),1) AS NPS, a.mes as mes, a.annio
                                FROM ".$this->getValueParams('_dbSelected').".$table as a
                                INNER JOIN ".$this->getValueParams('_dbSelected').".".$table."_start as b ON a.token = b.token 
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' ". $this->filterZona." ". $this->filterCentro." ".$this->whereCons ."
                                group by a.annio, a.mes) as a");
        }
        if($data[0]->meses == '0')
            return 0;
        return (string)(round($data[0]->total / $data[0]->meses));
    }

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

        $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minMaxNps')." AND ".$this->getValueParams('_maxMaxNps')." THEN 1 END) -
                            COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minNps')." AND ".$this->getValueParams('_maxNps')." THEN 1 END)) /
                            (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS
                            FROM ".$this->getValueParams('_dbSelected').".$table as a
                            left join ".$this->getValueParams('_dbSelected').".".$table."_start as b
                            on a.token = b.token
                            WHERE a.mes = $mes and a.annio = $annio $datafilters ". $this->filterZona." ". $this->filterCentro." ".$this->whereCons ."");

        return $data[0]->NPS;
    }

    private function surveyFilterZona($survey, $jwt, $request){
        $this->filterZona = '';
        if(isset($jwt[env('AUTH0_AUD')]->zona)){
            if(in_array( $survey,$this->surveysConsolidado())){
                $this->filterZona= " AND zonal = '". $this->setFilterZona($jwt, $request)."'" ;
            }
        }
    }

    private function setFilterZona($jwt, $request){
        if($request->get('zonal') !== null)
            return trim($request->get('zonal'));

        if(isset($jwt[env('AUTH0_AUD')]->zona))
            return $jwt[env('AUTH0_AUD')]->zona;
    } 

    private function surveysConsolidado(){
        return  ['mutcon','mutamb','muturg','mutimg','mutreh','muthos', 'mutcop'];
    }

    private function surveyFilterCentro($survey, $jwt, $request){
        $this->filterCentro = '';
        if(isset($jwt[env('AUTH0_AUD')]->centros)){
            if(in_array( $survey,$this->surveysConsolidado())){
                $this->filterCentro= " AND catencion in ('".$this->setFilterCentro($jwt, $request)."') ";
            }
        }
    }

    private function setFilterCentro($jwt, $request){
        $centros = '';
        if($request->get('catencion') !== null)
            return trim($request->get('catencion'));

        if(isset($jwt[env('AUTH0_AUD')]->centros)){
            $long = sizeof($jwt[env('AUTH0_AUD')]->centros);
            if($long == 1){ 
                return $jwt[env('AUTH0_AUD')]->centros[0];
            }
            if($long > 1){
                $centros = $jwt[env('AUTH0_AUD')]->centros[0];
                for($i=1; $i<$long; $i++){
                    $centros = $centros."' ,'".$jwt[env('AUTH0_AUD')]->centros[$i];
                }
                return $centros;
            }
        }
        return $centros;
    } 

    private function GraphCSATDriversMutual($db, $survey,  $dateEnd, $dateIni, $filter, $struct = 'two', $datafilters = null)
    {
        $graphCSAT = [];
        $endCsat = $this->getEndCsat($survey);
        $fieldBd = $this->getFielInDbCsat($survey);
        $query = "";
        if ($datafilters)
            $datafilters = " AND $datafilters";
   
        if ($filter != 'all') {
            $fieldBd = $this->getFielInDbCsat($survey);
            $query = "";
            for ($i = 1; $i < $endCsat; $i++) {
                if ($i != $endCsat) 
                    $query .= " ((COUNT(if( $fieldBd$i = ".$this->getValueParams('_minMaxCsat')." OR $fieldBd$i = ".$this->getValueParams('_maxMaxCsat').", $fieldBd$i, NULL))- count(if( $fieldBd$i < ".$this->getValueParams('_minMediumCsat').",  $fieldBd$i, NULL)))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                ((count(if( $fieldBd$i < ".$this->getValueParams('_minMediumCsat').",  $fieldBd$i, NULL))*100)/count(case when $fieldBd$i != 99 THEN  $fieldBd$i END)) as detractor$i, 
                                ((count(if( $fieldBd$i > ".$this->getValueParams('_maxMediumCsat')." AND $fieldBd$i <= ".$this->getValueParams('_maxMaxCsat').",  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                ((count(if( $fieldBd$i <= ".$this->getValueParams('_maxMediumCsat')." AND  $fieldBd$i >= ".$this->getValueParams('_minMediumCsat').",  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN   $fieldBd$i END)) as neutral$i,";
                }
               
                if ($i == $endCsat) {
                    $query .= " ((COUNT(if( $fieldBd$i = ".$this->getValueParams('_minMaxCsat')." OR $fieldBd$i = ".$this->getValueParams('_maxMaxCsat').", $fieldBd$i, NULL)) - count(if( $fieldBd$i < ".$this->getValueParams('_minMediumCsat').",  $fieldBd$i, NULL)))* 100)/COUNT(if($fieldBd$i !=99,1,NULL )) AS  $fieldBd$i, 
                                ((count(if( $fieldBd$i < ".$this->getValueParams('_minMediumCsat').",  $fieldBd$i, NULL))*100)/count(case when $fieldBd$i != 99 THEN  $fieldBd$i END)) as detractor$i, 
                                ((count(if( $fieldBd$i > ".$this->getValueParams('_maxMediumCsat')." AND $fieldBd$i <= ".$this->getValueParams('_maxMaxCsat').",  $fieldBd$i, NULL))*100)/count(if($fieldBd$i !=99,1,NULL ))) as promotor$i, 
                                ((count(if( $fieldBd$i <= ".$this->getValueParams('_maxMediumCsat')." AND  $fieldBd$i >= ".$this->getValueParams('_minMediumCsat').",  $fieldBd$i, NULL))*100)/count(case when  $fieldBd$i != 99 THEN  $fieldBd$i END)) as neutral$i ";
                }
            }
         
            $data = DB::select("SELECT $query,date_survey, WEEK(date_survey) AS week, a.mes
                                FROM ".$this->getValueParams('_dbSelected').".$db as a
                                LEFT JOIN ".$this->getValueParams('_dbSelected')."." . $db . "_start as b
                                on a.token = b.token 
                                WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' AND etapaencuesta = 'P2' $datafilters ". $this->filterZona." ". $this->filterCentro." ".$this->whereCons ."
                                ORDER BY date_survey");
                        
        $suite = new Suite($this->getValueParams('_jwt'));
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
                            "promoters"     => round($value->$pro),
                            "neutrals"      => (($value->$pro) == 0 && ($value->$det) == 0) ? round(round($value->$neu)) : (100 - (ROUND($value->$pro) + ROUND($value->$det))),//100 - (ROUND($value->$pro) + ROUND($value->$det)),
                            "detractors"    => ROUND($value->$det),
                            "csat"          => (int)round(round($value->$pro)-round($value->$det))
                        ]
                    ];
                }

                if ($struct == 'one') {
                    $graphCSAT[] =
                        [
                            'text'  =>  $suite->getInformationDriver($survey . '_' . $r),
                            'values' => round(round($value->$pro)-round($value->$det))
                        ];
                }
            }
        }
        return $graphCSAT;
    }

    private function graphInsMutual($table, $indicador, $dateIni, $dateEnd, $filter,  $datafilters = null)
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
        if ($datafilters)
            $datafilters = " AND $datafilters";

        if ($filter == 'all') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minMaxCsat')." AND ".$this->getValueParams('_maxMaxCsat')." THEN 1 END) - 
                                COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minCsat')." AND ".$this->getValueParams('_maxCsat')." THEN 1 END)) / 
                                (COUNT(CASE WHEN $indicador!=99 THEN 1 END)) * 100),1) AS ISN, 
                                a.mes, a.annio ,".$this->getValueParams('_fieldSelectInQuery')."  
                                FROM ".$this->getValueParams('_dbSelected').".$table as a
                                INNER JOIN ".$this->getValueParams('_dbSelected')."." . $table . "_start as b ON a.token = b.token 
                                WHERE  date_survey BETWEEN '$dateEnd' AND '$dateIni' AND etapaencuesta = 'P2' $datafilters ". $this->filterZona." ". $this->filterCentro." ".$this->whereCons ."
                                GROUP BY  a.mes, a.annio 
                                ORDER BY date_survey ASC");

            $data2 = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minMaxCsat')." AND ".$this->getValueParams('_maxMaxCsat')." THEN 1 END) - 
                                COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minCsat')." AND ".$this->getValueParams('_maxCsat')." THEN 1 END)) / 
                                (COUNT(CASE WHEN $indicador!=99 THEN 1 END)) * 100),1) AS ISN, 
                                a.mes, a.annio ,".$this->getValueParams('_fieldSelectInQuery')."  
                                FROM ".$this->getValueParams('_dbSelected').".$table as a
                                INNER JOIN ".$this->getValueParams('_dbSelected')."." . $table . "_start as b ON a.token = b.token 
                                WHERE  a.mes = $mes  AND a.annio = $annio AND etapaencuesta = 'P2' $datafilters ". $this->filterZona." ". $this->filterCentro." ".$this->whereCons ."
                                GROUP BY  a.mes, a.annio 
                                ORDER BY date_survey ASC");
        }

        if ($data != null && $data[0]->ISN != null){
            $data2 == null? $datas = 0 : $datas = $data2[0]->ISN;
            return[
                "name"              => "isn",
                "value"             => round($data[0]->ISN),
                "percentage"        => round($data[0]->ISN - $datas),
            ];
        }

        if ($data == null || $data[0]->ISN == null){
            return[
                "name"              => "isn",
                "value"             => 'N/A',
                "percentage"        => round(0),
            ];
        }
    } 

    private function graphCsatMutual($table,$indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    {
        if ($group !== null) {
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
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minMaxCsat')." AND ".$this->getValueParams('_maxMaxCsat')." THEN 1 END) - 
                                COUNT(CASE WHEN $indicador BETWEEN ".$this->getValueParams('_minCsat')." AND ".$this->getValueParams('_maxCsat')." THEN 1 END)) / 
                                (COUNT(CASE WHEN $indicador!=99 THEN 1 END)) * 100),1) AS ISN, 
                                count(if($indicador < ".$this->getValueParams('_minMediumCsat').", $indicador, NULL)) as Cdet,
					            count(if($indicador = ".$this->getValueParams('_minMaxCsat')." OR $indicador = ".$this->getValueParams('_maxMaxCsat').", $indicador, NULL)) as Cpro,
					            count(if($indicador = ".$this->getValueParams('_maxMediumCsat')." OR $indicador = ".$this->getValueParams('_minMediumCsat').", $indicador, NULL)) as Cneu,              
                                COUNT(CASE WHEN $indicador!=99 THEN 1 END) as total, 
                                ((count(if($indicador < ".$this->getValueParams('_minMediumCsat').", $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as detractor, 
                                ((count(if($indicador = ".$this->getValueParams('_minMaxCsat')." OR $indicador = ".$this->getValueParams('_maxMaxCsat').", $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as promotor, 
                                ((count(if($indicador = ".$this->getValueParams('_maxMediumCsat')." OR $indicador =".$this->getValueParams('_minMediumCsat').", $indicador, NULL))*100)/count(CASE WHEN $indicador != 99 THEN $indicador END)) as neutral,              
                                a.mes, a.annio, WEEK(date_survey) AS week, SUBDATE(date_survey, WEEKDAY(date_survey)) as mondayWeek,".$this->getValueParams('_fieldSelectInQuery')."  
                                FROM ".$this->getValueParams('_dbSelected').".$table as a
                                INNER JOIN ".$this->getValueParams('_dbSelected')."." . $table . "_start as b ON a.token = b.token 
                                WHERE  $where AND etapaencuesta = 'P2' $datafilters ". $this->filterZona." ". $this->filterCentro." ".$this->whereCons ."
                                GROUP BY $group
                                ORDER BY date_survey ASC");
        }
       
        foreach ($data as $key => $value) {
            if ($struct != 'one') {
                //echo (round(($value->detractor) + ($value->promotor)));exit;
                $graphCsatM[] = [
                    'xLegend'  =>(trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Lun ' . date('d',strtotime($value->mondayWeek)). '-' .date('m',strtotime($value->mondayWeek)) . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                    'values' => [
                        "satisfechos"       => round($value->promotor),
                        "neutrals"          => ((round($value->promotor) == 0) && (round($value->detractor) == 0)) ? round($value->neutral) : 100 - round(($value->detractor) + ($value->promotor)),
                        "insatisfechos"     => round($value->detractor),
                        "csat"              => round($value->ISN)
                    ],
                ];
            }
        }
        return $graphCsatM;
    }   

    private function structfilterM($request, $fieldbd, $fieldurl, $where)
    {
        if ($request->get($fieldurl) === null)
            return '';
        if ($request->get($fieldurl)) {
            if ($where != '' ) {
                if($fieldbd == 'catencion'){
                $where = " AND $fieldbd in( '" . $request->get($fieldurl) ."')";
                }
                if($fieldbd != 'catencion'){
                $where = " AND $fieldbd = '" . $request->get($fieldurl) . "'";
                }
            }
            if ($where == '') {
                if($fieldbd == 'catencion'){
                $where = " $fieldbd in ( '" . $request->get($fieldurl) . "')";
                }
                if($fieldbd != 'catencion'){
                    $where = " $fieldbd = '" . $request->get($fieldurl) . "'";
                }
            }
        }
        return $where;
    }

    private function infofilters($request)
    {
        $where = '';
        
        if(substr($request->survey,0,3) == 'mut'){
            $where .= $this->structfilterM($request, 'macroseg',         'Macrosegmento',      $where);
            $where .= $this->structfilterM($request, 'tatencion',        'Modalidad_Atencion', $where);
            $where .= $this->structfilterM($request, 'tipcliente',       'Tipo_Cliente',       $where);
            $where .= $this->structfilterM($request, 'canal',            'Canal',              $where);
            $where .= $this->structfilterM($request, 'tatencion',        'Tipo_Atencion',      $where);
            $where .= $this->structfilterM($request, 'catencion',        'Centro_Atencion',    $where);
            $where .= $this->structfilterM($request, 'aatencion',        'Area_Atencion',      $where);
            $where .= $this->structfilterM($request, 'gerenciamedica',   'Gerencia_Medica',    $where);
            $where .= $this->structfilterM($request, 'zonal',             'Zona',              $where);
                
            return $where;
        }
    }

    public function filters($request, $jwt, $datafilters = null)
    {
        $survey = $request->get('survey');
        $Gerencia =         [];
        $macrosegmento =    [];
        $modAtencion =      [];
        $tipoCliente =      [];
        $tipoCanal =        [];
        $tipAtencion =      [];
        $CenAtencionn =     [];
        $AreaAten      =    [];
        $ZonaHos =          [];
        $dbC = substr($survey, 3, 6);

      if ($this->getValueParams('_dbSelected')  == 'customer_colmena' && substr($survey, 0, 3) == 'mut'  && $survey != 'mutred') {

        if ($survey == "muthos" || $survey == "muturg" || $survey == "mutamb" || $survey == "mutimg" || $survey == "mutreh") {
            //$db = 'MUT001_mutcon_resp';
            if ($request->client) {
                //$db = 'adata_' . trim(substr($request->client, 0, 3)) . '_' . trim(substr($request->client, 3, 6));
                $dbC = substr($request->client, 3, 6);
            }
        }
      
        if ($dbC == 'be' || $dbC == 'ges') {
            $data = DB::select("SELECT DISTINCT(macroseg)
                                FROM ".$this->getValueParams('_dbSelected').".adata_mut_".$dbC."_start
                                WHERE macroseg != '0' and macroseg != '9' and macroseg != '8'");

            $this->_fieldSelectInQuery = 'macroseg';

            $macrosegmento = ['filter' => 'Macrosegmento', 'datas' => $this->contentfilter($data, 'macroseg')];
        }

        if ($dbC == 'eri' || $dbC == 'cas') {
            $data = DB::select("SELECT DISTINCT(tatencion)
                                FROM ".$this->getValueParams('_dbSelected').".adata_mut_".$dbC."_start
                                WHERE tatencion != '0' AND tatencion != 'NO APLICA'");

            $this->_fieldSelectInQuery = 'tatencion';

            $modAtencion = ['filter' => 'Modalidad_Atencion', 'datas' => $this->contentfilter($data, 'tatencion')];

            return ['filters' => [(object)$modAtencion], 'status' => Response::HTTP_OK];
        }

        if ($dbC == 'ges') {
            $data = DB::select("SELECT DISTINCT(tipcliente)
                                FROM ".$this->getValueParams('_dbSelected').".adata_mut_" . $dbC . "_start 
                                WHERE tipcliente!='9' AND tipcliente!='0' AND tipcliente!='Otro'");

            $this->_fieldSelectInQuery = 'tipcliente';

            $tipoCliente = ['filter' => 'Tipo_Cliente', 'datas' => $this->contentfilter($data, 'tipcliente')];
        }

        if ($dbC == 'ges') {
            $data = DB::select("SELECT DISTINCT(canal)
                                FROM ".$this->getValueParams('_dbSelected').".adata_mut_" . $dbC . "_start
                                WHERE canal != '0' and canal != '10'");
            $this->_fieldSelectInQuery = 'canal';

            $tipoCanal = ['filter' => 'Canal', 'datas' => $this->contentfilter($data, 'canal')];

            return ['filters' => [(object)$tipoCliente, (object)$macrosegmento, (object)$tipoCanal], 'status' => Response::HTTP_OK];
        }

        if ($dbC == 'hos' || $dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh' || $dbC == 'img') {
            $data = DB::select("SELECT DISTINCT(tatencion)
                            FROM ".$this->getValueParams('_dbSelected').".adata_mut_" . $dbC . "_start
                            where tatencion != '0'");

            $this->_fieldSelectInQuery = 'tatencion';

            $tipAtencion = ['filter' => 'Tipo_Atencion', 'datas' => $this->contentfilter($data, 'tatencion')];
        }

        if ($dbC == 'hos' || $dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh'|| $dbC == 'img' || $dbC == 'con' || $dbC == 'cop') {
            $cond = '';
            if ($datafilters != null && strpos($datafilters,'zonal') != false)
            {
                $cond = " AND zonal = '". $request->get('Zona')."'"; 
            }

            if(isset($jwt[env('AUTH0_AUD')]->centros)){
                $long = sizeof($jwt[env('AUTH0_AUD')]->centros);
                //print_r($jwt[env('AUTH0_AUD')]->centros);exit;
    
                if($long == 1){ 
                    $obj = array([$jwt[env('AUTH0_AUD')]->centros[0] =>  $jwt[env('AUTH0_AUD')]->centros[0]]);
                }
                
                if($long > 1){
                    $obj = array($jwt[env('AUTH0_AUD')]->centros[0] =>  $jwt[env('AUTH0_AUD')]->centros[0]);
                    for($i=1; $i<$long; $i++){
                        $obj = array_merge($obj,array($jwt[env('AUTH0_AUD')]->centros[$i] =>  $jwt[env('AUTH0_AUD')]->centros[$i]));
                    }
                }

                $CenAtencionn = ['filter' => 'Centro_Atencion', 'datas' => $obj];
            }

            if(empty($jwt[env('AUTH0_AUD')]->centros)){
                $data = DB::select("SELECT DISTINCT(catencion)
                                FROM ".$this->getValueParams('_dbSelected').".adata_mut_" . $dbC . "_start
                                WHERE catencion != '' and catencion != '0' $cond");

                $this->_fieldSelectInQuery = 'catencion';

                $CenAtencionn = ['filter' => 'Centro_Atencion', 'datas' => $this->contentfilter($data, 'catencion')];
            }
        }

        if ($dbC == 'hos' || $dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh' || $dbC == 'img') {
            $data = DB::select("SELECT DISTINCT(gerenciamedica)
                                FROM ".$this->getValueParams('_dbSelected').".adata_mut_" . $dbC . "_start
                                WHERE gerenciamedica != '' and gerenciamedica != '1' and gerenciamedica != '0'");
                                
            $this->_fieldSelectInQuery = 'gerenciamedica';

            $Gerencia = ['filter' => 'Gerencia_Medica', 'datas' => $this->contentfilter($data, 'gerenciamedica')];
        }

        if ($dbC == 'hos' || $dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh' || $dbC == 'img') {
            $data = DB::select("SELECT DISTINCT(aatencion)
                                FROM ".$this->getValueParams('_dbSelected').".adata_mut_" . $dbC . "_start
                                WHERE aatencion != '0' AND aatencion != '9' AND aatencion != ''");
                                
            $this->_fieldSelectInQuery = 'aatencion';

            $AreaAten = ['filter' => 'Area_Atencion', 'datas' => $this->contentfilter($data, 'aatencion')];
        }

        if ($dbC == 'hos' || $dbC == 'amb' || $dbC == 'urg' || $dbC == 'reh' || $dbC == 'img'|| $dbC == 'con'|| $dbC == 'cop') {
            if(isset($jwt[env('AUTH0_AUD')]->zona)){
                //$ZonaHos = ['filter' => 'Zona', 'datas' => ''];
                $ZonaHos = null;
            }

            if(empty($jwt[env('AUTH0_AUD')]->zona)){
                $data = DB::select("SELECT DISTINCT(zonal)
                                    FROM ".$this->getValueParams('_dbSelected').".adata_mut_" . $dbC . "_start
                                    WHERE zonal != '0' AND zonal != ''");
                                    
                $this->_fieldSelectInQuery = 'zonal';

                $ZonaHos = ['filter' => 'Zona', 'datas' => $this->contentfilter($data, 'zonal')];
            }

            return ['filters' => [ (object)$tipAtencion, (object)$CenAtencionn, (object)$Gerencia,  (object)$AreaAten,  (object)$ZonaHos], 'status' => Response::HTTP_OK];
        }

        return ['filters' => [(object)$macrosegmento], 'status' => Response::HTTP_OK];
        }
    }

    private function detailsProcedencia($db, $endDate, $startDate, $filterClient)
    {
        if ($filterClient != 'all') {
            $data = DB::select("Select *, ROUND(proce/total*100, 2) as porcentaje  from 
                                    (SELECT count(procedencia) as proce, procedencia 
                                    FROM ".$this->getValueParams('_dbSelected')."." . $db . "_start  as a
                                    left join ".$this->getValueParams('_dbSelected').".$db as b
                                    on a.token = b.token
                                    where procedencia != '' and procedencia != '-' and b.date_survey BETWEEN '$startDate' and '$endDate' AND etapaencuesta = 'P2' ". $this->filterZona." ". $this->filterCentro."
                                    group by procedencia) as a join 
                                    (select COUNT(*) as total 
                                    from ".$this->getValueParams('_dbSelected')."." . $db . "_start as a
                                    left join ".$this->getValueParams('_dbSelected').".$db as b
                                    on a.token = b.token
                                    where procedencia != '' and procedencia != '-' and b.date_survey BETWEEN '$startDate' and '$endDate' AND etapaencuesta = 'P2' ". $this->filterZona." ". $this->filterCentro.")
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

    private function graphProcedencia($db, $startDateFilterMonth, $endDateFilterMonth, $filterClient)
    {
        $dataProcedencia = $this->detailsProcedencia($db, $startDateFilterMonth, $endDateFilterMonth, $filterClient);

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

    private function cardCsatDriversMutual($csat, $name, $graphCsatM, $ButFilterWeeks, $width, $height)
    {
        return [
            "height" => $height,
            "width" => $width,
            "type" => "chart",
            "props" => [
                "callToAction" => $ButFilterWeeks,
                "icon" => "arrow-right",
                "text" => $csat . " • " . $name,
                "chart" => [
                    "fields" => [
                        [
                            "type" => "stacked-bar",
                            "key" => "insatisfechos",
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
                            "key" => "satisfechos",
                            "text" => "Satisfechos",
                            "bgColor" => "#17C784",
                        ],
                        [
                            "type" => "line",
                            "key" => "csat",
                            "text" => $csat,
                            "bgColor" => "#1a90ff",
                        ],
                    ],
                    "values" => $graphCsatM
                ],
            ],
        ];
    }

    private function consolidateMutual(){
        return [
            'name'      => 'CONSOLIDADO',
            'base'      => 'mutcon',
            'customer'  => 'MUT001',
        ];
    }

    private function consolidateMutualPeso(){
        return [
            'name'      => 'CONSOLIDADO',
            'base'      => 'mutcop',
            'customer'  => 'MUT001',
        ];
    }

    private function cardNpsBanmedica($dataNPSGraph)
    {
        //$indicador === 'CSAT' ? print_r($dataNPSGraph) : print_r('nada');
        return [
            "height" => 4,
            "width" => 12,
            "type" => "chart",
            "props" => [
                "icon" => "arrow-right",
                "text" => "NPS" ,
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


    //DETAILS DASH
    public function detailsDash($request, $jwt)
    {
        $closedMutual = false;

        if(!isset($request->Centro_Atencion)){
            $centros=$this->setFilterCentro($jwt, $request);
            //echo $centros;
            if(isset($jwt[env('AUTH0_AUD')]->centros)){
                $request->merge(['Centro_Atencion'=>$centros]);
            }
            //echo '----'.$request->Centro_Atencion;
        }

        if( $request->survey == 'mutcon' )
        {
            if(isset($jwt[env('AUTH0_AUD')]->surveysActive)){
                    $request->merge(['survey'=>'mutcon']);
                }
        }
        if( $request->survey == 'mutcop' )
        {
            if(isset($jwt[env('AUTH0_AUD')]->surveysActive)){
                    $request->merge(['survey'=>'mutcop']);
                }
        }

        // if($jwt[env('AUTH0_AUD')]->closedLoop == true){
        //     $closedMutual = true;
        // }

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
                $datafilters .= ' and date_survey between date_sub(NOW(), interval 9 week) and NOW() ';
                $group = " week ";
            }
            if ($datafilters == '') {
                $datafilters .= ' date_survey between date_sub(NOW(), interval 9 week) and NOW() ';
                $group = " week ";
            }
        }

        $filterClient  = ($request->client === null) ? $this->getValueParams('_initialFilter') : $request->client;
        $indetifyClient = substr($request->survey, 0, 3);
        $indetifyClient = ($filterClient == 'all') ? $indetifyClient : $filterClient;
      
        $db = 'adata_'.substr($request->survey,0,3).'_'.trim(substr($request->survey,3,6));

        $this->whereConsolidado(substr($request->survey,0,6),$jwt);

        $rankingSuc = null;
        $ges = null;
        $ejecutivo = null;
        $sucNpsCsat =  null;
        $regiones = null;
        $sucursal = null;
        $call = null;
        $venta = null;
        $Procedencia = null;
        $csat1 = null;
        $csat2 = null;
        $csat3 = null;
        $csat4 = null;
        $csat5 = null;
        
        $dataNps    = $this->resumenNpsM($db, $dateIni, $dateEndIndicatorPrincipal, 'nps', $filterClient, $datafilters);

        if ($this->getValueParams('_dbSelected')  == 'customer_colmena'  && substr($request->survey, 0, 3) == 'mut'  && ($request->survey != 'mutredsms')) {
            $name = 'Mutual';
            $nameCsat1 = 'Tiempo espera para tu atención';
            $nameCsat2 = 'Amabilidad profesionales Mutual';

            if ($db == 'adata_mut_amb'){
                $nameCsat3 = "Amabilidad personal médico";
                $nameCsat4 = "Claridad información entregada";
                $nameCsat5 = "Comodidad de instalaciones";
            }

            if ($db == 'adata_mut_urg'){
                $nameCsat3 = "Amabilidad personal médico";
                $nameCsat4 = "Claridad información entregada";
                $nameCsat5 = "Instalaciones y equipamiento para atención";
            }

            if ($db == 'adata_mut_reh'){
                $nameCsat3 = "Claridad información entregada";
                $nameCsat4 = "Instalaciones y equipamiento para atención";
                $nameCsat5 = "Resultados obtenidos con rehabilitación";
            }

            if ($db == 'adata_mut_hos'){
                $nameCsat1 = "Amabilidad personal clínico";
                $nameCsat2 = "Amabilidad personal médico";
                $nameCsat3 = "Claridad información entregada";
                $nameCsat4 = "Resolución problema salud";
                $nameCsat5 = "Instalaciones y quipamiento para atención";
            }

            if ($db == 'adata_mut_img'){
                $nameCsat3 = "Amabilidad personal clínico";
                $nameCsat4 = "Comodidad recepción";
                $nameCsat5 = "Claridad información entregada";
            }

            if(substr($request->survey, 3, 3) != 'con' && substr($request->survey, 3, 3) != 'cop'){
                $dataCsat1Graph       = $this->graphCsatMutual($db, 'csat1', $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
                $dataCsat2Graph       = $this->graphCsatMutual($db, 'csat2', $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
                $dataCsat3Graph       = $this->graphCsatMutual($db, 'csat3', $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
                $dataCsat4Graph       = $this->graphCsatMutual($db, 'csat4', $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
                $dataCsat5Graph       = substr($request->survey, 3, 3) != 'red'? $this->graphCsatMutual($db, 'csat5', $dateIni, $dateEnd, 'one', 'two', $datafilters, $group) : null;
                $graphCSATDrivers     = $this->GraphCSATDriversMutual($db, trim($request->survey),  $endDateFilterMonth, $startDateFilterMonth, 'one', 'two', $datafilters);
            }

            $dataIsn     = $this->graphCsatMutual($db, 'csat', $dateIni, $dateEnd, 'one', 'two', $datafilters, $group);
            $dataIsnP    = $this->graphInsMutual($db, 'csat',  $endDateFilterMonth, $startDateFilterMonth, 'all',  $datafilters);
            $dataNPSGraph         = $this->graphNps($db, 'nps', $dateIni, '2022-04-18', 'one', 'two', $datafilters, $group);
            
            $datasStatsByTaps     = null;

            if ($db == 'adata_mut_amb' ||  $db == 'adata_mut_urg' ||  $db == 'adata_mut_reh' || $db == 'adata_mut_hos' ||  $db == 'adata_mut_img') {
                $csat1 = $this->cardCsatDriversMutual($nameCsat1, $name, $dataCsat1Graph, $this->ButFilterWeeks, 6, 3);
                $csat2 = $this->cardCsatDriversMutual($nameCsat2, $name, $dataCsat2Graph, $this->ButFilterWeeks, 6, 3);
                $csat3 = $this->cardCsatDriversMutual($nameCsat3, $name, $dataCsat3Graph, $this->ButFilterWeeks, 6, 3);
                $csat4 = $this->cardCsatDriversMutual($nameCsat4, $name, $dataCsat4Graph, $this->ButFilterWeeks, 6, 3);
                $csat5 = $this->cardCsatDriversMutual($nameCsat5, $name, $dataCsat5Graph, $this->ButFilterWeeks, 6, 3);
            }

            if ($db == 'adata_mut_img') {
                $Procedencia = $this->graphProcedencia($db, $endDateFilterMonth, $startDateFilterMonth, $filterClient);
            }

            if ($db == 'adata_mut_reh' || $db == 'adata_mut_amb' || $db == 'adata_mut_urg') {
                $rankingSuc = $this->ranking($db, 'catencion', 'CentroAtencion', $endDateFilterMonth, $startDateFilterMonth, 'one',$datafilters, 6);
            }
            
            $welcome            = $this->welcome(substr($request->survey, 0, 3), $filterClient,$request->survey, $db);
            $performance        = $this->cardsPerformace($dataNps, $dataIsnP , $dateEnd, $dateIni, $request->survey, $datafilters);
            $npsConsolidado     = $this->cardCsatDriversMutual('ISN', $name, $dataIsn , $this->ButFilterWeeks, 12, 4);
            $npsBan             = (substr($request->survey, 3, 3) == 'con' || substr($request->survey, 3, 3) == 'cop')? $this->cardNpsBanmedica($dataNPSGraph) : $this->CSATJourney($graphCSATDrivers);
            $npsVid             = (substr($request->survey, 3, 3) == 'con' || substr($request->survey, 3, 3) == 'cop')? null : $this->CSATDrivers($graphCSATDrivers);
            $csatJourney        = $csat1;
            $csatDrivers        = $csat2;
            $cx                 = $csat3;
            $wordCloud          = $csat4;
            $closedLoop         = $csat5;
            $detailGender       = null;
            $detailGeneration   = null; //$this->closedLoop($db, 'nps', $endDateFilterMonth, $startDateFilterMonth, $filterClient, $datafilters)
            $detailsProcedencia = $Procedencia;
            $box14              = $venta;
            $box15              = $call;
            $box16              = $sucursal;
            $box17              = $regiones;
            $box18              = $ejecutivo;
            $box19              = $ges;
            $box20              = $sucNpsCsat;
            $box21              = $rankingSuc;
            $box22              = null;
        }
        $filters = $this->filters($request, $jwt, $datafilters);
        $data = [
            'client' =>  $this->setNameClient('_nameClient'),
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
}