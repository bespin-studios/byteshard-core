<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Locale;

use byteShard\Locale;

/**
 * Class en
 * @package byteShard\Internal\Locale
 *
 * contains all texts for the framework in english (generic)
 * Don't use the same token over and over again.
 * Make specific tokens for every single message and rather refer to the default message
 * That way in debug mode the token can be returned and is easy to find
 *
 * prepend an array dimension named "debug" for a message that will be displayed in the browser while debug is true
 * e.g.:
 * self::$locale['test'] = 'something happened';
 * self::$locale['debug']['test'] = "the coder didn't specify xyz";
 */
class en extends Locale
{
    protected static string $locale_name = 'EN';

    private string $default_message = 'An unknown error occurred';

    protected function get_utils_locale(): void
    {
        self::$locale['string']['vksprintf'] = 'UNDEFINED';
    }

    protected function get_exception_locale(): void
    {
        self::$locale['exception']['undefinedLocaleToken']                         = 'An error occurred';
        self::$locale['invalidArgumentException']['undefinedLocaleToken']          = 'An error occurred';
        self::$locale['logicException']['undefinedLocaleToken']                    = 'An error occurred';
        self::$locale['uploadException']['undefinedLocaleToken']                   = 'An error occurred during upload';
        self::$locale['debug']['exception']['undefinedLocaleToken']                = 'No debug message defined for exception';
        self::$locale['debug']['invalidArgumentException']['undefinedLocaleToken'] = 'No debug message defined for invalid argument exception';
        self::$locale['debug']['logicException']['undefinedLocaleToken']           = 'No debug message defined for logic exception';
        self::$locale['debug']['uploadException']['undefinedLocaleToken']          = 'No debug message defined for upload exception';
    }

    protected function get_generic_locale(): void
    {
        self::$locale['error'] = 'An error occurred';
    }

    protected function get_environment_locale(): void
    {
        self::$locale['tab']['label']['noPermission']       = 'No permission';
        self::$locale['cell']['label']['noPermission']      = "You don't have the permission to access %s";
        self::$locale['debug']['session']['notFound']       = 'Session object not found in SESSION[MAIN]';
        self::$locale['debug']['authenticate']['no_action'] = 'AuthenticationResult has no action specified';
    }

    protected function get_Internal_Permission_NoApplicationPermissionError_locale(): void
    {
        self::$locale['Tab']['Label']       = 'No permission';
        self::$locale['Cell']['a']['Label'] = 'No permission';
    }

    protected function get_session_locale(): void
    {
        self::$locale['tab']['label']['noPermission'] = 'No permission';
    }

    protected function get_eventHandler_locale(): void
    {
        self::$locale['byteShard']['notfound']            = $this->default_message;
        self::$locale['byteShard']['tab']['notfound']     = 'Interner Fehler: unbekannter Tab';
        self::$locale['id']['invalid']                    = 'Interner Fehler: ungültiger Schlüssel';
        self::$locale['gridEdit']['token']['invalid']     = $this->default_message;
        self::$locale['gridEdit']['token']['notFound']    = $this->default_message;
        self::$locale['gridEdit']['generic']              = $this->default_message;
        self::$locale['gridEdit']['class']['wrongParent'] = $this->default_message;
        self::$locale['gridEdit']['class']['notFound']    = $this->default_message;
    }

    protected function get_clientDataProcessor_locale(): void
    {
        self::$locale['decryptClientValue']['invalidValueDecodingNotAllowed'] = 'You must select an existing option in the combo "%LABEL$s"';
    }

    protected function get_login_locale(): void
    {
        self::$locale['password']                       = 'password:';
        self::$locale['user']                           = 'name:';
        self::$locale['login']                          = 'login';
        self::$locale['forgot']                         = 'forgot password';
        self::$locale['dbSchemaNotInstanceOfUserTable'] = 'Login: forgot password';
        self::$locale['checkStringLength']['failed']    = 'Login: forgot password';
        self::$locale['checkUsernamePattern']['failed'] = 'Login: forgot password';
        self::$locale['userId']['notFound']             = 'Login: User ID not found';
        self::$locale['serviceMode']['accessDenied']    = 'Service mode activated and user not service mode member';
    }

    protected function get_form_locale(): void
    {
        self::$locale['cell']['Label']['noPermission']['Label']                                    = "You don't have the permission to access this cell";
        self::$locale['formObject']['proxy']['setUploadUrlType']['duplicate_declaration']          = 'An internal error occurred';
        self::$locale['debug']['formObject']['proxy']['setUploadUrlType']['duplicate_declaration'] = 'GET attribute "type" already declared in BSFormUpload URL. Type is reserved by the byteShard framework and is automatically set.';
        self::$locale['debug']['update']['no_permission']                                          = 'You lack the permission to update content of this cell';
        self::$locale['update']['no_permission']                                                   = 'update failed';
        self::$locale['validation']['invalidArgument']['setMinLength']['minLength']                = 'An internal error occurred';
        self::$locale['validation']['invalidArgument']['setMaxLength']['maxLength']                = 'An internal error occurred';
        self::$locale['validation']['invalidArgument']['setEnumType']['enumClassName']             = 'An internal error occurred';
        self::$locale['debug']['validation']['invalidArgument']['setMinLength']['minLength']       = 'invalid type for parameter $minLength';
        self::$locale['debug']['validation']['invalidArgument']['setMaxLength']['maxLength']       = 'invalid type for parameter $maxLength';
        self::$locale['debug']['validation']['invalidArgument']['setEnumType']['enumClassName']    = 'invalid type for parameter $enumClassName';
        self::$locale['invalidArgument']['setAutoHeight']['delta']                                 = 'An internal error occurred';
        self::$locale['invalidArgument']['setAutoWidth']['delta']                                  = 'An internal error occurred';
        self::$locale['invalidArgument']['setBlockOffset']['int']                                  = 'An internal error occurred';
        self::$locale['invalidArgument']['setCalendarPosition']['enumOfCalendarPosition']          = 'An internal error occurred';
        self::$locale['invalidArgument']['setChecked']['bool']                                     = 'An internal error occurred';
        self::$locale['invalidArgument']['setClassName']['string']                                 = 'An internal error occurred';
        self::$locale['invalidArgument']['setCssName']['string']                                   = 'An internal error occurred';
        self::$locale['invalidArgument']['setDateFormat']['string']                                = 'An internal error occurred';
        self::$locale['invalidArgument']['setDisabled']['bool']                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setEnableTime']['bool']                                  = 'An internal error occurred';
        self::$locale['invalidArgument']['setFiltering']['bool']                                   = 'An internal error occurred';
        self::$locale['invalidArgument']['setAccessType']['accessType']                            = 'An internal error occurred';
        self::$locale['invalidArgument']['setDBColumnType']['enum_DB_ColumnType']                  = 'An internal error occurred';
        self::$locale['invalidArgument']['setHidden']['bool']                                      = 'An internal error occurred';
        self::$locale['invalidArgument']['setInfo']['bool']                                        = 'An internal error occurred';
        self::$locale['invalidArgument']['setInputHeight']['int']                                  = 'An internal error occurred';
        self::$locale['invalidArgument']['setInputLeft']['int']                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setInputTop']['int']                                     = 'An internal error occurred';
        self::$locale['invalidArgument']['setInputWidth']['int']                                   = 'An internal error occurred';
        self::$locale['invalidArgument']['setLabel']['string']                                     = 'An internal error occurred';
        self::$locale['invalidArgument']['setLabelAlign']['enumOfLabelAlign']                      = 'An internal error occurred';
        self::$locale['invalidArgument']['setLabelHeight']['int']                                  = 'An internal error occurred';
        self::$locale['invalidArgument']['setLabelLeft']['int']                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setLabelTop']['int']                                     = 'An internal error occurred';
        self::$locale['invalidArgument']['setLabelWidth']['int']                                   = 'An internal error occurred';
        self::$locale['invalidArgument']['setMaxLength']['int']                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setMinutesInterval']['enumOfMinutesInterval']            = 'An internal error occurred';
        self::$locale['invalidArgument']['setMode']['enumOfMode']                                  = 'An internal error occurred';
        self::$locale['invalidArgument']['setName']['string']                                      = 'An internal error occurred';
        self::$locale['invalidArgument']['setNote']['string']                                      = 'An internal error occurred';
        self::$locale['invalidArgument']['setNoteWidth']['int']                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setNumberFormat']['string']                              = 'An internal error occurred';
        self::$locale['invalidArgument']['setNumberFormatGroupSeparator']['string']                = 'An internal error occurred';
        self::$locale['invalidArgument']['setNumberFormatDecimalSeparator']['string']              = 'An internal error occurred';
        self::$locale['invalidArgument']['setOffset']['int']                                       = 'An internal error occurred';
        self::$locale['invalidArgument']['setOffsetLeft']['int']                                   = 'An internal error occurred';
        self::$locale['invalidArgument']['setOffsetTop']['int']                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setPosition']['enumOfLabelPosition']                     = 'An internal error occurred';
        self::$locale['invalidArgument']['setReadonly']['bool']                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setRequired']['bool']                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setRows']['int']                                         = 'An internal error occurred';
        self::$locale['invalidArgument']['setServerDateFormat']['string']                          = 'An internal error occurred';
        self::$locale['invalidArgument']['setShowWeeknumbers']['bool']                             = 'An internal error occurred';
        self::$locale['invalidArgument']['setStyle']['string']                                     = 'An internal error occurred';
        self::$locale['invalidArgument']['setTitleText']['string']                                 = 'An internal error occurred';
        self::$locale['invalidArgument']['setTooltip']['string']                                   = 'An internal error occurred';
        self::$locale['invalidArgument']['setUrl']['url']                                          = 'An internal error occurred';
        self::$locale['invalidArgument']['setUserdata']['keyValueArray']                           = 'An internal error occurred';
        self::$locale['invalidArgument']['setValue']['int']                                        = 'An internal error occurred';
        self::$locale['invalidArgument']['setWeekStart']['intOneToSeven']                          = 'An internal error occurred';
        self::$locale['invalidArgument']['setWidth']['int']                                        = 'An internal error occurred';
    }

    protected function get_upload_locale(): void
    {
        self::$locale['generic']['error']                           = 'An error occurred during upload';
        self::$locale['file']['size']['ini']                        = 'The selected file is too large';
        self::$locale['file']['size']['form']                       = 'The selected file is too large';
        self::$locale['file']['partial']                            = 'The selected file could not be uploaded completely. Please try again.';
        self::$locale['file']['notUploaded']                        = 'The selected file could not be uploaded';
        self::$locale['sanitizer']['fileName']['invalidCharacters'] = 'The filename has invalid characters';
        self::$locale['sanitizer']['fileName']['length']            = 'The filename is too long';
        self::$locale['sanitizer']['fileType']['invalid']           = 'This file type is not supported';
        self::$locale['sanitizer']['mimeType']['unidentified']      = 'This file type is not recognized';

        self::$locale['debug']['method']['undefined']                   = 'Method to process upload not declared in application';
        self::$locale['debug']['sanitizer']['error']                    = 'Sanitizer encountered an error';
        self::$locale['debug']['fileType']['notDefined']                = 'No allowed file types defined';
        self::$locale['debug']['type']['unsupported']                   = 'Unsupported upload type';
        self::$locale['debug']['type']['notFound']                      = 'No parameter "type" set in GET';
        self::$locale['debug']['files']['hashFileNotFound']             = 'unspecified index "file" in $_FILES';
        self::$locale['debug']['files']['hashErrorNotFound']            = 'unspecified index "error" in $_FILES';
        self::$locale['debug']['file']['size']['ini']                   = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        self::$locale['debug']['file']['size']['form']                  = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        self::$locale['debug']['file']['partial']                       = 'The uploaded file was only partially uploaded';
        self::$locale['debug']['file']['notUploaded']                   = 'No file was uploaded';
        self::$locale['debug']['file']['tempMissing']                   = 'Missing a temporary folder';
        self::$locale['debug']['file']['failedWrite']                   = 'Failed to write file to disk';
        self::$locale['debug']['file']['extension']                     = 'File upload stopped by extension';
        self::$locale['debug']['sanitizer']['mimeType']['invalid']['1'] = 'Mime types do not match (1)';
        self::$locale['debug']['sanitizer']['mimeType']['invalid']['2'] = 'Mime types do not match (2)';
        self::$locale['debug']['sanitizer']['mimeType']['invalid']['3'] = 'Mime types do not match (3)';
        self::$locale['debug']['sanitizer']['mimeType']['unidentified'] = 'Unidentified Mime type';
    }

    protected function get_toolbar_locale(): void
    {
        self::$locale['invalidArgument']['setDisabled']['bool']                                                = 'An internal error occurred';
        self::$locale['invalidArgument']['setImage']['string']                                                 = 'An internal error occurred';
        self::$locale['invalidArgument']['setImageDisabled']['string']                                         = 'An internal error occurred';
        self::$locale['invalidArgument']['setLength']['int']                                                   = 'An internal error occurred';
        self::$locale['invalidArgument']['setMaxOpen']['int']                                                  = 'An internal error occurred';
        self::$locale['invalidArgument']['setOpenAll']['bool']                                                 = 'An internal error occurred';
        self::$locale['invalidArgument']['setRenderSelect']['bool']                                            = 'An internal error occurred';
        self::$locale['invalidArgument']['setSelected']['bool']                                                = 'An internal error occurred';
        self::$locale['invalidArgument']['setText']['text']                                                    = 'An internal error occurred';
        self::$locale['invalidArgument']['setTextMax']['stringOrInt']                                          = 'An internal error occurred';
        self::$locale['invalidArgument']['setTextMin']['stringOrInt']                                          = 'An internal error occurred';
        self::$locale['invalidArgument']['setTooltip']['stringOrInt']                                          = 'An internal error occurred';
        self::$locale['invalidArgument']['setValue']['stringOrInt']                                            = 'An internal error occurred';
        self::$locale['invalidArgument']['setValueMax']['int']                                                 = 'An internal error occurred';
        self::$locale['invalidArgument']['setValueMin']['int']                                                 = 'An internal error occurred';
        self::$locale['invalidArgument']['setValueNow']['int']                                                 = 'An internal error occurred';
        self::$locale['invalidArgument']['setWidth']['int']                                                    = 'An internal error occurred';
        self::$locale['debug']['invalidArgument']['setDisabled']['bool']                                       = 'invalid type for parameter $bool in method setDisabled';
        self::$locale['debug']['invalidArgument']['setImage']['string']                                        = 'invalid type for parameter $string in method setImage';
        self::$locale['debug']['invalidArgument']['setImageDisabled']['string']                                = 'invalid type for parameter string in method setImageDisabled';
        self::$locale['debug']['invalidArgument']['setLength']['int']                                          = 'invalid type for parameter $int in method setLength';
        self::$locale['debug']['invalidArgument']['setMaxOpen']['int']                                         = 'invalid type for parameter $int in method setMaxOpen';
        self::$locale['debug']['invalidArgument']['setOpenAll']['bool']                                        = 'invalid type for parameter $bool in method setOpenAll';
        self::$locale['debug']['invalidArgument']['setRenderSelect']['bool']                                   = 'invalid type for parameter $bool in method setRenderSelect';
        self::$locale['debug']['invalidArgument']['setSelected']['bool']                                       = 'invalid type for parameter $bool in method setSelected';
        self::$locale['debug']['invalidArgument']['setText']['stringOrInt']                                    = 'invalid type for parameter $text in method setText';
        self::$locale['debug']['invalidArgument']['setTextMax']['stringOrInt']                                 = 'invalid type for parameter $stringOrInt in method setTextMax';
        self::$locale['debug']['invalidArgument']['setTextMin']['stringOrInt']                                 = 'invalid type for parameter $stringOrInt in method setTextMin';
        self::$locale['debug']['invalidArgument']['setTooltip']['stringOrInt']                                 = 'invalid type for parameter $stringOrInt in method setTooltip';
        self::$locale['debug']['invalidArgument']['setValue']['stringOrInt']                                   = 'invalid type for parameter $stringOrInt in method setValue';
        self::$locale['debug']['invalidArgument']['setValueMax']['int']                                        = 'invalid type for parameter $int in method setValueMax';
        self::$locale['debug']['invalidArgument']['setValueMin']['int']                                        = 'invalid type for parameter $int in method setValueMin';
        self::$locale['debug']['invalidArgument']['setValueNow']['int']                                        = 'invalid type for parameter $int in method setValueNow';
        self::$locale['debug']['invalidArgument']['setWidth']['int']                                           = 'invalid type for parameter $int in method setWidth';
        self::$locale['control']['twoStateButton']['state']['invalidArgument']['storeState']['state']          = 'An internal error occurred';
        self::$locale['control']['twoStateButton']['state']['logic']['storeState']['recordCount']              = 'An internal error occurred';
        self::$locale['debug']['control']['twoStateButton']['state']['invalidArgument']['storeState']['state'] = 'invalid type for parameter $state in method storeState';
    }

    protected function get_database_locale(): void
    {
        self::$locale['debug']['update']['connection_failed'] = 'database connection failed';
        self::$locale['update']['connection_failed']          = 'update failed';
    }

    protected function get_data_locale(): void
    {
        //Data class
        self::$locale['checkUnique']['not_unique']                      = 'At least one record exists with this value';
        self::$locale['checkReferences']['has_references']              = '';
        self::$locale['field_not_found']                                = 'An internal error occurred';
        self::$locale['invalidArgument']['setFields']['name']           = 'An internal error occurred';
        self::$locale['invalidArgument']['setUnique']['array']          = 'An internal error occurred';
        self::$locale['debug']['field_not_found']                       = 'Not all fields defined in \'defineUpdate\' found in ClientData. Field names: %s';
        self::$locale['debug']['invalidArgument']['setFields']['name']  = 'Invalid argument: Method setFields only accepts strings or numerics.';
        self::$locale['debug']['invalidArgument']['setUnique']['array'] = 'Invalid argument: Method setUnique only accepts strings or arrays.';
        //Data\Insert class
        self::$locale['insert']['failed']['label']                      = 'Error during creation';
        self::$locale['insert']['failed']['permission']                 = "You don't have permission to execute this function";
        self::$locale['insert']['failed']['table_not_defined']          = 'An internal error occurred';
        self::$locale['insert']['failed']['query']                      = 'An internal error occurred';
        self::$locale['insert']['failed']['insert']                     = 'An internal error occurred';
        self::$locale['debug']['insert']['failed']['table_not_defined'] = 'No table defined in Data\Insert. Method setTable needs to be called';
        //Data\Update class
        self::$locale['update']['failed']['label']                      = 'Error during update';
        self::$locale['update']['failed']['permission']                 = "You don't have permission to execute this function";
        self::$locale['update']['failed']['table_not_defined']          = 'An internal error occurred';
        self::$locale['update']['failed']['query']                      = 'An internal error occurred';
        self::$locale['update']['failed']['update']                     = 'An internal error occurred';
        self::$locale['invalidArgument']['useModifyLog']['bool']        = 'An internal error occurred';
        self::$locale['debug']['update']['failed']['table_not_defined'] = 'No table defined in Data\Update. Method setTable needs to be called';
        //Data\Archive class
        self::$locale['archive']['failed']['label'] = 'Error during archive';
    }

    protected function get_basecontainer_locale(): void
    {
        self::$locale['button']['logout'] = 'logout';
    }

    protected function get_errorHandler_locale(): void
    {
        self::$locale['shutdown']['autoload_failed']                = $this->default_message;
        self::$locale['debug']['shutdown']['autoload_failed']       = '%s';
        self::$locale['shutdown']['class_not_found']                = $this->default_message;
        self::$locale['debug']['shutdown']['class_not_found']       = '%s';
        self::$locale['print_cell_content']['no_message']           = $this->default_message;
        self::$locale['print_popup_content']['no_message']          = $this->default_message;
        self::$locale['debug']['print_cell_content']['no_message']  = 'An unknown error occurred and no message was passed to the error handler';
        self::$locale['debug']['print_popup_content']['no_message'] = 'An unknown error occurred and no message was passed to the error handler';
        self::$locale['popup_title']                                = 'Error';
        self::$locale['debug']['popup_title']                       = 'Error';
        self::$locale['debug']['error']                             = 'An error was triggered and transformed into an exception. More details can be found in the log file.';
        self::$locale['error']                                      = 'An error occurred';
        self::$locale['exception']                                  = 'An exception occurred';
        self::$locale['exception_db']                               = 'A database exception occurred';
        self::$locale['exception_upload']                           = 'An exception occurred during upload';
    }

    protected function get_validate_locale(): void
    {
        self::$locale['form']['field']                           = "Input validation in field '%s' failed: %s";
        self::$locale['grid']['column']                          = "Input validation in column '%s' failed: %s";
        self::$locale['rule']['min_length']                      = 'at least %s character';
        self::$locale['rule']['max_length']                      = 'only %s characters allowed';
        self::$locale['rule']['enum']                            = 'enum expected';
        self::$locale['rule']['valid_email']                     = 'valid email expected';
        self::$locale['type']['string']                          = 'string expected';
        self::$locale['type']['int']                             = 'number expected';
        self::$locale['type']['tinyint']                         = 'number expected';
        self::$locale['type']['bit']                             = 'boolean expected';
        self::$locale['type']['bigint_date']                     = 'date expected';
        self::$locale['type']['date']                            = 'date expected';
        self::$locale['type']['datetime']                        = 'date/time expected';
        self::$locale['type']['datetime_create_failed']          = 'date expected';
        self::$locale['debug']['type']['datetime_create_failed'] = "Couldn't create DateTime::createFromFormat";
        self::$locale['type']['id']                              = 'id expected';
    }

    protected function get_popup_locale(): void
    {
        self::$locale['debug']['getNavigationArray']['no_layout'] = 'No Layout has been attached to this popup';
        self::$locale['getNavigationArray']['no_layout']          = 'An internal error occurred';
        self::$locale['message']['error']                         = 'Error';
        self::$locale['message']['warning']                       = 'Warning';
        self::$locale['message']['notice']                        = 'Notice';
        self::$locale['message']['image']['error']                = 'bs/img/bs/error.png';
        self::$locale['message']['image']['notice']               = 'bs/img/bs/notice.png';
        self::$locale['message']['image']['warning']              = 'bs/img/bs/alert.png';
        self::$locale['message']['button']['ok']                  = 'Ok';
        self::$locale['message']['noMessageDefined']              = 'An error occurred';
        self::$locale['debug']['message']['noMessageDefined']     = 'No message defined. Token: %token$s';
        self::$locale['confirmation']['button']['proceed']        = 'Ok';
        self::$locale['confirmation']['button']['cancel']         = 'Cancel';
    }

    protected function get_bs_export_locale(): void
    {
        self::$locale['default_filename']                 = 'Export';
        self::$locale['error']                            = 'An error occurred';
        self::$locale['timeout']                          = 'Timeout';
        self::$locale['undefined_export_type']            = 'An error occurred during download';
        self::$locale['undefined_export_action']          = 'An error occurred during download';
        self::$locale['debug']['undefined_export_type']   = 'export_type is: %s';
        self::$locale['debug']['undefined_export_action'] = 'Undefined export action: %s';
    }

    protected function get_action_locale(): void
    {
        self::$locale['confirmAction']['noMessageDefined']                                                 = 'An error occurred';
        self::$locale['debug']['confirmAction']['noMessageDefined']                                        = 'No confirmation message defined. Token: %token$s';
        self::$locale['confirmAction']['invalidArgument']['showConfirmationDialogue']['bool']              = 'An internal error occurred';
        self::$locale['debug']['confirmAction']['invalidArgument']['showConfirmationDialogue']['bool']     = 'invalid type for parameter $bool in method showConfirmationDialogue';
        self::$locale['confirmAction']['invalidArgument']['setLocaleReplacements']['arrayObject']          = 'An internal error occurred';
        self::$locale['debug']['confirmAction']['invalidArgument']['setLocaleReplacements']['arrayObject'] = 'invalid type for parameter $replacements in method setLocaleReplacements';
        self::$locale['customExport']['invalidArgument']['__construct']['type']                            = 'An error occurred';
        self::$locale['debug']['customExport']['invalidArgument']['__construct']['type']                   = 'invalid type for parameter $type in __construct';
        self::$locale['custom_export']['default_name']                                                     = 'Export';
        self::$locale['reloadCell']['invalidArgument']['__construct']['cells']                             = 'An error occurred';
        self::$locale['setSelectedID']['invalidArgument']['__construct']['cells']                          = 'An error occurred';
        self::$locale['getCellData']['invalid_token']                                                      = 'An error occurred. Please reload the application and try again';
        self::$locale['debug']['reloadCell']['invalidArgument']['__construct']['cells']                    = 'Parameter for Action\ReloadCell must be string or byteShard\Cell or byteShard\Internal\CellContent';
        self::$locale['debug']['setSelectedID']['invalidArgument']['__construct']['cells']                 = 'Parameter for Action\SetSelectedID must be string or byteShard\Cell or byteShard\Internal\CellContent';
    }

    protected function get_cell_locale(): void
    {
        self::$locale['invalidArgument']['setWidth']['int']                   = 'An internal error occurred';
        self::$locale['debug']['invalidArgument']['setWidth']['int']          = 'invalid type for parameter $int in method setWidth';
        self::$locale['invalidArgument']['setWidthOnResize']['int']           = 'An internal error occurred';
        self::$locale['debug']['invalidArgument']['setWidthOnResize']['int']  = 'invalid type for parameter $int in method setWidthOnResize';
        self::$locale['invalidArgument']['setHeight']['int']                  = 'An internal error occurred';
        self::$locale['debug']['invalidArgument']['setHeight']['int']         = 'invalid type for parameter $int in method setHeight';
        self::$locale['invalidArgument']['setHeightOnResize']['int']          = 'An internal error occurred';
        self::$locale['debug']['invalidArgument']['setHeightOnResize']['int'] = 'invalid type for parameter $int in method setHeightOnResize';
    }

    protected function get_cellContent_locale(): void
    {
        self::$locale['generic']                                = $this->default_message;
        self::$locale['no_failed_validation_messages']          = 'Please validate your input';
        self::$locale['debug']['no_failed_validation_messages'] = 'At least one ValidationFailed, but no message defined';
        self::$locale['unexpected_client_data']                 = $this->default_message;
        self::$locale['debug']['unexpected_client_data']        = 'The parameter is neither of type Struct\ClientData nor Struct\ValidationFailed';
        self::$locale['unexpected_return_value']                = $this->default_message;
        self::$locale['debug']['unexpected_return_value']       = 'defineUpdate must return either an array, an array with actions or an action';
        self::$locale['permission']                             = 'You do not have the permission to edit this data';
        self::$locale['undefined_method']                       = $this->default_message;
        self::$locale['debug']['undefined_method']              = 'No update defined for this cell (undefined method: defineUpdate)';
    }

    protected function get_date_locale(): void
    {
        /**
         * .client is the format that will be displayed in the client to the user (dhx formatting)
         * .server is the format that will be returned to the server (dhx formatting)
         * .object is the format that will be used by php DateTime::createFromFormat
         */
        self::$locale['grid']['date_time']['client'] = 'm/d/Y H:i:s';
        self::$locale['grid']['date_time']['server'] = 'm/d/Y H:i:s';
        self::$locale['grid']['date_time']['object'] = 'm/d/Y H:i:s';
        self::$locale['grid']['date']['client']      = 'm/d/Y';
        self::$locale['grid']['date']['server']      = 'm/d/Y';
        self::$locale['grid']['date']['object']      = 'm/d/Y|';
        self::$locale['grid']['time']['client']      = 'H:i:s';
        self::$locale['grid']['time']['server']      = 'H:i:s';
        self::$locale['grid']['time']['object']      = 'H:i:s';
        self::$locale['form']['date_time']['client'] = '%d.%m.%Y %H:%i:%s';
        self::$locale['form']['date_time']['server'] = '%Y-%m-%d %H:%i:%s';
        self::$locale['form']['date_time']['object'] = 'Y-m-d H:i:s';
        self::$locale['form']['date']['client']      = '%d.%m.%Y';
        self::$locale['form']['date']['server']      = '%Y-%m-%d';
        self::$locale['form']['date']['object']      = 'Y-m-d|';
        self::$locale['form']['time']['client']      = '%H:%i:%s';
        self::$locale['form']['time']['server']      = '%H:%i:%s';
        self::$locale['form']['time']['object']      = 'H:i:s';

        self::$locale['dateTime']['grid'] = 'm/d/Y H:i:s';
        self::$locale['date']['grid']     = 'd.m.Y';
        self::$locale['time']['grid']     = 'H:i:s';

        self::$locale['month'][1]  = 'January';
        self::$locale['month'][2]  = 'February';
        self::$locale['month'][3]  = 'March';
        self::$locale['month'][4]  = 'April';
        self::$locale['month'][5]  = 'May';
        self::$locale['month'][6]  = 'June';
        self::$locale['month'][7]  = 'July';
        self::$locale['month'][8]  = 'August';
        self::$locale['month'][9]  = 'September';
        self::$locale['month'][10] = 'October';
        self::$locale['month'][11] = 'November';
        self::$locale['month'][12] = 'December';
        self::$locale['day'][1]    = 'Monday';
        self::$locale['day'][2]    = 'Tuesday';
        self::$locale['day'][3]    = 'Wednesday';
        self::$locale['day'][4]    = 'Thursday';
        self::$locale['day'][5]    = 'Friday';
        self::$locale['day'][6]    = 'Saturday';
        self::$locale['day'][7]    = 'Sunday';
    }
}
