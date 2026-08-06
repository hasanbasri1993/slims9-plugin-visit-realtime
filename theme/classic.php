<?php
# @Author: Waris Agung Widodo <user>
# @Date:   2018-01-21T11:46:42+07:00
# @Email:  ido.alit@gmail.com
# @Filename: classic.php
# @Last modified by:   user
# @Last modified time: 2018-01-26T18:43:30+07:00

// ----------------------------------------------------------------------------
// Be sure that this file not accessed directly
// ----------------------------------------------------------------------------
if (!defined('INDEX_AUTH')) {
  die("can not access this file directly");
} elseif (INDEX_AUTH != 1) {
  die("can not access this file directly");
}

// ----------------------------------------------------------------------------
// Define current public template directory
// ----------------------------------------------------------------------------
define('CURRENT_TEMPLATE_DIR', $sysconf['template']['dir'] . '/' . $sysconf['template']['theme'] . '/');

// ----------------------------------------------------------------------------
// Method for create url assets
// ----------------------------------------------------------------------------
function assets($path = '')
{
  return CURRENT_TEMPLATE_DIR . 'assets/' . $path;
}
