<?php

namespace App\Http\Middleware;

use Closure;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\SDK\Helpers\JWKFetcher;
use Auth0\SDK\Helpers\Tokens\AsymmetricVerifier;
use Auth0\SDK\Helpers\Tokens\TokenVerifier;

use Illuminate\Http\Response;

use App\Traits\ApiResponser;

class Auth0Middleware
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
        return $this->validateToken($token, $next, $request);
    }

    public function validateToken($token, $next, $request)
    {
        //echo $token;
        try {
            $jwksUri            = env('AUTH0_DOMAIN') . '.well-known/jwks.json';
            //echo $jwksUri
            $jwksFetcher        = new JWKFetcher(null, [ 'base_uri' => $jwksUri ]);
            $signatureVerifier  = new AsymmetricVerifier($jwksFetcher);
            $tokenVerifier      = new TokenVerifier(env('AUTH0_DOMAIN'), env('AUTH0_AUD'), $signatureVerifier);
            $dataJwt = $tokenVerifier->verify($token);
            if(isset($dataJwt)) {
                $request->dataJwt = $dataJwt;
                return $next($request);
            }
            if(!isset($dataJwt)){
                return $this->generic(["TOKEN INVALIDO"], Response::HTTP_FORBIDDEN);
            }
            //$request->name = 'Mr. Perfectionist';
            //echo 'INICIAMOS';
            //print_r($decoded);
        }
        catch(InvalidTokenException $e) {
            return $this->generic(["UNAUTHORIZED"], Response::HTTP_UNAUTHORIZED);
            return response()->json('No token provided', 401);
            //throw $e;
        };
    }
}