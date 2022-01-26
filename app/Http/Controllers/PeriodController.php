<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use App\PeriodCompare;

class PeriodController extends Controller
{
    use ApiResponser;
    private $_periodCompare;
    
    public function __construct()
    {
        //
        $this->_periodCompare = new PeriodCompare;
    }
    public function getPeriod(Request $request)
    {
        $data = $this->_periodCompare->GetPeriod($request, $request->dataJwt);
        return $this->generic($data['datas'], $data['status']);
    }
 
}