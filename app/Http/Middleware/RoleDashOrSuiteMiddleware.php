<?php

namespace App\Http\Middleware;

use Closure;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Helpers\JWKFetcher;
use Auth0\SDK\Helpers\Tokens\AsymmetricVerifier;
use Auth0\SDK\Helpers\Tokens\TokenVerifier;

use Illuminate\Http\Response;

use App\Traits\ApiResponser;

class RoleDashOrSuiteMiddleware
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
        return $this->validatePermiso($next, $request);
    }

    public function validatePermiso($next, $request)
    {
        try {
            if($request->dataJwt[env('AUTH0_AUD')]->client == 'BAN001'){
                if(in_array('Executive',$request->dataJwt[env('AUTH0_AUD')]->roles)){
                    return  $this->generic(['datas'=>'Unauthorized'], Response::HTTP_UNAUTHORIZED);
                }
            } 
            return $next($request);
        }
        catch(InvalidTokenException $e) {
            //echo $e->getMessage();
            return $this->generic([$e->getMessage()], Response::HTTP_UNAUTHORIZED);
            return response()->json('No token provided', 403);
            //throw $e;
        };
    }
}