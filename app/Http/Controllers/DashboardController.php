<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use App\Dashboard;

class DashboardController extends Controller
{
    use ApiResponser;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private $_dashboard;
    
    public function __construct()
    {
        $this->_dashboard = new Dashboard;
    }
    public function index(Request $request){
        $data = $this->_dashboard->generalInfo($request, $request->dataJwt);
        //print_r($data);
        return $this->generic($data['datas'], $data['status']);
    }
    
    public function indexBackCards(Request $request)
    {
        $data = $this->_dashboard->backCards($request, $request->dataJwt);
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
        $data = $this->_dashboard->detailsDash($request, $request->dataJwt);
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
        //print_r($data);
        return $this->generic($data['datas'], $data['status']);
    }
    
    
   
}
