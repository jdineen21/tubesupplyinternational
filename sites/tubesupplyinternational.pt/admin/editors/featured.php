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

	$action = $_POST['editorActionMode'] ?? '';
    $draftContentId = $_POST['draftContentId-' . $currentBranchRow['branchId'] . '-' . $regionRow['regionId'] . '-' . $repeatRow['rowId']] ?? '';
    $publishedContentId = $_POST['publishedContentId-' . $currentBranchRow['branchId'] . '-' . $regionRow['regionId'] . '-' . $repeatRow['rowId']] ?? '';

    if ($action == 'delete') {

		$query = sprintf("DELETE FROM a_content WHERE contentId = '%s'",
            db_escape( $draftContentId ));
        db_query($query);

    } else {
        // save and publish
        if ($action == 'publish') {

            if (!$publishedContentId) {

                $status = 'published';
                $contentId = $draftContentId;


            } else {
                // delete the draft version if it exists
                if ($draftContentId) {
                    $query = sprintf("DELETE FROM a_content WHERE contentId = '%s'",
                        db_escape( $draftContentId ));
                    db_query($query);
                }

                $status = 'published';
                $contentId = $publishedContentId;
            }

        }


        // save draft
        if ($action == 'draft') {

            $status = 'draft';
            $contentId = $draftContentId;

        }


        // check if content already exists
        if (  ($action == 'draft' && !$draftContentId) || (!$draftContentId && !$publishedContentId)  ) {
            $query = sprintf("INSERT INTO a_content SET branchId = '%s', regionId = '%s', repeatRegionRowId = '%s'",
                db_escape( $currentBranchRow['branchId'] ? $currentBranchRow['branchId']:0 ),
                db_escape( $regionRow['regionId'] ),
				db_escape( $repeatRow['rowId'] ? $repeatRow['rowId'] : 0 ) );
            db_query($query);

            $contentId = db_insert_id();
        }


		$content = $_POST['content-' . $currentBranchRow['branchId'] . '-' . $regionRow['regionId'] . '-' . $repeatRow['rowId']] ?? '';


        // update the content
		$query = sprintf("UPDATE a_content SET content = '%s', status = '%s' WHERE contentId = '%s'",
            db_escape( $content ),
            db_escape( $status ),
            db_escape( $contentId ));
        db_query($query);
		
		
		
		
		

    } // end delete if

    $updated = true;

}

$editMode ='';


$repeatRegionRowId = 0;
if ($repeatRow['rowId'] != '') {
	$repeatRegionRowId = $repeatRow['rowId'];
}
// get the content DRAFT
$query = sprintf("SELECT * FROM a_content WHERE branchId = '%s' AND regionId = '%s' AND repeatRegionRowId = '%s' AND status = 'draft'",
    db_escape( $currentBranchRow['branchId'] ),
    db_escape( $regionRow['regionId'] ),
	db_escape( $repeatRegionRowId ) );
$contentDraftResult = db_query($query);
$contentDraft = db_fetch_assoc($contentDraftResult);

// get the content PUBLISHED
$query = sprintf("SELECT * FROM a_content WHERE branchId = '%s' AND regionId = '%s' AND repeatRegionRowId = '%s' AND status = 'published'",
    db_escape( $currentBranchRow['branchId'] ),
    db_escape( $regionRow['regionId'] ),
	db_escape( $repeatRegionRowId ) );
$contentPublishedResult = db_query($query);
$contentPublished = db_fetch_assoc($contentPublishedResult);



// determine which content to edit
if (db_num_rows($contentDraftResult) > 0 && db_num_rows($contentPublishedResult) > 0) {
    $editMode = 'Draft';
}
if (db_num_rows($contentDraftResult) > 0 && db_num_rows($contentPublishedResult) == 0) {
    $editMode = 'Draft';
}
if (db_num_rows($contentDraftResult) == 0 && db_num_rows($contentPublishedResult) > 0) {
    $editMode = 'Published';
}

if ($editMode == 'Draft') {
    $contentToEdit = $contentDraft['content'] ?? null;
} else {
    $contentToEdit = $contentPublished['content'] ?? null;
}



// get categories
$query = sprintf("SELECT * FROM a_tree WHERE parentId = '%s' ORDER BY type",
    db_escape( $currentBranchRow['branchId'] ));
$res = db_query($query);
?>


    <strong <?php if ($editMode == 'Draft') { echo 'style="color: red;"'; } ?>><?php echo $editMode ?> Content:</strong>
    
	
	<p>
<?php if (db_num_rows($res) > 0) { ?>
<select name="content-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" class="chzn-select" onchange="changesMade=true" class="big">
     <?php while($option = db_fetch_assoc($res)) { ?>
	 <option value="<?php echo $option['branchId']; ?>" <?php if ($option['branchId'] == $contentToEdit) {echo 'selected="selected"';} ?>><?php echo $option['title']; ?></option>
	 <?php } ?>
</select> 

<?php } else { ?>
	No items have been added to this category yet.
<?php } ?>

</p>
	
	
	
	

        <input type="hidden" name="draftContentId-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" value="<?php echo $contentDraft['contentId'] ?? null ?>" />
        <input type="hidden" name="publishedContentId-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" value="<?php echo $contentPublished['contentId'] ?? null ?>" />
        
		  


