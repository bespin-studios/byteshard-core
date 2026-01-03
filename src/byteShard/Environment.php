<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

use byteShard\Config\CustomDataModelInterface;
use byteShard\Config\OverrideDataModelInterface;
use byteShard\Database\Enum\ConnectionType;
use byteShard\Database\Model;
use byteShard\Database\Struct\Parameters;
use byteShard\Enum\LogLevel;
use byteShard\Form\Enum\Label\Position;
use byteShard\Form\FormSettingsInterface;
use byteShard\Form\Settings;
use byteShard\Internal\ApplicationRootInterface;
use byteShard\Internal\Authentication\Authentication;
use byteShard\Internal\Authentication\AuthenticationAction;
use byteShard\Internal\Authentication\AuthenticationInterface;
use byteShard\Internal\Authentication\DeprecatedLdapProviderWrapper;
use byteShard\Internal\Authentication\LdapProviderInterface;
use byteShard\Internal\Authentication\LocalProviderInterface;
use byteShard\Internal\ByteShard\Css;
use byteShard\Internal\ByteShard\Javascript;
use byteShard\Internal\Config;
use byteShard\Internal\Database\ParametersInterface;
use byteShard\Internal\Deeplink\Deeplink;
use byteShard\Internal\ErrorHandler;
use byteShard\Internal\Login;
use byteShard\Internal\Server;
use byteShard\Internal\Session;
use byteShard\Internal\TabParentInterface;
use JsonSerializable;
use Monolog\Formatter\FormatterInterface;
use stdClass;

/**
 * Class Environment
 * @exceptionId 00004
 * @package byteShard
 */
abstract class Environment implements ParametersInterface, JsonSerializable
{
    const DRIVER_MySQL_mysqli   = 'mysql_mysqli';
    const DRIVER_MYSQL_PDO      = 'mysql_pdo';
    const DRIVER_PGSQL_PDO      = 'pgsql_pdo';
    const DRIVER_MSSQL_ado      = 'mssql_ado';
    const DRIVER_SQLITE_sqlite3 = 'sqlite_sqlite3';

    /* Example:
    * User     accTarget     authentication_target
    * 1        db            db
    * 2        db            ldap
    * 3        ldap          ldap
    * 4        ldap          db
    * 1: if the user is allowed to log in depends on a field "grantLogin" on the DB. If true, authentication will be done against a hash stored on the DB
    * 2: if the user is allowed to log in depends on a field "grantLogin" on the DB. If true, authentication will be done against the defined ldap host
    * 3: if the user is allowed to log in depends on a successful authentication against the defined ldap host. Access can be restricted by using ldap groups
    * 4: this usually makes no sense, since you need to authenticate against the ldap to check group permissions and then authenticate against a DB stored password...
    * accTarget and authentication_target can be defined in application Environment or if AUTH_TARGET_DEFINED_ON_DB &&|| ACCESS_CONTROL_DEFINED_ON_DB are used, accTarget &&|| authentication_target can be stored on a per-user basis on the DB
    * ('db' or 'ldap' must be stored in the respective fields)
    */

    protected bool    $require_ssl                   = true;
    protected int     $sessionTimeoutInMinutes       = 240; //after that many minutes without any action, the user has to re-login to continue
    static public int $zlib_output_compression       = 4096;
    static public int $zlib_output_compression_level = 9;
    protected bool    $service_mode                  = false;       //changing service_mode to true will immediately log out all users on their next request and prevent future logins unless User has a serviceAccount
    protected string  $main                          = 'byteShard'; //changing this will impact certain functionality
    public string     $dbCharset                     = 'iso-8859-1';
    public string     $clientCharset                 = 'utf8';
    public string     $phpCharset                    = 'utf8';
    protected bool    $use_single_user_token         = true;

    /*public        $db_time_format;
    public        $client_date_format = '%d.%m.%Y';
    public        $client_time_format = '%H:%i:%s';
    public        $client_datetime_format = '%d.%m.%Y %H:%i:%s';*/

    /**
     * @var string
     */
    protected string $locale = 'en';

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * List all supported locales depending on the application
     * @var array
     */
    protected array $locales = ['en'];

    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * environment which can be configured in config
     * @var string
     */
    protected string $environment = Config::PRODUCTION;

    // Date and Time related parameters
    //TODO: client formats by locale
    protected string $client_timezone = 'GMT+2';

    protected Enum\DB\ColumnType $client_form_control_calendar_default_db_column_type = Enum\DB\ColumnType::DATETIME2;
    protected Enum\DB\ColumnType $client_grid_column_calendar_default_db_column_type  = Enum\DB\ColumnType::DATETIME2;
    protected Enum\DB\ColumnType $client_grid_column_date_default_db_column_type      = Enum\DB\ColumnType::DATETIME2;
    protected Enum\DB\ColumnType $db_meta_data_column_created_on_column_type          = Enum\DB\ColumnType::DATETIME2;
    protected Enum\DB\ColumnType $db_meta_data_column_modified_on_column_type         = Enum\DB\ColumnType::DATETIME2;
    protected Enum\DB\ColumnType $db_meta_data_column_archived_on_column_type         = Enum\DB\ColumnType::DATETIME2;

    protected string $db_timezone                        = 'UTC';
    protected string $db_column_date_format              = 'Y-m-d';
    protected string $db_column_smalldatetime_format     = 'Y-m-d H:i:s';
    protected string $db_column_datetime_format          = 'Y-m-d H:i:s.u';
    protected int    $db_column_datetime_precision       = 3;
    protected string $db_column_datetime2_format         = 'Y-m-d H:i:s.u';
    protected int    $db_column_datetime2_precision      = 7;
    protected string $db_column_datetimeoffset_format    = 'Y-m-d H:i:s.u';
    protected int    $db_column_datetimeoffset_precision = 7;
    protected string $db_column_bigintdate_format        = 'YmdHis';
    protected string $db_column_time_format              = 'H:i:s.u';
    protected int    $db_column_time_precision           = 7;

    // Paths
    protected string  $appDir        = 'application';
    protected string  $cssPath       = 'css';
    protected string  $libPath       = 'lib';
    protected string  $jsPath        = 'js';
    protected string  $uploadDir     = 'upload';
    protected ?string $logDir        = null;   // if defined this must be a full qualified path starting with '/' or a drive letter and a trailing (back-)slash
    protected string  $defaultLogDir = 'logs'; // unless a logDir is specified, this directory will be used for all logging. This path will be prepended by the filerootDir

    // Files
    protected string $errorLog = 'error.log';
    protected string $debugLog = 'debug.log';

    // NEEDS TO BE SET IN ApplicationEnvironment
    // Database
    protected string $dbDriver = self::DRIVER_MySQL_mysqli;

    /**
     * @var string
     */
    protected string $database;

    /**
     * @var Parameters
     */
    protected Parameters $db_parameters_admin;

    /**
     * @var Parameters
     */
    protected Parameters $db_parameters_login;

    /**
     * @var Parameters
     */
    protected Parameters $db_parameters_read;

    /**
     * @var Parameters
     */
    protected Parameters $db_parameters_write;

    // Application
    protected string $favicon = '';
    /**
     * @var string
     */
    protected string $application_name = '';
    protected string $leftFooterText   = '';
    // Login related
    protected string  $ldap_host;
    protected string  $ldap_user;
    protected ?string $ldap_pass           = null;
    protected string  $ldap_port;
    protected string  $ldap_domain;
    protected bool    $lower_case_username = true;

    /**
     * ### DEBUGGING ###
     */
    /**
     * this will enable/disable all development related debug information
     * @var bool
     */
    protected bool $debug = false;

    /**
     * this will use the locale marked as debug
     * self::$debug overrides this setting
     *
     * @var bool $debug_locale
     */
    protected bool $debug_locale = false;

    /**
     * this will display the locale token of every single item
     * self::$debug overrides this setting
     *
     * @var bool $debug_locale_token
     */
    protected bool $debug_locale_token = false;

    /**
     * if true IDs won't be encrypted
     * self::$debug overrides this setting
     *
     * @var bool $debug_id
     */
    protected bool $debug_id = false;

    protected string $logoffButtonName = 'logoff';

    /**
     * setting this parameter will change the behavior how the application determines if a user is allowed to log in
     * this is necessary since there might be many records in the user table which are not actually users but user data used for a different purpose
     * @var Enum\AccessControlTarget
     */
    protected Enum\AccessControlTarget             $access_control_target = Enum\AccessControlTarget::ACCESS_CONTROLLED_BY_DB;
    protected bool                                 $showForgotPass        = false;
    protected \byteShard\Internal\Schema\LoginForm $loginFormSchema;

    /**
     * if logout is true the destructor of this class will end the session
     * @var bool
     */
    private bool $logout = false;

    /** @var string enabled|hidden|disabled
     * TODO: create functionality to implement different authentications in Config
     */
    private string $localAuthentication = 'enabled';

    private Config $config;

    public function hideLocalAuthentication(): void
    {
        $this->localAuthentication = 'hidden';
    }

    public function disableLocalAuthentication(): void
    {
        $this->localAuthentication = 'disabled';
    }

    public function isLocalAuthenticationEnabled(): bool
    {
        return $this->localAuthentication !== 'disabled';
    }

    /**
     * optional method for application environment
     * @return bool
     */
    protected function initializeApplicationGlobals(): bool
    {
        return true;
    }

    /**
     * optional method for application environment
     * @return bool
     */
    protected function initializeApplicationBeforeAuthentication(): bool
    {
        return true;
    }

    /**
     * optional method for application environment
     * this method is called on each and every request.
     * @return bool
     */
    protected function initializeApplicationAfterAuthentication(): bool
    {
        return true;
    }

    /**
     * optional method to specify user specific permissions to be used throughout the application
     * @return ?Permission
     */
    protected function initializePermissions(): ?Permission
    {
        return null;
    }

    /**
     * @param TabParentInterface $tab_parent
     * @return boolean
     */
    //abstract protected function initializeTabs(TabParentInterface $tab_parent);

    /**
     * optional method for application environment
     */
    protected function addCssTagsToHtmlHead(): array
    {
        $styleSheets = [];
        if (method_exists($this, 'getApplicationCss')) {
            trigger_error('getApplicationCss is deprecated. Please use addCssTagsToHtmlHead instead');
            $legacyApplicationCss = $this->getApplicationCss();
            if (is_string($legacyApplicationCss)) {
                $styleSheets[] = $legacyApplicationCss;
            } else {
                array_push($styleSheets, ...$legacyApplicationCss);
            }
        }
        return $styleSheets;
    }

    /**
     * returns array of <link> tags for the head
     * @return array
     */
    protected function addDhtmlxCSSTagsToHtmlHead(): array
    {
        return Css::includeCssFullPath(['dhx/css/dhtmlx5.css']);
    }

    /**
     * returns array of <link> tags for the head
     * @return array
     */
    protected function addApplicationCSSTagsToHtmlHead(): array
    {
        return Css::includeCssFullPath(['app/css/main.css']);
    }

    /**
     * optional method for application environment
     */
    protected function addJavascriptTagsToHtmlHead(): array
    {
        $scripts = [];
        if (method_exists($this, 'getApplicationJavascripts')) {
            trigger_error('getApplicationJavascripts is deprecated. Please use addJavascriptTagsToHtmlHead instead');
            $legacyApplicationJavaScript = $this->getApplicationJavascripts();
            if (is_string($legacyApplicationJavaScript)) {
                $scripts[] = $legacyApplicationJavaScript;
            } else {
                array_push($scripts, ...$legacyApplicationJavaScript);
            }
        }
        return $scripts;
    }

    /**
     * returns array of <script> tags for the head
     * @return array
     */
    protected function addDhtmlxJSTagsToHtmlHead(): array
    {
        return Javascript::includeJavascriptFullPath(['dhx/js/dhtmlx5.js']);
    }

    /**
     * returns array of <script> tags for the head
     * @return array
     */
    protected function addApplicationJSTagsToHtmlHead(): array
    {
        return Javascript::includeJavascriptFullPath(['app/js/main.js']);
    }

    /**
     * @return ?AuthenticationInterface
     */
    protected function getApplicationAuthenticationObject(): ?AuthenticationInterface
    {
        return null;
    }

    // override in App\Config\ByteShard
    public function getLdapProvider(): ?LdapProviderInterface
    {
        //TODO: create interface for "new" Ldap provider, create migration layer from old to new
        $legacy = $this->getDeprecatedLegacyProvider();
        if ($legacy instanceof AuthenticationInterface) {
            return new DeprecatedLdapProviderWrapper($legacy);
        }
        return null;
    }

    // override in App\Config\ByteShard
    public function getLocalProvider(): ?LocalProviderInterface
    {
        return null;
    }

    private function getDeprecatedLegacyProvider(): ?AuthenticationInterface
    {
        // method to encapsulate the deprecation error. Remove once getApplicationAuthenticationObject() is removed
        $legacyProvider = $this->getApplicationAuthenticationObject();
        if ($legacyProvider !== null) {
            trigger_error('getApplicationAuthenticationObject has been deprecated. Please migrate to getLocalProvider/getLdapProvider/getOauthProvider', E_USER_DEPRECATED);
        }
        return $legacyProvider;
    }

    // override in app config
    protected function defineLoginTemplate(): LoginFormInterface
    {
        return new Login\UnifiedTemplate();
    }

    public function getFaviconPath(): string
    {
        return $this->favicon ?? '';
    }

    public function getLoginTemplate(): LoginFormInterface
    {
        $template = $this->defineLoginTemplate();
        return $template;
    }


    /**
     *
     */
    protected function printLoginForm(): void
    {
        $template = new Login\Template(
            $this->loginFormSchema,
            $this->getJavascripts(['login.js'], '', 'bs'),
            $this->getCss(['login.css'], '', 'bs'),
            $this->favicon,
            $this->getAppName()
        );
        $template->printLoginForm();
    }

    protected function printLoginForm_failed($secondsToWait = 0): void
    {
        $this->printLoginForm();
    }

    protected function printLoginForm_error($fileaccess = true): void
    {
        $this->printLoginForm();
    }

    protected function printLoginForm_loggedout(): void
    {
        $this->printLoginForm();
    }

    protected function printLoginForm_passwordExpired(): void
    {
        $this->printLoginForm();
    }

    protected function printLoginForm_authTargetUnreachable(): void
    {
        $this->printLoginForm();
    }

    protected function printChangePasswordForm(): void
    {
        $this->printLoginForm();
    }

    protected function printChangePasswordForm_newPassDontMatch(): void
    {
        $this->printLoginForm();
    }

    protected function printChangePasswordForm_oldPassInvalid(): void
    {
        $this->printLoginForm();
    }

    protected function printChangePasswordForm_newPassUsed(): void
    {
        $this->printLoginForm();
    }

    protected function printChangePasswordForm_newPassInvalid(): void
    {
        $this->printLoginForm();
    }

    protected function printForgotPasswordForm(): void
    {
        $this->printLoginForm();
    }

    protected function setApplicationLoginFormSchema(): void
    {
        $this->loginFormSchema = new \byteShard\Internal\Schema\LoginForm();
    }

    protected function construct(Config $config)
    {
        $this->config      = $config;
        $this->environment = $config->getEnvironment();
        $this->initializeFrameworkGlobals($config->getLogLevel(), $config->getUrlContext());
    }

    /**
     * @return FormatterInterface|null
     */
    public function getLogFormatter(): FormatterInterface|null
    {
        return null;
    }

    /**
     * @return bool
     */
    public function getDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @return string|null
     */
    public function getDhtmlxCssImagePath(): ?string
    {
        return null;
    }

    /**
     * override in application environment in case you want to pass application-specific settings
     */
    public function getFormSettings(?int $labelWidth = null, ?int $inputWidth = null, ?Position $position = null): FormSettingsInterface
    {
        $formSettings = new Settings();
        if ($labelWidth !== null) {
            $formSettings->setLabelWidth($labelWidth);
        }
        if ($inputWidth !== null) {
            $formSettings->setInputWidth($inputWidth);
        }
        if ($position !== null) {
            $formSettings->setPosition($position);
        }

        return $formSettings;
    }

    /**
     * override in application environment in case a specific content needs to be displayed
     */
    public function getNoApplicationPermissionContent(): object
    {
        trigger_error('getNoApplicationPermissionContent() is deprecated. Please create a cell and return the namespace in getNoApplicationPermission()', E_USER_DEPRECATED);
        $result              = new stdClass();
        $result->label_width = 500;
        $result->tab_label   = Locale::get('byteShard.environment.tab.label.noPermission');
        $result->labels[]    = sprintf(Locale::get('byteShard.environment.cell.label.noPermission'), $this->application_name);
        return $result;
    }

    public function getNoApplicationPermission(): ?string
    {
        return null;
    }

    /**
     * @param \byteShard\ID\ID $lastTabId
     */
    public function setLastTab(\byteShard\ID\ID $lastTabId): void
    {
        $userId = \byteShard\Session::getUserId();
        if ($userId !== null) {
            $lastTab = $lastTabId->getTabId();
            $model   = $this->getDataModel();
            $model->setLastTab($userId, $lastTab);
        }
    }

    private function getDataModel(): DataModelInterface
    {
        if ($this instanceof CustomDataModelInterface) {
            return $this->getByteShardDataModel();
        }
        $model = match ($this->dbDriver) {
            self::DRIVER_MYSQL_PDO => new Model\MySQL\PDO(),
            self::DRIVER_PGSQL_PDO => new Model\PostgreSQL\PDO(),
            default                => new Model\DeprecatedModel(),
        };

        if ($this instanceof OverrideDataModelInterface) {
            $model->setUserTableSchema($this->getOverrideDefinitions());
        }
        return $model;
    }

    /**
     * @param $tabName
     * @param $cellName
     * @param $type
     * @param $item
     * @param $value
     */
    public function storeUserSetting($tabName, $cellName, $type, $item, $value): void
    {
        $userId = \byteShard\Session::getUserId();
        if (!empty($userId)) {
            $this->getDataModel()->storeUserSetting($tabName, $cellName, $type, $item, $userId, $value);
        }
    }

    public function deleteUserSetting(string $tabName, string $cellName, string $type, string $item): void
    {
        $userId = \byteShard\Session::getUserId();
        if ($userId !== null) {
            $this->getDataModel()->deleteUserSetting($tabName, $cellName, $type, $item, $userId);
        }
    }

    public function initSessionCallback(Session $session): void
    {
        $session->setLocales($this->locales);
        $session->setTimezones($this->client_timezone, $this->db_timezone);
        $session->setDBFormats($this->db_column_date_format, $this->db_column_smalldatetime_format, $this->db_column_datetime_format, $this->db_column_datetime_precision, $this->db_column_datetime2_format, $this->db_column_datetime2_precision, $this->db_column_datetimeoffset_format, $this->db_column_datetimeoffset_precision, $this->db_column_bigintdate_format, $this->db_column_time_format, $this->db_column_time_precision);
        $session->setClientFormats($this->client_form_control_calendar_default_db_column_type, $this->client_grid_column_calendar_default_db_column_type, $this->client_grid_column_date_default_db_column_type);
        $session->setMetaColumnFormats($this->db_meta_data_column_created_on_column_type, $this->db_meta_data_column_modified_on_column_type, $this->db_meta_data_column_archived_on_column_type);
    }

    public function initializeUserCallback(): void
    {
        $this->initializeUser();
    }

    private function initializeUser(): void
    {
        \byteShard\Session::setTimeOfLastUserRequest();

        if (isset($GLOBALS['error_handler']) && ($GLOBALS['error_handler'] instanceof ErrorHandler)) {
            $GLOBALS['error_handler']->setResultObject(ErrorHandler::RESULT_OBJECT_CELL_CONTENT); //in case of error display a cell content error and don't redirect to log in
        }

        $this->initializeApplicationAfterAuthentication();
        if (\byteShard\Session::arePermissionsInitialized() === false) {
            \byteShard\Session::setPermissionObject($this->initializePermissions());
            \byteShard\Session::setPermissionsAreInitialized();
        }
        if (\byteShard\Session::areTabsInitialized() === false && method_exists($this, 'initializeTabs') && $this->initializeTabs(\byteShard\Session::getSessionObject()) === true) {
            \byteShard\Session::setTabsAreInitialized();
        }
    }

    public function authenticate(): void
    {
        //TODO: rename this function, think of a more appropriate name
        // todo: move to LocalForm auth provider

        $this->initializeApplicationGlobals();
        $this->initializeFrameworkBeforeAuthentication();
        $this->initializeApplicationBeforeAuthentication();

        $session = $this->startSession();

        $this->setApplicationLoginFormSchema();

        $auth = new Internal\Authentication\Authentication(
            session                : $session,
            environment            : $this,
            serviceMode            : $this->service_mode,
            sessionTimeoutInMinutes: $this->sessionTimeoutInMinutes,
            logoffButtonName       : $this->logoffButtonName,
            dataModel              : $this->getDataModel()
        );

        $this->setApplicationLoginFormSchema();

        $auth->authenticate();
    }

    public function processSuccessfulLogin(string $username): void
    {
        $userId     = null;
        $deprecated = $this->getApplicationAuthenticationObject();
        if ($deprecated !== null) {
            trigger_error('Method getUserID in AuthenticationProvider is deprecated. Please implement in byteShard Datamodel', E_USER_DEPRECATED);
            $userId = $deprecated->getUserID($username);
        }
        if ($userId === null) {
            $userId = $this->getDataModel()->getUserId($username);
        }
        if ($userId === null) {
            // we can safely redirect with no local user here since the user already logged in successfully with the IDP
            // but the user doesn't exist in the local database.
            // usually we have to insert the user if it doesn't exist during the call to fetch the userId: $this->getByteShardDataModel()->getUserId($username);
            Authentication::logout(action: AuthenticationAction::NO_LOCAL_USER);
        }

        // change of privilege, regenerate session id to prevent session fixation attacks
        session_regenerate_id();

        // last login and login count will be stored. But only if fieldname_lastLogin and/or fieldname_loginCount are defined in user_table_schema
        $this->getDataModel()->successfulLoginCallback($userId);

        \byteShard\Session::setUserData($userId, $username, $this->getLastTab($userId));
        $this->successfulLoginCallback($userId, $username);
        Deeplink::checkReferrer();
        header('Location: '.BS_WEB_ROOT_DIR.'/');
    }

    public function startSession(): Session
    {
        $session = \byteShard\Session::createSession($this->locale, $this->require_ssl);
        $this->initSessionCallback($session);
        return $session;
    }

    public function getLastTab($userId): string
    {
        $model = $this->getDataModel();
        return $model->getLastTab($userId);
    }


    protected function successfulLoginCallback(int $userId, string $username)
    {
    }

    public function getDbDriver(): string
    {
        return $this->dbDriver;
    }

    public function getDatabase(?string $name = null): string
    {
        if (isset($this->database)) {
            return $this->database;
        }
        $this->database = $this->config->getDbParameters(ConnectionType::READ)->database;
        return $this->database;
    }

    public function getDbParameters(ConnectionType $type, ?string $name = null): Parameters
    {
        switch ($type) {
            case ConnectionType::LOGIN:
                if (!isset($this->db_parameters_login)) {
                    $this->db_parameters_login = $this->config->getDbParameters(ConnectionType::LOGIN);
                }
                break;
            case ConnectionType::READ:
                if (!isset($this->db_parameters_read)) {
                    $this->db_parameters_read = $this->config->getDbParameters(ConnectionType::READ);
                }
                break;
            case ConnectionType::WRITE:
                if (!isset($this->db_parameters_write)) {
                    $this->db_parameters_write = $this->config->getDbParameters(ConnectionType::WRITE);
                }
                break;
            case ConnectionType::ADMIN:
                if (!isset($this->db_parameters_admin)) {
                    $this->db_parameters_admin = $this->config->getDbParameters(ConnectionType::ADMIN);
                }
                break;
        }
        return match ($type) {
            ConnectionType::LOGIN => $this->db_parameters_login ?? $this->config->getDbParameters(ConnectionType::LOGIN),
            ConnectionType::READ  => $this->db_parameters_read ?? $this->config->getDbParameters(ConnectionType::READ),
            ConnectionType::WRITE => $this->db_parameters_write ?? $this->config->getDbParameters(ConnectionType::WRITE),
            ConnectionType::ADMIN => $this->db_parameters_admin ?? $this->config->getDbParameters(ConnectionType::ADMIN)
        };
    }

    public function getLdapHost(): string
    {
        return $this->ldap_host ?? '';
    }

    /**
     * print the site base container including all relevant java scripts and css
     * the dhtmlx content will be attached to the tabbar_init div
     */
    public function printSiteBaseContainer(): void
    {
        $result = [
            '<!DOCTYPE html>',
            '<html>',
            '<head>',
            '<meta http-equiv="X-UA-Compatible" content="IE=edge">',
            '<meta charset="utf-8">',
            '<title>'.$this->application_name.'</title>',
            '<link rel="SHORTCUT ICON" href="'.$this->favicon.'">'
        ];

        array_push($result, ...$this->addDhtmlxCSSTagsToHtmlHead());
        array_push($result, ...$this->addApplicationCSSTagsToHtmlHead());
        array_push($result, ...$this->addCssTagsToHtmlHead());

        array_push($result, ...$this->addDhtmlxJSTagsToHtmlHead());
        array_push($result, ...$this->addApplicationJSTagsToHtmlHead());
        array_push($result, ...$this->addJavascriptTagsToHtmlHead());
        $result[] = '</head>';
        $result[] = '<body>';
        array_push($result, ...$this->getApplicationHeader());
        $result[] = '<div id="tabbar_init"></div>';
        array_push($result, ...$this->getApplicationFooter());
        $result[] = '</body>';
        $result[] = '</html>';
        $result   = array_filter($result);
        $pretty   = false;
        if ($pretty === true) {
            $indent = 0;
            $spaces = 3;
            foreach ($result as &$tag) {
                switch ($tag) {
                    case '<html>':
                    case '<head>':
                    case '<body>':
                        $tag = str_repeat(' ', $indent * $spaces).$tag;
                        $indent++;
                        break;
                    case '</html>':
                    case '</head>':
                    case '</body>':
                        $indent--;
                        $tag = str_repeat(' ', $indent * $spaces).$tag;
                        break;
                    default:
                        $tag = str_repeat(' ', $indent * $spaces).$tag;
                        break;
                }
            }
            $GLOBALS['output_buffer'] = ob_get_clean();
            print implode("\n", $result);
        } else {
            $GLOBALS['output_buffer'] = ob_get_clean();
            print implode('', array_map('trim', $result));
        }
    }

    /**
     * the header which appears above the dhtmlx container
     * logout and locale selection are defined here as well
     * @return array
     */
    public function getApplicationHeader(): array
    {
        if (method_exists($this, 'getSiteBaseHeader')) {
            trigger_error('getSiteBaseHeader is deprecated. Use getApplicationHeader instead and return an array');
            $result = $this->getSiteBaseHeader();
            if (!is_array($result)) {
                return [$result];
            }
            return $result;
        }
        $class = match ($this->environment) {
            Config::DEVELOPMENT => 'header_dev',
            Config::TESTING     => 'header_test',
            default             => 'header',
        };

        $site_base_header[] = '<div id="header" class="'.$class.'">';
        $site_base_header[] = '   <div id="user_actions">';
        $site_base_header[] = '      <div id="locale"></div>';
        $site_base_header[] = '      <div id="logout"><a href="?action='.$this->logoffButtonName.'">'.Locale::get('byteShard.basecontainer.button.logout').'</a></div>';
        $site_base_header[] = '   </div>';
        $site_base_header[] = '</div>';
        return $site_base_header;
    }

    /**
     * the footer which appears below the dhtmlx container
     */
    public function getApplicationFooter(): array
    {
        if (method_exists($this, 'getSiteBaseFooter')) {
            trigger_error('getSiteBaseFooter is deprecated. Use getApplicationFooter instead and return an array');
            $result = $this->getSiteBaseFooter();
            if (!is_array($result)) {
                return [$result];
            }
            return $result;
        }
        $class = match ($this->environment) {
            Config::DEVELOPMENT => 'footer_dev',
            Config::TESTING     => 'footer_test',
            default             => 'footer',
        };
        return [
            '<div id="footer" class="'.$class.'"><span id="footer_left">'.$this->leftFooterText.'</span><span id="footer_right">powered by byteShard Framework &copy; Bespin Studios</span></div>'
        ];
    }

    public function includeJavascripts(array $arrayOfFilenames, string $scriptSubDirectory = '', string $target = 'app'): string
    {
        trigger_error(__METHOD__.' is deprecated. Please use getJavascripts() which returns an array');
        return implode('', $this->getJavascripts($arrayOfFilenames, $scriptSubDirectory, $target));
    }

    public function getJavascripts(array $files, string $scriptSubDirectory = '', string $target = 'app'): array
    {
        $js = new Javascript($this->jsPath);
        return $js->includeJavascripts($files, $scriptSubDirectory, $target);
    }

    public function includeCss(array $files, string $cssSubDirectory = '', string $target = 'app'): string
    {
        trigger_error(__METHOD__.' is deprecated. Please use getCss() which returns an array');
        return implode('', $this->getCss($files, $cssSubDirectory, $target));
    }

    public function getCss(array $files, string $cssSubDirectory = '', string $target = 'app'): array
    {
        $css = new Css($this->cssPath);
        return $css->includeCss($files, $cssSubDirectory, $target);
    }

    public function getAppName(): string
    {
        return $this->application_name;
    }

    private function initializeFrameworkGlobals($logLevel = Enum\LogLevel::CRITICAL, $context = ''): void
    {
        $this->sslRedirect();

        $this->initializeLogConstants($logLevel);

        //TODO: PHPSELFDIR is deprecated. use Server::getBaseUrl() instead
        //define('PHPSELFDIR', Server::getBaseUrl());

        $this->initializeByteShardConstants();

        $this->initializeDebugConstants();

        $this->initializeDirectoryConstants($context);
    }

    /**
     * checks if the last request was ssl encrypted. Redirects to https otherwise
     */
    private function sslRedirect(): void
    {
        if ($this->require_ssl === true && Server::getProtocol() !== 'https' && php_sapi_name() !== 'cli') {
            if (!($_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1' || $_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.0')) {
                $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            }
            header($_SERVER['SERVER_PROTOCOL'].' 301 Moved Permanently');
            header('Location: https://'.Server::getHost().$_SERVER['REQUEST_URI']);
            exit();
        }
    }

    private function initializeByteShardConstants(): void
    {
        define('ID_SEPARATOR', '_');
        define('MAIN', $this->main);
    }

    /**
     * sets all log related constants
     */
    private function initializeLogConstants($logLevel = Enum\LogLevel::CRITICAL): void
    {
        define('LOGLEVEL', $logLevel);
        define('DISCLOSE_CREDENTIALS', false);
        if ($this->logDir !== null) {
            ini_set('error_log', $this->logDir.$this->errorLog);
        } else {
            ini_set('error_log', BS_FILE_PRIVATE_ROOT.DIRECTORY_SEPARATOR.$this->defaultLogDir.DIRECTORY_SEPARATOR.$this->errorLog);
        }

        // display all errors, warnings and notices. They will be rerouted by the error handler
        // nothing will be displayed in the client
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    }

    /**
     * sets all debug related constants
     */
    private function initializeDebugConstants(): void
    {
        define('DEBUG', $this->debug);

        if ($this->debug === true) {
            define('DEBUG_LOCALE', $this->debug_locale);
            define('DEBUG_LOCALE_TOKEN', $this->debug_locale_token);
        } else {
            define('DEBUG_LOCALE', false);
            define('DEBUG_LOCALE_TOKEN', false);
        }
    }

    /**
     * sets all directory related constants
     */
    private function initializeDirectoryConstants($context): void
    {
        //BS_FILE_PUBLIC_ROOT defined in index.php
        define('BS_FILE_PUBLIC_FRAMEWORK', BS_FILE_PUBLIC_ROOT.'/bs');
        define('BS_FILE_PUBLIC_APP', BS_FILE_PUBLIC_ROOT.'/app');

        //BS_FILE_PRIVATE_ROOT defined in InitByteShard
        if (defined('BS_FILE_PRIVATE_BYTESHARD') === false) {
            //BS_FILE_PRIVATE_BYTESHARD might be defined in the ajax endpoints
            define('BS_FILE_PRIVATE_BYTESHARD', BS_FILE_PRIVATE_ROOT.DIRECTORY_SEPARATOR.'byteShard');
        }
        define('BS_FILE_PRIVATE_LIB', BS_FILE_PRIVATE_ROOT.DIRECTORY_SEPARATOR.$this->libPath);
        define('BS_FILE_PRIVATE_APP', BS_FILE_PRIVATE_ROOT.DIRECTORY_SEPARATOR.'application');
        define('BS_FILE_PRIVATE_UPLOAD', BS_FILE_PRIVATE_ROOT.DIRECTORY_SEPARATOR.$this->uploadDir);
        define('BS_FILE_PRIVATE_LOG', BS_FILE_PRIVATE_ROOT.DIRECTORY_SEPARATOR.'log');

        define('BS_WEB_ROOT_DIR', rtrim(Server::getProtocol().'://'.Server::getHost().'/'.trim($context, '/'), '/'));
        define('BS_WEB_APP_DIR', BS_WEB_ROOT_DIR.'/app');
        define('BS_WEB_FRAMEWORK_DIR', BS_WEB_ROOT_DIR.'/bs');
        define('BS_WEB_UPLOAD_DIR', BS_WEB_ROOT_DIR.'/'.$this->uploadDir);
        /* EXAMPLE -> MOVE TO DOCUMENTATION
        BS_FILE_PUBLIC_ROOT       /srv/www/htdocs/<APPNAME>
        BS_FILE_PUBLIC_FRAMEWORK  /srv/www/htdocs/<APPNAME>/bs
        BS_FILE_PUBLIC_APP        /srv/www/htdocs/<APPNAME>/app

        BS_FILE_PRIVATE_ROOT      /srv/<APPNAME>
        BS_FILE_PRIVATE_FRAMEWORK /srv/<APPNAME>/byteShard
        BS_FILE_PRIVATE_LIB       /srv/<APPNAME>/lib
        BS_FILE_PRIVATE_APP       /srv/<APPNAME>/application
        BS_FILE_PRIVATE_UPLOAD    /srv/<APPNAME>/upload
        BS_FILE_PRIVATE_LOG       /srv/<APPNAME>/log

        BS_WEB_ROOT_DIR:      https://www.<APPNAME>.com/sub/
        BS_WEB_APP_DIR:       https://www.<APPNAME>.com/sub/app/
        BS_WEB_FRAMEWORK_DIR: https://www.<APPNAME>.com/sub/bs/
        BS_WEB_UPLOAD_DIR:    https://www.<APPNAME>.com/sub/upload/
        */
    }

    private function initializeFrameworkBeforeAuthentication(): void
    {
        // Komprimierung aktivieren
        // TODO: funktioniert nicht mit dem Error Handler buffer testen ob ob_end und ob_start hilft
        ini_set('zlib.output_compression', self::$zlib_output_compression);
        ini_set('zlib.output_compression_level', self::$zlib_output_compression_level);
    }

    public function __debugInfo(): array
    {
        if (defined('DISCLOSE_CREDENTIALS') && DISCLOSE_CREDENTIALS === true && defined('LOGLEVEL') && LOGLEVEL === LogLevel::DEBUG) {
            return get_object_vars($this);
        }
        $debugInfo              = get_object_vars($this);
        $debugInfo['ldap_pass'] = $this->ldap_pass === null ? '' : 'CONFIDENTIAL';
        return $debugInfo;
    }

    public function jsonSerialize(): mixed
    {
        return $this->__debugInfo();
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getApplicationRoot(): ?ApplicationRootInterface
    {
        trigger_error('add '.__METHOD__.' to your \App\Config\ByteShard config and initialize your ApplicationRoot, e.g. with new '.TabBar::class, E_USER_DEPRECATED);
        return null;
    }
}
