<table border="1" cellpadding="10" style="min-width: 768px; width: 70vw; margin: 10px 0px;">
    <caption>
        <b>Elapsed Working Time <?php echo empty($withNonCountingTimeRanges) ? '' : ' with Non-counting Time Ranges' ?></b>
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
        <th>Non-counting Time</th>
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

                <td>
                    <?php 
                        foreach ((array) array_get($time, 'skip') as $skip) {
                            echo $skip[0]->format('H:i:s') . ' - ' .
                                (empty($skip[1]) ? '∞' : $skip[1]->format('H:i:s')) . '<br/>';    
                        }
                    ?>
                </td>

                <td align="right">
                    <?php 
                        echo "<b>" . $calendar->secondsForHumans($tmp = $time['partial_elapsed']) . "</b>";
                        echo "<br/>($tmp seconds)";
                    ?>
                </td>
            </tr>  
        
        <?php } ?>
    </tbody>
    <tfoot>
        <tr style="font-weight: bold">
            <td align="right"><b>Total</b></td>
            <td colspan="4" align="right">
                <?php echo $calendar->secondsForHumans($elapsed) ?>     
                <br/> 
                (<?php echo $elapsed ?> seconds)                         
            </td>
        </tr>
    </tfoot>
</table>