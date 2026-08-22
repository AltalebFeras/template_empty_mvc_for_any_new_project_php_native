/**
 * MAIN.JS — Global frontend script
 * Handles navigation, mobile menu, scroll effects, and alert dismissals.
 */
document.addEventListener('DOMContentLoaded', () => {
  // --- Mobile Navbar Toggle with Outside Click & Link Click Handlers ---
  const toggle = document.getElementById('nav-toggle');
  const links  = document.getElementById('nav-links');
  const header = document.getElementById('site-header');

  if (toggle && links) {
    function closeMenu() {
      links.classList.remove('open');
      toggle.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }

    function toggleMenu() {
      const isOpen = links.classList.toggle('open');
      toggle.classList.toggle('open', isOpen);
      toggle.setAttribute('aria-expanded', String(isOpen));
    }

    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleMenu();
    });

    // Close when clicking outside of navbar & links
    document.addEventListener('click', (e) => {
      if (links.classList.contains('open') && !links.contains(e.target) && !toggle.contains(e.target)) {
        closeMenu();
      }
    });

    // Close when clicking any nav link
    links.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        closeMenu();
      });
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && links.classList.contains('open')) {
        closeMenu();
      }
    });
  }

  // --- Scroll Effect on Header ---
  if (header) {
    const handleScroll = () => {
      header.classList.toggle('scrolled', window.scrollY > 10);
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
  }

  // --- Subnavigation Scrollspy & Auto-scroll active pill ---
  const subnav = document.querySelector('.tarifs-subnav-bar');
  if (subnav) {
    const subnavWrapper = subnav.querySelector('.tarifs-subnav-wrapper');
    const scrollContainer = subnav.querySelector('.tarifs-subnav-scroll');
    const pills = subnav.querySelectorAll('.tarifs-subnav-pill');
    const prevBtn = subnav.querySelector('.subnav-arrow-prev');
    const nextBtn = subnav.querySelector('.subnav-arrow-next');
    const sectionPairs = [];

    pills.forEach(pill => {
      const href = pill.getAttribute('href');
      if (href && href.startsWith('#')) {
        const sec = document.querySelector(href);
        if (sec) {
          sectionPairs.push({ pill, sec, id: href });
        }
      }
    });

    // Update gradient fade indicators & arrow opacities
    function updateScrollIndicators() {
      if (!scrollContainer || !subnavWrapper) return;
      const scrollLeft = scrollContainer.scrollLeft;
      const maxScroll = Math.max(0, scrollContainer.scrollWidth - scrollContainer.clientWidth);

      const canLeft = scrollLeft > 8;
      const canRight = (maxScroll - scrollLeft) > 8;

      subnavWrapper.classList.toggle('can-scroll-left', canLeft);
      subnavWrapper.classList.toggle('can-scroll-right', canRight);

      if (prevBtn) {
        prevBtn.style.opacity = canLeft ? '1' : '0.25';
        prevBtn.style.pointerEvents = canLeft ? 'auto' : 'none';
      }
      if (nextBtn) {
        nextBtn.style.opacity = canRight ? '1' : '0.25';
        nextBtn.style.pointerEvents = canRight ? 'auto' : 'none';
      }
    }

    function setActivePill(targetId) {
      let activePill = null;
      sectionPairs.forEach(({ pill, id }) => {
        const isMatch = (id === targetId);
        pill.classList.toggle('active', isMatch);
        if (isMatch) activePill = pill;
      });

      if (activePill && scrollContainer) {
        const containerLeft = scrollContainer.getBoundingClientRect().left;
        const pillLeft = activePill.getBoundingClientRect().left;
        const scrollOffset = pillLeft - containerLeft - (scrollContainer.clientWidth / 2) + (activePill.clientWidth / 2);
        scrollContainer.scrollBy({ left: scrollOffset, behavior: 'smooth' });
        setTimeout(updateScrollIndicators, 300);
      }
    }

    // Scroll arrows click listeners
    if (prevBtn && scrollContainer) {
      prevBtn.addEventListener('click', () => {
        scrollContainer.scrollBy({ left: -160, behavior: 'smooth' });
      });
    }
    if (nextBtn && scrollContainer) {
      nextBtn.addEventListener('click', () => {
        scrollContainer.scrollBy({ left: 160, behavior: 'smooth' });
      });
    }

    if (scrollContainer) {
      scrollContainer.addEventListener('scroll', updateScrollIndicators, { passive: true });
      window.addEventListener('resize', updateScrollIndicators, { passive: true });
      setTimeout(updateScrollIndicators, 150);
    }

    // --- Precise Scrollspy (Scroll UP & DOWN) ---
    let isClickScrolling = false;
    let clickTimeout = null;

    function getTopOffset() {
      const headerEl = document.getElementById('site-header');
      const headerH = headerEl ? headerEl.offsetHeight : 70;
      const subnavH = subnav ? subnav.offsetHeight : 50;
      return headerH + subnavH + 30;
    }

    function calculateActiveSection() {
      if (isClickScrolling || sectionPairs.length === 0) return;

      const scrollPosition = window.scrollY;
      const topOffset = getTopOffset();
      const documentHeight = document.documentElement.scrollHeight;
      const viewportHeight = window.innerHeight;

      // If reached near bottom of page, activate the last pill
      if (scrollPosition + viewportHeight >= documentHeight - 50) {
        setActivePill(sectionPairs[sectionPairs.length - 1].id);
        return;
      }

      // Find the active section based on current scroll position
      let activeId = sectionPairs[0].id;
      for (let i = 0; i < sectionPairs.length; i++) {
        const sec = sectionPairs[i].sec;
        const rect = sec.getBoundingClientRect();
        const secTopFromDoc = rect.top + scrollPosition;
        if (scrollPosition + topOffset >= secTopFromDoc) {
          activeId = sectionPairs[i].id;
        }
      }

      setActivePill(activeId);
    }

    let scrollTicking = false;
    window.addEventListener('scroll', () => {
      if (!scrollTicking) {
        window.requestAnimationFrame(() => {
          calculateActiveSection();
          scrollTicking = false;
        });
        scrollTicking = true;
      }
    }, { passive: true });

    // Initial calculation on load & resize
    window.addEventListener('resize', calculateActiveSection, { passive: true });
    setTimeout(calculateActiveSection, 150);

    // Hash on load
    if (window.location.hash) {
      setTimeout(() => {
        setActivePill(window.location.hash);
      }, 100);
    }

    // Click handler on pills
    pills.forEach(pill => {
      pill.addEventListener('click', (e) => {
        const href = pill.getAttribute('href');
        if (href && href.startsWith('#')) {
          const targetSec = document.querySelector(href);
          if (targetSec) {
            e.preventDefault();
            isClickScrolling = true;
            setActivePill(href);
            clearTimeout(clickTimeout);

            const targetTop = targetSec.getBoundingClientRect().top + window.scrollY - getTopOffset() + 15;
            window.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });

            clickTimeout = setTimeout(() => {
              isClickScrolling = false;
            }, 750);
          }
        }
      });
    });
  }

  // ============================================================
  // Floating Toast Notification System (10s auto-hide, progress & close)
  // ============================================================
  const MAX_ACTIVE_TOASTS = 2;

  function setupToast(toast) {
    if (!toast || toast.dataset.toastInit === 'true') return;
    toast.dataset.toastInit = 'true';

    let duration = parseInt(toast.getAttribute('data-auto-dismiss'), 10) || 10000;
    let timer = null;
    let startTime = Date.now();
    let remaining = duration;
    let isPaused = false;
    const progressBar = toast.querySelector('.toast-progress-bar');

    function dismiss() {
      if (toast.classList.contains('toast-hiding')) return;
      toast.classList.add('toast-hiding');
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 320);
    }

    function startTimer() {
      if (remaining <= 0) {
        dismiss();
        return;
      }
      isPaused = false;
      toast.classList.remove('toast-paused');
      if (progressBar) progressBar.style.animationPlayState = 'running';
      startTime = Date.now();
      timer = setTimeout(dismiss, remaining);
    }

    function pauseTimer() {
      if (isPaused) return;
      isPaused = true;
      toast.classList.add('toast-paused');
      if (progressBar) progressBar.style.animationPlayState = 'paused';
      clearTimeout(timer);
      const elapsed = Date.now() - startTime;
      remaining = Math.max(0, remaining - elapsed);
    }

    // Expose reset method for deduplication
    toast._resetTimer = function(newDuration = 10000) {
      clearTimeout(timer);
      duration = newDuration;
      remaining = newDuration;
      isPaused = false;
      toast.classList.remove('toast-paused', 'toast-hiding');
      if (progressBar) {
        progressBar.style.animation = 'none';
        void progressBar.offsetWidth; // trigger reflow
        progressBar.style.animation = `toastProgress ${newDuration}ms linear forwards`;
      }
      startTimer();
    };

    startTimer();

    // Pause on Mouse Enter & Pointer Enter
    toast.addEventListener('mouseenter', pauseTimer);
    toast.addEventListener('pointerenter', pauseTimer);

    // Resume on Mouse Leave & Pointer Leave
    toast.addEventListener('mouseleave', () => {
      if (remaining > 0) startTimer();
    });
    toast.addEventListener('pointerleave', () => {
      if (remaining > 0) startTimer();
    });

    // Touch support (Mobile / Tablet)
    toast.addEventListener('touchstart', pauseTimer, { passive: true });
    toast.addEventListener('touchend', () => {
      if (remaining > 0) startTimer();
    }, { passive: true });
    toast.addEventListener('touchcancel', () => {
      if (remaining > 0) startTimer();
    }, { passive: true });

    // Close button
    const closeBtn = toast.querySelector('.toast-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        clearTimeout(timer);
        dismiss();
      });
    }
  }

  // Initialize existing toasts
  document.querySelectorAll('.toast').forEach(setupToast);

  // Global window.showToast API (With deduplication & max 2 limit)
  window.showToast = function(message, type = 'success', duration = 10000, title = '') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'true');
      document.body.appendChild(container);
    }

    const cleanMsg = (message || '').trim();

    // 1. DEDUPLICATION: Check if identical toast is already displayed
    const existingToasts = Array.from(container.querySelectorAll('.toast:not(.toast-hiding)'));
    for (let i = 0; i < existingToasts.length; i++) {
      const existing = existingToasts[i];
      const existingText = existing.querySelector('.toast-text')?.textContent?.trim();
      if (existingText === cleanMsg) {
        // Shake existing toast and refresh its timer instead of duplicating
        existing.classList.remove('shake-error');
        void existing.offsetWidth; // trigger reflow
        existing.classList.add('shake-error');
        if (typeof existing._resetTimer === 'function') {
          existing._resetTimer(duration);
        }
        return existing;
      }
    }

    // 2. LIMIT ACTIVE TOASTS: Max 2 simultaneous toasts
    if (existingToasts.length >= MAX_ACTIVE_TOASTS) {
      const oldest = existingToasts[0];
      if (oldest && !oldest.classList.contains('toast-hiding')) {
        oldest.classList.add('toast-hiding');
        setTimeout(() => {
          if (oldest.parentNode) oldest.parentNode.removeChild(oldest);
        }, 260);
      }
    }

    // 3. Create new toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('data-auto-dismiss', duration);

    let iconClass = 'bi-check-circle-fill';
    let defaultTitle = 'Succès';
    if (type === 'danger' || type === 'error') {
      iconClass = 'bi-exclamation-circle-fill';
      defaultTitle = 'Erreur';
    } else if (type === 'warning') {
      iconClass = 'bi-exclamation-triangle-fill';
      defaultTitle = 'Attention';
    } else if (type === 'info') {
      iconClass = 'bi-info-circle-fill';
      defaultTitle = 'Information';
    }

    toast.innerHTML = `
      <i class="bi ${iconClass} toast-icon"></i>
      <div class="toast-content">
        <div class="toast-title">${title || defaultTitle}</div>
        <div class="toast-text">${message}</div>
      </div>
      <button type="button" class="toast-close" aria-label="Fermer la notification">✕</button>
      <div class="toast-progress-bar" style="animation-duration: ${duration}ms;"></div>
    `;

    container.appendChild(toast);
    setupToast(toast);
    return toast;
  };

  // ============================================================
  // Form Validation Engine (Client-Side)
  // ============================================================
  function validateContactForm(form) {
    const errors = [];

    // 1. Name validation
    const nameEl = form.querySelector('[name="name"]');
    if (nameEl) {
      const val = nameEl.value.trim();
      if (val === '') {
        errors.push({ el: nameEl, msg: 'Le nom complet est obligatoire.' });
      } else if (val.length < 2 || val.length > 100) {
        errors.push({ el: nameEl, msg: 'Le nom complet doit comporter entre 2 et 100 caractères.' });
      } else if (!/^[\p{L}\p{M}\s\-\.'’]{2,100}$/u.test(val)) {
        errors.push({ el: nameEl, msg: 'Le nom complet contient des caractères non autorisés.' });
      }
    }

    // 2. Email validation
    const emailEl = form.querySelector('[name="email"]');
    if (emailEl) {
      const val = emailEl.value.trim();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (val === '') {
        errors.push({ el: emailEl, msg: "L'adresse email est obligatoire." });
      } else if (val.length > 180 || !emailRegex.test(val)) {
        errors.push({ el: emailEl, msg: 'Veuillez indiquer une adresse email valide (ex. jean@exemple.fr).' });
      }
    }

    // 3. Phone validation (optional)
    const phoneEl = form.querySelector('[name="phone"]');
    if (phoneEl && phoneEl.value.trim() !== '') {
      const val = phoneEl.value.trim();
      if (val.length > 30 || !/^(\+?[0-9\s\-\.\(\)]{6,25})$/.test(val)) {
        errors.push({ el: phoneEl, msg: "Le numéro de téléphone n'est pas dans un format valide (ex. +33 6 12 34 56 78)." });
      }
    }

    // 4. Company validation (optional)
    const companyEl = form.querySelector('[name="company"]');
    if (companyEl && companyEl.value.trim().length > 120) {
      errors.push({ el: companyEl, msg: "Le nom de l'entreprise ne doit pas dépasser 120 caractères." });
    }

    // 5. Message validation
    const messageEl = form.querySelector('[name="message"]');
    if (messageEl) {
      const val = messageEl.value.trim();
      if (val === '') {
        errors.push({ el: messageEl, msg: 'Votre message est obligatoire.' });
      } else if (val.length < 10 || val.length > 4000) {
        errors.push({ el: messageEl, msg: 'Votre message doit comporter entre 10 et 4 000 caractères.' });
      }
    }

    // 6. Turnstile anti-bot verification check
    const turnstileWrapper = form.querySelector('.cf-turnstile');
    if (turnstileWrapper) {
      const turnstileInput = form.querySelector('[name="cf-turnstile-response"]');
      if (!turnstileInput || !turnstileInput.value || turnstileInput.value.trim() === '') {
        errors.push({ el: turnstileWrapper, msg: 'Veuillez valider la case anti-robot (Cloudflare Turnstile) avant d\'envoyer.' });
      }
    }

    return errors;
  }

  function validateGenericForm(form) {
    const errors = [];
    const requiredInputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    requiredInputs.forEach(input => {
      const val = input.value.trim();
      const label = form.querySelector(`label[for="${input.id}"]`)?.textContent?.replace('*', '').trim() || input.name;
      if (val === '') {
        errors.push({ el: input, msg: `Le champ « ${label} » est obligatoire.` });
      } else if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
        errors.push({ el: input, msg: 'Veuillez indiquer une adresse email valide.' });
      }
    });
    return errors;
  }

  function handleFormValidationAndSubmit(form, submitBtn, e) {
    // Clear existing error states
    form.querySelectorAll('.is-invalid, .shake-error').forEach(el => {
      el.classList.remove('is-invalid', 'shake-error');
    });

    let errors = [];
    if (form.id === 'contact-form' || form.getAttribute('action')?.includes('/contact')) {
      errors = validateContactForm(form);
    } else {
      errors = validateGenericForm(form);
    }

    if (errors.length > 0) {
      if (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
      }

      // Highlight all invalid fields
      errors.forEach(err => {
        if (err.el) {
          err.el.classList.add('is-invalid', 'shake-error');
          const removeErr = () => {
            err.el.classList.remove('is-invalid', 'shake-error');
          };
          err.el.addEventListener('input', removeErr, { once: true });
          err.el.addEventListener('change', removeErr, { once: true });
        }
      });

      // Display the primary error in a floating toast (deduplicated & capped)
      window.showToast(errors[0].msg, 'danger', 7500);

      // Focus the first invalid field
      if (errors[0].el && typeof errors[0].el.focus === 'function') {
        errors[0].el.focus();
      }
      return false;
    }

    // All inputs valid -> activate loader and allow submission
    if (submitBtn) {
      activateButtonLoader(submitBtn);
    }
    return true;
  }

  // ============================================================
  // Global Form Submission Loader & Button Disabling
  // ============================================================
  function activateButtonLoader(submitBtn) {
    if (!submitBtn || submitBtn.dataset.submitting === 'true') return;
    submitBtn.dataset.submitting = 'true';

    // Store dimensions and original content
    const originalHtml = submitBtn.innerHTML;
    const btnWidth = submitBtn.offsetWidth;
    if (btnWidth > 0) {
      submitBtn.style.minWidth = btnWidth + 'px';
    }

    // Add loading styles immediately
    submitBtn.classList.add('loading', 'disabled');
    submitBtn.style.pointerEvents = 'none';

    const loadingText = submitBtn.getAttribute('data-loading-text') || 'Envoi en cours...';
    submitBtn.innerHTML = `<span class="btn-spinner" aria-hidden="true"></span> ${loadingText}`;

    // Disable button slightly after event cycle so form submit isn't canceled
    setTimeout(() => {
      if (submitBtn) submitBtn.disabled = true;
    }, 15);

    // Safeguard reset if page does not reload within 25 seconds
    setTimeout(() => {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('loading', 'disabled');
        submitBtn.style.pointerEvents = '';
        submitBtn.dataset.submitting = 'false';
        submitBtn.innerHTML = originalHtml;
      }
    }, 25000);
  }

  // Single submit listener for all forms (handles button click, Enter key, and form dispatch)
  document.addEventListener('submit', function(e) {
    const form = e.target;
    if (!form || form.tagName !== 'FORM') return;

    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"], #contact-submit');
    const isValid = handleFormValidationAndSubmit(form, submitBtn, e);
    if (!isValid) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  });

  // --- Alert Dismissals (Legacy) ---
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.alert-dismiss-btn, .alert button[aria-label="Fermer"]');
    if (btn) {
      const alert = btn.closest('.alert');
      if (alert) alert.remove();
    }
  });
});

