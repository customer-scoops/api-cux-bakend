<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Traits\ApiResponser;
use App\Indicator;

class IndicatorController extends Controller
{
    use ApiResponser;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private $indicator;
    
    public function __construct()
    {
        //
        $this->indicator = new Indicator;
    }
    public function getAll(Request $request)
    {
        $data = $this->indicator->resumenIndicator($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function getDataCards(Request $request)
    {
        $data = $this->indicator->indicatorPrincipal($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function getSurvey(Request $request)
    {
        $data = $this->indicator->getSurvey($request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function saveRegister(Request $request)
    {
        $data = $this->indicator->saveUpdate($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    //
}
