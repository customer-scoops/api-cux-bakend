<?php

namespace App\Http\Middleware;

use Closure;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Helpers\JWKFetcher;
use Auth0\SDK\Helpers\Tokens\AsymmetricVerifier;
use Auth0\SDK\Helpers\Tokens\TokenVerifier;

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
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        //echo $token;
        if(!$token) {
            return $this->generic(["UNAUTHORIZED"], Response::HTTP_UNAUTHORIZED);
        }
        return $this->usersList($next, $request);
    }

    public function usersList($next, $request)
    {

        try {
            if($request->dataJwt[env('AUTH0_AUD')]->client == 'BAN001'){
                if(!in_array('Developer',$request->dataJwt[env('AUTH0_AUD')]->roles)){
                    $user = [
                        "company"   => $request->dataJwt[env('AUTH0_AUD')]->client,
                        "rol"       => $request->dataJwt[env('AUTH0_AUD')]->roles,
                        "userEmail" => $request->dataJwt[env('AUTH0_AUD')]->email,
                        "date"      => 
                    ]
                }
            } 
            return $next($request);
        }
        catch(InvalidTokenException $e) {
            //echo $e->getMessage();
            return $this->generic([$e->getMessage()], Response::HTTP_UNAUTHORIZED);
            return response()->json('No token provided', 401);
            //throw $e;
        };
    }
}