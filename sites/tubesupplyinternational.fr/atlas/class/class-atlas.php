<?php
// **** Atlas Main Class
// **** (c) 2013 Climb Creative Ltd
// **** Author Stephen Downey

class Atlas {

	// private vars
	private $url;
	private $urlStructure;
	private $urlStructureIds; // branchIds of each url segment
	private $currentPage;
	private $currentTemplate;
	private $currentTemplateId;
	private $currentBranchId;
	private $title;
	private $dateAdded;
    private $adminMode;
    private $contentStatus; // 'draft' will show all draft versions of content in admin mode
	private $siteSettings; // array loaded from a_settings table
	private $branchResult; // array of current branch columns

	// constructor
	public function __construct() {

        $this->adminMode = false;
        $this->contentStatus = 'published';

        // check if admin logged in
        if (isset($_SESSION['a_login'])) {
            $this->adminMode = true;
            if (($_SESSION['a_contentPreview'] ?? '') == 'live') {
                $this->contentStatus = 'published';
            } elseif (($_SESSION['a_contentPreview'] ?? '') == 'draft') {
                $this->contentStatus = 'draft';
            }
        }

		$this->siteSettings = db_fetch_assoc(db_query('SELECT a_settings.*, HTREE.templateId AS homeTemplateId, HTREE.dateAdded AS homeDateAdded, H.templateName AS homeTemplateName, OTREE.templateId AS offlineTemplateId, O.templateName AS offlineTemplateName FROM a_settings LEFT OUTER JOIN a_tree HTREE ON a_settings.homeBranchId = HTREE.branchId LEFT OUTER JOIN a_templates H ON HTREE.templateId = H.templateId   LEFT OUTER JOIN a_tree OTREE ON a_settings.offlineBranchId = OTREE.branchId LEFT OUTER JOIN a_templates O ON OTREE.templateId = O.templateId'));
		$this->url = rtrim(ltrim(strtolower($_GET['url'] ?? ''),'/'),'/');
		$this->urlStructure = explode('/',$this->url);
		$this->urlStructureIds = array();
		$this->setCurrentPage();
				
		
	}

	
	
	// functions
	public function renderPage() {
			
		$templatePath = $_SERVER['DOCUMENT_ROOT'] . '/frontend/template/' . $this->getCurrentTemplate() . '.php';
			
				
		if (!is_file($templatePath)) {
			
			$templatePath = $_SERVER['DOCUMENT_ROOT'] . '/frontend/template/404.php';
			
		}		
		
		include($templatePath);	
				
	}
	
	
	public function getSetting($name) {
		
		return $this->siteSettings[$name];
		
	}
	
	
	public function excerpt($content,$limit = 500,$readMoreLink = '',$readMoreText = ' read more') {
		
		$content = strip_tags($content);
		
		if (strlen($content) > $limit) {
			$content = substr($content,0,$limit) . '...';
		}
		
		if ($readMoreLink) {
			$content .= '<a href="' . $readMoreLink . '">' . $readMoreText . '</a>';
		}
		
		
		return $content;
		
	}
	
	
	public function content($regionName, $repeatRegionRowId = 0, $scope = 'local', $branchId = 0) {
	
		// scope : 'local' = from current page / 'global' = site wide content / 'branch' = content from specific branchId (e.g use with repeatBranches)
		
		if ($scope == 'local') {
	
			$query = sprintf("SELECT a_regions.*, a_content.content, a_content.parameters FROM a_regions INNER JOIN a_content ON a_regions.regionId = a_content.regionId WHERE a_regions.templateId = '%s' AND a_regions.regionName = '%s' AND a_content.branchId = '%s' AND a_content.repeatRegionRowId = '%s' AND a_regions.global = 'N' AND a_content.status = '%s'",
				db_escape($this->currentTemplateId),
				db_escape($regionName),
				db_escape($this->currentBranchId),
				db_escape($repeatRegionRowId),
                db_escape($this->contentStatus));
            $contentResult = db_query($query);

            // show the live content if there is no draft version
            if ($this->contentStatus == 'draft' && db_num_rows($contentResult) == 0) {

                $query = sprintf("SELECT a_regions.*, a_content.content, a_content.parameters FROM a_regions INNER JOIN a_content ON a_regions.regionId = a_content.regionId WHERE a_regions.templateId = '%s' AND a_regions.regionName = '%s' AND a_content.branchId = '%s' AND a_content.repeatRegionRowId = '%s' AND a_regions.global = 'N' AND a_content.status = '%s'",
                    db_escape($this->currentTemplateId),
                    db_escape($regionName),
                    db_escape($this->currentBranchId),
                    db_escape($repeatRegionRowId),
                    db_escape('published'));
                $contentResult = db_query($query);

            }

		}
		
		if ($scope == 'global') {
	
			$query = sprintf("SELECT a_regions.*, a_content.content, a_content.parameters FROM a_regions INNER JOIN a_content ON a_regions.regionId = a_content.regionId WHERE a_regions.regionName = '%s' AND a_regions.global = 'Y' AND a_content.status = '%s'",
				db_escape($regionName),
                db_escape($this->contentStatus));
            $contentResult = db_query($query);

            // show the live content if there is no draft version
            if ($this->contentStatus == 'draft' && db_num_rows($contentResult) == 0) {
                $query = sprintf("SELECT a_regions.*, a_content.content, a_content.parameters FROM a_regions INNER JOIN a_content ON a_regions.regionId = a_content.regionId WHERE a_regions.regionName = '%s' AND a_regions.global = 'Y' AND a_content.status = '%s'",
                    db_escape($regionName),
                    db_escape('published'));
                $contentResult = db_query($query);
            }
		}


		if ($scope == 'branch') {
	
			$query = sprintf("SELECT a_regions.*, a_content.content, a_content.parameters FROM a_regions INNER JOIN a_content ON a_regions.regionId = a_content.regionId WHERE a_regions.regionName = '%s' AND a_regions.global = 'N' AND a_content.branchId = %s AND a_content.status = '%s'",
				db_escape($regionName),
				db_escape($branchId),
                db_escape($this->contentStatus));
            $contentResult = db_query($query);


            // show the live content if there is no draft version
            if ($this->contentStatus == 'draft' && db_num_rows($contentResult) == 0) {
                $query = sprintf("SELECT a_regions.*, a_content.content, a_content.parameters FROM a_regions INNER JOIN a_content ON a_regions.regionId = a_content.regionId WHERE a_regions.regionName = '%s' AND a_regions.global = 'N' AND a_content.branchId = %s AND a_content.status = '%s'",
				db_escape($regionName),
				db_escape($branchId),
                db_escape('published'));
            $contentResult = db_query($query);
				
            }
		}


		$contentResultRow = db_fetch_assoc($contentResult);
		$content = '';
		
		//$regionType = strtolower($contentResultRow['regionType']);
	
		
		/*if ($this->contentStatus == 'draft' && ($regionType == 'text' || $regionType == 'html') ) { // Outline regions
			$content = '<span style="display: inline-block; outline: red 1px dotted; min-width: 10px; min-height: 10px;" onmouseover="$(this).find(\'.atlas-tooltip\').show()" onmouseout="$(this).find(\'.atlas-tooltip\').hide()"><span class="atlas-tooltip" style="display: none; position: absolute; background-color: #f7f7f7; color: #000; border-radius: 3px; padding: 2px; font-weight: normal; font-size: 10px; font: arial"><a href="/admin/content-edit.htm?branchId=' . $this->currentBranchId . '#' . $contentResultRow['regionId'] . '" style="color: #000;" target="_parent">' . $regionName . '</a></span>';
		}*/
		
		if ($contentResultRow) {
			$content .= $contentResultRow['content'];
		}
		
		/*
		if ($this->contentStatus == 'draft' && ($regionType == 'text' || $regionType == 'html') ) {
			$content .= '</span>';
		}*/
		
		return $content;
	}
	
	
	public function image($url,$size = 'rendered', $class = '',$altImageUrl = '',$noCache = false) {
		
		$newUrl = $url;
		$altTag = '';
		
		if ($size) {
			$query = sprintf("SELECT * FROM a_content WHERE content = '%s'",
				db_escape( $url ) );
			$contentResult = db_query($query);
			$content = db_fetch_assoc($contentResult);
			
			if (db_num_rows($contentResult) > 0) {
				// Image found in content table
				$parameters = json_decode((string) $content['parameters']);
				
				$path = '/media/images/' . strtolower($size) . '/';
				$newUrl = $path . $content['contentId'] . '.jpg';
				
				$altTag = $parameters->alt ?? '';
				
			}
		}
		
		if (!is_file($_SERVER['DOCUMENT_ROOT'] . $newUrl)) {
			$newUrl = $altImageUrl;
		}
		
		$html = '';
		
		if ($newUrl && $noCache) {
			$newUrl .= '?r=' . rand();
		}
		
		if ($newUrl) {
			
			$html = '<img ';
		
			if ($class) {
				$html .= 'class="' . $class . '" ';
			}
			$html .= 'src="' . $newUrl . '" ';
			$html .= 'alt="' . $altTag . '" ';		
			
			$html .= ' />';
		
		}
		
		return $html;
		
	}
	
	
	public function repeatRegion($regionName, $orderBy = 'sortOrder', $orderDirection = 'ASC', $limit = 0, $random = false, $branchId = 0, $templateId = 0) {
		
		$sqlLimit = '';
		if ($limit > 0) {
			$sqlLimit = ' LIMIT ' . $limit;
		}
			
		if ($random) {
			$orderBy = 'RAND()';
		}
		
		if ($branchId == 0) {
			$branchId = $this->currentBranchId;
		}
		
		if ($templateId == 0) {
			$templateId = $this->currentTemplateId;
		}
			
		$query = sprintf("SELECT a_repeatregions.regionName, a_repeatregionsrows.* FROM a_repeatregions INNER JOIN a_repeatregionsrows ON a_repeatregions.repeatRegionId = a_repeatregionsrows.repeatRegionId WHERE a_repeatregionsrows.templateId = '%s' AND a_repeatregions.regionName = '%s' AND a_repeatregionsrows.branchId = '%s' ORDER BY %s %s" . $sqlLimit,
			db_escape($templateId),
			db_escape($regionName),
			db_escape($branchId),
			db_escape($orderBy),
			db_escape($orderDirection));

		return db_query($query);
					
	}
	
	
	public function repeatBranches($typeName,$limit=0,$parentId=0,$random=false) { // returns a mysql recordset of branches of a specific type
		
		$sqlLimit = '';
		if ($limit > 0) {
			$sqlLimit = ' LIMIT ' . $limit;
		}
				
		$query = sprintf("SELECT * FROM a_branchtypes WHERE typeName = '%s'", db_escape($typeName));
		$branchTypesResult = db_query($query);
		$branchTypes = db_fetch_assoc($branchTypesResult);
		
		$orderBy = $branchTypes['orderBy'];
		if ($random) {
			$orderBy = 'RAND()';
		}
		
		$sqlParent = '';
		if ($parentId > 0) {
			$sqlParent = ' AND parentId = ' . db_escape($parentId);
		}
		
		$query = "SELECT a_tree.* FROM a_tree WHERE type = '" . $branchTypes['typeName'] . "'" . $sqlParent . " ORDER BY " . $orderBy . " " . $branchTypes['orderByDirection'] . $sqlLimit;

		return db_query($query);
					
	}
	
	
	public function menu($menuName) {
	
		$query = sprintf("SELECT a_tree.* FROM a_tree INNER JOIN a_menus ON a_tree.menuId = a_menus.menuId WHERE a_menus.menuName = '%s'",
			db_escape($menuName));
			
		return db_query($query);
		
	}
	
	
	
	public function generateMenuFromBranchType($typeName,$parentId = 0,$ulClass = '',$maxDepth = 99,$depth = 0) {
		
		if ($depth < $maxDepth) {
		
			// Structure
			if ($depth == 0) {
						
				$query = sprintf("SELECT a.*, Deriv1.Count FROM a_tree a  LEFT OUTER JOIN (SELECT parentId, COUNT(*) AS Count FROM a_tree GROUP BY parentId) Deriv1 ON a.branchId = Deriv1.parentId WHERE a.parentId = '%s' AND a.type = '%s' ORDER BY a.sortOrder",
					db_escape($parentId),
					db_escape($typeName));
					
			} else {
				
				$query = sprintf("SELECT a.*, Deriv1.Count FROM a_tree a  LEFT OUTER JOIN (SELECT parentId, COUNT(*) AS Count FROM a_tree GROUP BY parentId) Deriv1 ON a.branchId = Deriv1.parentId WHERE a.parentId = '%s' ORDER BY a.sortOrder",
					db_escape($parentId));
				
			}
				
			$nav = db_query($query);
	
			echo '<ul class="' . $ulClass . '">';
	
			while($menuItem = db_fetch_assoc($nav)) {
				
				$link = $this->getURL($menuItem['branchId']);
				
				$liClass = '';
				if (in_array($menuItem['branchId'],$this->urlStructureIds)) { $liClass = 'current'; }
				
				
				if ($menuItem['Count'] > 0) {
				
	            	echo '<li id="navItem-' . $menuItem['branchId'] . '" class="' . $liClass . '"><a href="' . $link . '">' . $menuItem['navTitle'] . '</a>';
	            	$this->generateMenuFromBranchType($typeName,$menuItem['branchId'],'sub',$maxDepth,$depth + 1);
	            	echo '</li>';
	        	} elseif ($menuItem['Count']==0) {
	            	echo '<li id="navItem-' . $menuItem['branchId'] . '" class="' . $liClass . '"><a href="' . $link . '">' . $menuItem['navTitle'] . '</a></li>';
	        	} else {
					
				}
	
				
			}
			
			echo '</ul>';
		} // end if maxDepth
		
	}
	
	
	
	public function generateMenuHTML($menuName,$parentId = 0,$ulClass = '',$maxDepth = 99,$depth = 0,$listItemTemplate = '<a href="{link}">{navTitle}</a>{subTitle}', $ulOpenTemplate = '<ul class="{ulClass}">', $ulCloseTemplate = '</ul>') {
		
		if ($depth < $maxDepth) {
		
			// Menu structure
			$query = sprintf("SELECT a.*, Deriv1.Count FROM a_tree a  LEFT OUTER JOIN (SELECT parentId, COUNT(*) AS Count FROM a_tree GROUP BY parentId) Deriv1 ON a.branchId = Deriv1.parentId INNER JOIN a_menus ON a.menuId = a_menus.menuId WHERE a.parentId = '%s' AND a_menus.menuName = '%s' ORDER BY a.sortOrder",
				db_escape($parentId),
				db_escape($menuName));
				
			$nav = db_query($query);
	
			
			echo str_replace('{ulClass}', $ulClass, $ulOpenTemplate);
			
			$subTitle = '';
	
			while($menuItem = db_fetch_assoc($nav)) {
				
				if (strtolower($menuItem['type']) == 'Link') {
					$link = $menuItem['description'];	
				} else {
					$link = $this->getURL($menuItem['branchId']);
				}				
				
				$subTitle = '';
				if ($menuItem['subTitle'] != '') {
					$subTitle = '<span>' . $menuItem['subTitle'] . '</span>';
				}
				
				$liClass = '';
				if (in_array($menuItem['branchId'],$this->urlStructureIds)) { $liClass = 'current'; }
				if ($this->currentPage == 'home' && $menuItem['branchId'] == $this->siteSettings['homeBranchId']) { $liClass = 'current'; }
				
				
				$liStructure = str_replace('{link}',$link,$listItemTemplate);
				$liStructure = str_replace('{navTitle}',$menuItem['navTitle'],$liStructure);
				$liStructure = str_replace('{subTitle}',$subTitle,$liStructure);
				
				if ($menuItem['Count'] > 0) {
	            	echo '<li id="navItem-' . $menuItem['branchId'] . '" class="' . $liClass . '">' . $liStructure;
	            	$this->generateMenuHTML($menuName,$menuItem['branchId'],'sub',$maxDepth,$depth + 1,$listItemTemplate,$ulOpenTemplate,$ulCloseTemplate);
	            	echo '</li>';
	        	} elseif ($menuItem['Count']==0) {
	            	echo '<li id="navItem-' . $menuItem['branchId'] . '" class="' . $liClass . '">' . $liStructure . '</li>';
	        	} else {
					
				}
	
				
			}
			
			echo $ulCloseTemplate;
		
		} // end if maxDepth
		
	}
	
	
	public function deleteBranch($branchId) {
	
		// delete the branch
		$query = sprintf("DELETE FROM a_tree WHERE branchId = '%s'",
		db_escape( $branchId ));
		db_query($query);
		
		// move all children to root category
		$query = sprintf("UPDATE a_tree SET parentId = 0 WHERE parentId = '%s'",
		db_escape( $branchId ));
		db_query($query);
		
		// delete repeat content
		$query = sprintf("DELETE FROM a_repeatregionsrows WHERE branchId = '%s'",
		db_escape( $branchId ));
		db_query($query);
		
		// delete images
		$query = sprintf("SELECT a_content.*, a_regions.regionType FROM a_content INNER JOIN a_regions ON a_content.regionId = a_regions.regionId WHERE branchId = '%s' AND regionType = 'image'",
			db_escape( $branchId ));
		$content = db_query($query);
		
		while($item = db_fetch_assoc($content)) {
			
			$thumb = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'thumb/' . $item['contentId'] . '.jpg';
			if (is_file($thumb)) { unlink($thumb); }
			
			$original = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'original/' . $item['contentId'] . '.jpg';
			if (is_file($original)) { unlink($original); }
			
			$rendered = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'rendered/' . $item['contentId'] . '.jpg';
			if (is_file($rendered)) { unlink($rendered); }
		
		}
		
		
		// delete content
		$query = sprintf("DELETE FROM a_content WHERE branchId = '%s'",
		db_escape( $branchId ));
		db_query($query);
		
	}
	
	
	public function generateBreadcrumbs($itemTemplate = '<li class="crumb"><a href="{link}">{title}</a></li>', $lastItemTemplate = '<li class="crumbLast">{title}</li>') {
	
		// template accepts {title} , {navTitle} , {link}
	
		$buildUrl = array();
		$n = 1;
		$branchId = $this->currentBranchId;
		while ($branchId > 0) { 
			$query = sprintf("SELECT * FROM a_tree WHERE branchId = '%s'",
				db_escape( $branchId ) );
			$result = db_query($query);
			$branch = db_fetch_assoc($result);
			
				if ($n == 1) {
					$item = str_replace('{title}',$branch['title'],$lastItemTemplate);										
				} else {
					$item = str_replace('{title}',$branch['title'],$itemTemplate);
				}
				$item = str_replace('{navTitle}',$branch['navTitle'],$item);
				$item = str_replace('{link}',$this->getURL($branch['branchId']),$item);	
				
				array_push($buildUrl,$item);	
				$branchId = $branch['parentId'];
			$n++;
			if ($n >= 20) { // max 20 steps just in case!
				break;
			}
			
		}
		
		echo implode('',array_reverse($buildUrl));
		
		
	}
	
	
	// getters
	public function getTopLevelBranchId() {
		// returns the branchId of the top most branch
		
		$branchId = $this->currentBranchId;
		
		$n = 1;
		while ($branchId > 0) { 
			$query = sprintf("SELECT branchId, parentId FROM a_tree WHERE branchId = '%s'",
				db_escape( $branchId ) );
			$result = db_query($query);
			$branch = db_fetch_assoc($result);
			
			$branchId = $branch['parentId'];
			$topLevelBranchId = $branch['branchId'];
			
			$n++;
			if ($n >= 20) { // max 20 steps just in case!
				break;
			}
			
		}
		
		return $topLevelBranchId;
		
		
	}
	
	
	public function getDownloads($limit = 0, $branchId = 0) { // returns a mysql recordset of downloads
		
		$sqlLimit = '';
		if ($limit > 0) {
			$sqlLimit = ' LIMIT ' . $limit;
		}
		
		if ($branchId == 0) {	
			$branchId = $this->getCurrentBranchId();			
		} 
		
		$query = sprintf("SELECT * FROM a_downloads WHERE branchId = '%s' ORDER BY sortOrder" . $sqlLimit, db_escape($branchId));
		return db_query($query);		
					
	}
	
	
	public function getURL($branchId = 0) {
		// $branchId is optional - if provided then function will return full url of branchId otherwise url of current page.
		if ($branchId == 0) {
			return $this->url;
		} else {
			
			$buildUrl = array();
			$n = 1;
			while ($branchId > 0) { 
				$query = sprintf("SELECT branchId, parentId, urlSegment FROM a_tree WHERE branchId = '%s'",
					db_escape( $branchId ) );
				$result = db_query($query);
				$branch = db_fetch_assoc($result);
				
					array_push($buildUrl,$branch['urlSegment']);	
					$branchId = $branch['parentId'];
				$n++;
				if ($n >= 20) { // max 20 steps just in case!
					break;
				}
				
			}
			
			return '/' . implode('/',array_reverse($buildUrl));
			
		}
				
	}
	
	
	public function getCurrentPage() {	
		return $this->currentPage;		
	}
	
	public function getCurrentTemplate() {
            return strtolower($this->currentTemplate);
	}
	
	public function getCurrentTemplateId() {	
		return $this->currentTemplateId;		
	}
	
	public function getCurrentBranchId() {	
		return $this->currentBranchId;		
	}
	
	public function getParentBranchId() {	
		return $this->branchResult['parentId'];			
	}
	
	public function getTitle($branchId = '') {	
		if ($branchId == '') {
			return $this->title;			
		} else {
			$query = sprintf("SELECT title FROM a_tree WHERE branchId = '%s'",
				db_escape( $branchId ) );
			$result = db_query($query);
			$branch = db_fetch_assoc($result);
			return $branch['title'];
		}
	}
	
	public function getDateAdded() {	
		return $this->dateAdded;			
	}
	
	public function getMetaKeywords() {	
		return $this->branchResult['keywords'] ?? '';			
	}

	public function getMetaDescription() {	
		return $this->branchResult['description'] ?? '';			
	}
	
	
	// setters
	function setCurrentPage($page = '') {
	
		if ($page == '') {
			// work out current page from url
			$title = null; // only set for pages found from the url
			
			// homepage
			if ($this->url == '' || $this->url == '/') {
				$page = 'home';
				$templateId = $this->siteSettings['homeTemplateId'];
				$template = $this->siteSettings['homeTemplateName'];
				$this->currentBranchId = $this->siteSettings['homeBranchId'];
				$dateAdded = $this->siteSettings['homeDateAdded'];
				
				if ($templateId == '' || $template == '') {
					$page = '';
				}
			} else {
				
				$parentId = 0;
				
				for ($i = 0; $i < sizeof($this->urlStructure); ++$i) { // loop through part of url in array
				
					$urlSegment = $this->urlStructure[$i];
					
					if ($i == 0) { // first segment will never have a parent
						$query = sprintf("SELECT a_tree.*, a_templates.templateName FROM a_tree INNER JOIN a_templates ON a_tree.templateId = a_templates.templateId WHERE a_tree.urlSegment = '%s' AND a_tree.parentId = 0",
						    db_escape($urlSegment));	
					}
					
					if ($i > 0) {
						$query = sprintf("SELECT a_tree.*, a_templates.templateName FROM a_tree INNER JOIN a_templates ON a_tree.templateId = a_templates.templateId WHERE a_tree.urlSegment = '%s' AND a_tree.parentId = '%s'",
						    db_escape($urlSegment),
							db_escape($parentId)
							);	
					}
					
					$branchResult = db_query($query);					
					$branchResultRow = db_fetch_assoc($branchResult);
					
					if (db_num_rows($branchResult) > 0) { // segment found
						
						$this->urlStructureIds[] = $branchResultRow['branchId'];
						$parentId = $branchResultRow['branchId'];
						$this->branchResult = $branchResultRow;					
						
					}
					
					
					
					$page = $branchResultRow['urlSegment'] ?? '';
					$template = $branchResultRow['templateName'] ?? '';
					$title = $branchResultRow['title'] ?? '';
					$dateAdded = $branchResultRow['dateAdded'] ?? '';
					$templateId = $branchResultRow['templateId'] ?? '';
					
					
				} // end for loop
				
				// redirect if type is a manual link
				if (strtolower($branchResultRow['type'] ?? '')  == 'link') {
					header('location: ' . $branchResultRow['description']);
					exit;
				}				

			}

            // site offline page
            if ($this->siteSettings['offlineMode'] == 'Y' && !$this->adminMode) {
                $page = 'offline';
                $templateId = $this->siteSettings['offlineTemplateId'];
                $template = $this->siteSettings['offlineTemplateName'];
                $this->currentBranchId = $this->siteSettings['offlineBranchId'];
            }
			
			// nothing found so pop a 404
			if ($page == '') {
				$page = '404';
				$template = '404';
				$templateId = 0;
				$title = '404 Page Not Found';				
			}
			
		}
	
		$this->currentPage = $page;
		$this->currentTemplate = $template;
		$this->currentTemplateId = $templateId;
		if ($this->currentBranchId == '') { $this->currentBranchId = end($this->urlStructureIds); }
		$this->title = $title;
		$this->dateAdded = $dateAdded;
		$_SESSION['a_currentBranchId'] = $this->currentBranchId;
		
	}



}
?>