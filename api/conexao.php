<?php

$host = "SEU_HOST";
$usuario = "SEU_USUARIO";
$senha = "SUA_SENHA";
$banco = "NOME_DO_BANCO";

$mysqli = new mysqli(
    $host,
    $usuario,
    $senha,
    $banco
);

if ($mysqli->connect_error) {
    die("Erro ao conectar ao banco de dados.");
}

$mysqli->set_charset("utf8mb4");

?>
