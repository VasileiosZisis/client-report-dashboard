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

  onReady(function () {
    removeQueryParams([
      'cliredas_ga4_notice',
      'cliredas_ga4_error',
      'cliredas_ga4_error_desc',
      'cliredas_ga4_notice_nonce',
      'cliredas_cache_cleared',
      'cliredas_cache_cleared_nonce',
    ]);
  });
})();
