<?php
namespace Morbihanet\Modeler;

class MiddlewareCsrf
{
    /**
     * @var array|\ArrayAccess
     */
    private $session;

    /**
     * @var string
     */
    private $sessionKey;

    /**
     * @var string
     */
    private $formKey;

    /**
     * @var int
     */
    private $limit;

    protected array $except = [];

    /**
     * @param array|\ArrayAccess $session
     * @param int $limit
     * @param string $sessionKey
     * @param string $formKey
     */
    public function __construct(
        &$session,
        $limit = 50,
        $sessionKey = 'csrf.tokens',
        $formKey = '_csrf'
    ) {
        $this->session      = &$session;
        $this->sessionKey   = $sessionKey;
        $this->formKey      = $formKey;
        $this->limit        = $limit;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param callable $next
     * @return mixed
     */
    public function handle($request, callable $next)
    {
        $uri = $request->getRequestUri();

        $continue = true;

        foreach ($this->except as $except) {
            if (fnmatch($except, $uri)) {
                $continue = false;

                break;
            }
        }

        if (true === $continue && in_array($request->getMethod(), ['PUT', 'POST', 'DELETE'], true)) {
            $params = $request->all();

            if (!array_key_exists($this->formKey, $params)) {
                exception('NoCsrf', 'no csrf');
            }

            if (false === $this->check($params[$this->formKey], $this->session[$this->sessionKey] ?? [])) {
                exception('InvalidCsrf', 'invalid csrf');
            }

            $this->removeToken($params[$this->formKey]);
        }

        return $next($request);
    }

    /**
     * @param $csrf
     * @param $tokens
     *
     * @return bool
     */
    private function check($csrf, $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($csrf === $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function generateToken()
    {
        $token      = bin2hex(random_bytes(16));
        $tokens     = $this->session[$this->sessionKey] ?? [];
        $tokens[]   = $token;

        $this->session[$this->sessionKey] = $this->limitTokens($tokens);

        return $token;
    }

    /**
     * @param string $token
     */
    private function removeToken($token)
    {
        $this->session[$this->sessionKey] = array_filter(
            $this->session[$this->sessionKey] ?? [],
            function ($t) use ($token) {
                return $token !== $t;
            }
        );
    }

    /**
     * @return string
     */
    public function getSessionKey()
    {
        return $this->sessionKey;
    }

    /**
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey;
    }

    /**
     * @param array $tokens
     *
     * @return array
     */
    private function limitTokens(array $tokens)
    {
        if (count($tokens) > $this->limit) {
            array_shift($tokens);
        }

        return $tokens;
    }

    /**
     * @param int $limit
     * @return MiddlewareCsrf
     */
    public function setLimit(int $limit): MiddlewareCsrf
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @param string $sessionKey
     * @return MiddlewareCsrf
     */
    public function setSessionKey(string $sessionKey): MiddlewareCsrf
    {
        $this->sessionKey = $sessionKey;

        return $this;
    }

    /**
     * @param string $formKey
     * @return MiddlewareCsrf
     */
    public function setFormKey(string $formKey): MiddlewareCsrf
    {
        $this->formKey = $formKey;

        return $this;
    }
}
