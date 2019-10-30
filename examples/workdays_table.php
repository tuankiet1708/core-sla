<table border="1" cellpadding="10" style="min-width: 350px; width: 50vw; margin: 10px 0px;">
    <caption><b>Workdays</b></caption>
    <thead>
        <th>Day</th>
        <th>Working Time</th>
        <th>Break Time</th>
    </thead>
    <tbody>
        <?php foreach ($calendar->getWorkdays() as $day) { ?>
        
            <tr>
                <td>
                    <?php
                        switch ($day['day']) {
                            case 1: $d = 'Monday'; break;
                            case 2: $d = 'Tuesday'; break;
                            case 3: $d = 'Wednesday'; break;
                            case 4: $d = 'Thursday'; break;
                            case 5: $d = 'Friday'; break;
                            case 6: $d = 'Saturday'; break;
                            case 0: $d = 'Sunday'; break;
                            default: $d = 'N/A';
                        }
                        echo $d;
                    ?>
                </td>

                <td>
                    <?php echo str_pad($day['from_hour'], 2, '0', STR_PAD_LEFT) . ':' .
                               str_pad($day['from_minute'], 2, '0', STR_PAD_LEFT) . ' - ' . 
                               str_pad($day['to_hour'], 2, '0', STR_PAD_LEFT) . ':' .
                               str_pad($day['to_minute'], 2, '0', STR_PAD_LEFT)
                    ?>
                </td>

                <td>
                    <?php 
                        foreach ((array) array_get($day, 'break') as $time) {
                            echo str_pad($time['from_hour'], 2, '0', STR_PAD_LEFT) . ':' .
                               str_pad($time['from_minute'], 2, '0', STR_PAD_LEFT) . ' - ' . 
                               str_pad($time['to_hour'], 2, '0', STR_PAD_LEFT) . ':' .
                               str_pad($time['to_minute'], 2, '0', STR_PAD_LEFT) . '<br/>';                            
                        }
                    ?>
                </td>
            </tr>  
        
        <?php } ?>
    </tbody>
</table>