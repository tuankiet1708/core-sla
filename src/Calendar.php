<?php

namespace Leo\SLA;

use Carbon\Carbon;

class Calendar {
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
     * @param Carbon $haystack
     * @return bool
     */
    public function isHoliday(Carbon $haystack = null) : bool
    {
        if ($this->is247Calendar()) return false;

        // @todo: check with $haystack
        if (!empty($haystack)) return false;

        // now
        $time = Carbon::now($this->timezone());
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

    public function isWorkingDay(Carbon $haystack = null) : bool
    {
        if ($this->is247Calendar()) return true;

        // @todo: check with $haystack
        if (!empty($haystack)) return false;

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

    public function isTimeToTakeARest(Carbon $haystack = null) : bool
    {
        if ($this->is247Calendar()) return true;

        // @todo: check with $haystack
        if (!empty($haystack)) return false;

        return false;
    }

    public function isTimeToWork(Carbon $haystack = null) : bool
    {
        if ($this->is247Calendar()) return true;

        // @todo: check with $haystack
        if (!empty($haystack)) return false;

        return false;
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
}