<?php
// Front controller / router

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/src/Router.php';

// ── Public routes ────────────────────────────────────────────────────
Router::get('/', function () {
    include __DIR__ . '/pages/training_list.php';
});

Router::any('/login', function () {
    include __DIR__ . '/pages/login.php';
});

Router::get('/logout', function () {
    Auth::logout();
    header('Location: ' . APP_URL . '/');
    exit;
});

// Magic link verify
Router::get('/auth/verify', function () {
    include __DIR__ . '/api/auth.php';
});

// Registration confirm
Router::get('/registration/confirm', function () {
    include __DIR__ . '/api/registration_confirm.php';
});

// Email approve / reject buttons
Router::any('/api/approve', function () {
    include __DIR__ . '/api/approve.php';
});
Router::any('/api/reject', function () {
    include __DIR__ . '/api/reject.php';
});

// Training detail
Router::get('/training/:id', function (array $p) {
    $params = $p;
    include __DIR__ . '/pages/training_detail.php';
});

// Certificate (public)
Router::get('/certificate', function () {
    include __DIR__ . '/pages/certificate.php';
});

// ── Authenticated routes ─────────────────────────────────────────────
Router::get('/dashboard', function () {
    include __DIR__ . '/pages/dashboard.php';
});

Router::post('/profile/name', function () {
    include __DIR__ . '/api/profile_name.php';
});

// ── Approver ─────────────────────────────────────────────────────────
Router::get('/approver', function () {
    include __DIR__ . '/pages/approver_dashboard.php';
});

Router::post('/approver/decide', function () {
    include __DIR__ . '/api/approver_decide.php';
});

// ── Einsteller / Manager ──────────────────────────────────────────────
Router::get('/manage', function () {
    include __DIR__ . '/pages/manage_dashboard.php';
});

Router::any('/manage/training/create', function () {
    $params = [];
    include __DIR__ . '/pages/training_form.php';
});

Router::any('/manage/training/:id/edit', function (array $p) {
    $params = $p;
    include __DIR__ . '/pages/training_form.php';
});

Router::any('/manage/training/:id/participants', function (array $p) {
    $params = $p;
    include __DIR__ . '/pages/participants.php';
});

Router::any('/manage/training/:id/attendance', function (array $p) {
    $params = $p;
    include __DIR__ . '/pages/attendance.php';
});

Router::any('/manage/bulk-approve', function () {
    include __DIR__ . '/pages/bulk_approve.php';
});

// ── Admin ─────────────────────────────────────────────────────────────
Router::any('/admin', function () {
    include __DIR__ . '/pages/admin_settings.php';
});

Router::any('/admin/users', function () {
    include __DIR__ . '/pages/admin_users.php';
});

Router::any('/admin/domains', function () {
    include __DIR__ . '/pages/admin_domains.php';
});

// ── Install wizard (only if setup_complete = 0) ───────────────────────
Router::any('/install', function () {
    include __DIR__ . '/install.php';
});

// ── Dispatch ──────────────────────────────────────────────────────────
Router::dispatch();
