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
  var DOWNLOAD_EXTENSIONS =
    /\.(pdf|xlsx?|docx?|pptx?|csv|txt|rtf|odt|ods|odp|zip|rar|7z|gz|tar|dmg|exe|msi|pkg|apk|mp3|wav|mp4|mov|avi|mkv|epub|key|numbers|pages)$/i;

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

  var siteConfig = null;
  var pageviewSent = false;
  var currentPath = null;
  var engagedMs = 0;
  var visibleSince = null;
  var maxScroll = 0;
  var sentScroll = 0;

  // Cross-origin transport, deliberately non-credentialed:
  //  - Content-Type text/plain keeps this a CORS "simple request", so the
  //    browser sends it WITHOUT a preflight (faster, and no OPTIONS to fail).
  //  - credentials 'omit' means no cookies are sent to our origin, so the
  //    server's wildcard Access-Control-Allow-Origin '*' is valid for ANY
  //    customer domain. (sendBeacon can't do this — it always sends in
  //    credentials 'include' mode, which forbids the '*' wildcard.)
  // We never need cookies on our origin: the visitor id travels in the body.
  function transport(payload) {
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

  function send(visitorId, referrer) {
    var payload = {
      site: site,
      path: location.pathname,
      referrer: referrer || null,
    };
    var utm = readUtm();
    if (visitorId) payload.visitor_id = visitorId;
    if (utm) payload.utm = utm;
    transport(payload);
  }

  function postEvent(name, props, visitorId) {
    var payload = { site: site, type: 'event', name: name, path: location.pathname };
    if (props) payload.props = props;
    if (visitorId) payload.visitor_id = visitorId;
    transport(payload);
  }

  function firstPartyId() {
    var vid = getCookie(VID_COOKIE);
    if (!vid) {
      vid = randomId();
      setCookie(VID_COOKIE, vid, 365);
    }
    return vid;
  }

  function visitorId() {
    return siteConfig && siteConfig.tracking_mode === 'cookie' ? firstPartyId() : null;
  }

  var pendingEvents = [];
  var consentListenerInstalled = false;

  function flushPendingEvents() {
    if (!siteConfig || !consentGranted(siteConfig.consent_signal)) return;
    var queued = pendingEvents;
    pendingEvents = [];
    for (var i = 0; i < queued.length; i++) {
      postEvent(queued[i].name, queued[i].props, firstPartyId());
    }
  }

  function sendEvent(name, props) {
    if (!siteConfig) return;
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

    // own_banner is reserved for v1.1 and has no consent gate implemented — never track.
    if (siteConfig.consent_mode === 'own_banner') {
      return;
    }

    if (siteConfig.consent_mode === 'third_party_signal' && !consentGranted(siteConfig.consent_signal)) {
      // Buffer until the site's CMP dispatches 'marketix:consent'; then replay.
      pendingEvents.push({ name: name, props: props });
      if (!consentListenerInstalled) {
        consentListenerInstalled = true;
        window.addEventListener('marketix:consent', flushPendingEvents);
      }
      return;
    }

    postEvent(name, props, firstPartyId());
  }

  function installMarketix() {
    var queued = (window.marketix && window.marketix.q) || [];
    window.marketix = function (cmd) {
      if (cmd === 'event') {
        var name = arguments[1];
        if (name) sendEvent(String(name), arguments[2] || null);
      } else if (cmd === '404') {
        sendEvent('not_found', null);
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

  function trackingAllowed() {
    if (!siteConfig) return false;
    // cookieless: server derives a daily hash from IP + UA
    if (siteConfig.tracking_mode !== 'cookie') return true;
    if (siteConfig.consent_mode === 'immediate') return true;
    if (siteConfig.consent_mode === 'third_party_signal') return consentGranted(siteConfig.consent_signal);
    // own_banner: not selectable via the UI yet (v1.1) — no-op.
    return false;
  }

  function scrollDepth() {
    var doc = document.documentElement;
    var height = Math.max(doc.scrollHeight, document.body ? document.body.scrollHeight : 0);
    var viewport = window.innerHeight || doc.clientHeight;
    if (!height || height <= viewport) return 100;
    var top = window.pageYOffset || doc.scrollTop || 0;
    return Math.min(100, Math.round(((top + viewport) / height) * 100));
  }

  function pauseEngagement() {
    if (visibleSince !== null) {
      engagedMs += Date.now() - visibleSince;
      visibleSince = null;
    }
  }

  function flushEngagement() {
    if (!pageviewSent || currentPath === null) return;
    pauseEngagement();
    if (engagedMs < 1000 && maxScroll <= sentScroll) return;

    var payload = {
      site: site,
      type: 'engagement',
      path: currentPath,
      engaged_ms: Math.min(engagedMs, 86400000),
      scroll: maxScroll,
    };
    var id = visitorId();
    if (id) payload.visitor_id = id;
    transport(payload);

    engagedMs = 0;
    sentScroll = maxScroll;
  }

  function trackSiteSearch() {
    var params = siteConfig.search_params;
    if (!params || !params.length) return;
    var query;
    try {
      query = new URLSearchParams(location.search);
    } catch (e) {
      return;
    }
    for (var i = 0; i < params.length; i++) {
      var term = query.get(params[i]);
      if (term && term.trim()) {
        sendEvent('site_search', { term: term.trim().toLowerCase().slice(0, 100) });
        return;
      }
    }
  }

  function trackPageview(referrer) {
    if (!trackingAllowed()) return;
    flushEngagement();

    currentPath = location.pathname;
    engagedMs = 0;
    maxScroll = scrollDepth();
    sentScroll = 0;
    visibleSince = document.visibilityState === 'visible' ? Date.now() : null;
    pageviewSent = true;

    send(visitorId(), referrer);
    trackSiteSearch();
  }

  function onNavigate() {
    if (location.pathname === currentPath) return;
    trackPageview(currentPath === null ? document.referrer : location.origin + currentPath);
  }

  function patchHistory(method) {
    var original = history[method];
    if (typeof original !== 'function') return;
    history[method] = function () {
      var result = original.apply(this, arguments);
      onNavigate();
      return result;
    };
  }

  var scrollQueued = false;

  function measureScroll() {
    scrollQueued = false;
    var depth = scrollDepth();
    if (depth > maxScroll) maxScroll = depth;
  }

  function onScroll() {
    if (scrollQueued) return;
    scrollQueued = true;
    if (window.requestAnimationFrame) {
      window.requestAnimationFrame(measureScroll);
    } else {
      setTimeout(measureScroll, 100);
    }
  }

  function onLinkClick(event) {
    if (!siteConfig || (!siteConfig.outbound_links && !siteConfig.file_downloads)) return;
    if (event.type === 'auxclick' && event.button !== 1) return;

    var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!link) return;

    var url;
    try {
      url = new URL(link.href, location.href);
    } catch (e) {
      return;
    }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;

    var target = url.host + url.pathname;
    if (siteConfig.file_downloads && (link.hasAttribute('download') || DOWNLOAD_EXTENSIONS.test(url.pathname))) {
      sendEvent('file_download', { url: target });
    } else if (siteConfig.outbound_links && url.host !== location.host) {
      sendEvent('outbound_click', { url: target });
    }
  }

  function installListeners() {
    patchHistory('pushState');
    patchHistory('replaceState');
    window.addEventListener('popstate', onNavigate);
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('pagehide', flushEngagement);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        flushEngagement();
      } else if (pageviewSent && visibleSince === null) {
        visibleSince = Date.now();
      }
    });
    document.addEventListener('click', onLinkClick, true);
    document.addEventListener('auxclick', onLinkClick, true);
  }

  function start() {
    if (trackingAllowed()) {
      trackPageview(document.referrer);
      return;
    }

    if (siteConfig.tracking_mode === 'cookie' && siteConfig.consent_mode === 'third_party_signal') {
      // Re-attempt whenever the site's CMP signals a consent change by
      // dispatching a 'marketix:consent' event on window.
      window.addEventListener('marketix:consent', function () {
        if (!pageviewSent) trackPageview(document.referrer);
      });
    }
  }

  fetch(origin + '/a/config/' + encodeURIComponent(site), { credentials: 'omit', mode: 'cors' })
    .then(function (r) {
      if (!r.ok) throw new Error('config');
      return r.json();
    })
    .then(function (config) {
      siteConfig = config;
      installListeners();
      start();
      installMarketix();
    })
    .catch(function () {
      /* unknown site or network error: do nothing */
    });
})();
