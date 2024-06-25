<?php namespace CodeIgniter\HTTP;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use App\Config\App;
use Locale;

/**
 * Class IncomingRequest
 *
 * Represents an incoming, server-side HTTP request.
 *
 * Per the HTTP specification, this interface includes properties for
 * each of the following:
 *
 * - Protocol version
 * - HTTP method
 * - URI
 * - Headers
 * - Message body
 *
 * Additionally, it encapsulates all data as it has arrived to the
 * application from the CGI and/or PHP environment, including:
 *
 * - The values represented in $_SERVER.
 * - Any cookies provided (generally via $_COOKIE)
 * - Query string arguments (generally via $_GET, or as parsed via parse_str())
 * - Upload files, if any (as represented by $_FILES)
 * - Deserialized body parameters (generally from $_POST)
 *
 * @package CodeIgniter\HTTPLite
 */
class IncomingRequest extends Request
{

    /**
     * Parsed input stream data
     *
     * Parsed from php://input at runtime
     *
     * @var
     */
    protected $inputStream;

    /**
     * Enable CSRF flag
     *
     * Enables a CSRF cookie token to be set.
     * Set automatically based on config setting.
     *
     * @var bool
     */
    protected $enableCSRF = false;

    /**
     * A \CodeIgniter\HTTPLite\URI instance.
     *
     * @var URI
     */
    public $uri;

    /**
     * Set a cookie name prefix if you need to avoid collisions
     *
     * @var string
     */
    protected $cookiePrefix = '';

    /**
     * Set to .your-domain.com for site-wide cookies
     *
     * @var string
     */
    protected $cookieDomain = '';

    /**
     * Typically will be a forward slash
     *
     * @var string
     */
    protected $cookiePath = '/';

    /**
     * Cookie will only be set if a secure HTTPS connection exists.
     *
     * @var bool
     */
    protected $cookieSecure = false;

    /**
     * Cookie will only be accessible via HTTP(S) (no javascript)
     *
     * @var bool
     */
    protected $cookieHTTPOnly = false;

    /**
     * Negotiator
     *
     * @var Negotiate|null
     */
    protected $negotiator;

    /**
     * The default Locale this request
     * should operate under.
     *
     * @var string
     */
    protected $defaultLocale;

    /**
     * The current locale of the application.
     * Default value is set in app/Config/App.php
     *
     * @var string
     */
    protected $locale;

    /**
     * Stores the valid locale codes.
     *
     * @var array
     */
    protected $validLocales = [];

    //--------------------------------------------------------------------

    public function __construct($config, $uri = null, $body = 'php://input')
    {
        $config ??= config(App::class);

        if (
            $body === 'php://input'
            // php://input is not available with enctype="multipart/form-data".
            // See https://www.php.net/manual/en/wrappers.php.php#wrappers.php.input
            && ! str_contains($this->getHeaderLine('Content-Type'), 'multipart/form-data')
            && (int) $this->getHeaderLine('Content-Length') <= $this->getPostMaxSize()
        ) {
            // Get our body from php://input
            $body = file_get_contents('php://input');
        }

        // If file_get_contents() returns false or empty string, set null.
        if ($body === false || $body === '') {
            $body = null;
        }

        $this->body = $body;

        $this->validLocales = $config->supportedLocales;

        parent::__construct($config, $uri);

        $this->populateHeaders();

        $this->uri = $uri;

        $this->cookiePrefix   = config_item('cookie_prefix');
        $this->cookieDomain   = config_item('cookie_domain');
        $this->cookiePath     = config_item('cookie_path');
        $this->cookieSecure   = config_item('cookie_secure');
        $this->cookieHTTPOnly = config_item('cookie_httponly');

        $this->detectLocale($config);
    }

    private function getPostMaxSize(): int
    {
        $postMaxSize = ini_get('post_max_size');

        return match (strtoupper(substr($postMaxSize, -1))) {
            'G'     => (int) str_replace('G', '', $postMaxSize) * 1024 ** 3,
            'M'     => (int) str_replace('M', '', $postMaxSize) * 1024 ** 2,
            'K'     => (int) str_replace('K', '', $postMaxSize) * 1024,
            default => (int) $postMaxSize,
        };
    }

    /**
     * Handles setting up the locale, perhaps auto-detecting through
     * content negotiation.
     *
     * @param App $config
     *
     * @return void
     */
    public function detectLocale($config)
    {
        $this->locale = $this->defaultLocale = $config->defaultLocale;

        if (! $config->negotiateLocale) {
            return;
        }

        $this->setLocale($this->negotiate('language', $config->supportedLocales));
    }

    //--------------------------------------------------------------------

    /**
     * Determines if this request was made from the command line (CLI).
     *
     * @return bool
     */
    public function isCLI(): bool
    {
        return (PHP_SAPI === 'cli' OR defined('STDIN'));
    }

    //--------------------------------------------------------------------

    /**
     * Test to see if a request contains the HTTP_X_REQUESTED_WITH header.
     *
     * @return bool
     */
    public function isAJAX(): bool
    {
        return ( ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    //--------------------------------------------------------------------

    /**
     * Fetch an item from GET data.
     *
     * @param null $index  Index for item to fetch from $_GET.
     * @param null $filter A filter name to apply.
     *
     * @return mixed
     */
    public function getGet($index = null, $filter = null)
    {
        return $this->fetchGlobal(INPUT_GET, $index, $filter);
    }

    //--------------------------------------------------------------------

    /**
     * Fetch an item from POST.
     *
     * @param null $index  Index for item to fetch from $_POST.
     * @param null $filter A filter name to apply
     *
     * @return mixed
     */
    public function getPost($index = null, $filter = null)
    {
        return $this->fetchGlobal(INPUT_POST, $index, $filter);
    }

    //--------------------------------------------------------------------

    /**
     * Fetch an item from POST data with fallback to GET.
     *
     * @param null $index  Index for item to fetch from $_POST or $_GET
     * @param null $filter A filter name to apply
     *
     * @return mixed
     */
    public function getPostGet($index = null, $filter = null)
    {
        // Use $_POST directly here, since filter_has_var only
        // checks the initial POST data, not anything that might
        // have been added since.
        return isset($_POST[$index])
            ? $this->getPost($index, $filter)
            : $this->getGet($index, $filter);
    }

    //--------------------------------------------------------------------

    /**
     * Fetch an item from GET data with fallback to POST.
     *
     * @param null $index  Index for item to be fetched from $_GET or $_POST
     * @param null $filter A filter name to apply
     *
     * @return mixed
     */
    public function getGetPost($index = null, $filter = null)
    {
        // Use $_GET directly here, since filter_has_var only
        // checks the initial GET data, not anything that might
        // have been added since.
        return isset($_GET[$index])
            ? $this->getGet($index, $filter)
            : $this->getPost($index, $filter);
    }

    //--------------------------------------------------------------------

    /**
     * Fetch an item from the COOKIE array.
     *
     * @param null $index  Index for item to be fetched from $_COOKIE
     * @param null $filter A filter name to be applied
     *
     * @return mixed
     */
    public function getCookie($index = null, $filter = null)
    {
        return $this->fetchGlobal(INPUT_COOKIE, $index, $filter);
    }

    //--------------------------------------------------------------------

    /**
     * Fetch the user agent string
     *
     * @param null $filter
     */
    public function getUserAgent($filter = null)
    {
        return $this->fetchGlobal(INPUT_SERVER, 'HTTP_USER_AGENT', $filter);
    }

    //--------------------------------------------------------------------

    /**
     * Set a cookie
     *
     * Accepts an arbitrary number of parameters (up to 7) or an associateive
     * array in the first parameter containing all the values.
     *
     * @param            $name      Cookie name or array containing parameters
     * @param string     $value     Cookie value
     * @param string     $expire    Cookie expiration time in seconds
     * @param string     $domain    Cookie domain (e.g.: '.yourdomain.com')
     * @param string     $path      Cookie path (default: '/')
     * @param string     $prefix    Cookie name prefix
     * @param bool|false $secure    Whether to only transfer cookies via SSL
     * @param bool|false $httponly  Whether only make the cookie accessible via HTTP (no javascript)
     */
    public function setCookie(
        $name,
        $value = '',
        $expire = '',
        $domain = '',
        $path = '/',
        $prefix = '',
        $secure = false,
        $httponly = false
    )
    {
        if (is_array($name))
        {
            // always leave 'name' in last place, as the loop will break otherwise, due to $$item
            foreach (['value', 'expire', 'domain', 'path', 'prefix', 'secure', 'httponly', 'name'] as $item)
            {
                if (isset($name[$item]))
                {
                    $item = $name[$item];
                }
            }
        }

        if ($prefix === '' && $this->cookiePrefix !== '')
        {
            $prefix = $this->cookiePrefix;
        }

        if ($domain == '' && $this->cookieDomain != '')
        {
            $domain = $this->cookieDomain;
        }

        if ($path === '/' && $this->cookiePath !== '/')
        {
            $path = $this->cookiePath;
        }

        if ($secure === false && $this->cookieSecure === true)
        {
            $secure = $this->cookieSecure;
        }

        if ($httponly === false && $this->cookieHTTPOnly !== false)
        {
            $httponly = $this->cookieHTTPOnly;
        }

        if ( ! is_numeric($expire))
        {
            $expire = time() - 86500;
        }
        else
        {
            $expire = ($expire > 0) ? time() + $expire : 0;
        }

        setcookie($prefix.$name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    //--------------------------------------------------------------------

    /**
     * Attempts to detect if the current connection is secure through
     * a few different methods.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443')
        {
            return true;
        }
        elseif (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == '443')
        {
            return true;
        }
        elseif (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
        {
            return true;
        }
        elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
        {
            return true;
        }

        return false;
    }

    /**
     * Sets the locale string for this request.
     *
     * @return IncomingRequest
     */
    public function setLocale(string $locale)
    {
        // If it's not a valid locale, set it
        // to the default locale for the site.
        if (! in_array($locale, $this->validLocales, true)) {
            $locale = $this->defaultLocale;
        }

        $this->locale = $locale;
        Locale::setDefault($locale);

        return $this;
    }

    /**
     * Set the valid locales.
     *
     * @return $this
     */
    public function setValidLocales(array $locales)
    {
        $this->validLocales = $locales;

        return $this;
    }

    /**
     * Gets the current locale, with a fallback to the default
     * locale if none is set.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Returns the default locale as set in app/Config/App.php
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    //--------------------------------------------------------------------

    /**
     * Sets up our URI object based on the information we have. This is
     * either provided by the user in the baseURL config setting, or
     * determined from the environment as needed.
     *
     * @param $protocol
     * @param $baseURL
     */
    protected function detectURI($protocol, $baseURL)
    {
        $this->uri->setPath($this->detectPath($protocol));

        // Based on our baseURL provided by the developer (if set)
        // set our current domain name, scheme
        if ( ! empty($baseURL))
        {
            $this->uri->setScheme(parse_url($baseURL, PHP_URL_SCHEME));
            $this->uri->setHost(parse_url($baseURL, PHP_URL_HOST));
            $this->uri->setPort(parse_url($baseURL, PHP_URL_PORT));
        }
        else
        {
            $this->isSecure() ? $this->uri->setScheme('https') : $this->uri->setScheme('http');

            // While both SERVER_NAME and HTTP_HOST are open to security issues,
            // if we have to choose, we will go with the server-controller version first.
            ! empty($_SERVER['SERVER_NAME'])
                ? (isset($_SERVER['SERVER_NAME']) ? $this->uri->setHost($_SERVER['SERVER_NAME']) : null)
                : (isset($_SERVER['HTTP_HOST']) ? $this->uri->setHost($_SERVER['HTTP_HOST']) : null);

            if ( ! empty($_SERVER['SERVER_PORT']))
            {
                $this->uri->setPort($_SERVER['SERVER_PORT']);
            }
        }
    }

    //--------------------------------------------------------------------

    /**
     * Based on the URIProtocol config setting, will attempt to
     * detect the path portion of the current URI.
     *
     * @param $protocol
     *
     * @return string|string
     */
    public function detectPath($protocol)
    {
        if (empty($protocol))
        {
            $protocol = 'REQUEST_URI';
        }

        switch ($protocol)
        {
            case 'REQUEST_URI':
                $path = $this->parseRequestURI();
                break;
            case 'QUERY_STRING':
                $path = $this->parseQueryString();
                break;
            case 'PATH_INFO':
            default:
                $path = isset($_SERVER[$protocol])
                    ? $_SERVER[$protocol]
                    : $this->parseRequestURI();
                break;
        }

        return $path;
    }

    //--------------------------------------------------------------------

    /**
     * Will parse the REQUEST_URI and automatically detect the URI from it,
     * fixing the query string if necessary.
     *
     * @return string The URI it found.
     */
    protected function parseRequestURI(): string
    {
        if ( ! isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']))
        {
            return '';
        }

        // parse_url() returns false if no host is present, but the path or query string
        // contains a colon followed by a number
        $parts = parse_url('http://dummy'.$_SERVER['REQUEST_URI']);
        $query = isset($parts['query']) ? $parts['query'] : '';
        $uri   = isset($parts['path']) ? $parts['path'] : '';

        if (isset($_SERVER['SCRIPT_NAME'][0]))
        {
            if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0)
            {
                $uri = (string)substr($uri, strlen($_SERVER['SCRIPT_NAME']));
            }
            elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0)
            {
                $uri = (string)substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
            }
        }

        // This section ensures that even on servers that require the URI to be in the query string (Nginx) a correct
        // URI is found, and also fixes the QUERY_STRING server var and $_GET array.
        if (trim($uri, '/') === '' && strncmp($query, '/', 1) === 0)
        {
            $query                   = explode('?', $query, 2);
            $uri                     = $query[0];
            $_SERVER['QUERY_STRING'] = isset($query[1]) ? $query[1] : '';
        }
        else
        {
            $_SERVER['QUERY_STRING'] = $query;
        }

        parse_str($_SERVER['QUERY_STRING'], $_GET);

        if ($uri === '/' OR $uri === '')
        {
            return '/';
        }

        return $this->removeRelativeDirectory($uri);
    }

    //--------------------------------------------------------------------

    /**
     * Parse QUERY_STRING
     *
     * Will parse QUERY_STRING and automatically detect the URI from it.
     *
     * @return    string
     */
    protected function parseQueryString(): string
    {
        $uri = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : @getenv('QUERY_STRING');

        if (trim($uri, '/') === '')
        {
            return '';
        }
        elseif (strncmp($uri, '/', 1) === 0)
        {
            $uri                     = explode('?', $uri, 2);
            $_SERVER['QUERY_STRING'] = isset($uri[1]) ? $uri[1] : '';
            $uri                     = $uri[0];
        }

        parse_str($_SERVER['QUERY_STRING'], $_GET);

        return $this->removeRelativeDirectory($uri);
    }

    /**
     * Provides a convenient way to work with the Negotiate class
     * for content negotiation.
     */
    public function negotiate(string $type, array $supported, bool $strictMatch = false): string
    {
        if ($this->negotiator === null) {
            $this->negotiator = Services::negotiator($this, true);
        }

        return match (strtolower($type)) {
            'media'    => $this->negotiator->media($supported, $strictMatch),
            'charset'  => $this->negotiator->charset($supported),
            'encoding' => $this->negotiator->encoding($supported),
            'language' => $this->negotiator->language($supported),
            default    => throw HTTPException::forInvalidNegotiationType($type),
        };
    }

    /**
     * Remove relative directory (../) and multi slashes (///)
     *
     * Do some final cleaning of the URI and return it, currently only used in self::_parse_request_uri()
     *
     * @param    string $url
     *
     * @return    string
     */
    protected function removeRelativeDirectory($uri)
    {
        $uris = [];
        $tok  = strtok($uri, '/');
        while ($tok !== false)
        {
            if (( ! empty($tok) OR $tok === '0') && $tok !== '..')
            {
                $uris[] = $tok;
            }
            $tok = strtok('/');
        }

        return implode('/', $uris);
    }

    // --------------------------------------------------------------------
}