<?php
// Front controller / router
// public/ is the DocumentRoot; everything else lives one level up.

require_once dirname(__DIR__) . '/bootstrap.php';

// ── Public routes ────────────────────────────────────────────────────
Router::get('/', function () {
    include APP_PATH . '/pages/training_list.php';
});

Router::any('/login', function () {
    include APP_PATH . '/pages/login.php';
});

Router::get('/logout', function () {
    Auth::logout();
    header('Location: ' . APP_URL . '/');
    exit;
});

// Magic link verify
Router::get('/auth/verify', function () {
    include APP_PATH . '/api/auth.php';
});

// Registration confirm
Router::get('/registration/confirm', function () {
    include APP_PATH . '/api/registration_confirm.php';
});

// Email approve / reject buttons
Router::any('/api/approve', function () {
    include APP_PATH . '/api/approve.php';
});
Router::any('/api/reject', function () {
    include APP_PATH . '/api/reject.php';
});

// Training detail
Router::get('/training/:id', function (array $p) {
    $params = $p;
    include APP_PATH . '/pages/training_detail.php';
});

// Certificate (public)
Router::get('/certificate', function () {
    include APP_PATH . '/pages/certificate.php';
});

// ── Authenticated routes ─────────────────────────────────────────────
Router::get('/dashboard', function () {
    include APP_PATH . '/pages/dashboard.php';
});

Router::post('/profile/name', function () {
    include APP_PATH . '/api/profile_name.php';
});

// ── Approver ─────────────────────────────────────────────────────────
Router::get('/approver', function () {
    include APP_PATH . '/pages/approver_dashboard.php';
});

Router::post('/approver/decide', function () {
    include APP_PATH . '/api/approver_decide.php';
});

// ── Einsteller / Manager ──────────────────────────────────────────────
Router::get('/manage', function () {
    include APP_PATH . '/pages/manage_dashboard.php';
});

Router::any('/manage/training/create', function () {
    $params = [];
    include APP_PATH . '/pages/training_form.php';
});

Router::any('/manage/training/:id/edit', function (array $p) {
    $params = $p;
    include APP_PATH . '/pages/training_form.php';
});

Router::any('/manage/training/:id/participants', function (array $p) {
    $params = $p;
    include APP_PATH . '/pages/participants.php';
});

Router::any('/manage/training/:id/attendance', function (array $p) {
    $params = $p;
    include APP_PATH . '/pages/attendance.php';
});

Router::any('/manage/bulk-approve', function () {
    include APP_PATH . '/pages/bulk_approve.php';
});

// ── Admin ─────────────────────────────────────────────────────────────
Router::any('/admin', function () {
    include APP_PATH . '/pages/admin_settings.php';
});

Router::any('/admin/users', function () {
    include APP_PATH . '/pages/admin_users.php';
});

Router::any('/admin/domains', function () {
    include APP_PATH . '/pages/admin_domains.php';
});

// ── Install wizard (only if setup_complete = 0) ───────────────────────
Router::any('/install', function () {
    include __DIR__ . '/install.php'; // install.php lives alongside index.php in public/
});

// ── Dispatch ──────────────────────────────────────────────────────────
Router::dispatch();
