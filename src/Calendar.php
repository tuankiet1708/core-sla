<?php

namespace Leo\SLA;

use Carbon\Carbon;
use Carbon\CarbonInterval;
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
     * @param Carbon|null $matches
     * @return bool
     */
    public function isHoliday($haystack = null, &$matches = null) : bool
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
                $matches[] = $date;
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a day is a working day
     * 
     * @param Carbon|int $haystack
     * @param Carbon|null $matches
     * @return bool
     */
    public function isWorkingDay($haystack = null, &$matches = null) : bool
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
                $matches[] = $day;
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
     * @param Carbon|null &$matches
     * @return bool
     */
    public function isTimeToTakeARest($haystack = null, &$matches = null) : bool
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

        return ! $this->isTimeToWork($time, $matches);
    }

    /**
     * Check whether it is time to work
     * 
     * @param Carbon|int $haystack
     * @param Carbon|null &$matches
     * @return bool
     */
    public function isTimeToWork($haystack = null, &$matches = null) : bool
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
        if (! $this->isWorkingDay($time, $matches)) return false;

        // parse workdays
        $workdays = $this->parseWorkdays();

        // take the day matching with the given time
        $dayToCheck = array_values(array_filter($workdays, function($day) use ($time) { 
            return $time->dayOfWeek === $day['day'];
        }))[0];

        // generate Carbon ranges of working hours
        $range = $this->generateCarbonRangeOfTime($time, $dayToCheck);

        // the given time is not in range of working time.
        if (! $this->isInRange($time, $range)) {
            return false;
        }

        // generate Carbon ranges of break time hours
        $break = (array) array_get($dayToCheck, 'break');
        foreach ($break as $breakRange) {
            $range = $this->generateCarbonRangeOfTime($time, $breakRange);

            // the given time is in range of break time.
            if ($this->isInRange($time, $range)) {
                $matches[] = $range;
                return false;
            }
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
            $workdays[$index]['day'] = self::WEEK_MAP[$day['day']] ?? -1;
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
                $clone['additional_repeat'] = true;
                $additionalYearlyHolidays[] = $clone;
            }
        }

        return array_merge($holidays, $additionalYearlyHolidays);
    }

    /**
     * Get workdays
     * 
     * @return array
     */
    public function getWorkdays() : array 
    {
        return $this->parseWorkdays();
    }

    /**
     * Get holidays
     * 
     * @param Carbon|int $haystack
     * @return array
     */
    public function getHolidays($haystack = null) : array
    {
        if (! empty($haystack)) {
            if (! $haystack instanceof Carbon) {
                $haystack = $this->createCarbonFromTimestamp($haystack);
            }
        }

        // now or taking time from haystack 
        $time = $haystack ? $haystack->copy() : Carbon::now($this->timezone());

        return $this->parseHolidays($time);
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

    /**
     * Seconds For Humans
     * 
     * @param int $seconds
     * @return string
     */
    public function secondsForHumans(int $seconds) : string
    {
        return CarbonInterval::seconds($seconds)->cascade()->forHumans();
    }

    /**
     * Time elapse in seconds
     * 
     * @param Carbon|int $from
     * @param Carbon|int $to
     * @return int
     */
    public function elapseSeconds($from, $to) : int
    {
        if (! $from instanceof Carbon) {
            $from = $this->createCarbonFromTimestamp($from);
        }

        if (! $to instanceof Carbon) {
            $to = $this->createCarbonFromTimestamp($to);
        }

        return $from->diffInSeconds($to, false);
    } 

    /**
     * Working time elapse in seconds
     * 
     * @param Carbon|int $from
     * @param Carbon|int $to
     * @return int
     */
    public function elapseSecondsInWokingTime($from, $to) : int 
    {
        if (! $from instanceof Carbon) {
            $from = $this->createCarbonFromTimestamp($from);
        }

        if (! $to instanceof Carbon) {
            $to = $this->createCarbonFromTimestamp($to);
        }

        // collect dates consist of working hours
        $dates = [];
        while (true) {
            $date = isset($date) ? $date->addDay() : $from->copy();
            $date->hour = 0; $date->minute = 0; $date->second = 0;

            if ($date->diffInSeconds($to, false) < 0) break;

            $dates[] = $date->copy();
        }

        // for 247 calendar
        if ($this->is247Calendar()) {
            return $this->calculateElapseSecondsInWokingTimeFor247Calendar($from, $to, $dates);
        }

        // for custom calendar
        return $this->calculateElapseSecondsInWokingTimeForCustomCalendar($from, $to, $dates); 
    }

    /**
     * getElapsedSecondsOnTimeBand
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param array $config
     * @return array
     */
    protected function getElapsedSecondsOnTimeBand(Carbon $from, Carbon $to, array $config) : array
    {
        $secondsExcludes = $secondsIncludes = [];

        $secondsIncludes[] = $this->diffInSecondsByFromToHourMinute($config);

        foreach ((array) array_get($config, 'break') as $break) {
            $secondsExcludes[] = $this->diffInSecondsByFromToHourMinute($break);
        }

        return [$secondsIncludes, $secondsExcludes];
    }

    /**
     * calculateElapseSecondsInWokingTimeForCustomCalendar
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param array $dates
     * @return int
     */
    protected function calculateElapseSecondsInWokingTimeForCustomCalendar(Carbon $from, Carbon $to, array $dates) : int
    {
        // for making an exclusion of no working seconds
        $secondsExcludes = [];
        
        // for making a sum of working seconds
        $secondsIncludes = [];

        foreach ($dates as $index => $date) {
            // reset
            $matches = [];

            if (! $this->isWorkingDay($date, $matches)) continue;

            list ($includes, $excludes, $configMatches) = $this->getElapsedSeconds($from, $to, $date, $matches[0]);

            var_dump(
                ">>>>>>>>>",
                $configMatches, 
                $this->secondsForHumans(array_sum($includes) - array_sum($excludes)),
                "=========\n"
            );

            $secondsExcludes = array_merge($secondsExcludes, $excludes);
            $secondsIncludes = array_merge($secondsIncludes, $includes);
        }

        return array_sum($secondsIncludes) - array_sum($secondsExcludes);
    }

    /**
     * getElapsedSeconds
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param Carbon $date
     * @param array $config
     * @return array
     */
    protected function getElapsedSeconds(Carbon $from, Carbon $to, Carbon $date, array $config) : array
    {
        // flag to indicates whether ($from + $to) are on the same ($date)
        $fromIsOnTheDate = $from->toDateString() === $date->toDateString();
        $toIsOnTheDate = $to->toDateString() === $date->toDateString();

        // pool of break times
        $breakTimes = [];
        $mergedConfig = $config;

        // both of ($from + $to) are not on the same date
        if (! $fromIsOnTheDate && ! $toIsOnTheDate) {
            $result = $this->getElapsedSecondsOnTimeBand($from, $to, $config);
        }

        // $from is on the same date, but $to
        elseif ( $fromIsOnTheDate && ! $toIsOnTheDate ) {
            foreach ((array) array_get($config, 'break') as $index => $break) {
                $breakTimeFrom = $date->copy();
                $breakTimeFrom->addHours($break['from_hour'])->addMinutes($break['from_minute']);
                
                $breakTimeTo = $date->copy();
                $breakTimeTo->addHours($break['to_hour'])->addMinutes($break['to_minute']);

                // $from is in the range of $break
                // also upper than the point of $breakTimeTo
                if (
                    $this->isInRange($from, $this->generateCarbonRangeOfTime($date, $break))
                    && $from->diffInSeconds($breakTimeTo, false) > 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'from_hour' => $from->hour,
                        'from_minute' => $from->minute,
                    ]);
                }
                // $from is not in the range of $break
                elseif ($from->diffInSeconds($breakTimeFrom, false) >= 0) {
                    $breakTimes[] = $break;
                }                
            }

            $result = $this->getElapsedSecondsOnTimeBand($from, $to, $mergedConfig = array_merge($config, [
                'from_hour' => $from->hour,
                'from_minute' => $from->minute,
                'break' => $breakTimes
            ]));
        }

        // $to is on the same date, but $from
        elseif ( ! $fromIsOnTheDate && $toIsOnTheDate ) {
            foreach ((array) array_get($config, 'break') as $index => $break) {
                $breakTimeFrom = $date->copy();
                $breakTimeFrom->addHours($break['from_hour'])->addMinutes($break['from_minute']);
                
                $breakTimeTo = $date->copy();
                $breakTimeTo->addHours($break['to_hour'])->addMinutes($break['to_minute']);

                // $to is in the range of $break
                // also lower than the point of $breakTimeFrom
                if (
                    $this->isInRange($to, $this->generateCarbonRangeOfTime($date, $break))
                    && $to->diffInSeconds($breakTimeFrom, false) < 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ]);
                }
                // $to is not in the range of $break
                elseif ($to->diffInSeconds($breakTimeTo, false) <= 0) {
                    $breakTimes[] = $break;
                }                
            }

            $result = $this->getElapsedSecondsOnTimeBand($from, $to, $mergedConfig = array_merge($config, [
                'to_hour' => $to->hour,
                'to_minute' => $to->minute,
                'break' => $breakTimes
            ]));

        }

        // Both of ($from + $to) are on the same date
        else {
            foreach ((array) array_get($config, 'break') as $index => $break) {
                $breakTimeFrom = $date->copy();
                $breakTimeFrom->addHours($break['from_hour'])->addMinutes($break['from_minute']);
                
                $breakTimeTo = $date->copy();
                $breakTimeTo->addHours($break['to_hour'])->addMinutes($break['to_minute']);

                // both of ($from + $to) are in the range of $break
                if (
                    $this->isInRange($from, $this->generateCarbonRangeOfTime($date, $break))
                    && $this->isInRange($to, $this->generateCarbonRangeOfTime($date, $break))
                ) {
                    $breakTimes[] = array_merge($break, [
                        'from_hour' => $from->hour,
                        'from_minute' => $from->minute,
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ]);
                }

                // $from is in the range of $break
                // also upper than the point of $breakTimeTo
                elseif (
                    $this->isInRange($from, $this->generateCarbonRangeOfTime($date, $break))
                    && $from->diffInSeconds($breakTimeTo, false) > 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'from_hour' => $from->hour,
                        'from_minute' => $from->minute,
                    ]);
                }
                // $to is in the range of $break
                // also lower than the point of $breakTimeFrom
                elseif (
                    $this->isInRange($to, $this->generateCarbonRangeOfTime($date, $break))
                    && $to->diffInSeconds($breakTimeFrom, false) < 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ]);
                }
                // $from is upper than the point of $breakTimeFrom
                elseif (
                    $from->diffInSeconds($breakTimeFrom, false) >= 0
                ) {
                    $breakTimes[] = $break;
                }                
            }

            $result = $this->getElapsedSecondsOnTimeBand($from, $to, $mergedConfig = array_merge($config, [
                'from_hour' => $from->hour,
                'from_minute' => $from->minute,
                'to_hour' => $to->hour,
                'to_minute' => $to->minute,
                'break' => $breakTimes
            ]));
        }

        return array_merge($result, [$mergedConfig]);
    }

    /**
     * calculateElapseSecondsInWokingTimeFor247Calendar
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param array $dates
     * @return int
     */
    protected function calculateElapseSecondsInWokingTimeFor247Calendar(Carbon $from, Carbon $to, array $dates) : int
    {
        // for making an exclusion of no working seconds
        $secondsExcludes = [];
                
        // for making a sum of working seconds
        $secondsIncludes = [];

        foreach ($dates as $index => $date) {
            $secondsIncludes[] = 86400; // seconds

            if ($index === 0) {
                $secondsExcludes[] = $date->diffInSeconds($from);
            } 
            
            if ($index === count($dates) - 1) {
                $secondsExcludes[] = $date->copy()->addDay()->diffInSeconds($to);
            }
        }
        
        return array_sum($secondsIncludes) - array_sum($secondsExcludes);
    }

    /**
     * diffInSecondsByFromToHourMinute
     * 
     * @param array $config
     * $config under the format ['from_hour' => 8, 'from_minute' => 0, 'to_hour' => 17, 'to_minute' => 0]
     * @param Carbon|int $date
     * @return int
     */
    public function diffInSecondsByFromToHourMinute(array $config, $date = null) : int
    {
        if (! empty($date)) {
            if (! $date instanceof Carbon) {
                $date = $this->createCarbonFromTimestamp($date);
            }
        }

        $time = $date ? $date->copy() : Carbon::now($this->timezone());

        if ($time->hour != 0) $time->hour = 0;
        if ($time->minute != 0) $time->minute = 0;
        if ($time->second != 0) $time->second = 0;

        $from = $time->copy()
                     ->addHours($config['from_hour'])
                     ->addMinutes($config['from_minute']);

        $to = $time->copy()
                   ->addHours($config['to_hour'])
                   ->addMinutes($config['to_minute']);

        return $from->diffInSeconds($to);
    }
}