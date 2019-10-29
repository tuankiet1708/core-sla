<?php

namespace Leo\SLA\Exceptions;

use Exception;

class CalendarTimeRangeException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function __construct($message = 'The time range requires 2 values of Carbon.', $code = 411, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
