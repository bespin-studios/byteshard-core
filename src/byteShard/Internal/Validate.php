<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal;

use byteShard\Enum;
use byteShard\Exception;
use byteShard\Form\Control\Calendar;
use byteShard\Form\Control\Combo;
use byteShard\Locale;
use stdClass;

/**
 * Class Validate
 * @package byteShard\Internal
 */
class Validate
{
    /**
     * The value is validated by the fieldType and cast to that type if necessary.
     * If any additional validations are passed, they will be taken into account as well
     * @throws Exception
     * @throws \Exception
     */
    final public static function validate(string|int|float|bool|null|DateTime &$value, ?string $fieldType = null, array $validationArray = [], ?string $dateFormat = null): stdClass
    {
        $stringValue                         = strval($value);
        $validationResult                    = new stdClass();
        $validationResult->validationsFailed = 0;

        foreach ($validationArray as $validationRule => $validationValue) {
            if (Enum\Validation::is_enum($validationRule)) {
                switch ($validationRule) {
                    case Enum\Validation::MIN_LENGTH:
                        if (strlen($stringValue) < $validationValue) {
                            $validationResult->failedRules[$validationRule] = sprintf(Locale::get('byteShard.validate.rule.min_length'), $validationValue);
                            $validationResult->validationsFailed++;
                        }
                        break;
                    case Enum\Validation::MAX_LENGTH:
                        if (strlen($stringValue) > $validationValue) {
                            $validationResult->failedRules[$validationRule] = sprintf(Locale::get('byteShard.validate.rule.max_length'), $validationValue);
                            $validationResult->validationsFailed++;
                        }
                        break;
                    case Enum\Validation::ENUM:
                        /* @var Enum\Enum $validationValue */
                        if (!$validationValue::is_enum($value)) {
                            $validationResult->failedRules[$validationRule] = Locale::get('byteShard.validate.rule.enum');
                            $validationResult->validationsFailed++;
                        }
                        break;
                    case Enum\Validation::VALID_EMAIL:
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $validationResult->failedRules[$validationRule] = Locale::get('byteShard.validate.rule.valid_email');
                            $validationResult->validationsFailed++;
                        }
                        break;
                    //TODO: implement validations
                    case Enum\Validation::IS_EMPTY:
                    case Enum\Validation::NOT_EMPTY:
                    case Enum\Validation::VALID_ALPHA_NUMERIC:
                    case Enum\Validation::VALID_BOOLEAN:
                    case Enum\Validation::VALID_CURRENCY:
                    case Enum\Validation::VALID_DATE:
                    case Enum\Validation::VALID_DATETIME:
                    case Enum\Validation::VALID_INTEGER:
                    case Enum\Validation::VALID_IP_V4:
                    case Enum\Validation::VALID_NUMERIC:
                    case Enum\Validation::VALID_SIN:
                    case Enum\Validation::VALID_SSN:
                    case Enum\Validation::VALID_TEXT:
                        break;
                    default:
                        //TODO: throw exception
                        break;
                }
            }
        }
        $fieldType = Enum\DB\ColumnType::tryFrom($fieldType);
        if ($fieldType !== null) {
            switch ($fieldType) {
                case Enum\DB\ColumnType::VARCHAR:
                    if (!is_string($value)) {
                        $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.string');
                        $validationResult->validationsFailed++;
                    }
                    break;
                case Enum\DB\ColumnType::INT;
                case Enum\DB\ColumnType::INTEGER;
                case Enum\DB\ColumnType::BIGINT;
                    if (!(is_int($value) || ctype_digit($value))) {
                        $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.int');
                        $validationResult->validationsFailed++;
                    } else {
                        $value = (int)$value;
                    }
                    break;
                case Enum\DB\ColumnType::TINYINT;
                    if (is_numeric($value) && !($value < 255 && (is_int($value) || ctype_digit($value)))) {
                        $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.tinyint');
                        $validationResult->validationsFailed++;
                    } else {
                        $value = (int)$value;
                    }
                    break;
                case Enum\DB\ColumnType::BOOLEAN;
                    if (!(is_bool($value) || (ctype_digit($value) && ((int)$value === 1 || (int)$value === 0)))) {
                        $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.bit');
                        $validationResult->validationsFailed++;
                    } elseif (!is_bool($value)) {
                        $value = ((int)$value === 1);
                    }
                    break;
                case Enum\DB\ColumnType::BIGINT_DATE:
                    $tmp = str_replace(' ', '', str_replace('-', '', str_replace('/', '', str_replace(':', '', str_replace('.', '', $stringValue)))));
                    if (!ctype_digit($tmp)) {
                        $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.bigint_date');
                        $validationResult->validationsFailed++;
                    } else {
                        //Todo: Client Date Format in die Session schreiben und hier auswerten!
                        $dat   = new DateTime($stringValue);
                        $value = $dat->getBigintDateTime();
                    }
                    break;
                case Enum\DB\ColumnType::DATE;
                    if (!empty($value)) {
                        //Locale::get('byteShard.validate.type.date');
                        if (!ctype_digit(str_replace(' ', '', str_replace('-', '', str_replace('/', '', str_replace(':', '', str_replace('.', '', $stringValue))))))) {
                            $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.date');
                            $validationResult->validationsFailed++;
                        } else {
                            /* @var $_SESSION [MAIN] Session */
                            if ($dateFormat !== null) {
                                print "DateFormat: ".$dateFormat."\n<br>";
                                print "ClientData: ".$value."\n<br>";
                                //if ($_SESSION[MAIN] instanceof Session) {
                                //$_SESSION[MAIN]->getClientDateTimeFormat()
                                $helper = DateTime::createFromFormat($dateFormat, $stringValue, $_SESSION[MAIN]->getClientTimeZone());

                                //$value = DateTime::createFromFormat($date_format, $value, $_SESSION[MAIN]->getClientTimeZone());

                                if ($helper === false) {
                                    $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.datetime_create_failed');
                                    $validationResult->validationsFailed++;
                                } else {
                                    $value = DateTime::createFromFormat('Y-m-d H:i:s.u', $helper->format('Y-m-d'.' 00:00:00.000000'));
                                }
                            } else {
                                throw new Exception('date_format missing');
                            }
                        }
                    } else {
                        $value = null;
                    }
                    break;
                case Enum\DB\ColumnType::SMALLDATETIME:
                case Enum\DB\ColumnType::DATETIME;
                case Enum\DB\ColumnType::DATETIME2;
                    if (!empty($value)) {
                        if (!ctype_digit(str_replace(' ', '', str_replace('-', '', str_replace('/', '', str_replace(':', '', str_replace('.', '', $stringValue))))))) {
                            $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.datetime');
                            $validationResult->validationsFailed++;
                        } else {
                            /* @var $_SESSION [MAIN] Session */
                            if ($dateFormat !== null) {
                                //if ($_SESSION[MAIN] instanceof Session) {
                                //$_SESSION[MAIN]->getClientDateTimeFormat()
                                $value = DateTime::createFromFormat($dateFormat, $stringValue, $_SESSION[MAIN]->getClientTimeZone());
                                if ($value === false) {
                                    $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.datetime_create_failed');
                                    $validationResult->validationsFailed++;
                                }
                            } else {
                                throw new Exception('date_format missing');
                            }
                        }
                    } else {
                        $value = null;
                    }
                    break;
                case Combo::class:
                case Calendar::class:
                    break;
                default:
                    print_r(debug_backtrace());
                    var_dump($value);
                    if (!is_numeric($value)) {
                        $validationResult->failedRules['typeMismatch'] = Locale::get('byteShard.validate.type.id');
                        $validationResult->validationsFailed++;
                    }
                    break;
            }
        }
        return $validationResult;
    }
}
