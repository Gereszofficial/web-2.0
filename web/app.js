// BondiCars – luxus scroll + fullpage snap + középre igazítás (CSS-hez nem nyúlunk)
document.addEventListener('DOMContentLoaded', () => {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // ===== Year
  const year = $('#year');
  if (year) year.textContent = new Date().getFullYear();

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ===== Lenis (smooth)
  let lenis = null;
  if (window.Lenis && !prefersReduced) {
    lenis = new Lenis({
      duration: 1.2,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      smoothWheel: true,
      smoothTouch: false
    });
    function raf(time) { lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);
    window.lenis = lenis;
  }

  // ===== Parallax (hero)
  const grad = $('.hero-grad');
  const noise = $('.hero-noise');
  const onScrollParallax = () => {
    const y = window.scrollY || window.pageYOffset || 0;
    const p = y * 0.12;
    if (grad) grad.style.transform = `translateY(${p}px)`;
    if (noise) noise.style.transform = `translateY(${p * 0.6}px)`;
  };
  if (lenis) lenis.on('scroll', onScrollParallax);
  else window.addEventListener('scroll', onScrollParallax, { passive: true });

  // ===== Parallax (services háttér finom elmozdítás)
  const secWhy = $('.section-why');
  const updateWhy = () => {
    if (!secWhy) return;
    const r = secWhy.getBoundingClientRect();
    const vh = window.innerHeight || 0;
    const t = r.top / vh;
    const y = Math.max(-40, Math.min(40, t * -30));
    secWhy.style.setProperty('--parallax-y', `${y}px`);
  };
  if (lenis) lenis.on('scroll', updateWhy);
  else window.addEventListener('scroll', updateWhy, { passive: true });

  // ===== Navbar .scrolled
  const navbar = $('header.navbar');
  const onScrollNav = () => {
    if (!navbar) return;
    if ((window.scrollY || 0) > 50) navbar.classList.add('scrolled');
    else navbar.classList.remove('scrolled');
  };
  if (lenis) lenis.on('scroll', onScrollNav);
  else window.addEventListener('scroll', onScrollNav, { passive: true });

  // ===== Scroll spy (active link + dinamikus title)
  const navLinks = $$('.nav-link');
  const navSections = navLinks.map(l => $(l.getAttribute('href'))).filter(Boolean);
  const spy = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = '#' + entry.target.id;
        navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === id));
        const titleEl = entry.target.querySelector('.title, .hero-title');
        document.title = 'BondiCars – ' + (titleEl ? titleEl.textContent.trim() : 'Prémium élmény');
      }
    });
  }, { rootMargin: '-45% 0px -45% 0px', threshold: 0.01 });
  navSections.forEach(sec => spy.observe(sec));

  // ===== Reveal observer (alap)
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('is-visible');
        revealObserver.unobserve(e.target);
      }
    });
  }, { threshold: 0.18 });
  $$('.reveal').forEach(el => revealObserver.observe(el));

  const sections = $$('#root .section');
  if (!sections.length) return;

  // minden szekció legalább viewport magas
  const fitSections = () => {
    const h = window.innerHeight;
    sections.forEach((sec) => { sec.style.minHeight = h + 'px'; });
  };
  fitSections();
  window.addEventListener('resize', fitSections);

  // kiszámoljuk azt az Y-t, ahol a SZEKCIÓ KÖZEPE = VIEWPORT KÖZEPE
  const centerYFor = (el) => {
    const rectTop = el.getBoundingClientRect().top + (window.pageYOffset || 0);
    const vh = window.innerHeight || 0;
    const h = el.offsetHeight || vh;
    return Math.max(0, Math.round(rectTop - (vh - h) / 2));
  };

  // aktuális index
  const getCurrentIndex = () => {
    const centerY = (window.scrollY || 0) + (window.innerHeight || 0) / 2;
    let best = 0, bestDist = Infinity;
    for (let i = 0; i < sections.length; i++) {
      const top = sections[i].offsetTop;
      const mid = top + (sections[i].offsetHeight || 0) / 2;
      const dist = Math.abs(centerY - mid);
      if (dist < bestDist) { bestDist = dist; best = i; }
    }
    return best;
  };

  let snapping = false;
  let manualScroll = false;
  let current = getCurrentIndex();

  const scrollToIndex = (i) => {
    if (i < 0 || i >= sections.length) return;
    if (snapping) return;
    snapping = true;
    current = i;

    const target = sections[i];
    const reveals = $$('.reveal', target);
    reveals.forEach((el) => el.classList.remove('is-visible'));

    const y = centerYFor(target);

    if (lenis) {
      lenis.scrollTo(y, { duration: 1.15, easing: (t) => 1 - Math.pow(1 - t, 3) });
    } else {
      window.scrollTo({ top: y, behavior: prefersReduced ? 'auto' : 'smooth' });
    }

    setTimeout(() => {
      reveals.forEach((el, j) => setTimeout(() => el.classList.add('is-visible'), j * 80));
      setTimeout(() => { snapping = false; }, 250);
    }, 700);
  };

  // ===== Görgő / trackpad
  let wheelLock = false;
  let lastSnapAt = 0;
  const WHEEL_THRESHOLD = 12;
  const SNAP_GUARD_MS = 350;

  const onWheel = (e) => {
    const now = performance.now();
    const dy = e.deltaY || 0;

    if (Math.abs(dy) < WHEEL_THRESHOLD || (now - lastSnapAt) < SNAP_GUARD_MS) return;

    e.preventDefault();
    if (snapping || wheelLock) return;

    wheelLock = true;
    const dir = dy > 0 ? 1 : -1;
    current = getCurrentIndex();
    scrollToIndex(current + dir);

    lastSnapAt = now;
    setTimeout(() => (wheelLock = false), 320);
  };
  window.addEventListener('wheel', onWheel, { passive: false });

  // ===== Billentyűk
  window.addEventListener('keydown', (e) => {
    if (snapping) return;
    const nextKeys = ['PageDown', ' ', 'ArrowDown'];
    const prevKeys = ['PageUp', 'ArrowUp'];
    if (nextKeys.includes(e.key)) {
      e.preventDefault();
      current = getCurrentIndex();
      scrollToIndex(current + 1);
    } else if (prevKeys.includes(e.key)) {
      e.preventDefault();
      current = getCurrentIndex();
      scrollToIndex(current - 1);
    }
  });

  // ===== Touch (mobil)
  let touchStartY = 0;
  window.addEventListener('touchstart', (e) => {
    if (!e.touches[0]) return;
    touchStartY = e.touches[0].clientY;
  }, { passive: true });
  window.addEventListener('touchend', (e) => {
    if (snapping) return;
    const endY = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0].clientY : 0;
    const delta = touchStartY - endY;
    if (Math.abs(delta) < 30) return;
    current = getCurrentIndex();
    scrollToIndex(current + (delta > 0 ? 1 : -1));
  }, { passive: true });

  // ===== CTA (hero)
  const nextCta = $('.hero-cta [data-scroll-to]');
  if (nextCta) {
    nextCta.addEventListener('click', (e) => {
      e.preventDefault();
      manualScroll = true;
      current = getCurrentIndex();
      scrollToIndex(current + 1);
      setTimeout(() => { manualScroll = false; }, 1500);
    });
  }

  // ===== Navbar linkek
  const clickToIndex = (targetHash) =>
    sections.findIndex(sec => '#' + sec.id === targetHash);

  const navClick = (href) => {
    const targetIndex = clickToIndex(href);
    if (targetIndex >= 0) {
      manualScroll = true;
      scrollToIndex(targetIndex);
      setTimeout(() => { manualScroll = false; }, 1500);
    }
  };

  $$('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (href && href.startsWith('#')) {
        e.preventDefault();
        navClick(href);
      }
    });
  });

  // ===== Snap igazítás
  let snapTimeout;
  const onScrollSnapAlign = () => {
    if (snapping || manualScroll) return;
    clearTimeout(snapTimeout);
    snapTimeout = setTimeout(() => {
      const idx = getCurrentIndex();
      const y = window.scrollY || 0;
      const targetY = centerYFor(sections[idx]);
      const nearCenter = Math.abs(y - targetY) < (window.innerHeight * 0.18);
      if (!nearCenter) scrollToIndex(idx);
    }, 110);
  };
  if (lenis) lenis.on('scroll', onScrollSnapAlign);
  else window.addEventListener('scroll', onScrollSnapAlign, { passive: true });

  const centerGrids = () => {
    ['.glass-grid', '.grid-3'].forEach(sel => {
      $$(sel).forEach(grid => {
        grid.style.justifyItems = 'center';
      });
    });
    $$('.grid-3 .card, .glass-grid .glass').forEach(item => {
      item.style.width = '100%';
      item.style.maxWidth = '340px';
    });
  };
  centerGrids();
});

// Intro
requestAnimationFrame(() => { document.body.classList.add('is-ready'); });

// ==== AUTÓINK – dinamikus kártyák + prémium lightbox ====
document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('#work .gallery');
  if (!grid) return;

  const LIMIT = 6; // ennyi kártyát mutatunk elsőre

  // Lightbox felépítése (változatlan)
  function openLightbox(car, startIndex = 0) {
    let i = startIndex;

    const lb = document.createElement('div');
    lb.className = 'lightbox';
    lb.innerHTML = `
      <div class="frame">
        <button class="close" aria-label="Bezárás">✕</button>
        <div class="viewer">
          <button class="nav prev" aria-label="Előző">‹</button>
          <img class="img" alt="">
          <button class="nav next" aria-label="Következő">›</button>
        </div>
        <div class="meta">
          <h3>${car.title || ''}</h3>
          <p class="muted">${car.subtitle || ''}</p>
        </div>
        <div class="thumbs"></div>
      </div>
    `;

    const img = lb.querySelector('.img');
    const thumbs = lb.querySelector('.thumbs');

    function render() {
      const item = car.gallery[i];
      img.style.opacity = 0;
      img.onload = () => (img.style.opacity = 1);
      img.src = item.src;

      thumbs.innerHTML = '';
      car.gallery.forEach((g, idx) => {
        const t = document.createElement('img');
        t.src = g.src;
        t.className = idx === i ? 'active' : '';
        t.addEventListener('click', () => { i = idx; render(); });
        thumbs.appendChild(t);
      });
    }

    lb.querySelector('.prev').onclick = () => { i = (i - 1 + car.gallery.length) % car.gallery.length; render(); };
    lb.querySelector('.next').onclick = () => { i = (i + 1) % car.gallery.length; render(); };
    lb.querySelector('.close').onclick = () => document.body.removeChild(lb);

    lb.addEventListener('click', (e) => { if (e.target === lb) document.body.removeChild(lb); });
    window.addEventListener('keydown', onKey);
    function onKey(e){
      if (e.key === 'Escape'){ try{document.body.removeChild(lb);}catch{}; window.removeEventListener('keydown', onKey); }
      if (e.key === 'ArrowLeft'){ lb.querySelector('.prev').click(); }
      if (e.key === 'ArrowRight'){ lb.querySelector('.next').click(); }
    }

    document.body.appendChild(lb);
    requestAnimationFrame(() => lb.classList.add('open')); // kis animáció
    render();
  }

  // Összes autó overlay (cover fallbackkel)
  function openAll(cars) {
    const lb = document.createElement('div');
    lb.className = 'lightbox';
    lb.innerHTML = `
      <div class="frame allcars">
        <button class="close" aria-label="Bezárás">✕</button>
        <h3 style="margin:6px 0 10px 0">Összes autó</h3>
        <div class="all-grid"></div>
      </div>
    `;
    const gridAll = lb.querySelector('.all-grid');
    gridAll.style.display = 'grid';
    gridAll.style.gridTemplateColumns = 'repeat(auto-fit, minmax(180px, 1fr))';
    gridAll.style.gap = '12px';

    cars.forEach((car) => {
      const coverUrl = car.cover || `images/cars/${car.id}/cover.jpg`;
      const card = document.createElement('button');
      card.className = 'car-mini';
      card.style.height = '140px';
      card.style.border = '1px solid var(--border)';
      card.style.borderRadius = '14px';
      card.style.background = `#0f1624 url('${coverUrl}') center/cover no-repeat`;
      card.title = car.title;
      card.onclick = () => { document.body.removeChild(lb); openLightbox(car, 0); };
      gridAll.appendChild(card);
    });

    lb.querySelector('.close').onclick = () => document.body.removeChild(lb);
    lb.addEventListener('click', (e) => { if (e.target === lb) document.body.removeChild(lb); });
    document.body.appendChild(lb);
    requestAnimationFrame(() => lb.classList.add('open'));
  }

  // Betöltés a szerverről (PHP → JSON)
  const sources = ['cars.php?ts=' + Date.now(), 'cars.json?ts=' + Date.now()];
  (async () => {
    let cars = [];
    for (const url of sources) {
      try {
        const r = await fetch(url, { cache: 'no-store' }); // ne cache-eljen
        if (r.ok) { cars = await r.json(); if (Array.isArray(cars) && cars.length) break; }
      } catch (e) {}
    }

    // Kompatibilitási normalizálás az adminhoz
    cars = (cars || []).map(car => {
      const id = car.id || '';
      const cover = car.cover || (id ? `images/cars/${id}/cover.jpg` : '');
      let gallery = Array.isArray(car.gallery) ? car.gallery : [];
      if (!gallery.length && cover) {
        gallery = [{ src: cover }]; // legalább a borító lapozható legyen
      }
      return { ...car, id, cover, gallery };
    });

    // Rajzolás
    grid.innerHTML = '';
    cars.slice(0, LIMIT).forEach((car) => {
      const coverUrl = car.cover || `images/cars/${car.id}/cover.jpg`;
      const card = document.createElement('button');
      card.className = 'gal car-card';
      card.style.background = `#0f1624 url('${coverUrl}') center/cover no-repeat`;
      card.title = car.title || '';
      card.onclick = () => openLightbox(car, 0);
      grid.appendChild(card);
    });

    if (cars.length > LIMIT) {
      const more = document.createElement('a');
      more.href = '#';
      more.className = 'gal';
      more.style.display = 'grid';
      more.style.placeItems = 'center';
      more.style.fontWeight = '800';
      more.textContent = 'Összes autó';
      more.onclick = (e) => { e.preventDefault(); openAll(cars); };
      grid.appendChild(more);
    }
  })();
});
