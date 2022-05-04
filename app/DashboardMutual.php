<?php namespace App;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;


class DashboardMutual extends Dashboard
{
    
    public function __construct($jwt)
    {
        parent::__construct($jwt);
    }

    public function generalInfo($request, $jwt)
    {
        $surveys = $this->getDataSurvey($request, $jwt);
        $data = [];
        $otherGraph = [];
     //dd($surveys);
        if ($surveys['status'] == 200) {
            if($surveys['datas'][0]['customer'] == 'MUT001'){
                array_push($surveys['datas'], $this->consolidateMutual());
            }
            
            foreach ($surveys['datas'] as $key => $value) {
                if ($value['base'] != 'mutred'){
                    $db = 'adata_'.substr($value['base'],0,3).'_'.substr($value['base'],3,6);
                    $db2 = $this->primaryTable($db);
                    $npsInDb = 'nps';
                    $csatInDb = 'csat';
                    $infoNps =[$this->infoNps($db, date('Y-m-d'),date('Y-m-01'),$npsInDb,$this->getInitialFilter())]; 
                    $otherGraph = [$this->infoCsat($db, date('Y-m-d'),date('Y-m-01'), $csatInDb,$this->getInitialFilter())];
                    
                    if(substr($value['base'],0,3) == 'mut'){
                        $otherGraph = [$this->infoCsat($db,date('Y-m-d'),date('Y-m-01'), $csatInDb,$this->getInitialFilter())];
                    } 
                  
                    $data[] = [
                        'client'        => $this->_nameClient, 'clients'  => isset($jwt[env('AUTH0_AUD')]->clients) ? $jwt[env('AUTH0_AUD')]->clients: null,
                        "title"         => ucwords(strtolower($value['name'])),
                        "identifier"    => $value['base'],
                        "principalIndicator" => $infoNps,

                        "journeyMap"    => $this->GraphCSATDrivers($db,$db2,$value['base'],$csatInDb,date('Y-m-d'),date('Y-m-01'),$this->getInitialFilter(),'one'),
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
}