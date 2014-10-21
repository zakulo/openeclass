<?php

/* ========================================================================
 * Open eClass 3.0
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2014  Greek Universities Network - GUnet
 * A full copyright notice can be read in "/info/copyright.txt".
 * For a full list of contributors, see "credits.txt".
 *
 * Open eClass is an open platform distributed in the hope that it will
 * be useful (without any warranty), under the terms of the GNU (General
 * Public License) as published by the Free Software Foundation.
 * The full license can be read in "/info/license/license_gpl.txt".
 *
 * Contact address: GUnet Asynchronous eLearning Group,
 *                  Network Operations Center, University of Athens,
 *                  Panepistimiopolis Ilissia, 15784, Athens, Greece
 *                  e-mail: info@openeclass.org
 * ========================================================================

  ============================================================================
  @Description: Main script for the work tool
  ============================================================================
 */

$require_current_course = true;
$require_login = true;
$require_help = true;
$helpTopic = 'Work';

require_once '../../include/baseTheme.php';
require_once 'include/lib/forcedownload.php';
require_once 'work_functions.php';
require_once 'modules/group/group_functions.php';
require_once 'include/lib/fileUploadLib.inc.php';
require_once 'include/lib/fileManageLib.inc.php';
require_once 'include/sendMail.inc.php';
require_once 'modules/graphics/plotter.php';
require_once 'include/log.php';

// For colorbox, fancybox, shadowbox use
require_once 'include/lib/modalboxhelper.class.php';
require_once 'include/lib/multimediahelper.class.php';
ModalBoxHelper::loadModalBox();
/* * ** The following is added for statistics purposes ** */
require_once 'include/action.php';
$action = new action();
$action->record(MODULE_ID_ASSIGN);
/* * *********************************** */


$workPath = $webDir . "/courses/" . $course_code . "/work";
$works_url = array('url' => "$_SERVER[SCRIPT_NAME]?course=$course_code", 'name' => $langWorks);
$nameTools = $langWorks;

//-------------------------------------------
// main program
//-------------------------------------------

//Gets the student's assignment file ($file_type=NULL) 
//or the teacher's assignment ($file_type=1)
if (isset($_GET['get'])) {
    if (isset($_GET['file_type']) && $_GET['file_type']==1) {
        $file_type = intval($_GET['file_type']);
    } else {
        $file_type = NULL;
    }
    if (!send_file(intval($_GET['get']), $file_type)) {
        Session::Messages($langFileNotFound, 'caution');
    }
}

// Only course admins can download all assignments in a zip file
if ($is_editor) {    
    if (isset($_GET['download'])) {
        include 'include/pclzip/pclzip.lib.php';
        $as_id = intval($_GET['download']);
        // Allow unlimited time for creating the archive
        set_time_limit(0);
        if (!download_assignments($as_id)) {          
            Session::Messages($langNoAssignmentsExist, 'caution');
            redirect_to_home_page('modules/work/index.php?course='.$course_code.'&id='.$as_id);
        }
    }
}

if ($is_editor) {
    load_js('tools.js');
    load_js('jquery');
    load_js('jquery-ui');
    load_js('jquery-ui-timepicker-addon.min.js');  
    global $themeimg, $m;
    $head_content .= "<link rel='stylesheet' type='text/css' href='{$urlAppend}js/jquery-ui-timepicker-addon.min.css'>
    <script type='text/javascript'>
    $(function() {
        $('input[name=WorkEnd]').datetimepicker({
            showOn: 'both',
            buttonImage: '{$themeimg}/calendar.png',
            buttonImageOnly: true,
            dateFormat: 'dd-mm-yy', 
            timeFormat: 'HH:mm'
        });
        
        $('input[name=group_submissions]').click(changeAssignLabel);
        $('input[id=assign_button_some]').click(ajaxAssignees);        
        $('input[id=assign_button_all]').click(hideAssignees);
        function hideAssignees()
        {
            $('#assignees_tbl').hide();
            $('#assignee_box').find('option').remove();
        }
        function changeAssignLabel()
        {
            var assign_to_specific = $('input:radio[name=assign_to_specific]:checked').val();
            if(assign_to_specific==1){
               ajaxAssignees();
            }         
            if (this.id=='group_button') {
               $('label[for=assign_button_some]').text('$m[WorkToGroup]');
               $('td[#assignees]').text('$langGroups');    
            } else {
               $('label[for=assign_button_some]').text('$m[WorkToUser]');
               $('td[#assignees]').text('$langStudents');    
            }        
        }        
        function ajaxAssignees()
        {
            $('#assignees_tbl').show();
            var type = $('input:radio[name=group_submissions]:checked').val();
            $.post('$works_url[url]',
            {
              assign_type: type
            },
            function(data,status){
                var index;
                var parsed_data = JSON.parse(data);
                var select_content = '';
                if(type==0){
                    for (index = 0; index < parsed_data.length; ++index) {
                        select_content += '<option value=\"' + parsed_data[index]['id'] + '\">' + parsed_data[index]['surname'] + ' ' + parsed_data[index]['givenname'] + '<\/option>';
                    }
                } else {
                    for (index = 0; index < parsed_data.length; ++index) {
                        select_content += '<option value=\"' + parsed_data[index]['id'] + '\">' + parsed_data[index]['name'] + '<\/option>';
                    }            
                }
                $('#assignee_box').find('option').remove();
                $('#assign_box').find('option').remove().end().append(select_content);
            });
        }
    });
    
    </script>";    

    $email_notify = (isset($_POST['email']) && $_POST['email']);
    if (isset($_POST['grade_comments'])) {
        $work_title = Database::get()->querySingle("SELECT title FROM assignment WHERE id = ?d", intval($_POST['assignment']))->title;
        $nameTools = $work_title;
        $navigation[] = $works_url;
        submit_grade_comments($_POST['assignment'], $_POST['submission'], $_POST['grade'], $_POST['comments'], $email_notify);
    } elseif (isset($_GET['add'])) {
        $nameTools = $langNewAssign;
        $navigation[] = $works_url;        
        new_assignment();
    } elseif (isset($_POST['assign_type'])) {
        if ($_POST['assign_type']) {
            $data = Database::get()->queryArray("SELECT name,id FROM `group` WHERE course_id = ?d", $course_id);                
        } else {
            $data = Database::get()->queryArray("SELECT user.id AS id, surname, givenname
                                    FROM user, course_user
                                    WHERE user.id = course_user.user_id 
                                    AND course_user.course_id = ?d AND course_user.status = 5 
                                    AND user.id", $course_id);                
               
        }
        echo json_encode($data);
        exit;      
    } elseif (isset($_POST['new_assign'])) {
        if($_POST['title']) {
            if(add_assignment()) {
                Session::Messages($langNewAssignSuccess,'success');
                show_assignments();
            }
        } else {
            Session::Messages($m['WorkTitleValidation'],'caution');
            $nameTools = $langNewAssign;
            $navigation[] = $works_url;
            new_assignment();
        }
    } elseif (isset($_GET['as_id'])) {
        $as_id = intval($_GET['as_id']);
        $id = intval($_GET['id']);
        if(delete_user_assignment($as_id)){
            Session::Messages($langDeleted, 'success');
        } else {
            Session::Messages($langDelError, 'caution');
        }
        redirect_to_home_page('modules/work/index.php?course='.$course_code.'&id='.$id);
    } elseif (isset($_POST['grades'])) {
        $nameTools = $langWorks;
        $navigation[] = $works_url;
        submit_grades(intval($_POST['grades_id']), $_POST['grades'], $email_notify);
    } elseif (isset($_REQUEST['id'])) {
        $id = intval($_REQUEST['id']);
        $work_title = q(Database::get()->querySingle("SELECT title FROM assignment WHERE id = ?d", $id)->title);
        $work_id_url = array('url' => "$_SERVER[SCRIPT_NAME]?course=$course_code&id=$id",
            'name' => $work_title);
        if (isset($_POST['on_behalf_of'])) {
            if (isset($_POST['user_id'])) {
                $user_id = intval($_POST['user_id']);
            } else {
                $user_id = $uid;
            }
            $nameTools = $langAddGrade;
            $navigation[] = $works_url;
            $navigation[] = $work_id_url;
            submit_work($id, $user_id);
        } elseif (isset($_REQUEST['choice'])) {
            $choice = $_REQUEST['choice'];
            if ($choice == 'disable') {
                if (Database::get()->query("UPDATE assignment SET active = 0 WHERE id = ?d", $id)->affectedRows > 0) {
                    Session::Messages($langAssignmentDeactivated, 'success');
                }
                redirect_to_home_page('modules/work/index.php?course='.$course_code);
            } elseif ($choice == 'enable') {
                if (Database::get()->query("UPDATE assignment SET active = 1 WHERE id = ?d", $id)->affectedRows > 0) {
                    Session::Messages($langAssignmentActivated, 'success');
                }
                redirect_to_home_page('modules/work/index.php?course='.$course_code);
            } elseif ($choice == 'do_delete') {
                if(delete_assignment($id)) {
                    Session::Messages($langDeleted, 'success');
                } else {
                    Session::Messages($langDelError, 'caution');
                }
                redirect_to_home_page('modules/work/index.php?course='.$course_code);
            } elseif ($choice == 'do_delete_file') {
                if(delete_teacher_assignment_file($id)){
                    Session::Messages($langDelF, 'success');
                } else {
                    Session::Messages($langDelF, 'caution');
                }
                redirect_to_home_page('modules/work/index.php?course='.$course_code.'&id='.$id.'&choice=edit');
            } elseif ($choice == 'do_purge') {
                if (purge_assignment_subs($id)) {
                    Session::Messages($langAssignmentSubsDeleted, 'success');
                }
                redirect_to_home_page('modules/work/index.php?course='.$course_code);
            } elseif ($choice == 'edit') {
                $nameTools = $m['WorkEdit'];
                $navigation[] = $works_url;
                $navigation[] = $work_id_url;
                show_edit_assignment($id);
            } elseif ($choice == 'do_edit') {
                $nameTools = $langWorks;
                $navigation[] = $works_url;
                $navigation[] = $work_id_url;
                if($_POST['title']){
                    if (edit_assignment($id)) {
                        Session::Messages($langEditSuccess,'success');
                    }
                    redirect_to_home_page('modules/work/index.php?course='.$course_code);
                } else {
                    Session::Messages($m['WorkTitleValidation'],'caution');
                    redirect_to_home_page('modules/work/index.php?course='.$course_code.'&id='.$id.'&choice=edit');
                }         
            } elseif ($choice == 'add') {
                $nameTools = $langAddGrade;
                $navigation[] = $works_url;
                $navigation[] = $work_id_url;
                show_submission_form($id, groups_with_no_submissions($id), true);
            } elseif ($choice == 'plain') {
                show_plain_view($id);
            }
        } else {
            $nameTools = $work_title;
            $navigation[] = $works_url;
            if (isset($_GET['disp_results'])) {
                show_assignment($id, true);
            } elseif (isset($_GET['disp_non_submitted'])) {
                show_non_submitted($id);
            } else {
                show_assignment($id);
            }
        }
    } else {
        $nameTools = $langWorks;
        show_assignments();
    }
} else {
    if (isset($_REQUEST['id'])) {
        $id = intval($_REQUEST['id']);
        if (isset($_POST['work_submit'])) {
            $nameTools = $m['SubmissionStatusWorkInfo'];
            $navigation[] = $works_url;
            $navigation[] = array('url' => "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id", 'name' => $langWorks);
            submit_work($id);
        } else {
            $work_title = Database::get()->querySingle("SELECT title FROM assignment WHERE id = ?d", $id)->title;
            $nameTools = $work_title;
            $navigation[] = $works_url;
            show_student_assignment($id);
        }
    } else {
        show_student_assignments();
    }
}

add_units_navigation(TRUE);
draw($tool_content, 2, null, $head_content);

//-------------------------------------
// end of main program
//-------------------------------------

// insert the assignment into the database
function add_assignment() {
    global $tool_content, $workPath, $course_id, $uid;
    
    $title = $_POST['title'];
    $desc = $_POST['desc'];
    $deadline = (trim($_POST['WorkEnd'])!=FALSE) ? date('Y-m-d H:i', strtotime($_POST['WorkEnd'])) : '0000-00-00 00:00:00';
    $late_submission = ((isset($_POST['late_submission']) &&  trim($_POST['WorkEnd']!=FALSE)) ? 1 : 0);
    $group_submissions = filter_input(INPUT_POST, 'group_submissions', FILTER_VALIDATE_INT);
    $max_grade = filter_input(INPUT_POST, 'max_grade', FILTER_VALIDATE_FLOAT);
    $assign_to_specific = filter_input(INPUT_POST, 'assign_to_specific', FILTER_VALIDATE_INT);
    $assigned_to = filter_input(INPUT_POST, 'ingroup', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);
  	 $auto_judge = filter_input(INPUT_POST, 'auto_judge', FILTER_VALIDATE_INT);
      $secret = uniqid('');


    if ($assign_to_specific == 1 && empty($assigned_to)) {
        $assign_to_specific = 0;
    }
    if (@mkdir("$workPath/$secret", 0777) && @mkdir("$workPath/admin_files/$secret", 0777, true)) {       
        $id = Database::get()->query("INSERT INTO assignment (course_id, title, description, deadline, late_submission, comments, submission_date, secret_directory, group_submissions, max_grade, assign_to_specific, auto_judge) "
                . "VALUES (?d, ?s, ?s, ?t, ?d, ?s, ?t, ?s, ?d, ?d, ?d, ?d)", $course_id, $title, $desc, $deadline, $late_submission, '', date("Y-m-d H:i:s"), $secret, $group_submissions, $max_grade, $assign_to_specific, $auto_judge)->lastInsertID;
        $secret = work_secret($id);
        if ($id) {
            $local_name = uid_to_name($uid);
            $am = Database::get()->querySingle("SELECT am FROM user WHERE id = ?d", $uid)->am;
            if (!empty($am)) {
                $local_name .= $am;
            }
            $local_name = greek_to_latin($local_name);
            $local_name = replace_dangerous_char($local_name);            
            if (!isset($_FILES) || !$_FILES['userfile']['size']) {
                $_FILES['userfile']['name'] = '';
                $_FILES['userfile']['tmp_name'] = '';
            } else {
                validateUploadedFile($_FILES['userfile']['name'], 2);
                if (preg_match('/\.(ade|adp|bas|bat|chm|cmd|com|cpl|crt|exe|hlp|hta|' . 'inf|ins|isp|jse|lnk|mdb|mde|msc|msi|msp|mst|pcd|pif|reg|scr|sct|shs|' . 'shb|url|vbe|vbs|wsc|wsf|wsh)$/', $_FILES['userfile']['name'])) {
                    $tool_content .= "<p class=\"caution\">$langUnwantedFiletype: {$_FILES['userfile']['name']}<br />";
                    $tool_content .= "<a href=\"$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id\">$langBack</a></p><br />";
                    return;
                }
                $ext = get_file_extension($_FILES['userfile']['name']);
                $filename = "$secret/$local_name" . (empty($ext) ? '' : '.' . $ext);
                if (move_uploaded_file($_FILES['userfile']['tmp_name'], "$workPath/admin_files/$filename")) {
                    @chmod("$workPath/admin_files/$filename", 0644);
                    $file_name = $_FILES['userfile']['name'];
                    Database::get()->query("UPDATE assignment SET file_path = ?s, file_name = ?s WHERE id = ?d", $filename, $file_name, $id);
                }                
            }                    
            if ($assign_to_specific && !empty($assigned_to)) {
                if ($group_submissions == 1) {
                    $column = 'group_id';
                    $other_column = 'user_id';
                } else {
                    $column = 'user_id';
                    $other_column = 'group_id';
                }
                foreach ($assigned_to as $assignee_id) {
                    Database::get()->query("INSERT INTO assignment_to_specific ({$column}, {$other_column}, assignment_id) VALUES (?d, ?d, ?d)", $assignee_id, 0, $id);
                }
            }    
            Log::record($course_id, MODULE_ID_ASSIGN, LOG_INSERT, array('id' => $id,
                'title' => $title,
                'description' => $desc,
                'deadline' => $deadline,
                'secret' => $secret,
                'group' => $group_submissions));
            return true;
        } else {
            @rmdir("$workPath/$secret");
            return false;
        }
    } else {
       return false;
    }
}

function submit_work($id, $on_behalf_of = null) {
    global $tool_content, $workPath, $uid, $course_id, $works_url,
    $langUploadSuccess, $langBack, $langUploadError,
    $langExerciseNotPermit, $langUnwantedFiletype, $course_code,
    $langOnBehalfOfUserComment, $langOnBehalfOfGroupComment, $course_id;

    if (isset($on_behalf_of)) {
        $user_id = $on_behalf_of;
    } else {
        $user_id = $uid;
    }
    $submit_ok = FALSE; // Default do not allow submission
    if (isset($uid) && $uid) { // check if logged-in
        if ($GLOBALS['status'] == 10) { // user is guest
            $submit_ok = FALSE;
        } else { // user NOT guest
            if (isset($_SESSION['courses']) && isset($_SESSION['courses'][$_SESSION['dbname']])) {
                // user is registered to this lesson
                $row = Database::get()->querySingle("SELECT deadline, late_submission, CAST(UNIX_TIMESTAMP(deadline)-UNIX_TIMESTAMP(NOW()) AS SIGNED) AS time
                                              FROM assignment WHERE id = ?d", $id);
                if (($row->time < 0 && (int) $row->deadline && !$row->late_submission) and !$on_behalf_of) {
                    $submit_ok = FALSE; // after assignment deadline
                } else {
                    $submit_ok = TRUE; // before deadline
                }
            } else {
                //user NOT registered to this lesson
                $submit_ok = FALSE;
            }
        }
    } //checks for submission validity end here
    
    $row = Database::get()->querySingle("SELECT title, group_submissions, auto_judge FROM assignment WHERE course_id = ?d AND id = ?d", $course_id, $id);
    $title = q($row->title);
    $group_sub = $row->group_submissions;
    $auto_judge = $row->auto_judge;
    $nav[] = $works_url;
    $nav[] = array('url' => "$_SERVER[SCRIPT_NAME]?id=$id", 'name' => $title);

    if ($submit_ok) {
        if ($group_sub) {
            $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : -1;
            $gids = user_group_info($on_behalf_of ? null : $user_id, $course_id);
            $local_name = isset($gids[$group_id]) ? greek_to_latin($gids[$group_id]) : '';
        } else {
            $group_id = 0;
            $local_name = uid_to_name($user_id);
            $am = Database::get()->querySingle("SELECT am FROM user WHERE id = ?d", $user_id)->am;
            if (!empty($am)) {
                $local_name .= $am;
            }
            $local_name = greek_to_latin($local_name);
        }
        $local_name = replace_dangerous_char($local_name);
        if (isset($on_behalf_of) and
                (!isset($_FILES) or !$_FILES['userfile']['size'])) {
            $_FILES['userfile']['name'] = '';
            $_FILES['userfile']['tmp_name'] = '';
            $no_files = true;
        } else {
            $no_files = false;
        }

        validateUploadedFile($_FILES['userfile']['name'], 2);

        if (preg_match('/\.(ade|adp|bas|bat|chm|cmd|com|cpl|crt|exe|hlp|hta|' . 'inf|ins|isp|jse|lnk|mdb|mde|msc|msi|msp|mst|pcd|pif|reg|scr|sct|shs|' . 'shb|url|vbe|vbs|wsc|wsf|wsh)$/', $_FILES['userfile']['name'])) {
            $tool_content .= "<p class=\"caution\">$langUnwantedFiletype: {$_FILES['userfile']['name']}<br />";
            $tool_content .= "<a href=\"$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id\">$langBack</a></p><br />";
            return;
        }
        $secret = work_secret($id);
        $ext = get_file_extension($_FILES['userfile']['name']);
        $filename = "$secret/$local_name" . (empty($ext) ? '' : '.' . $ext);
        
        if (!isset($on_behalf_of)) {
            $msg1 = delete_submissions_by_uid($user_id, -1, $id);
            if ($group_sub) {
                if (array_key_exists($group_id, $gids)) {
                    $msg1 = delete_submissions_by_uid(-1, $group_id, $id);
                }
            }
        } else {
            $msg1 = '';
        }
        if ($no_files or move_uploaded_file($_FILES['userfile']['tmp_name'], "$workPath/$filename")) {
            if ($no_files) {
                $filename = '';
            } else {
                @chmod("$workPath/$filename", 0644);
            }
            $msg2 = $langUploadSuccess;
            $submit_ip = $_SERVER['REMOTE_ADDR'];
            if (isset($on_behalf_of)) {
                if ($group_sub) {
                    $auto_comments = sprintf($langOnBehalfOfGroupComment, uid_to_name($uid), $gids[$group_id]);
                } else {
                    $auto_comments = sprintf($langOnBehalfOfUserComment, uid_to_name($uid), uid_to_name($user_id));
                }
                $stud_comments = $auto_comments;
                $grade_comments = $_POST['stud_comments'];
                
                $grade_valid = filter_input(INPUT_POST, 'grade', FILTER_VALIDATE_FLOAT);
                (isset($_POST['grade']) && $grade_valid!== false) ? $grade = $grade_valid : $grade = NULL;
             
                $grade_ip = $submit_ip;
            } else {
                $stud_comments = $_POST['stud_comments'];
                $grade = NULL;
                $grade_comments = $grade_ip = "";            
            }
            if (!$group_sub or array_key_exists($group_id, $gids)) {
                $file_name = $_FILES['userfile']['name'];
                $sid = Database::get()->query("INSERT INTO assignment_submit
                                        (uid, assignment_id, submission_date, submission_ip, file_path,
                                         file_name, comments, grade, grade_comments, grade_submission_ip,
                                         grade_submission_date, group_id)
                                         VALUES (?d, ?d, NOW(), ?s, ?s, ?s, ?s, ?f, ?s, ?s, NOW(), ?d)", $user_id, $id, $submit_ip, $filename, $file_name, $stud_comments, $grade, $grade_comments, $grade_ip, $group_id)->lastInsertID;
                Log::record($course_id, MODULE_ID_ASSIGN, LOG_INSERT, array('id' => $sid,
                    'title' => $title,
                    'assignment_id' => $id,
                    'filepath' => $filename,
                    'filename' => $file_name,
                    'comments' => $stud_comments,
                    'group_id' => $group_id));
                if ($on_behalf_of and isset($_POST['email'])) {
                    $email_grade = $_POST['grade'];
                    $email_comments = "\n$auto_comments\n\n" . $_POST['stud_comments'];
                    grade_email_notify($id, $sid, $email_grade, $email_comments);
                }
            }
            $tool_content .= "<p class='success'>$msg2<br />$msg1<br /><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id'>$langBack</a></p><br />";
        } else {
            $tool_content .= "<p class='caution'>$langUploadError<br /><a href='$_SERVER[SCRIPT_NAME]?course=$course_code'>$langBack</a></p><br />";
        }
if($auto_judge){   // Auto-judge: Send file to hackearth
global $hackerEarthKey;
$content = file_get_contents("$workPath/$filename");
//set POST variables
$url = 'http://api.hackerearth.com/code/run/';
$fields = array('client_secret' => $hackerEarthKey, 'source' => $content, 'lang' => 'PYTHON');
//url-ify the data for the POST
foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
rtrim($fields_string, '&');
//open connection
$ch = curl_init();
//set the url, number of POST vars, POST data
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POST, count($fields));
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
//execute post
$result = curl_exec($ch);
$result = json_decode($result, true);
$result['run_status']['output'] = trim($result['run_status']['output']);
// Add the output as a comment
submit_grade_comments($id, $sid, 10, 'Output: '.$result['run_status']['output'], false);
}// End Auto-judge 

} 

else { // not submit_ok
        $tool_content .="<p class='caution'>$langExerciseNotPermit<br /><a href='$_SERVER[SCRIPT_NAME]?course=$course_code'>$langBack</a></p></br>";
    }
}

//  assignment - prof view only
function new_assignment() {
    global $tool_content, $m, $langAdd, $course_code, $course_id;
    global $desc;
    global $langBack, $langStudents, $langMove, $langWorkFile;
  
    $tool_content .= "<div id='operations_container'>
                        <ul id='opslist'>
                      <li><a href='$_SERVER[PHP_SELF]?course=$course_code'>$langBack</a></li>
                        </ul></div>";
    
    $tool_content .= "
        <form enctype='multipart/form-data' action='$_SERVER[SCRIPT_NAME]?course=$course_code' method='post'>
        <fieldset>
        <legend>$m[WorkInfo]</legend>
        <table class='tbl' width='100%'>    
        <tr>
          <th>$m[title]:</th>
          <td><input type='text' name='title' size='55' /></td>
        </tr>
        <tr>
          <th>$m[description]:</th>
          <td>" . rich_text_editor('desc', 4, 20, $desc) . " </td>
        </tr>
        <tr>
            <th class='left' width='150'>$langWorkFile:</th>
            <td><input type='file' name='userfile' /></td>
        </tr>
        <tr>
          <th>$m[max_grade]:</th>
          <td><input type='text' name='max_grade' size='5' value='". ((isset($_POST['max_grade'])) ? $_POST['max_grade'] : "10") ."'/></td>
        </tr>        
        <tr><th>$m[deadline]:</th><td><input type='radio' name='is_deadline' value='0'". ((isset($_POST['WorkEnd'])) ? "" : "checked") ." onclick='$(\"#deadline_row, #late_sub_row\").hide();$(\"#deadline\").val(\"\");' /><label for='user_button'>Χωρίς προθεσμία</label>
        <br /><input type='radio' name='is_deadline' value='1'". ((isset($_POST['WorkEnd'])) ? "checked" : "") ." onclick='$(\"#deadline_row, #late_sub_row\").show()' /><label for='user_button'>Με προθεσμία Υποβολής</label>       
        <td></tr>
        <tr id='deadline_row' ". ((isset($_POST['WorkEnd'])) ? "" : "style=\"display:none\"") .">
          <th></th>
          <td><input id='deadline' type='text' name='WorkEnd' value='".(isset($_POST['WorkEnd']) ? $_POST['WorkEnd'] : "")."' />&nbsp $m[deadline_notif]</td>
        </tr>
        <tr id='late_sub_row'". ((isset($_POST['WorkEnd'])) ? "" : "style=\"display:none\"") .">
               <th></th>
               <td><input type='checkbox' name='late_submission' value='1'>$m[late_submission_enable]</td>
         </tr> 
         <tr>
          <th>Auto-judge:</th>
          <td><input type='checkbox' id='auto_judge' name='auto_judge' value='1' checked='1' /></td>
</tr>         
        <tr>
          <th>$m[group_or_user]:</th>
          <td><input type='radio' id='user_button' name='group_submissions' value='0' checked='1' /><label for='user_button'>$m[user_work]</label>
          <br /><input type='radio' id='group_button' name='group_submissions' value='1' /><label for='group_button'>$m[group_work]</label></td>
        </tr>
        <tr>
          <th>$m[WorkAssignTo]:</th>
          <td><input type='radio' id='assign_button_all' name='assign_to_specific' value='0' checked='1' /><label for='assign_button_all'>Όλους</label>
          <br /><input type='radio' id='assign_button_some' name='assign_to_specific' value='1' /><label for='assign_button_some'>$m[WorkToUser]</label></td>
        </tr>        
        <tr id='assignees_tbl' style='display:none;'>
          <th class='left' valign='top'></th>
          <td>
              <table width='99%' align='center' class='tbl_white'>
              <tr class='title1'>
                <td id='assignees'>$langStudents</td>
                <td width='100' class='center'>$langMove</td>
                <td class='center'>$m[WorkAssignTo]</td>
              </tr>
              <tr>
                <td>
                  <select id='assign_box' size='15' style='width:180px' multiple>

                  </select>
                </td>
                <td class='center'>
                  <input type='button' onClick=\"move('assign_box','assignee_box')\" value='   &gt;&gt;   ' /><br /><input type='button' onClick=\"move('assignee_box','assign_box')\" value='   &lt;&lt;   ' />
                </td>
                <td class='right'>
                  <select id='assignee_box' name='ingroup[]' size='15' style='width:180px' multiple>

                  </select>
                </td>
              </tr>
              </table>
          </td>
        </tr>        
        <tr>
          <th>&nbsp;</th>
          <td class='right'><input type='submit' name='new_assign' value='$langAdd' onclick=\"selectAll('assignee_box',true)\" /></td>
        </tr>
        </table>
        </fieldset>
        </form>";    
}

//form for editing
function show_edit_assignment($id) {
    
    global $tool_content, $m, $langEdit, $langBack, $course_code,
    $urlAppend, $works_url, $course_id, 
    $langStudents, $langMove, $langWorkFile, $themeimg;

    $row = Database::get()->querySingle("SELECT * FROM assignment WHERE id = ?d", $id);
    if ($row->assign_to_specific) {
        //preparing options in select boxes for assigning to speficic users/groups
        $assignee_options='';
        $unassigned_options='';
        if ($row->group_submissions) {
            $assignees = Database::get()->queryArray("SELECT `group`.id AS id, `group`.name
                                   FROM assignment_to_specific, `group` 
                                   WHERE `group`.id = assignment_to_specific.group_id AND assignment_to_specific.assignment_id = ?d", $id);
            $all_groups = Database::get()->queryArray("SELECT name,id FROM `group` WHERE course_id = ?d", $course_id);
            foreach ($assignees as $assignee_row) {
                $assignee_options .= "<option value='".$assignee_row->id."'>".$assignee_row->name."</option>";
            }
            $unassigned = array_udiff($all_groups, $assignees,
              function ($obj_a, $obj_b) {
                return $obj_a->id - $obj_b->id;
              }
            );
            foreach ($unassigned as $unassigned_row) {
                $unassigned_options .= "<option value='$unassigned_row->id'>$unassigned_row->name</option>";
            }           
        } else {
            $assignees = Database::get()->queryArray("SELECT user.id AS id, surname, givenname
                                   FROM assignment_to_specific, user 
                                   WHERE user.id = assignment_to_specific.user_id AND assignment_to_specific.assignment_id = ?d", $id);
            $all_users = Database::get()->queryArray("SELECT user.id AS id, user.givenname, user.surname
                                    FROM user, course_user
                                    WHERE user.id = course_user.user_id 
                                    AND course_user.course_id = ?d AND course_user.status = 5 
                                    AND user.id", $course_id);
            foreach ($assignees as $assignee_row) {
                $assignee_options .= "<option value='$assignee_row->id'>$assignee_row->surname $assignee_row->givenname</option>";
            }         
            $unassigned = array_udiff($all_users, $assignees,
              function ($obj_a, $obj_b) {
                return $obj_a->id - $obj_b->id;
              }
            );
            foreach ($unassigned as $unassigned_row) {
                $unassigned_options .= "<option value='$unassigned_row->id'>$unassigned_row->surname $unassigned_row->givenname</option>";
            }
        }      
    }
    if ((int)$row->deadline) {
        $deadline = date('d-m-Y H:i',strtotime($row->deadline));
    } else {
        $deadline = '';
    }
    $tool_content .= "<div id='operations_container'>
                        <ul id='opslist'>
                      <li><a href='$_SERVER[PHP_SELF]?course=$course_code'>$langBack</a></li>
                        </ul></div>";
    
    $textarea = rich_text_editor('desc', 4, 20, $row->description);    
    $tool_content .= "
    <form enctype='multipart/form-data' action='$_SERVER[SCRIPT_NAME]?course=$course_code' method='post'>
    <input type='hidden' name='id' value='$id' />
    <input type='hidden' name='choice' value='do_edit' />
    <fieldset>
    <legend>$m[WorkInfo]</legend>
    <table class='tbl'>
    <tr>
      <th>$m[title]:</th>
      <td><input type='text' name='title' size='45' value='".q($row->title)."' /></td>
    </tr>
    <tr>
      <th valign='top'>$m[description]:</th>
      <td>$textarea</td>
    </tr>";
    $comments = trim($row->comments);
    if (!empty($comments)) {
        $tool_content .= "
                <tr>
                <th>$m[comments]:</th>
                <td>" . rich_text_editor('comments', 5, 65, $comments) . "</td>
                </tr>";
    }
    $tool_content .= "
        <tr>
            <th class='left' width='150'>$langWorkFile:</th>
            <td>".(($row->file_name)? "<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;get=$row->id&amp;file_type=1'>".q($row->file_name)."</a>"
            . "<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id&amp;choice=do_delete_file' onClick='return confirmation(\"$m[WorkDeleteAssignmentFileConfirm]\");'>
                                 <img src='$themeimg/delete.png' title='$m[WorkDeleteAssignmentFile]' /></a>" : "<input type='file' name='userfile' />")."</td>
        </tr>";
    $tool_content .= "
    <tr>
        <th>$m[max_grade]:</th>
        <td><input type='text' name='max_grade' size='5' value='$row->max_grade'/></td>
    </tr>           
    <tr>
        <th>$m[deadline]:</th><td><input type='radio' name='is_deadline' value='0'". ((!empty($deadline)) ? "" : "checked") ." onclick='$(\"#deadline_row, #late_sub_row\").hide();$(\"#deadline\").val(\"\");' /><label for='user_button'>Χωρίς προθεσμία</label>
        <br /><input type='radio' name='is_deadline' value='1'". ((!empty($deadline)) ? "checked" : "") ." onclick='$(\"#deadline_row, #late_sub_row\").show()' /><label for='user_button'>Με προθεσμία Υποβολής</label>       
        <td>
    </tr>
    <tr id='deadline_row'". (!empty($deadline) ? "" : "style=\"display:none\"") .">
          <th></th>
          <td><input id='deadline' type='text' name='WorkEnd' value='{$deadline}' />&nbsp $m[deadline_notif]</td>
    </tr>  
    <tr id='late_sub_row'". (!empty($deadline) ? "" : "style=\"display:none\"") .">
          <th></th>
          <td><input type='checkbox' name='late_submission' value='1' ".(($row->late_submission)? 'checked' : '').">$m[late_submission_enable]</td>
    </tr>     
    <tr>
      <th valign='top'>$m[group_or_user]:</th>
      <td><input type='radio' id='user_button' name='group_submissions' value='0'".(($row->group_submissions==1) ? '' : 'checked')." />
          <label for='user_button'>$m[user_work]</label><br />
          <input type='radio' id='group_button' name='group_submissions' value='1'".(($row->group_submissions==1) ? 'checked' : '')." />
          <label for='group_button'>$m[group_work]</label></td>
    </tr>
        <tr>
          <th>$m[WorkAssignTo]:</th>
          <td><input type='radio' id='assign_button_all' name='assign_to_specific' value='0'".(($row->assign_to_specific==1) ? '' : 'checked')."  /><label for='assign_button_all'>Όλους</label>
          <br /><input type='radio' id='assign_button_some' name='assign_to_specific' value='1'".(($row->assign_to_specific==1) ? 'checked' : '')." /><label for='assign_button_some'>".(($row->group_submissions) ? $m['WorkToGroup'] : $m['WorkToUser'])."</label></td>
        </tr>        
        <tr id='assignees_tbl'".(($row->assign_to_specific==1) ? '' : 'style="display:none;"').">
          <th class='left' valign='top'></th>
          <td>
              <table width='99%' align='center' class='tbl_white'>
              <tr class='title1'>
                <td id='assignees'>$langStudents</td>
                <td width='100' class='center'>$langMove</td>
                <td class='center'>$m[WorkAssignTo]</td>
              </tr>
              <tr>
                <td>
                  <select id='assign_box' size='15' style='width:180px' multiple>
                    ".((isset($unassigned_options)) ? $unassigned_options : '')."
                  </select>
                </td>
                <td class='center'>
                  <input type='button' onClick=\"move('assign_box','assignee_box')\" value='   &gt;&gt;   ' /><br /><input type='button' onClick=\"move('assignee_box','assign_box')\" value='   &lt;&lt;   ' />
                </td>
                <td class='right'>
                  <select id='assignee_box' name='ingroup[]' size='15' style='width:180px' multiple>
                        ".((isset($assignee_options)) ? $assignee_options : '')."
                  </select>
                </td>
              </tr>
              </table>
          </td>
        </tr>            
    <tr>
      <th>&nbsp;</th>
      <td><input type='submit' name='do_edit' value='$langEdit' onclick=\"selectAll('assignee_box',true)\" /></td>
    </tr>
    </table>
    </fieldset>
    </form>";
}

// edit assignment
function edit_assignment($id) {

    global $tool_content, $langBackAssignment, $langEditSuccess,
    $langEditError, $course_code, $works_url, $course_id, $uid, $workPath;

    $row = Database::get()->querySingle("SELECT * FROM assignment WHERE id = ?d", $id);
    $title = $_POST['title'];
    $desc = purify($_POST['desc']);
    $deadline = trim($_POST['WorkEnd']) == FALSE ? '0000-00-00 00:00': date('Y-m-d H:i', strtotime($_POST['WorkEnd']));
    $late_submission = ((isset($_POST['late_submission']) && trim($_POST['WorkEnd']) != FALSE) ? 1 : 0);
    $group_submissions = $_POST['group_submissions'];
    $max_grade = filter_input(INPUT_POST, 'max_grade', FILTER_VALIDATE_FLOAT);
    $assign_to_specific = filter_input(INPUT_POST, 'assign_to_specific', FILTER_VALIDATE_INT);
    $assigned_to = filter_input(INPUT_POST, 'ingroup', FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY);
    
    if ($assign_to_specific == 1 && empty($assigned_to)) {
        $assign_to_specific = 0;
    }

    if (!isset($_POST['comments'])) {
        $comments = '';
    } else {
        $comments = purify($_POST['comments']);
    }
    
    if (!isset($_FILES) || !$_FILES['userfile']['size']) {
        $_FILES['userfile']['name'] = '';
        $_FILES['userfile']['tmp_name'] = '';
        $filename = $row->file_path;
        $file_name = $row->file_name;
    } else {
        validateUploadedFile($_FILES['userfile']['name'], 2);
        if (preg_match('/\.(ade|adp|bas|bat|chm|cmd|com|cpl|crt|exe|hlp|hta|' .
                           'inf|ins|isp|jse|lnk|mdb|mde|msc|msi|msp|mst|pcd|pif|reg|scr|sct|shs|' .
                           'shb|url|vbe|vbs|wsc|wsf|wsh)$/', $_FILES['userfile']['name'])) {
            $tool_content .= "<p class=\"caution\">$langUnwantedFiletype: {$_FILES['userfile']['name']}<br />";
            $tool_content .= "<a href=\"$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id\">$langBack</a></p><br />";
            return;
        }
        $local_name = uid_to_name($uid);
        $am = Database::get()->querySingle("SELECT am FROM user WHERE id = ?d", $uid)->am;
        if (!empty($am)) {
            $local_name .= $am;
        }                
        $local_name = greek_to_latin($local_name);
        $local_name = replace_dangerous_char($local_name);
        $secret = $row->secret_directory;
        $ext = get_file_extension($_FILES['userfile']['name']);
        $filename = "$secret/$local_name" . (empty($ext) ? '' : '.' . $ext);                
        if (move_uploaded_file($_FILES['userfile']['tmp_name'], "$workPath/admin_files/$filename")) {
            @chmod("$workPath/admin_files/$filename", 0644);
            $file_name = $_FILES['userfile']['name'];
        }        
    }   
    Database::get()->query("UPDATE assignment SET title = ?s, description = ?s, 
        group_submissions = ?d, comments = ?s, deadline = ?t, late_submission = ?d, max_grade = ?d, 
        assign_to_specific = ?d, file_path = ?s, file_name = ?s
        WHERE course_id = ?d AND id = ?d", $title, $desc, $group_submissions, 
        $comments, $deadline, $late_submission, $max_grade, $assign_to_specific, $filename, $file_name, $course_id, $id);

    Database::get()->query("DELETE FROM assignment_to_specific WHERE assignment_id = ?d", $id);

    if ($assign_to_specific && !empty($assigned_to)) {
        if ($group_submissions == 1) {
            $column = 'group_id';
            $other_column = 'user_id';
        } else {
            $column = 'user_id';
            $other_column = 'group_id';
        }
        foreach ($assigned_to as $assignee_id) {
            Database::get()->query("INSERT INTO assignment_to_specific ({$column}, {$other_column}, assignment_id) VALUES (?d, ?d, ?d)", $assignee_id, 0, $id);
        }
    }    
    Log::record($course_id, MODULE_ID_ASSIGN, LOG_MODIFY, array('id' => $id,
            'title' => $title,
            'description' => $desc,
            'deadline' => $deadline,
            'group' => $group_submissions));
    return true;
}

/**
 * @brief delete assignment
 * @global type $tool_content
 * @global string $workPath
 * @global type $course_code
 * @global type $webDir
 * @global type $langBack
 * @global type $langDeleted
 * @global type $course_id
 * @param type $id
 */
function delete_assignment($id) {

    global $tool_content, $workPath, $course_code, $webDir, $langBack, $langDeleted, $course_id;

    $secret = work_secret($id);
    $row = Database::get()->querySingle("SELECT title,assign_to_specific FROM assignment WHERE course_id = ?d
                                        AND id = ?d", $course_id, $id);
    if (count($row) > 0) {
        if (Database::get()->query("DELETE FROM assignment WHERE course_id = ?d AND id = ?d", $course_id, $id)->affectedRows > 0){
            Database::get()->query("DELETE FROM assignment_submit WHERE assignment_id = ?d", $id);
            if ($row->assign_to_specific) {
                Database::get()->query("DELETE FROM assignment_to_specific WHERE assignment_id = ?d", $id);
            }
            move_dir("$workPath/$secret", "$webDir/courses/garbage/${course_code}_work_${id}_$secret");

            Log::record($course_id, MODULE_ID_ASSIGN, LOG_DELETE, array('id' => $id,
                'title' => $row->title));
            return true;
        }
        return false;
    }
    return false;
}
/**
 * @brief delete assignment's submissions
 * @global type $tool_content
 * @global string $workPath
 * @global type $course_code
 * @global type $webDir
 * @global type $langBack
 * @global type $langDeleted
 * @global type $course_id
 * @param type $id
 */
function purge_assignment_subs($id) {

	global $tool_content, $workPath, $webDir, $langBack, $langDeleted, $langAssignmentSubsDeleted, $course_code, $course_id;
        
	$secret = work_secret($id);
        $row = Database::get()->querySingle("SELECT title,assign_to_specific FROM assignment WHERE course_id = ?d
                                        AND id = ?d", $course_id, $id);        
        if (Database::get()->query("DELETE FROM assignment_submit WHERE assignment_id = ?d", $id)->affectedRows > 0) {
            if ($row->assign_to_specific) {
                Database::get()->query("DELETE FROM assignment_to_specific WHERE assignment_id = ?d", $id);
            }
            move_dir("$workPath/$secret",
            "$webDir/courses/garbage/${course_code}_work_${id}_$secret");
            return true;
        }
        return false;
}
/**
 * @brief delete user assignment
 * @global string $tool_content
 * @global type $course_id
 * @global type $course_code
 * @global type $webDir
 * @param type $id
 */
function delete_user_assignment($id) {
    global $tool_content, $course_code, $webDir;

    $filename = Database::get()->querySingle("SELECT file_path FROM assignment_submit WHERE id = ?d", $id);
    $file = $webDir . "/courses/" . $course_code . "/work/" . $filename->file_path;
    if (Database::get()->query("DELETE FROM assignment_submit WHERE id = ?d", $id)->affectedRows > 0) {
        if (my_delete($file)) {
            return true;
        }
        return false;
    }
}
/**
 * @brief delete teacher assignment file
 * @global string $tool_content
 * @global type $course_id
 * @global type $course_code
 * @global type $webDir
 * @param type $id
 */
function delete_teacher_assignment_file($id) {
    global $tool_content, $course_code, $webDir;

    $filename = Database::get()->querySingle("SELECT file_path FROM assignment WHERE id = ?d", $id);
    $file = $webDir . "/courses/" . $course_code . "/work/admin_files/" . $filename->file_path;
    if (Database::get()->query("UPDATE assignment SET file_path='', file_name='' WHERE id = ?d", $id)->affectedRows > 0) {
        if (my_delete($file)) {
            return true;
        }
        return false;
    }
}
/**
 * @brief display user assignment
 * @global type $tool_content
 * @global type $m
 * @global type $uid
 * @global type $langUserOnly
 * @global type $langBack
 * @global type $course_code
 * @global type $course_id
 * @global type $course_code
 * @param type $id
 */
function show_student_assignment($id) {
    global $tool_content, $m, $uid, $langUserOnly, $langBack,
    $course_code, $course_id, $course_code;

    $user_group_info = user_group_info($uid, $course_id);
    $row = Database::get()->querySingle("SELECT *, CAST(UNIX_TIMESTAMP(deadline)-UNIX_TIMESTAMP(NOW()) AS SIGNED) AS time
                                         FROM assignment WHERE course_id = ?d AND id = ?d", $course_id, $id);

    assignment_details($id, $row);

    $submit_ok = ($row->time > 0 || !(int) $row->deadline || $row->time <= 0 && $row->late_submission);

    if (!$uid) {
        $tool_content .= "<p>$langUserOnly</p>";
        $submit_ok = FALSE;
    } elseif ($GLOBALS['status'] == 10) {
        $tool_content .= "\n  <p class='alert1'>$m[noguest]</p>";
        $submit_ok = FALSE;;
    } else {
        foreach (find_submissions($row->group_submissions, $uid, $id, $user_group_info) as $sub) {
            if ($sub->grade != '') {
                $submit_ok = false;
            
            }
            show_submission_details($sub->id);
        }
    }
    if ($submit_ok) {
        show_submission_form($id, $user_group_info);
    }
    $tool_content .= "<br/><p align='right'><a href='$_SERVER[SCRIPT_NAME]?course=$course_code'>$langBack</a></p>";
}

function show_submission_form($id, $user_group_info, $on_behalf_of = false) {
    global $tool_content, $m, $langWorkFile, $langSendFile, $langSubmit, $uid, $langNotice3, $gid, $is_member,
    $urlAppend, $langGroupSpaceLink, $langOnBehalfOf, $course_code;

    $group_select_hidden_input = $group_select_form = '';
    $is_group_assignment = is_group_assignment($id);
    if ($is_group_assignment) {
        if (!$on_behalf_of) {
            if (count($user_group_info) == 1) {
                $gids = array_keys($user_group_info);
                $group_link = $urlAppend . '/modules/group/document.php?gid=' . $gids[0];
                $group_select_hidden_input = "<input type='hidden' name='group_id' value='$gids[0]' />";
            } elseif ($user_group_info) {
                $group_select_form = "<tr><th class='left'>$langGroupSpaceLink:</th><td>" .
                        selection($user_group_info, 'group_id') . "</td></tr>";
            } else {
                $group_link = $urlAppend . 'modules/group/';
                $tool_content .= "<p class='alert1'>$m[this_is_group_assignment] <br />" .
                        sprintf(count($user_group_info) ?
                                        $m['group_assignment_publish'] :
                                        $m['group_assignment_no_groups'], $group_link) .
                        "</p>\n";
            }
        } else {
            $groups_with_no_submissions = groups_with_no_submissions($id);
            if (count($groups_with_no_submissions)>0) {
                $group_select_form = "<tr><th class='left'>$langGroupSpaceLink:</th><td>" .
                        selection($groups_with_no_submissions, 'group_id') . "</td></tr>";
            }else{
                Session::Messages($m['NoneWorkGroupNoSubmission'], 'caution');
                redirect_to_home_page('modules/work/index.php?course='.$course_code.'&id='.$id);                
            }
        }
    } elseif ($on_behalf_of) {
            $users_with_no_submissions = users_with_no_submissions($id);
            if (count($users_with_no_submissions)>0) {
                $group_select_form = "<tr><th class='left'>$langOnBehalfOf:</th><td>" .
                        selection($users_with_no_submissions, 'user_id') . "</td></tr>";
            } else {
                Session::Messages($m['NoneWorkUserNoSubmission'], 'caution');
                redirect_to_home_page('modules/work/index.php?course='.$course_code.'&id='.$id);
            }
    }
    $notice = $on_behalf_of ? '' : "<br />$langNotice3";
    $extra = $on_behalf_of ? "<tr><th class='left'>$m[grade]</th>
                                     <td><input type='text' name='grade' maxlength='3' size='3'> ($m[max_grade]:)
                                         <input type='hidden' name='on_behalf_of' value='1'></td></tr>
                                 <tr><th><label for='email_button'>$m[email_users]:</label></th>
                                     <td><input type='checkbox' value='1' id='email_button' name='email'></td></tr>" : '';
    if (!$is_group_assignment or count($user_group_info) or $on_behalf_of) {
        $tool_content .= "
                     <form enctype='multipart/form-data' action='$_SERVER[SCRIPT_NAME]?course=$course_code' method='post'>
                        <input type='hidden' name='id' value='$id' />$group_select_hidden_input
                        <fieldset>
                        <legend>$langSubmit</legend>
                        <table width='100%' class='tbl'>
                        $group_select_form
                        <tr>
                          <th class='left' width='150'>$langWorkFile:</th>
                          <td><input type='file' name='userfile' /></td>
                        </tr>
                        <tr>
                          <th class='left'>$m[comments]:</th>
                          <td><textarea name='stud_comments' rows='5' cols='55'></textarea></td>
                        </tr>
                        $extra
                        <tr>
                          <th>&nbsp;</th>
                          <td align='right'><input type='submit' value='$langSubmit' name='work_submit' />$notice</td>
                        </tr>
                        </table>
                        </fieldset>
                     </form>
                     <p align='right'><small>$GLOBALS[langMaxFileSize] " .
                ini_get('upload_max_filesize') . "</small></p>";
    }
}

// Print a box with the details of an assignment
function assignment_details($id, $row) {
    global $tool_content, $is_editor, $course_code, $themeimg, $m, $langDaysLeft,
    $langDays, $langWEndDeadline, $langNEndDeadLine, $langNEndDeadline,
    $langEndDeadline, $langDelAssign, $langAddGrade, $langZipDownload,
    $langSaved, $langGraphResults, $langConfirmDelete, $langWorkFile;

    if ($is_editor) {
        $tool_content .= "
            <div id='operations_container'>
              <ul id='opslist'>
              <li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id&amp;choice=do_delete' onClick='return confirmation(\"" . $langConfirmDelete . "\");'>$langDelAssign</a></li>
                <li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;download=$id'>$langZipDownload</a></li>
		<li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id&amp;disp_results=true'>$langGraphResults</a></li><br>
                    <li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id&amp;disp_non_submitted=true'>$m[WorkUserGroupNoSubmission]</a></li>
		<li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id&amp;choice=add'>$langAddGrade</a></li>
              </ul>
            </div>";
    }

    $tool_content .= "
        <fieldset>
        <legend>" . $m['WorkInfo'];
    if ($is_editor) {
        $tool_content .= "&nbsp;" . icon('edit', $m['edit'],
                 "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id&amp;choice=edit");
    }
    $tool_content .= "</legend>
        <table class='tbl'>
        <tr>
          <th width='150'>$m[title]:</th>
          <td>".q($row->title)."</td>
        </tr>";
    if (!empty($row->description)) {
        $tool_content .= "
                <tr>
                  <th class='left'>$m[description]:</th>
                  <td>".purify($row->description)."</td>
                </tr>";
    }    
    if (!empty($row->comments)) {
        $tool_content .= "
                <tr>
                  <th class='left'>$m[comments]:</th>
                  <td>".purify($row->comments)."</td>
                </tr>";
    }
    if (!empty($row->file_name)) {
        $tool_content .= "
                <tr>
                  <th class='left'>$langWorkFile:</th>
                  <td><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;get=$row->id&amp;file_type=1'>".q($row->file_name)."</a></td>
                </tr>";
    }   
    if((int)$row->deadline){
        $deadline = nice_format($row->deadline, true);
    }else{
        $deadline = $m['no_deadline'];
    }
    $tool_content .= "
        <tr>
            <th class='left'>$m[max_grade]:</th>
            <td>$row->max_grade</td>
        </tr>        
        <tr>
          <th>$m[start_date]:</th>
          <td>" . nice_format($row->submission_date, true) . "</td>
        </tr>
        <tr>
          <th valign='top'>$m[deadline]:</th>
          <td>" . $deadline . " <br />";

    if ($row->time > 0) {
        $tool_content .= "<span>($langDaysLeft " . format_time_duration($row->time) . ")</span></td>
                </tr>";
    } else if((int)$row->deadline){
        $tool_content .= "<span class='expired'>$langEndDeadline</span></td>
                </tr>";
    }
    $tool_content .= "
        <tr>
          <th>$m[group_or_user]:</th>
          <td>";
    if ($row->group_submissions == '0') {
        $tool_content .= "$m[user_work]</td>
        </tr>";
    } else {
        $tool_content .= "$m[group_work]</td>
        </tr>";
    }
    $tool_content .= "
        </table>
        </fieldset>";
}

// Show a table header which is a link with the appropriate sorting
// parameters - $attrib should contain any extra attributes requered in
// the <th> tags
function sort_link($title, $opt, $attrib = '') {
    global $tool_content, $course_code;
    $i = '';
    if (isset($_REQUEST['id'])) {
        $i = "&id=$_REQUEST[id]";
    }
    if (@($_REQUEST['sort'] == $opt)) {
        if (@($_REQUEST['rev'] == 1)) {
            $r = 0;
        } else {
            $r = 1;
        }
        $tool_content .= "
                  <th $attrib><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;sort=$opt&rev=$r$i'>" . "$title</a></th>";
    } else {
        $tool_content .= "
                  <th $attrib><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;sort=$opt$i'>$title</a></th>";
    }
}

// show assignment - prof view only
// the optional message appears instead of assignment details
function show_assignment($id, $display_graph_results = false) {
    global $tool_content, $m, $langBack, $langNoSubmissions, $langSubmissions,
    $langEndDeadline, $langWEndDeadline, $langNEndDeadline,
    $langDays, $langDaysLeft, $langGradeOk, $course_code, $webDir, $urlServer,
    $langGraphResults, $m, $course_code, $themeimg, $works_url, $course_id, $langDelWarnUserAssignment;

    $row = Database::get()->querySingle("SELECT *, CAST(UNIX_TIMESTAMP(deadline)-UNIX_TIMESTAMP(NOW()) AS SIGNED) AS time
                                FROM assignment
                                WHERE course_id = ?d AND id = ?d", $course_id, $id);

    $nav[] = $works_url;
    assignment_details($id, $row);
    
    $rev = (@($_REQUEST['rev'] == 1)) ? ' DESC' : '';
    if (isset($_REQUEST['sort'])) {
        if ($_REQUEST['sort'] == 'am') {
            $order = 'am';
        } elseif ($_REQUEST['sort'] == 'date') {
            $order = 'submission_date';
        } elseif ($_REQUEST['sort'] == 'grade') {
            $order = 'grade';
        } elseif ($_REQUEST['sort'] == 'filename') {
            $order = 'file_name';
        } else {
            $order = 'surname';
        }
    } else {
        $order = 'surname';
    }

    $result = Database::get()->queryArray("SELECT * FROM assignment_submit AS assign, user
                                 WHERE assign.assignment_id = ?d AND user.id = assign.uid
                                 ORDER BY ?s ?s", $id, $order, $rev);

    $num_results = count($result);
    if ($num_results > 0) {
        if ($num_results == 1) {
            $num_of_submissions = $m['one_submission'];
        } else {
            $num_of_submissions = sprintf("$m[more_submissions]", $num_results);
        }

        $gradeOccurances = array(); // Named array to hold grade occurances/stats
        $gradesExists = 0;
        foreach ($result as $row) {
            $theGrade = $row->grade;
            if ($theGrade) {
                $gradesExists = 1;
                if (!isset($gradeOccurances[$theGrade])) {
                    $gradeOccurances[$theGrade] = 1;
                } else {
                    if ($gradesExists) {
                        ++$gradeOccurances[$theGrade];
                    }
                }
            }
        }
        if (!$display_graph_results) {
            $result = Database::get()->queryArray("SELECT assign.id id, assign.file_name file_name,
                                                   assign.uid uid, assign.group_id group_id, 
                                                   assign.submission_date submission_date,
                                                   assign.grade_submission_date grade_submission_date,
                                                   assign.grade grade, assign.comments comments,
                                                   assign.grade_comments grade_comments,
                                                   assignment.deadline deadline 
                                                   FROM assignment_submit AS assign, user, assignment
                                                   WHERE assign.assignment_id = ?d AND assign.assignment_id = assignment.id AND user.id = assign.uid
                                                   ORDER BY ?s ?s", $id, $order, $rev);

            $tool_content .= "
                        <form action='$_SERVER[SCRIPT_NAME]?course=$course_code' method='post'>
                        <input type='hidden' name='grades_id' value='$id' />
                        <p><div class='sub_title1'>$langSubmissions:</div><p>
                        <p>$num_of_submissions</p>
                        <table width='100%' class='sortable'>
                        <tr>
                      <th width='3'>&nbsp;</th>";
            sort_link($m['username'], 'username');
            sort_link($m['am'], 'am');
            sort_link($m['filename'], 'filename');
            sort_link($m['sub_date'], 'date');
            sort_link($m['grade'], 'grade');
            $tool_content .= "</tr>";

            $i = 1;
            foreach ($result as $row) {
                //is it a group assignment?
                if (!empty($row->group_id)) {
                    $subContentGroup = "$m[groupsubmit] " .
                            "<a href='../group/group_space.php?course=$course_code&amp;group_id=$row->group_id'>" .
                            "$m[ofgroup] " . gid_to_name($row->group_id) . "</a>";
                } else {
                    $subContentGroup = '';
                }
                $uid_2_name = display_user($row->uid);
                $stud_am = Database::get()->querySingle("SELECT am FROM user WHERE id = ?d", $row->uid)->am;
                if ($i % 2 == 1) {
                    $row_color = "class='even'";
                } else {
                    $row_color = "class='odd'";
                }
                $filelink = empty($row->file_name) ? '&nbsp;' :
                        ("<a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;get=$row->id'>" .
                        q($row->file_name) . "</a>");
                
                $late_sub_text = ((int) $row->deadline && $row->submission_date > $row->deadline) ?  '<div style="color:red;">$m[late_submission]</div>' : '';
                $tool_content .= "
                                <tr $row_color>
                                <td align='right' width='4' rowspan='2' valign='top'>$i.</td>
                                <td>${uid_2_name}</td>
                                <td width='85'>" . q($stud_am) . "</td>
                                <td width='180'>$filelink
                                <a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$id&amp;as_id=$row->id' onClick='return confirmation(\"$langDelWarnUserAssignment\");'>
                                 <img src='$themeimg/delete.png' title='$m[WorkDelete]' />
                                </a>                                
                                </td>
                                <td width='100'>" . nice_format($row->submission_date, TRUE) .$late_sub_text. "</td>
                                <td width='5'>
                                <div align='center'><input type='text' value='{$row->grade}' maxlength='3' size='3' name='grades[{$row->id}]'></div>
                                </td>
                                </tr>
                                <tr $row_color>
                                <td colspan='5'>
                                <div>$subContentGroup</div>";
                if (trim($row->comments != '')) {
                    $tool_content .= "<div style='margin-top: .5em;'><b>$m[comments]:</b> " .
                            q($row->comments) . '</div>';
                }
                //professor comments
                $gradelink = "grade_edit.php?course=$course_code&amp;assignment=$id&amp;submission=$row->id";
                if (trim($row->grade_comments)) {
                    $label = $m['gradecomments'] . ':';
                    $icon = 'edit.png';
                    $comments = "<div class='smaller'>" . standard_text_escape($row->grade_comments) . "</div>";
                } else {
                    $label = $m['addgradecomments'];
                    $icon = 'add.png';
                    $comments = '';
                }
                if ($row->grade_comments || $row->grade != '') {
                    $comments .= "<div class='smaller'><i>($m[grade_comment_date]: " .
                            nice_format($row->grade_submission_date) . ")</i></div>";
                }
                $tool_content .= "<div style='padding-top: .5em;'><a href='$gradelink'><b>$label</b></a>
				  <a href='$gradelink'><img src='$themeimg/$icon'></a>
				  $comments
                                </td>
                                </tr>";
                $i++;
            } //END of Foreach

            $tool_content .= "</table>
                        <p class='smaller right'><img src='$themeimg/email.png' alt='' >
                                $m[email_users]: <input type='checkbox' value='1' name='email'></p>
                        <p><input type='submit' name='submit_grades' value='$langGradeOk'></p>
                        </form>";
        } else {
        // display pie chart with grades results
            if ($gradesExists) {
                // Used to display grades distribution chart
                $graded_submissions_count = Database::get()->querySingle("SELECT COUNT(*) AS count FROM assignment_submit AS assign, user
                                                             WHERE assign.assignment_id = ?d AND user.id = assign.uid AND
                                                             assign.grade <> ''", $id)->count;                
                $chart = new Plotter();
                $chart->setTitle("$langGraphResults");
                foreach ($gradeOccurances as $gradeValue => $gradeOccurance) {
                    $percentage = round((100.0 * $gradeOccurance / $graded_submissions_count),2);
                    $chart->growWithPoint("$gradeValue ($percentage%)", $percentage);
                }
                $tool_content .= $chart->plot();
            }
        }
    } else {
        $tool_content .= "
                      <p class='sub_title1'>$langSubmissions:</p>
                      <p class='alert1'>$langNoSubmissions</p>";
    }
    $tool_content .= "<br/>
                <p align='right'><a href='$_SERVER[SCRIPT_NAME]?course=$course_code'>$langBack</a></p>";
}

function show_non_submitted($id) {
    global $tool_content, $works_url, $course_id, $m, $langSubmissions,
            $langGroup, $course_code;    
    $row = Database::get()->querySingle("SELECT *, CAST(UNIX_TIMESTAMP(deadline)-UNIX_TIMESTAMP(NOW()) AS SIGNED) AS time
                                FROM assignment
                                WHERE course_id = ?d AND id = ?d", $course_id, $id);

    $nav[] = $works_url;
    assignment_details($id, $row);
    if ($row->group_submissions) {
        $groups = groups_with_no_submissions($id);
        $num_results = count($groups);
        if ($num_results > 0) {
            if ($num_results == 1) {
                $num_of_submissions = $m['one_submission'];
            } else {
                $num_of_submissions = sprintf("$m[more_submissions]", $num_results);
            }
                $tool_content .= "
                            <p><div class='sub_title1'>$m[WorkGroupNoSubmission]:</div><p>
                            <p>$num_of_submissions</p>
                            <table width='100%' class='sortable'>
                            <tr>
                          <th width='3'>&nbsp;</th>";
                sort_link($langGroup, 'username');
                $tool_content .= "</tr>";
                $i=1;
                foreach ($groups as $row => $value){
                    if ($i % 2 == 1) {
                        $row_color = "class='even'";
                    } else {
                        $row_color = "class='odd'";
                    }
                    $tool_content .= "<tr>
                            <td>$i.</td>
                            <td><a href='../group/group_space.php?course=$course_code&amp;group_id=$row'>$value</a></td>
                            </tr>";
                    $i++;
                }
                $tool_content .= "</table>";
        } else {
            $tool_content .= "
                      <p class='sub_title1'>$m[WorkGroupNoSubmission]:</p>
                      <p class='alert1'>$m[NoneWorkGroupNoSubmission]</p>";
        }
        
    } else {
        $users = users_with_no_submissions($id);
        $num_results = count($users);
        if ($num_results > 0) {
            if ($num_results == 1) {
                $num_of_submissions = $m['one_non_submission'];
            } else {
                $num_of_submissions = sprintf("$m[more_non_submissions]", $num_results);
            }
                $tool_content .= "
                            <p><div class='sub_title1'>$m[WorkUserNoSubmission]:</div><p>
                            <p>$num_of_submissions</p>
                            <table width='100%' class='sortable'>
                            <tr>
                          <th width='3'>&nbsp;</th>";
                sort_link($m['username'], 'username');
                sort_link($m['am'], 'am');
                $tool_content .= "</tr>";
                $i=1;
                foreach ($users as $row => $value){
                    if ($i % 2 == 1) {
                        $row_color = "class='even'";
                    } else {
                        $row_color = "class='odd'";
                    }
                    $tool_content .= "<tr>
                    <td>$i.</td>
                    <td>".display_user($row)."</td>
                    <td>".  uid_to_am($row) ."</td>    
                    </tr>";
                            
                    $i++;
                }
                $tool_content .= "</table>";
        } else {
            $tool_content .= "
                      <p class='sub_title1'>$m[WorkUserNoSubmission]:</p>
                      <p class='alert1'>$m[NoneWorkUserNoSubmission]</p>";
        }              
    } 
}
// show all the assignments - student view only
function show_student_assignments() {
    global $tool_content, $m, $uid, $course_id, $course_code,
    $langDaysLeft, $langDays, $langNoAssign, $urlServer,
    $course_code, $themeimg;

    $gids = user_group_info($uid, $course_id);
    if (!empty($gids)) {
        $gids_sql_ready = implode(',',array_keys($gids));
    } else {
        $gids_sql_ready = "''";
    }

    $result = Database::get()->queryArray("SELECT *, CAST(UNIX_TIMESTAMP(deadline)-UNIX_TIMESTAMP(NOW()) AS SIGNED) AS time
                                 FROM assignment WHERE course_id = ?d AND active = 1 AND 
                                 (assign_to_specific = 0 OR assign_to_specific = 1 AND id IN
                                    (SELECT assignment_id FROM assignment_to_specific WHERE user_id = ?d UNION SELECT assignment_id FROM assignment_to_specific WHERE group_id IN ($gids_sql_ready))
                                 )
                                 ORDER BY CASE WHEN CAST(deadline AS UNSIGNED) = '0' THEN 1 ELSE 0 END, deadline", $course_id, $uid);
    
    if (count($result)>0) {
        $tool_content .= "<table class='tbl_alt' width='100%'>
                                  <tr>
                                      <th colspan='2'>$m[title]</th>
                                      <th class='center'>$m[deadline]</th>
                                      <th class='center'>$m[submitted]</th>
                                      <th>$m[grade]</th>
                                  </tr>";
        $k = 0;
        foreach ($result as $row) {
            $title_temp = q($row->title);
            $class = $k % 2 ? 'odd' : 'even';
            $test = (int)$row->deadline;
            if((int)$row->deadline){
                $deadline = nice_format($row->deadline, true);
            }else{
                $deadline = $m['no_deadline'];
            }
            $tool_content .= "
                                <tr class='$class'>
                                    <td width='16'><img src='$themeimg/arrow.png' title='bullet' /></td>
                                    <td><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$row->id'>$title_temp</a></td>
                                    <td width='150' align='center'>" . $deadline ;
            if ($row->time > 0) {
                $tool_content .= " (<span>$langDaysLeft" . format_time_duration($row->time) . ")</span>";
            } else if((int)$row->deadline){
                $tool_content .= " (<span class='expired'>$m[expired]</span>)";
            }
            $tool_content .= "</td><td width='170' align='center'>";

            if ($submission = find_submissions(is_group_assignment($row->id), $uid, $row->id, $gids)) {
                foreach ($submission as $sub) {
                    if (isset($sub->group_id)) { // if is a group assignment
                        $tool_content .= "<div style='padding-bottom: 5px;padding-top:5px;font-size:9px;'>($m[groupsubmit] " .
                                "<a href='../group/group_space.php?course=$course_code&amp;group_id=$sub->group_id'>" .
                                "$m[ofgroup] " . gid_to_name($sub['group_id']) . "</a>)</div>";
                    }
                    $tool_content .= "<img src='$themeimg/checkbox_on.png' alt='$m[yes]' /><br />";
                }
            } else {
                $tool_content .= "<img src='$themeimg/checkbox_off.png' alt='$m[no]' />";
            }
            $tool_content .= "</td>
                                    <td width='30' align='center'>";
            foreach ($submission as $sub) {
                $grade = submission_grade($sub->id);
                if (!$grade) {
                    $grade = "<div style='padding-bottom: 5px;padding-top:5px;'> - </div>";
                }
                $tool_content .= "<div style='padding-bottom: 5px;padding-top:5px;'>$grade</div>";
            }
            $tool_content .= "</td>
                                  </tr>";
            $k++;
        }
        $tool_content .= '
                                  </table>';
    } else {
        $tool_content .= "<p class='alert1'>$langNoAssign</p>";
    }
}

// show all the assignments
function show_assignments() {
    global $tool_content, $m, $langNoAssign, $langNewAssign, $langCommands,
    $course_code, $themeimg, $course_id, $langConfirmDelete, $langDaysLeft, $m,
    $langWarnForSubmissions, $langDelSure;
    

    $result = Database::get()->queryArray("SELECT *, CAST(UNIX_TIMESTAMP(deadline)-UNIX_TIMESTAMP(NOW()) AS SIGNED) AS time
              FROM assignment WHERE course_id = ?d ORDER BY CASE WHEN CAST(deadline AS UNSIGNED) = '0' THEN 1 ELSE 0 END, deadline", $course_id);
 
    $tool_content .="
            <div id='operations_container'>
              <ul id='opslist'>
                <li><a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;add=1'>$langNewAssign</a></li>
              </ul>
            </div>";

    if (count($result)>0) {
        $tool_content .= "
                    <table width='100%' class='tbl_alt'>
                    <tr>
                      <th>$m[title]</th>
                      <th width='60'>$m[subm]</th>
                      <th width='60'>$m[nogr]</th>
                      <th width='130'>$m[deadline]</th>
                      <th width='80'>$langCommands</th>
                    </tr>";
        $index = 0;
        foreach ($result as $row) {
            // Check if assignement contains submissions
            $num_submitted = Database::get()->querySingle("SELECT COUNT(*) AS count FROM assignment_submit WHERE assignment_id = ?d", $row->id)->count;
            if (!$num_submitted) {
                $num_submitted = '&nbsp;';
            }
                    
            $num_ungraded = Database::get()->querySingle("SELECT COUNT(*) AS count FROM assignment_submit WHERE assignment_id = ?d AND grade IS NULL", $row->id)->count;            
            if (!$num_ungraded) {
                $num_ungraded = '&nbsp;';
            }
            if (!$row->active) {
                $tool_content .= "\n<tr class = 'invisible'>";
            } else {
                if ($index % 2 == 0) {
                    $tool_content .= "\n<tr class='even'>";
                } else {
                    $tool_content .= "\n<tr class='odd'>";
                }
            }
            if((int)$row->deadline){
                $deadline = nice_format($row->deadline, true);
            }else{
                $deadline = $m['no_deadline'];
            }
            $tool_content .= "
			  <td><img src='$themeimg/arrow.png' alt=''>
                              <a href='$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id={$row->id}'";
            $tool_content .= ">";
            $tool_content .= q($row->title);

            $tool_content .= "</a></td>
			  <td class='center'>$num_submitted</td>
			  <td class='center'>$num_ungraded</td>
			  <td class='center'>" . $deadline; 
            if ($row->time > 0) {
                $tool_content .= " (<span>$langDaysLeft" . format_time_duration($row->time) . ")</span>";
            } else if((int)$row->deadline){
                $tool_content .= " (<span class='expired'>$m[expired]</span>)";
            }                         
           $tool_content .= "</td>
              <td class='right'>" .
                  icon('edit', $m['edit'],
                      "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$row->id&amp;choice=edit") .
                  '&nbsp;';
           if (is_numeric($num_submitted) && $num_submitted > 0) {
                $tool_content .= icon('clear', $m['WorkSubsDelete'],
                    "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$row->id&amp;choice=do_purge",
                    "onClick='return confirmation(\"$langWarnForSubmissions. $langDelSure\")'") .
                    '&nbsp;';
           }
            $tool_content .= icon('delete', $m['delete'],
                "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;id=$row->id&amp;choice=do_delete",
                "onClick='return confirmation(\"$langConfirmDelete\")'") .
                '&nbsp;';
            if ($row->active) {
                $tool_content .= icon('visible', $m['deactivate'],
                    "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;choice=disable&amp;id=$row->id");
            } else {
                $tool_content .= icon('invisible', $m['activate'],
                    "$_SERVER[SCRIPT_NAME]?course=$course_code&amp;choice=enable&amp;id=$row->id");
            }
            $tool_content .= "</td></tr>";
            $index++;
        }
        $tool_content .= '</table>';
    } else {
        $tool_content .= "\n<p class='alert1'>$langNoAssign</p>";        
    }
}

// submit grade and comment for a student submission
function submit_grade_comments($id, $sid, $grade, $comment, $email) {
    global $tool_content, $langGrades, $langWorkWrongInput, $course_id;

    $grade_valid = filter_var($grade, FILTER_VALIDATE_FLOAT);
    (isset($grade) && $grade_valid!== false) ? $grade = $grade_valid : $grade = NULL;
        
    if (Database::get()->query("UPDATE assignment_submit 
                                SET grade = ?d, grade_comments = ?s,
                                grade_submission_date = NOW(), grade_submission_ip = ?s
                                WHERE id = ?d", $grade, $comment, $_SERVER['REMOTE_ADDR'], $sid)->affectedRows>0) {
        $title = Database::get()->querySingle("SELECT title FROM assignment WHERE id = ?d", $id)->title;
        Log::record($course_id, MODULE_ID_ASSIGN, LOG_MODIFY, array('id' => $sid,
                'title' => $title,
                'grade' => $grade,
                'comments' => $comment));
       Session::Messages($langGrades, 'success'); 
    } else {
        Session::Messages($langGrades, 'alert1');
    }
    if ($email) {
        grade_email_notify($id, $sid, $grade, $comment);
    }    
    show_assignment($id);
}

// submit grades to students
function submit_grades($grades_id, $grades, $email = false) {
    global $tool_content, $langGrades, $langWorkWrongInput, $course_id;

    foreach ($grades as $sid => $grade) {
        $sid = intval($sid);
        $val = Database::get()->querySingle("SELECT grade from assignment_submit WHERE id = ?d", $sid)->grade;
        $grade_valid = filter_var($grade, FILTER_VALIDATE_FLOAT);
        (isset($grade) && $grade_valid!== false) ? $grade = $grade_valid : $grade = NULL;             
        if ($val != $grade) {
            if (Database::get()->query("UPDATE assignment_submit
                                        SET grade = ?d, grade_submission_date = NOW(), grade_submission_ip = ?s
                                        WHERE id = ?d", $grade, $_SERVER['REMOTE_ADDR'], $sid)->affectedRows > 0) {
                $assign_id = Database::get()->querySingle("SELECT assignment_id FROM assignment_submit WHERE id = ?d", $sid)->assignment_id;
                $title = Database::get()->querySingle("SELECT title FROM assignment WHERE assignment.id = ?d", $assign_id)->title;
                Log::record($course_id, MODULE_ID_ASSIGN, LOG_MODIFY, array('id' => $sid,
                        'title' => $title,
                        'grade' => $grade));
                if ($email) {
                    grade_email_notify($grades_id, $sid, $grade, '');
                }          
                Session::Messages($langGrades, 'success');
            }
        }
    }
    show_assignment($grades_id);
}

// functions for downloading
function send_file($id, $file_type) {
    global $course_code, $uid, $is_editor;
    if (isset($file_type)) {
        $info = Database::get()->querySingle("SELECT * FROM assignment WHERE id = ?d", $id);
        if (count($info)==0) {
            return false;
        }
        if (!($is_editor || $GLOBALS['is_member'])) {
            return false;
        }        
        send_file_to_client("$GLOBALS[workPath]/admin_files/$info->file_path", $info->file_name, null, true);
    } else {
        $info = Database::get()->querySingle("SELECT * FROM assignment_submit WHERE id = ?d", $id);
        if (count($info)==0) {
            return false;
        }
        if ($info->group_id) {
            initialize_group_info($info->group_id);
        }
        if (!($is_editor or $info->uid == $uid or $GLOBALS['is_member'])) {
            return false;
        }
        send_file_to_client("$GLOBALS[workPath]/$info->file_path", $info->file_name, null, true);        
    }
    exit;
}

// Zip submissions to assignment $id and send it to user
function download_assignments($id) {
    global $workPath, $course_code;
    $counter = Database::get()->querySingle('SELECT COUNT(*) AS count FROM assignment_submit WHERE assignment_id = ?d', $id)->count;
    if ($counter>0) {
        $secret = work_secret($id);
        $filename = "{$course_code}_work_$id.zip";  
        chdir($workPath);
        create_zip_index("$secret/index.html", $id);
        $zip = new PclZip($filename);
        $flag = $zip->create($secret, "work_$id", $secret);
        header("Content-Type: application/x-zip");
        header("Content-Disposition: attachment; filename=$filename");
        stop_output_buffering();
        @readfile($filename);
        @unlink($filename);
        exit;
    }else{
        return false;
    }
}

// Create an index.html file for assignment $id listing user submissions
// Set $online to TRUE to get an online view (on the web) - else the
// index.html works for the zip file
function create_zip_index($path, $id, $online = FALSE) {
    global $charset, $m;

    $fp = fopen($path, "w");
    if (!$fp) {
        die("Unable to create assignment index file - aborting");
    }
    fputs($fp, '
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=' . $charset . '">
                <style type="text/css">
                .sep td, th { border: 1px solid; }
                td { border: none; }
                table { border-collapse: collapse; border: 2px solid; }
                .sep { border-top: 2px solid black; }
                </style>
	</head>
	<body>
		<table width="95%" class="tbl">
			<tr>
				<th>' . $m['username'] . '</th>
				<th>' . $m['am'] . '</th>
				<th>' . $m['filename'] . '</th>
				<th>' . $m['sub_date'] . '</th>
				<th>' . $m['grade'] . '</th>
			</tr>');

    $result = Database::get()->queryArray("SELECT a.uid, a.file_path, a.submission_date, a.grade, a.comments, a.grade_comments, a.group_id, b.deadline FROM assignment_submit a, assignment b WHERE a.assignment_id = ?d AND a.assignment_id = b.id ORDER BY a.id", $id);

    foreach ($result as $row) {
        $filename = basename($row->file_path);
        $filelink = empty($filename) ? '&nbsp;' :
                ("<a href='$filename'>" . htmlspecialchars($filename) . '</a>');
        $late_sub_text = ((int) $row->deadline && $row->submission_date > $row->deadline) ?  '<div style="color:red;">$m[late_submission]</div>' : '';
        fputs($fp, '
			<tr class="sep">
				<td>' . q(uid_to_name($row->uid)) . '</td>
				<td>' . q(uid_to_am($row->uid)) . '</td>
				<td align="center">' . $filelink . '</td>
				<td align="center">' . $row->submission_date .$late_sub_text. '</td>
				<td align="center">' . $row->grade . '</td>
			</tr>');
        if (trim($row->comments != '')) {
            fputs($fp, "
			<tr><td colspan='6'><b>$m[comments]: " .
                    "</b>$row->comments</td></tr>");
        }
        if (trim($row->grade_comments != '')) {
            fputs($fp, "
			<tr><td colspan='6'><b>$m[gradecomments]: " .
                    "</b>$row->grade_comments</td></tr>");
        }
        if (!empty($row->group_id)) {
            fputs($fp, "<tr><td colspan='6'>$m[groupsubmit] " .
                    "$m[ofgroup] $row->group_id</td></tr>\n");
        }
    }
    fputs($fp, ' </table></body></html>');
    fclose($fp);
}

// Show a simple html page with grades and submissions
function show_plain_view($id) {
    global $workPath, $charset;

    $secret = work_secret($id);
    create_zip_index("$secret/index.html", $id, TRUE);
    header("Content-Type: text/html; charset=$charset");
    readfile("$workPath/$secret/index.html");
    exit;
}

// Notify students by email about grade/comment submission
// Send to single user for individual submissions or group members for group
// submissions
function grade_email_notify($assignment_id, $submission_id, $grade, $comments) {
    global $m, $currentCourseName, $urlServer, $course_code;
    static $title, $group;

    if (!isset($title)) {
        $res = Database::get()->querySingle("SELECT title, group_submissions FROM assignment WHERE id = ?d", $assignment_id);
        $title = $res->title;
        $group = $res->group_submissions;
    }
    $info = Database::get()->querySingle("SELECT uid, group_id
                                         FROM assignment_submit WHERE id= ?d", $submission_id);

    $subject = sprintf($m['work_email_subject'], $title);
    $body = sprintf($m['work_email_message'], $title, $currentCourseName) . "\n\n";
    if ($grade != '') {
        $body .= "$m[grade]: $grade\n";
    }
    if ($comments) {
        $body .= "$m[gradecomments]: $comments\n";
    }
    $body .= "\n$m[link_follows]\n{$urlServer}modules/work/work.php?course=$course_code&id=$assignment_id\n";
    if (!$group or !$info->group_id) {
        send_mail_to_user_id($info->uid, $subject, $body);
    } else {
        send_mail_to_group_id($info->group_id, $subject, $body);
    }
}

function send_mail_to_group_id($gid, $subject, $body) {
    global $charset;
    $res = Database::get()->queryArray("SELECT surname, givenname, email
                                 FROM user, group_members AS members
                                 WHERE members.group_id = ?d 
                                 AND user.id = members.user_id", $gid);
    foreach ($res as $info) {
        send_mail('', '', "$info->givenname $info->surname", $info->email, $subject, $body, $charset);
    }
}

function send_mail_to_user_id($uid, $subject, $body) {
    global $charset;
    $user = Database::get()->querySingle("SELECT surname, givenname, email FROM user WHERE id = ?d", $uid);
    send_mail('', '', "$user->givenname $user->surname", $user->email, $subject, $body, $charset);
}

// Return a list of users with no submissions for assignment $id
function users_with_no_submissions($id) {
    global $course_id;
    if (Database::get()->querySingle("SELECT assign_to_specific FROM assignment WHERE id = ?d", $id)->assign_to_specific) {   
        $q = Database::get()->queryArray("SELECT user.id AS id, surname, givenname
                                FROM user, course_user
                                WHERE user.id = course_user.user_id 
                                AND course_user.course_id = ?d AND course_user.status = 5 
                                AND user.id NOT IN (SELECT uid FROM assignment_submit
                                                    WHERE assignment_id = ?d) AND user.id IN (SELECT user_id FROM assignment_to_specific WHERE assignment_id = ?d)", $course_id, $id, $id);       
    } else {
        $q = Database::get()->queryArray("SELECT user.id AS id, surname, givenname
                                FROM user, course_user
                                WHERE user.id = course_user.user_id 
                                AND course_user.course_id = ?d AND course_user.status = 5 
                                AND user.id NOT IN (SELECT uid FROM assignment_submit
                                                    WHERE assignment_id = ?d)", $course_id, $id);
    }
    $users = array();
    foreach ($q as $row) {
        $users[$row->id] = "$row->surname $row->givenname";
    }
    return $users;
}

// Return a list of groups with no submissions for assignment $id
function groups_with_no_submissions($id) {
    global $course_id;
    
    $q = Database::get()->queryArray('SELECT group_id FROM assignment_submit WHERE assignment_id = ?d', $id);
    $groups = user_group_info(null, $course_id, $id);
    if (count($q)>0) {
        foreach ($q as $row) {
            unset($groups[$row->group_id]);
        }
    }
    return $groups;
}
