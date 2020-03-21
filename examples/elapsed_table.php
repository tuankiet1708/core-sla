<table border="1" cellpadding="10" style="min-width: 350px; width: 50vw; margin: 10px 0px;">
    <caption>
        <b>Elapsed Working Time</b>
        <br/>
        <span>
            <b>From</b> <?php echo $from->toDateTimeString() ?> |
            <b>To</b> <?php echo $to->toDateTimeString() ?>
        </span>
    </caption>
    <thead>
        <th>Date</th>
        <th>Working Time</th>
        <th>Break Time</th>
        <th>Total</th>
    </thead>
    <tbody>
        <?php foreach ($timeMatches as $index => $time) { ?>
            <?php 
                switch ($time['day']) {
                    case 1: $d = 'Monday'; break;
                    case 2: $d = 'Tuesday'; break;
                    case 3: $d = 'Wednesday'; break;
                    case 4: $d = 'Thursday'; break;
                    case 5: $d = 'Friday'; break;
                    case 6: $d = 'Saturday'; break;
                    case 0: $d = 'Sunday'; break;
                    default: $d = 'N/A';
                }

            ?>
            <tr>
                <td>
                    <?php echo "$d (" . $time['date']->toDateString() . ")" ?>
                </td>

                <td>
                    <?php 
                        echo    str_pad($time['from_hour'], 2, '0', STR_PAD_LEFT) . ':' .
                                str_pad($time['from_minute'], 2, '0', STR_PAD_LEFT) . ' - ' . 
                                str_pad($time['to_hour'], 2, '0', STR_PAD_LEFT) . ':' .
                                str_pad($time['to_minute'], 2, '0', STR_PAD_LEFT);
                    ?>
                </td>

                <td>
                    <?php 
                        foreach ((array) array_get($time, 'break') as $break) {
                            echo str_pad($break['from_hour'], 2, '0', STR_PAD_LEFT) . ':' .
                                str_pad($break['from_minute'], 2, '0', STR_PAD_LEFT) . ' - ' . 
                                str_pad($break['to_hour'], 2, '0', STR_PAD_LEFT) . ':' .
                                str_pad($break['to_minute'], 2, '0', STR_PAD_LEFT) . '<br/>';    
                        }
                    ?>
                </td>

                <td align="right">
                    <?php 
                        $partialFrom = $time['date']->copy()->addHours($time['from_hour'])->addMinutes($time['from_minute']);

                        if (isset($timeMatches[$index + 1]) && $time['to_hour'] === 0 && $time['to_minute'] === 0) {
                            $partialTo = $time['date']->copy()->addDay();
                        } else {
                            $partialTo = $time['date']->copy()->addHours($time['to_hour'])->addMinutes($time['to_minute']);
                        }
                        
                        $total = $calendar->elapsedSecondsInWorkingTime($partialFrom, $partialTo);

                        echo "<b>" . $calendar->secondsForHumans($total) . "</b>";
                        echo "<br/>($total seconds)";
                    ?>
                </td>
            </tr>  
        
        <?php } ?>
    </tbody>
    <tfoot>
        <tr style="font-weight: bold">
            <td align="right"><b>Total</b></td>
            <td colspan="3" align="right">
                <?php echo $calendar->secondsForHumans($elapsed) ?>     
                <br/> 
                (<?php echo $elapsed ?> seconds)                         
            </td>
        </tr>
    </tfoot>
</table>