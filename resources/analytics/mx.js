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

  function readUtm() {
    var keys = ['source', 'medium', 'campaign', 'term', 'content'];
    var params;
    try {
      params = new URLSearchParams(location.search);
    } catch (e) {
      return null;
    }
    var utm = null;
    for (var i = 0; i < keys.length; i++) {
      var v = params.get('utm_' + keys[i]);
      if (v) {
        if (!utm) utm = {};
        utm[keys[i]] = v.slice(0, 255);
      }
    }
    return utm; // null when no utm_* present
  }

  var utmParams = readUtm();

  var siteConfig = null;

  function send(visitorId) {
    var payload = {
      site: site,
      path: location.pathname,
      referrer: document.referrer || null,
    };
    if (visitorId) payload.visitor_id = visitorId;
    if (utmParams) payload.utm = utmParams;

    // Cross-origin transport, deliberately non-credentialed:
    //  - Content-Type text/plain keeps this a CORS "simple request", so the
    //    browser sends it WITHOUT a preflight (faster, and no OPTIONS to fail).
    //  - credentials 'omit' means no cookies are sent to our origin, so the
    //    server's wildcard Access-Control-Allow-Origin '*' is valid for ANY
    //    customer domain. (sendBeacon can't do this — it always sends in
    //    credentials 'include' mode, which forbids the '*' wildcard.)
    // We never need cookies on our origin: the visitor id travels in the body.
    fetch(origin + '/a/event', {
      method: 'POST',
      headers: { 'Content-Type': 'text/plain' },
      body: JSON.stringify(payload),
      keepalive: true,
      credentials: 'omit',
      mode: 'cors',
    }).catch(function () {
      /* network error: drop the hit silently */
    });
  }

  function postEvent(name, props, visitorId) {
    var payload = { site: site, type: 'event', name: name, path: location.pathname };
    if (props) payload.props = props;
    if (visitorId) payload.visitor_id = visitorId;

    fetch(origin + '/a/event', {
      method: 'POST',
      headers: { 'Content-Type': 'text/plain' },
      body: JSON.stringify(payload),
      keepalive: true,
      credentials: 'omit',
      mode: 'cors',
    }).catch(function () {});
  }

  function firstPartyId() {
    var vid = getCookie(VID_COOKIE);
    if (!vid) {
      vid = randomId();
      setCookie(VID_COOKIE, vid, 365);
    }
    return vid;
  }

  function sendEvent(name, props) {
    if (!siteConfig) return; // config not yet loaded (queued calls flush after load)
    if (props) {
      try {
        if (JSON.stringify(props).length > 1024) props = null;
      } catch (e) {
        props = null;
      }
    }
    if (siteConfig.tracking_mode !== 'cookie') {
      postEvent(name, props, null);
      return;
    }
    if (siteConfig.consent_mode === 'third_party_signal' && !consentGranted(siteConfig.consent_signal)) {
      return; // no consent, no event
    }
    postEvent(name, props, firstPartyId());
  }

  function installMarketix() {
    var queued = (window.marketix && window.marketix.q) || [];
    window.marketix = function (cmd) {
      if (cmd === 'event') {
        var name = arguments[1];
        if (name) sendEvent(String(name), arguments[2] || null);
      }
    };
    for (var i = 0; i < queued.length; i++) {
      window.marketix.apply(null, queued[i]);
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

  fetch(origin + '/a/config/' + encodeURIComponent(site), { credentials: 'omit', mode: 'cors' })
    .then(function (r) {
      if (!r.ok) throw new Error('config');
      return r.json();
    })
    .then(function (config) {
      siteConfig = config;
      track(config);
      installMarketix();
    })
    .catch(function () {
      /* unknown site or network error: do nothing */
    });
})();
