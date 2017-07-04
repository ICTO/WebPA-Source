<?php
/**
 *
 * UEA Style report
 *
 * This is the report suggested from the team using WebPA at UEA, UK
 *
 *
 * @copyright 2007 Loughborough University
 * @license http://www.gnu.org/licenses/gpl.txt
 * @version 0.0.0.1
 * @since 8 Aug 2008
 *
 */

require_once("../../../includes/inc_global.php");
require_once(DOC__ROOT . 'includes/classes/class_assessment.php');
require_once(DOC__ROOT . 'includes/classes/class_algorithm_factory.php');
require_once(DOC__ROOT . 'includes/functions/lib_array_functions.php');

if (!check_user($_user, APP__USER_TYPE_TUTOR)){
  header('Location:'. APP__WWW .'/logout.php?msg=denied');
  exit;
}

// --------------------------------------------------------------------------------
// Process GET/POST

$assessment_id = fetch_GET('a');

$type = fetch_GET('t', 'view');

$tab = fetch_GET('tab');
$year = fetch_GET('y', date('Y'));

$marking_date = fetch_GET('md');

// --------------------------------------------------------------------------------

$assessment = new Assessment($DB);
if (!$assessment->load($assessment_id)) {
  $assessment = null;

  echo(gettext('Error: The requested assessment could not be loaded.'));
  exit;
} else {

  // ----------------------------------------
  // Get the marking parameters used for the marksheet this report will display
  $marking_params = $assessment->get_marking_params($marking_date);
  if (!$marking_params) {
    echo(gettext('Error: The requested marksheet could not be loaded.'));
    exit;
  }

  // ----------------------------------------
  // Get the appropriate algorithm and calculate the grades
  $algorithm = AlgorithmFactory::get_algorithm($marking_params['algorithm']);

  if (!$algorithm) {
    echo(gettext('Error: The requested algorithm could not be loaded.'));
    exit;
  } else {
    $algorithm->set_assessment($assessment);
    $algorithm->set_marking_params($marking_params);
    $algorithm->calculate();

    $group_members = $algorithm->get_group_members();

    $member_names = array();

    for ($i =0; $i<count($group_members); $i++){
      $array_key = array_keys($group_members);
      $temp = $group_members[$array_key[$i]];
      for ($j=0; $j<count($temp);$j++){
        array_push($member_names, $CIS->get_user($temp[$j]));
      }
    }
  }// /if-else(is algorithm)

}// /if-else(is assessment)


// ----------------------------------------
// Get the questions used in this assessment
$form = new Form($DB);
$form_xml =& $assessment->get_form_xml();
$form->load_from_xml($form_xml);
$question_count = (int) $form->get_question_count();

// Create the actual array (question_ids are 0-based)
if ($question_count>0) {
  $questions = range(0, $question_count-1);
} else {
  $questions = array();
}

//get the information in the format required
$score_array = null;

//get the information in the format required
//get an array of the group names
$group_names = $algorithm->get_group_names();

if ($assessment) {
  foreach ($group_members as $group_id => $g_members) {
    $g_member_count = count($group_members[$group_id]);

    foreach ($questions as $question_id) {
      $q_index = $question_id+1;
      $question = $form->get_question($question_id);
      $q_text = "Q{$q_index} : {$question['text']['_data']}";

      foreach ($g_members as $i => $member_id) {
        $individ = $CIS->get_user($member_id);

        $mark_recipient = "{$individ['lastname']}, {$individ['forename']}";

        foreach ($g_members as $j => $target_member_id) {
          $individ = $CIS->get_user($target_member_id);

          $marker = "{$individ['lastname']}, {$individ['forename']}";

          if ($assessment->assessment_type == '0') {

            if ($member_id == $target_member_id) {
              $score_array[$group_names[$group_id]][$mark_recipient][$q_text][$marker] = 'n/a';
            } else {
              $score_array[$group_names[$group_id]][$mark_recipient][$q_text][$marker] = $algorithm->get_member_response($group_id, $target_member_id, $question_id,$member_id );
            }

          } else {
            $score_array[$group_names[$group_id]][$mark_recipient][$q_text][$marker] = $algorithm->get_member_response($group_id, $target_member_id, $question_id, $member_id);
          }
          if (is_null($score_array[$group_names[$group_id]][$mark_recipient][$q_text][$marker])) {
            $score_array[$group_names[$group_id]][$mark_recipient][$q_text][$marker] = '-';
          }
        }
      }
    }
  }
}

/*
* --------------------------------------------------------------------------------
* If report type is HTML view
* --------------------------------------------------------------------------------
*/
if ($type == 'view') {
  // Begin Page

  $page_title = ($assessment) ? "{$assessment->name}" : gettext('report');

  $UI->page_title = APP__NAME . ' ' . $page_title;
  $UI->head();
  ?>
  <style type="text/css">
  <!--

  #side_bar { display: none; }
  #main { margin: 0px; }

  table.grid th { padding: 8px; }
  table.grid td { padding: 8px; text-align: center; }

  table.grid td.important { background-color: #eec; }

  -->
  </style>
  <?php
  $UI->body();
  $UI->content_start();
?>

  <div class="content_box">

  <h1 style="font-size: 150%;"><?php echo gettext('Student Responses');?></h1>

  <table class="grid" cellpadding="2" cellspacing="1">
  <tr>
    <td>

<?php

  // add chart javascript
  echo '<script src="../../../js/Chart.min.js" type="text/javascript"></script>
  <style>
  .debug-table{
    display:none;
  }
  </style>
  ';
  //get an array of the group names
  $group_names = $algorithm->get_group_names();

  $teams = array_keys($score_array);
  $r_count = 1;
  foreach ($teams as $i=> $team) {
    echo "<h2>{$team}</h2>";
    $team_members = array_keys($score_array[$team]);
    foreach ($team_members as $team_member) {
      echo "<h3>".gettext("Results for:")." {$team_member}</h3>";
      $questions = array_keys($score_array[$team][$team_member]);
      //print_r($questions);
      echo "<table class='grid debug-table' cellpadding='2' cellspacing='1' style='font-size: 0.8em'>";
      $q_count = 0;
      $labels = [];
      $own_data = [];
      $other_data = [];
      foreach ($questions as $question) {
        $labels[] = 'Q'.($q_count+1);

        $markers = array_keys($score_array[$team][$team_member][$question]);

        $markers_row = '';
        $scores_row = '';
        $other_avarage = [];
        foreach ($markers as $marker) {
          $score = $score_array[$team][$team_member][$question][$marker];
          if(is_numeric($score)){
            if($marker == $team_member) {
              $own_data[] = $score;
            } else {
              $other_avarage[] = $score;
            }
          }
          $markers_row =  $markers_row ."<th>{$marker}</th>";
          $scores_row = $scores_row . "<td>{$score}</td>";
        }
        $other_data[] = str_replace(array('.', ','), array('', '.'), round(array_sum($other_avarage)/count($other_avarage),2));
        if ($q_count == 0) {
          echo "<tr><th>&nbsp;</th>";
          echo $markers_row;
        }
        echo "</tr><tr><th>{$question}</th>";
        echo $scores_row;
        $q_count++;
      }
      // print chart
      ?>
      <canvas width="800px" height="300px" id="Radar<?php echo $r_count; ?>"></canvas>
      <script>
        var data = {
          labels: ["<?php echo implode('","', $labels); ?>"],
          datasets: [
            {
              label: "Gemiddelde score gegeven door de andere studenten",
              backgroundColor: "rgba(30,100,200,0.2)",
              borderColor: "rgba(30,100,200,1)",
              pointBackgroundColor: "rgba(30,100,200,1)",
              pointBorderColor: "#fff",
              pointHoverBackgroundColor: "#fff",
              pointHoverBorderColor: "rgba(30,100,200,1)",
              data: [<?php echo implode(',', $other_data)?>]
            }
          <?php if($own_data) { ?>
            ,{
              label: "Eigen score",
              backgroundColor: "rgba(179,181,198,0.2)",
              borderColor: "rgba(179,181,198,1)",
              pointBackgroundColor: "rgba(179,181,198,1)",
              pointBorderColor: "#fff",
              pointHoverBackgroundColor: "#fff",
              pointHoverBorderColor: "rgba(179,181,198,1)",
              data: [<?php echo implode(',', $own_data)?>]
            }
          <?php } ?>
          ]
        };
        var options =
        {
          legend: {
            display: true
          },
          scale: {
              ticks: {
                  beginAtZero: true,
                  userCallback: function (value, index, values) {
                      return value;
                  }
              }
          }
        }
        var ctx = document.getElementById("Radar<?php echo $r_count; ?>").getContext("2d");
        var RadarChart = new Chart(ctx, {
            type: 'radar',
            data: data,
            options: options
        });
      </script>
      <?php

      $r_count++;

      echo "</tr></table><br/><br/>";
    }
  }
?>

    </td>
  </tr>
  </table>
  </div>

<?php
  $UI->content_end(false, false, false);
}
