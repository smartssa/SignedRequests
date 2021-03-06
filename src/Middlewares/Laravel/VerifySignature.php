<?php

namespace SoapBox\SignedRequests\Middlewares\Laravel;

use Closure;
use Illuminate\Http\Request;
use SoapBox\SignedRequests\Requests\Verifier;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Configurations;
use SoapBox\SignedRequests\Exceptions\ExpiredRequestException;
use SoapBox\SignedRequests\Exceptions\InvalidSignatureException;
use SoapBox\SignedRequests\Exceptions\InvalidConfigurationException;

class VerifySignature
{
    /**
     * An instance of the configurations repository.
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $configurations;

    /**
     * An instance of the cache repository.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Expect an instance of the configurations repository so we can lookup
     * where to find our signature, algorithm, and key from.
     *
     * @param \Illuminate\Contracts\Config\Repository $configurations
     *        An instance of the Illuminate configurations repository to lookup
     *        configurations with.
     * @param \Illuminate\Contracts\Cache\Repository $cache
     *        An instance of the Illuminate cache repository for preventing
     *        replay attacks.
     */
    public function __construct(Configurations $configurations, Cache $cache)
    {
        $this->configurations = $configurations;
        $this->cache = $cache;
    }

    /**
     * Applies the middleware to the request before moving onto the next request
     * handler.
     *
     * @throws \SoapBox\SignedRequests\Exceptions\InvalidSignatureException
     *         Thrown when the signature of the request is not valid.
     * @throws \SoapBox\SignedRequests\Exceptions\ExpiredRequestException
     *         Thrown if request replays are disabled and either the request
     *         timestamp is outside the window of tolerance, or the request has
     *         previously been served.
     * @throws \SoapBox\SignedRequests\Exceptions\InvalidConfigurationException
     *         Thrown if the request key is not defined in the config
     *
     * @param  \Illuminate\Http\Request $request
     *         An instance of the request.
     * @param  \Closure $next
     *         A callback function of where to go next.
     * @param  mixed $requestKey
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $requestKey = 'default')
    {
        if (!array_key_exists($requestKey, $this->configurations->get('signed-requests'))) {
            throw new InvalidConfigurationException();
        }

        $signed = new Verifier($request);

        $key = sprintf(
            '%s.%s',
            $this->configurations->get("signed-requests.$requestKey.cache-prefix"),
            $signed->getId()
        );

        $tolerance = $this->configurations->get("signed-requests.$requestKey.request-replay.tolerance");

        if (true !== $this->configurations->get("signed-requests.$requestKey.request-replay.allow")) {
            $isExpired = $signed->isExpired($tolerance);

            if ($isExpired || $this->cache->has($key)) {
                throw new ExpiredRequestException();
            }
        }

        $signed
            ->setSignatureHeader($this->configurations->get("signed-requests.$requestKey.headers.signature"))
            ->setAlgorithmHeader($this->configurations->get("signed-requests.$requestKey.headers.algorithm"));

        if (!$signed->isValid($this->configurations->get("signed-requests.$requestKey.key"))) {
            throw new InvalidSignatureException();
        }

        $this->cache->put($key, $key, $tolerance / 60);

        return $next($request);
    }
}
