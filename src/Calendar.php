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
        $range = $this->createCarbonRangeOfTime($time, $dayToCheck);

        // the given time is not in range of working time.
        if (! $this->isInRange($time, $range)) {
            return false;
        }

        // generate Carbon ranges of break time hours
        $break = (array) array_get($dayToCheck, 'break');
        foreach ($break as $breakRange) {
            $range = $this->createCarbonRangeOfTime($time, $breakRange);

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
     * Create Carbon range of time
     * 
     * @param Carbon $date
     * @param array $timeRange 
     * $timeRange under the format ['from_hour' => 8, 'from_minute' => 0, 'to_hour' => 17, 'to_minute' => 0]
     * 
     * @return array [from, to]
     */
    protected function createCarbonRangeOfTime(Carbon $date, array $timeRange) : array
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
     * Create Carbon range of time by unix time
     * 
     * @param int $unixTimeFrom
     * @param int|null $unixTimeTo
     * @return array [from, to, isEqual]
     */
    public function createCarbonRangeOfTimeByUnixTime(int $unixTimeFrom, int $unixTimeTo = null) : array
    {
        $from = $this->createCarbonFromTimestamp($unixTimeFrom);
        $to = $this->createCarbonFromTimestamp($unixTimeTo ?? $unixTimeFrom);

        return [$from, $to, !$unixTimeTo];
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
        if (! $seconds) return 0;
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
     * @param mixed &$timeMatches
     * @param array $nonCountingTimeRanges
     * @return int
     */
    public function elapseSecondsInWorkingTime($from, $to, &$timeMatches = null, array $nonCountingTimeRanges = []) : int 
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
            $result = $this->calculateElapseSecondsInWorkingTimeFor247Calendar($from, $to, $dates, $timeMatches);
        }
        // for custom calendar
        else {
            $result = $this->calculateElapseSecondsInWorkingTimeForCustomCalendar($from, $to, $dates, $timeMatches); 
        }

        return $result;
    }

    /**
     * getElapsedSecondsOnTimeBand
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param Carbon $date
     * @param array &$config
     * @return array
     */
    protected function getElapsedSecondsOnTimeBand(Carbon $from, Carbon $to, Carbon $date, array &$config) : array
    {
        $secondsExcludes = $secondsIncludes = [];

        // find the day configuration
        $dayConfig = array_values(array_filter(
            $this->parseWorkdays(), 
            function ($item) use ($config) {
                return $config['day'] === $item['day'];
            }
        ))[0] ?? null;

        // day of $from matches day configuration
        if (
            $dayConfig && $from->dayOfWeek === $dayConfig['day'] 
            && $date->toDateString() === $from->toDateString()
        ) {
            $time = $from->copy();
            $time->hour = 0; $time->minute = 0; $time->second = 0;

            $dayFrom = $time->copy()->addHours($dayConfig['from_hour'])->addMinutes($dayConfig['from_minute']);
            $dayTo = $time->copy()->addHours($dayConfig['to_hour'])->addMinutes($dayConfig['to_minute']);

            // $from comes before the finish time of the day
            if ($from->diffInSeconds($dayTo, false) < 0) {
                return [[0], [0]];
            }
            // $from comes after the start time of the day
            elseif ($from->diffInSeconds($dayFrom, false) > 0) {
                $config['from_hour'] = $dayConfig['from_hour'];
                $config['from_minute'] = $dayConfig['from_minute'];
            }
        }

        // day of $to matches day configuration
        if (
            $dayConfig && $to->dayOfWeek === $dayConfig['day'] 
            && $date->toDateString() === $to->toDateString()
        ) {
            $time = $to->copy();
            $time->hour = 0; $time->minute = 0; $time->second = 0;

            $dayFrom = $time->copy()->addHours($dayConfig['from_hour'])->addMinutes($dayConfig['from_minute']);
            $dayTo = $time->copy()->addHours($dayConfig['to_hour'])->addMinutes($dayConfig['to_minute']);

            // $to comes after the start time of the day
            if ($to->diffInSeconds($dayFrom, false) > 0) {
                return [[0], [0]];
            }
            // $to comes before the finish time of the day
            elseif ($to->diffInSeconds($dayTo, false) < 0) {
                $config['to_hour'] = $dayConfig['to_hour'];
                $config['to_minute'] = $dayConfig['to_minute'];
            }
        }

        //
        $secondsIncludes[] = $this->diffInSecondsByFromToHourMinute($config);

        foreach ((array) array_get($config, 'break') as $break) {
            $secondsExcludes[] = $this->diffInSecondsByFromToHourMinute($break);
        }

        return [$secondsIncludes, $secondsExcludes];
    }

    /**
     * calculateElapseSecondsInWorkingTimeForCustomCalendar
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param array $dates
     * @param mixed &$timeMatches
     * @return int
     */
    protected function calculateElapseSecondsInWorkingTimeForCustomCalendar(Carbon $from, Carbon $to, array $dates, &$timeMatches = null) : int
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

            $timeMatches[] = $configMatches + ['date' => $date];

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
            $result = $this->getElapsedSecondsOnTimeBand($from, $to, $date, $config);
        }

        // $from is on the same date, but $to
        elseif ( $fromIsOnTheDate && ! $toIsOnTheDate ) {
            foreach ((array) array_get($config, 'break') as $index => $break) {
                list($breakTimeFrom, $breakTimeTo) = $this->createCarbonRangeOfTime($date, $break);

                // $from is in the range of $break
                // also comes after the point of $breakTimeTo
                if (
                    $this->isInRange($from, [$breakTimeFrom, $breakTimeTo])
                    && $from->diffInSeconds($breakTimeTo, false) > 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'from_hour' => $from->hour,
                        'from_minute' => $from->minute,
                    ]);
                }
                // $from is not in the range of $break
                // also comes after the point of $breakTimeFrom
                elseif ($from->diffInSeconds($breakTimeFrom, false) >= 0) {
                    $breakTimes[] = $break;
                }                
            }

            $mergedConfig = array_merge($config, [
                'from_hour' => $from->hour,
                'from_minute' => $from->minute,
                'break' => $breakTimes
            ]);
            
            $result = $this->getElapsedSecondsOnTimeBand($from, $to, $date, $mergedConfig);
        }

        // $to is on the same date, but $from
        elseif ( ! $fromIsOnTheDate && $toIsOnTheDate ) {
            foreach ((array) array_get($config, 'break') as $index => $break) {
                list($breakTimeFrom, $breakTimeTo) = $this->createCarbonRangeOfTime($date, $break);

                // $to is in the range of $break
                // also comes before the point of $breakTimeFrom
                if (
                    $this->isInRange($to, [$breakTimeFrom, $breakTimeTo])
                    && $to->diffInSeconds($breakTimeFrom, false) < 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ]);
                }
                // $to is not in the range of $break
                // also comes before the point of $breakTimeTo 
                elseif ($to->diffInSeconds($breakTimeTo, false) <= 0) {
                    $breakTimes[] = $break;
                }                
            }

            $mergedConfig = array_merge($config, [
                'to_hour' => $to->hour,
                'to_minute' => $to->minute,
                'break' => $breakTimes
            ]);
            $result = $this->getElapsedSecondsOnTimeBand($from, $to, $date, $mergedConfig);

        }

        // Both of ($from + $to) are on the same date
        else {
            foreach ((array) array_get($config, 'break') as $index => $break) {
                list($breakTimeFrom, $breakTimeTo) = $this->createCarbonRangeOfTime($date, $break);

                // range of Carbon instances
                $range = [$breakTimeFrom, $breakTimeTo];

                // both of ($from + $to) are in the range of $break
                if (
                    $this->isInRange($from, $range)
                    && $this->isInRange($to, $range)
                ) {
                    $breakTimes[] = array_merge($break, [
                        'from_hour' => $from->hour,
                        'from_minute' => $from->minute,
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ]);
                }

                // $from is in the range of $break
                // also comes after the point of $breakTimeTo
                elseif (
                    $this->isInRange($from, $range)
                    && $from->diffInSeconds($breakTimeTo, false) > 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'from_hour' => $from->hour,
                        'from_minute' => $from->minute,
                    ]);
                }
                // $to is in the range of $break
                // also comes before the point of $breakTimeFrom
                elseif (
                    $this->isInRange($to, $range)
                    && $to->diffInSeconds($breakTimeFrom, false) < 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ]);
                }
                // $from comes after the point of $breakTimeFrom
                // and $to comes before the point of $breakTimeTo
                elseif (
                    $from->diffInSeconds($breakTimeFrom, false) >= 0
                    && $to->diffInSeconds($breakTimeTo, false) <= 0
                ) {
                    $breakTimes[] = $break;
                }                
            }

            $mergedConfig = array_merge($config, [
                'from_hour' => $from->hour,
                'from_minute' => $from->minute,
                'to_hour' => $to->hour,
                'to_minute' => $to->minute,
                'break' => $breakTimes
            ]);
            $result = $this->getElapsedSecondsOnTimeBand($from, $to, $date, $mergedConfig);
        }

        return array_merge($result, [$mergedConfig]);
    }

    /**
     * calculateElapseSecondsInWorkingTimeFor247Calendar
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param array $dates
     * @param mixed &$timeMatches
     * @return int
     */
    protected function calculateElapseSecondsInWorkingTimeFor247Calendar(Carbon $from, Carbon $to, array $dates, &$timeMatches = null) : int
    {
        // for making an exclusion of no working seconds
        $secondsExcludes = [];
                
        // for making a sum of working seconds
        $secondsIncludes = [];

        foreach ($dates as $index => $date) {
            $secondsIncludes[] = 86400; // seconds

            if ($index === 0) {
                $secondsExcludes[] = $date->diffInSeconds($from);
              
                $timeMatches[] = [
                    'date' => $date,
                    'day' => $date->dayOfWeek,
                    'from_hour' => $from->hour,
                    'from_minute' => $from->minute,
                    'to_hour' => $date->copy()->addDay()->hour,
                    'to_minute' => $date->copy()->addDay()->minute,
                ];
            } 
            
            if ($index === count($dates) - 1) {
                $secondsExcludes[] = $date->copy()->addDay()->diffInSeconds($to);

                // both of ($from + $to) are on the same date
                if ($index === 0) {
                    $timeMatches[$index]['to_hour'] = $to->hour;
                    $timeMatches[$index]['to_minute'] = $to->minute;
                } 
                else {
                    $timeMatches[] = [
                        'date' => $date,
                        'day' => $date->dayOfWeek,
                        'from_hour' => $date->hour,
                        'from_minute' => $date->minute,
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ];
                }
            }

            if (($index !== 0) && ($index !== count($dates) - 1)) {
                $timeMatches[] = [
                    'date' => $date,
                    'day' => $date->dayOfWeek,
                    'from_hour' => $date->hour,
                    'from_minute' => $date->minute,
                    'to_hour' => $date->copy()->addDay()->hour,
                    'to_minute' => $date->copy()->addDay()->minute,
                ];
            }
        }
        
        return array_sum($secondsIncludes) - array_sum($secondsExcludes);
    }

    /**
     * Normalize overlapped time ranges
     * 
     * @param array $timeRanges [ [1584074475, 1584074480], ... ]
     * @param array &$formattedTimeRanges
     * @return array
     */
    public function normalizeOverlappedTimeRanges(array $timeRanges = [], &$formattedTimeRanges = []) : array
    {
        $result = [];

        // convert to Carbon instances
        $timeRanges = array_map(function($item) {
            return $this->createCarbonRangeOfTimeByUnixTime($item[0] ?? time(), $item[1] ?? null);
        }, $timeRanges);

        // sort ascending
        usort($timeRanges, function($p1, $p2) {
            return (
                $p1[0]->diffInSeconds($p2[0], false) < 0
            ) ? 1 : -1;
        });
        $formattedTimeRanges = $timeRanges;

        $total = count($timeRanges);
        $index = 0;
        $min = $timeRanges[0][0] ?? null;
        $max = $timeRanges[0][1] ?? null;

        // store the first time range
        if ($total) {
            $result[] = [
                $min,
                $max
            ];
        }
        
        while ($total) {
            $next = $timeRanges[$index + 1];

            // the starting point of the next time range is inner [<$min>, <$max>]
            if ($this->isInRange($next[0], [$min, $max])) {
                // push the time range when the ending point of the next time range comes before <$max>
                if (! $this->isInRange($next[1], [$min, $max])) {
                    $result[] = [
                        $max,
                        $max = $next[1]
                    ];
                } 
            }
            // the starting point of the next time range is outer [<$min>, <$max>]
            else {
                $result[] = [
                    $min = $next[0],
                    $max = $next[1]
                ];
            }

            if ( (++$index) == ($total - 1) ) break;
        }

        return $result;
    }

    /**
     * diffInSecondsByFromToHourMinute
     * 
     * @param array $config
     * $config under the format ['from_hour' => 8, 'from_minute' => 0, 'to_hour' => 17, 'to_minute' => 0]
     * @param Carbon|int $date
     * @param bool $absolute
     * @return int
     */
    public function diffInSecondsByFromToHourMinute(array $config, $date = null, bool $absolute = true) : int
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

        return $from->diffInSeconds($to, $absolute);
    }

    /**
     * Estimate what timestamp matches the target total in seconds
     * 
     * @param Carbon|int $from
     * @param int $target in seconds
     * @return Carbon|null
     */
    public function estimateTimestampMatchesTargetTotal($from, int $target) 
    {
        if (! $from instanceof Carbon) {
            $from = $this->createCarbonFromTimestamp($from);
        }

        while (true) {
            $backtrace = isset($try) ? $try : $from;
            // try to add seconds
            $try = $backtrace->copy()->addSeconds($target);
            // run a test
            $test = $this->elapseSecondsInWorkingTime($from, $try);

            if ($test >= $target) {
                $result = $try;
                break;
            }
        }

        // $estimate & $from are different
        while ($result->diffInSeconds($from) != 0) {
            // expect the accurate $estimate
            $estimate = $this->binaryEstimate(
                $from, 
                isset($estimate) ? $estimate->copy()->subDay() : $backtrace, 
                isset($estimate) ? $estimate : $backtrace->copy()->addSeconds($target), 
                $target,
                $test
            );
            
            if ($test < $target) break;

            $result = $estimate;
        }

        return $result;
    }

    /**
     * Estimate with Binary Search
     * 
     * @param Carbon $from
     * @param Carbon $start
     * @param Carbon $finish
     * @param int $target
     * @param int &$target
     * @return Carbon|null
     */
    protected function binaryEstimate(Carbon $from, Carbon $start, Carbon $finish, int $target, &$test) 
    {
        if ($start->diffInSeconds($finish, false) >= 0) {
            $midSec = (int) floor($start->diffInSeconds($finish, false) / 2);
            $mid = $start->copy()->addSeconds($midSec);

            $test = $this->elapseSecondsInWorkingTime($from, $mid);

            if ($test === $target || $midSec == 0) {
                return $mid;
            }

            if ($test < $target) {
                return $this->binaryEstimate($from, $mid, $finish, $target, $test);
            }
            
            if ($test > $target) {
                return $this->binaryEstimate($from, $start, $mid, $target, $test);
            }
        }

        return null;
    }
}