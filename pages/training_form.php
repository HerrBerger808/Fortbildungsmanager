<?php
// Create / Edit training form
Auth::requireRole('admin', 'einsteller');

$user       = Auth::user();
$isEdit     = isset($params['id']);
$trainingId = $isEdit ? (int)$params['id'] : 0;
$training   = $isEdit ? Training::getById($trainingId) : null;
$sessions   = $isEdit ? Training::getSessions($trainingId) : [];
$levels     = $isEdit ? Training::getApprovalLevels($trainingId) : [];
$genehmiger = User::getGenehmiger();

// Access check for edit
if ($isEdit && $training && $training['creator_id'] != $user['id'] && $user['role'] !== 'admin') {
    http_response_code(403);
    include APP_PATH . '/templates/error.php';
    exit;
}

$errors = [];
$data   = $training ?: [
    'title'                 => '',
    'description'           => '',
    'location'              => '',
    'is_multi_part'         => 0,
    'max_participants'      => '',
    'waitlist_enabled'      => 1,
    'approval_mode'         => 'auto',
    'registration_deadline' => '',
    'status'                => 'draft',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = $_POST;

    $data = [
        'title'                 => trim($post['title'] ?? ''),
        'description'           => trim($post['description'] ?? ''),
        'location'              => trim($post['location'] ?? ''),
        'is_multi_part'         => isset($post['is_multi_part']) ? 1 : 0,
        'max_participants'      => $post['max_participants'] !== '' ? (int)$post['max_participants'] : null,
        'waitlist_enabled'      => isset($post['waitlist_enabled']) ? 1 : 0,
        'approval_mode'         => $post['approval_mode'] ?? 'auto',
        'registration_deadline' => !empty($post['registration_deadline']) ? $post['registration_deadline'] : null,
        'status'                => $post['status'] ?? 'draft',
    ];

    if (empty($data['title'])) $errors[] = 'Bitte geben Sie einen Titel ein.';
    if (!in_array($data['approval_mode'], ['auto','manual_individual','manual_bulk'])) {
        $errors[] = 'Ungültiger Genehmigungsmodus.';
    }

    if (empty($errors)) {
        if ($isEdit) {
            Training::update($trainingId, $data);
        } else {
            $trainingId = Training::create($data, $user['id']);
            $isEdit     = true;
        }

        // Save sessions
        // First delete removed sessions
        $keepIds = [];
        foreach ($post['session_id'] ?? [] as $i => $sid) {
            $start = $post['session_start'][$i] ?? '';
            $end   = $post['session_end'][$i] ?? '';
            if (!$start || !$end) continue;
            $sData = [
                'session_number' => $i + 1,
                'title'          => $post['session_title'][$i] ?? '',
                'start_datetime' => $start,
                'end_datetime'   => $end,
                'location'       => $post['session_location'][$i] ?? '',
                'notes'          => $post['session_notes'][$i] ?? '',
            ];
            if ($sid) {
                Training::updateSession((int)$sid, $sData);
                $keepIds[] = (int)$sid;
            } else {
                $newId = Training::addSession($trainingId, $sData);
                $keepIds[] = $newId;
            }
        }
        // Delete sessions not in keepIds
        foreach ($sessions as $s) {
            if (!in_array($s['id'], $keepIds)) {
                Training::deleteSession($s['id']);
            }
        }

        // Save approval levels
        $levelUsers = [];
        foreach ($post['approver_level'] ?? [] as $i => $lvl) {
            $uid = (int)($post['approver_user'][$i] ?? 0);
            if ($uid && $lvl) {
                $levelUsers[] = ['level' => (int)$lvl, 'user_id' => $uid];
            }
        }
        Training::setApprovalLevels($trainingId, $levelUsers);

        $_SESSION['flash_success'] = $isEdit ? 'Fortbildung gespeichert.' : 'Fortbildung angelegt.';
        header('Location: ' . APP_URL . '/manage/training/' . $trainingId . '/edit');
        exit;
    }

    // Reload after save attempt
    $sessions = $isEdit ? Training::getSessions($trainingId) : [];
    $levels   = $isEdit ? Training::getApprovalLevels($trainingId) : [];
}

// Group levels
$level1 = array_filter($levels, fn($l) => $l['level'] == 1);
$level2 = array_filter($levels, fn($l) => $l['level'] == 2);

ob_start();
?>
<div class="container">
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/manage">Verwaltung</a> &rsaquo; <?= $isEdit ? htmlspecialchars($training['title'] ?? 'Bearbeiten') : 'Neue Fortbildung' ?>
  </div>
  <h1><?= $isEdit ? 'Fortbildung bearbeiten' : 'Neue Fortbildung' ?></h1>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="form" id="training-form">

    <!-- Basic info -->
    <div class="card">
      <h2>Grunddaten</h2>
      <div class="form-group">
        <label for="title">Titel <span class="required">*</span></label>
        <input type="text" id="title" name="title" required value="<?= htmlspecialchars($data['title']) ?>">
      </div>
      <div class="form-group">
        <label for="description">Beschreibung</label>
        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($data['description']) ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="location">Veranstaltungsort</label>
          <input type="text" id="location" name="location" value="<?= htmlspecialchars($data['location']) ?>">
        </div>
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="draft"    <?= $data['status'] === 'draft'    ? 'selected' : '' ?>>Entwurf</option>
            <option value="open"     <?= $data['status'] === 'open'     ? 'selected' : '' ?>>Offen (Anmeldung möglich)</option>
            <option value="closed"   <?= $data['status'] === 'closed'   ? 'selected' : '' ?>>Geschlossen</option>
            <option value="archived" <?= $data['status'] === 'archived' ? 'selected' : '' ?>>Archiviert</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label><input type="checkbox" name="is_multi_part" <?= $data['is_multi_part'] ? 'checked' : '' ?>>
            Mehrteilige Fortbildung</label>
        </div>
      </div>
    </div>

    <!-- Capacity -->
    <div class="card">
      <h2>Kapazität</h2>
      <div class="form-row">
        <div class="form-group">
          <label for="max_participants">Maximale Teilnehmerzahl</label>
          <input type="number" id="max_participants" name="max_participants" min="1"
                 value="<?= htmlspecialchars($data['max_participants'] ?? '') ?>"
                 placeholder="Unbegrenzt">
          <small>Leer lassen für unbegrenzte Teilnehmerzahl.</small>
        </div>
        <div class="form-group">
          <label><input type="checkbox" name="waitlist_enabled" <?= $data['waitlist_enabled'] ? 'checked' : '' ?>>
            Warteliste aktivieren</label>
          <small>Bei ausgebuchter Fortbildung werden weitere Anmeldungen auf die Warteliste gesetzt.</small>
        </div>
      </div>
      <div class="form-group">
        <label for="registration_deadline">Anmeldeschluss</label>
        <input type="datetime-local" id="registration_deadline" name="registration_deadline"
               value="<?= $data['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($data['registration_deadline'])) : '' ?>">
      </div>
    </div>

    <!-- Approval mode -->
    <div class="card">
      <h2>Genehmigungsmodus</h2>
      <div class="form-group">
        <label><input type="radio" name="approval_mode" value="auto" <?= $data['approval_mode'] === 'auto' ? 'checked' : '' ?>>
          <strong>Automatisch</strong> – Anmeldungen werden sofort genehmigt</label>
      </div>
      <div class="form-group">
        <label><input type="radio" name="approval_mode" value="manual_individual" <?= $data['approval_mode'] === 'manual_individual' ? 'checked' : '' ?>>
          <strong>Einzelgenehmigung</strong> – Sie werden per E-Mail benachrichtigt und können direkt per Schaltfläche genehmigen/ablehnen</label>
      </div>
      <div class="form-group">
        <label><input type="radio" name="approval_mode" value="manual_bulk" <?= $data['approval_mode'] === 'manual_bulk' ? 'checked' : '' ?>>
          <strong>Sammelgenehmigung</strong> – Bei Ablauf der Anmeldefrist erhalten Sie eine Nachricht und können alle auf einmal genehmigen</label>
      </div>
    </div>

    <!-- Approval levels -->
    <div class="card">
      <h2>Genehmigungsebenen <small>(optional)</small></h2>
      <p class="hint">Sie können bis zu 2 Genehmigungsebenen definieren. Ebene 1 muss zuerst genehmigen, bevor Ebene 2 tätig wird.</p>

      <h3>Ebene 1</h3>
      <div id="level1-list">
        <?php foreach ($level1 as $l): ?>
          <div class="approver-row">
            <input type="hidden" name="approver_level[]" value="1">
            <select name="approver_user[]" class="select-approver">
              <option value="">– auswählen –</option>
              <?php foreach ($genehmiger as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $l['user_id'] == $g['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($g['name'] ?: $g['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-danger remove-approver">–</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-sm btn-secondary" onclick="addApprover(1)">+ Genehmiger Ebene 1 hinzufügen</button>

      <h3 style="margin-top:16px">Ebene 2</h3>
      <div id="level2-list">
        <?php foreach ($level2 as $l): ?>
          <div class="approver-row">
            <input type="hidden" name="approver_level[]" value="2">
            <select name="approver_user[]" class="select-approver">
              <option value="">– auswählen –</option>
              <?php foreach ($genehmiger as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $l['user_id'] == $g['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($g['name'] ?: $g['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-danger remove-approver">–</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-sm btn-secondary" onclick="addApprover(2)">+ Genehmiger Ebene 2 hinzufügen</button>
    </div>

    <!-- Sessions -->
    <div class="card">
      <h2>Termine</h2>
      <p class="hint">Fügen Sie einen oder mehrere Termine hinzu.</p>
      <div id="sessions-list">
        <?php foreach ($sessions as $i => $s): ?>
          <div class="session-row card-inner" data-index="<?= $i ?>">
            <div class="session-row-header">
              <strong>Termin <?= $i + 1 ?></strong>
              <button type="button" class="btn btn-sm btn-danger remove-session">Entfernen</button>
            </div>
            <input type="hidden" name="session_id[]" value="<?= $s['id'] ?>">
            <div class="form-row">
              <div class="form-group">
                <label>Titel/Thema (optional)</label>
                <input type="text" name="session_title[]" value="<?= htmlspecialchars($s['title']) ?>">
              </div>
              <div class="form-group">
                <label>Ort (optional)</label>
                <input type="text" name="session_location[]" value="<?= htmlspecialchars($s['location']) ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Beginn <span class="required">*</span></label>
                <input type="datetime-local" name="session_start[]" required
                       value="<?= date('Y-m-d\TH:i', strtotime($s['start_datetime'])) ?>">
              </div>
              <div class="form-group">
                <label>Ende <span class="required">*</span></label>
                <input type="datetime-local" name="session_end[]" required
                       value="<?= date('Y-m-d\TH:i', strtotime($s['end_datetime'])) ?>">
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-secondary" id="add-session">+ Termin hinzufügen</button>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Speichern</button>
      <a href="<?= APP_URL ?>/manage" class="btn btn-secondary">Abbrechen</a>
    </div>
  </form>
</div>

<script>
const genehmiger = <?= json_encode(array_map(fn($g) => ['id' => $g['id'], 'label' => $g['name'] ?: $g['email']], $genehmiger)) ?>;
let sessionCount = <?= count($sessions) ?>;

function autoFillEndTime(row) {
  const startEl = row.querySelector('input[name="session_start[]"]');
  const endEl   = row.querySelector('input[name="session_end[]"]');
  if (!startEl || !endEl) return;
  startEl.addEventListener('change', function () {
    if (!this.value) return;
    const start = new Date(this.value);
    if (isNaN(start)) return;
    // Only fill if end is still empty or equal to start+1h from previous start
    const expected = endEl.dataset.autoEnd || '';
    if (endEl.value === '' || endEl.value === expected) {
      start.setHours(start.getHours() + 1);
      const pad = n => String(n).padStart(2, '0');
      const val = `${start.getFullYear()}-${pad(start.getMonth()+1)}-${pad(start.getDate())}T${pad(start.getHours())}:${pad(start.getMinutes())}`;
      endEl.value = val;
      endEl.dataset.autoEnd = val;
    }
  });
}

// Wire existing session rows
document.querySelectorAll('.session-row').forEach(autoFillEndTime);

function addApprover(level) {
  const container = document.getElementById('level' + level + '-list');
  const div = document.createElement('div');
  div.className = 'approver-row';
  let opts = '<option value="">– auswählen –</option>';
  genehmiger.forEach(g => { opts += `<option value="${g.id}">${g.label}</option>`; });
  div.innerHTML = `<input type="hidden" name="approver_level[]" value="${level}">
    <select name="approver_user[]" class="select-approver">${opts}</select>
    <button type="button" class="btn btn-sm btn-danger remove-approver">–</button>`;
  container.appendChild(div);
  div.querySelector('.remove-approver').addEventListener('click', () => div.remove());
}

document.querySelectorAll('.remove-approver').forEach(btn => {
  btn.addEventListener('click', () => btn.closest('.approver-row').remove());
});

document.getElementById('add-session').addEventListener('click', () => {
  const container = document.getElementById('sessions-list');
  const idx = sessionCount++;
  const div = document.createElement('div');
  div.className = 'session-row card-inner';
  div.innerHTML = `
    <div class="session-row-header">
      <strong>Termin ${idx + 1}</strong>
      <button type="button" class="btn btn-sm btn-danger remove-session">Entfernen</button>
    </div>
    <input type="hidden" name="session_id[]" value="">
    <div class="form-row">
      <div class="form-group">
        <label>Titel/Thema (optional)</label>
        <input type="text" name="session_title[]">
      </div>
      <div class="form-group">
        <label>Ort (optional)</label>
        <input type="text" name="session_location[]">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Beginn <span class="required">*</span></label>
        <input type="datetime-local" name="session_start[]" required>
      </div>
      <div class="form-group">
        <label>Ende <span class="required">*</span></label>
        <input type="datetime-local" name="session_end[]" required>
      </div>
    </div>`;
  container.appendChild(div);
  div.querySelector('.remove-session').addEventListener('click', () => div.remove());
  autoFillEndTime(div);
});

document.querySelectorAll('.remove-session').forEach(btn => {
  btn.addEventListener('click', () => btn.closest('.session-row').remove());
});
</script>
<?php
$content   = ob_get_clean();
$pageTitle = $isEdit ? 'Fortbildung bearbeiten' : 'Neue Fortbildung';
include APP_PATH . '/templates/layout.php';
