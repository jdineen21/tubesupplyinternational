<?php $currentSection = "content"; $currentPageName = "Create New Page"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php
$updated = false;

if (($_POST['action'] ?? '') == 'submit') {


	$title = strip_tags($_POST['title'] ?? '');
	$title = trim(substr($title, 0, 255));
	
	$navTitle = $title;
	$urlSegment = generateSlug($title);
	
	$menuId = $_POST['menuId'] ?? '';
	if ($menuId == '') {
		$menuId = 0;
	}
	
	if (($_POST['addAsChild'] ?? '') == '1' && ($_POST['parentId'] ?? '') > 0) {
		
		$query = sprintf("SELECT * FROM a_tree WHERE branchId = '%s'",
    		db_escape( $_POST['parentId'] ?? '' ));
		$getParentResult = db_query($query);
		$getParent = db_fetch_assoc($getParentResult);
		
		$menuId = $getParent['menuId'];
		
	}

    $query = sprintf("INSERT INTO a_tree SET parentId = '%s', type = '%s', templateId = '%s', title = '%s', urlSegment = '%s', navTitle = '%s', menuId = '%s', allowDelete = 'Y', dateAdded = '%s', editedBy = '%s' ",
        db_escape( strip_tags($_POST['parentId'] ?? '') ),
		db_escape( strip_tags($_POST['type'] ?? '') ),
		db_escape( strip_tags($_POST['templateId'] ?? '') ),
		db_escape( strip_tags($title) ),
		db_escape( strip_tags($urlSegment)),
		db_escape( strip_tags($navTitle) ),
		db_escape( strip_tags($menuId) ),
		db_escape( dateToMySQL(date('c'))),
		db_escape( strip_tags($userInfoArr["username"]) )
		);
		
    db_query($query);

	$branchId = db_insert_id();

    $updated = true;

	
	header('location: content-edit.htm?branchId=' . $branchId);
	exit;

}


// types
$query = sprintf("SELECT * FROM a_branchtypes WHERE typeName = '%s'",
	db_escape($_GET['type'] ?? ''));
$type = db_query($query);
$typeRow = db_fetch_assoc($type);

if (db_num_rows($type) < 1) {
	
	header('location: /admin/');
	exit;
	
}

// templates
$query = sprintf("SELECT * FROM a_templates WHERE type = '" . $typeRow['typeName'] . "' AND allowNew = 'Y' ORDER BY templateName");
$templates = db_query($query);


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title><?php echo $siteName ?> | <?php echo $currentPageName ?></title>
    <?php include("inc/htmlhead.php") ?>
</head>
<body class="<?php echo $currentSection ?>">
    <div id="alertMessage" class="error"></div>

    <?php include("inc/header.php") ?>
    <?php include("inc/sidebar.php") ?>

    <div id="content">
        <div class="inner">

            <?php include("inc/topcolumn.php") ?>

            <!-- Start content -->



            <!-- full width -->
            <div class="widget">
                <div class="header"><span><span class="ico gray list"></span>Create New <?php echo ucfirst($typeRow['singularName']) ?></span>
                </div><!-- End header -->

                <div class="content">

                <!-- title box -->
                <div class="boxtitle min">Settings</div>


                    <form id="settingsForm" action="new-branch.htm" method="post" class="validate" >

                        
						<div class="section">
                              <label>Template<small>Choose a template to base this <?php echo $typeRow['singularName'] ?> on</small></label>   
							  <?php if (db_num_rows($templates) > 0) { ?>
                              <div>
                              	  <select name="templateId" class="big">
                                     <?php while($option = db_fetch_assoc($templates)) { ?>
									 <option value="<?php echo $option['templateId']; ?>" <?php if ($option['templateName'] == 'page') {echo 'selected="selected"';} ?>><?php echo ucfirst($option['templateName']); ?></option>
									 <?php } ?>
                                </select>       
							</div>
							<?php } ?>

                        </div>

						
						<div class="section" >
                            <label>Title / Name of <?php echo $typeRow['singularName'] ?><small>The title is used as the main identifier and used for SEO</small></label>   
                            <div> <input type="text" name="title" id="title" class="validate[required] full"  /></div>
                        </div>
						
						<?php if (($_GET['type'] ?? '') == 'page') { ?>
						<div class="section">
                              <label>Parent<small>Optional.</small></label>   
							  
                              <div>
                              	  <select name="parentId" class="big">
								  	 <option value="0">None</option>
                                     <?php generateOptionListOfPages(); ?>
                                </select>       
							</div>
							<input type="hidden" name="addAsChild" value="1"  />
                        </div>
						<?php } else { ?>
						<input type="hidden" name="parentId" value="<?php echo $typeRow['defaultParentId'] ?>"  />
						<?php } ?>

						

					<?php if (db_num_rows($templates) > 0) { ?>
                    <div class="section last">
						<input type="hidden" name="menuId" value="<?php echo $typeRow['defaultMenuId'] ?>"  />
						<input type="hidden" name="action" value="submit"  />
                        <div><a class="uibutton loading" title="Saving" href="javascript:;" onclick="changesMade = false; $(this).parents('form').submit();" >Create <?php echo ucfirst($typeRow['singularName']) ?></a></div>
                    </div>
					<input type="hidden" name="type" value="<?php echo $typeRow['typeName'] ?>" />
					
					<?php } else { ?>
						<div class="alertMessage error">CMS Error. No templates found for this type.</div>
					<?php } ?>

                    </form>



            </div><!-- End content -->
            </div><!-- End full width -->





            <?php include("inc/footer.php") ?>
        </div> <!--// End inner -->
    </div> <!--// End content -->
    <script type="text/javascript">
        <?php if ($updated) { ?>
        showSuccess('Settings updated');
        <?php } ?>
    </script>
</body>
</html>