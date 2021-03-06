<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Leo\SLA\Calendar;
use Carbon\Carbon;

// Load a calendar config
// $config = config_get('calendar.default');
$config = config_get('calendar.8_5_calendar');

// initialize a instance of Calendar
$calendar = new Calendar($config); 

$time = Carbon::createFromTimestamp(time(), $calendar->timezone());
// $time = Carbon::parse('2019-01-01 00:00', $calendar->timezone());

// ▒█▀▀█ ░█▀▀█ ▒█▀▀▀█ ▀█▀ ▒█▀▀█ 
// ▒█▀▀▄ ▒█▄▄█ ░▀▀▀▄▄ ▒█░ ▒█░░░ 
// ▒█▄▄█ ▒█░▒█ ▒█▄▄▄█ ▄█▄ ▒█▄▄█ 

echo "<pre style='font-size:18'>";
echo "<b>Kind of calendar</b>: " . ($calendar->is247Calendar() ? "247" : "custom");
echo "\n";
echo "<b>Timezone</b>: " . $calendar->timezone();
echo "\n";
echo "<b>Time</b>: " . $time->format('l, Y-m-d H:i:s');
echo "\n";
echo "Is it a <b>holiday</b>? " . json_encode($calendar->isHoliday($time, $holidayMatches));
// dd($holidayMatches);
echo "\n";
echo "Is it a <b>working day</b>? " . json_encode($isWorkingDay = $calendar->isWorkingDay($time, $workdayMatches));
// dd($workdayMatches);
echo "\n";
echo "Is it time to <b>work</b>? " . json_encode($isTimeToWork = $calendar->isTimeToWork($time, $timeMatches));
// dd($timeMatches);
echo "\n";
echo "Is it time for a <b>break</b>? " . json_encode($isTimeToTakeARest = $calendar->isTimeToTakeARest($time, $breakMatches));
// dd($breakMatches);

// Table of holidays
load_view_path(__DIR__ . '/holidays_table.php', compact('calendar', 'time', 'holidayMatches'));
// Table of workdays
load_view_path(__DIR__ . '/workdays_table.php', compact('calendar', 'time', 'workdayMatches', 'timeMatches', 'breakMatches', 'isWorkingDay', 'isTimeToWork', 'isTimeToTakeARest'));


// ░█▀▀█ ▒█▀▀▄ ▒█░░▒█ ░█▀▀█ ▒█▄░▒█ ▒█▀▀█ ▒█▀▀▀ ▒█▀▀▄ 
// ▒█▄▄█ ▒█░▒█ ░▒█▒█░ ▒█▄▄█ ▒█▒█▒█ ▒█░░░ ▒█▀▀▀ ▒█░▒█ 
// ▒█░▒█ ▒█▄▄▀ ░░▀▄▀░ ▒█░▒█ ▒█░░▀█ ▒█▄▄█ ▒█▄▄▄ ▒█▄▄▀ 

// ================================================>
// Elapsed caculation
$from = Carbon::parse('2019-10-21 09:45', $calendar->timezone());
// $to = Carbon::createFromTimestamp(time(), $calendar->timezone());
$to = Carbon::parse('2019-11-11 13:45', $calendar->timezone());
// $to = Carbon::createFromTimestamp(1572514410, $calendar->timezone());

$timeMatches = [];
$elapsed = $calendar->elapsedSecondsInWorkingTime($from, $to, $timeMatches);

// dd($timeMatches);

// Table of elapsed 
load_view_path(__DIR__ . '/elapsed_table.php', compact('calendar', 'from', 'to', 'timeMatches', 'elapsed'));

// Estimate what timestamp matches the target
$target = $elapsed;
$estimate = $calendar->estimateTimestampMatchesTargetTotal($from, $target);
echo "<b>Estimate</b>\n";
echo "From " . $from->toDateTimeString();
echo " will elapse ";
echo "<b>" . $calendar->secondsForHumans($target) . "</b>";
echo " at around ";
echo "<b>" . $estimate->toDateTimeString() . "</b>.";


// ================================================> 
// Elapsed caculation with NON-COUNTING TIME RANGES 
$timeMatches = [];
$from = Carbon::parse('2019-10-21 09:45', $calendar->timezone());
$to = Carbon::parse('2019-10-23 13:45', $calendar->timezone());
$time = $from->timestamp;
$nonCountingTimeRanges = [
    [$time,            $time + 3600],    
    // [$time,         $time + 60],
    // [$time + 720,   $time + 780],
    // [$time + 10,    $time + 30],
    [$time + 86410,    null],
];
$elapsed = $calendar->elapsedSecondsInWorkingTime($from, $to, $timeMatches, $nonCountingTimeRanges);
$withNonCountingTimeRanges = true;
load_view_path(__DIR__ . '/elapsed_table.php', compact('calendar', 'from', 'to', 'timeMatches', 'elapsed', 'withNonCountingTimeRanges'));