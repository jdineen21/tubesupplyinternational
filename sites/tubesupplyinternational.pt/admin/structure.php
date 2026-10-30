<?php $currentSection = "structure"; $currentPageName = "Site Structure"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php
$updated = false;

if (($_POST['action'] ?? '') == 'delete') {
	
	deleteBranch($_POST['actionId'] ?? '');
	
	$updated = true;

}

// menus
$query = sprintf("SELECT * FROM a_menus ORDER BY menuName");
$menus = db_query($query);

// unallocated tree items
$query = sprintf("SELECT a_tree.* FROM a_tree INNER JOIN a_branchtypes ON a_tree.type = a_branchtypes.typeName WHERE a_tree.menuId = 0 AND a_branchtypes.inStructure = 'Y' ORDER BY a_tree.navTitle");
$unallocated = db_query($query);



?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title><?php echo $siteName ?> | <?php echo $currentPageName ?></title>
    <?php include("inc/htmlhead.php") ?>


    <style type="text/css">

        .placeholder {
            background-color: rgba(247, 245, 0, 0.20);
            border-radius: 4px;
        }

        .ui-nestedSortable-error {
            background:#fbe3e4;
            color:#8a1f11;
        }

        .sortableWrapper {
            margin: 30px 0 30px 0;
        }

        ol {
            margin: 0 !important;
           
            /*min-height: 28px;*/
        }

		.ui-sortable {
			
			padding-bottom: 50px !important;
			
		}
        
		ol.sortable, ol.sortable ol {
            padding: 5px 0 5px 0;
            list-style-type: none;
        }
		
		
		ol.sub {			
			padding-bottom: 0px !important;
		}
		
		ol ol {
			 padding: 0 0 5px 25px !important;
			
		}

        .sortable li {
            margin: 5px 0 0 0;
            padding: 0;
        }

        .sortable li .listItem  {
            border: 1px solid #cecece;
            border-radius: 4px;
            padding: 5px;
            margin: 0;

        }

        .sortable .handle {
            float: left;
            width: 16px;
            height: 16px;
            margin-right: 10px;
            background: url("images/icon/arrow_out.png") no-repeat;
            cursor: move;
        }

        .sortable .listItemName {
            float: left;
        }

        .sortable .listItemTools {
            float: right;
            display: none;
        }

        .sortable li .listItem:hover .listItemTools  {
            display: block;
        }

        .sortable .listItemTools a {
            float: left;
            margin-left: 5px;
        }



    </style>



</head>
<body class="<?php echo $currentSection ?>Page">
    <div id="alertMessage" class="error"></div>

    <?php include("inc/header.php") ?>
    <?php include("inc/sidebar.php") ?>

    <div id="content">
        <div class="inner">

            <?php include("inc/topcolumn.php") ?>

            <!-- Start content -->


            <!-- Third / Half -->
            <div class="widgets">
                <div class="oneThree">
                    <div class="widget">
                        <div class="header"><span><span class="ico gray window"></span>Pages</span></div>
                        <div class="content">

                            <form class="formEl_b">
                                <fieldset>
                                    <legend>Create New</legend>
									<a class="uibutton icon add " href="new-branch.htm?type=page">Create new page</a>
                                </fieldset>
                            </form>

                            <form class="formEl_b" method="POST" action="structure.htm" >
                                <fieldset>
                                    <legend>Unallocated Items</legend>
									<input type="hidden" name="action" class="formAction" value="">
									<input type="hidden" name="actionId" class="formActionId" value="">
									<div class="sortableWrapper">
                                        <ol class="sortable" id="unallocated">
                                            <?php while($item = db_fetch_assoc($unallocated)) { ?>
                                            <li id="branchId-<?php echo $item['branchId'] ?>" class="no-nest"><div class="listItem"><div class="handle"></div><div class="listItemName"><strong><?php echo $item['title'] ?></strong></div><div class="listItemTools"><a href="content-edit.htm?branchId=<?php echo $item['branchId'] ?>">Edit</a> <?php if ($item['allowDelete'] == 'Y') { ?><a class="special Delete" href="javascript:;" onclick="$('.formAction').val('delete'); $('.formActionId').val('<?php echo $item['branchId'] ?>'); changesMade = false;" name="<?php echo $item['title'] ?>">Delete</a><?php } ?></div><div class="clear"></div></div></li>
                                            <?php } ?>
                                        </ol>
                                    </div>
									
                                    <script type="text/javascript">

                                        $(document).ready(function(){

                                            $('#unallocated').sortable({
                                                forcePlaceholderSize: true,
                                                handle: '.handle',
                                                helper:	'clone',
                                                items: 'li',
                                                maxLevels: 1,
                                                opacity: .6,
                                                placeholder: 'placeholder',
                                                revert: 250,
                                                tabSize: 25,
                                                tolerance: 'pointer',
                                                toleranceElement: '> div',
                                                connectWith: '.sortable'
                                            });
                                        });
                                    </script>


                                </fieldset>
                            </form>

                        </div>
                    </div>
                </div>

                <div class="twoOne">
                    <div class="widget">
                        <div class="header"><span><span class="ico gray window"></span>Menu Structure</span><div id="buttom_top">
                                <ul class="uibutton-group">
                                    <li><a class="uibutton" href="javascript:;" id="updateMenuStructure" style="display: none">Update Menu Structure</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="content">

                            <?php while($menu = db_fetch_assoc($menus)) { ?>
                            <script type="text/javascript">

                                $(document).ready(function(){

                                    $('#menu<?php echo $menu['menuId']?>').nestedSortable({
                                        disableNesting: 'no-nest',
                                        forcePlaceholderSize: true,
                                        handle: '.handle',
                                        helper:	'clone',
                                        items: 'li',
                                        maxLevels: <?php echo $menu['maxDepth'] ?>,
                                        opacity: .6,
                                        placeholder: 'placeholder',
                                        revert: 250,
                                        tabSize: 25,
                                        tolerance: 'pointer',
                                        toleranceElement: '> div',
                                        connectWith: '.sortable',
                                        update: function(event,ui) { changesMade = true; $('#updateMenuStructure').show();}
                                    });
                                });
                            </script>
                            <!-- title box -->
                            <div class="boxtitle min" id="boxtitle<?php echo $menu['menuId']?>"><?php echo $menu['menuName'] ?></div>

                            <div class="sortableWrapper">
								<form method="POST" action="structure.htm">
								<input type="hidden" name="action" class="formAction" value="">
								<input type="hidden" name="actionId" class="formActionId" value="">
                                <?php generateMenuHTML($menu['menuId'],$menu['menuName'],0,0,'sortable') ?>
								</form>

                            </div>

                            <?php } ?>

                            <script type="text/javascript">
                                $('#updateMenuStructure').click(function(){
                                <?php
                                    db_data_seek($menus,0);
                                    while($menu = db_fetch_assoc($menus)) {
                                ?>
                                        serialized = $('#menu<?php echo $menu['menuId']?>').nestedSortable('serialize');


                                        $.ajax({
                                            type: "POST",
                                            url: "ajax/structure-update.htm?menuId=<?php echo $menu['menuId']?>",
                                            data: serialized
                                        }).done(function( msg ) {
                                            //alert( "Data Saved: " + msg );
                                            changesMade = false;
                                            $('#updateMenuStructure').hide();
                                            showSuccess('Menu structure updated');
                                        });
                                <?php } ?>
                                });
                            </script>

                        </div>
                    </div>
                </div>
                <div class="clear"></div>
            </div>







            <?php include("inc/footer.php") ?>
        </div> <!--// End inner -->
    </div> <!--// End content -->
	
	<script type="text/javascript">
        <?php if ($updated) { ?>
        showSuccess('Structure Updated');
            <?php } ?>
    </script>
	
</body>
</html>