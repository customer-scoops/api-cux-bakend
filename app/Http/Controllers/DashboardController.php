<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use App\Dashboard;
use App\DashboardMutual;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    use ApiResponser;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private $_dashboard;
    
    public function __construct(Request $request)
    {
        $this->_dashboard = new Dashboard($request->dataJwt);
    }
    public function index(Request $request){
        if(TRIM($request->dataJwt[env('AUTH0_AUD')]->client) == 'MUT001'){
            $dashboarMut = new DashboardMutual($request->dataJwt, $request);
            $data = $dashboarMut->generalInfo($request, $request->dataJwt);
        }
        if(TRIM($request->dataJwt[env('AUTH0_AUD')]->client) != 'MUT001'){
         $data = $this->_dashboard->generalInfo($request, $request->dataJwt);
        }
        return $this->generic($data['datas'], $data['status']);
    }
    public function indexBackCards(Request $request)
    {
        if(TRIM($request->dataJwt[env('AUTH0_AUD')]->client) == 'MUT001'){
            $dashboarMut = new DashboardMutual($request->dataJwt, $request);
            $data = $dashboarMut->backCards($request, $request->dataJwt);
        }
        if(TRIM($request->dataJwt[env('AUTH0_AUD')]->client) != 'MUT001'){
            $data = $this->_dashboard->backCards($request, $request->dataJwt);
        }
        
        return $this->generic($data['datas'], $data['status']);
    }
    //CX-INTELLIGENCE AND WORD CLUOD
    public function detailsDashCxWord(Request $request)
    {
        $data = $this->_dashboard->detailsDashCxWord($request, $request->dataJwt);
        //print_r($data);
        return $this->generic($data['datas'], $data['status']);
    }
    public function detailsDash(Request $request)
    {
        if(TRIM($request->dataJwt[env('AUTH0_AUD')]->client) == 'MUT001'){
            $dashboarMut = new DashboardMutual($request->dataJwt, $request);
            $data = $dashboarMut->detailsDash($request, $request->dataJwt);
        }
        if(TRIM($request->dataJwt[env('AUTH0_AUD')]->client) != 'MUT001'){
         $data = $this->_dashboard->detailsDash($request, $request->dataJwt);
        }

        //$data = $this->_dashboard->detailsDash($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function textMining(Request $request)
    {
        $data = $this->_dashboard->textMining($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function matriz(Request $request)
    {
        $data = $this->_dashboard->matriz($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
     public function  filters(Request $request)
    {
        $data = $this->_dashboard->filters($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function downloadExcel(Request $request)
    {  
        $startDate  = $request->get('startDate');
        $endDate    = $request->get('endDate');
        $survey     = $request->get('survey');
    
        if(!isset($startDate) && !isset($endDate) && !isset($survey)){return $this->generic('Not datas filters', Response::HTTP_UNPROCESSABLE_ENTITY);}
        $resp = $this->_dashboard->downloadExcel($request, $request->dataJwt);
        return response($resp, 200)
                    ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->header('Content-Disposition', 'attachment;filename="Periodo de Datos del '.$startDate.' al '.$endDate.' - '.$survey.'.xlsx"')
                    ->header('Access-Control-Allow-Origin'      , '*')
                    ->header('Access-Control-Allow-Methods'     , 'POST, GET, OPTIONS, PUT')
                    ->header('Access-Control-Allow-Credentials' , 'true')
                    ->header('Access-Control-Max-Age'           , '86400')
                    ->header('Access-Control-Allow-Headers'     , 'Content-Type, Authorization, X-Requested-With');
    }

    public function downloadExcelLogin(Request $request)
    {  
        $startDate  = $request->get('startDate');
        $endDate    = $request->get('endDate');
        
        if(!isset($startDate) && !isset($endDate)){return $this->generic('Not datas filters', Response::HTTP_UNPROCESSABLE_ENTITY);}

        $resp = $this->_dashboard->downloadExcelLogin($request);

        return response($resp, 200)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', 'attachment;filename=Data.csv')
                    ->header('Access-Control-Allow-Origin'      , '*')
                    ->header('Access-Control-Allow-Methods'     , 'POST, GET, OPTIONS, PUT')
                    ->header('Access-Control-Allow-Credentials' , 'true')
                    ->header('Access-Control-Max-Age'           , '86400')
                    ->header('Access-Control-Allow-Headers'     , 'Content-Type, Authorization, X-Requested-With');
    }
}