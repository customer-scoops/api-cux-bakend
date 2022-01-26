<?php
namespace App\Traits;
use Illuminate\Http\Response;

trait ApiResponser
{
    public function generic($data, $code)
    {
        return  response()->json(["data" => $data], $code)->header('Content-Type', 'application/json');
    }
}