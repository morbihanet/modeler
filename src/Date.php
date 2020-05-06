<?php

namespace Morbihanet\Modeler;

use Carbon\Carbon;

class Date
{
    public static function generateDateAtTheBeginningOfTheITWorldWithSpecificTimeFromDate(
        Carbon $dateWithSpecificTime
    ): Carbon {
        return Carbon::create(1970, 1, 1, $dateWithSpecificTime->hour, $dateWithSpecificTime->minute);
    }

    public static function createAllDatesArrayFromStartDateAndEndDate(
        $startDate,
        $endDate, $step = '+1 day',
        $output_format = 'Y-m-d'
    ): array {
        $dates = [];
        $current = strtotime($startDate);
        $last = strtotime($endDate);

        while ($current <= $last) {
            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }

    public static function getNumberOfMinutesWorkedInASingleDay(Carbon $startDateTime, Carbon $endDateTime): int
    {
        $today = Carbon::now(new \DateTimeZone('Europe/Paris'));
        $startDateTime->setDate($today->year, $today->month, $today->day);
        $endDateTime->setDate($today->year, $today->month, $today->day);

        if (!self::isEndTimeInferiorToStartTime($startDateTime, $endDateTime)) {
            $numberOfMinutesWorked = $endDateTime->diffInMinutes($startDateTime);
        } else {
            $numberOfMinutesWorked = static::getDifferenceInMinutesFromTimeToMidnight($startDateTime);
            $numberOfMinutesWorked += static::getDifferenceInMinutesFromMidnightToTime($endDateTime);
        }

        return $numberOfMinutesWorked;
    }

    public static function isEndTimeInferiorToStartTime($startTime, $endTime): bool
    {
        $startTime  = Carbon::parse($startTime, new \DateTimeZone('Europe/Paris'));
        $endTime    = Carbon::parse($endTime, new \DateTimeZone('Europe/Paris'));
        $today      = Carbon::now();

        $startTime->setDate($today->year, $today->month, $today->day);
        $endTime->setDate($today->year, $today->month, $today->day);

        return $startTime > $endTime;
    }

    public static function getDifferenceInMinutesFromTimeToMidnight(Carbon $date): int
    {
        $dateAsMidnight = Carbon::parse($date);
        $dateAsMidnight->setTime(0, 0, 0);
        $dateAsMidnight->addDay();

        return $date->diffInMinutes($dateAsMidnight);
    }

    public static function getDifferenceInMinutesFromMidnightToTime(Carbon $date): int
    {
        $dateAsMidnight = Carbon::parse($date);
        $dateAsMidnight->setTime(0, 0, 0);

        return $dateAsMidnight->diffInMinutes($date);
    }

    public static function isThereAnyMissingDayBetweenTwoDatesAndAndArrayOfDates(
        $startDate,
        $endDate,
        array $allWorkingDates
    ): bool {
        $allDates = self::createAllDatesArrayFromStartDateAndEndDate($startDate, $endDate);

        return count($allDates) != count($allWorkingDates);
    }

}
