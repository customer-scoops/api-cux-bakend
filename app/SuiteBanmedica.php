<?php namespace App;

use Validator;
use Freshwork\ChileanBundle\Rut;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
//use DB;
use Illuminate\Support\Facades\DB;

class SuiteBanmedica extends Suite
{
   public function __construct($jwt, $request)
   {
       parent::__construct($jwt);
       $this->setIndicators($request);
   }

   public function saveUpdate($request, $jwt)
   {

       $rules = [
           "survey" => 'required|string|max:6',
           "ticket" => 'required|numeric',
           "status" => 'required|numeric',
           "detail" => 'required|string',
           "subStatus1" => 'required|string',
           "subStatus2" => 'required|string',
           //"data.field1" => 'required|string',
           //"data.field2" => 'required|string',
           //"data.field3" => 'required|string',
           "dateSchedule" => 'date_format:Y-m-d',
           "timeSchedule" => 'date_format:H:i:s'
       ];

       //print_r($request);
       //echo $request->sbuStatus1;exit;
       $validator = \Validator::make($request->all(), $rules);
       
       if ($validator->fails()) {
           return [
               "datas" => $validator->errors(),
               "status" => Response::HTTP_UNPROCESSABLE_ENTITY
            ];
        }

        try {
            $resp = DB::table($this->getDBSelected().'.'.'adata_'.substr($request->survey,0,3).'_'.substr($request->survey,3,6).'_start')->where('id', $request->ticket)->
            update(
                [
                    'estado_close' => $request->status, 
                    'det_close' => $request->detail, 
                    'fec_close'=>date('Y-m-d'),
                    'fecha_programa_llamada'=> $request->dateSchedule,
                    'hora_programa_llamada'=> $request->timeSchedule,
                    'field_1'=>$request->subStatus1,
                    'field_2'=>$request->subStatus2,
                    //'field_1'=>$request->data["field1"],
                    //'field_2'=>$request->data["field2"],
                    //'field_3'=>$request->data["field3"]
                    ]
                );
                
           if($resp===1){
               $namev = DB::table($this->getDBSelected().'.'.'adata_'.substr($request->survey,0,3).'_'.substr($request->survey,3,6).'_start')->where('id', $request->ticket)->first();
               //$this->sendedEmail($namev->nom, $namev->mail, $namev->token, $request->survey); // Cuando se pruebe hay que comentar esto para que no le mande le mail al cliente.
           }
           return[
               'datas'  => 'complet',
               'status' => Response::HTTP_CREATED
           ];
       }catch(\Throwable $e) {
           return [
               'datas'  => $e->getMessage(),
               'status' => Response::HTTP_UNPROCESSABLE_ENTITY
           ];
       }
   }

   public function setIndicators($request){
       
        if($request->get('typeClient') !== null) {
            $typeClient = $request->get('typeClient');
            // TODO validar endDate
            if($typeClient == 'promotor')
            {
                $this->setMinNps(9);
                $this->setMaxNps(10);
            }

            if($typeClient == 'neutral')
            {
                $this->setMinNps(7) ;
                $this->setMaxNps(8);
            }

            if($typeClient == 'detractor')
            {
                $this->setMinNps(0) ;
                $this->setMaxNps(6);
            }
        }
   }
}