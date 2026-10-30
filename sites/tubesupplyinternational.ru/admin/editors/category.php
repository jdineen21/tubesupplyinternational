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

        $query = sprintf("UPDATE a_tree SET parentId = '%s' WHERE branchId = '%s'",
            db_escape( $_POST['content-' . $currentBranchRow['branchId'] . '-' .  $regionRow['regionId'] . '-' . $repeatRow['rowId'] ] ?? '' ),
            db_escape( $currentBranchRow['branchId'] ));
        db_query($query);

}

$contentToEdit = $_POST['content-' . $currentBranchRow['branchId'] . '-' .  $regionRow['regionId'] . '-' . $repeatRow['rowId'] ] ?? '';
if ($contentToEdit == '') {
	$contentToEdit = $currentBranchRow['parentId'];
}


// get categories
$query = sprintf("SELECT * FROM a_tree WHERE type = '%s' ORDER BY type",
    db_escape( $parameters->typeName ?? null ));
$repeatRegionsResult = db_query($query);
?>
<strong>Please select a category:</strong>
<p>
<select name="content-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" onchange="changesMade=true" class="big">
	<option value="0" <?php if ($contentToEdit == '0') {echo 'selected="selected"';} ?>>None</option>
     <?php while($option = db_fetch_assoc($repeatRegionsResult)) { ?>	 
	 <option value="<?php echo $option['branchId']; ?>" <?php if ($option['branchId'] == $contentToEdit) {echo 'selected="selected"';} ?>><?php echo $option['title']; ?></option>
	 <?php } ?>
</select> 
</p>

    


		  


