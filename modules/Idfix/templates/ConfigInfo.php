<?php $counter = 0; ?>



<?php foreach( $aTables as $aInfo): 
        extract($aInfo);
        $newline = ((++$counter%3)==0 );
        if ( $newline) print '<div class="row">';
?>
    
    <div class="col col-sm-4">
        <div class="panel <?php print $class ?>">
            <div class="panel-heading">
              
              <div class="panel-title">
                <h3><?php print $icon ?> <?php print $title ?></h3>
              </div>
              
            </div>
            <div class="panel-body">
             
            <em><?php print $description ?$description . '<br /><br />' : '' ?></em>
            <?php print $data ?>
            </div>
        </div>
    </div>

    <?php if ( $newline ) print '</div>'; ?>


<?php endforeach; ?>


