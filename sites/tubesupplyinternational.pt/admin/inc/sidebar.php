<!-- START SIDEBAR -->
<div id="left_menu">
    <ul id="main_menu" class="main_menu">
        <li class="limenu<?php if ($currentSection == 'dashboard') { echo ' select'; } ?>"><a href="/admin/index.htm"><span class="ico gray shadow home" ></span><b>Dashboard</b></a></li>
        <li class="limenu<?php if ($currentSection == 'structure') { echo ' select'; } ?>" ><a href="/admin/structure.htm" ><span class="ico gray shadow list"></span><b>Menu Structure</b></a></li>
        <li class="limenu<?php if ($currentSection == 'content') { echo ' select'; } ?>" ><a href="/admin/content-edit.htm" ><span class="ico gray shadow pencil"></span><b>Edit Content</b></a></li>
		<li class="limenu<?php if ($currentSection == 'media') { echo ' select'; } ?>" ><a href="/admin/media.htm" ><span class="ico gray shadow pictures_folder"></span><b>Media Manager</b></a></li>
        
		<?php include("../admin-custom/sidebar.php") // custom admin menu items ?>
		
		<li class="limenu<?php if ($currentSection == 'users') { echo ' select'; } ?>" ><a href="/admin/users.htm" ><span class="ico gray shadow group"></span><b>Admin Users</b></a></li>
		
		
		
		<?php if (($_SESSION['a_loginType'] ?? '') == 'developer') { ?>
		<li class="limenu<?php if ($currentSection == 'developer') { echo ' select'; } ?>" ><a href="#" ><span class="ico gray shadow gear"></span><b>Developer</b></a>
			<ul>
				<li ><a href="/admin/dev-tasks.htm">Task List</a></li>
			</ul>

		</li>
		<?php } ?>
		
		
		
		<li class="limenu<?php if ($currentSection == 'settings') { echo ' select'; } ?>" ><a href="/admin/settings.htm" ><span class="ico gray shadow gear"></span><b>Site Settings</b></a></li>

		
		
		<?php if($devName == "Climb Creative") { ?>
		<li class="climb"><a href="http://www.newcastlewebdesign.co.uk" target="_blank"><img src="/admin/images/climb.png"/></a></li>
		<?php } ?>
		
		
    </ul>
	
	
</div>
<!-- END SIDEBAR -->