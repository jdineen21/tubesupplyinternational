<?php
if (!isset($regionRow)) { exit; } // only runs when included by content-edit.php (which checks the login)
// get parameters from region
$regionId = $regionRow['regionId'];

$query = sprintf("SELECT * FROM a_regions WHERE regionId = '%s'",
    db_escape( $regionId ) );
$regionResult = db_query($query);
$region = db_fetch_assoc($regionResult);

$parameters = json_decode($region['parameters'] ?? '');


// save content
if (($_POST['editorActionMode'] ?? '') != '') {

	// update the content

        $query = sprintf("UPDATE a_tree SET dateAdded = '%s' WHERE branchId = '%s'",
            db_escape( setUSDate($_POST['content-' . $currentBranchRow['branchId'] . '-' .  $regionRow['regionId'] ] ?? '') ),
            db_escape( $currentBranchRow['branchId'] ));
        db_query($query);		

}


$contentToEdit = $_POST['content-' . $currentBranchRow['branchId'] . '-' .  $regionRow['regionId'] ] ?? '';
if ($contentToEdit == '') {
	$contentToEdit = getUKDate($currentBranchRow['dateAdded']);
}
?>

<div><input type="text"  id="content-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>" class="datepicker" readonly="readonly" name="content-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>" value="<?php echo $contentToEdit ?>" /></div>

   


		  


