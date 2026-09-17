<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['subject_choice']);
}

header('Location: subject-choice.php');
exit;
