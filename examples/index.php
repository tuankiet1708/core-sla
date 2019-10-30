<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Leo\SLA\Calendar;
use Carbon\Carbon;

$config = config_get('sla.8_5_calendar');
$calendar = new Calendar($config); 

// $time = Carbon::createFromTimestamp(time(), $calendar->timezone());
$time = Carbon::parse('2019-01-01 00:00', $calendar->timezone());

// Table of holidays
load_view_path(__DIR__ . '/holidays_table.php', compact('calendar', 'time'));
// Table of workdays
load_view_path(__DIR__ . '/workdays_table.php', compact('calendar', 'time'));

echo "<pre style='font-size:18'>";
echo "<b>Kind of calendar</b>: " . ($calendar->is247Calendar() ? "247" : "custom");
echo "\n";
echo "<b>Timezone</b>: " . $calendar->timezone();
echo "\n";
echo "<b>Current time</b>: " . $time->toDayDateTimeString();
echo "\n";
echo "Is it a <b>holiday</b>? " . json_encode($calendar->isHoliday($time));
echo "\n";
echo "Is it a <b>working day</b>? " . json_encode($calendar->isWorkingDay($time));
echo "\n";
echo "Is it time to <b>work</b>? " . json_encode($calendar->isTimeToWork($time));
echo "\n";
echo "Is it time for a <b>break</b>? " . json_encode($calendar->isTimeToTakeARest($time));