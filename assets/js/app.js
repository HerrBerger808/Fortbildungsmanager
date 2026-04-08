/* Fortbildungsmanager – App JS */

document.addEventListener('DOMContentLoaded', () => {

  // Auto-dismiss flash alerts after 6 seconds
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 500);
    }, 6000);
  });

  // Confirm dangerous actions
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // Registration form: prevent double submit
  document.querySelectorAll('form.no-double-submit').forEach(form => {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.textContent = 'Bitte warten…'; }
    });
  });

  // Approval mode hint
  const modeRadios = document.querySelectorAll('input[name=approval_mode]');
  const deadlineGroup = document.querySelector('#registration_deadline')?.closest('.form-group');
  function updateModeHint() {
    const selected = document.querySelector('input[name=approval_mode]:checked')?.value;
    if (!deadlineGroup) return;
    if (selected === 'manual_bulk') {
      deadlineGroup.querySelector('label').style.fontWeight = '700';
    } else {
      if (deadlineGroup.querySelector('label')) deadlineGroup.querySelector('label').style.fontWeight = '';
    }
  }
  modeRadios.forEach(r => r.addEventListener('change', updateModeHint));
  updateModeHint();

  // Max participants & waitlist coupling hint
  const maxInput = document.getElementById('max_participants');
  const waitlistCb = document.querySelector('input[name=waitlist_enabled]');
  if (maxInput && waitlistCb) {
    function updateWaitlistHint() {
      const small = waitlistCb.closest('.form-group')?.querySelector('small');
      if (!small) return;
      if (!maxInput.value) {
        waitlistCb.disabled = true;
        small.textContent = 'Warteliste ist nur bei begrenzter Teilnehmerzahl relevant.';
      } else {
        waitlistCb.disabled = false;
        small.textContent = 'Bei ausgebuchter Fortbildung werden weitere Anmeldungen auf die Warteliste gesetzt.';
      }
    }
    maxInput.addEventListener('input', updateWaitlistHint);
    updateWaitlistHint();
  }

  // Approve/reject mutual exclusion checkboxes per row
  document.querySelectorAll('.approve-cb, .reject-cb').forEach(cb => {
    cb.addEventListener('change', () => {
      const row   = cb.closest('tr');
      if (!row) return;
      const other = cb.classList.contains('approve-cb')
        ? row.querySelector('.reject-cb')
        : row.querySelector('.approve-cb');
      if (other && cb.checked) other.checked = false;
    });
  });
});
