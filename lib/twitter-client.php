<?php
/**
 * Simple Twitter API 1.1 REST Client using cURL extension
 * @author Tim Whitlock, http://timwhitlock.info
 * @license MIT
 */

define('TWITTER_API_TIMEOUT', 5 );  

define('TWITTER_API_USERAGENT', 'PHP/'.PHP_VERSION.'; http://github.com/timwhitlock/php-twitter-api' );  

define('TWITTER_API_BASE', 'https://api.twitter.com/1.1' );

define('TWITTER_OAUTH_REQUEST_TOKEN_URL', 'https://twitter.com/oauth/request_token');

define('TWITTER_OAUTH_AUTHORIZE_URL', 'https://twitter.com/oauth/authorize');

define('TWITTER_OAUTH_AUTHENTICATE_URL', 'https://twitter.com/oauth/authenticate');

define('TWITTER_OAUTH_ACCESS_TOKEN_URL', 'https://twitter.com/oauth/access_token');
 



/**
 * Client for the Twitter REST API 1.1
 */
class TwitterApiClient {

    /**
     * Consumer key token for application
     * @var TwitterOAuthToken
     */
    private $Consumer;

    /**
     * Authenticated access token
     * @var TwitterOAuthToken
     */
    private $AccessToken;
    
    /**
     * Whether caching API GET requests
     * @var int
     */
    private $cache_ttl = null;
    
    /**
     * Namespace/prefix for cache keys
     * @var string
     */    
    private $cache_ns;     
    
    /**
     * Registry of last rate limit arrays by full api function call
     * @var array
     */    
    private $last_rate = array();    
    
    /**
     * Last api function called, e.g. "direct_messages/sent"
     * @var string
     */    
    private $last_call;     

    
    /**
     * @internal
     */
    public function __sleep(){
       return array('Consumer','AccessToken');
    }
    
    /**
     * Enable caching of subsequent API calls
     * @return TwitterApiClient
     */
    public function enable_cache( $ttl = 0, $namespace = 'twitter_api_' ){
       if( function_exists('apc_store') ){
          $this->cache_ttl = (int) $ttl;
          $this->cache_ns  = $namespace;
          return $this;
       }
       trigger_error( 'Cannot enable Twitter API cache without APC extension' );
       return $this->disable_cache();
    }
    
    /**
     * Disable caching for susequent API calls
     * @return TwitterApiClient
     */
    public function disable_cache(){
       $this->cache_ttl = null;
       $this->cache_ns  = null;
       return $this;
    }

    /**
     * Test whether the client has full authentication data.
     * Warning: does not validate credentials 
     * @return bool
     */
    public function has_auth(){
        return $this->AccessToken instanceof TwitterOAuthToken && $this->AccessToken->secret;
    }    
    
    /**
     * Unset all logged in credentials - useful in error situations
     * @return TwitterApiClient
     */
    public function deauthorize(){
        $this->AccessToken = null;
        return $this;
    }


    /**
     * Set currently logged in user's OAuth access token
     * @param string consumer api key
     * @param string consumer secret
     * @param string access token
     * @param string access token secret
     * @return TwitterApiClient
     */
    public function set_oauth( $consumer_key, $consumer_secret, $access_key = '', $access_secret = '' ){
        $this->deauthorize();
        $this->Consumer = new TwitterOAuthToken( $consumer_key, $consumer_secret );
        if( $access_key && $access_secret ){
            $this->AccessToken = new TwitterOAuthToken( $access_key, $access_secret );
        }
        return $this;
    }
    

    /**
     * Set consumer oauth token by object
     * @param TwitterOAuthToken
     * @return TwitterApiClient
     */
    public function set_oauth_consumer( TwitterOAuthToken $token ){
        $this->Consumer = $token;
        return $this;
    }
    

    /**
     * Set access oauth token by object
     * @param TwitterOAuthToken
     * @return TwitterApiClient
     */
    public function set_oauth_access( TwitterOAuthToken $token ){
        $this->AccessToken = $token;
        return $this;
    }

    
    
    /**
     * Contact Twitter for a request token, which will be exchanged for an access token later.
     * @param string callback URL or "oob" for desktop apps (out of bounds)
     * @return TwitterOAuthToken Request token
     */
    public function get_oauth_request_token( $oauth_callback = 'oob' ){
        $params = $this->oauth_exchange( TWITTER_OAUTH_REQUEST_TOKEN_URL, compact('oauth_callback') );
        return new TwitterOAuthToken( $params['oauth_token'], $params['oauth_token_secret'] );
    }



    /**
     * Exchange request token for an access token after authentication/authorization by user
     * @param string verifier passed back from Twitter or copied out of browser window
     * @return TwitterOAuthToken Access token
     */
    public function get_oauth_access_token( $oauth_verifier ){
        $params = $this->oauth_exchange( TWITTER_OAUTH_ACCESS_TOKEN_URL, compact('oauth_verifier') );
        $token = new TwitterOAuthToken( $params['oauth_token'], $params['oauth_token_secret'] );
        $token->user = array (
            'id' => $params['user_id'],
            'screen_name' => $params['screen_name'],
        );
        return $token;
    }    
    
    
    
    /**
     * Basic sanitation of api request arguments
     * @param array original params passed by client code
     * @return array sanitized params that we'll serialize
     */    
    private function sanitize_args( array $_args ){
        // transform some arguments and ensure strings
        // no further validation is performed
        $args = array();
        foreach( $_args as $key => $val ){
            if( is_string($val) ){
                $args[$key] = $val;
            }
            else if( true === $val ){
                $args[$key] = 'true';
            }
            else if( false === $val || null === $val ){
                 $args[$key] = 'false';
            }
            else if( ! is_scalar($val) ){
                throw new TwitterApiException( 'Invalid Twitter parameter ('.gettype($val).') '.$key, -1 );
            }
            else {
                $args[$key] = (string) $val;
            }
        }
        return $args;
    }    
    
    
    
    /**
     * Call API method over HTTP and return serialized data
     * @param string API method, e.g. "users/show"
     * @param array method arguments
     * @param string http request method
     * @return array unserialized data returned from Twitter
     * @throws TwitterApiException
     */
    public function call( $path, array $args = array(), $http_method = 'GET' ){
        $args = $this->sanitize_args( $args );
        // Fetch response from cache if possible / allowed / enabled
        if( $http_method === 'GET' && isset($this->cache_ttl) ){
           $cachekey = $this->cache_ns.$path.'_'.md5( serialize($args) );
           if( preg_match('/^(\d+)-/', $this->AccessToken->key, $reg ) ){
              $cachekey .= '_'.$reg[1];
           }
           $data = apc_fetch( $cachekey );
           if( is_array($data) ){
               return $data;
           }
        }
        $http = $this->rest_request( $path, $args, $http_method );
        // Deserialize response
        $status = $http['status'];
        $data = json_decode( $http['body'], true );
        // unserializable array assumed to be serious error
        if( ! is_array($data) ){
            $err = array( 
                'message' => $http['error'], 
                'code' => -1 
            );
            TwitterApiException::chuck( $err, $status );
        }
        // else could be well-formed error
        if( isset( $data['errors'] ) ) {
            while( $err = array_shift($data['errors']) ){
                $err['message'] = $err['message'];
                if( $data['errors'] ){
                    $message = sprintf('Twitter error #%d', $err['code'] ).' "'.$err['message'].'"';
                    trigger_error( $message, E_USER_WARNING );
                }
                else {
                    TwitterApiException::chuck( $err, $status );
                }
            }
        }
        if( isset($cachekey) ){
           apc_store( $cachekey, $data, $this->cache_ttl );
        }
        return $data;
    }



    /**
     * Call API method over HTTP and return raw response data without caching
     * @param string API method, e.g. "users/show"
     * @param array method arguments
     * @param string http request method
     * @return array structure from http_request
     * @throws TwitterApiException
     */
    public function raw( $path, array $args = array(), $http_method = 'GET' ){
        $args = $this->sanitize_args( $args );
        return $this->rest_request( $path, $args, $http_method );
    }



    /**
     * Perform an OAuth request - these differ somewhat from regular API calls
     * @internal
     */
    private function oauth_exchange( $endpoint, array $args ){
        // build a post request and authenticate via OAuth header
        $params = new TwitterOAuthParams( $args );
        $params->set_consumer( $this->Consumer );
        if( $this->AccessToken ){
            $params->set_token( $this->AccessToken );
        }
        $params->sign_hmac( 'POST', $endpoint );
        $conf = array (
            'method' => 'POST',
            'headers' => array( 'Authorization' => $params->oauth_header() ),
        );
        $http = self::http_request( $endpoint, $conf );
        $body = trim( $http['body'] );
        $stat = $http['status'];
        if( 200 !== $stat ){
            // Twitter might respond as XML, but with an HTML content type for some reason
            if( 0 === strpos($body, '<?') ){
                $xml = simplexml_load_string($body);
                $body = (string) $xml->error;
            }
            throw new TwitterApiException( $body, -1, $stat );
        }
        parse_str( $body, $params );
        if( ! is_array($params) || ! isset($params['oauth_token']) || ! isset($params['oauth_token_secret']) ){
            throw new TwitterApiException( 'Malformed response from Twitter', -1, $stat );
        }
        return $params;   
    }
    
    

    
    /**
     * Sign and execute REST API call
     * @return array
     */
    private function rest_request( $path, array $args, $http_method ){
        // all calls must be authenticated in API 1.1
        if( ! $this->has_auth() ){
            throw new TwitterApiException( 'Twitter client not authenticated', 0, 401 );
        }
        // prepare HTTP request config
        $conf = array (
            'method' => $http_method,
        );
        // build signed URL and request parameters
        $endpoint = TWITTER_API_BASE.'/'.$path.'.json';
        $params = new TwitterOAuthParams( $args );
        $params->set_consumer( $this->Consumer );
        $params->set_token( $this->AccessToken );
        $params->sign_hmac( $http_method, $endpoint );
        if( 'GET' === $http_method ){
            $endpoint .= '?'.$params->serialize();
        }
        else {
            $conf['body'] = $params->serialize();
        }
        $http = self::http_request( $endpoint, $conf );        
        // remember current rate limits for this endpoint
        $this->last_call = $path;
        if( isset($http['headers']['x-rate-limit-limit']) ) {
            $this->last_rate[$path] = array (
                'limit'     => (int) $http['headers']['x-rate-limit-limit'],
                'remaining' => (int) $http['headers']['x-rate-limit-remaining'],
                'reset'     => (int) $http['headers']['x-rate-limit-reset'],
            );
        }
        return $http;
    }    



    /**
     * Abstract HTTP call, currently just uses cURL extension
     * @return array e.g. { body: '', error: '', status: 200, headers: {} }
     */
    public static function http_request( $endpoint, array $conf ){
        $conf += array(
            'body' => '',
            'method'  => 'GET',
            'headers' => array(),
        );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $endpoint );
        curl_setopt( $ch, CURLOPT_TIMEOUT, TWITTER_API_TIMEOUT );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, TWITTER_API_TIMEOUT );
        curl_setopt( $ch, CURLOPT_USERAGENT, TWITTER_API_USERAGENT );
        curl_setopt( $ch, CURLOPT_HEADER, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        
        switch ( $conf['method'] ) {
        case 'GET':
            break;
        case 'POST':
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $conf['body'] );
            break;
        default:
            throw new TwitterApiException('Unsupported method '.$conf['method'] );    
        }
        
        foreach( $conf['headers'] as $key => $val ){
            $headers[] = $key.': '.$val;
        }
        if( isset($headers) ) {
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        }
        
        // execute and parse response
        $response = curl_exec( $ch );
        if ( 60 === curl_errno($ch) ) { // CURLE_SSL_CACERT
            curl_setopt( $ch, CURLOPT_CAINFO, __DIR__.'/ca-chain-bundle.crt');
            $response = curl_exec($ch);
        }
        $status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $headers = array();
        $body = '';
        if( $response && $status ){
            list( $header, $body ) = preg_split('/\r\n\r\n/', $response, 2 ); 
            if( preg_match_all('/^(Content[\w\-]+|X-Rate[^:]+):\s*(.+)/mi', $header, $r, PREG_SET_ORDER ) ){
                foreach( $r as $match ){
                    $headers[ strtolower($match[1]) ] = $match[2];
                }        
            }
            curl_close($ch);
        }
        else {
            $error = curl_error( $ch ) or 
            $error = 'No response from Twitter';
            is_resource($ch) and curl_close($ch);
            throw new TwitterApiException( $error );
        }
        return array (
            'body'    => $body,
            'status'  => $status,
            'headers' => $headers,
        );
    }



    /**
     * Get current rate limit, if known. does not look it up
     */
    public function last_rate_limit_data( $func = '' ){
        $func or $func = $this->last_call;
        return isset($this->last_rate[$func]) ? $this->last_rate[$func] : array( 'limit' => 0 );
    }
    
    
    /**
     * Get rate limit allowance for last endpoint request
     */
    public function last_rate_limit_allowance( $func = '' ){
        $data = $this->last_rate_limit_data($func);
        return isset($data['limit']) ? $data['limit'] : null;
    }
    
    
    /**
     * Get number of requests remaining this period for last endpoint request
     */
    public function last_rate_limit_remaining( $func = '' ){
        $data = $this->last_rate_limit_data($func);
        return isset($data['remaining']) ? $data['remaining'] : null;
    }
    
    
    /**
     * Get rate limit reset time for last endpoint request
     */
    public function last_rate_limit_reset( $func = '' ){
        $data = $this->last_rate_limit_data($func);
        return isset($data['reset']) ? $data['reset'] : null;
    }

}







/**
 * Simple token class that holds key and secret
 * @internal
 */
class TwitterOAuthToken {

    public $key;
    public $secret;
    public $verifier;
    public $user;

    public function __construct( $key, $secret = '' ){
        if( ! $key ){
           throw new Exception( 'Invalid OAuth token - Key required even if secret is empty' );
        }
        $this->key = $key;
        $this->secret = $secret;
        $this->verifier = '';
    }

    public function get_authorization_url(){
        return TWITTER_OAUTH_AUTHORIZE_URL.'?oauth_token='.rawurlencode($this->key);
    }

    public function get_authentication_url(){
        return TWITTER_OAUTH_AUTHENTICATE_URL.'?oauth_token='.rawurlencode($this->key);
    }

}





/**
 * Class for compiling, signing and serializing OAuth parameters
 * @internal
 */
class TwitterOAuthParams {
    
    private $args;
    private $consumer_secret;
    private $token_secret;
    
    private static function urlencode( $val ){
        return str_replace( '%7E', '~', rawurlencode($val) );
    }    
    
    public function __construct( array $args = array() ){
        $this->args = $args + array ( 
            'oauth_version' => '1.0',
        );
    }
    
    public function set_consumer( TwitterOAuthToken $Consumer ){
        $this->consumer_secret = $Consumer->secret;
        $this->args['oauth_consumer_key'] = $Consumer->key;
    }   
    
    public function set_token( TwitterOAuthToken $Token ){
        $this->token_secret = $Token->secret;
        $this->args['oauth_token'] = $Token->key;
    }   
    
    private function normalize(){
        $flags = SORT_STRING | SORT_ASC;
        ksort( $this->args, $flags );
        foreach( $this->args as $k => $a ){
            if( is_array($a) ){
                sort( $this->args[$k], $flags );
            }
        }
        return $this->args;
    }
    
    public function serialize(){
        $str = http_build_query( $this->args );
        // PHP_QUERY_RFC3986 requires PHP >= 5.4
        $str = str_replace( array('+','%7E'), array('%20','~'), $str );
        return $str;
    }

    public function sign_hmac( $http_method, $http_rsc ){
        $this->args['oauth_signature_method'] = 'HMAC-SHA1';
        $this->args['oauth_timestamp'] = sprintf('%u', time() );
        $this->args['oauth_nonce'] = sprintf('%f', microtime(true) );
        unset( $this->args['oauth_signature'] );
        $this->normalize();
        $str = $this->serialize();
        $str = strtoupper($http_method).'&'.self::urlencode($http_rsc).'&'.self::urlencode($str);
        $key = self::urlencode($this->consumer_secret).'&'.self::urlencode($this->token_secret);
        $this->args['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $str, $key, true ) );
        return $this->args;
    }

    public function oauth_header(){
        $lines = array();
        foreach( $this->args as $key => $val ){
            $lines[] = self::urlencode($key).'="'.self::urlencode($val).'"';
        }
        return 'OAuth '.implode( ",\n ", $lines );
    }

}






/**
 * HTTP status codes with some overridden for Twitter-related messages.
 * Note these do not replace error text from Twitter, they're for complete API failures.
 * @param int HTTP status code
 * @return string HTTP status text
 */
function _twitter_api_http_status_text( $s ){
    static $codes = array (
        100 => 'Continue',
        101 => 'Switching Protocols',
        
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        
        400 => 'Bad Request',
        401 => 'Authorization Required',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        //  ..
        429 => 'Twitter API rate limit exceeded',
        
        500 => 'Twitter server error',
        501 => 'Not Implemented',
        502 => 'Twitter is not responding',
        503 => 'Twitter is too busy to respond',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
    );
    return  isset($codes[$s]) ? $codes[$s] : sprintf('Status %u from Twitter', $s );
}





/**
 * Exception for throwing when Twitter responds with something unpleasant
 */
class TwitterApiException extends Exception {

    /**
     * HTTP Status of error
     * @var int
     */        
    protected $status = 0;        

        
    /**
     * Throw appropriate exception type according to HTTP status code
     * @param array Twitter error data from their response 
     */
    public static function chuck( array $err, $status ){
        $code = isset($err['code']) ? (int) $err['code'] : -1;
        $mess = isset($err['message']) ? trim($err['message']) : '';
        static $classes = array (
            404 => 'TwitterApiNotFoundException',
            429 => 'TwitterApiRateLimitException',
        );
        $eclass = isset($classes[$status]) ? $classes[$status] : __CLASS__;
        throw new $eclass( $mess, $code, $status );
    }
        
        
    /**
     * Construct TwitterApiException with addition of HTTP status code.
     * @overload
     */        
    public function __construct( $message, $code = 0 ){
        if( 2 < func_num_args() ){
            $this->status = (int) func_get_arg(2);
        }
        if( ! $message ){
            $message = _twitter_api_http_status_text($this->status);
        }
        parent::__construct( $message, $code );
    }
    
    
    /**
     * Get HTTP status of error
     * @return int
     */
    public function getStatus(){
        return $this->status;
    }
    
}


/** 404 */
class TwitterApiNotFoundException extends TwitterApiException {
    
}


/** 429 */
class TwitterApiRateLimitException extends TwitterApiException {
    
}
