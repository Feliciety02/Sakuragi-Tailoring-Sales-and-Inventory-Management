(function() {
  'use strict';

  const main = document.querySelector('.dash-main');
  if (!main) return;

  const sharedScripts = ['/public/assets/js/sidebar.js', '/public/assets/js/ajax-nav.js'];

  let isLoading = false;

  // ── Intercept sidebar link clicks ──
  document.addEventListener('click', (e) => {
    const link = e.target.closest('.sidebar-item, .sidebar-top-item');
    if (!link || !link.href) return;

    if (link.href.includes('logout') || link.host !== window.location.host || link.hash) return;
    if (link.href === window.location.href) return;

    e.preventDefault();
    navigateTo(link.href);
  });

  // ── Navigation function ──
  window.navigateTo = function(url) {
    if (isLoading) return;
    isLoading = true;

    updateActiveState(url);

    main.style.opacity = '0.4';
    main.style.transition = 'opacity 0.15s ease';
    main.style.pointerEvents = 'none';

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => {
        if (!r.ok) throw new Error('Page load failed');
        return r.text();
      })
      .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const newMain = doc.querySelector('.dash-main');
        if (!newMain) {
          window.location.href = url;
          return;
        }

        document.title = doc.title || document.title;

        // ── Transfer stylesheets from fetched page ──
        const currentLinks = new Set();
        document.querySelectorAll('link[rel="stylesheet"]').forEach(el => {
          currentLinks.add(el.href);
        });
        doc.querySelectorAll('link[rel="stylesheet"]').forEach(el => {
          if (!currentLinks.has(el.href)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = el.href;
            document.head.appendChild(link);
          }
        });
        // Transfer inline <style> blocks that have an id (page-specific)
        doc.querySelectorAll('style[id]').forEach(el => {
          if (!document.getElementById(el.id)) {
            document.head.appendChild(el.cloneNode(true));
          }
        });

        // Remove shared scripts before inserting
        const temp = document.createElement('div');
        temp.innerHTML = newMain.innerHTML;
        temp.querySelectorAll('script').forEach(s => {
          if (s.src && sharedScripts.some(ss => s.src.includes(ss))) {
            s.remove();
          }
        });

        main.innerHTML = temp.innerHTML;

        // Re-execute remaining inline scripts
        main.querySelectorAll('script').forEach(oldScript => {
          const newScript = document.createElement('script');
          if (oldScript.src) {
            newScript.src = oldScript.src;
            newScript.async = false;
          } else {
            newScript.textContent = oldScript.textContent;
          }
          document.body.appendChild(newScript);
          document.body.removeChild(newScript);
        });

        window.history.pushState({ url }, '', url);
        updateActiveState(url);

        main.style.opacity = '1';
        main.style.pointerEvents = '';
        isLoading = false;
      })
      .catch(() => {
        window.location.href = url;
      });
  };

  // ── Update sidebar active state ──
  function updateActiveState(url) {
    const current = url.split('/').pop().split('?')[0];

    document.querySelectorAll('.sidebar-item, .sidebar-top-item').forEach(item => {
      item.classList.remove('active');
      const href = item.getAttribute('href');
      if (href && href.split('/').pop() === current) {
        item.classList.add('active');
        const group = item.closest('.nav-group');
        if (group) group.classList.add('expanded');
      }
    });
  }

  // ── Handle browser back/forward ──
  window.addEventListener('popstate', (e) => {
    if (e.state && e.state.url) {
      navigateTo(e.state.url);
    }
  });
})();
