<?php
if (!isset($regionRow)) { exit; } // only runs when included by content-edit.php (which checks the login)

// get parameters from region
$regionId = $regionRow['regionId'];

$query = sprintf("SELECT * FROM a_regions WHERE regionId = '%s'",
    db_escape( $regionId ) );
$regionResult = db_query($query);
$region = db_fetch_assoc($regionResult);

$parameters = json_decode($region['parameters'] ?? '');


$customThumbWidth = $parameters->customThumbWidth ?? null;
$customThumbHeight = $parameters->customThumbHeight ?? null;

$customThumbParams = '';
if ($customThumbWidth != '' && $customThumbHeight != '') {
	$customThumbParams = '&customThumb=true&customThumbWidth=' . $customThumbWidth . '&customThumbHeight=' . $customThumbHeight;
}



// save content
if (($_POST['editorActionMode'] ?? '') != '') {

	$action = $_POST['editorActionMode'] ?? '';
    $publishedContentId = $_POST['publishedContentId-' . $currentBranchRow['branchId'] . '-' . $regionRow['regionId'] . '-' . $repeatRow['rowId']] ?? '';

    if ($action == 'delete') {

		// not used		

    } else {
        // save and publish
        if ($action == 'publish') {

                $status = 'published';
                $contentId = $publishedContentId;

        }

		
		
		
		if (($parameters->crop ?? null) == true) {
			// crop - crop needs cropWidth and cropHeight parameters
			$aspectRatio = ($parameters->cropWidth ?? null) / ($parameters->cropHeight ?? null);
		}
		
		// load original image and save cropped version
		$extension = 'jpg';
		
		$targetFolder = '/media/images/'; // Relative to the root
		
		$targetPathOriginal = $_SERVER['DOCUMENT_ROOT'] . $targetFolder . 'original/';
		$targetFileOriginal = rtrim($targetPathOriginal,'/') . '/' . $contentId . '.' . $extension;
		
		
		// check if delete image has been ticked
		$delete = $_POST['deleteImage-' . $contentId ] ?? '';
		
		if ($delete == 'delete') {
			
			$thumb = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'thumb/' . $contentId . '.jpg';
			if (is_file($thumb)) { unlink($thumb); }
			
			$thumbCustom = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'thumb-custom/' . $contentId . '.jpg';
			if (is_file($thumbCustom)) { unlink($thumbCustom); }
			
			$original = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'original/' . $contentId . '.jpg';
			if (is_file($original)) { unlink($original); }
		
			$rendered = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'rendered/' . $contentId . '.jpg';
			if (is_file($rendered)) { unlink($rendered); }
			
			$query = sprintf("DElETE FROM a_content WHERE contentId = '%s'",
	            db_escape( $contentId ));
			db_query($query);
			
		}
		
		if (is_file($targetFileOriginal) && $delete == '') {
			
				
			$targetFileOriginalSize = getimagesize($targetFileOriginal);
			$targetFileOriginalWidth = $targetFileOriginalSize[0];
			$targetFileOriginalHeight = $targetFileOriginalSize[1];
			
			
			$targetPathThumb = $_SERVER['DOCUMENT_ROOT'] . $targetFolder . 'thumb/';
			$targetFileThumb = rtrim($targetPathThumb,'/') . '/' . $contentId . '.' . $extension;		
			$targetFileThumbSize = getimagesize($targetFileThumb);
			$targetFileThumbWidth = $targetFileThumbSize[0];
			$targetFileThumbHeight = $targetFileThumbSize[1];
			
			
			$targetPathRendered = $_SERVER['DOCUMENT_ROOT'] . $targetFolder . 'rendered/';
			$targetFileRendered = rtrim($targetPathRendered,'/') . '/' . $contentId . '.' . $extension;
			$urlFileRendered = $targetFolder . 'rendered/' . $contentId . '.' . $extension;
			
			$targetPathCustomThumb = $_SERVER['DOCUMENT_ROOT'] . $targetFolder . 'thumb-custom/';
			$targetFileCustomThumb = rtrim($targetPathCustomThumb,'/') . '/' . $contentId . '.' . $extension;
			
			
			$ratio = $targetFileThumbWidth ? $targetFileOriginalWidth / $targetFileThumbWidth : 0; // PHP 8 throws on division by zero
			
	
			
			// (float): PHP 8 throws a TypeError for '' * number, PHP 5 treated '' as 0.
			// Rounded to whole pixels so the crop comes out at exactly the set size.
			$sx = round((float) ($_POST['cropX-' . $contentId ] ?? 0)*$ratio);
			$sy = round((float) ($_POST['cropY-' . $contentId ] ?? 0)*$ratio);
			$sx2 = round((float) ($_POST['cropW-' . $contentId ] ?? 0)*$ratio);
			$sy2 = round((float) ($_POST['cropH-' . $contentId ] ?? 0)*$ratio);
			$sw = $sx2 - $sx;
			$sh = $sy2 - $sy;
			
			// Quality
			$quality = $parameters->quality ?? null;
			if ($quality == '') {
				$quality = 90;
			}
			
			// Aspect modifier
			if (($parameters->crop ?? null) == true) {
				$iar = 1;
			} else {
				$iar = 0;
			}
						
			// Dimensions
			if (($parameters->crop ?? null) == true) { 
				$setWidth = $parameters->cropWidth ?? null;			
				$setHeight = $parameters->cropHeight ?? null;
			} else {
				$setWidth = $parameters->maxWidth ?? null;			
				$setHeight = $parameters->maxHeight ?? null;
			}
			
			if ($setWidth == '') {
				$setWidth = $targetFileOriginalWidth;
			}
			if ($setHeight == '') {
				$setHeight = $targetFileOriginalHeight;
			}
			
			// Custom Thumb
			if ($customThumbParams != '') {				
				$phpThumb = new phpThumb();		
				$phpThumb->setSourceFilename($targetFileOriginal);				
				$phpThumb->setParameter('q', $quality); // Quality				
				$phpThumb->setParameter('sx', $sx); // Source crop
				$phpThumb->setParameter('sy', $sy); // Source crop
				$phpThumb->setParameter('sw', $sw); // Source crop
				$phpThumb->setParameter('sh', $sh); // Source crop
				$phpThumb->setParameter('w', $customThumbWidth);
				$phpThumb->setParameter('h', $customThumbHeight);
				$phpThumb->GenerateThumbnail();
				$phpThumb->RenderToFile($targetFileCustomThumb);	
			}
			
			
			
			
			$phpThumb = new phpThumb();		
			$phpThumb->setSourceFilename($targetFileOriginal);				
			$phpThumb->setParameter('q', $quality); // Quality
			$phpThumb->setParameter('iar', $iar); // Source crop
			$phpThumb->setParameter('sx', $sx); // Source crop
			$phpThumb->setParameter('sy', $sy); // Source crop
			$phpThumb->setParameter('sw', $sw); // Source crop
			$phpThumb->setParameter('sh', $sh); // Source crop
			$phpThumb->setParameter('w', $setWidth);
			$phpThumb->setParameter('h', $setHeight);
			$phpThumb->GenerateThumbnail();
			$phpThumb->RenderToFile($targetFileRendered);
			
			//echo 'Failed:<pre>'.implode("\n\n", $phpThumb->debugmessages).'</pre>';
			
			
			if (($parameters->crop ?? null) == true) {
				$saveParameters = '{"cropX":' . ($_POST['cropX-' . $contentId ] ?? '') . ',"cropY":' . ($_POST['cropY-' . $contentId ] ?? '') . ',"cropX2":' . ($_POST['cropW-' . $contentId ] ?? '') . ',"cropY2":'. ($_POST['cropH-' . $contentId ] ?? '') . ',"alt":"' . strip_tags($_POST['alt-' . $contentId ] ?? '') . '"} ';
			} else {
				$saveParameters = '{"alt":"' . strip_tags($_POST['alt-' . $contentId ] ?? '') . '"} ';
			}
	
	
	        // update the content
			$query = sprintf("UPDATE a_content SET content = '%s', status = '%s', parameters = '%s' WHERE contentId = '%s'",
	            
	            db_escape( $urlFileRendered ),
				db_escape( $status ),
				db_escape( $saveParameters ),
	            db_escape( $contentId ));
				
	        db_query($query);
			
		
			
		} // end is_file
		

    } // end delete if

    $updated = true;

}



$repeatRegionRowId = 0;
if ($repeatRow['rowId'] != '') {
	$repeatRegionRowId = $repeatRow['rowId'];
}
// get the content PUBLISHED
$query = sprintf("SELECT * FROM a_content WHERE branchId = '%s' AND regionId = '%s' AND repeatRegionRowId = '%s' AND status = 'published'",
    db_escape( $currentBranchRow['branchId'] ),
    db_escape( $regionRow['regionId'] ),
	db_escape( $repeatRegionRowId ) );
$contentPublishedResult = db_query($query);
$contentPublished = db_fetch_assoc($contentPublishedResult);


    $contentToEdit = $contentPublished['content'] ?? null;
	$contentId = $contentPublished['contentId'] ?? null;
	$contentParameters = json_decode($contentPublished['parameters'] ?? '');







// create db entry if none yet created (needed for id for image)
if (db_num_rows($contentPublishedResult) == 0) {
	
	$query = sprintf("INSERT INTO a_content SET branchId = '%s', regionId = '%s', repeatRegionRowId = '%s'",
        db_escape( $currentBranchRow['branchId'] ),
        db_escape( $regionRow['regionId'] ),
		db_escape( $repeatRow['rowId'] ? $repeatRow['rowId'] : 0 )
		);
    db_query($query);

    $contentId = db_insert_id();
	
}

?>
    
	
	
		<?php
		// get parameters from region
		$regionId = $regionRow['regionId'];
		
		$query = sprintf("SELECT * FROM a_regions WHERE regionId = '%s'",
		    db_escape( $regionId ) );
		$regionResult = db_query($query);
		$region = db_fetch_assoc($regionResult);
		
		$parameters = json_decode($region['parameters'] ?? '');
		
		if (($parameters->crop ?? null) == true) {
			// crop - crop needs cropWidth and cropHeight parameters
			$aspectRatio = ($parameters->cropWidth ?? null) / ($parameters->cropHeight ?? null);
		}

		?>
	
	<div style="width: 30%; float: left">
			
	
		<input type="hidden" name="file_upload" id="upload-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" />
		
		<div style="display: none;" class="alertMessage" id="upload-message-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>"></div>
	
	
		<br/><strong>Alt tag:</strong><br/><input type="text" id="alt-<?php echo $contentId ?>" name="alt-<?php echo $contentId ?>" value="<?php echo $contentParameters->alt ?? null ?>" /><br/><small>Describe the image for accessibillity.</small>

		<br/><br/>

		<?php if ($contentToEdit != '') { ?>
		<input type="checkbox" name="deleteImage-<?php echo $contentId ?>" id="deleteImage-<?php echo $contentId ?>" value="delete" class="ck"/>
        <label for="deleteImage-<?php echo $contentId ?>">Tick to remove image.</label>
		<?php } ?>
		
	
	</div><!-- end left col -->
	
	
	<div style="width: 70%; float: left">
	
		<?php if (($parameters->crop ?? null) == true) { // CROP ?>
		<div style="width:200px;height:<?php echo 200/$aspectRatio ?>px;overflow:hidden; <?php if ($contentToEdit == '') { echo 'display: none;'; } ?>" class="editorImagePreview">
			<img src="/media/images/thumb/<?php echo $contentId ?>.jpg?rnd=<?php echo rand(1, 1000000000); ?>" id="image-preview-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" >
		</div>
		<?php } // end CROP ?>
	
		
		<img src="/media/images/thumb/<?php echo $contentId ?>.jpg?rnd=<?php echo rand(1, 1000000000); ?>" id="image-thumb-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" <?php if ($contentToEdit == '') { echo 'style="display: none;"'; } ?>>
		
		
		
		<script type="text/javascript">
		$(window).load(function() {
		
			$('#upload-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>').atlasUpload({ // admin/js/image-upload.js (Uploadify needed Flash)
		        'uploader' : '/admin/editors/image-upload.htm?regionId=<?php echo $regionRow['regionId'] ?>&id=<?php echo $contentId ?><?php echo $customThumbParams ?>',
				'multi'	: false,
				'buttonText' : 'Browse for image...',
				'onUploadError' : function(file, errorCode, errorMsg, errorString) {
					$('#upload-message-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>').addClass('error').show().html(errorString).delay(1500).fadeOut(500);         		   
        		},
				'onUploadSuccess' : function(file, data, response) {
					if (data.substr(0,5) == 'Error') {
						messageClass = 'error';						
					} else {
						messageClass = 'success';
					}
         		    $('#upload-message-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>').addClass(messageClass).show().html(data).delay(1500).fadeOut(500);
					
					$('#image-thumb-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>').attr('src','/media/images/thumb/<?php echo $contentId ?>.jpg?rnd=' + Math.floor(Math.random()*1000000000000)).show();
					
					<?php // check for crop 
					if (($parameters->crop ?? null) == true) { ?>
						//initCrop_<?php echo $contentId ?>();
						previewSource = '/media/images/thumb/<?php echo $contentId ?>.jpg?rnd=' + Math.floor(Math.random()*1000000000000);
						$("#image-preview-<?php echo $currentBranchRow['branchId']?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>").attr('src',previewSource).parent().show();
						
						if (cropAPI_<?php echo $contentId ?>) {						
							cropAPI_<?php echo $contentId ?>.destroy();
						}
						
						
						var tempImg = new Image();
						tempImg.onload = function() {
							$("#image-thumb-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>").css({
						        width: tempImg.width + 'px',
						        height: tempImg.height + 'px'
						    });
						
							thumbWidth_<?php echo $currentBranchRow['branchId'] ?>_<?php echo $regionRow['regionId'] ?>_<?php echo $repeatRow['rowId'] ?> = tempImg.width;
							thumbHeight_<?php echo $currentBranchRow['branchId'] ?>_<?php echo $regionRow['regionId'] ?>_<?php echo $repeatRow['rowId'] ?> = tempImg.height;
							rx = 200 / (tempImg.width * <?php echo $aspectRatio ?>);
							ry = 200 / (tempImg.height * <?php echo $aspectRatio ?>);
							$("#image-preview-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>").css({
						        width: Math.round(rx * thumbWidth_<?php echo $currentBranchRow['branchId'] ?>_<?php echo $regionRow['regionId'] ?>_<?php echo $repeatRow['rowId'] ?>) + 'px',
						        height: Math.round(ry * thumbHeight_<?php echo $currentBranchRow['branchId'] ?>_<?php echo $regionRow['regionId'] ?>_<?php echo $repeatRow['rowId'] ?> ) + 'px'
						    });
							initCrop_<?php echo $contentId ?>();
						}
						tempImg.src = previewSource;
						
						
					<?php } // end check for crop ?>
									   
        		}
		        
		    });
		
		
		<?php if (($parameters->crop ?? null) == true) { // CROP ?>
			// hide preview if no image uploaded
			<?php if ($contentToEdit == '') {
				echo '$("#image-preview-' . $currentBranchRow['branchId'] . '-' . $regionRow['regionId'] . '-' . $repeatRow['rowId'] . '").parent().hide();';
			}?>
			
			var thumb = $("#image-thumb-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>");
			var thumbWidth_<?php echo $currentBranchRow['branchId'] ?>_<?php echo $regionRow['regionId'] ?>_<?php echo $repeatRow['rowId'] ?> = thumb.width();
			var thumbHeight_<?php echo $currentBranchRow['branchId'] ?>_<?php echo $regionRow['regionId'] ?>_<?php echo $repeatRow['rowId'] ?> = thumb.height();
			var cropAPI_<?php echo $contentId ?>;
		
			function initCrop_<?php echo $contentId ?> () {
			
				<?php
				$setSelectCropX = ($contentParameters->cropX ?? null) ? ($contentParameters->cropX ?? null) : 0;
				$setSelectCropY = ($contentParameters->cropY ?? null) ? ($contentParameters->cropY ?? null) : 0;
				$setSelectCropX2 = ($contentParameters->cropX2 ?? null) ? ($contentParameters->cropX2 ?? null) : 100*$aspectRatio;
				$setSelectCropY2 = ($contentParameters->cropY2 ?? null) ? ($contentParameters->cropY2 ?? null) : 400*$aspectRatio;
				?>
						
				$("#image-thumb-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>").Jcrop({
					aspectRatio: <?php echo $aspectRatio ?>,
					setSelect:   [ <?php echo $setSelectCropX . ',' . $setSelectCropY . ',' . $setSelectCropX2 . ',' . $setSelectCropY2 ?> ],
					keySupport: false,
					onChange: showPreview,
					onSelect: showPreview
				},function(){
					cropAPI_<?php echo $contentId ?> = this;
				});
			}
			
			<?php if ($contentToEdit != '') { 
				echo 'initCrop_' . $contentId . '()';
			} ?>
			  
			function showPreview(coords) {
			  
			  	var preview = $("#image-preview-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>");
				
				$('#cropX-<?php echo $contentId ?>').val(coords.x);
				$('#cropY-<?php echo $contentId ?>').val(coords.y);
				$('#cropW-<?php echo $contentId ?>').val(coords.x2);
				$('#cropH-<?php echo $contentId ?>').val(coords.y2);
								
				
			    if (parseInt(coords.w) > 0)
			    {
			      var rx = 200 / coords.w;
			      var ry = <?php echo 200/$aspectRatio ?> / coords.h;
			
			
			      preview.css({
			        width: Math.round(rx * thumbWidth_<?php echo $currentBranchRow['branchId'] ?>_<?php echo $regionRow['regionId'] ?>_<?php echo $repeatRow['rowId'] ?>) + 'px',
			        height: Math.round(ry * thumbHeight_<?php echo $currentBranchRow['branchId'] ?>_<?php echo $regionRow['regionId'] ?>_<?php echo $repeatRow['rowId'] ?> ) + 'px',
			        marginLeft: '-' + Math.round(rx * coords.x) + 'px',
			        marginTop: '-' + Math.round(ry * coords.y) + 'px'
			      }).show();
			    }
			  }
			
	    	
			
			<?php } // end CROP ?>
	});
	    </script>
		
		
		
		</div><!-- end right col -->
		
		

		
		
		<input type="hidden" id="cropX-<?php echo $contentId ?>" name="cropX-<?php echo $contentId ?>" />
		<input type="hidden" id="cropY-<?php echo $contentId ?>" name="cropY-<?php echo $contentId ?>" />
		<input type="hidden" id="cropW-<?php echo $contentId ?>" name="cropW-<?php echo $contentId ?>" />
		<input type="hidden" id="cropH-<?php echo $contentId ?>" name="cropH-<?php echo $contentId ?>" />

		
		<?php
		if (!($contentPublished['contentId'] ?? null))  {
			$contentPublished = $contentId;
		} else {
			$contentPublished = $contentPublished['contentId'] ?? null;
		}
		?>
        <input type="hidden" name="publishedContentId-<?php echo $currentBranchRow['branchId'] ?>-<?php echo $regionRow['regionId'] ?>-<?php echo $repeatRow['rowId'] ?>" value="<?php echo $contentPublished ?>" />
        
		  <div class="clear"></div>


