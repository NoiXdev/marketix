<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting…</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }
        .wrap { text-align: center; }
        .spinner {
            width: 40px; height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #4f46e5;
            border-radius: 50%;
            animation: spin .75s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        p { font-size: .875rem; }
    </style>

    {{-- ── Pixel scripts ──────────────────────────────────────── --}}
    @foreach ($pixels as $pixel)
        @switch($pixel->provider->value)

            {{-- Google Tag Manager --}}
            @case('google_tag_manager')
                <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $pixel->tag }}');</script>
            @break

            {{-- Google Analytics --}}
            @case('google_analytics')
                <script async src="https://www.googletagmanager.com/gtag/js?id={{ $pixel->tag }}"></script>
                <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $pixel->tag }}');</script>
            @break

            {{-- Facebook --}}
            @case('facebook')
                <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $pixel->tag }}');fbq('track','PageView');</script>
                <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $pixel->tag }}&ev=PageView&noscript=1" /></noscript>
            @break

            {{-- Google Ads --}}
            @case('google_ads')
                <script async src="https://www.googletagmanager.com/gtag/js?id={{ $pixel->tag }}"></script>
                <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $pixel->tag }}');</script>
            @break

            {{-- LinkedIn --}}
            @case('linkedin')
                <script>_linkedin_partner_id="{{ $pixel->tag }}";window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(_linkedin_partner_id);(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName("script")[0];var b=document.createElement("script");b.type="text/javascript";b.async=true;b.src="https://snap.licdn.com/li.lms-analytics/insight.min.js";s.parentNode.insertBefore(b,s)})(window.lintrk);</script>
                <noscript><img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid={{ $pixel->tag }}&fmt=gif" /></noscript>
            @break

            {{-- Twitter / X --}}
            @case('twitter')
                <script>!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments)},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');twq('config','{{ $pixel->tag }}');</script>
            @break

            {{-- AdRoll --}}
            @case('adroll')
                <script>window.adroll_adv_id="{{ $pixel->tag }}";(function(w,d,e,o,a){w.__adroll_loaded||(w.__adroll_loaded=!0,a=d.createElement('script'),a.async=!0,a.src='https://s.adroll.com/j/roundtrip.js',o=d.getElementsByTagName('script')[0],o.parentNode.insertBefore(a,o))})(window,document);</script>
            @break

            {{-- Quora --}}
            @case('quora')
                <script>!function(q,e,v,n,t,s){if(q.qp)return;n=q.qp=function(){n.qp?n.qp.apply(n,arguments):n.queue.push(arguments)};n.queue=[];t=document.createElement(e);t.async=!0;t.src=v;s=document.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,'script','https://a.quora.com/qevents.js');qp('init','{{ $pixel->tag }}');qp('track','ViewContent');</script>
                <noscript><img height="1" width="1" style="display:none" src="https://q.quora.com/_/ad/{{ $pixel->tag }}/pixel?tag=ViewContent&noscript=1" /></noscript>
            @break

            {{-- Pinterest --}}
            @case('pinterest')
                <script>!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version='3.0';var t=document.createElement('script');t.async=!0,t.src=e;var r=document.getElementsByTagName('script')[0];r.parentNode.insertBefore(t,r)}}('https://s.pinimg.com/ct/core.js');pintrk('load','{{ $pixel->tag }}',{em:'<user_email_address>'});pintrk('page');</script>
                <noscript><img height="1" width="1" style="display:none;" alt="" src="https://ct.pinterest.com/v3/?event=init&tid={{ $pixel->tag }}&ac=none&noscript=1" /></noscript>
            @break

            {{-- Bing --}}
            @case('bing')
                <script>(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:'{{ $pixel->tag }}'};o.q=w[u],w[u]=new UET(o),w[u].push('pageLoad')},n=d.createElement(t),n.src=r,n.async=1,n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!=='loaded'&&s!=='complete'||(f(),n.onload=n.onreadystatechange=null)},i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})(window,document,'script','https://bat.bing.com/bat.js','uetq');</script>
                <noscript><img src="https://bat.bing.com/action/0?ti={{ $pixel->tag }}&Ver=2&mid=&evt=nojs&fo=0" height="0" width="0" style="display:none; visibility: hidden;" /></noscript>
            @break

            {{-- Snapchat --}}
            @case('snapchat')
                <script>(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script';r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u);})(window,document,'https://sc-static.net/scevent.min.js');snaptr('init','{{ $pixel->tag }}',{'user_email':''});snaptr('track','PAGE_VIEW');</script>
            @break

            {{-- Reddit --}}
            @case('reddit')
                <script>!function(w,d){if(!w.rdt){var p=w.rdt=function(){p.sendEvent?p.sendEvent.apply(p,arguments):p.callQueue.push(arguments)};p.callQueue=[];var t=d.createElement('script');t.src='https://www.redditstatic.com/ads/v2.js',t.async=!0;var s=d.getElementsByTagName('script')[0];s.parentNode.insertBefore(t,s)}}(window,document);rdt('init','{{ $pixel->tag }}');rdt('track','PageVisit');</script>
                <noscript><img src="https://alb.reddit.com/snoo.gif?q=CAAHAAABAAoACQAAAAAAAAAAAAoACQAAAAAAAAAAAA==&s=PAAAP-NomhpgDXAl1CQdHiqFqFqfkfqy-9HA-G0-wY0=" height="1" width="1" alt="" /></noscript>
            @break

            {{-- TikTok --}}
            @case('tiktok')
                <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement('script');o.type='text/javascript',o.async=!0,o.src=i+'?sdkid='+e+'&lib='+t;var a=document.getElementsByTagName('script')[0];a.parentNode.insertBefore(o,a)};ttq.load('{{ $pixel->tag }}');ttq.page();}(window,document,'ttq');</script>
            @break

        @endswitch
    @endforeach
</head>
<body>
    <div class="wrap">
        <div class="spinner"></div>
        <p>Redirecting…</p>
    </div>
    <script>
        setTimeout(function () {
            window.location.replace({{ Js::from($targetUrl) }});
        }, 2000);
    </script>
</body>
</html>
