<?php

use Leo\SLA\Calendar;
use Carbon\Carbon;

require_once __DIR__ . '/../vendor/autoload.php';

$calendar = config_get('sla.8_5_calendar');
$calendar = new Leo\SLA\Calendar($calendar); 

var_dump($calendar->isTimeToWork(time()));
var_dump($calendar->isTimeToTakeARest(time()));
