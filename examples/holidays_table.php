<table border="1" cellpadding="10" style="min-width: 350px; width: 50vw; margin: 10px 0px;">
    <caption><b>Holidays</b></caption>
    <thead>
        <th>Date</th>
        <th>Name</th>
        <th>Repeat</th>
    </thead>
    <tbody>
        <?php foreach ($calendar->getHolidays($time) as $date) { ?>
        
            <tr <?php echo ($date['additional_repeat'] ?? false) ? 'style="color:red;font-weight:bold"' : '' ?> >
                <td>
                    <?php echo $date['date']->toDateString() ?>
                </td>

                <td>
                    <?php echo $date['name'] ?>
                </td>

                <td>
                    <?php echo $date['repeat'] . ( ($date['additional_repeat'] ?? false) ? ' (+)' : '' ) ?>
                </td>
            </tr>  
        
        <?php } ?>
    </tbody>
</table>