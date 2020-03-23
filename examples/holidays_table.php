<table border="1" cellpadding="10" style="min-width: 768px; width: 70vw; margin: 10px 0px;">
    <caption><b>Holidays</b></caption>
    <thead>
        <th>Date</th>
        <th>Name</th>
        <th>Repeat</th>
    </thead>
    <tbody>
        <?php foreach ($calendar->getHolidays($time) as $date) { ?>
        
            <tr <?php echo (!empty($holidayMatches) && $holidayMatches[0]['date']->equalTo($date['date'])) ? 'style="color:red;font-weight:bold"' : '' ?> >
                <td>
                    <?php echo $date['date']->toDateString() ?>
                </td>

                <td>
                    <?php echo $date['name'] ?>
                </td>

                <td>
                    <?php echo $date['repeat'] ?>
                </td>
            </tr>  
        
        <?php } ?>
    </tbody>
</table>