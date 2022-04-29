<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Traits\ApiResponser;
use App\Suite;
use App\SuiteBanmedica;

class SuiteController extends Controller
{
    use ApiResponser;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private $_suite;
    public function __construct(Request $request)
    {
        $this->_suite = new Suite($request->dataJwt);
    }
    public function getAll(Request $request)
    {
        $data = $this->_suite->resumenIndicator($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function getDataCards(Request $request)
    {
        $data = $this->_suite->indicatorPrincipal($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function getSurvey(Request $request)
    {
        $data = $this->_suite->getSurvey($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function saveRegister(Request $request)
    {
        $data = $this->_suite->saveUpdate($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    public function updateRegisterBanmedica(Request $request)
    {
        $suiteBan = new SuiteBanmedica($request->dataJwt);
        $data = $suiteBan->saveUpdate($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
    //
}
