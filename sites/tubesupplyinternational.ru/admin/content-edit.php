<?php $currentSection = "content"; $currentPageName = "Edit Content"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php include("inc/phpthumb/phpthumb.class.php") ?>
<?php require_once('inc/htmlpurifier/HTMLPurifier.auto.php'); ?>
<?php

$config = HTMLPurifier_Config::createDefault();
$config->set('Attr.AllowedFrameTargets', array('_blank','_self'));
$purifier = new HTMLPurifier($config);

$branchId = $_GET['branchId'] ?? '';
if ($branchId !== '') {
	$branchId = (string) (int) $branchId; // ids are numbers; stops anything else in the URL reaching the page
}

// set below or by the editors
$updated = false;
$uploadRefused = false;
$setOpenTab = 0;
$openThisBox = 0;
$editMode = '';
$itemId = '';

if (($_POST['editorActionMode'] ?? '') != '' && $branchId != '') {

	// update some tree fields

        $query = sprintf("UPDATE a_tree SET editedBy = '%s' WHERE branchId = '%s'",
            db_escape( strip_tags($userInfoArr["username"]) ),
            db_escape( $branchId ));
        db_query($query);

}





if (($_POST['action'] ?? '') == 'downloads') {

	
	$n = 0;
	foreach(($_POST['id'] ?? array()) as $id) {
		
		if ($id == 'new') {
			
				
				$tempFile = $_FILES['uploadNew']['tmp_name'] ?? '';				
				if ($tempFile && !isSafeUploadName($_FILES['uploadNew']['name'])) {
					$tempFile = ''; // not saved: a file the server could run as code
					$uploadRefused = true;
				}
			
				if ($tempFile) {
								
					$targetFolder = '/media/downloads/';		
					$targetPath = $_SERVER['DOCUMENT_ROOT'] . $targetFolder;
					$targetFile = $targetPath . $_FILES['uploadNew']['name'];
					

					if (is_file($targetFile)) { 
						// file already exisits
						if (move_uploaded_file($tempFile,$targetFile)) {
						
							$query = sprintf("SELECT * FROM a_downloads WHERE fileName = '%s'",
					    		db_escape( $_FILES['uploadNew']['name'] ));
							$result = db_query($query);
							$oldEntry = db_fetch_assoc($result);
							
							$newTitle = $_POST['title'][$n] ?? '';
							if ($newTitle == '') {
								$newTitle = $oldEntry['title'];
							}
							
							$newDescription = $_POST['description'][$n] ?? '';
							if ($newDescription == '') {
								$newDescription = $oldEntry['description'];
							}
							
							$query = sprintf("UPDATE a_downloads SET title = '%s', description = '%s' WHERE fileId = '%s'",								
								db_escape( cleanAndStrip($newTitle) ),
								db_escape( cleanAndStrip($newDescription) ),
								db_escape( $oldEntry['fileId'] )
							);	
							db_query($query);
							
						
						}
					
					} else {
						
						if (move_uploaded_file($tempFile,$targetFile)) {
													
							$path_parts = pathinfo($_FILES["uploadNew"]["name"]);
							
							$query = sprintf("INSERT INTO a_downloads SET title = '%s', description = '%s', fileName = '%s', branchId = '%s', fileType = '%s'",
								db_escape( $_POST['title'][$n] ?? '' ),
								db_escape( $_POST['description'][$n] ?? '' ),
								db_escape( $_FILES['uploadNew']['name'] ),
								db_escape( $branchId ),
								db_escape( strtolower($path_parts['extension']) )
							);	
							db_query($query);
							
						}
						
					}

					
					
					
										
					
				}
				
			
		} else {
			
	
			$query = sprintf("UPDATE a_downloads SET title = '%s', description = '%s', sortOrder = '%s' WHERE fileId = '%s' ",
				db_escape( $_POST['title'][$n] ?? '' ),
				db_escape( $_POST['description'][$n] ?? '' ),				
				db_escape( $n+1 ),
				db_escape( $id )
			);	
			db_query($query);
					
		}
	
		$n ++;
	}
	
	if ($_POST['deleteDownloadItem'] ?? '') {
		
		$query = sprintf("SELECT * FROM a_downloads WHERE fileId = '%s'",
    		db_escape( $_POST['deleteDownloadItem'] ?? '' ));
		$result = db_query($query);
		$fileToDelete = db_fetch_assoc($result);
		
		$file = $_SERVER['DOCUMENT_ROOT'] . '/media/downloads/' . $fileToDelete['fileName'];
		if (is_file($file)) { unlink($file); }
		
		$query = sprintf("DELETE FROM a_downloads WHERE fileId = '%s'",
   		db_escape( $_POST['deleteDownloadItem'] ?? '' ));
		db_query($query);
		
		
	
	}

	$updated = true;
	$setOpenTab = 4;
	
}




if (($_GET['action'] ?? '') == 'newRepeatRegion') {
	
	$query = sprintf("INSERT INTO a_repeatregionsrows SET repeatRegionId = '%s', templateId = '%s', branchId = '%s', sortOrder = 0, displayName = 'Unnamed item'",
		db_escape( $_GET['repeatRegionId'] ?? '' ),
		db_escape( $_GET['templateId'] ?? '' ),
		db_escape( $_GET['branchId'] ?? '' )
	);	
	db_query($query);
	
	
	header('location: content-edit.htm?branchId=' . ($_GET['branchId'] ?? '') . '&openRepeatRegionRow=' . db_insert_id() . '#repeatRegion' . ($_GET['repeatRegionId'] ?? ''));
		
}



if(!empty($_POST['repeatRegionUpdateId'])) {
	$n = 0;
	
	foreach(($_POST['repeatRegionUpdateId'] ?? array()) as $id) {

		$query = sprintf("UPDATE a_repeatregionsrows SET displayName = '%s', sortOrder = '%s' WHERE rowId = '%s'",
   			db_escape( $_POST['repeatRegionDisplayName'][$n] ?? '' ),
			db_escape( $_POST['repeatRegionSort'][$n] ?? '' ),
			db_escape( $id ) );
		db_query($query);
		$n++;
		
	}

}

if(!empty($_POST['deleteRepeatRegionRow'])) {
	foreach(($_POST['deleteRepeatRegionRow'] ?? array()) as $check) {
		
		// delete images
		$query = sprintf("SELECT a_content.*, a_regions.regionType FROM a_content INNER JOIN a_regions ON a_content.regionId = a_regions.regionId WHERE repeatRegionRowId = '%s' AND regionType = 'image'",
	    	db_escape( $check ));
		$content = db_query($query);
		
		while($item = db_fetch_assoc($content)) {
			
			$thumb = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'thumb/' . $item['contentId'] . '.jpg';
			if (is_file($thumb)) { unlink($thumb); }
			
			$thumbCustom = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'thumb-custom/' . $item['contentId'] . '.jpg';
			if (is_file($thumbCustom)) { unlink($thumbCustom); }
			
			$original = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'original/' . $item['contentId'] . '.jpg';
			if (is_file($original)) { unlink($original); }
			
			$rendered = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'rendered/' . $item['contentId'] . '.jpg';
			if (is_file($rendered)) { unlink($rendered); }
		
		}
		
		// delete content
		$query = sprintf("DELETE FROM a_content WHERE repeatRegionRowId = '%s'",
	    db_escape( $check ));
		db_query($query);
		
		// delete repeatRegionRow
		$query = sprintf("DELETE FROM a_repeatregionsrows WHERE rowId = '%s'",
	    db_escape( $check ));
		db_query($query);
		
	}
}




if (($_GET['action'] ?? '') == 'editCurrent') {
	$branchId = $_SESSION['a_currentBranchId'] ?? '';	
}


if (($_POST['action'] ?? '') == 'delete') {
	
	deleteBranch($_POST['actionId'] ?? '');
	
	$updated = true;

}


if (($_POST['action'] ?? '') == 'submitMeta') {
	
	$title = cleanAndStrip($_POST['title'] ?? '');
	$title = trim(substr($title, 0, 255));
	
	$navTitle = cleanAndStrip($_POST['navTitle'] ?? '');
	$navTitle = trim(substr($navTitle, 0, 255));
	if ($navTitle == '') {
		$navTitle = $title;
	}
	
	$subTitle = cleanAndStrip($_POST['subTitle'] ?? '');
	$subTitle = trim(substr($subTitle, 0, 255));
	
	$urlSegment = generateSlug($_POST['urlSegment'] ?? '');
	if ($urlSegment == '') {
		$urlSegment = generateSlug($title);
	}
	if (($_POST['home'] ?? '') == 'true') {
		$urlSegment = '';
	}
	
	$description = strip_tags($_POST['description'] ?? '');
	$description = trim(substr($description, 0, 255));
	
	$keywords = strip_tags($_POST['keywords'] ?? '');
	$keywords = trim(substr($keywords, 0, 255));
	
	$query = sprintf("UPDATE a_tree SET title = '%s', navTitle = '%s', subTitle = '%s', urlSegment = '%s', description = '%s', keywords = '%s', editedBy = '%s' WHERE branchId = '%s'",
	db_escape( $title ),
	db_escape( $navTitle ),
	db_escape( $subTitle ),
	db_escape( $urlSegment ),
	db_escape( $description ),
	db_escape( $keywords ),
	db_escape( strip_tags($userInfoArr["username"]) ),	
    db_escape( $branchId ));
	db_query($query);
	
	$updated = true;
	$setOpenTab = 2;

}

// current branch
$query = sprintf("SELECT a_tree.*, a_templates.*, a_branchtypes.* FROM a_tree INNER JOIN a_branchtypes ON a_tree.type = a_branchtypes.typeName INNER JOIN a_templates ON a_tree.templateId = a_templates.templateId WHERE branchId = '%s'",
    db_escape( $branchId ));
$branchResult = db_query($query);
$currentBranchRow = db_fetch_assoc($branchResult);
if (!$currentBranchRow) {
	// editing global content (no branch): all branch fields are empty
	$currentBranchRow = array_fill_keys(array('branchId', 'parentId', 'templateId', 'title', 'navTitle', 'subTitle', 'urlSegment', 'description', 'keywords', 'dateAdded', 'allowDelete', 'allowMultiUpload', 'singularName'), '');
}

// vars
$currentTemplateId = $currentBranchRow['templateId'];
if ($currentBranchRow['allowMultiUpload'] == 'Y') {
	$allowMultiUpload = true;	
} else {
	$allowMultiUpload = false;
}

// regions
if ($branchId != '') {
	
	$query = sprintf("SELECT * FROM a_regions WHERE templateId = '%s' AND global = 'N' AND repeatRegionId = 0 ORDER BY cmsOrder",
    	db_escape( $currentTemplateId ));
	$regionsResult = db_query($query);
	
} else {
	// display global content to edit
	$query = sprintf("SELECT * FROM a_regions WHERE global = 'Y' AND repeatRegionId = 0 ORDER BY cmsOrder",
    	db_escape( $currentTemplateId ));
	$regionsResult = db_query($query);
}

// repeat regions
$query = sprintf("SELECT * FROM a_repeatregions WHERE templateId = '%s'",
    db_escape( $currentTemplateId ));
$repeatRegionsResult = db_query($query);

// branch types
$query = sprintf("SELECT * FROM a_branchtypes WHERE typeId <> 1 ORDER BY cmsOrder");
$branchTypesResult = db_query($query);



?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title><?php echo $siteName ?> | <?php echo $currentPageName ?></title>
    <?php include("inc/htmlhead.php") ?>
	<link rel="stylesheet" type="text/css" href="/admin/css/timepicker.css"/>
	<script type="text/javascript" src="/admin/components/spinner/ui.spinner.js"></script>
	<script type="text/javascript" src="/admin/components/ui/timepicker.js"></script>
	
	<script type="text/javascript">
	$(document).ready(function(e) {
		
		//datetimepicker
	   $(".datetimepicker").datetimepicker();
	   $('.timepicker').timepicker({});

	
		// uploadButton  ( Add file )
			$('#uploadButton').hover(function(){
				$('#upload_b').addClass('hover');
			},function(){
				$('#upload_b').removeClass('hover');
			});	
			
		});
	
	</script>
	
</head>
<body class="<?php echo $currentSection ?>Page">


    <div id="alertMessage" class="error"></div>

    <?php include("inc/header.php") ?>
    <?php include("inc/sidebar.php") ?>

    <div id="content">
        <div class="inner">

            <?php include("inc/topcolumn.php") ?>

            <!-- Start content -->
			

            <!-- Quarter -->
            <div class="widgets">
                <div class="oneFour">
					
					
					
					
					
					
					
					
					
					<!-- PAGES -->
					<div class="widget">
                        <div class="header"><span><a href="javascript:;" onclick="toggleBranchTypeBox(1);"><span class="ico gray directional_right" id="branchTypeBoxIcon-1"></span></a>Pages</span>
							<div id="buttom_top"><a class="uibutton icon add" href="new-branch.htm?type=page" >New</a></div>			
						</div>
						
                        <div class="content siteTreePages" style="display: none;" id="branchTypeBox-1">

							
                            <ul class="siteTree non-sortable ">

                                <li class="ico gray sphere <?php if (($treeItem['branchId'] ?? null) == $currentBranchRow['branchId']) { echo 'current'; } ?>"><a href="content-edit.htm">Edit Global Content</a></li>



								<?php generateListOfPages($currentBranchRow['branchId']); ?>


                            </ul>
							<div class="clear"></div>
                        </div>
                    </div>
					
					
					
					
					
					
					
					
					
					
					
					
					
					<?php while($branchType = db_fetch_assoc($branchTypesResult)) {
					if ($branchType['visibleInCMS'] == 'Y') {
					?>
                    <div class="widget">
                        <div class="header"><span><a href="javascript:;" onclick="toggleBranchTypeBox(<?php echo $branchType['typeId'] ?>);"><span class="ico gray directional_right" id="branchTypeBoxIcon-<?php echo $branchType['typeId'] ?>"></span></a><?php echo  ucfirst($branchType['pluralName']) ?></span>
							<?php if ($branchType['allowNew'] == 'Y') { ?><div id="buttom_top"><a class="uibutton icon add" href="new-branch.htm?type=<?php echo $branchType['typeName'] ?>" >New</a></div><?php } ?>			
						</div>
						
                        <div class="content" style="display: none;" id="branchTypeBox-<?php echo $branchType['typeId'] ?>">

							<?php if ($branchType['sortable'] == 'Y') { ?>
							<form id="settingsForm" action="new-branch.htm" method="post" class="validate" >
                        
								<div class="section" style="margin-bottom: 15px;">
		                              <label>Order by:<small>On site</small></label>   
		                              <div>
		                              	  <select name="orderBy" id="order-<?php echo $branchType['typeId'] ?>">
		                                    <option value="dragdrop" <?php if ($branchType['orderBy'] == 'sortOrder' && $branchType['orderByDirection'] == 'ASC') { echo 'selected="selected"';} ?>>As ordered below</option>
											<option value="dateAsc" <?php if ($branchType['orderBy'] == 'dateAdded' && $branchType['orderByDirection'] == 'ASC') { echo 'selected="selected"';} ?>>Date ascending</option>
											<option value="dateDesc" <?php if ($branchType['orderBy'] == 'dateAdded' && $branchType['orderByDirection'] == 'DESC') { echo 'selected="selected"';} ?>>Date descending</option>
											<option value="alphaAsc" <?php if ($branchType['orderBy'] == 'title' && $branchType['orderByDirection'] == 'ASC') { echo 'selected="selected"';} ?>>Alpha ascending</option>
											<option value="alphaDesc" <?php if ($branchType['orderBy'] == 'title' && $branchType['orderByDirection'] == 'DESC') { echo 'selected="selected"';} ?>>Alpha descending</option>
		                                </select>       
									</div>
		
		                        </div>
							</form>
							<script type="text/javascript">						
								$('#order-<?php echo $branchType['typeId'] ?>').change(function(){
									typeId = <?php echo $branchType['typeId'] ?>;
									orderBy = $(this).val();
									postData = 'typeId=' + typeId + '&orderBy=' + orderBy;
									$.ajax({
                                        type: "POST",
                                        url: "ajax/branch-order-sort-update.htm",
                                        data: postData
                                    });
									loading('Saving');
									setTimeout("unloading()",1000); 
								});
							</script>
							<?php } ?>


                            <ul class="siteTree <?php if ($branchType['sortable'] == 'Y') { echo 'sortable'; } else { echo 'non-sortable'; } ?>" id="sort-<?php echo $branchType['typeId'] ?>">

                                <?php if (strtolower($branchType['typeName']) == 'page') { ?><li class="ico gray sphere <?php if (($treeItem['branchId'] ?? null) == $currentBranchRow['branchId']) { echo 'current'; } ?>"><a href="content-edit.htm">Edit Global Content</a></li><?php } ?>

							<?php
							// tree
							$viewOrder = 'sortOrder';
							if ($branchType['sortable'] == 'N' && $branchType['inStructure'] == 'N') {
								$viewOrder = $branchType['orderBy'] . ' ' . $branchType['orderByDirection'];
							}
							if (strtolower($branchType['typeName']) == 'page') {
								$viewOrder = $branchType['orderBy'] . ' ' . $branchType['orderByDirection'];
							}
							$query = sprintf("SELECT * FROM a_tree WHERE type = '" . $branchType['typeName'] . "' ORDER BY " . $viewOrder);
							$treeResult = db_query($query);
							?>
                            <?php while($treeItem = db_fetch_assoc($treeResult)) { ?>

                                <li id="item<?php echo $branchType['typeId'] ?>_<?php echo $treeItem['branchId'] ?>" class="ico gray <?php if ($branchType['sortable'] == 'N') { echo 'document'; } else { echo 'sort'; } ?> <?php if ($treeItem['branchId'] == $currentBranchRow['branchId']) { echo 'current'; $openThisBox = $branchType['typeId']; } ?>"><a href="content-edit.htm?branchId=<?php echo $treeItem['branchId'] ?>"><?php echo $treeItem['title'] ?></a></li>

                            <?php } ?>
                            </ul>
							<div class="clear"></div>
                        </div>
                    </div>
					<?php } // end if ($branchType['visibleInCMS'] == 'Y') ?>
					
						<?php if ($branchType['sortable'] == 'Y') { ?>
						<script type="text/javascript">
						
							$('#sort-<?php echo $branchType['typeId'] ?>').sortable({							
								update: function(event,ui) {
									serialized = $(this).sortable('serialize');
									
									$.ajax({
                                        type: "POST",
                                        url: "ajax/branch-order-update.htm?type=item<?php echo $branchType['typeId'] ?>",
                                        data: serialized
                                    })
								}
							});
						
						</script>
						<?php } ?>
					<?php } ?>
					
					
					
					<script type="text/javascript">			
					function toggleBranchTypeBox(id) {
										
					
						$('#branchTypeBox-'+id).slideToggle('fast', function(){
							if ($(this).is(":hidden")) {
								//closed									
								$('#branchTypeBoxIcon-'+id).removeClass('directional_down');
								$('#branchTypeBoxIcon-'+id).addClass('directional_right');
							} else {
								//open
								$('#branchTypeBoxIcon-'+id).removeClass('directional_right');
								$('#branchTypeBoxIcon-'+id).addClass('directional_down');								
							}
						});
						
					}
					<?php if (!$openThisBox) {
						$openThisBox = 1;
					}
						echo 'toggleBranchTypeBox(' . $openThisBox . ');';					
					?>
					</script>
					
					
					
                </div>

                <div class="threeFour">
                    <div class="widget">
                        <div class="header"><span><span class="ico gray pencil"></span>Edit Content - <?php if ($branchId != '') { echo $currentBranchRow['title'];} else { echo 'Global'; } ?></span></div>
                        <div class="content">


                        <div id="UITab">
                        <ul class="tabs" >
                            <li id="tabButton1"><a href="#tab1">Content</a></li>
							<?php if ($allowMultiUpload) { ?>
							<li id="tabButton4"><a href="#tab4">Downloads</a></li>
							<?php } ?>
								
                            <?php if ($branchId != '') { ?>
							<li id="tabButton2"><a href="#tab2">Meta</a></li>
							<li id="tabButton3"><a href="#tab3">Actions</a></li>
							<?php } ?>
                        </ul>

                        <div class="tab_container" >

                            <div id="tab1" class="tab_content">
                                <div class="load_page">
                                    <div class="formEl_b">







                                        <!-- content edit tab -->

										<form action="content-edit.htm?branchId=<?php echo $currentBranchRow['branchId'] ?>" method="post" class="validate">
                                        <?php
                                        $repeatRow = array('rowId' => ''); // these regions are not in a repeat region
                                        while($regionRow = db_fetch_assoc($regionsResult)) { ?>

											<a name="<?php echo $regionRow['regionId'] ?>"></a>
                                            <fieldset style="margin-bottom: 25px">
                                                <legend style="text-transform: capitalize"><?php echo $regionRow['regionName'] ?></legend>

                                                <?php if ($regionRow['description']) { ?><p><?php echo $regionRow['description'] ?></p><?php } ?>

                                                <?php include("editors/" . strtolower($regionRow['regionType']) . ".php") ?>

                                            </fieldset>

                                        <?php } ?>
										
										
										
										
										
										
										<script type="text/javascript">
										function newRepeatRegion() {
											if (changesMade) {
																								
												$.confirm({
													'title': 'Warning',
													'message': " <strong>YOU HAVE UNSAVED CHANGES</strong><br /><span style=\'color:red\'>Click Continue to go back and save your changes.</span> ",
													'buttons': 
														{
															'Continue'	: {'class'	: ''}
														}
												
												});
																 
												return false;
												
											} else {
												return true;
											}											
										}
										</script>
										
										<!-- -------------------- Repeat regions -->

                                        <?php while($repeatRegionRow = db_fetch_assoc($repeatRegionsResult)) { ?>
										<a name="repeatRegion<?php echo $repeatRegionRow['repeatRegionId'] ?>"></a>
                                        <fieldset style="margin-bottom: 25px" id="repeatRegionId-<?php echo $repeatRegionRow['repeatRegionId'] ?>">
											<div style="position: absolute; top: 0px; right: 10px;"><a class="uibutton icon add" href="content-edit.htm?action=newRepeatRegion&repeatRegionId=<?php echo $repeatRegionRow['repeatRegionId'] ?>&templateId=<?php echo $currentTemplateId ?>&branchId=<?php echo $branchId ?>" onclick="return newRepeatRegion();">New</a></div>
                                            <legend style="text-transform: capitalize"><?php echo $repeatRegionRow['regionName'] ?> - Repeatable Region</legend>

                                            <?php if ($repeatRegionRow['description']) { ?><p><?php echo $repeatRegionRow['description'] ?></p><?php } ?>


                                            <?php
                                            $query1 = sprintf("SELECT * FROM a_repeatregionsrows WHERE repeatRegionId = '%s' AND templateId = '%s' AND branchId = '%s' ORDER BY sortOrder",											
                                            db_escape( $repeatRegionRow['repeatRegionId'] ),
											db_escape( $currentTemplateId ),
											db_escape( $branchId ));
                                            $repeatRowResult = db_query($query1);

                                            while($repeatRow = db_fetch_assoc($repeatRowResult)) {
                                                
												$query2 = sprintf("SELECT * FROM a_regions WHERE templateId = '%s' AND repeatRegionId = '%s' ORDER BY cmsOrder",
	                                            db_escape( $currentTemplateId ),
												db_escape( $repeatRow['repeatRegionId'] ));
	                                            $rResult = db_query($query2);

												?>
												
												<fieldset style="margin-top: 10px;" id="rowId_<?php echo $repeatRow['rowId']?>">
													
													<legend>
														<a href="javascript:;" onclick="$('#repeatRegion-<?php echo $repeatRow['rowId']?>').slideToggle();">
															<span class="ico gray directional_right"></span>
															<?php if ($repeatRow['sortOrder'] > 0) { echo $repeatRow['sortOrder'] . ':'; }?> <?php echo ucfirst($repeatRow['displayName']) ?>
														</a>
													</legend>
												
												<div <?php if (($_GET['openRepeatRegionRow'] ?? '') == $repeatRow['rowId']) { echo 'style="display: block"'; } ?> class="repeatRegionRowsContent" id="repeatRegion-<?php echo $repeatRow['rowId'] ?>" >
												
												<h4>Options</h4>
												<input type="hidden" name="repeatRegionUpdateId[]" value="<?php echo $repeatRow['rowId'] ?>">
												Display Name: <input type="text" name="repeatRegionDisplayName[]" value="<?php echo $repeatRow['displayName'] ?>">&nbsp;&nbsp;&nbsp;&nbsp;
												Sort Order: <input id="repeatRegionSortId-<?php echo $repeatRow['rowId'] ?>" type="text" name="repeatRegionSort[]" value="<?php echo $repeatRow['sortOrder'] ?>">&nbsp;&nbsp;&nbsp;&nbsp;
												<input type="checkbox" name="deleteRepeatRegionRow[]" id="deleteRepeatRegionRow-<?php echo $repeatRow['rowId'] ?>" value="<?php echo $repeatRow['rowId'] ?>" class="ck"/><label for="deleteRepeatRegionRow-<?php echo $repeatRow['rowId'] ?>">Tick to remove this item (Note: Will delete from draft too).</label>
												<script type="text/javascript">$('#repeatRegionSortId-<?php echo $repeatRow['rowId'] ?>').spinner();</script>
												
												<?php 
	                                            while($regionRow = db_fetch_assoc($rResult)) {
												?>
												
													
													<h4><?php echo $regionRow['regionName']; ?></h4>
													
													<?php if ($regionRow['description']) { ?><p><?php echo $regionRow['description'] ?></p><?php } ?>
												
                                                	<?php include("editors/" . $regionRow['regionType'] . ".php") ?>
													
													
													
													<br>
												<?php
												}// end while region
												?>
												
													
												
												</div><!-- end .repeatRegionRowsContent -->
												</fieldset>
	
	
											<?php
                                            }// end while $repeatRegionRow ?>
											
											

                                        </fieldset>
										
										
										<?php } ?>
										
										
										
										
										
										
										
										
										<input type="hidden" name="editorActionMode" id="editorActionMode">
										<a class="uibutton loading" title="Saving" href="javascript:;" onclick="$('#editorActionMode').val('publish'); changesMade = false; $(this).parents('form').submit();" >Save and Publish</a> <a class="uibutton loading confirm" title="Saving" href="javascript:;" onclick="$('#editorActionMode').val('draft'); changesMade = false; $(this).parents('form').submit();" >Save Draft</a> <?php if ($editMode == 'Draft') { ?><a class="uibutton special Delete" name="Delete DRAFT content - Published content will remain" onclick="$('#editorActionMode').val('delete'); changesMade = false;" >Delete Draft</a><?php } ?>
										
										</form>

                                        



                                        <!-- /END content edit tab -->

                                    </div>

                                </div>
                            </div><!--tab1-->


                            <div id="tab2" class="tab_content">
                                <div class="load_page">
                                    <div class="formEl_b">

                                        <!-- meta edit tab -->

                                        <form action="content-edit.php?branchId=<?php echo $branchId ?>" method="POST" id="metaForm" class="validate">

                                            <div class="section" >
					                            <label>Title *<small>The page title can be used at the top of the page and for SEO</small></label>   
					                            <div> <input type="text" name="title" id="title" class="validate[required] full" value="<?php echo htmlentities($currentBranchRow['title']) ?>" /></div>
					                        </div>
											
											<div class="section" >
					                            <label>Navigation / Menu Title<small>This will be auto generated if left blank</small></label>   
					                            <div> <input type="text" name="navTitle" id="navTitle" class=" full" value="<?php echo htmlentities($currentBranchRow['navTitle']) ?>" /></div>
					                        </div>
											
											<div class="section" >
					                            <label>Navigation Sub Title<small>This will only show if your site supports this feature</small></label>   
					                            <div> <input type="text" name="subTitle" id="subTitle" class=" full" value="<?php echo htmlentities($currentBranchRow['subTitle']) ?>" /></div>
					                        </div>
											
											<div class="section" >
					                            <label>URL Link<small>This will be auto generated if left blank. Spaces and special characters other than '-' will be removed.</small></label>   
					                            <div> <input type="text" name="urlSegment" id="urlSegment" class="full" <?php if ($siteSettings['homeBranchId'] == $currentBranchRow['branchId']) { echo 'disabled="disabled"'; } ?> value="<?php echo $currentBranchRow['urlSegment'] ?>" onkeyup="$('#slug').load('ajax/live-slug.htm?slug=' + encodeURIComponent($(this).val()));" /><span class="f_help"><?php echo $siteURL . getURL($currentBranchRow['branchId'],true) ?>/<span id="slug" style="color: #89bd00;"><?php echo $currentBranchRow['urlSegment'] ?></span></span></div>
					                        </div>
											
											
											<div class="section" >
					                            <label>Description<small>Used for SEO</small></label>   
					                            <div> <input type="text" name="description" id="description" class=" full" value="<?php echo $currentBranchRow['description'] ?>" maxlength="255" /></div>
					                        </div>
											
											
											<div class="section" >
					                            <label>Keywords<small>Used for SEO. Separate each keyword with a comma.</small></label>   
					                            <div> <input type="text" name="keywords" id="keywords" class=" full" value="<?php echo $currentBranchRow['keywords'] ?>" maxlength="255" /></div>
					                        </div>
											
											
											
											
											<div class="section last">
						                        <input type="hidden" name="action" value="submitMeta" />
												<?php if ($siteSettings['homeBranchId'] == $currentBranchRow['branchId']) { ?><input type="hidden" name="home" value="true" /><?php } ?>
						                        <div><a class="uibutton loading submit_form" title="Saving" href="javascript:;" onclick="changesMade = false; " >Save Changes</a></div>
						                    </div>

                                        </form>

                                        <!-- /END meta edit tab -->

                                    </div>

                                </div>
                            </div><!--tab2-->


							<div id="tab3" class="tab_content">
                                <div class="load_page">
                                    <div class="formEl_b">

                                        <!-- actions edit tab -->

                                        <?php if ($currentBranchRow['allowDelete'] == 'Y' && $siteSettings['homeBranchId'] != $currentBranchRow['branchId']) { ?>
										<form action="content-edit.htm" method="POST">
											<input type="hidden" name="action" class="formAction" value="delete">
											<input type="hidden" name="actionId" class="formActionId" value="<?php echo $currentBranchRow['branchId'] ?>">
                                            <a class="uibutton special Delete" name="<?php echo $currentBranchRow['title'] ?>" onclick="changesMade = false;" >Delete this <?php echo $currentBranchRow['singularName'] ?></a>

                                        </form>
										<?php } ?>

                                        <!-- /END actions edit tab -->

                                    </div>

                                </div>
                            </div><!--tab3-->





							<?php if ($allowMultiUpload) {
								$query = sprintf("SELECT * FROM a_downloads WHERE branchId = '%s' ORDER BY sortOrder",										
									db_escape( $branchId ));
                                $downloadResult = db_query($query);
							
							?>							
							<div id="tab4" class="tab_content">
                                <div class="load_page">
                                    <div class="formEl_b">

                                        <!-- downloads edit tab -->

                                        
										<form action="content-edit.htm?branchId=<?php echo $branchId ?>" method="POST" id="uploadForm" enctype="multipart/form-data" class="validate">
										
											
											<table class="display" id="downloadsTable">
												<thead>
												  <tr>
												    <th align="left">Sort</th>
													<th >Type</th>
													<th >File</th>
													<th >Name</th>
													<th >Description</th>
													<th ></th>
												  </tr>
												</thead>
												<tbody>
											  
												  <?php while($item = db_fetch_assoc($downloadResult)) { ?>
												  
												  <tr id="item_<?php echo $item['fileId'] ?>">
												  	<td><div class="handle"></div><input type="hidden" name="id[]" value="<?php echo $item['fileId'] ?>" /></td>
													<td><?php echo $item['fileType'] ?></td>
													<td><?php echo $item['fileName'] ?></td>
													<td><input type="text" class="" name="title[]" value="<?php echo $item['title'] ?>" /></td>
													<td ><input type="text" class="full" name="description[]" value="<?php echo $item['description'] ?>" /></td>													
													<td >
													 
													  <span class="tip" >
														  <a id="1" class="Delete"  name="" onclick="changesMade = false; $('#deleteDownloadItem').val('<?php echo $item['fileId'] ?>');" title="Delete"  >
															  <img src="../admin/images/icon/icon_delete.png" >
														  </a>
													  </span> 
														</td>
												  </tr>
												  <?php } ?>
												  </tbody>
												  	<input type="hidden" name="id[]" value="new" />
													<tr>
													<td></td>
													<td></td>
													<td><input type="file" name="uploadNew" /></td>
													<td ><input type="text" class="" name="title[]" value="" /></td>
													<td ><input type="text" class="full" name="description[]" value="" /></td>
													<td >
													New
														</td>
												  </tr>
												
												
											  </table>

											<script type="text/javascript">
		
							
												$('#downloadsTable tbody').sortable({
													update: function(event,ui) {
														serialized = $(this).sortable('serialize');
														
													}
												});
												
										
											
											</script>	
										
							
										
										
											<div class="section last">
												<input type="hidden" name="deleteDownloadItem" id="deleteDownloadItem" value="">
												<input type="hidden" name="action" class="formAction" value="downloads">
												<input type="hidden" name="actionId" class="formActionId" value="<?php echo $itemId ?>">
												<div><a class="uibutton loading submit_form" title="Saving" href="javascript:;" onclick="changesMade = false; " >Upload / Save Changes</a></div>
											</div>

                                        </form>
										

                                        <!-- /END downloads edit tab -->

                                    </div>

                                </div>
                            </div><!--tab4-->
							<?php } // end if ($allowMultiUpload)  ?>




                        </div>
                        </div><!--/END TABS/-->
                        <div class="clear"></div>



						
						






















                        </div>
                    </div>
                </div>

            </div>




            <?php include("inc/footer.php") ?>
        </div> <!--// End inner -->
    </div> <!--// End content -->

    <script type="text/javascript">
		
		<?php
		
		if ($setOpenTab) {
		?>			
		$(document).ready(function(){
		
			$("ul.tabs li").removeClass("active");						   
			$('#tabButton<?php echo $setOpenTab ?>').addClass("active");  
		  	
		  	$('.tab_content').hide();		
		  	$('#tab<?php echo $setOpenTab ?>').delay(400).show();	
		
		});
		<?php } ?>
	
        <?php if ($updated) { ?>
        showSuccess('Updated');
        <?php } ?>
<?php if ($uploadRefused) { ?>
        showError('That type of file cannot be uploaded.');
<?php } ?>
		$(window).load(function() {
			$('#preloader').fadeOut();
		});
		
		//$('.non-sortable').quickPager({pageSize:15});
    </script>

	<div id="preloader" style="display: block;">Loading...</div>

</body>
</html>
