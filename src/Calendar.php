<?php

namespace Leo\SLA;

use Carbon\Carbon;

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
        $time = $haystack ?? Carbon::now($this->timezone());

        // only need date, no nead time
        $time->hour = 0; $time->minute = 0; $time->second = 0;

        // convert to Carbon
        $holidays = $this->parseHolidays();

        foreach ($holidays as $date) {
            if ($time->equalTo($date['date'])) {
                return true;
            }
        }

        return false;
    }

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
        $time = $haystack ?? Carbon::now($this->timezone());

        // it's a holiday.
        if ($this->isHoliday($time)) return false;

        // parse workdays
        $workdays = $this->parseWorkdays();

        foreach ($workdays as $day) {
            if ($time->dayOfWeek === $day['day']) return true;
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

    public function isTimeToTakeARest($haystack = null) : bool
    {
        // When type of calendar is 247, all days are working days.
        if ($this->is247Calendar()) return true;

        // @todo: check with $haystack
        if (! empty($haystack)) {
            if (! $haystack instanceof Carbon) {
                $haystack = $this->createCarbonFromTimestamp($haystack);
            }
            
            return false;
        }

        return false;
    }

    public function isTimeToWork($haystack = null) : bool
    {
        if ($this->is247Calendar()) return true;

        // @todo: check with $haystack
        if (! empty($haystack)) {
            if (! $haystack instanceof Carbon) {
                $haystack = $this->createCarbonFromTimestamp($haystack);
            }
            
            return false;
        }

        return false;
    }

    /**
     * Parse work days
     * 
     * @return array
     */
    public function parseWorkdays() : array
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
     * @return array
     */
    public function parseHolidays() : array
    {
        $holidays = (array) array_get($this->config, 'holidays');

        // current year
        $currentYear = Carbon::now($this->timezone())->year;

        // pool for yearly holidays 
        $additionalYearlyHolidays = [];

        foreach ($holidays as $index => $date) {        
            $holidays[$index]['date'] = Carbon::parse($date['date'], $this->timezone());

            // push to pool for yearly holidays  
            if ($date['repeat'] === 'yearly') {
                $clone = $holidays[$index];
                $clone['date'] = $clone['date']->copy();
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