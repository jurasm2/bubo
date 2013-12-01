<?php

namespace Bubo\Utils;

use DateTime;

/**
 * DateHelper
 *
 */
class DateHelper {

    public static $dateTimeFormats = array(
        'd.m.Y H:i',
        'd.m.Y',
        'Y-m-d H:i',
        'Y-m-d',
    );

    /**
     * Checks if given string is in provided format
     * @param string $format
     * @param string $dateTimeString
     * @return bool
     */
    public static function isInDateFormat($format, $dateTimeString)
    {
        return DateTime::createFromFormat($format, $dateTimeString) !== FALSE;
    }

    /**
     *
     * @param string $dateString in format d.m.Y
     * @param string|null $timeString in format H:i
     * @return Datetime|FALSE
     */
    public static function createDate($dateString, $timeString = null, $preferredFormats = array())
    {
        $dateTimeString = $dateString;
        $datetime = FALSE;

        // is timestring provided
        if ($timeString !== null) {
            // try to parse time string
            $dateTimeString = trim(sprintf('%s %s',$dateString, $timeString));
        }

        foreach (((array) $preferredFormats + self::$dateTimeFormats) as $format) {
            $datetime = DateTime::createFromFormat($format, $dateTimeString);
            if ($datetime !== FALSE) {
                break;
            }
        }
        return $datetime;
    }

}

