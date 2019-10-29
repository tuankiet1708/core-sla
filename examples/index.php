<?php

use Leo\SLA\Calendar;
use Carbon\Carbon;

require_once __DIR__ . '/../vendor/autoload.php';

$calendar = config_get('sla.8_5_calendar');
$calendar = new Leo\SLA\Calendar($calendar);

// Saturday, 2019-10-26 11:37:45.0 Asia/Singapore (+08:00)
dd($calendar->isWorkingDay(time()));
// dd($calendar->parseHolidays());

dd($calendar->isHoliday());

dd($calendar);
