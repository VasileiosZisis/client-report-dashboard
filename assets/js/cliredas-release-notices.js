(function () {
  function ready() {
    function openChangelog() {
      if (window.location.hash !== '#cliredas-changelog') return;
      const notes = document.getElementById('cliredas-changelog');
      if (!notes) return;
      notes.open = true;
      notes.scrollIntoView({ block: 'start' });
      const summary = notes.querySelector('summary');
      if (summary) summary.focus({ preventScroll: true });
    }

    openChangelog();
    window.addEventListener('hashchange', openChangelog);

    try {
      const url = new URL(window.location.href);
      if (url.searchParams.has('cliredas_release_dismiss_error')) {
        url.searchParams.delete('cliredas_release_dismiss_error');
        url.searchParams.delete('cliredas_release_error_nonce');
        window.history.replaceState({}, document.title, url.toString());
      }
    } catch (error) {}

    const notice = document.getElementById('cliredas-release-notice');
    const config = window.cliredasReleaseNotice;
    if (!notice) return;
    const form = notice.querySelector('.cliredas-release-dismiss-form');
    const feedback = notice.querySelector('.cliredas-release-error');
    if (!form || !feedback) return;
    const canAjax = config && typeof window.fetch === 'function';
    let loading = false;

    function dismiss() {
      if (loading) return;
      if (!canAjax) {
        form.submit();
        return;
      }
      loading = true;
      feedback.hidden = true;
      notice.setAttribute('aria-busy', 'true');
      notice.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
      fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: new FormData(form),
      }).then(function (response) {
        if (!response.ok) throw new Error('Dismissal failed');
        return response.json();
      }).then(function (result) {
        if (!result || result.success !== true) throw new Error('Dismissal failed');
        notice.remove();
      }).catch(function () {
        feedback.textContent = config.errorMessage;
        feedback.hidden = false;
      }).finally(function () {
        loading = false;
        notice.removeAttribute('aria-busy');
        notice.querySelectorAll('button').forEach(function (button) { button.disabled = false; });
      });
    }

    if (canAjax) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        dismiss();
      });
    }
    // Stop WordPress from removing its close-icon notice before persistence succeeds.
    notice.addEventListener('click', function (event) {
      if (!event.target.closest('.notice-dismiss')) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      dismiss();
    }, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready);
  } else {
    ready();
  }
})();
