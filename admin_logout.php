<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/Repositories.php';
require_once __DIR__ . '/classes/Services.php';

$database = new db();
$pdo = $database->connect();
$organisationRepository = new OrganisationRepository($pdo);
$authService = new AuthService($organisationRepository);
$authService->logout();
header('Location: admin_login.php');
exit;
