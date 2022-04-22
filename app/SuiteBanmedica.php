<?php namespace App;

use Validator;
use Freshwork\ChileanBundle\Rut;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
//use DB;
use Illuminate\Support\Facades\DB;

class SuiteBanmedica extends Suite
{
   public function __construct($jwt)
   {
       parent::__construct($jwt);
   }

   public function saveUpdate($request, $jwt)
   {

       $rules = [
           "survey" => 'required|string|max:6',
           "ticket" => 'required|numeric',
           "status" => 'required|numeric',
           "detail" => 'required|string',
           "sbuStatus1" => 'required|string',
           "sbuStatus2" => 'required|string',
           //"data.field1" => 'required|string',
           //"data.field2" => 'required|string',
           //"data.field3" => 'required|string',
           //"dateSchedule" => 'required|date_format:Y-m-d',
           //"timeSchedule" => 'required|date_format:H:i:s'
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
                    //'fecha_programa_llamada'=> $request->dateSchedule,
                    //'hora_programa_llamada'=> $request->timeSchedule,
                    'field_1'=>$request->sbuStatus1,
                    'field_2'=>$request->sbuStatus2,
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
}