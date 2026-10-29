<?php
require_once __DIR__ . '/includes/functions.php';

$u = current_user();
if (!$u) {
    redirect('login.php');
}
redirect($u['role'] === 'caregiver' ? 'caregiver.php' : 'patient.php');
