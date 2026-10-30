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




        // update the content
		$clean_html = $purifier->purify( $_POST['content-' . $currentBranchRow['branchId'] . '-' . $regionRow['regionId'] . '-' . $repeatRow['rowId']] ?? '' );
		
        $query = sprintf("UPDATE a_content SET content = '%s', status = '%s' WHERE contentId = '%s'",
            db_escape( $clean_html ),
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

?>
    <strong <?php if ($editMode == 'Draft') { echo 'style="color: red;"'; } ?>><?php echo $editMode ?> Content:</strong>
    <p><textarea name="content-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" id="htmlEditor-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" class="htmlEditor" style="width: 90%"><?php echo $contentToEdit ?></textarea></p>


        <input type="hidden" name="draftContentId-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" value="<?php echo $contentDraft['contentId'] ?? null ?>" />
        <input type="hidden" name="publishedContentId-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" value="<?php echo $contentPublished['contentId'] ?? null ?>" />
        
<script type="text/javascript">
// WYSIWYG Editor
	$("#htmlEditor-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>").tinymce({
		theme : "<?php if (($parameters->basicMode ?? null) == true) { echo 'simple'; } else { echo 'advanced'; } ?>",
		plugins : "paste,inlinepopups,advimage,autoresize",
		dialog_type : "modal",		
		theme_advanced_resizing : true,
		theme_advanced_resize_horizontal : false,
		autoresize_max_height: 500,
		paste_auto_cleanup_on_paste : true,
        paste_remove_styles: true,
        paste_remove_styles_if_webkit: true,
        paste_strip_class_attributes: "all",
		document_base_url : "/",
		content_css : "/frontend/css/user.css? "+ new Date().getTime(),
		relative_urls : false,
		file_browser_callback : elFinderBrowser,
		onchange_callback : tinymceChange
     
   });
   
   function tinymceChange(inst) {
		changesMade = true;
   }
   
   function elFinderBrowser (field_name, url, type, win) {
   
	  var elfinder_url = '/admin/components/tiny_mce/plugins/elfinder/elfinder.htm';    // use an absolute path!
	  tinyMCE.activeEditor.windowManager.open({
	    file: elfinder_url,
	    title: 'elFinder 2.0',
	    width: 900,  
	    height: 450,
	    resizable: 'yes',
	    inline: 'yes',    // This parameter only has an effect if you use the inlinepopups plugin!
	    popup_css: false, // Disable TinyMCE's default popup CSS
	    close_previous: 'no'
	  }, {
	    window: win,
	    input: field_name
	  });
	  return false;
	}
</script>