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
    var secure = location.protocol === 'https:' ? ';Secure' : '';
    document.cookie =
      name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax' + secure;
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
    // Strict contract: consent is granted only when the site owner's CMP has
    // set window[signalName] === true. No object/function truthiness — a CMP
    // script merely having loaded (e.g. window.UC_UI existing as an API
    // object) must NOT count as consent.
    if (!signalName) return false;
    return window[signalName] === true;
  }

  function trackWithFirstPartyId() {
    var vid = getCookie(VID_COOKIE);
    if (!vid) {
      vid = randomId();
      setCookie(VID_COOKIE, vid, 365);
    }
    send(vid);
  }

  function track(config) {
    if (config.tracking_mode !== 'cookie') {
      // cookieless: server derives a daily hash from IP + UA
      send(null);
      return;
    }

    if (config.consent_mode === 'immediate') {
      trackWithFirstPartyId();
      return;
    }

    if (config.consent_mode === 'third_party_signal') {
      var tracked = false;

      function attempt() {
        if (tracked) return;
        if (!consentGranted(config.consent_signal)) return;
        tracked = true;
        trackWithFirstPartyId();
      }

      // Attempt once immediately (consent may already be strictly granted),
      // then re-attempt whenever the site's CMP signals a consent change by
      // dispatching a 'marketix:consent' event on window.
      attempt();
      window.addEventListener('marketix:consent', attempt);
      return;
    }

    // own_banner: not selectable via the UI yet (v1.1) — no-op.
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
