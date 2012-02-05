<?php
function list_users() {
  $prefix = FALSE;
  if ($_GET['filter']) {
    $prefix = $_SESSION['saldo_prefix'];
  }
  $users = get_users(FALSE,$prefix);
  return drupal_to_js($users);
}