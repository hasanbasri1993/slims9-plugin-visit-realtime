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
$opac = $opac ?? null;
$visitor = new Visitor($sysconf['allowed_counter_ip'], $sysconf['time_visitor_limitation'], $opac);
$visitor->accessCheck();

// start the output buffering for main content
ob_start();

// AJAX member autocomplete search endpoint
if (isset($_GET['action']) && $_GET['action'] === 'search_member') {
    ob_end_clean();
    $keywords = trim($_GET['keywords'] ?? '');
    if (strlen($keywords) < 2) {
        die(Json::stringify([])->withHeader());
    }
    
    $kwPattern = '%' . $keywords . '%';
    try {
        $stmt = \SLiMS\DB::getInstance()->prepare("
            SELECT member_id, member_name, inst_name, COALESCE(member_notes, '') AS member_notes, COALESCE(member_image, 'photo.png') AS member_image
            FROM member
            WHERE (is_pending = 0 OR is_pending IS NULL)
              AND expire_date >= CURDATE()
              AND (member_id LIKE :kw OR member_name LIKE :kw)
            ORDER BY member_name ASC
            LIMIT 8
        ");
        $stmt->execute([':kw' => $kwPattern]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        die(Json::stringify($results)->withHeader());
    } catch (\Throwable $e) {
        die(Json::stringify([])->withHeader());
    }
}

if (isset($_POST['counter'])) {

  if (!isset($_POST['memberID']) || trim($_POST['memberID']) == '') {
    die(Json::stringify(['message' => __('Member ID can\'t be empty'), 'image' => 'person.png'])->withHeader());
  }

  // Older/IoT clients send the visit purpose code as "institution" instead of
  // "visitPurpose" — fall back to it only when visitPurpose wasn't sent at all,
  // so it never overrides a real value coming from the web form.
  if ((!isset($_POST['visitPurpose']) || trim($_POST['visitPurpose']) == '') && isset($_POST['institution'])) {
    $_POST['visitPurpose'] = $_POST['institution'];
  }

  if (!isset($_POST['visitPurpose']) || trim($_POST['visitPurpose']) == '') {
    die(Json::stringify(['message' => __('Please select a visit purpose'), 'image' => 'person.png'])->withHeader());
  }

  // Same fallback for room_code: IoT clients don't pass ?room= in the query
  // string, so Visitor::record() would otherwise store a null room_code.
  if (!isset($_GET['room']) || trim($_GET['room']) === '') {
    $_GET['room'] = trim($_POST['visitPurpose']);
  }

  // sleep for a while
  sleep(0);

  // Record visitor data
  $visitor = $visitor->record(trim($_POST['memberID']));

  $image = 'person.png'; // default image
  $visitPurpose = trim($_POST['visitPurpose']);
  $visitPurposeText = '';
  
  // Convert visit purpose value to text
  switch($visitPurpose) {
    case '1':
      $visitPurposeText = __('Baca');
      break;
    case '2':
      $visitPurposeText = __('Mengerjakan Tugas');
      break;
    case '3':
      $visitPurposeText = __('Mencari Referensi');
      break;
    case '4':
      $visitPurposeText = __('Mengakses Internet/Komputer');
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
  
  // Get Pusher configuration from environment variables
  $pusherKey = $env['PUSHER_KEY'] ?? '';
  $pusherSecret = $env['PUSHER_SECRET'] ?? '';
  $pusherAppId = $env['PUSHER_APP_ID'] ?? '';
  $pusherCluster = $env['PUSHER_CLUSTER'] ?? 'ap1';
  $pusherUseTls = isset($env['PUSHER_USE_TLS']) ? $env['PUSHER_USE_TLS'] === 'true' : true;
  $pusherChannel = $env['PUSHER_CHANNEL'] ?? 'my-channel';
  $pusherEvent = $env['PUSHER_EVENT'] ?? 'my-event';
  
  $pusher = new SimplePusher(
    $pusherKey,
    $pusherSecret,
    $pusherAppId,
    $pusherCluster,
    $pusherUseTls
  );

  $data['member_image'] = $image;
  $data['member_id'] = $memberId;
  $data['member_name'] = $memberName;
  $data['institution'] = $institution;
  $data['visit_purpose'] = $visitPurpose;
  $data['visit_purpose_text'] = $visitPurposeText;
  $data['message'] =  $memberName . __(', thank you for inserting your data to our visitor log');
  if (isset($visitPurposeText) && !empty($visitPurposeText)) {
    $data['message'] .= ' (' .  $visitPurposeText . ')';
  }
  // Exclude the submitting browser's own Pusher connection from the broadcast,
  // otherwise it hears the greeting twice: once from this HTTP response, once
  // from the Pusher event it triggered on itself.
  $pusher->trigger($pusherChannel, $pusherEvent, $data, $_POST['socket_id'] ?? null);

  // send response with visit purpose and member details
  die(Json::stringify([
    'message' => $message, 
    'image' => $image, 
    'status' => $visitor->getError(),
    'member_id' => $memberId ?? trim($_POST['memberID']),
    'member_name' => $memberName ?? trim($_POST['memberID']),
    'memberName' => $memberName ?? trim($_POST['memberID']),
    'visit_purpose' => $visitPurpose,
    'visit_purpose_text' => $visitPurposeText
  ])->withHeader());
}

// include visitor form template
require __DIR__ . '/theme/visitor_template.php';

// main content
$main_content = ob_get_clean();
// page title
$page_title = __('Visitor Counter').' | ' . $sysconf['library_name'];
require $main_template_path;
exit();

