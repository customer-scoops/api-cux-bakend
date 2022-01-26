<?php namespace App;

use Validator;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;


class Generic
{
    public function configInitial($client){
        if($client == "VID001" || $client == "BAN001"){
            return [
                'bd' => 'customer_banmedica',
                'minNps'=> 9,
                'maxNps'=> 10,
                'filters' =>''
            ];
        }
    }
}