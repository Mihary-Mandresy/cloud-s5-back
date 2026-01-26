protected $routeMiddleware = [
    'auth.jwt' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
    'role' => \App\Http\Middleware\CheckRole::class,
    'token.expiration' => \App\Http\Middleware\CheckTokenExpiration::class,
    \App\Http\Middleware\FixSessionLookup::class,
];