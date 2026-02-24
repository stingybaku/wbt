<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request;

class TrustProxies extends Middleware
{
    /**
     * Trust all proxies (Railway/ingress) so forwarded proto/host are honored.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * Headers used to detect original request details behind proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_AWS_ELB;
}
