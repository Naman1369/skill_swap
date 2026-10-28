/**
 * SkillSwap client-side behaviour.
 * Everything here is progressive enhancement: the site works without JavaScript,
 * and all validation is repeated on the server in PHP.
 */
(function () {
  'use strict';

  var THEME_KEY = 'skillswap-theme';
  var SEARCH_FOCUS_KEY = 'skillswap-refocus-search';

  function storageSet(key, value) {
    try { window.localStorage.setItem(key, value); } catch (e) { /* storage unavailable */ }
  }

  /* ---------- Theme toggle ---------- */
  function currentTheme() {
    var explicit = document.documentElement.getAttribute('data-theme');
    if (explicit) return explicit;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  document.querySelectorAll('.theme-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      storageSet(THEME_KEY, next);
    });
  });

  /* ---------- Mobile navigation ---------- */
  var navToggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (navToggle && nav) {
    navToggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.focus();
      }
    });
  }

  /* ---------- Flash messages ---------- */
  function hideFlash(flash) {
    flash.classList.add('is-hiding');
    window.setTimeout(function () { flash.remove(); }, 260);
  }
  document.querySelectorAll('.flash').forEach(function (flash) {
    var close = flash.querySelector('.flash__close');
    if (close) close.addEventListener('click', function () { hideFlash(flash); });
    if (flash.classList.contains('flash--success')) {
      window.setTimeout(function () { if (document.body.contains(flash)) hideFlash(flash); }, 6000);
    }
  });

  /* ---------- Confirmation dialogs ---------- */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  /* ---------- Password visibility ---------- */
  document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    var input = document.querySelector(button.getAttribute('data-toggle-password'));
    if (!input) return;
    var showIcon = button.innerHTML;
    var hideIcon = '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    button.addEventListener('click', function () {
      var reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      button.innerHTML = reveal ? hideIcon : showIcon;
      button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
  });

  /* ---------- Character counters ---------- */
  document.querySelectorAll('textarea[data-counter][maxlength]').forEach(function (textarea) {
    var max = parseInt(textarea.getAttribute('maxlength'), 10);
    var counter = document.createElement('p');
    counter.className = 'char-counter';
    counter.setAttribute('aria-live', 'polite');
    textarea.insertAdjacentElement('afterend', counter);
    function update() {
      var length = textarea.value.length;
      counter.textContent = length + ' / ' + max;
      counter.classList.toggle('is-near', length > max * 0.9);
    }
    textarea.addEventListener('input', update);
    update();
  });

  /* ---------- Avatar preview ---------- */
  // Photos are stored in the database, so the browser shrinks them to at most 512 px (JPEG) before upload.
  // If anything goes wrong the original file is sent and the server applies its own size limit.
  function shrinkImage(file, maxSide) {
    return new Promise(function (resolve) {
      var source = new Image();
      var url = URL.createObjectURL(file);
      source.onload = function () {
        URL.revokeObjectURL(url);
        var scale = Math.min(1, maxSide / Math.max(source.width, source.height));
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(source.width * scale));
        canvas.height = Math.max(1, Math.round(source.height * scale));
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff'; // JPEG has no transparency
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
          resolve(blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.85);
      };
      source.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      source.src = url;
    });
  }

  var avatarInput = document.querySelector('[data-avatar-input]');
  var avatarPreview = document.querySelector('[data-avatar-preview]');
  if (avatarInput && avatarPreview && window.URL) {
    avatarInput.addEventListener('change', function () {
      var file = avatarInput.files && avatarInput.files[0];
      if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;

      var canResize = window.DataTransfer && window.File && HTMLCanvasElement.prototype.toBlob;
      (canResize ? shrinkImage(file, 512) : Promise.resolve(file)).then(function (result) {
        if (result !== file) {
          try {
            var transfer = new DataTransfer();
            transfer.items.add(result);
            avatarInput.files = transfer.files;
          } catch (e) { result = file; }
        }
        var img = document.createElement('img');
        img.className = 'avatar avatar--xl';
        img.alt = 'New profile photo preview';
        img.src = URL.createObjectURL(result);
        avatarPreview.innerHTML = '';
        avatarPreview.appendChild(img);
      });
    });
  }

  /* ---------- Form validation ---------- */
  function errorElementFor(input) {
    var group = input.closest('.form-group');
    if (!group) return null;
    var el = group.querySelector('.field-error');
    if (!el) {
      el = document.createElement('p');
      el.className = 'field-error';
      el.id = (input.id || input.name) + '-error';
      el.hidden = true;
      group.appendChild(el);
    }
    return el;
  }

  function showError(input, message) {
    var el = errorElementFor(input);
    if (!el) return;
    el.textContent = message;
    el.hidden = !message;
    input.classList.toggle('is-invalid', !!message);
    if (message) {
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', el.id);
    } else {
      input.removeAttribute('aria-invalid');
    }
  }

  function validateField(input) {
    var isPassword = /password/.test(input.name);
    var value = isPassword ? input.value : input.value.trim();
    var label = input.getAttribute('data-label') || 'This field';

    if (input.required && !value) {
      return input.tagName === 'SELECT' ? 'Please choose a ' + label.toLowerCase() + '.' : label + ' is required.';
    }
    if (!value) return '';
    if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      return 'Please enter a valid email address.';
    }
    if (input.minLength > 0 && value.length < input.minLength) {
      return label + ' must be at least ' + input.minLength + ' characters.';
    }
    var pattern = input.getAttribute('data-pattern');
    if (pattern && !new RegExp(pattern).test(value)) {
      return input.getAttribute('data-pattern-message') || label + ' is not in the right format.';
    }
    var matchSelector = input.getAttribute('data-match');
    if (matchSelector) {
      var other = document.querySelector(matchSelector);
      if (other && other.value !== input.value) return 'Passwords do not match.';
    }
    return '';
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    var fields = Array.prototype.filter.call(
      form.querySelectorAll('input, select, textarea'),
      function (el) { return ['hidden', 'file', 'radio', 'checkbox', 'submit'].indexOf(el.type) === -1; }
    );

    fields.forEach(function (input) {
      input.addEventListener('blur', function () {
        if (input.value !== '' || input.classList.contains('is-invalid')) showError(input, validateField(input));
      });
      input.addEventListener('input', function () {
        if (input.classList.contains('is-invalid')) showError(input, validateField(input));
      });
    });

    form.addEventListener('submit', function (event) {
      var firstInvalid = null;
      fields.forEach(function (input) {
        var message = validateField(input);
        showError(input, message);
        if (message && !firstInvalid) firstInvalid = input;
      });
      if (firstInvalid) {
        event.preventDefault();
        firstInvalid.focus();
        return;
      }
      // Avoid accidental double submissions.
      var submit = form.querySelector('[type="submit"]');
      if (submit) {
        window.setTimeout(function () { submit.disabled = true; }, 0);
      }
    });
  });

  // Re-enable submit buttons when the user navigates back to a cached page.
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-validate] [type="submit"]').forEach(function (button) {
      button.disabled = false;
    });
  });

  /* ---------- Browse page: filters apply automatically ---------- */
  var filterForm = document.querySelector('form[data-autosubmit]');
  if (filterForm) {
    var search = filterForm.querySelector('input[type="search"]');
    var timer = null;

    filterForm.querySelectorAll('select').forEach(function (select) {
      select.addEventListener('change', function () { filterForm.submit(); });
    });

    if (search) {
      search.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          try { window.sessionStorage.setItem(SEARCH_FOCUS_KEY, '1'); } catch (e) { /* ignore */ }
          filterForm.submit();
        }, 600);
      });

      // Keep typing where you left off after the page reloads with new results.
      var refocus = null;
      try { refocus = window.sessionStorage.getItem(SEARCH_FOCUS_KEY); window.sessionStorage.removeItem(SEARCH_FOCUS_KEY); } catch (e) { /* ignore */ }
      if (refocus) {
        search.focus();
        var end = search.value.length;
        try { search.setSelectionRange(end, end); } catch (e) { /* not supported for search inputs in some browsers */ }
      }
    }
  }
})();
