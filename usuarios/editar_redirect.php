<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== 'admin') {
    header("Location: ../views/login_form.php");
    exit();
}

if (isset($_POST['edit_user_id'])) {
    $_SESSION['edit_user_id'] = (int)$_POST['edit_user_id'];
    header("Location: editar_usuario.php");
    exit();
}

header("Location: listar.php");
exit();
?>