<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Database\Enum\ConnectionType;
use byteShard\Database\Struct\Parameters;
use byteShard\Enum\LogLevel;
use byteShard\Enum\LogLocation;
use byteShard\Environment;
use byteShard\Exception;
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
    protected ?string         $url                          = null;
    protected ?string         $url_context                  = null;
    protected string          $ldap_url                     = '';
    protected int             $ldap_port;
    protected array|string    $ldap_domains                 = '';
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
    private array             $dbOptions                    = [];

    public function __construct()
    {
        if ($this->url === null) {
            $this->url = $this->getProtocol().'://'.$this->getHost();
        }
        if ($this->url_context === null) {
            $this->url_context = $this->identifyUrlContext();
        }
        if ($this->log_path === null) {
            $this->log_path = BS_FILE_PRIVATE_ROOT.DIRECTORY_SEPARATOR.'log';
        }
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

    private function identifyUrlContext(): string
    {
        $scriptName = strtolower($_SERVER['SCRIPT_NAME']);
        $endpoints  = [
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
            'index.php',
            'setup.php',
            'bs/bs_async.php',
        ];
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
        return $this->url ?? '';
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

    /**
     * @return LogLevel
     */
    public function getLogLevel(): LogLevel
    {
        return $this->log_level;
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
