<?php
/**
*
* Visitor Counter
* Copyright (C) 2010 Arie Nugraha (dicarve@yahoo.com)
* Modified By Eddy Subratha (eddy.subratha@gmail.com)
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*
*/
use SLiMS\{Visitor,Json};

// be sure that this file not accessed directly
if (!defined('INDEX_AUTH')) {
    die("can not access this file directly");
} elseif (INDEX_AUTH != 1) {
    die("can not access this file directly");
}

$env = loadVisitPluginEnv();

// Create visitor instance
$visitor = new Visitor($sysconf['allowed_counter_ip'], $sysconf['time_visitor_limitation'], $opac);
$visitor->accessCheck();

if ($sysconf['enable_counter_by_ip'] && !$visitor->isAccessAllow()) {
    header ("location: index.php");
    exit;
}

// start the output buffering for main content
ob_start();

if (isset($_POST['counter'])) {

  if (trim($_POST['memberID']) == '') {
    die(Json::stringify(['message' => __('Member ID can\'t be empty'), 'image' => 'person.png'])->withHeader());
  }
  
  if (!isset($_POST['visitPurpose']) || trim($_POST['visitPurpose']) == '') {
    die(Json::stringify(['message' => __('Please select a visit purpose'), 'image' => 'person.png'])->withHeader());
  }
   
  // sleep for a while
  sleep(0);

  // Record visitor data
  $visitor->record(trim($_POST['memberID']));

  $image = 'person.png'; // default image
  $visitPurpose = trim($_POST['visitPurpose']);
  $visitPurposeText = '';
  
  // Convert visit purpose value to text
  switch($visitPurpose) {
    case '1':
      $visitPurposeText = __('Baca');
      break;
    case '2':
      $visitPurposeText = __('Browsing');
      break;
    case '3':
      $visitPurposeText = __('Belajar');
      break;
    default:
      $visitPurposeText = __('Unknown');
  }
  
  if ($visitor->getResult() === true) {
    // Map visitor data into variable list
    list($memberId, $memberName, $institution, $image) = $visitor->getData();

    // default message with visit purpose
    $message = $memberName . __(', thank you for inserting your data to our visitor log') . ' (' . $visitPurposeText . ')';

    // Expire message
    if ($visitor->isMemberExpire()) $message = '<div class="error visitor-error">'.__('Your membership already EXPIRED, please renew/extend your membership immediately').'</div>';

    // already checkin message
    if ($visitor->isAlreadyCheckIn()) $message = __('Welcome back').' '.$memberName.'. (' . $visitPurposeText . ')';

  // For guest access, we now require visit purpose instead of institution
  } else {
    $message = ENVIRONMENT === 'production' ? __('Error inserting counter data to database!') : $visitor->getError();
  }
  
  // send response with visit purpose
  die(Json::stringify([
    'message' => $message, 
    'image' => $image, 
    'status' => $visitor->getError(),
    'visit_purpose' => $visitPurpose,
    'visit_purpose_text' => $visitPurposeText
  ])->withHeader());
}

// include visitor form template
require __DIR__ . '/theme/visitor_template.php';
// require SB.$sysconf['template']['dir'].'/'.$sysconf['template']['theme'].'/visitor_template.php';


?>
<div style="display: none !important;">
<input type="text" id="text_voice" value=""></input>
<button type="button" id="speak">Speak</button>
</div>

<script type="text/javascript">
</script>

<?php
// main content
$main_content = ob_get_clean();
// page title
$page_title = __('Visitor Counter').' | ' . $sysconf['library_name'];
require $main_template_path;
exit();

