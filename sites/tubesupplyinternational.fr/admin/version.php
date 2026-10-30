<?php
$atlasVersion = '1.1.8';
/*
********************** CHANGE LOG

1.1.8 - 08/03/2013

- dev completed tasks fade out and go to bottom
- added cleanAndStrip to admin functions. Removes html and cleans Words smart-quotes - used in meta
- fixed alt tags not saving when crop is off
- added old browser detection notice to frontend
- $atlas->getSetting($settingName) function to class to retrieve a site setting from settings table
- database update: analyticsUA column added to a_settings for analytics account code (VARCHAR 50)
- google analytics code option added to site settings panel. If entered, js code will automatically be added to htmlhead in frontend
- updated timepicker.js to fix 'now' function on datetime / postdate regions


1.1.7 - 04/03/2013

- added new region type: link - saves region id to content table
- admin dropdowns have been widened
- html editor now autoresizes to content
- html editor can pull in user styles from the /frontend/css/user.css file
- repeatRegion function has new parameters (branchId, templatedId) that allow you to use repeat regions from other pages


1.1.6 - 22/02/2013

- new feature: repeat regions
- boolean region type now has a new parameter: default
- fixed the colour picker region type as it only worked for 1 instance
- image region type can now create custom thumb sizes (new media folder added: thumb-custom)
- image region type has new parameters (quality, maxWidth, maxHeight, customThumbWidth, customThumbHeight)
- the admin side bar menu minimizes on screens below 1280 to give more room
- fixed the main admin windows width to prevent it from 'crunching' the content on small screens
- fixed global content regions sort order
- parent dropdown option added to create new page
- edit content pages list ordered as structure with scrollbar


1.1.5 - 14/02/2013

- added new region type: colour with options (defaultColour)


1.1.4 - 31/01/2013

- $atlas->getParentBranchId() function added to class
- $atlas->getDateAdded() function now fixed for homepage
- $atlas->generateMenuHTML $listItemTemplate updated to allow templating of {subTitle}
- added new region type: datetime with options (date, time, datetime, birthday)
- added new region type: number with options {number, currency, percentage)
- added ui-spinner js


1.1.3 - 03/01/2013

- $atlas->generateBreadcrumbs function added to class
- $atlas->generateMenuHTML function now has 3 extra optional parameters for controlling how the HTML is generated - $listItemTemplate, $ulOpenTemplate and $ulCloseTemplate


1.1.2 - 18/12/2012

- added navigation sub title feature to meta tab. Outputs text wrapped in a span within the navigation li
- database updates: subTitle added to a_tree
- 'go to live site' icon added to top bar to open site in a new window
- climb brand added to sidebar
- added a user loginType of 'viewer'. These have no admin access at all but is useful for viewing a site that has been set to offline


1.1.1 - 26/11/2012

- updated cookie cuttr to a new style
 

1.1.0 - 26/11/2012

- jCrop bug fixed where newly uploaded and then re-uploaded images werent showing preivew in jCrop
- new Atlas branding
- whitelabel features added
- developer tab added
- task list added to developer section
- database updates: new dev_tasks table, new a_downloads table, loginType added to a_users, allowMultiUpload add to a_templates
- downloads tab in edit content if a_templates.allowMultiUpload set to 'Y'
- $atlas->getDownloads function added to class
- added 301 redirect for non-www in .htaccess


1.0.9 - 23/10/2012

- updated template loader to convert filename to lowercase for linux compatability. same for editor loader in admin
- type field in a_tree table now accepts 'Link' as an option to manually link to the specified page or external site. Put the link in the description field
- added Link as a branchType in database
- fixed error in update structure when menu items were disappearing
- fixed query string not being passed between pages on Linux - updated .htaccess


1.0.8 - 18/10/2012

- updated admin/functions.php to stop inStructure='N' appearing in list on the right
- new column in a_branchtytpes - defaultMenuId - allows custom types to appear in menus
- updated admin/branchtypes.php to automatically add defaultMenuId to a branch
- set the default Page type to sort by title


1.0.7 - 02/10/2012

- added new region type: dropdown
- added new region type: boolean
- set the current page to be rendered by setting $branchId for the instance. i.e $atlas = new Atlas($branchId);
- manually change the content using setCurrentPage($branchId)
- $parentId parameter added to repeatBranches in class
- $atlas->image function now has ability to accept blank $size (if blank will use url specified)
- $atlas->image function now has $noCache parameter



1.0.6 - 19/09/2012

- getTitle() function in class improved. Added optional $branchId parameter
- HTMLPurifier $config updated to allow anchor target options ('_self','_blank')


1.0.5 - 17/09/2012

- getDateAdded function added to class


1.0.4 - 13/09/2012

- fixed session transfer problem in uploadify
- fixed jcrop showing twice in some browsers
- fixed home page title not being set
- tidied image upload box


1.0.3 - 11/09/2012

- deleteBranch function added to class


1.0.2 - 10/09/2012

- CMS now allows you to delete images


1.0.1 - 07/09/2012

- Duplicate class instantiation removed from template files
- New function added to class: generateMenuFromBranchType
- generateMenuHTML parameters changed: $maxDepth added, $depth move to last parameter


1.0.0 - 24/08/2012

- First Release


*/
?>