<?php

require_once __DIR__ . '/../vendor/autoload.php';

$calendar = config_get('sla.8_5_calendar');
$calendar = new Leo\SLA\Calendar($calendar);

// dd($calendar->parseHolidays());

dd($calendar->isHoliday());

dd($calendar);
