<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Dictionary;

class TextMining extends Dictionary
{
    
    private $_dictionary;

    public function __construct(Request $request)
    {
        $this->_dictionary = new Dictionary($request);
    }

    private function selectDB($survey){
        $dbData = [
          "ban" => "customer_banmedica",
          "jet" => "customer_jetsmart",
          "tra" => "customer_colmena",
        ];
    
        return $dbData[substr($survey, 0, 3)];
      }
    
      public function response(Request $request){
        try {
    
          //$result = [];
          //$response = [];
    
          // $pruebNull = [
          //   'count' => 0,
          //   'prom' => 0,
          //   'det' => 0,
          //   'neut' => 0,
          //   'mes' => 0,
          //   'annio' => 0,
          // ];
          //Preguntarle a Fermin si esto lo pongo en el constructor
          $startDate = $request->get('startDate');
          $endDate = $request->get('endDate');
          $survey = $request->get('survey');
          
          
          $firstDate  = date_create($startDate);
          $secondDate = date_create($endDate);

          //Encerrar todo en un if que si la fecha final es mayor a la inicial devuelva un error o que sea todo nulo. Preguntar que devolver.

          $diffMonth = $firstDate <= $secondDate ? date_diff($firstDate, $secondDate)->format('%m') + 1 : 0;

          
          //Hacer esto en una funcion en este archivo para seleccionar la query
          // $dbSelected = $this->selectDB($survey);
          
          // if(substr($survey, 0, 3) == 'ban'){
          //   $query = "SELECT `obs_nps`, nps, mes, annio 
          //             FROM " . $dbSelected . ".adata_ban_" . substr($survey, 3, 3) . " 
          //             WHERE `date_survey` BETWEEN '$startDate' AND '$endDate' AND `etapaencuesta` = 'P2' AND obs_nps != ''
          //             GROUP BY mes, annio, obs_nps
          //             UNION
          //             SELECT `obs_nps`, nps, mes, annio 
          //             FROM " . $dbSelected . ".adata_vid_" . substr($survey, 3, 3) . " 
          //             WHERE `date_survey` BETWEEN '$startDate' AND '$endDate' AND `etapaencuesta` = 'P2' AND obs_nps != ''
          //             GROUP BY mes, annio, obs_nps"; //Armo la query para que me traiga las observaciones y los nps del dia de la fecha
          // }
          
          // if(substr($survey, 0, 3) != 'ban'){
          //   $query = "SELECT `obs_nps`, nps, mes, annio 
          //   FROM " . $dbSelected . ".adata_".substr($survey, 0, 3). "_" . substr($survey, 3, 3) . " 
          //   WHERE `date_survey` BETWEEN '$startDate' AND '$endDate' AND `etapaencuesta` = 'P2' AND obs_nps != ''
          //   GROUP BY mes, annio, obs_nps"; //Armo la query para que me traiga las observaciones y los nps del dia de la fecha
          // }
    
          $query = $this->querySelector($survey, $startDate, $endDate); //Lo de arriba se paso a una función aparte.
          
          $data = DB::select($query);
          
          $text = $this->_dictionary->getTexts();
          
          //Sacar estos foreach en una sola funcion APARTE EN ESTE ARCHIVO
          // foreach ($text as $group => $value) {
          //   $total = 0;
          //   foreach ($value as $subGroup => $val) {
          //     $prueb = [];
          //     $wordCloudCount = 0;
          //     foreach ($data as $key => $dat) {
          //       $res = Str::contains(strtolower($dat->obs_nps), $val);
                
          //       if($res){
          //         $total += 1;
          //         $wordCloudCount += 1;
          //          if(!isset($prueb[$dat->annio.$dat->mes]['count'])){
          //           $prueb[$dat->annio.$dat->mes]['count'] = 0;
          //           $prueb[$dat->annio.$dat->mes]['prom'] = 0;
          //           $prueb[$dat->annio.$dat->mes]['det'] = 0;
          //           $prueb[$dat->annio.$dat->mes]['neut'] = 0;
          //         }
                  
          //         $prueb[$dat->annio.$dat->mes] = [
          //           'count' => $prueb[$dat->annio.$dat->mes]['count'] + 1,
          //           'prom'  => $dat->nps >= 9 ? $prueb[$dat->annio.$dat->mes]['prom'] + 1 : $prueb[$dat->annio.$dat->mes]['prom'],
          //           'det'   => $dat->nps <= 6 && $dat->nps >= 0 ? $prueb[$dat->annio.$dat->mes]['det'] + 1 : $prueb[$dat->annio.$dat->mes]['det'],
          //           'neut'  => $dat->nps == 7 || $dat->nps == 8 ? $prueb[$dat->annio.$dat->mes]['neut'] + 1 : $prueb[$dat->annio.$dat->mes]['neut'],
          //           'mes'   => $dat->mes,
          //           'annio' => $dat->annio,
          //         ];
          //       }
          //     }
    
          //     if($wordCloudCount){
          //       $response['wordCloud'][$subGroup] = $wordCloudCount;
          //     }
    
          //     if($prueb && count($prueb) == $diffMonth){
          //       $result[$group][$subGroup] = $prueb;
          //     }
              
          //     if($prueb && count($prueb) != $diffMonth){
          //       $result[$group][$subGroup] = $prueb;
          //       for($i = count($result[$group][$subGroup]); $i < $diffMonth; $i++){
          //         $result[$group][$subGroup][$i] = $pruebNull;
          //       }
          //     }
              
          //     if(!$prueb){
          //       for($i = 0; $i < $diffMonth; $i++){
          //         $result[$group][$subGroup][$i] = $pruebNull;
          //       }
          //     }
          //   }
            
          //   $pos = 0;
          //   foreach ($result[$group] as $key1 => $value) {
          //     $cant = 1;
    
          //     foreach ($value as $key2 => $val) {
    
          //       if($val['count'] != 0){
          //         $response['values'][$group][$pos][] = [
          //           'word'.$cant        => $key1,
          //           'quantity'.$cant    => $val['count'],
          //           'percentaje'.$cant  => round($val['count'] * 100 / $total),
          //           'nps'.$cant         => $val['prom'] || $val['det'] || $val['neut'] ? round(($val['prom']-$val['det']) * 100 / ($val['prom'] + $val['det'] + $val['neut'])) : 'N/A',
          //           'group'.$cant       => $key1 . ' en ' . $group,
          //         ];
          //       }
    
          //       if($val['count'] == 0){
          //         $response['values'][$group][$pos][] = [
          //           'word'.$cant        => $key1,
          //           'quantity'.$cant    => 0,
          //           'percentaje'.$cant  => 0,
          //           'nps'.$cant         => 'N/A',
          //           'group'.$cant       => $key1 . ' en ' . $group,
          //         ];
          //       }
          //       $cant++;
          //     }
          //     $pos++;
          //   }
          // }
          
          $dataOrder = $this->dataOrder($data, $text, $diffMonth); 

          //Fin Sacar estos foreach en una sola funcion APARTE EN ESTE ARCHIVO

          //Sacar este for en una sola funcion APARTE EN ESTE ARCHIVO
          // for($i = 0; $i < $diffMonth; $i++){
          //   $colNum = $i + 1;
          //   $response['columns'][$i] = [
          //     'word'.$colNum       => date("m/Y", strtotime($startDate . "+ $i month")),
          //     'quantity'.$colNum   => 'Cantidad',
          //     'percentaje'.$colNum => '%',
          //     'nps'.$colNum        => 'NPS',
          //   ];
          // }

          $response = $this->columnsOrder($dataOrder, $diffMonth, $startDate);
          //Sacar este for en una sola funcion APARTE EN ESTE ARCHIVO
          dd($response);

          return json_encode($response);
        } catch (\Throwable $th) {
          return $th;
        }
      }

      private function querySelector($survey, $startDate, $endDate) { //En caso de que el start date y el end date se pongan en el constructor sacarlo de aca y ponerle el this

        $dbSelected = $this->selectDB($survey); //Preguntarle a Fermin si esto lo dejo aca o lo pongo en el constructor

        if(substr($survey, 0, 3) == 'ban'){
          $query = "SELECT `obs_nps`, nps, mes, annio 
                    FROM " . $dbSelected . ".adata_ban_" . substr($survey, 3, 3) . " 
                    WHERE `date_survey` BETWEEN '$startDate' AND '$endDate' AND `etapaencuesta` = 'P2' AND obs_nps != ''
                    GROUP BY mes, annio, obs_nps
                    UNION
                    SELECT `obs_nps`, nps, mes, annio 
                    FROM " . $dbSelected . ".adata_vid_" . substr($survey, 3, 3) . " 
                    WHERE `date_survey` BETWEEN '$startDate' AND '$endDate' AND `etapaencuesta` = 'P2' AND obs_nps != ''
                    GROUP BY mes, annio, obs_nps"; //Armo la query para que me traiga las observaciones y los nps del dia de la fecha
        }
        
        if(substr($survey, 0, 3) != 'ban'){
          $query = "SELECT `obs_nps`, nps, mes, annio 
          FROM " . $dbSelected . ".adata_".substr($survey, 0, 3). "_" . substr($survey, 3, 3) . " 
          WHERE `date_survey` BETWEEN '$startDate' AND '$endDate' AND `etapaencuesta` = 'P2' AND obs_nps != ''
          GROUP BY mes, annio, obs_nps"; //Armo la query para que me traiga las observaciones y los nps del dia de la fecha
        }

        return $query;
      }

      private function dataOrder($data, $text, $diffMonth){
        $response = [];

        $pruebNull = [
          'count' => 0,
          'prom' => 0,
          'det' => 0,
          'neut' => 0,
          'mes' => 0,
          'annio' => 0,
        ];

        foreach ($text as $group => $value) {
          $total = 0;
          foreach ($value as $subGroup => $val) {
            $prueb = [];
            $wordCloudCount = 0;
            foreach ($data as $key => $dat) {
              $res = Str::contains(strtolower($dat->obs_nps), $val);
              
              if($res){
                $total += 1;
                $wordCloudCount += 1;
                 if(!isset($prueb[$dat->annio.$dat->mes]['count'])){
                  $prueb[$dat->annio.$dat->mes]['count'] = 0;
                  $prueb[$dat->annio.$dat->mes]['prom'] = 0;
                  $prueb[$dat->annio.$dat->mes]['det'] = 0;
                  $prueb[$dat->annio.$dat->mes]['neut'] = 0;
                }
                
                $prueb[$dat->annio.$dat->mes] = [
                  'count' => $prueb[$dat->annio.$dat->mes]['count'] + 1,
                  'prom'  => $dat->nps >= 9 ? $prueb[$dat->annio.$dat->mes]['prom'] + 1 : $prueb[$dat->annio.$dat->mes]['prom'],
                  'det'   => $dat->nps <= 6 && $dat->nps >= 0 ? $prueb[$dat->annio.$dat->mes]['det'] + 1 : $prueb[$dat->annio.$dat->mes]['det'],
                  'neut'  => $dat->nps == 7 || $dat->nps == 8 ? $prueb[$dat->annio.$dat->mes]['neut'] + 1 : $prueb[$dat->annio.$dat->mes]['neut'],
                  'mes'   => $dat->mes,
                  'annio' => $dat->annio,
                ];
              }
            }
  
            if($wordCloudCount){
              $response['wordCloud'][$subGroup] = $wordCloudCount;
            }
  
            if($prueb && count($prueb) == $diffMonth){
              $result[$group][$subGroup] = $prueb;
            }
            
            if($prueb && count($prueb) != $diffMonth){
              $result[$group][$subGroup] = $prueb;
              for($i = count($result[$group][$subGroup]); $i < $diffMonth; $i++){
                $result[$group][$subGroup][$i] = $pruebNull;
              }
            }
            
            if(!$prueb){
              for($i = 0; $i < $diffMonth; $i++){
                $result[$group][$subGroup][$i] = $pruebNull;
              }
            }
          }
          
          $pos = 0;
          foreach ($result[$group] as $key1 => $value) {
            $cant = 1;
  
            foreach ($value as $key2 => $val) {
  
              if($val['count'] != 0){
                $response['values'][$group][$pos][] = [
                  'word'.$cant        => $key1,
                  'quantity'.$cant    => $val['count'],
                  'percentaje'.$cant  => round($val['count'] * 100 / $total),
                  'nps'.$cant         => $val['prom'] || $val['det'] || $val['neut'] ? round(($val['prom']-$val['det']) * 100 / ($val['prom'] + $val['det'] + $val['neut'])) : 'N/A',
                  'group'.$cant       => $key1 . ' en ' . $group,
                ];
              }
  
              if($val['count'] == 0){
                $response['values'][$group][$pos][] = [
                  'word'.$cant        => $key1,
                  'quantity'.$cant    => 0,
                  'percentaje'.$cant  => 0,
                  'nps'.$cant         => 'N/A',
                  'group'.$cant       => $key1 . ' en ' . $group,
                ];
              }
              $cant++;
            }
            $pos++;
          }
        }

        return $response;
      }

      private function columnsOrder($response, $diffMonth, $startDate){ //Ver si se pone el start date en el constructor sacarlo de los parametros y colocarle el this.
        for($i = 0; $i < $diffMonth; $i++){
          $colNum = $i + 1;
          $response['columns'][$i] = [
            'word'.$colNum       => date("m/Y", strtotime($startDate . "+ $i month")),
            'quantity'.$colNum   => 'Cantidad',
            'percentaje'.$colNum => '%',
            'nps'.$colNum        => 'NPS',
          ];
        }

        return $response;
      }
}