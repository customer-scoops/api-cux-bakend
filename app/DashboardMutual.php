<?php namespace App;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;

class DashboardMutual extends Dashboard
{
    private $_filterZona = '';

    public function __construct($jwt, $request)
    {
        parent::__construct($jwt);
        $this->setFilterZona($jwt, $request);
    }

    public function generalInfo($request, $jwt)
    {
        $surveys = $this->getDataSurvey($request, $jwt);
        $data = [];
        $otherGraph = [];
        if ($surveys['status'] == 200) {
            if($surveys['datas'][0]['customer'] == 'MUT001'){
                array_push($surveys['datas'], $this->consolidateMutual());
            }

            foreach ($surveys['datas'] as $key => $value) {
                if ($value['base'] != 'mutred'){
                    $db         = 'adata_'.substr($value['base'],0,3).'_'.substr($value['base'],3,6);
                    $db2        = $this->primaryTable($db);
                    $infoNps    = [$this->infoNpsMutual($db, date('Y-m-d'),date('Y-m-01'),'nps',null,$this->getInitialFilter(), $this->surveyFilterZona($value['base'], $jwt, $request))]; 
                    $otherGraph = [$this->infoCsat($db, date('Y-m-d'),date('Y-m-01'), 'csat',$this->getInitialFilter())];
                    
                    if(substr($value['base'],0,3) == 'mut'){
                        $otherGraph = [$this->infoCsat($db,date('Y-m-d'),date('Y-m-01'), 'csat',$this->getInitialFilter())];
                    } 
                  
                    $data[] = [
                        'client'        => $this->setNameClient('_nameClient'), 'clients'  => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: null,
                        "title"         => ucwords(strtolower($value['name'])),
                        "identifier"    => $value['base'],
                        "principalIndicator" => $infoNps,

                        "journeyMap"    => $this->GraphCSATDrivers($db,$db2,$value['base'],'csat',date('Y-m-d'),date('Y-m-01'),$this->getInitialFilter(),'one'),
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

    protected function infoNpsMutual($table,  $dateIni, $dateEnd, $indicador, $filter, $dataFilter,$zona)
    {
     
        $generalDataNps             = $this->resumenNps($table,  $dateIni, $dateEnd, $indicador, $filter, '', $zona);
        $generalDataNps['graph']    = $this->graphNps($table,  $indicador, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $filter, 'one', $zona);

        return $generalDataNps;
    }

    private function dbResumenNps1 ($table,$indicador,$dateIni,$dateEnd, $datafilters, $filter, $zona)
    {
        $query = "SELECT count(*) as total, 
        ((count(if(nps <= ".$this->getValueParams('_maxNps').", nps, NULL))*100)/COUNT(CASE WHEN nps !=99 THEN 1 END)) as detractor, 
        ((count(if(nps = ".$this->getValueParams('_minMaxNps')." or  nps = ".$this->getValueParams('_maxMaxNps')." , nps, NULL))*100)/COUNT(CASE WHEN nps != 99 THEN 1 END)) as promotor,
        ((count(if(nps =  ".$this->getValueParams('_maxMediumNps')." OR nps = ".$this->getValueParams('_minMediumNps').", nps, NULL))*100)/COUNT(CASE WHEN nps != 99 THEN 1 END)) as neutral,
        AVG(nps) as promedio,
        ROUND(((COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minMaxNps')." AND ".$this->getValueParams('_maxMaxNps')." THEN 1 END) - 
        COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minNps')." AND ".$this->getValueParams('_maxNps')." THEN 1 END)) / 
        (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS,  ".$this->getValueParams('_fieldSelectInQuery')."
        FROM ".$this->getValueParams('_dbSelected').".$table as a
        LEFT JOIN ".$this->getValueParams('_dbSelected')."." . $table . "_start as b
        on a.token = b.token
        WHERE date_survey BETWEEN '$dateIni' AND '$dateEnd' ".$this->activeP2 ($table)." $zona
        GROUP BY a.mes, a.annio
        ORDER BY date_survey ASC";
        $data = DB::select($query);


        return $data;
    }
    //OKK
    private function resumenNps($table,  $dateEnd, $dateIni, $indicador, $filter, $datafilters, $zona)
    {

        if ($datafilters)
            $datafilters = " AND $datafilters";
        
        $data = $this->dbResumenNps1($table,$indicador,$dateIni,$dateEnd, '', $filter, $zona);

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
                "smAvg"         => $this->AVGLast6MonthNPS($table, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $indicador, $filter)
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
                "percentage"        => substr($table, 6, 3) == 'mut'? '0' : $npsActive - round($npsPreviousPeriod),
                "smAvg"             => substr($table, 6, 3) == 'mut'? '0' :$this->AVGLast6MonthNPS($table, date('Y-m-d'), date('Y-m-d', strtotime(date('Y-m-d') . "- 5 month")), $indicador, $filter),
                'NPSPReV'           => $npsPreviousPeriod,
            ];
        }
    }

    private function graphNps($table, $indicador, $dateIni, $dateEnd, $filter, $struct = 'two', $datafilters = null, $group = null)
    {
        $activeP2 ='';
        $graphNPS  = [];

        if(substr($table, 6, 3) == 'mut'){
            return $graphNPS;
        }
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

        
        
        if ($filter != 'all') {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('$this->_minMaxNps')." AND ".$this->getValueParams('$this->_maxMaxNps')." THEN 1 END) - 
                                COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minNps')." AND ".$this->getValueParams('_maxNps')." THEN 1 END)) / 
                                COUNT(CASE WHEN nps!=99 THEN 1 END) * 100),1) AS NPS, 
                                count(if(nps <= ".$this->getValueParams('_maxNps')." , nps, NULL)) as Cdet,
					            count(if(nps = ".$this->getValueParams('_minMaxNps')." or nps =".$this->getValueParams('_maxMaxNps').", nps, NULL)) as Cpro,
					            count(if(nps=".$this->getValueParams('_maxMediumNps')." OR nps=".$this->getValueParams('_minMediumNps').", nps, NULL)) as Cneu,              
                                count(*) as total, 
                                ((count(if(nps <= ".$this->getValueParams('_maxNps').", nps, NULL))*100)/count(CASE WHEN nps != 99 THEN nps END)) as detractor, 
                                ((count(if(nps = ".$this->getValueParams('_minMaxNps')." OR nps =".$this->getValueParams('_maxMaxNps').", nps, NULL))*100)/count(CASE WHEN nps != 99 THEN nps END)) as promotor, 
                                ((count(if(nps=".$this->getValueParams('_maxMediumNps')." OR nps=".$this->getValueParams('_minMediumNps').", nps, NULL))*100)/count(CASE WHEN nps != 99 THEN nps END)) as neutral,              
                                a.mes, a.annio, WEEK(date_survey) AS week,".$this->getValueParams('_fieldSelectInQuery')."  
                                FROM ".$this->getValueParams('_dbSelected').".$table as a
                                INNER JOIN ".$this->getValueParams('_dbSelected').".".$table."_start as b ON a.token = b.token 
                                WHERE  $where $activeP2 $datafilters 
                                GROUP BY $group2
                                ORDER BY date_survey ASC");
        }

        if ($group2 == 'week') 
        { 
            $mondayWeek = $this->getFirstMond();
        }
       $count = count($data)-1;

        if ($data) {
            if ($data[0]->total === null) {
                foreach ($data as $key => $value) {
                    if ($struct != 'one') {
                        $graphNPS[] = [
                            //'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                            'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Lun ' . date('m-d', strtotime($mondayWeek . "- $count week")) . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
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

                    $count -= 1;
                }
            }
            if ($data[0]->total !== null) {
                foreach ($data as $key => $value) {
                    if ($struct != 'one') {
                        $graphNPS[] = [
                            //'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
                            'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Lun ' . date('m-d', strtotime($mondayWeek . "- $count week")) . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
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

                    $count -= 1;
                }
            }
        }
       
        if ($data === null) {
                if ($struct != 'one') {
                    $graphNPS[] = [
                        //'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Semana ' . $value->week . ' (0)',
                        'xLegend'  => (trim($group) != 'week') ? 'Mes ' . $value->mes . '-' . $value->annio . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')' : 'Lun ' . date('m-d', strtotime($mondayWeek . "- $count week")) . ' (' . ($value->Cdet + $value->Cpro + $value->Cneu) . ')',
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
            $data = DB::select("SELECT sum(NPS) as total, COUNT(distinct mes) as meses from (SELECT ROUND(((COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minMaxNps')." AND ".$this->getValueParams('_maxMaxNps')." THEN 1 END) -
                                COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minNps')." AND ".$this->getValueParams('_maxNps')." THEN 1 END)) /
                                 COUNT(CASE WHEN nps != 99 THEN 1 END) * 100),1) AS NPS, mes, annio
                                FROM ".$this->getValueParams('_dbSelected').".$table
                                WHERE date_survey BETWEEN '$dateEnd' AND '$dateIni' 
                                group by annio, mes) as a");
        }
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
       
        $table2 = $this->primaryTable($table);

        if ($this->getValueParams('_dbSelected') == 'customer_colmena' && substr($table, 6, 3) == 'mut' || substr($table, 0, 3) == 'MUT' ) {
            $data = DB::select("SELECT ROUND(((COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minMaxNps')." AND ".$this->getValueParams('_maxMaxNps')." THEN 1 END) -
                                COUNT(CASE WHEN nps BETWEEN ".$this->getValueParams('_minNps')." AND ".$this->getValueParams('_maxNps')." THEN 1 END)) /
                                (COUNT(CASE WHEN nps != 99 THEN nps END)) * 100),1) AS NPS
                                FROM ".$this->getValueParams('_dbSelected').".$table as a
                                left join ".$this->getValueParams('_dbSelected').".".$table."_start as b
                                on a.token = b.token
                                WHERE a.mes = $mes and a.annio = $annio $datafilters");
            return $data[0]->NPS;
        }
    }

    private function surveyFilterZona($survey, $jwt, $request){
        $filter = ['mutamb','mutreh','muturg','mutimg','muthos'];
        if(array_search( $survey,$filter, true)){
            return " AND zonal = '". $this->setFilterZona($jwt, $request);
        }
    }

    private function setFilterZona($jwt, $request){
        // if (isset($jwt[env('AUTH0_AUD')]->surveysActive)) {
        //     foreach ($jwt[env('AUTH0_AUD')]->surveysActive as $key => $value) {
        //         $surv[] = $value; 
        //     }
        //     $db->whereIn('codDbase',$surv);
        //     unset($surv);
        // }
        // array_search($this->surveyFilterZona());

        if(isset($jwt[env('AUTH0_AUD')]->zona))
            $this->_filterZona = " AND zonal = '". $jwt[env('AUTH0_AUD')]->zona."'";

        if($request->get('zonal') !== null)
            $this->_filterZona = " AND zonal = '". trim($request->get('zonal'))."'";
    } 

}