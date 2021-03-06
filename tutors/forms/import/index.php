<?php
/**
 *
 * Interface that allows the user to import a XML form
 *
 *
 * @copyright 2007 Loughborough University
 * @license http://www.gnu.org/licenses/gpl.txt
 * @version 0.0.0.2
 * @since 29 Oct 2007
 *
 */

require_once('../../../includes/inc_global.php');

if (!check_user($_user, APP__USER_TYPE_TUTOR)){
  header('Location:'. APP__WWW .'/logout.php?msg=denied');
  exit;
}

$UI->page_title = APP__NAME .' '.gettext('load form');
$UI->menu_selected = gettext('my forms');
$UI->help_link = '?q=node/244';
$UI->breadcrumbs = array('home'      => '../../' ,
    gettext('my forms')  => '../' ,
    gettext('load form') => null,);

$UI->set_page_bar_button(gettext('List Forms'), '../../../../images/buttons/button_form_list.gif', '../');
$UI->set_page_bar_button(gettext('Create a new Form'), '../../../../images/buttons/button_form_create.gif', '../create/');
$UI->set_page_bar_button(gettext('Clone a Form'), '../../../../images/buttons/button_form_clone.gif', '../clone/');
$UI->set_page_bar_button(gettext('Import a Form'), '../../../../images/buttons/button_form_import.gif', '../import/');

$UI->head();
$UI->body();

$UI->content_start();
?>
<p><?php echo gettext('Here you can import forms which have been exported from the WebPA system.');?></p>
<div class="content_box">
  <h2><?php echo gettext('load form by \'cut and paste\'');?></h2>
  <p>
<?php echo gettext('Please copy and paste the contents of the form file into the text area below and click the <i>load</i> button.');?>
  </p>

  <form name="frmXml" action="import.php" method="get" enctype="text/plain">

    <textarea cols="80" rows="20" name="txtXml"></textarea><br/>
    <input type="submit" name="btnLoad" value="<?php echo gettext('load');?>"/>

  </form>
</div>

<div class="content_box">
  <h2><?php echo gettext('load form by XML file');?></h2>
  <p>
<?php echo gettext('Browse for the XML file on your computer and click the <i>load</i> button.');?>
  </p>


  <form name="frmXml" enctype="multipart/form-data" action="xml_file.php" method="POST">
    <input type="hidden" name="MAX_FILE_SIZE" value="500000" />
    <input name="uploadedfile" type="file" /><br/>
    <input type="submit" name="btnLoad" value="<?php echo gettext('load');?>"/>

  </form>
</div>
<?php

$UI->content_end();

?>
