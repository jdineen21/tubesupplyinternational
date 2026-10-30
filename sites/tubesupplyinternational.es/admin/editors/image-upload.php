<?php include("../../common/settings.php") ?>
<?php include("../inc/settings.php") ?>
<?php include("../inc/functions.php") ?>
<?php include("../inc/main.php") ?>
<?php include("../inc/phpthumb/phpthumb.class.php") ?>
<?php
// Define a destination
$targetFolder = '/media/images/'; // Relative to the root


// get parameters
$regionId = $_GET['regionId'] ?? '';
$id = (int) ($_GET['id'] ?? 0); // used in the file name

$query = sprintf("SELECT * FROM a_regions WHERE regionId = '%s'",
    db_escape( $regionId ) );
$regionResult = db_query($query);
$region = db_fetch_assoc($regionResult);

$parameters = json_decode($region['parameters'] ?? '');

//echo $parameters->width;



if (!empty($_FILES)) {
	$tempFile = $_FILES['Filedata']['tmp_name'];
	
	// Validate the file type
	$fileTypes = array('jpg','jpeg','gif','png'); // File extensions
	$fileParts = pathinfo($_FILES['Filedata']['name']);
	$extension = strtolower($fileParts['extension']);
	
	$targetPathOriginal = $_SERVER['DOCUMENT_ROOT'] . $targetFolder . 'original/';
	$targetFileOriginal = rtrim($targetPathOriginal,'/') . '/' . $id . '.' . 'jpg';
	
	$targetPathThumb = $_SERVER['DOCUMENT_ROOT'] . $targetFolder . 'thumb/';
	$targetFileThumb = rtrim($targetPathThumb,'/') . '/' . $id . '.' . 'jpg';
	
	$targetPathThumbCustom = $_SERVER['DOCUMENT_ROOT'] . $targetFolder . 'thumb-custom/';
	$targetFileThumbCustom = rtrim($targetPathThumbCustom,'/') . '/' . $id . '.' . 'jpg';
	
	
	
	if (in_array($extension,$fileTypes)) {
	
	
		move_uploaded_file($tempFile,$targetFileOriginal);
		
		
		/*
		$image = new SimpleImage();
		$image->load($tempFile);
		$image->resizeToWidth(200);
		$image->resizeToHeight(200);
		$image->save($targetFile);
		*/
		
		
		
		$phpThumb = new phpThumb();		
		$phpThumb->setSourceFilename($targetFileOriginal);
		$phpThumb->setParameter('q', 90); // Quality
		$phpThumb->setParameter('w', 400);
		$phpThumb->setParameter('h', 200);
		$phpThumb->GenerateThumbnail();
		$phpThumb->RenderToFile($targetFileThumb);
		
		
		if (($_GET['customThumb'] ?? '') == 'true') {
			$phpThumb = new phpThumb();		
			$phpThumb->setSourceFilename($targetFileOriginal);
			$phpThumb->setParameter('q', 90); // Quality
			$phpThumb->setParameter('w', (int) ($_GET['customThumbWidth'] ?? 0));
			$phpThumb->setParameter('h', (int) ($_GET['customThumbHeight'] ?? 0));
			$phpThumb->GenerateThumbnail();
			$phpThumb->RenderToFile($targetFileThumbCustom);
		}
		
		/*  //generate & output thumbnail
		if ($phpThumb->GenerateThumbnail()) { // this line is VERY important, do not remove it!
			if ($phpThumb->RenderToFile($targetFileThumb)) {
				// do something on success
				echo 'Successfully rendered to "'.$targetFile.'"';
			} else {
				// do something with debug/error messages
				echo '1Failed:<pre>'.implode("\n\n", $phpThumb->debugmessages).'</pre>';
			}
			$phpThumb->purgeTempFiles();
		} else {
			// do something with debug/error messages
			echo '2Failed:<pre>'.$phpThumb->fatalerror."-\n\n".implode("\n\n", $phpThumb->debugmessages).'</pre>';
		}
		*/
		
		
		
		
		echo 'Upload successful.';
	} else {
		echo 'Error! Invalid file type.';
	}
}

?>