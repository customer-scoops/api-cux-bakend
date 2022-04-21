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
       //echo $this->getDBSelected(); exit;
       $rules = [
           "survey" => 'required|string|max:6',
           "ticket" => 'required|numeric',
           "status" => 'required|numeric',
           "detail" => 'required|string'
       ];

       $validator = \Validator::make($request->all(), $rules);
       if ($validator->fails()) {
           return [
               "datas" => $validator->errors(),
               "status" => Response::HTTP_UNPROCESSABLE_ENTITY
           ];
       }
       try {
           $resp = DB::table($this->getDBSelected().'.'.'adata_'.substr($request->survey,0,3).'_'.substr($request->survey,3,6).'_start')->where('id', $request->ticket)->update(['estado_close' => $request->status, 'det_close' => $request->detail, 'fec_close'=>date('Y-m-d')]);
           //echo $resp;
           if($resp===1){
               $namev = DB::table($this->getDBSelected().'.'.'adata_'.substr($request->survey,0,3).'_'.substr($request->survey,3,6).'_start')->where('id', $request->ticket)->first();
               $this->sendedEmail($namev->nom, $namev->mail, $namev->token, $request->survey);
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