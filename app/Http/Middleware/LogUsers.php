<?php

namespace App\Http\Middleware;

use Closure;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Helpers\JWKFetcher;
use Auth0\SDK\Helpers\Tokens\AsymmetricVerifier;
use Auth0\SDK\Helpers\Tokens\TokenVerifier;
use DB;
//use Illuminate\Support\Facades\DB;

use Illuminate\Http\Response;

use App\Traits\ApiResponser;

class LogUsers
{
    use ApiResponser;
    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $app)
    {
        $token = $request->bearerToken();
        //echo $token;
        if(!$token) {
            return $this->generic(["UNAUTHORIZED"], Response::HTTP_UNAUTHORIZED);
        }
        return $this->usersList($next, $request, $app);
    }

    public function usersList($next, $request, $app)
    {
        try {
            if(!in_array('Developer',$request->dataJwt[env('AUTH0_AUD')]->roles)){
                DB::table('customerscoops_general_info.log_users')->insert(['company' => $request->dataJwt[env('AUTH0_AUD')]->client,
                                                'app' => $app,
                                                'rol' => json_encode($request->dataJwt[env('AUTH0_AUD')]->roles),
                                                'email' => $request->dataJwt[env('AUTH0_AUD')]->email,
                                                'date' => date("Y-m-d"),
                                                'time' => date("H:i")]);
            }
        return $next($request);
        }
        catch(InvalidTokenException $e) {
            return $this->generic([$e->getMessage()], Response::HTTP_UNAUTHORIZED);
            return response()->json('No token provided', 401);
        };
    }
}