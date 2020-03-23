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
        
        return $this->compareDateTime($time, $range[0]) >= 0
            && $this->compareDateTime($time, $range[1]) <= 0;
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

        $result[] = $time->copy()
                        ->addHours($timeRange['from_hour'])
                        ->addMinutes($timeRange['from_minute']);

        if ($timeRange['to_hour'] === 0 && $timeRange['to_minute'] === 0) {
            $result[] = $time->copy()->addDay();
        } else {
            $result[] = $time->copy()->addHours($timeRange['to_hour'])->addMinutes($timeRange['to_minute']);
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
     * Elapsed time in seconds
     * 
     * @param Carbon|int $from
     * @param Carbon|int $to
     * @return int
     */
    public function elapsedSeconds($from, $to) : int
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
     * Elapsed working time in seconds
     * 
     * @param Carbon|int $from
     * @param Carbon|int $to
     * @param mixed &$timeMatches
     * @param array $nonCountingTimeRanges
     * @return int
     */
    public function elapsedSecondsInWorkingTime($from, $to, &$timeMatches = null, array $nonCountingTimeRanges = []) : int 
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

            if ($this->compareDateTime($date, $to) > 0) break;

            $dates[] = $date->copy();
        }

        // for 247 calendar
        if ($this->is247Calendar()) {
            $result = $this->calculateElapsedSecondsInWorkingTimeFor247Calendar($from, $to, $dates, $timeMatches, $nonCountingTimeRanges);
        }
        // for custom calendar
        else {
            $result = $this->calculateElapsedSecondsInWorkingTimeForCustomCalendar($from, $to, $dates, $timeMatches, $nonCountingTimeRanges); 
        }

        return $result;
    }

    /**
     * countElapsedSecondsOnTimeBand
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param Carbon $date
     * @param array &$config
     * @param array &$nonCountingTimeRanges
     * @return array
     */
    protected function countElapsedSecondsOnTimeBand(Carbon $from, Carbon $to, Carbon $date, array &$config, array &$nonCountingTimeRanges) : array
    {
        $secondsExcludes = $secondsIncludes = [];        
        $config['partial_elapsed'] = 0;

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

            if ($dayConfig['to_hour'] === 0 && $dayConfig['to_minute'] === 0) {
                $dayTo = $time->copy()->addDay();
            } else {
                $dayTo = $time->copy()->addHours($dayConfig['to_hour'])->addMinutes($dayConfig['to_minute']);
            }

            // $dayTo < $from
            if ($this->compareDateTime($from, $dayTo) > 0) {
                return [[0], [0]];
            }
            // $from < $dayFrom
            elseif ($this->compareDateTime($from, $dayFrom) < 0) {
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
            
            if ($dayConfig['to_hour'] === 0 && $dayConfig['to_minute'] === 0) {
                $dayTo = $time->copy()->addDay();
            } else {
                $dayTo = $time->copy()->addHours($dayConfig['to_hour'])->addMinutes($dayConfig['to_minute']);
            }

            // $to < $dayFrom
            if ($this->compareDateTime($to, $dayFrom) < 0) {
                return [[0], [0]];
            }
            // $dayTo < $to
            elseif ($this->compareDateTime($to, $dayTo) > 0) {
                $config['to_hour'] = $dayConfig['to_hour'];
                $config['to_minute'] = $dayConfig['to_minute'];
            }
        }

        //
        $secondsIncludes[] = $this->diffInSecondsByFromToHourMinute($config);

        // foreach ((array) array_get($config, 'break') as $break) {
        //     $secondsExcludes[] = $this->diffInSecondsByFromToHourMinute($break);
        // }

        // revert the format 
        $nonCountingTimeRanges = array_map(function($item) {
            return [
                $item[0]->timestamp,
                empty($item[1]) ? null : $item[1]->timestamp,
            ];
        }, $nonCountingTimeRanges);

        // push breaks to nonCountingTimeRanges
        foreach ((array) array_get($config, 'break') as $break) {
            $breakFrom = $date->copy()->addHours($break['from_hour'])->addMinutes($break['from_minute']);
            
            if ($break['to_hour'] === 0 && $break['to_minute'] === 0) {
                $breakTo = $date->copy()->addDay();
            } else {
                $breakTo = $date->copy()->addHours($break['to_hour'])->addMinutes($break['to_minute']);
            }

            $nonCountingTimeRanges[] = [
                $breakFrom->timestamp, 
                $breakTo->timestamp
            ];
        }

        // normalize
        $nonCountingTimeRanges = $this->normalizeOverlappedTimeRanges($nonCountingTimeRanges);

        // merge with non-counting time ranges
        $indication = $this->mergeWithNonCountingTimeRanges(
            $config,
            $nonCountingTimeRanges,
            $secondsExcludes,
            $secondsIncludes
        );

        return [$secondsIncludes, $secondsExcludes, $indication ?? null];
    }

    /**
     * calculateElapsedSecondsInWorkingTimeForCustomCalendar
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param array $dates
     * @param mixed &$timeMatches
     * @param array $nonCountingTimeRanges
     * @return int
     */
    protected function calculateElapsedSecondsInWorkingTimeForCustomCalendar(Carbon $from, Carbon $to, array $dates, &$timeMatches = null, array $nonCountingTimeRanges) : int
    {
        // for making an exclusion of no working seconds
        $secondsExcludes = [];
        
        // for making a sum of working seconds
        $secondsIncludes = [];

        // normalize
        $nonCountingTimeRanges = $this->normalizeOverlappedTimeRanges($nonCountingTimeRanges);

        foreach ($dates as $index => $date) {
            // reset
            $matches = [];

            if (! $this->isWorkingDay($date, $matches)) continue;

            list ($includes, $excludes, $configMatches, $indication) = $this->executeElapsedSecondsInWorkingTime($from, $to, $date, $matches[0], $nonCountingTimeRanges);

            $timeMatches[] = $configMatches;

            $secondsExcludes = array_merge($secondsExcludes, $excludes);
            $secondsIncludes = array_merge($secondsIncludes, $includes);

            if ($indication === -1) break;
        }        

        return array_sum($secondsIncludes) - array_sum($secondsExcludes);
    }

    /**
     * executeElapsedSecondsInWorkingTime
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param Carbon $date
     * @param array $config
     * @param array &$nonCountingTimeRanges
     * @return array
     */
    protected function executeElapsedSecondsInWorkingTime(Carbon $from, Carbon $to, Carbon $date, array $config, array &$nonCountingTimeRanges) : array
    {
        // flag to indicates whether ($from + $to) are on the same ($date)
        $fromIsOnTheDate = $from->toDateString() === $date->toDateString();
        $toIsOnTheDate = $to->toDateString() === $date->toDateString();

        // pool of break times
        $breakTimes = [];

        // merge with $date
        $config['date'] = $date;
        $mergedConfig = $config;

        // both of ($from + $to) are not on the same date
        if (! $fromIsOnTheDate && ! $toIsOnTheDate) {
            $result = $this->countElapsedSecondsOnTimeBand($from, $to, $date, $mergedConfig, $nonCountingTimeRanges);
        }

        // $from is on the same date, but $to
        elseif ( $fromIsOnTheDate && ! $toIsOnTheDate ) {
            foreach ((array) array_get($config, 'break') as $index => $break) {
                list($breakTimeFrom, $breakTimeTo) = $this->createCarbonRangeOfTime($date, $break);

                // $from is in the range of $break
                // and $from < $breakTimeTo
                if (
                    $this->isInRange($from, [$breakTimeFrom, $breakTimeTo])
                    && $this->compareDateTime($from, $breakTimeTo) < 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'from_hour' => $from->hour,
                        'from_minute' => $from->minute,
                    ]);
                }
                // $from is not in the range of $break
                // and $from <= $breakTimeFrom
                elseif ($this->compareDateTime($from, $breakTimeFrom) <= 0) {
                    $breakTimes[] = $break;
                }                
            }

            $mergedConfig = array_merge($config, [
                'from_hour' => $from->hour,
                'from_minute' => $from->minute,
                'break' => $breakTimes
            ]);
            
            $result = $this->countElapsedSecondsOnTimeBand($from, $to, $date, $mergedConfig, $nonCountingTimeRanges);
        }

        // $to is on the same date, but $from
        elseif ( ! $fromIsOnTheDate && $toIsOnTheDate ) {
            foreach ((array) array_get($config, 'break') as $index => $break) {
                list($breakTimeFrom, $breakTimeTo) = $this->createCarbonRangeOfTime($date, $break);

                // $to is in the range of $break
                // and $breakTimeFrom < $to
                if (
                    $this->isInRange($to, [$breakTimeFrom, $breakTimeTo])
                    && $this->compareDateTime($to, $breakTimeFrom) > 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ]);
                }
                // $to is not in the range of $break
                // and $breakTimeTo <= $to
                elseif ($this->compareDateTime($to, $breakTimeTo) >= 0) {
                    $breakTimes[] = $break;
                }                
            }

            $mergedConfig = array_merge($config, [
                'to_hour' => $to->hour,
                'to_minute' => $to->minute,
                'break' => $breakTimes
            ]);
            $result = $this->countElapsedSecondsOnTimeBand($from, $to, $date, $mergedConfig, $nonCountingTimeRanges);

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
                // and $from < $breakTimeTo
                elseif (
                    $this->isInRange($from, $range)
                    && $this->compareDateTime($from, $breakTimeTo) < 0 
                ) {
                    $breakTimes[] = array_merge($break, [
                        'from_hour' => $from->hour,
                        'from_minute' => $from->minute,
                    ]);
                }
                // $to is in the range of $break
                // and $breakTimeFrom < $to
                elseif (
                    $this->isInRange($to, $range)
                    && $this->compareDateTime($to, $breakTimeFrom) > 0
                ) {
                    $breakTimes[] = array_merge($break, [
                        'to_hour' => $to->hour,
                        'to_minute' => $to->minute,
                    ]);
                }
                // $from <= $breakTimeFrom
                // and $breakTimeTo <= $to
                elseif (
                    $this->compareDateTime($from, $breakTimeFrom) <= 0
                    && $this->compareDateTime($to, $breakTimeTo) >= 0
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
            $result = $this->countElapsedSecondsOnTimeBand($from, $to, $date, $mergedConfig, $nonCountingTimeRanges);
        }

        return [
            $result[0], // secondIncludes
            $result[1], // secondExcludes
            $mergedConfig,
            $result[2] ?? null, // indication
        ];
    }

    /**
     * Compare a datetime with a given datetime
     * 
     * @param Carbon $haystack
     * @param Carbon|null $comparedWith
     * @return int -1: <$haystack> is the past, 1: <$haystack> is the future, 0: same date
     */
    public function compareDateTime(Carbon $haystack, Carbon $comparedWith = null) : int
    {
        if (empty($comparedWith)) {
            $comparedWith = Carbon::now($this->timezone());
        }

        // $comparedWith->hour = 0; $comparedWith->minute = 0; $comparedWith->second = 0;
        // $haystack->hour = 0; $haystack->minute = 0; $haystack->second = 0;

        $diff = $haystack->diffInSeconds($comparedWith, false);

        if ($diff > 0) return -1;
        return $diff === 0 ? 0 : 1;
    }

    /**
     * Merge with non-counting time ranges
     * 
     * @param array &$dayConfig
     * @param array &$nonCountingTimeRanges
     * @param array &$secondsExcludes
     * @param array $secondsIncludes
     * @return int 0: empty non-counting, 1: indicate calculation should be continued, -1: indicate calculation should be stopped
     */
    protected function mergeWithNonCountingTimeRanges(array &$dayConfig, array &$nonCountingTimeRanges, array &$secondsExcludes, array $secondsIncludes) : int
    {
        $result = 0; $partialExcludes = [];
        $dayConfig['partial_elapsed'] = array_sum($secondsIncludes) - array_sum($partialExcludes);

        // empty non-counting
        if (empty($nonCountingTimeRanges)) return $result;

        $from = $dayConfig['date']->copy()->addHours($dayConfig['from_hour'])->addMinutes($dayConfig['from_minute']);

        if ($dayConfig['to_hour'] === 0 && $dayConfig['to_minute'] === 0) {
            $to = $dayConfig['date']->copy()->addDay();
        }
        else {
            $to = $dayConfig['date']->copy()->addHours($dayConfig['to_hour'])->addMinutes($dayConfig['to_minute']);
        }

        $result = 1;

        foreach ($nonCountingTimeRanges as $index => &$range) {
            // compare $range[1] with $from
            // $range[1] is the past
            if ($this->compareDateTime($range[1] ?? $from, $from) < 0) {
                $nonCountingTimeRanges[$index] = null;
                continue;
            }

            // compare $range[0] with $to
            // $range[0] is the future
            if ($this->compareDateTime($range[0], $to) >= 0) {
                break;
            }

            // $range[0] is the past when compared with $from
            if ($this->compareDateTime($range[0], $from) < 0) {
                $range[0] = $from->copy();
            }

            // calculation should be stopped 
            if (empty($range[1])) {                
                $secondsExcludes[] = $exclude = $range[0]->diffInSeconds($to);
                $partialExcludes[] = $exclude;
                
                // add skip
                $dayConfig['skip'][] = [$range[0]];
                
                $result = -1;
                array_splice($nonCountingTimeRanges, $index);

                break;
            }

            // $range[1] is the future when compared with $to
            if ($this->compareDateTime($range[1], $to) > 0) {   
                // divide into 2 ranges, and push back to the array             
                array_splice($nonCountingTimeRanges, $index, 1, [
                    [$range[0]->copy(), $to->copy()],
                    [$to->copy(), $range[1]->copy()],
                ]);
            }

            $diff = $nonCountingTimeRanges[$index][0]->diffInSeconds($nonCountingTimeRanges[$index][1]);

            if ($diff !== 0) {
                $secondsExcludes[] = $exclude = $nonCountingTimeRanges[$index][0]->diffInSeconds($nonCountingTimeRanges[$index][1]);
                $partialExcludes[] = $exclude;

                $dayConfig['skip'][] = [
                    $nonCountingTimeRanges[$index][0],
                    $nonCountingTimeRanges[$index][1]
                ];
            }
        }

        $dayConfig['partial_elapsed'] = array_sum($secondsIncludes) - array_sum($partialExcludes);

        $nonCountingTimeRanges = array_values(array_filter($nonCountingTimeRanges));

        // calculation should be continued
        return $result;
    }

    /**
     * calculateElapsedSecondsInWorkingTimeFor247Calendar
     * 
     * @param Carbon $from
     * @param Carbon $to
     * @param array $dates
     * @param mixed &$timeMatches
     * @param array $nonCountingTimeRanges
     * @return int
     */
    protected function calculateElapsedSecondsInWorkingTimeFor247Calendar(Carbon $from, Carbon $to, array $dates, &$timeMatches = null, array $nonCountingTimeRanges = []) : int
    {
        // for making an exclusion of no working seconds
        $secondsExcludes = [];
                
        // for making a sum of working seconds
        $secondsIncludes = [];

        // normalize
        $nonCountingTimeRanges = $this->normalizeOverlappedTimeRanges($nonCountingTimeRanges);

        foreach ($dates as $index => $date) {
            $secondsIncludes[] = 86400; // seconds
            $partialExcludes = [];

            if ($index === 0) {
                $secondsExcludes[] = $exclude = $date->diffInSeconds($from);
                $partialExcludes[] = $exclude;
              
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
                $secondsExcludes[] = $exclude = $date->copy()->addDay()->diffInSeconds($to);
                $partialExcludes[] = $exclude;

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

            $indication = $this->mergeWithNonCountingTimeRanges(
                $timeMatches[count($timeMatches) - 1], 
                $nonCountingTimeRanges, 
                $secondsExcludes,
                [86400 - array_sum($partialExcludes)]
            );

            if ($indication === -1) {
                break;
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
                $this->compareDateTime($p1[0], $p2[0]) > 0
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
            $next = $timeRanges[$index + 1] ?? null;

            if (empty($next)) break;

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

        $result = array_map(function($item) {                      
            $diff = $item[0]->diffInSeconds($item[1] ?? $item[0]);

            return [
                $item[0],     
                
                // $diff == 0 
                // => $item[0] == $item[1] 
                // => the point where it is being skipped without knowing when to be continued  
                // then assign <null> - a point indiciates the calculation should be stopped
                $diff === 0 ? null : $item[1], 
            ];
        }, $result);

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

        if ($config['to_hour'] === 0 && $config['to_minute'] === 0) {
            $to = $time->copy()->addDay();
        } else {
            $to = $time->copy()
                        ->addHours($config['to_hour'])
                        ->addMinutes($config['to_minute']);
        }

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
            $test = $this->elapsedSecondsInWorkingTime($from, $try);

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
        if ($this->compareDateTime($start, $finish) <= 0) {
            $midSec = (int) floor($start->diffInSeconds($finish, false) / 2);
            $mid = $start->copy()->addSeconds($midSec);

            $test = $this->elapsedSecondsInWorkingTime($from, $mid);

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