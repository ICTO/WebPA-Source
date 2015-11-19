<?php

/**
 *
 * Edit Assessment
 *
 *
 * @copyright 2007 Loughborough University
 * @license http://www.gnu.org/licenses/gpl.txt
 * @version 1.0.0.4
 *
 */

require_once("../../../includes/inc_global.php");
require_once(DOC__ROOT . 'includes/classes/class_assessment.php');
require_once(DOC__ROOT . 'includes/classes/class_form.php');
require_once(DOC__ROOT . 'includes/classes/class_group_handler.php');
require_once(DOC__ROOT . 'includes/classes/class_result_handler.php');
require_once(DOC__ROOT . 'includes/functions/lib_form_functions.php');
require_once('../../../includes/classes/class_email.php');

if (!check_user($_user, APP__USER_TYPE_TUTOR)){
  header('Location:'. APP__WWW .'/logout.php?msg=denied');
  exit;
}

// --------------------------------------------------------------------------------
// Process GET/POST

$assessment_id = fetch_GET('a');

$tab = fetch_GET('tab');
$year = fetch_GET('y', date('Y'));

$command = fetch_POST('command');

$list_url = "../index.php?tab={$tab}&y={$year}";

// --------------------------------------------------------------------------------

$assessment = new Assessment($DB);
if ($assessment->load($assessment_id)) {
  $assessment_qs = "a={$assessment->id}&tab={$tab}&y={$year}";

  $form = new Form($DB);
  $form_xml =& $assessment->get_form_xml();
  $form->load_from_xml($form_xml);

  $group_handler = new GroupHandler();
  $collection =& $group_handler->get_collection($assessment->get_collection_id());

  $result_handler = new ResultHandler($DB);
  $result_handler->set_assessment($assessment);
} else {
  $assessment = null;
}

// --------------------------------------------------------------------------------
// Process Form

$errors = null;

if (($command) && ($assessment)) {
  switch ($command) {
    case 'save':
      // Change of name
      $assessment->name = fetch_POST('assessment_name');
      if (empty($assessment->name)) {
        $errors[] = gettext('You must give this assessment a name.');
      }

      // open_date
      $time_parts = explode(':', fetch_POST('open_date_time') );
      $time_h = $time_parts[0];
      $time_m = $time_parts[1];
      $open_date = mktime( $time_h, $time_m, 0, fetch_POST('open_date_month'), fetch_POST('open_date_day'), fetch_POST('open_date_year') );

      // close_date
      $time_parts = explode(':', fetch_POST('close_date_time') );
      $time_h = $time_parts[0];
      $time_m = $time_parts[1];
      $close_date = mktime( $time_h, $time_m, 0, fetch_POST('close_date_month'), fetch_POST('close_date_day'), fetch_POST('close_date_year') );

      //check the dates to trigger emails if needed.
      if ($assessment->open_date == $open_date) {
        $assessment->open_date = $open_date;
      } else {
        //we know the assessment has been re-opened, so email those that have not completed
        $assessment->open_date = $open_date;

        $result_handler = new ResultHandler($DB);
        $result_handler->set_assessment($assessment);
        $responded_users = $result_handler->get_responded_users();

        $member_arr = (array) $collection->get_members();
        $all_users = array_keys($member_arr);

        $users_to_email = array_diff((array) $all_users, (array) $responded_users);

        //set the email details
        $bcc_list = array_extract_column($users_to_email, 'email');
        $bcc_list[] = $_user->email;

        if (is_array($bcc_list)) {
          // Send the email
          $email = new Email();
          $email->set_bcc($bcc_list);
          $email->set_from($_user->email);
          $email->set_subject(gettext("Your Assessment has been re-opened"));
          $email->set_body(gettext("Your tutor has re-opend your assessment.")." \n" .
              gettext("The new dates for the assessment are;")."\n" .
              gettext("Open:")." " . $open_date . "\n" .
              gettext("Close:")." " . $close_date . "\n" .
              gettext("To complete your assessment please go to:")." " . APP__WWW . "\n" .
              "---------------------------------------------------------\n" .
              gettext("This is an automated email sent by the WebPA tool"));
          $email->send();
        }

      }

      $assessment->close_date = $close_date;
      if ($open_date>=$close_date) { $errors[] = gettext('You must select a closing date/time that is after your opening date'); }

      $assessment->introduction = fetch_POST('introduction');

      $assessment->assessment_type = fetch_POST('assessment_type');
      $assessment->allow_feedback = fetch_POST('allow_feedback');
      $assessment->allow_assessment_feedback = fetch_POST('allow_assessment_feedback');
      $assessment->feedback_name = fetch_POST('feedback_title');
      $assessment->email_opening = fetch_POST('email_opening');
      $assessment->email_closing = fetch_POST('email_closing');

      // If there were no errors, save the changes
      if (!$errors) {
        $assessment->save();
      }
      break;

    case 'delete':
      $assessment->delete();
      header("Location: $list_url");
      exit;
      break;
  }// /switch
}

// --------------------------------------------------------------------------------
/*
* Render a set of dropdown boxes for datetime selection
*/
function render_datetime_boxes($field_name = 'datetime' , $selected_datetime) {
  echo('<table cellpadding="0" cellspacing="0"><tr>');

  // Draw day box
  echo("<td><select name=\"{$field_name}_day\">");
  render_options_range(1, 31, 1, date('j',$selected_datetime));
  echo('</select></td>');

  $form_months = array( 1 => gettext('January'),gettext('February'),gettext('March'),gettext('April'),gettext('May'),gettext('June'),gettext('July'),gettext('August'),gettext('September'),gettext('October'),gettext('November'),gettext('December'));

  // Draw month box
  echo("<td><select name=\"{$field_name}_month\">");
  render_options($form_months, date('n', $selected_datetime));
  echo('</select></td>');

  // Draw year box
  echo("<td><select name=\"{$field_name}_year\">");
  $year = (date('Y',time())<date('Y',$selected_datetime)) ? date('Y',time()) : date('Y',$selected_datetime) ;
  render_options_range($year, date('Y',time())+1, 1, date('Y', $selected_datetime));
  echo('</select></td>');

  echo('<th>'.gettext('at').'</th>');

  // Draw time box
  $time = date('H:i', $selected_datetime);
  $time_parts = explode(':',$time);
  $time_h = (int) $time_parts[0];
  $time_m = (int) $time_parts[1];

  echo("<td><select name=\"{$field_name}_time\">");
  for ($i=0; $i<=23; $i++) {
    for ($j=0; $j<=45; $j=$j+15) {
      $selected = ( ($i == $time_h) && ($j == $time_m) ) ? 'selected="selected"' : '' ;
      printf('<option value="%1$02d:%2$02d" '. $selected .'> %1$02d:%2$02d </option>',$i,$j);
    }
  }
  echo('</select></td>');

  echo('</tr></table>');
}

// --------------------------------------------------------------------------------
// Begin Page

$page_title = ($assessment) ? gettext("manage:")." {$assessment->name}" : gettext('manage assessment');

$UI->page_title = APP__NAME . ' ' . $page_title;
$UI->menu_selected = gettext('my assessments');
$UI->breadcrumbs = array  ('home'       => '/' ,
    gettext('my assessments') => '/tutors/assessments/' ,
               $page_title    => null ,);

$UI->set_page_bar_button(gettext('List Assessments'), '../../../../images/buttons/button_assessment_list.gif', '../');
$UI->set_page_bar_button(gettext('Create Assessments'), '../../../../images/buttons/button_assessment_create.gif', '../create/');

$UI->head();
?>
<style type="text/css">
<!--

table.grid th { text-align: center; }
table.grid td { text-align: center; }

div.question { padding-bottom: 4px; }
span.question_range { font-size: 0.8em; }

-->
</style>
<script language="JavaScript" type="text/javascript">
<!--

  function do_command(com) {
    switch (com) {
      case 'delete' :
            if (confirm('<?php echo gettext('This assessment will be deleted.\\n\\nClick OK to confirm.');?>')) {
              document.assessment_form.command.value = '<?php echo gettext('delete');?>';
              document.assessment_form.submit();
            }
            break;
      case 'preview' :
            var popupwin;
            popupwin = window.open('../../tutors/forms/preview_form.php?f=<?php echo($form->id); ?>','<?php echo gettext('preview');?>');
            popupwin.focus();
            break;
      default :
            document.assessment_form.command.value = com;
            document.assessment_form.submit();
    }
  }// /do_command()

//-->
</script>
<?php
$UI->content_start();

$UI->draw_boxed_list($errors, 'error_box', gettext('The following errors were found:'), gettext('No changes have been saved. Please check the details in the form, and try again.'));

?>

<p><?php echo gettext('On this page you can change the name of this assessment, and change the collection of groups to assess, and the form to use.');?></p>

<div class="content_box">

<?php
if (!$assessment) {
?>
  <div class="nav_button_bar">
    <a href="<?php echo($list_url) ?>"><img src="../../../images/buttons/arrow_green_left.gif" alt="back -"> <?php echo gettext('back to assessments list');?></a>
  </div>

  <p><?php echo gettext('The assessment you selected could not be loaded for some reason - please go back and try again.');?></p>
<?php
} else {
?>

  <form action="edit_assessment.php?<?php echo($assessment_qs); ?>" method="post" name="assessment_form">
  <input type="hidden" name="command" value="none" />

  <div class="nav_button_bar">
    <table cellpadding="0" cellspacing="0" width="100%">
    <tr>
      <td><a href="<?php echo($list_url); ?>"><img src="../../../images/buttons/arrow_green_left.gif" alt="<?php echo gettext('back');?> -"> <?php echo gettext('back to assessment list');?></a></td>

<?php
  // If not locked, allow deletion
  if (!$assessment->is_locked()) {
?>
      <td align="right"><input class="danger_button" type="button" name="" value="<?php echo gettext('delete assessment');?>" onclick="do_command('delete');" /></td>
<?php
  }
?>
    </tr>
    </table>
  </div>

<?php
  // If not locked, allow deletion
  if ($assessment->is_locked()) {
?>
    <div class="warning_box">
      <p><strong><?php echo gettext('Student marks have been recorded for this assessment.');?></strong></p>
      <p><?php echo gettext('Some parts of the assessment are now locked for editing.');?></p>
    </div>
<?php
  }
?>

  <h2><?php echo gettext('Assessment Details');?></h2>
  <div class="form_section form_line">
    <p><?php echo gettext('You can change this assessment\'s name, schedule and details using the box below. When you\'ve made your changes, click the <em>save changes</em> button.');?></p>
    <div class="form_section">
      <table class="form" cellpadding="2" cellspacing="2">
      <tr>
        <th><label for="assessment_name"><?php echo gettext('Name');?></label></th>
        <td><input type="text" name="assessment_name" id="assessment_name" maxlength="100" size="40" value="<?php echo($assessment->name)?>" /></td>
      </tr>
      </table>
    </div>

    <p><?php echo gettext('The schedule for when, and for how long, this assessment will run.');?></p>
    <div class="form_section">
      <table class="form" cellpadding="2" cellspacing="2">
      <tr>
        <th><label><?php echo gettext('Opening date');?></label></th>
        <td><?php render_datetime_boxes('open_date', $assessment->open_date); ?></td>
      </tr>
      <tr>
        <th><label><?php echo gettext('Closing date');?></label></th>
        <td><?php render_datetime_boxes('close_date', $assessment->close_date); ?></td>
      </tr>
      </table>
    </div>

    <p><?php echo gettext('The text to use as the introduction to your assessment (optional).');?></p>
    <div class="form_section">
      <table class="form" cellpadding="2" cellspacing="2">
      <tr>
        <th valign="top" style="padding-top: 2px; vertical-align: top;"><label for="introduction"><?php echo gettext('Introduction');?></label></th>
        <td width="100%"><textarea name="introduction" id="introduction" rows="5" cols="40" style="width: 90%;"><?php echo($assessment->introduction); ?></textarea></td>
      </tr>
      </table>
    </div>

    <div style="text-align: right">
      <input type="button" name="savebutton1" id="savebutton1" value="<?php echo gettext('save changes');?>" onclick="do_command('save');" />
    </div>
  </div>

  <h2><?php echo gettext('Email Notifications');?></h2>
  <div class="form_section form_line">

<?php
  if (APP__REMINDER_OPENING) {

    $email_opening = $assessment->email_opening;
?>
      <p><label><?php echo gettext('Email a reminder to all students 48 hours before the assessment is opened');?></label></p>

      <div class="form_section">
        <table class="form" cellpadding="2" cellspacing="2">
        <tr>
          <td><input type="radio" name="email_opening" id="email_yes" value="1" <?php echo( ($email_opening) ? 'checked="checked"' : '' ); ?> /></td>
          <td valign="top"><label class="small" for="email_yes"><?php echo gettext('Yes, email all students.');?></label></td>
        </tr>
        <tr>
          <td><input type="radio" name="email_opening" id="email_no" value="0" <?php echo( (!$email_opening) ? 'checked="checked"' : '' ); ?> /></td>
          <td valign="top"><label class="small" for="email_no"><?php echo gettext('No, don\'t email all students.');?>'</label></td>
        </tr>
        </table>
      </div>
<?php
  }

  if (APP__REMINDER_CLOSING) {
    $email_closing = $assessment->email_closing;
?>
      <p><label><?php echo gettext('Email all students 48 hours before the assessment closes');?></label></p>

      <div class="form_section">
        <table class="form" cellpadding="2" cellspacing="2">
        <tr>
          <td><input type="radio" name="email_closing" id="email_yes" value="1" <?php echo( ($email_closing) ? 'checked="checked"' : '' ); ?> /></td>
          <td valign="top"><label class="small" for="email_yes"><?php echo gettext('Yes, email all students.');?></label></td>
        </tr>
        <tr>
          <td><input type="radio" name="email_closing" id="email_no" value="0" <?php echo( (!$email_closing) ? 'checked="checked"' : '' ); ?> /></td>
          <td valign="top"><label class="small" for="email_no"><?php echo gettext('No, don\'t email all students.');?>'</label></td>
        </tr>
        </table>
      </div>
<?php
  }
?>
  </div>

  <h2><?php echo gettext('Assessment Form');?></h2>
  <div class="form_section form_line">
<?php
  echo("<p><label>".gettext('You are using a copy of form:')." </label><em>{$form->name}</em></p>");

  $question_count = (int) $form->get_question_count();
  if ($question_count==0) {
?>
      <p><?php echo gettext('This form has no questions.');?></p>
<?php
  } else {
?>
      <p><?php echo gettext('Below are the questions that your students will use to mark each other.');?></p>

      <ul>
<?php
    for($i=0; $i<$question_count; $i++) {
      $question = $form->get_question($i);
      ?>
      <li><div class="question"><?php echo( $question['text']['_data']); ?> <span class="question_range">(scoring range: <?php echo($question['range']['_data']); ?>)</span></div></li>
      <?php
    }
    echo('</ul>');
  }

  // If not locked, allow change of form
  if ($assessment->is_locked()) {
    ?>
    <div class="info_box">
      <p><?php echo gettext('Student marks have been recorded, so you can no longer change forms.');?></p>
    </div>
<?php
  } else {
?>
      <p><?php echo sprintf(gettext('You cannot directly change any aspect of this form or its criteria. If you need to change it, you must <a href="change_assessment_form.php?%s">select a different assessment form to use'), $assessment_qs);?></a>.</p>
<?php
  }
?>
    </div>
    <h2><?php echo gettext('Feedback / Justification');?></h2>
    <div class="form_section form_line">
    <p><label><?php echo gettext('Allow the students to view feedback on their performance in this assessment?');?></label></p>
      <div class="form_section">
        <table class="form" cellpadding="2" cellspacing="2">
        <tr>
          <td><input type="radio" name="allow_feedback" id="allow_feedback_yes" value="1" <?php echo( ($assessment->allow_feedback) ? 'checked="checked"' : '' ); ?> /></td>
          <td valign="top"><label class="small" for="allow_feedback_yes"><?php echo gettext('Yes, allow students to view feedback.');?></label></td>
        </tr>
        <tr>
          <td><input type="radio" name="allow_feedback" id="allow_feedback_no" value="0" <?php echo( (!$assessment->allow_feedback) ? 'checked="checked"' : '' ); ?> /></td>
          <td valign="top"><label class="small" for="allow_feedback_no"><?php echo gettext('No, there is no feedback for this assessment.');?></label></td>
        </tr>
        </table>
      </div>

      <p><label><?php echo gettext('Allow the students to enter feedback / justification for this assessment?');?></label></p>
      <div class="form_section">
        <table class="form" cellpadding="2" cellspacing="2">
        <tr>
          <td><label><?php echo gettext('Title');?> &nbsp;</label></td>
          <td><input type="text" name="feedback_title" value="<?php echo $assessment->feedback_name; ?>" size="40" maxlength="40"/></td>
        </tr>
        <tr>
          <td><input type="radio" name="allow_assessment_feedback" id="allow_assessment_feedback_yes" value="1" <?php echo( ($assessment->allow_assessment_feedback) ? 'checked="checked"' : '' ); ?> /></td>
          <td valign="top"><label class="small" for="allow_assessment_feedback_yes"><?php echo gettext('Yes, allow students to give feedback / justification.');?></label></td>
        </tr>
        <tr>
          <td><input type="radio" name="allow_assessment_feedback" id="allow_assessment_feedback_no" value="0" <?php echo( (!$assessment->allow_assessment_feedback) ? 'checked="checked"' : '' ); ?> /></td>
          <td valign="top"><label class="small" for="allow_assessment_feedback_no"><?php echo gettext('No, don\'t allow feedback / justification.');?></label></td>
        </tr>
        </table>
      </div>
    </div>


  <h2><?php echo gettext('Assessment Groups');?></h2>
  <div class="form_section form_line">

<?php
  echo("<p><label>".gettext('You are using a copy of collection:')." </label><em>{$collection->name}</em></p>");

  $groups = $collection->get_groups_iterator();

  $num_module_students = $CIS->get_module_students_count($_module_id);

  if ($groups->size()==0) {
?>
      <p><?php echo gettext('This collection does not contain any groups');?></p>
<?php
  } else {
?>
      <table class="grid" cellpadding="2" cellspacing="1">
      <tr>
        <th><?php echo gettext('Group Name');?></th>
        <th><?php echo gettext('Members');?></th>
        <th><?php echo gettext('Responses');?></th>
      </tr>
<?php
    $collection_total_members = 0;
    for($groups->reset(); $groups->is_valid(); $groups->next()) {
      $group =& $groups->current();
      $num_responses = $result_handler->get_responses_count($group->id);
      $num_responses = ($num_responses) ? $num_responses : '-';

      $num_members = $group->get_members_count();
      $collection_total_members += $num_members;

      echo('<tr>');
      echo("<td>{$group->name}</td>");
      echo('<td>'. $num_members .'</td>');
      echo("<td>$num_responses</td>");
      echo('</tr>');
    }
    echo('</table>');

    if ($collection_total_members<$num_module_students) {
      $diff = $num_module_students - $collection_total_members;
      $diff_units = ($diff==1) ? gettext('person remains') : gettext('people remain');
      echo("<div class=\"warning_box\"><p><strong>".gettext('Warning</strong></p><p>Not all of the people within this collection have been allocated a group.')."</p><p>".sprintf(gettext('%d %s unallocated'), $diff, $diff_units).".</p></div>");
    }
  }

  // If not locked, allow change of collection
  if ($assessment->is_locked()) {
?>
      <div class="info_box">
        <p><?php echo gettext('Student marks have been recorded, so you can no longer change collections.');?></p>
      </div>
<?php
  } else {
?>
      <p><?php echo sprintf(gettext('You cannot directly change the composition of any of these groups. If you need to change them, you must <a href="change_assessment_collection.php?%s">select a different collection of groups to use'), $assessment_qs);?></a>.</p>
<?php
  }
?>

  </div>
   <h2><?php echo gettext('Assessment Type');?></h2>
  <div class="form_section form_line">


<?php
  if ($assessment->assessment_type == '1') {
    $self_checked = 'checked=\"checked\"';
    $peer_checked = '';
  } else {
    $peer_checked = 'checked=\"checked\"';
    $self_checked = '';
  }
?>
    <table class="form" cellpadding="2" cellspacing="2">
    <tr>
      <td>
        <input type="radio" name="assessment_type" value="1" id="both" <?php echo $self_checked; ?>/>
      </td>
      <td>
        <label class="small" for="both"><?php echo gettext('Self and peer assessment');?></label>
      </td>
    </tr>
    <tr>
      <td>
        <input type="radio" name="assessment_type" value="0" id="peer" <?php echo $peer_checked; ?>/>
      </td>
      <td>
        <label class="small" for="peer"><?php echo gettext('Peer assessment only');?></label>
      </td>
    </tr>
    </table>
  </div>
  <div style="text-align: right">
    <input type="button" name="savebutton1" id="savebutton1" value="<?php echo gettext('save changes');?>" onclick="do_command('save');" />
  </div>
<?php
// If not locked, allow change of collection
  if ($assessment->is_locked()) {
?>
  <div class="info_box">
    <p><?php echo gettext('Student marks have been recorded, so you can no longer change then assessment type.');?></p>
  </div>
<?php
    }
?>
  </form>
<?php
}
?>
</div>

<?php

$UI->content_end();

?>
