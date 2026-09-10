/* DRIVE24 front-end behaviour - vanilla JS, no CDN */
(function () {
  var body = document.body;
  var BASE = body.getAttribute('data-base') || '';
  var CSRF = body.getAttribute('data-csrf') || '';

  function host() {
    var h = document.querySelector('.toast-host');
    if (!h) { h = document.createElement('div'); h.className = 'toast-host'; document.body.appendChild(h); }
    return h;
  }
  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'toast'; t.textContent = msg;
    host().appendChild(t);
    setTimeout(function () { t.remove(); }, 2800);
  }
  function apiUrl(p) { return BASE.replace(/\/$/, '') + '/api/' + p; }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload || {})
    }).then(function (r) { return r.json(); });
  }

  document.addEventListener('click', function (ev) {
    var wish = ev.target.closest('[data-wishlist]');
    if (wish) {
      ev.preventDefault();
      postJson(apiUrl('wishlist.php'), { listing_id: Number(wish.getAttribute('data-wishlist')) })
        .then(function (res) {
          toast(res.message || 'Done');
          if (!res.ok) { return; }
          wish.classList.toggle('on', !!res.saved);
          wish.setAttribute('aria-pressed', res.saved ? 'true' : 'false');
          document.querySelectorAll('[data-wishlist-count]').forEach(function (n) { n.textContent = res.count; });
        })
        .catch(function () { toast('Network error, please retry.'); });
      return;
    }
    var cmp = ev.target.closest('[data-compare]');
    if (cmp) {
      ev.preventDefault();
      postJson(apiUrl('compare.php'), { listing_id: Number(cmp.getAttribute('data-compare')) })
        .then(function (res) {
          toast(res.message || 'Done');
          if (!res.ok) { return; }
          cmp.classList.toggle('active', !!res.added);
          document.querySelectorAll('[data-compare-count]').forEach(function (n) { n.textContent = res.count; });
        })
        .catch(function () { toast('Network error, please retry.'); });
    }
  });

  // live EMI calculator
  function bindEmi(root) {
    var price = Number(root.getAttribute('data-emi-price') || 0);
    var down = root.querySelector('[data-emi-down]');
    var rate = root.querySelector('[data-emi-rate]');
    var tenure = root.querySelector('[data-emi-tenure]');
    var out = root.querySelector('[data-emi-out]');
    if (!out) { return; }
    function inr(n) {
      n = Math.round(n);
      var s = String(Math.abs(n)), last3 = s.slice(-3), rest = s.slice(0, -3);
      if (rest) { rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ','); s = rest + ',' + last3; }
      return '\u20B9' + s;
    }
    function calc() {
      var d = Number(down && down.value || 0);
      var r = Number(rate && rate.value || 9.5) / 1200;
      var m = Number(tenure && tenure.value || 60);
      var p = Math.max(price - d, 0);
      var emi = r > 0 ? p * r * Math.pow(1 + r, m) / (Math.pow(1 + r, m) - 1) : (m ? p / m : 0);
      out.textContent = inr(emi || 0);
      var total = root.querySelector('[data-emi-total]');
      var interest = root.querySelector('[data-emi-interest]');
      var principal = root.querySelector('[data-emi-principal]');
      if (total) { total.textContent = inr(emi * m); }
      if (interest) { interest.textContent = inr(emi * m - p); }
      if (principal) { principal.textContent = inr(p); }
      root.querySelectorAll('[data-emi-echo]').forEach(function (n) {
        var key = n.getAttribute('data-emi-echo');
        if (key === 'down') { n.textContent = inr(d); }
        if (key === 'rate') { n.textContent = (rate ? rate.value : '9.5') + '%'; }
        if (key === 'tenure') { n.textContent = m + ' months'; }
      });
    }
    [down, rate, tenure].forEach(function (el) {
      if (el) { el.addEventListener('input', calc); el.addEventListener('change', calc); }
    });
    calc();
  }
  document.querySelectorAll('[data-emi-price]').forEach(bindEmi);

  var toggle = document.querySelector('.menu-btn');
  if (toggle) {
    toggle.addEventListener('click', function () { document.querySelector('.nav').classList.toggle('open'); });
  }
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });
})();

/* SRS additions: gallery, VIN decode, chat polling, copy link */
(function () {
  var body = document.body;
  var BASE = (body.getAttribute('data-base') || '').replace(/\/$/, '');

  // photo gallery on car page
  document.querySelectorAll('[data-gal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var main = document.getElementById('galMain');
      if (main) { main.src = btn.getAttribute('data-gal'); }
      document.querySelectorAll('[data-gal]').forEach(function (b) { b.classList.remove('on'); });
      btn.classList.add('on');
    });
  });

  // copy-link buttons
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var v = btn.getAttribute('data-copy') || location.href;
      if (navigator.clipboard) { navigator.clipboard.writeText(v); }
      btn.textContent = 'Copied!';
      setTimeout(function () { btn.textContent = 'Copy link'; }, 1600);
    });
  });

  // VIN decode on sell page
  var vinBtn = document.querySelector('[data-vin-decode]');
  if (vinBtn) {
    vinBtn.addEventListener('click', function () {
      var box = vinBtn.closest('[data-vin-box]');
      var input = box ? box.querySelector('[data-vin-input]') : null;
      var out = box ? box.querySelector('[data-vin-out]') : null;
      var vin = input ? input.value.trim() : '';
      if (vin.length !== 17) { if (out) { out.textContent = 'VIN must be exactly 17 characters.'; } return; }
      fetch(BASE + '/api/vin.php?vin=' + encodeURIComponent(vin))
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.ok) { if (out) { out.textContent = res.message || 'Invalid VIN.'; } return; }
          var form = document.getElementById('listingForm');
          if (res.make && form) { var mk = form.querySelector('[data-vin-make]'); if (mk && !mk.value) { mk.value = res.make; } }
          if (res.year && form) { var yr = form.querySelector('[data-vin-year]'); if (yr && !yr.value) { yr.value = res.year; } }
          if (out) { out.textContent = 'Decoded: ' + (res.make || 'unknown make') + (res.year ? ' - ' + res.year : '') + ' (' + res.country + ').'; }
        })
        .catch(function () { if (out) { out.textContent = 'VIN service unreachable, please type details manually.'; } });
    });
  }

  // chat auto-refresh: poll for messages newer than data-last every 6s
  var log = document.getElementById('chatLog');
  if (log) {
    log.scrollTop = log.scrollHeight;
    var thread = log.getAttribute('data-thread');
    setInterval(function () {
      var last = log.getAttribute('data-last') || '0';
      fetch(BASE + '/api/chat.php?thread=' + thread + '&after=' + last, { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (res) {
          if (!res || !res.ok || !res.messages.length) { return; }
          res.messages.forEach(function (m) {
            var d = document.createElement('div');
            d.className = 'bubble';
            d.textContent = m.body;
            log.appendChild(d);
            log.setAttribute('data-last', m.id);
          });
          log.scrollTop = log.scrollHeight;
        })
        .catch(function () {});
    }, 6000);
  }
})();

/* Motion engine: scroll reveals, counters, header state, back-to-top, hero parallax */
(function () {
  document.documentElement.classList.add('js');

  // auto-reveal animated entrances across all pages
  var auto = document.querySelectorAll('.car-card, .kpi, .grid.four > *, .spec-grid > div, .chat-row');
  auto.forEach(function (el) { el.classList.add('reveal'); });

  // stagger siblings so grids cascade nicely
  document.querySelectorAll('.grid, .kpis, .spec-grid, .hero-stats').forEach(function (parent) {
    var kids = parent.querySelectorAll(':scope > .reveal');
    kids.forEach(function (k, i) {
      if (!k.hasAttribute('data-delay')) { k.style.transitionDelay = Math.min(i * 70, 560) + 'ms'; }
    });
  });

  // reveal on scroll
  var revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('visible'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('visible'); });
  }

  // animated counters with Indian digit grouping
  function inr(n) {
    n = Math.round(n);
    var s = String(Math.abs(n));
    if (s.length > 3) {
      var last3 = s.slice(-3), rest = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');
      s = rest + ',' + last3;
    }
    return (n < 0 ? '-' : '') + s;
  }
  var counters = document.querySelectorAll('[data-count]');
  function runCounter(el) {
    var target = Number(el.getAttribute('data-count') || 0);
    var dur = 1400, t0 = null;
    function tick(t) {
      if (!t0) { t0 = t; }
      var p = Math.min((t - t0) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = inr(target * eased);
      if (p < 1) { requestAnimationFrame(tick); }
    }
    requestAnimationFrame(tick);
  }
  if ('IntersectionObserver' in window && counters.length) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { runCounter(en.target); cio.unobserve(en.target); }
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { cio.observe(el); });
  } else {
    counters.forEach(runCounter);
  }

  // header shadow + back-to-top on scroll
  var head = document.querySelector('.site-head');
  var toTop = document.createElement('button');
  toTop.id = 'toTop';
  toTop.type = 'button';
  toTop.title = 'Back to top';
  toTop.setAttribute('aria-label', 'Back to top');
  toTop.textContent = '\u2191';
  document.body.appendChild(toTop);
  toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  function onScroll() {
    var y = window.scrollY || 0;
    if (head) { head.classList.toggle('scrolled', y > 12); }
    toTop.classList.toggle('show', y > 600);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // subtle hero parallax on mouse move
  var hero = document.querySelector('.hero');
  if (hero && window.matchMedia('(pointer:fine)').matches) {
    var layers = hero.querySelectorAll('[data-depth]');
    hero.addEventListener('mousemove', function (ev) {
      var r = hero.getBoundingClientRect();
      var x = (ev.clientX - r.left) / r.width - 0.5;
      var y = (ev.clientY - r.top) / r.height - 0.5;
      layers.forEach(function (l) {
        var d = Number(l.getAttribute('data-depth') || 10);
        l.style.translate = (-x * d) + 'px ' + (-y * d) + 'px';
      });
    });
  }

  // gallery crossfade
  document.querySelectorAll('[data-gal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var main = document.getElementById('galMain');
      if (main) { main.style.animation = 'none'; void main.offsetWidth; main.style.animation = ''; }
    });
  });
})();
