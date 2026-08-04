(function () {
  'use strict';

  var script = document.currentScript;
  if (!script) return;

  var site = script.getAttribute('data-site');
  if (!site) return;

  // Derive our origin from the script's own src.
  var origin;
  try {
    origin = new URL(script.src).origin;
  } catch (e) {
    return;
  }

  var VID_COOKIE = 'mx_vid';

  function getCookie(name) {
    var m = document.cookie.match('(?:^|;)\\s*' + name + '=([^;]*)');
    return m ? decodeURIComponent(m[1]) : null;
  }

  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + days * 864e5);
    document.cookie =
      name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }

  function randomId() {
    if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
    return String(Date.now()) + '-' + Math.floor(Math.random() * 1e9);
  }

  function send(visitorId) {
    var payload = {
      site: site,
      path: location.pathname,
      referrer: document.referrer || null,
    };
    if (visitorId) payload.visitor_id = visitorId;

    var body = JSON.stringify(payload);
    var url = origin + '/a/event';

    if (navigator.sendBeacon) {
      navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
    } else {
      fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true });
    }
  }

  function consentGranted(signalName) {
    // A truthy global named by the site's consent_signal indicates consent.
    // Supports both a boolean/function global (e.g. window.UC_UI) and a
    // simple flag like window.marketixConsent === true.
    if (!signalName) return false;
    var v = window[signalName];
    return v === true || (v && typeof v === 'object') || (v && typeof v === 'function');
  }

  function track(config) {
    if (config.tracking_mode === 'cookie') {
      if (config.consent_mode === 'third_party_signal') {
        if (!consentGranted(config.consent_signal)) return; // no consent, no tracking
      }
      // immediate (and granted third_party_signal): set/reuse first-party id
      var vid = getCookie(VID_COOKIE);
      if (!vid) {
        vid = randomId();
        setCookie(VID_COOKIE, vid, 365);
      }
      send(vid);
    } else {
      // cookieless: server derives a daily hash from IP + UA
      send(null);
    }
  }

  fetch(origin + '/a/config/' + encodeURIComponent(site))
    .then(function (r) {
      if (!r.ok) throw new Error('config');
      return r.json();
    })
    .then(track)
    .catch(function () {
      /* unknown site or network error: do nothing */
    });
})();
