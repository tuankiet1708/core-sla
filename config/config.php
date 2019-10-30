<?php

return [
    'sla' => [
        /*
        |--------------------------------------------------------------------------
        | SLAs' Configurations
        |--------------------------------------------------------------------------
        |
        */
    
        "8_5_calendar" => [
            // . The value of "type" consists of 2 options: custom, 247.
            "type" => "custom", 
            // . The value of "timezone" listed at https://timezonedb.com/time-zones
            "timezone" => "Asia/Singapore",  

            // . Other extra values
            "code" => "8_5_calendar", 
            "name" => "8/5 Calendar", 
            "description" => "Default 8/5 Calendar", 
            
            // . When the type is 247, "work_week" and "holidays" are meaningless.     
            "work_week" => [
                // . The value of "day" is following:
                //   1: Monday
                //   2: Tuesday
                //   3: Wednesday
                //   4: Thursday
                //   5: Friday
                //   6: Saturday
                //   7: Sunday
                //
                // . The value of "hour" is under 24-hour format.
                // . The values of "break" mean break times in a day.
                [
                    "day" => 1, // Monday
                    "from_hour" => 8, 
                    "from_minute" => 0, 
                    "to_hour" => 17, 
                    "to_minute" => 0, 
                    "break" => [
                        [
                            "from_hour" => 12, 
                            "from_minute" => 0, 
                            "to_hour" => 13, 
                            "to_minute" => 0
                        ]
                    ]
                ], 
                [
                    "day" => 2, // Tuesday
                    "from_hour" => 8, 
                    "from_minute" => 0, 
                    "to_hour" => 17, 
                    "to_minute" => 0, 
                    "break" => [
                        [
                            "from_hour" => 12, 
                            "from_minute" => 0, 
                            "to_hour" => 13, 
                            "to_minute" => 0
                        ]
                    ]
                ], 
                [
                    "day" => 3, // Wednesday
                    "from_hour" => 8, 
                    "from_minute" => 0, 
                    "to_hour" => 17, 
                    "to_minute" => 0, 
                    "break" => [
                        [
                            "from_hour" => 12, 
                            "from_minute" => 0, 
                            "to_hour" => 13, 
                            "to_minute" => 0
                        ]
                    ]
                ], 
                [
                    "day" => 4, // Thursday
                    "from_hour" => 8, 
                    "from_minute" => 0, 
                    "to_hour" => 17, 
                    "to_minute" => 0, 
                    "break" => [
                        [
                            "from_hour" => 12, 
                            "from_minute" => 0, 
                            "to_hour" => 13, 
                            "to_minute" => 0
                        ]
                    ]
                ], 
                [
                    "day" => 5, // Friday
                    "from_hour" => 8, 
                    "from_minute" => 0, 
                    "to_hour" => 17, 
                    "to_minute" => 0, 
                    "break" => [
                        [
                            "from_hour" => 12, 
                            "from_minute" => 0, 
                            "to_hour" => 13, 
                            "to_minute" => 0
                        ]
                    ]
                ],
                [
                    "day" => 6, // Saturday
                    "from_hour" => 8, 
                    "from_minute" => 0, 
                    "to_hour" => 12, 
                    "to_minute" => 0, 
                    "break" => null
                ],
            ],
            "holidays" => [
                // . The value of "date" is formatted as yyyy-mm-dd.
                // . The value of "repeat" consists of 2 options: yearly, never.
                [
                    "name" => "Happy New Year", 
                    "date" => "2019-01-01", 
                    "repeat" => "yearly"
                ], 
                [
                    "name" => "A day as Lunar New Year", 
                    "date" => "2020-01-25", 
                    "repeat" => "never"
                ],
                [
                    "name" => "Extra test", 
                    "date" => "2019-10-28", 
                    "repeat" => "never"
                ]

            ]
        ],

        "default" => [
            // . The value of "type" consists of 2 options: custom, 247.
            "type" => "247",       
            // . The value of "timezone" listed at https://timezonedb.com/time-zones
            "timezone" => "Asia/Singapore", 
            
            "code" => "default",             
            "name" => "24/7 Calendar (Default)", 
            "description" => "Default Business Calendar"

            // . When the type is 247, "work_week" and "holidays" are meaningless. So let "work_week" and "holidays" blank.
        ]
    ]
];