<?php
// CSV export of a training: info, sessions, registrations, attendance.
// Included by index.php via GET /manage/training/:id/export
Auth::requireRole('admin', 'einsteller');

$user       = Auth::user();
$trainingId = (int)($params['id'] ?? 0);
$training   = Training::getById($trainingId);

if (!$training) { http_response_code(404); exit; }
if ($training['creator_id'] != $user['id'] && $user['role'] !== 'admin') {
    http_response_code(403); exit;
}

$sessions     = Training::getSessions($trainingId);
$participants = Training::getParticipants($trainingId);

// Attendance: all records for this training
$attendance = Database::fetchAll(
    'SELECT a.*, u.name AS uname, u.email AS uemail, ts.title AS session_title,
            ts.start_datetime
     FROM `attendance` a
     JOIN `users` u ON a.user_id = u.id
     LEFT JOIN `training_sessions` ts ON a.session_id = ts.id
     WHERE a.training_id = ?
     ORDER BY ts.start_datetime, u.name',
    [$trainingId]
);

// ── CSV helpers ────────────────────────────────────────────────────────

function csvRow(array $fields): string {
    return implode(';', array_map(function ($v) {
        $v = str_replace('"', '""', (string)$v);
        return '"' . $v . '"';
    }, $fields)) . "\r\n";
}

function csvSection(string $title): string {
    return "\r\n" . csvRow([$title]) . "\r\n";
}

// ── Build output ──────────────────────────────────────────────────────

$filename = 'fortbildung_' . $training['public_id'] . '_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

// ── 1. Grunddaten ──────────────────────────────────────────────────────
echo csvRow(['FORTBILDUNG – Exportiert am ' . date('d.m.Y H:i')]);
echo "\r\n";
echo csvRow(['Feld', 'Wert']);
echo csvRow(['Nummer',           $training['public_id']]);
echo csvRow(['Titel',            $training['title']]);
echo csvRow(['Beschreibung',     $training['description'] ?? '']);
echo csvRow(['Veranstaltungsort',$training['location'] ?? '']);
echo csvRow(['Status',           match($training['status']) {
    'open'     => 'Offen',
    'draft'    => 'Entwurf',
    'closed'   => 'Geschlossen',
    'archived' => 'Archiviert',
    default    => $training['status'],
}]);
echo csvRow(['Genehmigungsmodus', match($training['approval_mode']) {
    'auto'             => 'Automatisch',
    'manual_individual'=> 'Einzelgenehmigung',
    'manual_bulk'      => 'Sammelgenehmigung',
    default            => $training['approval_mode'],
}]);
echo csvRow(['Max. Teilnehmende', $training['max_participants'] ?? 'Unbegrenzt']);
echo csvRow(['Warteliste',        $training['waitlist_enabled'] ? 'Ja' : 'Nein']);
echo csvRow(['Anmeldeschluss',    $training['registration_deadline']
    ? date('d.m.Y H:i', strtotime($training['registration_deadline'])) : '–']);
echo csvRow(['Erstellt am',       date('d.m.Y H:i', strtotime($training['created_at']))]);
echo csvRow(['Erstellt von',      $training['creator_name'] ?: $training['creator_email']]);

// ── 2. Termine ────────────────────────────────────────────────────────
echo csvSection('TERMINE');
if ($sessions) {
    echo csvRow(['Nr.', 'Titel', 'Datum', 'Beginn', 'Ende', 'Ort', 'Notizen']);
    foreach ($sessions as $s) {
        echo csvRow([
            $s['session_number'],
            $s['title'] ?? '',
            date('d.m.Y', strtotime($s['start_datetime'])),
            date('H:i',   strtotime($s['start_datetime'])),
            date('H:i',   strtotime($s['end_datetime'])),
            $s['location'] ?? '',
            $s['notes']    ?? '',
        ]);
    }
} else {
    echo csvRow(['Keine Termine eingetragen.']);
}

// ── 3. Anmeldungen ────────────────────────────────────────────────────
$statusLabels = [
    'approved'        => 'Genehmigt',
    'pending_approval'=> 'Ausstehend',
    'pending_confirm' => 'Unbestätigt',
    'waitlist'        => 'Warteliste',
    'rejected'        => 'Abgelehnt',
    'cancelled'       => 'Storniert',
];

echo csvSection('ANMELDUNGEN (' . count($participants) . ')');
echo csvRow(['Name', 'E-Mail', 'Status', 'Wartelistenplatz', 'Angemeldet am', 'Entschieden am', 'Ablehnungsgrund']);
foreach ($participants as $p) {
    echo csvRow([
        $p['name']  ?? '',
        $p['email'] ?? '',
        $statusLabels[$p['status']] ?? $p['status'],
        $p['waitlist_position'] ?? '',
        $p['created_at']  ? date('d.m.Y H:i', strtotime($p['created_at']))  : '',
        $p['decided_at']  ? date('d.m.Y H:i', strtotime($p['decided_at']))  : '',
        $p['rejection_reason'] ?? '',
    ]);
}

// ── 4. Anwesenheit ────────────────────────────────────────────────────
echo csvSection('ANWESENHEIT (' . count($attendance) . ' Einträge)');
if ($attendance) {
    echo csvRow(['Name', 'E-Mail', 'Termin', 'Datum', 'Anwesend', 'Erfasst am']);
    foreach ($attendance as $a) {
        echo csvRow([
            $a['uname']  ?? '',
            $a['uemail'] ?? '',
            $a['session_title'] ?? '(Gesamtveranstaltung)',
            $a['start_datetime'] ? date('d.m.Y', strtotime($a['start_datetime'])) : '',
            $a['attended'] ? 'Ja' : 'Nein',
            $a['recorded_at'] ? date('d.m.Y H:i', strtotime($a['recorded_at'])) : '',
        ]);
    }
} else {
    echo csvRow(['Keine Anwesenheitsdaten erfasst.']);
}

exit;
