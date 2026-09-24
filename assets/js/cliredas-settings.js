(function () {
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  function removeQueryParams(params) {
    try {
      const url = new URL(window.location.href);
      let changed = false;

      params.forEach(function (key) {
        if (url.searchParams.has(key)) {
          url.searchParams.delete(key);
          changed = true;
        }
      });

      if (changed) {
        window.history.replaceState({}, document.title, url.toString());
      }
    } catch (e) {}
  }

  function copyText(text) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(text);
    }

    return new Promise(function (resolve, reject) {
      const input = document.createElement('textarea');
      input.value = text;
      input.setAttribute('readonly', 'readonly');
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.appendChild(input);
      input.select();

      try {
        if (!document.execCommand('copy')) {
          throw new Error('Copy failed');
        }
        resolve();
      } catch (error) {
        reject(error);
      } finally {
        document.body.removeChild(input);
      }
    });
  }

  onReady(function () {
    removeQueryParams([
      'cliredas_ga4_notice',
      'cliredas_ga4_error',
      'cliredas_ga4_error_desc',
      'cliredas_ga4_notice_nonce',
      'cliredas_cache_cleared',
      'cliredas_cache_cleared_nonce',
      'cliredas_diagnostics',
      'cliredas_diagnostics_nonce',
    ]);

    document.querySelectorAll('.cliredas-copy-button').forEach(function (button) {
      button.addEventListener('click', function () {
        const targetId = button.getAttribute('data-copy-target');
        const target = targetId ? document.getElementById(targetId) : null;
        const status = button.parentElement
          ? button.parentElement.querySelector('.cliredas-copy-status')
          : null;

        if (!target) return;

        copyText(target.textContent || '')
          .then(function () {
            if (status) {
              status.textContent = button.getAttribute('data-copied-label') || 'Copied';
            }
          })
          .catch(function () {
            if (status) {
              status.textContent = '';
            }
          });
      });
    });

    document.querySelectorAll('.cliredas-diagnostics-form').forEach(function (form) {
      form.addEventListener('submit', function () {
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;

        button.disabled = true;
        button.textContent = button.getAttribute('data-running-label') || button.textContent;
      });
    });
  });
})();
