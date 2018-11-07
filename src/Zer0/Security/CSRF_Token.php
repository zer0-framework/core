<?php

namespace Zer0\Security;

use Zer0\Config\Interfaces\ConfigInterface;
use Zer0\HTTP\HTTP;
use Zer0\Helpers\Str;

/**
 * Class for generating and validating CSRF tokens.
 * https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)
 * https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)_Prevention_Cheat_Sheet
 *
 * CSRF was most likely used by attackers in Jun 2014 to change email addresses.
 */
class CSRF_Token
{
    /**
     * @var string
     */
    protected $cookieName;

    /**
     * @var int
     */
    protected $bytes;

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var HTTP
     */
    protected $http;

    /**
     * CSRF_Token constructor.
     * @param ConfigInterface $config
     * @param HTTP $http
     */
    public function __construct(ConfigInterface $config, HTTP $http)
    {
        $this->http = $http;
        $this->bytes = $config->bytes ?? 8;
        $this->cookieName = $config->bytes ?? 'csrf_cookie';
        $this->fieldName = $config->fieldName ?? 'csrf_token';
        $this->secret = $config->secret;
    }

    /**
     * Check a supplied token against the value stored in session.
     *
     * @param null|string $token
     * @return bool
     */
    public function validate(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if ($token === null) {
                $token = $_POST[$this->fieldName] ?? null;
                unset($_POST[$this->fieldName]);
            }
        }
        return is_string($token) && hash_equals($this->get(), $token);
    }

    /**
     * Get token from session.
     *
     * @return string
     */
    public function get(): string
    {
        $cookie =& $_COOKIE[$this->cookieName];
        if ($cookie === null || !is_string($cookie)) {
            $this->reset();
        }
        return Str::base64UrlEncode(hash_hmac('sha3-256', $cookie, $this->secret, true));
    }

    /**
     *
     */
    public function reset()
    {
        $_COOKIE[$this->cookieName] = Str::base64UrlEncode(random_bytes($this->bytes));
        $this->http->setcookie(
            $this->cookieName,
            $_COOKIE[$this->cookieName],
            0,
            '/',
            '',
            null,
            true
        );
    }

    /**
     * Get html form field for csrf token.
     *
     * @return string
     */
    public function tokenField(): string
    {
        return '<input type="hidden" name="' . htmlspecialchars($this->fieldName, ENT_QUOTES)
            . '" value="' . htmlspecialchars($this->get(), ENT_QUOTES) . '">';
    }
}
