<?php

namespace Leo\SLA;

use Carbon\Carbon;
use Leo\SLA\Exceptions\CalendarTimeRangeException;

class Calendar {
    /**
     * Constant of Weekdays
     * @var array
     */
    public const WEEK_MAP = [
        7 => Carbon::SUNDAY,        
        1 => Carbon::MONDAY,
        2 => Carbon::TUESDAY,
        3 => Carbon::WEDNESDAY,
        4 => Carbon::THURSDAY,
        5 => Carbon::FRIDAY,
        6 => Carbon::SATURDAY
    ];

    /**
     * Calendar configuration
     * 
     * @var array
     */
    protected $config;

    /**
     * Constructor
     * 
     * @param string $timezone
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get timezone info
     * 
     * @return string
     */
    public function timezone() : string
    {
        return array_get($this->config, 'timezone');
    }

    /**
     * Check whether a date is holiday
     * 
     * @param Carbon|int $haystack
     * @return bool
     */
    public function isHoliday($haystack = null) : bool
    {
        // When type of calendar is 247, all days are working days.
        if ($this->is247Calendar()) return false;

        if (! empty($haystack)) {
            if (! $haystack instanceof Carbon) {
                $haystack = $this->createCarbonFromTimestamp($haystack);
            }
        }

        // now or taking time from haystack 
        $time = $haystack ? $haystack->copy() : Carbon::now($this->timezone());

        // only need date, no nead time
        $time->hour = 0; $time->minute = 0; $time->second = 0;

        // convert to Carbon
        $holidays = $this->parseHolidays($time);

        foreach ($holidays as $date) {
            if ($time->equalTo($date['date'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a day is a working day
     * 
     * @param Carbon|int $haystack
     * @return bool
     */
    public function isWorkingDay($haystack = null) : bool
    {
        // When type of calendar is 247, all days are working days.
        if ($this->is247Calendar()) return true;

        if (! empty($haystack)) {
            if (! $haystack instanceof Carbon) {
                $haystack = $this->createCarbonFromTimestamp($haystack);
            }
        }
        
        // now or taking time from haystack 
        $time = $haystack ? $haystack->copy() : Carbon::now($this->timezone());

        // it's a holiday.
        if ($this->isHoliday($time)) return false;

        // parse workdays
        $workdays = $this->parseWorkdays();

        foreach ($workdays as $day) {
            if ($time->dayOfWeek === $day['day']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a calendar config is kind of 247
     * 
     * @return bool
     */
    public function is247Calendar() : bool
    {
        return array_get($this->config, 'type') === '247';
    }

    /**
     * Check whether a calendar config is kind of custom
     * 
     * @return bool
     */
    public function isCustomCalendar() : bool
    {
        return array_get($this->config, 'type') === 'custom';
    }

    /**
     * Check whether it is time to take a rest
     * 
     * @param Carbon|int $haystack
     * @return bool
     */
    public function isTimeToTakeARest($haystack = null) : bool
    {
        // When type of calendar is 247, all days are working days.
        if ($this->is247Calendar()) return false;

        if (! empty($haystack)) {
            if (! $haystack instanceof Carbon) {
                $haystack = $this->createCarbonFromTimestamp($haystack);
            }
        }

        // now or taking time from haystack 
        $time = $haystack ? $haystack->copy() : Carbon::now($this->timezone());

        return ! $this->isTimeToWork($time);
    }

    /**
     * Check whether it is time to work
     * 
     * @param Carbon|int $haystack
     * @return bool
     */
    public function isTimeToWork($haystack = null) : bool
    {
        // When type of calendar is 247, all days are working days.
        if ($this->is247Calendar()) return true;

        if (! empty($haystack)) {
            if (! $haystack instanceof Carbon) {
                $haystack = $this->createCarbonFromTimestamp($haystack);
            }
        }

        // now or taking time from haystack 
        $time = $haystack ? $haystack->copy() : Carbon::now($this->timezone());

        // it's not a working day.
        if (! $this->isWorkingDay($time)) return false;

        // parse workdays
        $workdays = $this->parseWorkdays();

        // take the day matching with the given time
        $dayToCheck = array_values(array_filter($workdays, function($day) use ($time) { 
            return $time->dayOfWeek === $day['day'];
        }))[0];

        // generate Carbon ranges of working hours
        $range = $this->generateCarbonRangeOfTime($time, $dayToCheck);

        // the given time is not in range of working time.
        if (! $this->isInRange($time, $range)) return false;

        // generate Carbon ranges of break time hours
        $break = (array) array_get($dayToCheck, 'break');
        foreach ($break as $breakRange) {
            $range = $this->generateCarbonRangeOfTime($time, $breakRange);

            // the given time is in range of break time.
            if ($this->isInRange($time, $range)) return false;
        }

        return true;
    }

    /**
     * Check whether the given time is in the given range.
     * 
     * @param Carbon|int $haystack
     * @param array $range
     * @return bool
     */
    protected function isInRange($haystack, array $range) : bool
    {
        if (count($range) !== 2) {
            throw new CalendarTimeRangeException;
        }

        if (! empty($haystack)) {
            if (! $haystack instanceof Carbon) {
                $haystack = $this->createCarbonFromTimestamp($haystack);
            }
        }

        // now or taking time from haystack 
        $time = $haystack ? $haystack->copy() : Carbon::now($this->timezone());

        return $time->diffInSeconds($range[0], false) <= 0 
            && $time->diffInSeconds($range[1], false) >= 0; 
    }

    /**
     * Generate Carbon range of time
     * 
     * @param Carbon $date
     * @param array $timeRange 
     * $timeRange under the format ['from_hour' => 8, 'from_minute' => 0, 'to_hour' => 17, 'to_minute' => 0]
     * 
     * @return array [from, to]
     */
    protected function generateCarbonRangeOfTime(Carbon $date, array $timeRange) : array
    {
        // only need date, no nead time
        $time = $date->copy();
        $time->hour = 0; $time->minute = 0; $time->second = 0;

        $result = [];

        foreach (['from', 'to'] as $point) {
            $result[] = $time->copy()
                             ->addHours($timeRange["{$point}_hour"])
                             ->addMinutes($timeRange["{$point}_minute"]);
        }

        return $result;
    } 

    /**
     * Parse work days
     * 
     * @return array
     */
    protected function parseWorkdays() : array
    {
        $workdays = (array) array_get($this->config, 'work_week');

        foreach ($workdays as $index => $day) {
            $workdays[$index]['day'] = self::WEEK_MAP[$day['day']];
        }

        return $workdays;
    }

    /**
     * Parse holidays
     * 
     * @param Carbon $time
     * @return array
     */
    protected function parseHolidays(Carbon $time = null) : array
    {
        $holidays = (array) array_get($this->config, 'holidays');

        // current year
        $currentYear = $time ? $time->year : Carbon::now($this->timezone())->year;

        // pool array for yearly holidays 
        $additionalYearlyHolidays = [];

        foreach ($holidays as $index => $date) {        
            $holidays[$index]['date'] = Carbon::parse($date['date'], $this->timezone());

            // push to pool for yearly holidays  
            if ($date['repeat'] === 'yearly') {
                // no pushing more when existing current-year holiday
                if ($holidays[$index]['date']->year === $currentYear) continue;

                // clone
                $clone = $holidays[$index];
                $clone['date'] = $clone['date']->copy();

                // repeat the holiday for current year
                $clone['date']->year = $currentYear;
                $additionalYearlyHolidays[] = $clone;
            }
        }

        return array_merge($holidays, $additionalYearlyHolidays);
    }

    /**
     * Create a Carbon instance from timestamp
     * 
     * @param int $timestamp
     * @return Carbon
     */
    public function createCarbonFromTimestamp(int $timestamp) : Carbon
    {
        return Carbon::createFromTimestamp($timestamp, $this->timezone());
    }
}