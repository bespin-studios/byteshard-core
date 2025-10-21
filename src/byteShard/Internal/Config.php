<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Authentication\JWTProperties;
use byteShard\Database\Enum\ConnectionType;
use byteShard\Database\Struct\Parameters;
use byteShard\Enum\LogFormat;
use byteShard\Enum\LogLevel;
use byteShard\Enum\LogLocation;
use byteShard\Environment;
use byteShard\Exception;
use byteShard\Ldap\Attribute;
use byteShard\Ldap\Attributes;
use byteShard\Ldap\Enum\ResultObject;
use byteShard\Jwt;
use byteShard\Password;
use JsonSerializable;

/**
 * Class Config
 * @package byteShard\Internal
 */
abstract class Config implements JsonSerializable
{
    const DEVELOPMENT = 'dev';
    const PRODUCTION  = 'prod';
    const TESTING     = 'test';

    protected string          $environment                  = Config::PRODUCTION;
    protected string          $public_path                  = 'public';
    protected ?string         $log_path                     = null;
    protected string          $log_file                     = 'byteShard.log';
    protected string          $log_channel_name             = 'byteShard';
    protected LogLevel        $log_level                    = LogLevel::ERROR;
    protected LogLocation     $log_location                 = LogLocation::FILE;
    protected LogFormat       $log_format                   = LogFormat::JSON;
    protected ?string         $url                          = null;
    protected ?string         $url_context                  = null;
    protected string          $ldap_url                     = '';
    protected int             $ldap_port;
    protected array|string    $ldap_domains                 = '';
    protected string          $ldap_bind_dn                 = '';
    protected string          $ldap_bind_user               = '';
    protected string          $ldap_bind_pass               = '';
    protected string          $ldap_uid                     = 'uid';
    protected string          $ldap_base_dn                 = '';
    protected string          $ldap_method                  = '';
    protected bool            $show_ldap_domains            = true;
    protected string          $application_name             = 'byteShard application';
    protected string          $application_version          = '1.0';
    protected string          $db_server                    = '';
    protected null|int|string $db_port                      = '';
    protected string          $db                           = '';
    protected string          $schema                       = '';
    protected string          $db_user;
    protected string          $db_pass;
    protected string          $db_user_login;
    protected string          $db_user_read;
    protected string          $db_user_write;
    protected string          $db_user_admin;
    protected Password        $db_pass_login;
    protected Password        $db_pass_read;
    protected Password        $db_pass_write;
    protected Password        $db_pass_admin;
    protected string          $db_collate                   = 'utf8mb4_unicode_ci';
    protected string          $db_charset                   = 'utf8mb4';
    protected bool            $decodeUTF8                   = true;
    protected bool            $useClientTimeZone            = false;
    protected bool            $developmentJavascriptFiles   = false;
    protected bool            $useSVG                       = false;
    protected bool            $convertImageNamesToLowerCase = false;
    protected string          $jwtPrivateKeyPath;
    protected string          $jwtPublicKeyPath;
    protected int             $jwtAlgorithm                 = OPENSSL_ALGO_SHA256;
    private array             $dbOptions                    = [];
    private string            $realUrl;

    public function __construct()
    {
        if ($this->url !== null) {
            $this->realUrl = $this->url;
        } else {
            Debug::warning('No URL set in config. Please define "protected ?string $url" otherwise this will be derived from unsafe headers');
            $this->realUrl = Server::getProtocol().'://'.Server::getHost();
        }
        if ($this->url_context === null) {
            $this->url_context = $this->identifyUrlContext();
        }
        if ($this->log_path === null) {
            $this->log_path = BS_FILE_PRIVATE_ROOT.DIRECTORY_SEPARATOR.'log';
        }
    }

    /**
     * @throws Exception
     */
    public static function getConfig(): object
    {
        if (class_exists('\\config')) {
            return new \config();
        }
        throw new Exception('Config class does not exists. Please create the Class with namespace \\config');
    }

    public function getJwtPrivateKeyPath(): string
    {
        if (!isset($this->jwtPrivateKeyPath) || !is_readable($this->jwtPrivateKeyPath)) {
            $this->createTmpJwtKeyPair();
        }
        return $this->jwtPrivateKeyPath;
    }

    public function getJwtPublicKeyPath(): string
    {
        if (!isset($this->jwtPublicKeyPath) || !is_readable($this->jwtPublicKeyPath)) {
            $this->createTmpJwtKeyPair();
        }
        return $this->jwtPublicKeyPath;
    }

    private function createTmpJwtKeyPair(): void
    {
        Debug::warning('No private and/or public key set in config. Using temporary generated keys. Please configure them with $jwtPrivateKeyPath and $jwtPublicKeyPath');
        $directory = '/tmp/jwtKeys';
        if (!is_dir($directory)) {
            mkdir($directory);
        }
        $this->jwtPrivateKeyPath = $directory.DIRECTORY_SEPARATOR.'private.key';
        $this->jwtPublicKeyPath  = $directory.DIRECTORY_SEPARATOR.'public.key';
        if (!is_file($this->jwtPrivateKeyPath) || !is_file($this->jwtPublicKeyPath)) {
            Jwt::generateKeyPair($this->jwtPrivateKeyPath, $this->jwtPublicKeyPath);
        }
    }

    public function getJwtAlgorithm(): int
    {
        return $this->jwtAlgorithm;
    }

    public function getConfiguredClaims(): array
    {
        return [
            JWTProperties::Firstname->value => 'given_name',
            JWTProperties::Lastname->value  => 'family_name',
            JWTProperties::Username->value  => 'preferred_username',
            JWTProperties::Email->value     => 'email',
            JWTProperties::Groups->value    => 'groups'
        ];
    }

    /**
     * @return bool
     */
    public function convertImageNamesToLowerCase(): bool
    {
        return $this->convertImageNamesToLowerCase;
    }

    /**
     * @return bool
     */
    public function useSVG(): bool
    {
        return $this->useSVG;
    }

    public static function getInternalEndpoints(): array
    {
        return [
            'bs/bs_cellcontent.php',
            'bs/bs_event.php',
            'bs/bs_parameters.php',
            'bs/bs_tabcontent.php',
            'bs/bs_error.php',
            'bs/bs_combo.php',
            'bs/bs_export.php',
            'bs/bs_loader.php',
            'bs/bs_locale.php',
            'bs/bs_queue.php',
            'bs/bs_upload.php',
            'bs/bs_async.php',
        ];
    }

    private function identifyUrlContext(): string
    {
        $scriptName  = strtolower($_SERVER['SCRIPT_NAME']);
        $endpoints   = self::getInternalEndpoints();
        $endpoints[] = 'login/index.php';
        $endpoints[] = 'index.php';
        $endpoints[] = 'setup.php';
        foreach ($endpoints as $endpoint) {
            if (str_contains($scriptName, $endpoint)) {
                return '/'.trim(str_replace($endpoint, '', $scriptName), '/');
            }
        }
        return '';
    }

    /**
     * @return bool
     * @API
     */
    public function useDevelopmentJavascriptFiles(): bool
    {
        return $this->developmentJavascriptFiles;
    }

    /**
     * @return bool
     */
    public function useDecodeUtf8(): bool
    {
        return $this->decodeUTF8;
    }

    /**
     * @return bool
     * @API
     */
    public function isUseClientTimeZone(): bool
    {
        return $this->useClientTimeZone;
    }

    private function getProtocol(): string
    {
        $https = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $https = true;
        } elseif ((isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_HTTPS']) && $_SERVER['HTTP_X_FORWARDED_HTTPS'] === 'on')) {
            $https = true;
        }
        return (($https === true) ? 'https' : 'http');
    }

    private function getHost(): string
    {
        $host_keys = ['HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR'];

        $host = '';
        foreach ($host_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $host = $_SERVER[$key];
                break;
            }
        }

        // HTTP_X_FORWARDED_HOST might be a comma separated list
        if (str_contains($host, ',')) {
            $tmp  = explode(',', $host);
            $host = trim(end($tmp));
        }

        // Remove port number from host
        $host = preg_replace('/:\d+$/', '', $host);

        $port      = 443;
        $port_keys = array('HTTP_X_FORWARDED_PORT', 'SERVER_PORT');
        foreach ($port_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $port = (int)$_SERVER[$key];
                break;
            }
        }
        if ($port === 443 || $port === 80) {
            $port = '';
        } else {
            $port = ':'.$port;
        }
        return trim($host.$port);
    }

    public function getEnvironment(): string
    {
        if ($this->environment === self::DEVELOPMENT) {
            return self::DEVELOPMENT;
        }
        if ($this->environment === self::TESTING) {
            return self::TESTING;
        }
        return self::PRODUCTION;
    }

    /**
     * @return Environment
     * @throws Exception
     * @API
     */
    public function getSettingsObject(): Environment
    {
        if (class_exists('\\App\\Environment')) {
            /**
             * @noinspection PhpUndefinedNamespaceInspection
             * @noinspection PhpUndefinedClassInspection
             */
            $environment = new \App\Environment();
            if ($environment instanceof Environment) {
                return $environment;
            }
            throw new Exception('Application environment '.get_class($environment).' must extend byteShard\Environment');
        } else {
            throw new Exception('Application environment does not exists. Please create the Class with namespace \\App\\Environment');
        }
    }

    /**
     * @return string
     * @API
     */
    public function getApplicationName(): string
    {
        return $this->application_name;
    }

    /**
     * @return string
     * @API
     */
    public function getApplicationVersion(): string
    {
        return $this->application_version;
    }

    /**
     * @return string
     */
    public function getLogPath(): string
    {
        if (!empty($this->log_path)) {
            return $this->log_path;
        }
        return str_replace(DIRECTORY_SEPARATOR.'byteShard'.DIRECTORY_SEPARATOR.'Internal', '', __DIR__).DIRECTORY_SEPARATOR.'log';
    }

    /**
     * @return LogLocation
     */
    public function getLogLocation(): LogLocation
    {
        return $this->log_location;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->realUrl;
    }

    /**
     * @return string
     */
    public function getUrlContext(): string
    {
        return $this->url_context ?? '';
    }

    /**
     * @return string
     * @API
     */
    public function getLdapUrl(): string
    {
        return $this->ldap_url;
    }

    /**
     * @return ?int
     * @API
     */
    public function getLdapPort(): ?int
    {
        return $this->ldap_port ?? null;
    }

    public function getLdapBindDn(): string
    {
        return $this->ldap_bind_dn;
    }

    public function getLdapBindUser(): string
    {
        return $this->ldap_bind_user;
    }

    public function getLdapBindPass(): string
    {
        return $this->ldap_bind_pass;
    }

    public function getLdapUid(): string
    {
        return $this->ldap_uid;
    }

    public function getLdapBaseDn(): string
    {
        return $this->ldap_base_dn;
    }

    public function getLdapMethod(): string
    {
        return $this->ldap_method;
    }

    /**
     * @return array
     * @API
     */
    public function getLdapDomains(): array
    {
        if (!is_array($this->ldap_domains)) {
            if ($this->ldap_domains !== '') {
                return [$this->ldap_domains];
            }
            return [];
        }
        return $this->ldap_domains;
    }

    /**
     * @return bool
     * @API
     */
    public function showLdapDomains(): bool
    {
        return $this->show_ldap_domains;
    }

    public function getLdapAttributes(): Attributes
    {
        return new Attributes(
            new Attribute('dn', ResultObject::dn),
            new Attribute('uid', ResultObject::Username),
            new Attribute('sn', ResultObject::Lastname),
            new Attribute('givenname', ResultObject::Firstname),
            new Attribute('mail', ResultObject::Mail),
            new Attribute('memberOf', ResultObject::Groups),
        );
    }

    /**
     * @return LogLevel
     */
    public function getLogLevel(): LogLevel
    {
        return $this->log_level;
    }

    /**
     * @return LogFormat
     */
    public function getLogFormatting(): LogFormat
    {
        return $this->log_format;
    }

    /**
     * @return string
     * @API
     */
    public function getLogFile(): string
    {
        return $this->log_file;
    }

    /**
     * @return string
     */
    public function getLogFilePath(): string
    {
        return $this->log_path.DIRECTORY_SEPARATOR.$this->log_file;
    }

    /**
     * @return string
     */
    public function getLogChannelName(): string
    {
        return $this->log_channel_name;
    }

    /**
     * @return string
     * @API
     */
    public function getPublicPath(): string
    {
        return $this->public_path;
    }

    /**
     * @param ConnectionType|null $type
     * @return Parameters
     */
    public function getDbParameters(?ConnectionType $type = null): Parameters
    {
        $parameters           = new Parameters();
        $parameters->server   = $this->db_server;
        $parameters->port     = is_string($this->db_port) ? (int)$this->db_port : $this->db_port;
        $parameters->database = $this->db;
        $parameters->schema   = $this->schema;
        if ($type === null) {
            $parameters->username = $this->db_user;
            $parameters->password = $this->db_pass;
        } else {
            switch ($type) {
                case ConnectionType::ADMIN:
                    $parameters->username = $this->db_user_admin ?? $this->db_user;
                    $parameters->password = $this->db_pass_admin ?? $this->db_pass;
                    break;
                case ConnectionType::LOGIN:
                    $parameters->username = $this->db_user_login ?? $this->db_user;
                    $parameters->password = $this->db_pass_login ?? $this->db_pass;
                    break;
                case ConnectionType::READ:
                    $parameters->username = $this->db_user_read ?? $this->db_user;
                    $parameters->password = $this->db_pass_read ?? $this->db_pass;
                    break;
                case ConnectionType::WRITE:
                    $parameters->username = $this->db_user_write ?? $this->db_user;
                    $parameters->password = $this->db_pass_write ?? $this->db_pass;
                    break;
            }
        }
        return $parameters;
    }

    public function getDbOptions(): array
    {
        return $this->dbOptions;
    }

    /**
     * The first five parameters will be passed to mysqli ssl_set
     * verifySSL will verify the server SSL
     * @API
     */
    public function setDbUseSSL(?string $key, ?string $certificate, ?string $certificateBundle, ?string $caPath, ?string $cipherAlgorithms, bool $verifySSL = true): self
    {
        $this->dbOptions['useSSL'] = true;
        if ($key !== null || $certificate !== null || $certificateBundle !== null || $caPath !== null || $cipherAlgorithms !== null) {
            $this->dbOptions['sslSet'] = [$key, $certificate, $certificateBundle, $caPath, $cipherAlgorithms];
        }
        $this->dbOptions['verifySSL'] = $verifySSL;
        return $this;
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        if (defined('DISCLOSE_CREDENTIALS') && DISCLOSE_CREDENTIALS === true && defined('LOGLEVEL') && LOGLEVEL === LogLevel::DEBUG) {
            return get_object_vars($this);
        }
        $debug_info                  = get_object_vars($this);
        $debug_info['db_pass']       = !isset($this->db_pass) ? '' : 'CONFIDENTIAL';
        $debug_info['db_pass_admin'] = !isset($this->db_pass_admin) ? '' : 'CONFIDENTIAL';
        $debug_info['db_pass_login'] = !isset($this->db_pass_login) ? '' : 'CONFIDENTIAL';
        $debug_info['db_pass_read']  = !isset($this->db_pass_read) ? '' : 'CONFIDENTIAL';
        $debug_info['db_pass_write'] = !isset($this->db_pass_write) ? '' : 'CONFIDENTIAL';
        return $debug_info;
    }

    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * @return string
     */
    public function getCollate(): string
    {
        return $this->db_collate;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->db_charset;
    }
}
